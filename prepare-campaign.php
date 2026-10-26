<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$campaignId = $argv[1] ?? '';
$promoCode = $argv[2] ?? '';

try {
    $db = (new Lab\Database())->connect();

    $preparer = new Lab\CampaignPreparer($db);
    $preparer->prepare($campaignId, $promoCode);
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . PHP_EOL);
    exit(1);
}