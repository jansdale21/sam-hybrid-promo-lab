<?php
declare(strict_types=1);

namespace Lab;

use Aws\Sqs\SqsClient;
use RuntimeException;

final class Settings
{
    public static function required(string $name): string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '') {
            throw new RuntimeException(
                "Missing required environment variable: $name"
            );
        }

        return $value;
    }

    public static function queue(): SqsClient
    {
        return new SqsClient([
            'version' => 'latest',
            'region' => self::required('AWS_REGION'),
        ]);
    }
}
