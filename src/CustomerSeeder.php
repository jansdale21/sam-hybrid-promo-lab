<?php

declare(strict_types=1);

namespace Lab;

use Faker\Generator;
use PDO;
use Throwable;

final class CustomerSeeder
{
    public function __construct(
        private PDO $db,
        private Generator $faker
    ) {}

    public function seed(): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO customers
                (customer_id, full_name, email, marketing_opt_in, created_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                email = VALUES(email),
                marketing_opt_in = VALUES(marketing_opt_in)'
        );

        $this->db->beginTransaction();

        try {
            for ($i = 1; $i <= 10000; $i++) {
                $customerId = sprintf('CUST-%05d', $i);
                $email = sprintf('customer%05d@example.com', $i);

                // TODO S1: Generate a fictional name
                $name = $this->faker->name();

                // TODO S2: Assign marketing consent
                $consent = $i <= 8000 ? 1 : 0;

                $stmt->execute([
                    $customerId,
                    $name,
                    $email,
                    $consent,
                    gmdate('Y-m-d H:i:s'),
                ]);
            }

            $this->db->commit();

            echo "Successfully seeded 10,000 customers.\n";
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}