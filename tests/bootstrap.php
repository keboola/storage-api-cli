<?php

require __DIR__ . '/../vendor/autoload.php';

error_reporting(E_ALL);
set_error_handler(function ($errno, $errstr, $errfile, $errline, array $errcontext): bool {
    if (!(error_reporting() & $errno)) {
        // respect error_reporting() level
        // libraries used in custom components may emit notices that cannot be fixed
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

define('TEST_STORAGE_API_URL', getenv('TEST_STORAGE_API_URL'));
define('TEST_STORAGE_API_TOKEN', getenv('TEST_STORAGE_API_TOKEN') ?: 'your_token');
define('TEST_STORAGE_API_SECONDARY_TOKEN', getenv('TEST_STORAGE_API_SECONDARY_TOKEN') ?: 'your_token');
define('TEST_BACKUP_AWS_ACCESS_KEY_ID', getenv('TEST_BACKUP_AWS_ACCESS_KEY_ID') ?: 'your_token');
define('TEST_BACKUP_AWS_SECRET_ACCESS_KEY', getenv('TEST_BACKUP_AWS_SECRET_ACCESS_KEY') ?: 'your_token');
define('TEST_BACKUP_S3_BUCKET', getenv('TEST_BACKUP_S3_BUCKET') ?: 'sapi-backup-test');
define('TEST_RESTORE_AWS_ACCESS_KEY_ID', getenv('TEST_RESTORE_AWS_ACCESS_KEY_ID') ?: 'your_token');
define('TEST_RESTORE_AWS_SECRET_ACCESS_KEY', getenv('TEST_RESTORE_AWS_SECRET_ACCESS_KEY') ?: 'your_token');
define('TEST_RESTORE_S3_BUCKET', getenv('TEST_RESTORE_S3_BUCKET') ?: 'sapi-backup-test');
define('TEST_AWS_REGION', getenv('TEST_AWS_REGION') ?: 'us-east-1');
