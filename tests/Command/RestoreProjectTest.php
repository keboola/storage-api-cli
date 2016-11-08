<?php

namespace Keboola\DockerBundle\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\RestoreProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class RestoreProjectTest extends \PHPUnit_Framework_TestCase
{
    const S3_PATH = 'cli-client-restore-test/';
    const S3_REGION = 'us-east-1';

    public function testExecuteBackendError()
    {
        putenv('AWS_ACCESS_KEY_ID=' . TEST_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new RestoreProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'restore-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--ignore-storage-backend' => false,
            'bucket' => TEST_S3_BUCKET,
            'region' => self::S3_REGION,
            'path' => self::S3_PATH
        ]);

        $this->assertEquals(1, $applicationTester->getStatusCode());
        $ret = $applicationTester->getDisplay();
        $this->assertContains('Missing', $ret);
    }

    public function testExecuteWithIgnoreStorageBackend()
    {
        putenv('AWS_ACCESS_KEY_ID=' . TEST_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new RestoreProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'restore-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--ignore-storage-backend' => true,
            'bucket' => TEST_S3_BUCKET,
            'region' => self::S3_REGION,
            'path' => self::S3_PATH
        ]);

        $this->assertEquals(0, $applicationTester->getStatusCode());

        // basic verification
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
        $this->assertCount(12, $client->listBuckets());
        $this->assertCount(20, $client->listTables());

        // bucket attributes
        $this->assertEquals(
            [
                [
                    "name" => "myKey",
                    "value" => "myValue",
                    "protected" => false
                ],
                [
                    "name" => "myProtectedKey",
                    "value" => "myProtectedValue",
                    "protected" => true
                ]
            ],
            $client->getBucket("in.c-snowflake")["attributes"]
        );

        $mysqlAccountTable = $client->getTable("in.c-mysql.Account");
        $snowflakeAccountTable = $client->getTable("in.c-snowflake.Account");
        $redshiftAccountTable = $client->getTable("in.c-redshift.Account");

        // primary keys
        $this->assertEquals(["Id"], $mysqlAccountTable["primaryKey"]);
        $this->assertEquals(["Id"], $redshiftAccountTable["primaryKey"]);
        $this->assertEquals(["Id", "Name"], $snowflakeAccountTable["primaryKey"]);

        // indexes
        $this->assertEquals(["Id", "Name"], $mysqlAccountTable["indexedColumns"]);
        $this->assertEquals(["Id", "Name"], $redshiftAccountTable["indexedColumns"]);
        $this->assertEquals(["Id", "Name"], $snowflakeAccountTable["indexedColumns"]);

        // check table attributes
        $this->assertEquals(
            [
                [
                    "name" => "myKey",
                    "value" => "myValue",
                    "protected" => false
                ],
                [
                    "name" => "myProtectedKey",
                    "value" => "myProtectedValue",
                    "protected" => true
                ]
            ],
            $snowflakeAccountTable["attributes"]
        );

        // aliases
        $snowflakeAlias = $client->getTable("out.c-snowflake-aliases.Account");
        $this->assertTrue($snowflakeAlias["isAlias"]);
        $this->assertTrue($snowflakeAlias["aliasColumnsAutoSync"]);
        $this->assertEquals(["Id", "Name"], $snowflakeAlias["columns"]);

        $redshiftAlias = $client->getTable("out.c-redshift-aliases.Account");
        $this->assertTrue($redshiftAlias["isAlias"]);
        $this->assertTrue($redshiftAlias["aliasColumnsAutoSync"]);
        $this->assertEquals(["Id", "Name"], $redshiftAlias["columns"]);

        $mysqlAlias = $client->getTable("out.c-mysql-aliases.Account");
        $this->assertTrue($mysqlAlias["isAlias"]);
        $this->assertFalse($mysqlAlias["aliasColumnsAutoSync"]);
        $this->assertEquals(["Id"], $mysqlAlias["columns"]);
        $this->assertEquals(["column" => "Name", "operator" => "eq", "values" => ["Keboola"]], $mysqlAlias["aliasFilter"]);

        // configurations
        $components = new Components($client);
        $componentsList = $components->listComponents();
        // orchestrator is not imported
        $this->assertCount(3, $componentsList);

        // configuration versions
        $slackConfig = $components->getConfiguration('keboola.ex-slack', 213957518);
        $this->assertEquals(2, $slackConfig["version"]);
        $this->assertEquals("Configuration 213957518 restored from backup", $slackConfig["changeDescription"]);

        // configuration rows
        $transformationConfig = $components->getConfiguration('transformation', 213956216);
        $this->assertEquals(5, $transformationConfig["version"]);
        $this->assertCount(2, $transformationConfig["rows"]);
        $this->assertEquals("Row 213956392 restored from backup", $transformationConfig["changeDescription"]);

        // empty array and object in config
        $tmp = new Temp();
        $file = $tmp->createFile('config.json');
        $client->apiGet('storage/components/transformation/configs/213956216', $file->getPathname());
        $config = json_decode(file_get_contents($file->getPathname()));
        $this->assertEquals(new \stdClass(), $config->rows[0]->configuration->dummyObject);
        $this->assertEquals([], $config->rows[0]->configuration->queries);


    }

    public function setUp()
    {
        // load data to S3
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => self::S3_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $s3Client->uploadDirectory(__DIR__ . "/../data/projectBackup", TEST_S3_BUCKET, self::S3_PATH);
    }

    public function tearDown()
    {
        // purge project
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN
        ]);

        // delete from S3
        $s3Client = new S3Client([
            'version' => 'latest',
            'region' => self::S3_REGION,
            'credentials' => [
                'key' => TEST_AWS_ACCESS_KEY_ID,
                'secret' => TEST_AWS_SECRET_ACCESS_KEY,
            ]
        ]);
        $s3Client->deleteMatchingObjects(TEST_S3_BUCKET, self::S3_PATH);
    }
}
