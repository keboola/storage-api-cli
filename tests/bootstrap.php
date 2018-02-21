<?php
// Define path to application directory
define('ROOT_PATH', __DIR__);
ini_set('display_errors', true);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Prague');

set_error_handler('exceptions_error_handler');
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}


require_once ROOT_PATH . '/../vendor/autoload.php';

defined('TEST_STORAGE_API_URL') || define('TEST_STORAGE_API_URL', getenv('TEST_STORAGE_API_URL'));
defined('TEST_STORAGE_API_TOKEN') || define('TEST_STORAGE_API_TOKEN', getenv('TEST_STORAGE_API_TOKEN') ?: 'your_token');
defined('TEST_STORAGE_API_SECONDARY_TOKEN') || define('TEST_STORAGE_API_SECONDARY_TOKEN', getenv('TEST_STORAGE_API_SECONDARY_TOKEN') ?: 'your_token');
defined('TEST_BACKUP_AWS_ACCESS_KEY_ID') || define('TEST_BACKUP_AWS_ACCESS_KEY_ID', getenv('TEST_BACKUP_AWS_ACCESS_KEY_ID') ?: 'your_token');
defined('TEST_BACKUP_AWS_SECRET_ACCESS_KEY') || define('TEST_BACKUP_AWS_SECRET_ACCESS_KEY', getenv('TEST_BACKUP_AWS_SECRET_ACCESS_KEY') ?: 'your_token');
defined('TEST_BACKUP_S3_BUCKET') || define('TEST_BACKUP_S3_BUCKET', getenv('TEST_BACKUP_S3_BUCKET') ?: 'sapi-backup-test');
defined('TEST_RESTORE_AWS_ACCESS_KEY_ID') || define('TEST_RESTORE_AWS_ACCESS_KEY_ID', getenv('TEST_RESTORE_AWS_ACCESS_KEY_ID') ?: 'your_token');
defined('TEST_RESTORE_AWS_SECRET_ACCESS_KEY') || define('TEST_RESTORE_AWS_SECRET_ACCESS_KEY', getenv('TEST_RESTORE_AWS_SECRET_ACCESS_KEY') ?: 'your_token');
defined('TEST_RESTORE_S3_BUCKET') || define('TEST_RESTORE_S3_BUCKET', getenv('TEST_RESTORE_S3_BUCKET') ?: 'sapi-backup-test');
defined('TEST_AWS_REGION') || define('TEST_AWS_REGION', getenv('TEST_AWS_REGION') ?: 'us-east-1');
