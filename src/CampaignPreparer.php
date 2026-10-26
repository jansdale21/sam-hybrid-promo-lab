<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;
use Throwable;

final class CampaignPreparer
{
    public function __construct(private PDO $db)
    {
    }

    public function prepare(string $campaignId, string $promoCode): void
    {
        if (trim($campaignId) === '' || trim($promoCode) === '') {
            throw new RuntimeException('Campaign ID and promo code are required.');
        }

        $this->db->beginTransaction();

        try {
            // TODO C3: Preserve an existing complete campaign.
            $countStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = ?'
            );
            $countStmt->execute([$campaignId]);
            $existing = (int) $countStmt->fetchColumn();

            if ($existing === 50) {
                $this->db->commit();
                echo "Campaign already prepared. Original 50 recipients preserved.\n";
                return;
            }

            if ($existing !== 0) {
                throw new RuntimeException(
                    "Incomplete campaign: found $existing outbox rows."
                );
            }

            // TODO C2: Randomly select 50 opted-in customers.
            $customers = $this->db->query(
                'SELECT customer_id
                 FROM customers
                 WHERE marketing_opt_in = 1
                 ORDER BY RAND()
                 LIMIT 50'
            )->fetchAll(PDO::FETCH_COLUMN);

            if (count($customers) !== 50) {
                throw new RuntimeException('Not enough eligible customers.');
            }

            $insert = $this->db->prepare(
                'INSERT INTO marketing_outbox
                 (request_id, campaign_id, customer_id, promo_code,
                  subject, body, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );

            foreach ($customers as $customerId) {
                $requestId = bin2hex(random_bytes(16));
                $subject = 'Laboratory promotion';
                $body = 'You have been selected to receive our promotional offer.';
                $createdAt = gmdate('Y-m-d H:i:s');

                // TODO C1: Supply the seven required values.
                $insert->execute([
                    $requestId,
                    $campaignId,
                    $customerId,
                    $promoCode,
                    $subject,
                    $body,
                    $createdAt,
                ]);
            }

            $this->db->commit();
            echo "Successfully prepared 50 campaign requests.\n";
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}