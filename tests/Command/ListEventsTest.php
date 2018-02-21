<?php

namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\StorageApi\Cli\Command\ListEvents;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Symfony\Component\Console\Tester\ApplicationTester;

class ListEventsTest extends BaseTest
{
    public function setUp()
    {
        $client = $this->createStorageClient();
        $client->createBucket('empty-test', 'in');
    }

    public function testExecute()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ListEvents());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'list-events',
            '--token' => TEST_STORAGE_API_TOKEN,
        ], ['interactive' => false]);

        self::assertContains('Created bucket in.c-empty-test', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
    }

    public function testExecuteComponent()
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new ListEvents());
        $applicationTester = new ApplicationTester($application);
        $applicationTester->run([
            'list-events',
            '--component' => 'storage',
            '--token' => TEST_STORAGE_API_TOKEN,
        ], ['interactive' => false]);

        self::assertContains('storage[storage.bucketCreated]', $applicationTester->getDisplay());
        self::assertEquals(0, $applicationTester->getStatusCode());
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
