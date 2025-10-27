<?php

namespace App\Command;

use App\Message\ParseVehiclesMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:parse:vehicles',
    description: 'Parse vehicles from Glonass API',
)]
class ParseVehiclesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('async', null, InputOption::VALUE_NONE, 'Run parsing asynchronously via Messenger')
            ->setHelp('This command fetches all vehicles from the Glonass API and stores them in the database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Parsing Vehicles from Glonass API');

        $message = new ParseVehiclesMessage();

        if ($input->getOption('async')) {
            $this->messageBus->dispatch($message);
            $io->success('Vehicles parsing job has been queued for async execution.');
        } else {
            $this->messageBus->dispatch($message);
            $io->success('Vehicles parsing completed successfully.');
        }

        return Command::SUCCESS;
    }
}
