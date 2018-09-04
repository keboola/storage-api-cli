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
    ],
]);

// Clean S3 bucket
echo "Removing existing objects from S3\n";
$result = $s3Client->listObjects(['Bucket' => TEST_RESTORE_S3_BUCKET])->toArray();
if (isset($result['Contents'])) {
    if (count($result['Contents']) > 0) {
        $result = $s3Client->deleteObjects(
            [
                'Bucket' => TEST_RESTORE_S3_BUCKET,
                'Delete' => ['Objects' => $result['Contents']],
            ]
        );
    }
}

// Check if all files aws deleted - prevent no delete permission
$result = $s3Client->listObjects(['Bucket' => TEST_RESTORE_S3_BUCKET])->toArray();
if (isset($result['Contents'])) {
    if (count($result['Contents']) > 0) {
        throw new \Exception('AWS S3 bucket is not empty');
    }
}

// Where the files will be source from
$source = $basedir . '/data/backups';

// Where the files will be transferred to
$dest = 's3://' . TEST_RESTORE_S3_BUCKET . '/';

// Create a transfer object.
echo "Updating fixtures in S3\n";
$manager = new \Aws\S3\Transfer($s3Client, $source, $dest, []);

// Perform the transfer synchronously.
$manager->transfer();
