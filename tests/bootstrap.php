<?php

require __DIR__ . '/../vendor/autoload.php';

set_error_handler('exceptions_error_handler');
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting
        return;
    }
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

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
