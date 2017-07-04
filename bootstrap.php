<?php
// Define path to application directory
define('ROOT_PATH', __DIR__);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

require_once ROOT_PATH . '/vendor/autoload.php';

defined('TEST_STORAGE_API_TOKEN') || define('TEST_STORAGE_API_TOKEN', getenv('TEST_STORAGE_API_TOKEN') ?: 'your_token');
defined('TEST_STORAGE_API_SECONDARY_TOKEN') || define('TEST_STORAGE_API_SECONDARY_TOKEN', getenv('TEST_STORAGE_API_SECONDARY_TOKEN') ?: 'your_token');
defined('TEST_AWS_ACCESS_KEY_ID') || define('TEST_AWS_ACCESS_KEY_ID', getenv('TEST_AWS_ACCESS_KEY_ID') ?: 'your_token');
defined('TEST_AWS_SECRET_ACCESS_KEY') || define('TEST_AWS_SECRET_ACCESS_KEY', getenv('TEST_AWS_SECRET_ACCESS_KEY') ?: 'your_token');
defined('TEST_S3_BUCKET') || define('TEST_S3_BUCKET', getenv('TEST_S3_BUCKET') ?: 'sapi-backup-test');
