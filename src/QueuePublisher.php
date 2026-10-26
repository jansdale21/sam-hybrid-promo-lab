<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use PDO;
use RuntimeException;
use Throwable;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): void
    {
        $stmt = $this->db->query(
            "SELECT request_id, campaign_id, customer_id,
                    promo_code, subject, body
             FROM marketing_outbox
             WHERE status = 'PENDING'
             ORDER BY created_at, request_id"
        );

        $requests = $stmt->fetchAll();

        $update = $this->db->prepare(
            "UPDATE marketing_outbox
             SET status = 'QUEUED',
                 queued_at = ?,
                 sqs_message_id = ?
             WHERE request_id = ?
               AND status = 'PENDING'"
        );

        foreach ($requests as $request) {
            $job = [
                'requestId' => $request['request_id'],
                'campaignId' => $request['campaign_id'],
                'customerId' => $request['customer_id'],
                'promoCode' => $request['promo_code'],
                'subject' => $request['subject'],
                'body' => $request['body'],
            ];

            try {
                // TODO Q1: Publish to SQS
                $result = $this->sqs->sendMessage([
                    'QueueUrl' => $this->queueUrl,
                    'MessageBody' => json_encode(
                        $job,
                        JSON_THROW_ON_ERROR
                    ),
                ]);

                $messageId = $result->get('MessageId');

                if (!is_string($messageId) || $messageId === '') {
                    throw new RuntimeException(
                        'SQS did not return a MessageId.'
                    );
                }

                // TODO Q2: Update only after SQS acknowledgment
                $update->execute([
                    gmdate('Y-m-d H:i:s'),
                    $messageId,
                    $request['request_id'],
                ]);

                if ($update->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Outbox update did not affect exactly one row.'
                    );
                }

                // TODO M1: Record successful publication
                $this->logger->record('queued', [
                    'requestId' => $request['request_id'],
                    'campaignId' => $request['campaign_id'],
                    'customerId' => $request['customer_id'],
                    'sqsMessageId' => $messageId,
                ]);

                echo "Queued: {$request['request_id']}\n";
            } catch (Throwable $e) {
                $this->logger->record('enqueue_failed', [
                    'requestId' => $request['request_id'],
                    'campaignId' => $request['campaign_id'],
                    'customerId' => $request['customer_id'],
                    'reason' => get_class($e),
                ]);

                throw $e;
            }
        }
    }
}
