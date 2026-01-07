<?php
// CLI Script to expire overdue tasks
// Run this via cron: * * * * * php /path/to/project/backend/cli/expire.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Assignment.php';

// Keep timestamps consistent for logs/scheduler
if (!ini_get('date.timezone')) {
    date_default_timezone_set('UTC');
}

$now = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();

    $assignmentModel = new Assignment($db);
    $expiredCount = $assignmentModel->expireOverdue();

    $line = sprintf("[%s] expiry_job expired=%d\n", $now, (int)$expiredCount);
    echo $line;

    // Append to a simple audit log (safe for Task Scheduler)
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logDir . '/expiry.log', $line, FILE_APPEND);

    exit(0);
} catch (Throwable $e) {
    $line = sprintf("[%s] expiry_job ERROR %s\n", $now, $e->getMessage());
    fwrite(STDERR, $line);

    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    @file_put_contents($logDir . '/expiry.log', $line, FILE_APPEND);

    exit(1);
}
