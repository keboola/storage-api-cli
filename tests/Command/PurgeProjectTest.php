<?php

namespace Keboola\DockerBundle\Tests\Command;

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

class PurgeProjectTest extends \PHPUnit_Framework_TestCase
{

    public function testExecute()
    {
        // add configs
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
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
        $temp = new Temp();
        $temp->initRunFolder();
        $filename = $temp->createFile("table.csv");
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
        $this->assertCount(2, $client->listTables());
        $this->assertCount(1, $client->listFiles());
        $components = new Components($client);
        $this->assertCount(1, $components->listComponents());

        // run command
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new PurgeProject());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'purge-project',
            '--token' => TEST_STORAGE_API_TOKEN
        ]);

        // check for the results
        $this->assertCount(0, $client->listTables());
        $this->assertCount(0, $client->listBuckets());
        $this->assertCount(0, $client->listFiles());
        $this->assertCount(0, $components->listComponents());
    }

    public function tearDown()
    {

    }
}
