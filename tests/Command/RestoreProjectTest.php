<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Aws\S3\S3Client;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Command\RestoreProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\TableExporter;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;

class RestoreProjectTest extends BaseTest
{
    private const S3_PATH = '';

    /**
     * @var Temp
     */
    private $temp;

    public function setUp(): void
    {
        $this->temp = new Temp();

        // purge project
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        // clean up components configs in test project
        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentList = $components->listComponents();
        foreach ($componentList as $componentItem) {
            foreach ($componentItem["configurations"] as $config) {
                // mark as isDeleted=true
                $components->deleteConfiguration($componentItem["id"], $config["id"]);
                // delete completely
                $components->deleteConfiguration($componentItem["id"], $config["id"]);
            }
        }
    }

    public function testRestoreBuckets(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'buckets/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $buckets = $client->listBuckets();
        self::assertCount(2, $buckets);
        self::assertEquals("in.c-bucket1", $buckets[0]["id"]);
        self::assertEquals("in.c-bucket2", $buckets[1]["id"]);
    }

    public function testRestoreBucketsIgnoreStorageBackend(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'buckets-multiple-backends/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $buckets = $client->listBuckets();
        self::assertCount(3, $buckets);
        self::assertTrue($client->bucketExists("in.c-snowflake"));
        self::assertTrue($client->bucketExists("in.c-redshift"));
        self::assertTrue($client->bucketExists("in.c-mysql"));
    }

