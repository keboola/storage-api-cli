<?php

namespace Keboola\DockerBundle\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\BackupProject;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\RestoreProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
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

        // TODO check configurations
        /*
        $components = new Components($client);
        $componentsList = $components->listComponents();
        $this->assertCount(4, $componentsList);
        */

        // TODO check configuration rows
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
