<?php

declare(strict_types=1);

namespace App\Command;

use App\Inventory\Exporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ExportCommand extends Command
{
    protected static $defaultName = 'app:export';

    public function __construct(private Exporter $exporter)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to export all the database data.')
            ->addArgument('file', InputArgument::OPTIONAL, 'File name to be used for the export.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $file = $input->getArgument('file');
        if (null === $file) {
            $output->write($this->exporter->getExport());
            return Command::SUCCESS;
        }

        $output->writeln([
            'Data Exporter',
            '=============',
            '',
        ]);
        $output->writeln(sprintf('Writing to %s', $file));
        $this->exporter->export($file);
        $output->writeln(sprintf('Done!'));

        return Command::SUCCESS;
    }
}