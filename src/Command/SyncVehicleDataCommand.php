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
    name: 'app:sync:vehicle-data',
    description: 'Synchronize vehicle data from Glonass API (GPS, speed, status)',
    aliases: ['app:update:vehicle-status']
)]
class SyncVehicleDataCommand extends Command
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

        $io->title('Synchronize Vehicle Data from Glonass API');

        if ($vehicleId) {
            $io->section("Synchronizing data for vehicle: {$vehicleId}");
        } else {
            $io->section('Synchronizing data for all vehicles');
        }

        try {
            $message = new UpdateVehicleStatusMessage(
                $vehicleId ? (int)$vehicleId : null,
                []
            );

            $this->messageBus->dispatch($message);

            if ($async) {
                $io->success('Data synchronization queued. Run messenger:consume to process.');
                $io->note([
                    'This synchronizes GPS coordinates, speed, and status for all vehicles.',
                    'Uses POST /api/v3/vehicles/getlastdata endpoint',
                    'Batch size: 25 vehicles, Rate limit: 2 seconds between requests',
                    'Processes oldest-checked vehicles first',
                    'Processing ~12,000 vehicles takes approximately 17 minutes (486 batches × 2 sec)',
                ]);
            } else {
                $io->success('Data synchronization completed!');
            }

            if ($vehicleId) {
                $io->info("Synchronized vehicle: {$vehicleId}");
            } else {
                $io->info('All vehicles synchronized with latest API data');
                $io->note('GPS status auto-updated based on last position time');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Status update failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
