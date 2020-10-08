<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Keboola\StorageApi\Options\Components\ConfigurationRow;
use Keboola\StorageApi\Options\FileUploadOptions;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class PurgeProjectTest extends BaseTest
{
    /**
     * @var Temp
     */
    private $temp;

    public function setUp(): void
    {
        // add configs
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

        // add table
        $client->createBucket("main", Client::STAGE_IN);
        $this->temp = new Temp();
        $this->temp->initRunFolder();
        $filename = $this->temp->createFile("table.csv");
        $csv = new CsvFile($filename->getPathname());
        $csv->writeRow(["Id", "Name"]);
        $csv->writeRow(["1", "test"]);
        $client->createTable("in.c-main", "test", $csv);

        // add aliases
        $client->createBucket("alias", Client::STAGE_IN);
        $client->createAliasTable("in.c-alias.alias", "in.c-main.test");

        // add file uploads
        $fileUploadOptions = new FileUploadOptions();
        $fileUploadOptions->setFileName("test");
        $client->uploadFile($csv->getPathname(), $fileUploadOptions);

        // check stats
        self::assertCount(2, $client->listTables());
        self::assertCount(2, $client->listBuckets());
        $components = new Components($client);
        self::assertCount(1, $components->listComponents());
        sleep(5);
        self::assertCount(1, $client->listFiles());
    }

    public function testExecuteFull(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        // check for the results
        $client = $this->createStorageClient();
        $components = new Components($client);
        self::assertCount(0, $client->listTables());
        self::assertCount(0, $client->listBuckets());
        self::assertCount(0, $components->listComponents());
        sleep(5);
        self::assertCount(0, $client->listFiles());
    }

    public function testPurgeConfigurations(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--configurations' => true,
        ]);

        // check stats
        $client = $this->createStorageClient();
        $components = new Components($client);
        self::assertCount(2, $client->listTables());
        self::assertCount(2, $client->listBuckets());
        self::assertCount(0, $components->listComponents());
        sleep(5);
        self::assertCount(1, $client->listFiles());
    }

    public function testPurgeFileUploads(): void
    {
        $client = $this->createStorageClient();
        $fileUploadOptions = new FileUploadOptions();
        $fileUploadOptions->setFileName("test");
        $fs = new Filesystem();
        for ($i = 0; $i <= 100; $i++) {
            $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'tmp-' . $i;
            $fs->dumpFile($fileName, 'content' . $i);
            $client->uploadFile($fileName, $fileUploadOptions);
        }

        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--file-uploads' => true,
        ]);

        // check stats
        $client = $this->createStorageClient();
        $components = new Components($client);
        self::assertCount(2, $client->listTables());
        self::assertCount(2, $client->listBuckets());
        self::assertCount(1, $components->listComponents());
        sleep(5);
        self::assertCount(0, $client->listFiles());
    }

    public function testPurgeData(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--data' => true,
        ]);

        // check stats
        $client = $this->createStorageClient();
        $components = new Components($client);
        self::assertCount(0, $client->listTables());
        self::assertCount(0, $client->listBuckets());
        self::assertCount(1, $components->listComponents());
        sleep(5);
        self::assertCount(1, $client->listFiles());
    }

    public function testPurgeAliases(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
            '--aliases' => true,
        ]);

        // check stats
        $client = $this->createStorageClient();
        $components = new Components($client);
        self::assertCount(1, $client->listTables());
        self::assertCount(2, $client->listBuckets());
        self::assertCount(1, $components->listComponents());
        sleep(5);
        self::assertCount(1, $client->listFiles());
    }

    public function tearDown(): void
    {
        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);
    }
}
