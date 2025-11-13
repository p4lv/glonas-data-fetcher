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
    private const BATCH_SIZE = 200; // Process 100 vehicles at a time

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
            // Use generator for memory-efficient processing
            $vehicleGenerator = $this->apiClient->getVehiclesGenerator($message->getFilters(), self::BATCH_SIZE);

            $processedCount = 0;
            $batchNumber = 0;
            $currentBatch = [];



            foreach ($vehicleGenerator as $vehicleData) {
                $currentBatch[] = $vehicleData;
                $peakMemory = memory_get_peak_usage(true);
                $this->logger->error('Current batch ' , [
                    'peak_memory' => $this->formatBytes($peakMemory)
                ]);
                // When batch is full, process it
                if (count($currentBatch) >= self::BATCH_SIZE) {
                    $batchNumber++;
                    $this->processBatch($currentBatch, $batchNumber);
                    $processedCount += count($currentBatch);
                    $currentBatch = []; // Clear batch
                }
            }

            // Process remaining vehicles in the last batch
            if (!empty($currentBatch)) {
                $batchNumber++;
                $this->processBatch($currentBatch, $batchNumber);
                $processedCount += count($currentBatch);
            }

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

        foreach ($batch as $vehicleData) {
            $this->processVehicle($vehicleData);
        }

        // Flush changes to database
        $this->entityManager->flush();

        // Clear EntityManager to free memory
        $this->entityManager->clear();

        $duration = microtime(true) - $startTime;
        $batchSize = count($batch);

        $this->logger->info(sprintf(
            'Batch %d processed (%d vehicles) in %.2f seconds',
            $batchNumber,
            $batchSize,
            $duration
        ));
    }

    private function processVehicle(array $vehicleData): void
    {
        // Try different ID field names (API uses camelCase)
        $externalId = $vehicleData['vehicleId'] ?? $vehicleData['Id'] ?? $vehicleData['VehicleId'] ?? $vehicleData['vehicleGuid'] ?? null;

        if (!$externalId) {
            $this->logger->warning('Vehicle without ID found, skipping', ['data' => array_keys($vehicleData)]);
            return;
        }

        // Use findOneBy instead of custom method (works after clear())
        $vehicle = $this->vehicleRepository->findOneBy(['externalId' => (string)$externalId]);

        if (!$vehicle) {
            $vehicle = new Vehicle();
            $vehicle->setExternalId((string)$externalId);
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

        // Store all additional data in JSON field
        $vehicle->setAdditionalData($vehicleData);
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
