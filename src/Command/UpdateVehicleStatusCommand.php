<?php

namespace App\Command;

use App\Message\UpdateVehicleStatusMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:update:vehicle-status',
    description: 'Update GPS status for all vehicles or specific vehicle',
)]
class UpdateVehicleStatusCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('async', null, InputOption::VALUE_NONE, 'Run asynchronously via Messenger')
            ->addOption('vehicle-id', null, InputOption::VALUE_REQUIRED, 'Update specific vehicle by ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $async = $input->getOption('async');
        $vehicleId = $input->getOption('vehicle-id');

        $io->title('Update Vehicle GPS Status');

        if ($vehicleId) {
            $io->section("Updating status for vehicle: {$vehicleId}");
        } else {
            $io->section('Updating status for all vehicles');
        }

        try {
            $message = new UpdateVehicleStatusMessage(
                $vehicleId ? (int)$vehicleId : null,
                []
            );

            $this->messageBus->dispatch($message);

            if ($async) {
                $io->success('Status update task queued successfully. Run messenger:consume to process.');
            } else {
                $io->success('Status update completed successfully!');
            }

            if ($vehicleId) {
                $io->info("Updated vehicle: {$vehicleId}");
            } else {
                $io->info('All vehicles have been updated with current GPS status');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Status update failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
