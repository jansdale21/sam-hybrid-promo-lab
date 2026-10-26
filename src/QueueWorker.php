<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;
use Throwable;

final class QueueWorker
{
    public function __construct(
        private SqsClient $sqs,
        private string $queueUrl,
        private MarketingProcessor $processor,
        private ActivityLogger $logger
    ) {}

    public function run(): void
    {
        echo "Queue worker started. Press Ctrl+C to stop.\n";

        while (true) {
            try {
                $result = $this->sqs->receiveMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MaxNumberOfMessages' => 1,
                    'WaitTimeSeconds' => 20,
                    'MessageSystemAttributeNames' => ['All'],
                ]);

                foreach ($result->get('Messages') ?? [] as $message) {
                    $this->handle($message);
                }
            } catch (Throwable $e) {
                echo "Worker error: {$e->getMessage()}\n";
                sleep(3);
            }
        }
    }

    private function handle(array $message): void
    {
        $sqsMessageId = (string) ($message['MessageId'] ?? '');
        $receiptHandle = (string) ($message['ReceiptHandle'] ?? '');

        $this->logger->record('received', [
            'sqsMessageId' => $sqsMessageId,
        ]);

        $phase = 'decode';

        try {
            $job = json_decode(
                (string) ($message['Body'] ?? ''),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($job)) {
                throw new RuntimeException(
                    'SQS message body must be a JSON object.'
                );
            }

            // TODO W1: Process the job.
            $phase = 'process';
            $this->processor->process($job);

            // TODO M3: Change phase before acknowledgment.
            $phase = 'delete';

            if ($receiptHandle === '') {
                throw new RuntimeException(
                    'Missing SQS ReceiptHandle.'
                );
            }

            // TODO W2: Delete only after successful processing.
            $this->sqs->deleteMessage([
                'QueueUrl' => $this->queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);

            // TODO M3: Log only after successful deletion.
            $this->logger->record('deleted', [
                'sqsMessageId' => $sqsMessageId,
                'requestId' => $job['requestId'] ?? null,
                'campaignId' => $job['campaignId'] ?? null,
                'customerId' => $job['customerId'] ?? null,
            ]);

            echo "Processed and deleted: $sqsMessageId\n";

        } catch (Throwable $e) {
            $this->logger->record(
                $phase === 'delete'
                    ? 'ack_failed'
                    : 'processing_failed',
                [
                    'sqsMessageId' => $sqsMessageId,
                    'phase' => $phase,
                    'reason' => get_class($e),
                ]
            );

            echo "Message failed ($phase): {$e->getMessage()}\n";

            // Do not delete failed messages.
            // SQS will retry them after the visibility timeout.
        }
    }
}
