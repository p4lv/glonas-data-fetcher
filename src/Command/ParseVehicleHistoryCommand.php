<?php

namespace App\Command;

use App\Message\ParseVehicleHistoryMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:parse:vehicle-history',
    description: 'Parse command history for a specific vehicle',
)]
class ParseVehicleHistoryCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('vehicle-id', InputArgument::REQUIRED, 'Vehicle ID')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date (Y-m-d H:i:s)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date (Y-m-d H:i:s)')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Run parsing asynchronously via Messenger')
            ->setHelp('This command fetches command history for a specific vehicle from the Glonass API.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $vehicleId = $input->getArgument('vehicle-id');
        $from = $input->getOption('from') ? new \DateTime($input->getOption('from')) : null;
        $to = $input->getOption('to') ? new \DateTime($input->getOption('to')) : null;

        $io->title(sprintf('Parsing Command History for Vehicle: %s', $vehicleId));

        if ($from) {
            $io->writeln(sprintf('From: %s', $from->format('Y-m-d H:i:s')));
        }
        if ($to) {
            $io->writeln(sprintf('To: %s', $to->format('Y-m-d H:i:s')));
        }

        $message = new ParseVehicleHistoryMessage($vehicleId, $from, $to);

        if ($input->getOption('async')) {
            $this->messageBus->dispatch($message);
            $io->success('Command history parsing job has been queued for async execution.');
        } else {
            $this->messageBus->dispatch($message);
            $io->success('Command history parsing completed successfully.');
        }

        return Command::SUCCESS;
    }
}
