<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Aws\S3\S3Client;
use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\BackupProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Exception;
use Keboola\StorageApi\Metadata;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Symfony\Component\Console\Tester\ApplicationTester;

class BackupProjectTest extends BaseTest
{
    public function setUp(): void
    {
        parent::setUp();
        $client = $this->createStorageClient();
        $component = new Components($client);
        try {
            $component->deleteConfiguration('transformation', 'sapi-php-test');
        } catch (\Throwable $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        // drop linked buckets
        foreach ($client->listBuckets() as $bucket) {
            if (isset($bucket['sourceBucket'])) {
                $client->dropBucket($bucket["id"], ["force" => true]);
            }
        }

        foreach ($client->listBuckets() as $bucket) {
            $client->dropBucket($bucket["id"], ["force" => true]);
        }
    }

    public function testExecuteNoVersions(): void
    {
        $client = $this->createStorageClient();
        $config = new Configuration();
        $config->setComponentId('transformation');
        $config->setDescription('Test Configuration');
        $config->setConfigurationId('sapi-php-test');
        $config->setName('test-configuration');
        $component = new Components($client);
        $configData = $component->addConfiguration($config);
        $config->setConfigurationId($configData['id']);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 1');
        $row->setConfiguration(
            ['name' => 'test 1', 'backend' => 'docker', 'type' => 'r', 'queries' => ['foo']]
        );
        $component->addConfigurationRow($row);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 2');
        $row->setConfiguration(
            ['name' => 'test 2', 'backend' => 'docker', 'type' => 'r', 'queries' => ['bar']]
        );
        $component->addConfigurationRow($row);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => 'backup',
        ]);
        $ret = $applicationTester->getDisplay();
        self::assertContains('Exporting buckets', $ret);
        self::assertContains('Exporting tables', $ret);
        self::assertContains('Exporting configurations', $ret);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);
        $targetFile = $tmp . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/configurations.json',
            'SaveAs' => $targetFile,
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents, true);
        $targetComponent = [];
        foreach ($targetData as $component) {
            if ($component['id'] === 'transformation') {
                $targetComponent = $component;
                break;
            }
        }
        self::assertGreaterThan(0, count($targetComponent));

        $targetConfiguration = [];
        foreach ($targetComponent['configurations'] as $configuration) {
            if ($configuration['name'] === 'test-configuration') {
                $targetConfiguration = $configuration;
            }
        }
        self::assertGreaterThan(0, count($targetConfiguration));
        self::assertEquals('Test Configuration', $targetConfiguration['description']);
        self::assertNotContains('rows', $targetConfiguration);

        $configurationId = $targetConfiguration['id'];
        $targetFile = $tmp . $configurationId . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/configurations/transformation/' . $configurationId . '.json',
            'SaveAs' => $targetFile,
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetConfiguration = json_decode($targetContents, true);
        self::assertGreaterThan(0, count($targetConfiguration));
        self::assertEquals('test-configuration', $targetConfiguration['name']);
        self::assertEquals('Test Configuration', $targetConfiguration['description']);
        self::assertArrayHasKey('rows', $targetConfiguration);
        self::assertEquals(2, count($targetConfiguration['rows']));
        self::assertEquals('foo', $targetConfiguration['rows'][0]['configuration']['queries'][0]);
        self::assertEquals('bar', $targetConfiguration['rows'][1]['configuration']['queries'][0]);
        self::assertNotContains('versions', $targetConfiguration);
        self::assertNotContains('versions', $targetConfiguration['rows'][0]);
    }

    /**
     * @dataProvider largeConfigurationsProvider
     * @param int $configurationRowsCount
     * @throws \Exception
     */
    public function testLargeConfigurations(int $configurationRowsCount): void
    {
        $client = $this->createStorageClient();
        $config = new Configuration();
        $config->setComponentId('transformation');
        $config->setDescription('Test Configuration');
        $config->setConfigurationId('sapi-php-test');
        $config->setName('test-configuration');
        $config->setState(['key' => 'value']);

        $component = new Components($client);
        $configData = $component->addConfiguration($config);
        $config->setConfigurationId($configData['id']);

        $largeRowConfiguration = [
            'values' => [],
        ];
        $valuesCount = 100;
        for ($i = 0; $i < $valuesCount; $i++) {
            $largeRowConfiguration['values'][] = sha1(random_bytes(128));
        }

        for ($i = 0; $i < $configurationRowsCount; $i++) {
            $row = new ConfigurationRow($config);
            $row->setChangeDescription('Row 1');
            $row->setConfiguration($largeRowConfiguration);

            if ($i === 0) {
                $row->setState(['rowKey' => 'value']);
            }

            $component->addConfigurationRow($row);
        }

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => 'backup',
        ]);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);
        $targetFile = $tmp . $config->getConfigurationId() . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/configurations/transformation/' . $config->getConfigurationId() . '.json',
            'SaveAs' => $targetFile,
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetConfiguration = json_decode($targetContents, true);
        self::assertGreaterThan(0, count($targetConfiguration));
        self::assertEquals('test-configuration', $targetConfiguration['name']);
        self::assertEquals('Test Configuration', $targetConfiguration['description']);
        self::assertEquals(['key' => 'value'], $targetConfiguration['state']);
        self::assertArrayHasKey('rows', $targetConfiguration);
        self::assertCount($configurationRowsCount, $targetConfiguration['rows']);

        $firstRow = reset($targetConfiguration['rows']);
        $this->assertEquals(['rowKey' => 'value'], $firstRow['state']);
        $lastRow = end($targetConfiguration['rows']);
        $this->assertEmpty($lastRow['state']);
    }

    public function largeConfigurationsProvider(): array
    {
        return [
            [
                10,
            ],
            [
                30,
            ],
        ];
    }

    public function testPreserveEmptyObjectAndArray(): void
    {
        $client = $this->createStorageClient();
        $config = new Configuration();
        $config->setComponentId('transformation');
        $config->setDescription('Test Configuration');
        $config->setConfigurationId('sapi-php-test');
        $config->setName('test-configuration');
        $config->setConfiguration(
            [
                "dummyObject" => new \stdClass(),
                "dummyArray" => [],
            ]
        );
        $component = new Components($client);
        $configData = $component->addConfiguration($config);
        $config->setConfigurationId($configData['id']);



        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 1');
        $row->setConfiguration(
            [
                'name' => 'test 1',
                'backend' => 'docker',
                'type' => 'r',
                'queries' => ['foo'],
                "dummyObject" => new \stdClass(),
                "dummyArray" => [],
            ]
        );
        $component->addConfigurationRow($row);

        $row = new ConfigurationRow($config);
        $row->setChangeDescription('Row 2');
        $row->setConfiguration(
            [
                'name' => 'test 2',
                'backend' => 'docker',
                'type' => 'r',
                'queries' => ['bar'],
                "dummyObject" => new \stdClass(),
                "dummyArray" => [],
            ]
        );
        $component->addConfigurationRow($row);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => 'backup',
        ]);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $ret = $applicationTester->getDisplay();
        self::assertContains('Exporting buckets', $ret);
        self::assertContains('Exporting tables', $ret);
        self::assertContains('Exporting configurations', $ret);

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);
        $targetFile = $tmp . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/configurations.json',
            'SaveAs' => $targetFile,
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetData = json_decode($targetContents);
        $targetConfiguration = $targetData[0]->configurations[0];

        self::assertEquals(new \stdClass(), $targetConfiguration->configuration->dummyObject);
        self::assertEquals([], $targetConfiguration->configuration->dummyArray);

        $configurationId = $targetConfiguration->id;
        $targetFile = $tmp . $configurationId . 'configurations.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/configurations/transformation/' . $configurationId . '.json',
            'SaveAs' => $targetFile,
        ]);
        $targetContents = file_get_contents($targetFile);
        $targetConfiguration = json_decode($targetContents);

        self::assertEquals(new \stdClass(), $targetConfiguration->rows[0]->configuration->dummyObject);
        self::assertEquals([], $targetConfiguration->rows[0]->configuration->dummyArray);
    }

    public function testExecuteLinkedBuckets(): void
    {
        $client = $this->createStorageClient();
        $bucketId = $client->createBucket("main", Client::STAGE_IN);

        $client->setBucketAttribute($bucketId, 'key', 'value', true);
        $client->shareBucket($bucketId, ['sharing' => 'organization']);

        $client->createTable("in.c-main", "sample", new CsvFile(__DIR__ . "/../data/sample.csv"));

        $token = $client->verifyToken();
        $projectId = $token['owner']['id'];

        $client->linkBucket('linked', 'in', $projectId, $bucketId);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => 'backup',
        ]);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);

        $targetFile = $tmp . 'buckets.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/buckets.json',
            'SaveAs' => $targetFile,
        ]);
        $buckets = json_decode(file_get_contents($targetFile), true);

        self::assertCount(2, $buckets);
        self::assertNotEmpty($buckets[1]['sourceBucket']);

        $targetFile = $tmp . 'tables.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/tables.json',
            'SaveAs' => $targetFile,
        ]);

        $tables = json_decode(file_get_contents($targetFile), true);

        self::assertCount(2, $tables);
        self::assertNotEmpty($tables[1]['sourceTable']);
    }

    public function testExecuteMetadata(): void
    {
        $client = $this->createStorageClient();
        $client->createBucket("main", Client::STAGE_IN);
        $client->createTable("in.c-main", "sample", new CsvFile(__DIR__ . "/../data/sample.csv"));
        $metadata = new Metadata($client);
        $metadata->postBucketMetadata("in.c-main", "system", [
            [
                "key" => "bucketKey",
                "value" => "bucketValue",
            ],
        ]);
        $metadata->postTableMetadata("in.c-main.sample", "system", [
            [
                "key" => "tableKey",
                "value" => "tableValue",
            ],
        ]);
        $metadata->postColumnMetadata("in.c-main.sample.col1", "system", [
            [
                "key" => "columnKey",
                "value" => "columnValue",
            ],
        ]);

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => 'backup',
        ]);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);

        $targetFile = $tmp . 'buckets.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/buckets.json',
            'SaveAs' => $targetFile,
        ]);
        $data = json_decode(file_get_contents($targetFile), true);
        $this->assertEquals("bucketKey", $data[0]["metadata"][0]["key"]);
        $this->assertEquals("bucketValue", $data[0]["metadata"][0]["value"]);

        $targetFile = $tmp . 'tables.json';
        $s3Client->getObject([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
            'Key' => 'backup/tables.json',
            'SaveAs' => $targetFile,
        ]);
        $data = json_decode(file_get_contents($targetFile), true);
        $this->assertEquals("tableKey", $data[0]["metadata"][0]["key"]);
        $this->assertEquals("tableValue", $data[0]["metadata"][0]["value"]);
        $this->assertEquals("columnKey", $data[0]["columnMetadata"]["col1"][0]["key"]);
        $this->assertEquals("columnValue", $data[0]["columnMetadata"]["col1"][0]["value"]);
    }

    public function testExecuteWithoutPath(): void
    {
        $client = $this->createStorageClient();
        $client->createBucket("main", Client::STAGE_IN);
        $client->createTable("in.c-main", "sample", new CsvFile(__DIR__ . "/../data/sample.csv"));

        putenv('AWS_ACCESS_KEY_ID=' . TEST_BACKUP_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_BACKUP_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new BackupProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'backup-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--structure-only' => true,
            'bucket' => TEST_BACKUP_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => '',
        ]);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);

        $keys = array_map(function ($key) {
            return $key["Key"];
        }, $s3Client->listObjects([
            'Bucket' => TEST_BACKUP_S3_BUCKET,
        ])->toArray()["Contents"]);

        self::assertTrue(in_array('buckets.json', $keys));
        self::assertTrue(in_array('tables.json', $keys));
        self::assertTrue(in_array('configurations.json', $keys));
        self::assertCount(3, $keys);
    }

    public function tearDown(): void
    {
        $client = $this->createStorageClient();
        $component = new Components($client);
        try {
            $component->deleteConfiguration('transformation', 'sapi-php-test');
        } catch (\Throwable $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => TEST_AWS_REGION,
            'credentials' => [
                'key' => TEST_BACKUP_AWS_ACCESS_KEY_ID,
                'secret' => TEST_BACKUP_AWS_SECRET_ACCESS_KEY,
            ],
        ]);
        $keys = $s3Client->listObjects(['Bucket' => TEST_BACKUP_S3_BUCKET]);
        $keys = $keys->toArray()['Contents'];
        $deleteObjects = [];
        foreach ($keys as $key) {
            $deleteObjects[] = $key;
        }

        if (count($deleteObjects) > 0) {
            $s3Client->deleteObjects(
                [
                    'Bucket' => TEST_BACKUP_S3_BUCKET,
                    'Delete' => ['Objects' => $deleteObjects],
                ]
            );
        }

        // drop linked buckets
        foreach ($client->listBuckets() as $bucket) {
            if (isset($bucket['sourceBucket'])) {
                $client->dropBucket($bucket["id"], ["force" => true]);
            }
        }

        foreach ($client->listBuckets() as $bucket) {
            $client->dropBucket($bucket["id"], ["force" => true]);
        }
    }
}