    public function testBackendMissingError(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'buckets-multiple-backends/', false);
        self::assertEquals(1, $applicationTester->getStatusCode());
        $ret = $applicationTester->getDisplay();
        self::assertContains('Missing', $ret);
    }

    public function testRestoreBucketAttributes(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'buckets/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertEquals(
            [
                [
                    "name" => "myKey",
                    "value" => "myValue",
                    "protected" => false,
                ],
                [
                    "name" => "myProtectedKey",
                    "value" => "myProtectedValue",
                    "protected" => true,
                ],
            ],
            $client->getBucket("in.c-bucket1")["attributes"]
        );
    }

    public function testRestoreTableWithHeader(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-with-header/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $file = $this->temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $fileContents = file_get_contents($file->getPathname());
        self::assertContains('"Id","Name"', $fileContents);
        self::assertContains('"001C000000xYbhhIAC","Keboola"', $fileContents);
        self::assertContains('"001C000000xYbhhIAD","Keboola 2"', $fileContents);
    }

    public function testRestoreTableWithoutHeader(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-without-header/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $file = $this->temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $fileContents = file_get_contents($file->getPathname());
        self::assertContains('"Id","Name"', $fileContents);
        self::assertContains('"001C000000xYbhhIAC","Keboola"', $fileContents);
        self::assertContains('"001C000000xYbhhIAD","Keboola 2"', $fileContents);
    }


    public function testRestoreTableFromMultipleSlices(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-multiple-slices/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
        $tableExporter = new TableExporter($client);
        $file = $this->temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $fileContents = file_get_contents($file->getPathname());
        self::assertContains('"Id","Name"', $fileContents);
        self::assertContains('"001C000000xYbhhIAC","Keboola"', $fileContents);
        self::assertContains('"001C000000xYbhhIAD","Keboola 2"', $fileContents);
    }

    public function testRestoreTableFromMultipleSlicesSharedPrefix(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-multiple-slices-shared-prefix/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
        self::assertTrue($client->tableExists("in.c-bucket.Account2"));

        $tableExporter = new TableExporter($client);
        $file = $this->temp->createFile("account.csv");
        $tableExporter->exportTable("in.c-bucket.Account", $file->getPathname(), []);
        $fileContents = file_get_contents($file->getPathname());
        self::assertContains('"Id","Name"', $fileContents);
        self::assertContains('"001C000000xYbhhIAC","Keboola"', $fileContents);
        self::assertContains('"001C000000xYbhhIAD","Keboola 2"', $fileContents);
        self::assertCount(4, explode("\n", $fileContents));

        $file = $this->temp->createFile("account2.csv");
        $tableExporter->exportTable("in.c-bucket.Account2", $file->getPathname(), []);
        $fileContents = file_get_contents($file->getPathname());
        self::assertContains('"Id","Name"', $fileContents);
        self::assertContains('"001C000000xYbhhIAC","Keboola"', $fileContents);
        self::assertContains('"001C000000xYbhhIAD","Keboola 2"', $fileContents);
        self::assertCount(4, explode("\n", $fileContents));
    }

    public function testRestoreTableAttributes(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-properties');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertEquals(
            [
                [
                    "name" => "myKey",
                    "value" => "myValue",
                    "protected" => false,
                ],
                [
                    "name" => "myProtectedKey",
                    "value" => "myProtectedValue",
                    "protected" => true,
                ],
            ],
            $client->getTable("in.c-bucket.Account")["attributes"]
        );
    }

    public function testRestoreTablePrimaryKeys(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-properties/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $accountTable = $client->getTable("in.c-bucket.Account");
        $account2Table = $client->getTable("in.c-bucket.Account2");
        self::assertEquals(["Id", "Name"], $accountTable["primaryKey"]);
        self::assertEquals(["Id"], $account2Table["primaryKey"]);
    }

    public function testRestoreAlias(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'alias');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $aliasTable = $client->getTable("out.c-bucket.Account");
        self::assertEquals(true, $aliasTable["isAlias"]);
        self::assertEquals(true, $aliasTable["aliasColumnsAutoSync"]);
        self::assertEquals(["Id", "Name"], $aliasTable["columns"]);
        self::assertEquals("in.c-bucket.Account", $aliasTable["sourceTable"]["id"]);
    }

    public function testRestoreFilteredAlias(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'alias-filtered');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $aliasTable = $client->getTable("out.c-bucket.Account");
        self::assertEquals(true, $aliasTable["isAlias"]);
        self::assertEquals(false, $aliasTable["aliasColumnsAutoSync"]);
        self::assertEquals(["Id"], $aliasTable["columns"]);
        self::assertEquals("in.c-bucket.Account", $aliasTable["sourceTable"]["id"]);
        self::assertEquals(["column" => "Name", "operator" => "eq", "values" => ["Keboola"]], $aliasTable["aliasFilter"]);
    }

    public function testRestoreConfigurations(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configurations/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        print $applicationTester->getDisplay();
        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();
        self::assertCount(2, $componentsList);
        self::assertEquals("keboola.csv-import", $componentsList[0]["id"]);
        self::assertEquals("keboola.ex-slack", $componentsList[1]["id"]);

        $config = $components->getConfiguration("keboola.csv-import", 1);
        self::assertEquals(1, $config["version"]);
        self::assertEquals("", $config["changeDescription"]);
        self::assertEquals("Accounts", $config["name"]);
        self::assertEquals("Default CSV Importer", $config["description"]);

        $config = $components->getConfiguration("keboola.ex-slack", 2);
        self::assertEquals(2, $config["version"]);
        self::assertEquals("Configuration 2 restored from backup", $config["changeDescription"]);
        self::assertEquals(["key" => "value"], $config["state"]);
    }


    public function testRestoreConfigurationsWithoutVersions(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configurations-no-versions');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();

        self::assertCount(2, $componentsList);
        self::assertEquals("keboola.csv-import", $componentsList[0]["id"]);
        self::assertEquals("keboola.ex-slack", $componentsList[1]["id"]);

        $config = $components->getConfiguration("keboola.csv-import", 1);

        self::assertEquals(1, $config["version"]);
        self::assertEquals("", $config["changeDescription"]);
        self::assertEquals("Accounts", $config["name"]);
        self::assertEquals("Default CSV Importer", $config["description"]);

        $config = $components->getConfiguration("keboola.ex-slack", 2);
        self::assertEquals(2, $config["version"]);
        self::assertEquals("Configuration 2 restored from backup", $config["changeDescription"]);
        self::assertEquals(["key" => "value"], $config["state"]);
    }

    public function testDoNotRestoreOrchestrationConfigurations(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configuration-orchestration/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();
        self::assertCount(0, $componentsList);
    }

    public function testRestoreEmptyObjectInConfiguration(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configuration-empty-object/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->createStorageClient();

        // empty array and object in config
        $file = $this->temp->createFile('config.json');
        $client->apiGet('storage/components/keboola.csv-import/configs/1', $file->getPathname());
        $config = json_decode(file_get_contents($file->getPathname()));
        self::assertEquals(new \stdClass(), $config->configuration->emptyObject);
        self::assertEquals([], $config->configuration->emptyArray);
    }

    public function testRestoreConfigurationRows(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configuration-rows/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();

        self::assertCount(1, $componentsList);
        self::assertEquals("transformation", $componentsList[0]["id"]);
        self::assertCount(2, $componentsList[0]["configurations"]);

        $config = $components->getConfiguration("transformation", 1);
        self::assertEquals("MySQL", $config["name"]);
        self::assertEquals(5, $config["version"]);
        self::assertEquals("Row 4 restored from backup", $config["changeDescription"]);
        self::assertCount(2, $config["rows"]);
        self::assertEquals(3, $config["rows"][0]["id"]);
        self::assertEquals("Account", $config["rows"][0]["configuration"]["name"]);
        self::assertEquals(4, $config["rows"][1]["id"]);
        self::assertEquals("Ratings", $config["rows"][1]["configuration"]["name"]);

        $config = $components->getConfiguration("transformation", 2);
        self::assertEquals("Snowflake", $config["name"]);
        self::assertEquals(5, $config["version"]);
        self::assertEquals("Row 6 restored from backup", $config["changeDescription"]);
        self::assertEquals(5, $config["rows"][0]["id"]);
        self::assertEquals("Account", $config["rows"][0]["configuration"]["name"]);
        self::assertEquals(6, $config["rows"][1]["id"]);
        self::assertEquals("Ratings", $config["rows"][1]["configuration"]["name"]);
    }

    public function testRestoreEmptyObjectInConfigurationRow(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configuration-rows/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));

        // empty array and object in config
        $file = $this->temp->createFile('config.json');
        $client = $this->createStorageClient();
        $client->apiGet('storage/components/transformation/configs/1/rows', $file->getPathname());
        $config = json_decode(file_get_contents($file->getPathname()));
        self::assertEquals(new \stdClass(), $config[0]->configuration->input[0]->datatypes);
        self::assertEquals([], $config[0]->configuration->queries);
    }

    public function testRestoreOnlyConfigurations(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-with-header/', false, true, false);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertFalse($client->tableExists("in.c-bucket.Account"));
    }

    public function testRestoreOnlyData(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'configurations/', false, false, true);
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $components = new Components($client);
        $componentsList = $components->listComponents();
        self::assertCount(0, $componentsList);
    }

    public function testRestoreBucketWithoutPrefix(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'bucket-without-prefix/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $buckets = $client->listBuckets();
        self::assertCount(0, $buckets);
    }

    public function testRestoreTableWithoutPrefix(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-without-prefix/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        $buckets = $client->listBuckets();
        self::assertCount(0, $buckets);
    }

    public function testRestoreTableEmpty(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'table-empty/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
    }

    public function testRestoreMetadata(): void
    {
        $applicationTester = $this->runCommand(self::S3_PATH . 'metadata/');
        self::assertEquals(0, $applicationTester->getStatusCode(), print_r($applicationTester->getDisplay(), 1));
        $client = $this->createStorageClient();
        self::assertTrue($client->tableExists("in.c-bucket.Account"));
        $table = $client->getTable("in.c-bucket.Account");
        self::assertEquals("tableKey", $table["metadata"][0]["key"]);
        self::assertEquals("tableValue", $table["metadata"][0]["value"]);
        self::assertEquals("columnKey", $table["columnMetadata"]["Id"][0]["key"]);
        self::assertEquals("columnValue", $table["columnMetadata"]["Id"][0]["value"]);
        $bucket = $client->listBuckets(["include" => "metadata"])[0];
        self::assertEquals("bucketKey", $bucket["metadata"][0]["key"]);
        self::assertEquals("bucketValue", $bucket["metadata"][0]["value"]);
    }

    protected function runCommand(string $path, bool $ignoreStorageBackend = true, bool $onlyConfigurations = false, bool $onlyData = false): ApplicationTester
    {
        putenv('AWS_ACCESS_KEY_ID=' . TEST_RESTORE_AWS_ACCESS_KEY_ID);
        putenv('AWS_SECRET_ACCESS_KEY=' . TEST_RESTORE_AWS_SECRET_ACCESS_KEY);
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new RestoreProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'restore-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--ignore-storage-backend' => $ignoreStorageBackend,
            '--configurations' => $onlyConfigurations,
            '--data' => $onlyData,
            'bucket' => TEST_RESTORE_S3_BUCKET,
            'region' => TEST_AWS_REGION,
            'path' => $path,
        ]);
        return $applicationTester;
    }
}
