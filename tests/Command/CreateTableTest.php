<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\CreateTable;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\TableExporter;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class CreateTableTest extends BaseTest
{
    /**
     * @var Temp
     */
    private $temp;

    public function setUp(): void
    {
        $this->temp = new Temp();
        $client = $this->createStorageClient();
        $client->createBucket('test', 'in');
    }

    public function testExecute(): void
    {
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'someData.csv';
        $fs->dumpFile($fileName, "name,id\nfoo,1\nbar,2");

        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new CreateTable());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'create-table',
            'bucketId' => 'in.c-test',
            'name' => 'some-table',
            'filePath' => $fileName,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        $exporter = new TableExporter($client);
        $destination = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $destination, []);
        $csv = new CsvFile($destination);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        self::assertEquals(['name', 'id'], $csv->getHeader());
        self::assertEquals([1 => 'foo', 2 => 'bar', 'id' => 'name'], $results);
    }

    public function testExecuteDelimiter(): void
    {
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'someData.csv';
        $fs->dumpFile($fileName, "name\tid\nfoo\t1\nbar\t2");

        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new CreateTable());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'create-table',
            'bucketId' => 'in.c-test',
            'name' => 'some-table',
            'filePath' => $fileName,
            '--delimiter' => "\t",
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        $exporter = new TableExporter($client);
        $destination = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'destination.csv';
        $exporter->exportTable('in.c-test.some-table', $destination, []);
        $csv = new CsvFile($destination);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        self::assertEquals(['name', 'id'], $csv->getHeader());
        self::assertEquals([1 => 'foo', 2 => 'bar', 'id' => 'name'], $results);
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
