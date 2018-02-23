<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\Csv\CsvFile;
use Keboola\StorageApi\Cli\Command\CopyBucket;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\ClientException;
use Keboola\Temp\Temp;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

class CopyBucketTest extends BaseTest
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
        $application->add(new CopyBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'copy-bucket',
            'sourceBucketId' => 'in.c-test',
            'destinationBucketId' => 'in.c-destination',
            'destinationBucketBackend' => 'snowflake',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Table in.c-test.some-table copied', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = $this->createStorageClient();
        self::assertTrue($client->bucketExists('in.c-destination'));
        self::assertTrue($client->tableExists('in.c-destination.some-table'));
    }

    public function testExecuteExists(): void
    {
        $client = $this->createStorageClient();
        $client->createBucket('destination', 'in');
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new CopyBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'copy-bucket',
            'sourceBucketId' => 'in.c-test',
            'destinationBucketId' => 'in.c-destination',
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Destination bucket in.c-destination already exists', $applicationTester->getDisplay());
        self::assertEquals(1, $applicationTester->getStatusCode());
    }

    public function testExecuteDifferentProject(): void
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new CopyBucket());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'copy-bucket',
            'sourceBucketId' => 'in.c-test',
            'destinationBucketId' => 'in.c-destination',
            'destinationBucketBackend' => 'snowflake',
            'dstToken' => TEST_STORAGE_API_SECONDARY_TOKEN,
            '--token' => TEST_STORAGE_API_TOKEN,
        ]);

        self::assertContains('Table in.c-test.some-table copied', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
        // check for the results
        $client = new Client([
            'url' => TEST_STORAGE_API_URL,
            'token' => TEST_STORAGE_API_SECONDARY_TOKEN,
        ]);
        self::assertTrue($client->bucketExists('in.c-destination'));
        self::assertTrue($client->tableExists('in.c-destination.some-table'));
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

        $client = new Client([
            'url' => TEST_STORAGE_API_URL,
            'token' => TEST_STORAGE_API_SECONDARY_TOKEN,
        ]);
        try {
            $client->dropBucket('in.c-destination', ['force' => true]);
        } catch (ClientException $e) {
            if ($e->getCode() != 404) {
                throw $e;
            }
        }
    }
}
