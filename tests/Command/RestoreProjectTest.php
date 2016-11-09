<?php

namespace Keboola\DockerBundle\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\RestoreProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\TableExporter;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class RestoreProjectTest extends \PHPUnit_Framework_TestCase
{
    const S3_PATH = 'cli-client-restore-test/';
    const S3_REGION = 'us-east-1';

    public function testRestoreBuckets()
    {
        $this->loadBackupToS3('buckets');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $buckets = $client->listBuckets();
        $this->assertCount(2, $buckets);
        $this->assertEquals("in.c-bucket1", $buckets[0]["id"]);
        $this->assertEquals("in.c-bucket2", $buckets[1]["id"]);
    }

    public function testRestoreBucketsIgnoreStorageBackend()
    {
        $this->loadBackupToS3('buckets-multiple-backends');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode());
        $client = $this->getClient();
        $buckets = $client->listBuckets();
        $this->assertCount(3, $buckets);
        $this->assertTrue($client->bucketExists("in.c-snowflake"));
        $this->assertTrue($client->bucketExists("in.c-redshift"));
        $this->assertTrue($client->bucketExists("in.c-mysql"));
    }

    public function testBackendMissingError()
    {
        $this->loadBackupToS3('buckets-multiple-backends');
        $applicationTester = $this->runCommand(false);
        $this->assertEquals(1, $applicationTester->getStatusCode());
        $ret = $applicationTester->getDisplay();
        $this->assertContains('Missing', $ret);
    }

    public function testRestoreBucketAttributes()
    {
        $this->loadBackupToS3('buckets');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
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
            $client->getBucket("in.c-bucket1")["attributes"]
        );
    }

    public function testRestoreTableWithHeader()
    {
        $this->loadBackupToS3('table-with-header');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $this->assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $temp = new Temp();
        $file = $temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $expectedResult = <<<EOF
"Id","Name"
"001C000000xYbhhIAC","Keboola"
"001C000000xYbhhIAD","Keboola 2"

EOF;
        $this->assertEquals($expectedResult, file_get_contents($file->getPathname()));
    }

    public function testRestoreTableWithoutHeader()
    {
        $this->loadBackupToS3('table-without-header');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $this->assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $temp = new Temp();
        $file = $temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $expectedResult = <<<EOF
"Id","Name"
"001C000000xYbhhIAC","Keboola"
"001C000000xYbhhIAD","Keboola 2"

EOF;
        $this->assertEquals($expectedResult, file_get_contents($file->getPathname()));
    }


    public function testRestoreTableFromMultipleSlices()
    {
        $this->loadBackupToS3('table-multiple-slices');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $this->assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $temp = new Temp();
        $file = $temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $expectedResult = <<<EOF
"Id","Name"
"001C000000xYbhhIAC","Keboola"
"001C000000xYbhhIAD","Keboola 2"

EOF;
        $this->assertEquals($expectedResult, file_get_contents($file->getPathname()));
    }

    public function testRestoreTableAttributes()
    {
        $this->loadBackupToS3('table-properties');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
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
            $client->getTable("in.c-bucket.Account")["attributes"]
        );
    }

    public function testRestoreTableIndexesAndPrimaryKeys()
    {
        $this->loadBackupToS3('table-properties');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $accountTable = $client->getTable("in.c-bucket.Account");
        $account2Table = $client->getTable("in.c-bucket.Account2");
        $this->assertEquals(["Id", "Name"], $accountTable["indexedColumns"]);
        $this->assertEquals(["Id", "Name"], $accountTable["primaryKey"]);
        $this->assertEquals(["Id"], $account2Table["primaryKey"]);
        $this->assertEquals(["Id", "Name"], $account2Table["indexedColumns"]);
    }

    public function testRestoreAlias()
    {
        $this->loadBackupToS3('alias');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $aliasTable = $client->getTable("out.c-bucket.Account");
        $this->assertEquals(true, $aliasTable["isAlias"]);
        $this->assertEquals(true, $aliasTable["aliasColumnsAutoSync"]);
        $this->assertEquals(["Id", "Name"], $aliasTable["columns"]);
        $this->assertEquals("in.c-bucket.Account", $aliasTable["sourceTable"]["id"]);
    }

    public function testRestoreFilteredAlias()
    {
        $this->loadBackupToS3('alias-filtered');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->getClient();
        $aliasTable = $client->getTable("out.c-bucket.Account");
        $this->assertEquals(true, $aliasTable["isAlias"]);
        $this->assertEquals(false, $aliasTable["aliasColumnsAutoSync"]);
        $this->assertEquals(["Id"], $aliasTable["columns"]);
        $this->assertEquals("in.c-bucket.Account", $aliasTable["sourceTable"]["id"]);
        $this->assertEquals(["column" => "Name", "operator" => "eq", "values" => ["Keboola"]], $aliasTable["aliasFilter"]);
    }


    public function testRestoreConfigurations()
    {
        $this->loadBackupToS3('configurations');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->getClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();
        $this->assertCount(2, $componentsList);
        $this->assertEquals("keboola.csv-import", $componentsList[0]["id"]);
        $this->assertEquals("keboola.ex-slack", $componentsList[1]["id"]);

        $config = $components->getConfiguration("keboola.csv-import", 1);
        $this->assertEquals(1, $config["version"]);
        $this->assertEquals("", $config["changeDescription"]);
        $this->assertEquals("Accounts", $config["name"]);
        $this->assertEquals("Default CSV Importer", $config["description"]);

        $config = $components->getConfiguration("keboola.ex-slack", 2);
        $this->assertEquals(2, $config["version"]);
        $this->assertEquals("Configuration 2 restored from backup", $config["changeDescription"]);
        $this->assertEquals(["key" => "value"], $config["state"]);
    }

    public function testDoNotRestoreOrchestrationConfigurations()
    {
        $this->loadBackupToS3('configuration-orchestration');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->getClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();
        $this->assertCount(0, $componentsList);
    }

    public function testRestoreEmptyObjectInConfiguration()
    {
        $this->loadBackupToS3('configuration-empty-object');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->getClient();

        // empty array and object in config
        $tmp = new Temp();
        $file = $tmp->createFile('config.json');
        $client->apiGet('storage/components/keboola.csv-import/configs/1', $file->getPathname());
        $config = json_decode(file_get_contents($file->getPathname()));
        $this->assertEquals(new \stdClass(), $config->configuration->emptyObject);
        $this->assertEquals([], $config->configuration->emptyArray);
    }

    public function testRestoreConfigurationRows()
    {
        $this->loadBackupToS3('configuration-rows');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->getClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();

        $this->assertCount(1, $componentsList);
        $this->assertEquals("transformation", $componentsList[0]["id"]);
        $this->assertCount(2, $componentsList[0]["configurations"]);

        $config = $components->getConfiguration("transformation", 1);
        $this->assertEquals("MySQL", $config["name"]);
        $this->assertEquals(5, $config["version"]);
        $this->assertEquals("Row 4 restored from backup", $config["changeDescription"]);
        $this->assertCount(2, $config["rows"]);
        $this->assertEquals(3, $config["rows"][0]["id"]);
        $this->assertEquals("Account", $config["rows"][0]["configuration"]["name"]);
        $this->assertEquals(4, $config["rows"][1]["id"]);
        $this->assertEquals("Ratings", $config["rows"][1]["configuration"]["name"]);

        $config = $components->getConfiguration("transformation", 2);
        $this->assertEquals("Snowflake", $config["name"]);
        $this->assertEquals(5, $config["version"]);
        $this->assertEquals("Row 6 restored from backup", $config["changeDescription"]);
        $this->assertEquals(5, $config["rows"][0]["id"]);
        $this->assertEquals("Account", $config["rows"][0]["configuration"]["name"]);
        $this->assertEquals(6, $config["rows"][1]["id"]);
        $this->assertEquals("Ratings", $config["rows"][1]["configuration"]["name"]);
    }

    public function testRestoreEmptyObjectInConfigurationRow()
    {
        $this->loadBackupToS3('configuration-rows');
        $applicationTester = $this->runCommand();
        $this->assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        // empty array and object in config
        $tmp = new Temp();
        $file = $tmp->createFile('config.json');
        $client = $this->getClient();
        $client->apiGet('storage/components/transformation/configs/1/rows', $file->getPathname());
        $config = json_decode(file_get_contents($file->getPathname()));
        $this->assertEquals(new \stdClass(), $config[0]->configuration->input[0]->datatypes);
        $this->assertEquals([], $config[0]->configuration->queries);
    }

    protected function loadBackupToS3($backup)
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
        $s3Client->uploadDirectory(__DIR__ . "/../data/backups/{$backup}", TEST_S3_BUCKET, self::S3_PATH);
    }

    protected function runCommand($ignoreStorageBackend = true)
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
            '--ignore-storage-backend' => $ignoreStorageBackend,
            'bucket' => TEST_S3_BUCKET,
            'region' => self::S3_REGION,
            'path' => self::S3_PATH
        ]);
        return $applicationTester;
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

    public function getClient()
    {
        return new Client(['token' => TEST_STORAGE_API_TOKEN]);
    }
}
