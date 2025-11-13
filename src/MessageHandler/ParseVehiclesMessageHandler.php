<?php

namespace App\MessageHandler;

use App\Entity\Vehicle;
use App\Message\ParseVehiclesMessage;
use App\Repository\VehicleRepository;
use App\Service\GlonassApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ParseVehiclesMessageHandler
{
    private const BATCH_SIZE = 50; // Process 50 vehicles at a time to reduce memory usage

    public function __construct(
        private readonly GlonassApiClient $apiClient,
        private readonly VehicleRepository $vehicleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ParseVehiclesMessage $message): void
    {
        $this->logger->info('Starting vehicles parsing');

        // TEMPORARY FIX: Increase memory limit to handle large API responses
        // TODO: Replace with proper streaming/pagination once API capabilities are verified
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '1024M');

        $this->logger->info('Memory limit increased', [
            'original' => $originalMemoryLimit,
            'new' => ini_get('memory_limit'),
            'current_usage' => $this->formatBytes(memory_get_usage(true))
        ]);

        try {
            // Fetch all vehicles directly (API doesn't support pagination anyway)
            $this->logger->info('Fetching vehicles from API...');
            $vehicles = $this->apiClient->getVehicles($message->getFilters());
            $totalCount = count($vehicles);

            $this->logger->info("Fetched {$totalCount} vehicles, processing in batches of " . self::BATCH_SIZE);

            // Process in batches to reduce memory usage
            $processedCount = 0;
            $batchNumber = 0;

            // Split into chunks without loading all into memory at once
            for ($offset = 0; $offset < $totalCount; $offset += self::BATCH_SIZE) {
                $batchNumber++;
                $batch = array_slice($vehicles, $offset, self::BATCH_SIZE);

                $this->processBatch($batch, $batchNumber);
                $processedCount += count($batch);

                // Force garbage collection after each batch
                gc_collect_cycles();

                $this->logger->info(sprintf(
                    'Progress: %d/%d vehicles (%.1f%%)',
                    $processedCount,
                    $totalCount,
                    ($processedCount / $totalCount) * 100
                ));
            }

            // Clear the original vehicles array to free memory
            unset($vehicles);

            $peakMemory = memory_get_peak_usage(true);
            $this->logger->info('Vehicles parsing completed successfully', [
                'total' => $processedCount,
                'batches' => $batchNumber,
                'peak_memory' => $this->formatBytes($peakMemory),
                'memory_limit' => ini_get('memory_limit')
            ]);
        } catch (\Throwable $e) {
            $peakMemory = memory_get_peak_usage(true);
            $this->logger->error('Vehicles parsing failed: ' . $e->getMessage(), [
                'peak_memory' => $this->formatBytes($peakMemory)
            ]);
            throw $e;
        }
    }

    private function processBatch(array $batch, int $batchNumber): void
    {
        $startTime = microtime(true);
        $memoryBefore = memory_get_usage(true);

        // Extract all external IDs from the batch
        $externalIds = [];
        foreach ($batch as $vehicleData) {
            $externalId = $vehicleData['vehicleId'] ?? $vehicleData['Id'] ?? $vehicleData['VehicleId'] ?? $vehicleData['vehicleGuid'] ?? null;
            if ($externalId) {
                $externalIds[] = (string)$externalId;
            }
        }

        // Load all existing vehicles in ONE query instead of N queries
        $existingVehicles = [];
        if (!empty($externalIds)) {
            $vehicles = $this->vehicleRepository->findBy(['externalId' => $externalIds]);
            foreach ($vehicles as $vehicle) {
                $existingVehicles[$vehicle->getExternalId()] = $vehicle;
            }
        }

        // Process each vehicle
        foreach ($batch as $vehicleData) {
            $this->processVehicle($vehicleData, $existingVehicles);
        }

        // Flush changes to database
        $this->entityManager->flush();

        // Clear EntityManager to free memory
        $this->entityManager->clear();

        // Explicitly clear the local variables
        unset($existingVehicles, $externalIds, $batch);

        $duration = microtime(true) - $startTime;
        $memoryAfter = memory_get_usage(true);
        $memoryDelta = $memoryAfter - $memoryBefore;

        $this->logger->info(sprintf(
            'Batch %d processed in %.2f seconds | Memory: %s (delta: %s)',
            $batchNumber,
            $duration,
            $this->formatBytes($memoryAfter),
            $this->formatBytes($memoryDelta)
        ));
    }

    private function processVehicle(array $vehicleData, array &$existingVehicles): void
    {
        // Try different ID field names (API uses camelCase)
        $externalId = $vehicleData['vehicleId'] ?? $vehicleData['Id'] ?? $vehicleData['VehicleId'] ?? $vehicleData['vehicleGuid'] ?? null;

        if (!$externalId) {
            $this->logger->warning('Vehicle without ID found, skipping', ['data' => array_keys($vehicleData)]);
            return;
        }

        $externalId = (string)$externalId;

        // Use pre-loaded vehicle from batch query instead of individual findOneBy
        $vehicle = $existingVehicles[$externalId] ?? null;

        if (!$vehicle) {
            $vehicle = new Vehicle();
            $vehicle->setExternalId($externalId);
            $vehicle->setCreatedAt(new \DateTime());
        }

        // Update vehicle data (API uses camelCase field names)
        if (isset($vehicleData['name'])) {
            $vehicle->setName($vehicleData['name']);
        }

        if (isset($vehicleData['plateNumber'])) {
            $vehicle->setPlateNumber($vehicleData['plateNumber']);
        }

        // GPS coordinates (try both camelCase and PascalCase)
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

        if (isset($vehicleData['lastPositionTime']) || isset($vehicleData['LastPositionTime'])) {
            try {
                $dateStr = $vehicleData['lastPositionTime'] ?? $vehicleData['LastPositionTime'];
                $vehicle->setLastPositionTime(new \DateTime($dateStr));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse date: " . $e->getMessage());
            }
        }

        // Store only NON-extracted fields in additionalData to avoid duplication
        // Remove fields that are already stored in dedicated columns
        $additionalData = $vehicleData;
        unset(
            $additionalData['vehicleId'],
            $additionalData['Id'],
            $additionalData['VehicleId'],
            $additionalData['vehicleGuid'],
            $additionalData['name'],
            $additionalData['plateNumber'],
            $additionalData['latitude'],
            $additionalData['Latitude'],
            $additionalData['longitude'],
            $additionalData['Longitude'],
            $additionalData['speed'],
            $additionalData['Speed'],
            $additionalData['course'],
            $additionalData['Course'],
            $additionalData['lastPositionTime'],
            $additionalData['LastPositionTime']
        );

        // Only store if there's actually additional data left
        if (!empty($additionalData)) {
            $vehicle->setAdditionalData($additionalData);
        } else {
            $vehicle->setAdditionalData(null);
        }

        $vehicle->setUpdatedAt(new \DateTime());

        // Update GPS status based on last position time
        $vehicle->updateGpsStatus();

        $this->entityManager->persist($vehicle);

        $this->logger->debug(sprintf(
            'Processed vehicle: %s (%s) - GPS Status: %s',
            $vehicle->getName(),
            $externalId,
            $vehicle->getGpsStatus()
        ));
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
