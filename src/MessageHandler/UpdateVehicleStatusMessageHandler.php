<?php

namespace App\MessageHandler;

use App\Message\UpdateVehicleStatusMessage;
use App\Repository\VehicleRepository;
use App\Service\GlonassApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateVehicleStatusMessageHandler
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly GlonassApiClient $apiClient,
        private readonly VehicleRepository $vehicleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(UpdateVehicleStatusMessage $message): void
    {
        $vehicleId = $message->getVehicleId();

        if ($vehicleId !== null) {
            $this->updateSingleVehicle($vehicleId);
        } else {
            $this->updateAllVehicles($message->getFilters());
        }
    }

    private function updateSingleVehicle(int $vehicleId): void
    {
        $this->logger->info("Updating status for vehicle: {$vehicleId}");

        try {
            $vehicleData = $this->apiClient->getVehicle((string)$vehicleId);

            if (!$vehicleData) {
                $this->logger->warning("Vehicle {$vehicleId} not found in API");
                return;
            }

            $vehicle = $this->vehicleRepository->findOneBy(['externalId' => (string)$vehicleId]);

            if (!$vehicle) {
                $this->logger->warning("Vehicle {$vehicleId} not found in database");
                return;
            }

            $this->updateVehicleData($vehicle, $vehicleData);
            $this->entityManager->flush();

            $this->logger->info("Status updated for vehicle: {$vehicleId}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to update vehicle {$vehicleId}: " . $e->getMessage());
            throw $e;
        }
    }

    private function updateAllVehicles(array $filters): void
    {
        $this->logger->info('Starting vehicle status update for all vehicles');

        try {
            $vehicles = $this->apiClient->getVehicles($filters);
            $totalCount = count($vehicles);

            $this->logger->info(sprintf('Found %d vehicles to update', $totalCount));

            // Process in batches
            $batches = array_chunk($vehicles, self::BATCH_SIZE);
            $batchCount = count($batches);

            $this->logger->info(sprintf('Processing in %d batches of %d vehicles', $batchCount, self::BATCH_SIZE));

            foreach ($batches as $batchNumber => $batch) {
                $this->processBatch($batch, $batchNumber + 1, $totalCount);
            }

            $this->logger->info('Vehicle status update completed successfully', [
                'total' => $totalCount,
                'batches' => $batchCount,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Vehicle status update failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processBatch(array $batch, int $batchNumber, int $total): void
    {
        $startTime = microtime(true);

        foreach ($batch as $vehicleData) {
            $this->processVehicleData($vehicleData);
        }

        $this->entityManager->flush();
        $this->entityManager->clear();

        $duration = microtime(true) - $startTime;
        $processed = $batchNumber * self::BATCH_SIZE;
        $processed = min($processed, $total);

        $this->logger->info(sprintf(
            'Batch %d/%d processed (%d/%d vehicles) in %.2f seconds',
            $batchNumber,
            (int)ceil($total / self::BATCH_SIZE),
            $processed,
            $total,
            $duration
        ));
    }

    private function processVehicleData(array $vehicleData): void
    {
        $externalId = $vehicleData['vehicleId'] ?? $vehicleData['Id'] ?? $vehicleData['VehicleId'] ?? $vehicleData['vehicleGuid'] ?? null;

        if (!$externalId) {
            $this->logger->warning('Vehicle without ID found, skipping', ['data' => array_keys($vehicleData)]);
            return;
        }

        $vehicle = $this->vehicleRepository->findOneBy(['externalId' => (string)$externalId]);

        if (!$vehicle) {
            $this->logger->debug("Vehicle {$externalId} not found in database, skipping status update");
            return;
        }

        $this->updateVehicleData($vehicle, $vehicleData);
        $this->entityManager->persist($vehicle);
    }

    private function updateVehicleData($vehicle, array $vehicleData): void
    {
        // Update GPS coordinates if available
        if (isset($vehicleData['latitude']) || isset($vehicleData['Latitude'])) {
            $vehicle->setLatitude((float)($vehicleData['latitude'] ?? $vehicleData['Latitude']));
        }

        if (isset($vehicleData['longitude']) || isset($vehicleData['Longitude'])) {
            $vehicle->setLongitude((float)($vehicleData['longitude'] ?? $vehicleData['Longitude']));
        }

        if (isset($vehicleData['speed']) || isset($vehicleData['Speed'])) {
            $vehicle->setSpeed((float)($vehicleData['speed'] ?? $vehicleData['Speed']));
        }

        if (isset($vehicleData['course']) || isset($vehicleData['Course'])) {
            $vehicle->setCourse((float)($vehicleData['course'] ?? $vehicleData['Course']));
        }

        // Update last position time
        if (isset($vehicleData['lastPositionTime']) || isset($vehicleData['LastPositionTime'])) {
            try {
                $dateStr = $vehicleData['lastPositionTime'] ?? $vehicleData['LastPositionTime'];
                $vehicle->setLastPositionTime(new \DateTime($dateStr));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse date: " . $e->getMessage());
            }
        }

        // Update GPS status based on last position time
        $vehicle->updateGpsStatus();

        // Update timestamp
        $vehicle->setUpdatedAt(new \DateTime());

        $this->logger->debug(sprintf(
            'Updated vehicle: %s (%s) - GPS Status: %s',
            $vehicle->getName(),
            $vehicle->getExternalId(),
            $vehicle->getGpsStatus()
        ));
    }
}
