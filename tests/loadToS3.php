<?php
/**
 * Loads test fixtures into S3
 */

date_default_timezone_set('Europe/Prague');
ini_set('display_errors', true);
error_reporting(E_ALL);

$basedir = dirname(__FILE__);

require_once $basedir . '/bootstrap.php';

echo "Loading fixtures to S3\n";

// delete from S3
$s3Client = new \Aws\S3\S3Client([
    'version' => 'latest',
    'region' => TEST_AWS_REGION,
    'credentials' => [
        'key' => TEST_RESTORE_AWS_ACCESS_KEY_ID,
        'secret' => TEST_RESTORE_AWS_SECRET_ACCESS_KEY,
    ]
]);
$s3Client->deleteMatchingObjects(TEST_RESTORE_S3_BUCKET, '*');

// Where the files will be source from
$source = $basedir . '/data/backups';

// Where the files will be transferred to
$dest = 's3://' . TEST_RESTORE_S3_BUCKET . '/';

// Create a transfer object.
$manager = new \Aws\S3\Transfer($s3Client, $source, $dest, []);

// Perform the transfer synchronously.
$manager->transfer();
