<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();
$logger = new Lab\ActivityLogger();

$smtpSettings = [
    'host' => Lab\Settings::required('MAILTRAP_HOST'),
    'port' => Lab\Settings::required('MAILTRAP_PORT'),
    'username' => Lab\Settings::required('MAILTRAP_USERNAME'),
    'password' => Lab\Settings::required('MAILTRAP_PASSWORD'),
    'from_address' => Lab\Settings::required('MAIL_FROM_ADDRESS'),
];

$sender = new Lab\EmailSender($smtpSettings);
$history = new Lab\EmailHistory($db);

$processor = new Lab\MarketingProcessor(
    $db,
    $history,
    $sender,
    $logger,
    (int) (getenv('MAILTRAP_DELAY_SECONDS') ?: 11)
);

$worker = new Lab\QueueWorker(
    Lab\Settings::queue(),
    Lab\Settings::required('SQS_QUEUE_URL'),
    $processor,
    $logger
);

$worker->run();