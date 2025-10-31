<?php

namespace App\Command;

use App\Repository\VehicleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:update:device-types',
    description: 'Update device types and link paired devices (beacons with GPS trackers)',
)]
class UpdateDeviceTypesCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Updating Device Types and Linking Paired Devices');

        try {
            $totalCount = $this->vehicleRepository->count([]);
            $batchCount = (int)ceil($totalCount / self::BATCH_SIZE);

            $io->section(sprintf('Processing %d vehicles in %d batches', $totalCount, $batchCount));

            $beaconCount = 0;
            $trackerCount = 0;
            $linkedCount = 0;

            for ($batchNumber = 0; $batchNumber < $batchCount; $batchNumber++) {
                $offset = $batchNumber * self::BATCH_SIZE;
                $vehicles = $this->vehicleRepository->findBy(
                    [],
                    ['id' => 'ASC'],
                    self::BATCH_SIZE,
                    $offset
                );

                foreach ($vehicles as $vehicle) {
                    // Determine device type
                    $vehicle->determineDeviceType();

                    if ($vehicle->isBeacon()) {
                        $beaconCount++;

                        // Try to find paired GPS tracker
                        $baseName = $vehicle->getBaseName();
                        if ($baseName) {
                            $pairedTracker = $this->vehicleRepository->findPairedDevice($baseName, 'gps_tracker');
                            if ($pairedTracker) {
                                $vehicle->setParentVehicle($pairedTracker);
                                $linkedCount++;
                            }
                        }
                    } else {
                        $trackerCount++;
                    }
                }

                $this->entityManager->flush();
                $this->entityManager->clear();

                $processed = min(($batchNumber + 1) * self::BATCH_SIZE, $totalCount);
                $io->writeln(sprintf('Processed %d/%d vehicles...', $processed, $totalCount));
            }

            $io->success('Device types updated successfully!');
            $io->table(
                ['Type', 'Count'],
                [
                    ['GPS Trackers', $trackerCount],
                    ['Beacons', $beaconCount],
                    ['Linked Pairs', $linkedCount],
                    ['Total', $totalCount],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to update device types: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
