<?php

namespace Keboola\DockerBundle\Tests\Command;

use Keboola\StorageApi\Cli\Command\ListEvents;
use Keboola\StorageApi\Cli\Command\PurgeProject;
use Keboola\StorageApi\Cli\Console\Application;
use Keboola\StorageApi\Client;
use Symfony\Component\Console\Tester\ApplicationTester;

class ListEventsTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $client = new Client(['token' => TEST_STORAGE_API_TOKEN]);
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

        $this->assertContains('Created bucket in.c-empty-test', $applicationTester->getDisplay());
        $this->assertEquals(0, $applicationTester->getStatusCode());
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

        $this->assertContains('storage[storage.bucketCreated]', $applicationTester->getDisplay());
        $this->assertEquals(0, $applicationTester->getStatusCode());

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