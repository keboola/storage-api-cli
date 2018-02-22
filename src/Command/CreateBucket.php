<?php

namespace Keboola\StorageApi\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CreateBucket extends Command
{
    public function configure(): void
    {
        $this
            ->setName('create-bucket')
            ->setDescription('Create bucket')
            ->setDefinition([
                new InputArgument('bucketStage', InputArgument::REQUIRED, "Bucket stage"),
                new InputArgument('bucketName', InputArgument::REQUIRED, "Bucket name"),
                new InputArgument('bucketDescription', InputArgument::OPTIONAL, "Bucket description"),
            ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $sapiClient = $this->getSapiClient();
        $bucketId = $sapiClient->createBucket(
            $input->getArgument('bucketName'),
            $input->getArgument('bucketStage'),
            $input->getArgument('bucketDescription')
        );
        $output->writeln("Bucket created: $bucketId");
    }
}
