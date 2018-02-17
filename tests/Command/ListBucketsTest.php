<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\ListBuckets;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class ListBucketsTest extends BaseTest
{
    public function setUp()
    {
        $client = $this->createStorageClient();
        $client->createBucket('empty-test', 'in');
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

    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ListBuckets());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'list-buckets',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Buckets:', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        self::assertContains('in.c-test', $applicationTester->getDisplay());
        self::assertContains('in.c-empty-test', $applicationTester->getDisplay());
        self::assertNotContains('some-table', $applicationTester->getDisplay());
    }

    public function testExecuteInclude()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ListBuckets());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'list-buckets',
            '--include-tables' => 1,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Buckets:', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        self::assertContains('in.c-test', $applicationTester->getDisplay());
        self::assertContains('in.c-empty-test', $applicationTester->getDisplay());
        self::assertContains('some-table', $applicationTester->getDisplay());
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
            '--token' => TEST_STORAGE_API_TOKEN
        ]);
    }
}
