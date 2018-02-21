<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\ExportTable;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class ExportTableTest extends BaseTest
{
    /**
     * @var Temp
     */
    private $temp;

    public function setUp()
    {
        $this->temp = new Temp('sapi-cli-test');
        $client = $this->createStorageClient();
        $client->createBucket('test', 'in');
        $fs = new Filesystem();
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'someData.csv';
        $fs->dumpFile($fileName, "name,id\nfoo,1\nbar,2\nbaz,3");
        $csv = new CsvFile($fileName);
        $client->createTable('in.c-test', 'some-table', $csv);
    }

    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ExportTable());
        $applicationTester = new ApplicationTester($application);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'exported.csv';
        $applicationTester->run([
            'export-table',
            'tableId' => 'in.c-test.some-table',
            'filePath' => $fileName,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        self::assertFileExists($fileName);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        self::assertEquals(['name', 'id'], $csv->getHeader());
        self::assertEquals([1 => 'foo', 2 => 'bar', 3 => 'baz', 'id' => 'name'], $results);
    }

    public function testExecuteExtended()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ExportTable());
        $applicationTester = new ApplicationTester($application);
        $fileName = $this->temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'exported.csv';
        $applicationTester->run([
            'export-table',
            'tableId' => 'in.c-test.some-table',
            'filePath' => $fileName,
            '--whereOperator' => 'eq',
            '--whereColumn' => 'id',
            '--whereValues' => ['3', '1'],
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        self::assertFileExists($fileName);
        $csv = new CsvFile($fileName);
        $results = [];
        foreach ($csv as $line) {
            $results[$line[1]] = $line[0];
        }
        self::assertEquals(['name', 'id'], $csv->getHeader());
        self::assertEquals([1 => 'foo', 3 => 'baz', 'id' => 'name'], $results);
    }

    public function tearDown()
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
