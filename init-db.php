<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();
$tools = new Lab\LocalDatabaseTools($db);
$tools->initializeSchema(__DIR__ . '/schema.sql');
