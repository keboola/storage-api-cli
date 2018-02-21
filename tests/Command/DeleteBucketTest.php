<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\DeleteBucket;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class DeleteBucketTest extends BaseTest
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

    public function testExecuteEmpty()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-bucket',
            'bucketId' => 'in.c-empty-test',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        self::assertFalse($client->bucketExists('in.c-empty-test'));
        self::assertTrue($client->bucketExists('in.c-test'));
    }

    public function testExecuteNonEmpty()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-bucket',
            'bucketId' => 'in.c-test',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(1, $applicationTester->getStatusCode());
        self::assertContains('not empty', $applicationTester->getDisplay());
        // check for the results
        $client = $this->createStorageClient();
        self::assertTrue($client->bucketExists('in.c-empty-test'));
        self::assertTrue($client->bucketExists('in.c-test'));
    }

    public function testExecuteNonEmptyForce()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new DeleteBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'delete-bucket',
            'bucketId' => 'in.c-test',
            '--recursive' => true,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        self::assertTrue($client->bucketExists('in.c-empty-test'));
        self::assertFalse($client->bucketExists('in.c-test'));
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
