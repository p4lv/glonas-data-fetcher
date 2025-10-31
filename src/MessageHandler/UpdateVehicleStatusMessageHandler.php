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
    private const BATCH_SIZE = 25;

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
        $this->logger->info('Starting vehicle status update for all vehicles using /vehicles/getlastdata endpoint');

        try {
            // Get total count for progress tracking
            $totalCount = $this->vehicleRepository->count([]);
            $batchCount = (int)ceil($totalCount / self::BATCH_SIZE);

            $this->logger->info(sprintf('Found %d vehicles in database to update (priority: unknown + never checked, then oldest checked first)', $totalCount));
            $this->logger->info(sprintf('Processing in %d batches of %d vehicles', $batchCount, self::BATCH_SIZE));

            // Process vehicles in batches - fetch each batch from database separately
            // This prevents memory issues and allows clear() to work properly
            // Priority: unknown + never checked first, then oldest checked first
            for ($batchNumber = 0; $batchNumber < $batchCount; $batchNumber++) {
                // Fetch this batch from database with priority sorting
                $offset = $batchNumber * self::BATCH_SIZE;
                $batch = $this->vehicleRepository->findForSyncPrioritized(
                    self::BATCH_SIZE,
                    $offset
                );

                if (empty($batch)) {
                    break;
                }

                $this->processBatchWithGetLastData($batch, $batchNumber + 1, $totalCount);
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

    private function processBatchWithGetLastData(array $batch, int $batchNumber, int $total): void
    {
        $startTime = microtime(true);

        // Extract external IDs from vehicle entities
        $externalIds = array_map(fn($vehicle) => (int)$vehicle->getExternalId(), $batch);

        $this->logger->info(sprintf(
            'Fetching data for batch %d with %d vehicle IDs',
            $batchNumber,
            count($externalIds)
        ));

        // Fetch data from API using getlastdata endpoint
        try {
            $vehiclesData = $this->apiClient->getLastData($externalIds);

            $this->logger->info(sprintf('Received %d vehicle records from API', count($vehiclesData)));

            // Create a map of externalId => vehicleData for quick lookup
            $dataMap = [];
            foreach ($vehiclesData as $vehicleData) {
                $vehicleId = $vehicleData['vehicleId'] ?? null;
                if ($vehicleId) {
                    $dataMap[$vehicleId] = $vehicleData;
                }
            }

            // Update each vehicle with data from API
            foreach ($batch as $vehicle) {
                $externalId = (int)$vehicle->getExternalId();
                if (isset($dataMap[$externalId])) {
                    $this->updateVehicleDataFromGetLastData($vehicle, $dataMap[$externalId]);
                } else {
                    // No data from API - set status to no_data
                    $vehicle->setGpsStatus('no_data');
                    $vehicle->setConnectionStatus('no_data');
                    $vehicle->setStatusCheckedAt(new \DateTime());
                    $vehicle->setUpdatedAt(new \DateTime());

                    $this->logger->debug(sprintf(
                        'No data returned from API for vehicle %s (%d) - set status to no_data',
                        $vehicle->getName(),
                        $externalId
                    ));
                }
            }

            $this->entityManager->flush();
            $this->entityManager->clear();

            // Force garbage collection every 10 batches to prevent memory buildup
            if ($batchNumber % 10 === 0) {
                gc_collect_cycles();
            }

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
        } catch (\Exception $e) {
            $this->logger->error(sprintf('Failed to process batch %d: %s', $batchNumber, $e->getMessage()));
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

    private function updateVehicleDataFromGetLastData($vehicle, array $vehicleData): void
    {
        // Check if API returned data but all GPS fields are null
        $hasGpsData = (isset($vehicleData['latitude']) && $vehicleData['latitude'] !== null) ||
                      (isset($vehicleData['longitude']) && $vehicleData['longitude'] !== null) ||
                      (isset($vehicleData['recordTime']) && $vehicleData['recordTime'] !== null);

        if (!$hasGpsData) {
            // API returned vehicle but no GPS data - set status to no_data
            $vehicle->setGpsStatus('no_data');
            $vehicle->setConnectionStatus('no_data');
            $vehicle->setStatusCheckedAt(new \DateTime());
            $vehicle->setUpdatedAt(new \DateTime());

            $this->logger->debug(sprintf(
                'Vehicle %s (%s) exists in API but has no GPS data - set status to no_data',
                $vehicle->getName(),
                $vehicle->getExternalId()
            ));
            return;
        }

        // Update GPS coordinates if available
        if (isset($vehicleData['latitude']) && $vehicleData['latitude'] !== null) {
            $vehicle->setLatitude((float)$vehicleData['latitude']);
        }

        if (isset($vehicleData['longitude']) && $vehicleData['longitude'] !== null) {
            $vehicle->setLongitude((float)$vehicleData['longitude']);
        }

        if (isset($vehicleData['speed']) && $vehicleData['speed'] !== null) {
            $vehicle->setSpeed((float)$vehicleData['speed']);
        }

        if (isset($vehicleData['course']) && $vehicleData['course'] !== null) {
            $vehicle->setCourse((float)$vehicleData['course']);
        }

        // Update last position time from recordTime
        if (isset($vehicleData['recordTime']) && $vehicleData['recordTime'] !== null) {
            try {
                $vehicle->setLastPositionTime(new \DateTime($vehicleData['recordTime']));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse recordTime: " . $e->getMessage());
            }
        }

        // Update GPS status based on last position time
        $vehicle->updateGpsStatus();

        // Update timestamp
        $vehicle->setUpdatedAt(new \DateTime());

        $this->logger->debug(sprintf(
            'Updated vehicle: %s (%s) - GPS Status: %s, Speed: %s, Lat/Lon: %s/%s',
            $vehicle->getName(),
            $vehicle->getExternalId(),
            $vehicle->getGpsStatus(),
            $vehicleData['speed'] ?? 'null',
            $vehicleData['latitude'] ?? 'null',
            $vehicleData['longitude'] ?? 'null'
        ));
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
