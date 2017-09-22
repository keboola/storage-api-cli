<?php
/**
 * Created by JetBrains PhpStorm.
 * User: martinhalamicek
 * Date: 5/16/13
 * Time: 2:34 PM
 * To change this template use File | Settings | File Templates.
 */

namespace Keboola\StorageApi\Cli\Console;

use Keboola\StorageApi\Client;
use Keboola\StorageApi\Cli\Command;
use Keboola\Symfony\Console\Helper\NestedFormatterHelper\NestedFormatterHelper;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    const VERSION = '1.0.0';
    /**
     * @var \Keboola\StorageApi\Client
     */
    private $sapiClient;

    /**
     * @var string Token
     */
    private $sapiToken;

    /**
     * @var string SAPI url
     */
    private $sapiUrl;

    /**
     * @var OutputInterface
     */
    private $output;


    public function __construct()
    {
        parent::__construct('Keboola Storage API CLI', self::VERSION);

        $this->getDefinition()
            ->addOption(new InputOption('token', null, InputOption::VALUE_REQUIRED, "Storage API Token"));

        $this->getDefinition()
            ->addOption(new InputOption('url', null, InputOption::VALUE_REQUIRED, "Storage API URL"));
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()
            ->setStyle('success', new OutputFormatterStyle('green'));

        $output->getFormatter()
            ->setStyle('warning', new OutputFormatterStyle('yellow'));

        if ($input->getParameterOption('--token')) {
            $this->sapiToken = $input->getParameterOption('--token');
            $this->sapiClient = null;
        }

        if ($input->getParameterOption('--url')) {
            $this->sapiUrl = $input->getParameterOption('--url');
        }

        $this->output = $output;
        return parent::doRun($input, $output);
    }

    /**
     * @return Client
     */
    public function getStorageApiClient()
    {
        if (!$this->sapiClient) {
            if (!$this->sapiToken) {
                throw new \RuntimeException('Token --token must be set');
            }
            $runId = $this->getRunId();
            $this->sapiClient = new Client([
                'token' => $this->sapiToken,
                'url' => $this->sapiUrl,
                'userAgent' => $this->userAgent(),
            ]);
            if ($runId) {
                $this->sapiClient->setRunId($runId);
            }
            $logData = $this->sapiClient->verifyToken();
            $this->output->writeln("Authorized as: {$logData['description']} ({$logData['owner']['name']})");
        }
        return $this->sapiClient;
    }

    private function getRunId()
    {
        return getenv('KBC_RUN_ID') ? getenv('KBC_RUN_ID') : getenv('KBC_RUNID');
    }

    public function userAgent()
    {
        return "{$this->getName()}/{$this->getVersion()}";
    }

    public function getDefaultCommands()
    {
        return array_merge(array(
            new Command\ListBuckets(),
            new Command\CopyBucket(),
            new Command\DeleteBucket(),
            new Command\CreateTable(),
            new Command\DeleteTable(),
            new Command\WriteTable(),
            new Command\CopyTable(),
            new Command\CreateBucket(),
            new Command\RestoreTableFromImports(),
            new Command\TruncateTable(),
            new Command\ListEvents(),
            new Command\ExportTable(),
            new Command\BackupProject(),
            new Command\RestoreProject(),
            new Command\PurgeProject(),
            new Command\DeleteMetadata(),
        ), parent::getDefaultCommands());
    }

    protected function getDefaultHelperSet()
    {
        $helperSet = parent::getDefaultHelperSet();
        $helperSet->set(new NestedFormatterHelper());
        return $helperSet;
    }
}
