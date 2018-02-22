<?php


namespace Keboola\StorageApi\Cli\Tests\Command;

use Keboola\StorageApi\Client;

abstract class BaseTest extends \PHPUnit_Framework_TestCase
{

    public function createStorageClient(): Client
    {
        return new Client([
            'url' => TEST_STORAGE_API_URL,
            'token' => TEST_STORAGE_API_TOKEN,
        ]);
    }
}
