<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\DeleteTable;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class DeleteTableTest extends BaseTest
{
    public function setUp(): void
    {
        $client = $this->createStorageClient();
        $client->createBucket('test', 'in');
        $temp = new Temp();
        $fs = new Filesystem();
        $fileName = $temp->getTmpFolder() . DIRECTORY_SEPARATOR . 'someData.csv';
        $fs->dumpFile($fileName, "name,id\nfoo,1\nbar,2");
        $csv = new CsvFile($fileName);
        $client->createTable('in.c-test', 'some-table', $csv);
        unset($csv);
        unset($temp);
    }

    public function testExecute(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteTable());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-table',
            'tableId' => 'in.c-test.some-table',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        self::assertFalse($client->tableExists('in.c-test.some-table'));
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
