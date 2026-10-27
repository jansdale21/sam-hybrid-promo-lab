<?php

declare(strict_types=1);

namespace Lab;

use PDO;
use RuntimeException;

/** Local-only database setup and inspection. Does not access SQS or Mailtrap. */
final class LocalDatabaseTools
{
    private const TABLES = [
        'customers',
        'marketing_outbox',
        'sent_marketing_emails',
    ];

    public function __construct(private PDO $db)
    {
    }

    public function initializeSchema(string $schemaFile): void
    {
        $existing = [];
        $check = $this->db->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = ?'
        );

        foreach (self::TABLES as $table) {
            $check->execute([$table]);
            if ((int) $check->fetchColumn() > 0) {
                $existing[] = $table;
            }
        }

        if (count($existing) === count(self::TABLES)) {
            echo "All three tables already exist. No changes made.\n";
            return;
        }

        if ($existing !== []) {
            throw new RuntimeException(
                'Partial schema found (' . implode(', ', $existing) . '). '
                . 'No tables were changed. Review the existing schema first.'
            );
        }

        if (!is_file($schemaFile) || !is_readable($schemaFile)) {
            throw new RuntimeException('Cannot read schema file: ' . $schemaFile);
        }

        $schema = file_get_contents($schemaFile);
        if ($schema === false) {
            throw new RuntimeException('Unable to load schema file.');
        }

        // Extract only CREATE TABLE definitions from the project's SQL export.
        // Never run DROP, INSERT, DELETE, or other statements from the export.
        preg_match_all(
            '~CREATE\s+TABLE\s+`?([a-z_]+)`?\s*\(.*?\)\s*ENGINE\s*=\s*InnoDB[^;]*;~is',
            $schema,
            $matches,
            PREG_SET_ORDER
        );

        $statements = [];
        foreach ($matches as $match) {
            $statements[$match[1]] = $match[0];
        }

        foreach (self::TABLES as $table) {
            if (!isset($statements[$table])) {
                throw new RuntimeException('Expected CREATE TABLE missing: ' . $table);
            }
        }

        // Create in dependency order (customers first, then its referencing outbox).
        foreach (self::TABLES as $table) {
            $this->db->exec($statements[$table]);
            echo $table . ": created\n";
        }

        echo "Schema initialized. No customer records generated.\n";
    }

    public function inspect(): void
    {
        $counts = $this->db->query(
            'SELECT COUNT(*) AS total, '
            . 'SUM(marketing_opt_in = 1) AS opted_in, '
            . 'SUM(marketing_opt_in = 0) AS opted_out '
            . 'FROM customers'
        )->fetch(PDO::FETCH_ASSOC);

        echo "=== Customers ===\n";
        printf("Total: %d | Opted in: %d | Opted out: %d\n",
            (int) $counts['total'],
            (int) $counts['opted_in'],
            (int) $counts['opted_out']
        );

        echo "\n=== Sample customers (fictional, first 10) ===\n";
        $sample = $this->db->query(
            'SELECT customer_id, full_name, email, marketing_opt_in '
            . 'FROM customers ORDER BY customer_id LIMIT 10'
        );
        foreach ($sample as $row) {
            printf("%s | %s | %s | opted_in=%d\n",
                $row['customer_id'],
                $row['full_name'],
                $row['email'],
                (int) $row['marketing_opt_in']
            );
        }

        echo "\n=== CAMP-001 outbox statuses ===\n";
        $outbox = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM marketing_outbox '
            . 'WHERE campaign_id = ? GROUP BY status ORDER BY status'
        );
        $outbox->execute(['CAMP-001']);
        foreach ($outbox as $row) {
            printf("%s: %d\n", $row['status'], (int) $row['total']);
        }

        echo "\n=== CAMP-001 processing outcomes ===\n";
        $history = $this->db->prepare(
            'SELECT status, COUNT(*) AS total FROM sent_marketing_emails '
            . 'WHERE campaign_id = ? GROUP BY status ORDER BY status'
        );
        $history->execute(['CAMP-001']);
        foreach ($history as $row) {
            printf("%s: %d\n", $row['status'], (int) $row['total']);
        }
    }
}
