<?php
declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;
use Throwable;

final class MarketingProcessor
{
    public function __construct(
        private PDO $db,
        private EmailHistory $history,
        private EmailSender $sender,
        private ActivityLogger $logger,
        private int $sendDelay = 11
    ) {}

    public function process(array $job): void
    {
        // Validate the required SQS payload fields.
        foreach ([
            'requestId',
            'campaignId',
            'customerId',
            'promoCode',
            'subject',
            'body'
        ] as $field) {
            if (
                !isset($job[$field]) ||
                !is_string($job[$field]) ||
                trim($job[$field]) === ''
            ) {
                throw new RuntimeException(
                    "Invalid or missing job field: $field"
                );
            }
        }

        if (!preg_match('/^[a-f0-9]{32}$/i', $job['requestId'])) {
            throw new RuntimeException('Invalid requestId format.');
        }

        $ids = [
            'requestId' => $job['requestId'],
            'campaignId' => $job['campaignId'],
            'customerId' => $job['customerId'],
        ];

        // TODO P1: Prevent duplicate email submissions.
        if ($this->history->isComplete(
            $job['campaignId'],
            $job['customerId']
        )) {
            $this->logger->record('duplicate_skipped', $ids);
            return;
        }

        // TODO M2: Record processing start.
        $this->logger->record('processing_started', $ids);

        try {
            // TODO P2: Look up the customer locally.
            $stmt = $this->db->prepare(
                "SELECT customer_id, full_name, email,
                        marketing_opt_in
                 FROM customers
                 WHERE customer_id = ?
                 LIMIT 1"
            );

            $stmt->execute([$job['customerId']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            // TODO P3: Skip missing customers.
            if (!$customer) {
                $this->history->save(
                    $job,
                    'SKIPPED',
                    reason: 'Customer not found'
                );

                $this->logger->record('skipped', $ids + [
                    'reason' => 'Customer not found'
                ]);

                return;
            }

            // TODO P3: Recheck current marketing consent.
            if ((int) $customer['marketing_opt_in'] !== 1) {
                $this->history->save(
                    $job,
                    'SKIPPED',
                    reason: 'Customer has not opted in'
                );

                $this->logger->record('skipped', $ids + [
                    'reason' => 'Customer has not opted in'
                ]);

                return;
            }

            // Respect the Mailtrap sending-rate limit.
            if ($this->sendDelay > 0) {
                sleep($this->sendDelay);
            }

            // TODO P4: Submit the email via Mailtrap.
            $messageId = $this->sender->send($customer, $job);

            // TODO P5: Save successful SMTP acceptance.
            $this->history->save(
                $job,
                'SENT',
                recipientEmail: $customer['email'],
                provider: 'Mailtrap',
                messageId: $messageId
            );

            // TODO M2: Log only after saving SENT.
            $this->logger->record('sent', $ids + [
                'messageId' => $messageId
            ]);

        } catch (Throwable $e) {
            // Record failure, then rethrow so SQS is not
            // acknowledged by the worker.
            $this->history->save(
                $job,
                'FAILED',
                reason: get_class($e) . ': ' . $e->getMessage()
            );

            throw $e;
        }
    }
}
