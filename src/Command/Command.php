<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 3:10 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Command;

use Keboola\Symfony\Console\Helper\NestedFormatterHelper\NestedFormatterHelper;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Keboola\StorageApi\Client;
use Keboola\StorageApi\Cli\Console\Application;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class Command extends BaseCommand
{

    /**
     * @var Client
     */
    private $sapiClient;

    /**
     * @var string
     */
    private $tmpDir = "";

    /**
     * @param Client $client
     * @return $this
     */
    public function setSapiClient(Client $client)
    {
        $this->sapiClient = $client;
        return $this;
    }


    public function getSapiClient(): Client
    {
        if ($this->sapiClient === null) {
            $application = $this->getApplication();
            if ($application instanceof Application) {
                $this->setSapiClient($application->getStorageApiClient());
            } else {
                throw new \RuntimeException("Storage api client must be injected.");
            }
        }

        return $this->sapiClient;
    }

    /**
     *
     * Get or create temporary folder
     *
     * @return string
     */
    public function getTmpDir(): string
    {
        if ($this->tmpDir === "") {
            $fs = new Filesystem();
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "sapi-cli-" . uniqid();
            $fs->mkdir($dir);
            $this->tmpDir = $dir;
        }
        return $this->tmpDir;
    }

    /**
     * Deletes temporary dir and all its contents
     */
    public function destroyTmpDir(): void
    {
        if ($this->tmpDir !== "") {
            $fs = new Filesystem();
            $fs->remove($this->tmpDir);
        }
        $this->tmpDir = "";
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // require sapi client
        $this->getSapiClient();
    }

    public function getFormatterHelper(): FormatterHelper
    {
        return $this->getHelper('formatter');
    }

    public function getQuestionHelper(): QuestionHelper
    {
        return $this->getHelper('question');
    }

    public function getNestedFormatterHelper(): NestedFormatterHelper
    {
        return $this->getHelper('nestedFormatter');
    }

    public function getUserAgent(): string
    {
        $application = $this->getApplication();
        assert($application instanceof Application);
        return $application->userAgent();
    }
}
