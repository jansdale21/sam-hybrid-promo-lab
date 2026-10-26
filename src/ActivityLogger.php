<?php
declare(strict_types=1);

namespace Lab;

use RuntimeException;

final class ActivityLogger
{
    public function record(string $event, array $metadata = []): void
    {
        $directory = dirname(__DIR__) . '/logs';

        if (!is_dir($directory) &&
            !mkdir($directory, 0775, true) &&
            !is_dir($directory)) {
            throw new RuntimeException('Cannot create log directory.');
        }

        $entry = array_merge([
            'timestamp' => gmdate('c'),
            'event' => $event,
        ], $metadata);

        $json = json_encode($entry, JSON_THROW_ON_ERROR);

        if (file_put_contents(
            $directory . '/events.jsonl',
            $json . PHP_EOL,
            FILE_APPEND | LOCK_EX
        ) === false) {
            throw new RuntimeException('Cannot write activity log.');
        }
    }
}
