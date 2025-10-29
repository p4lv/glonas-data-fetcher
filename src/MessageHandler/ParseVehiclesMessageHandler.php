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
    private const BATCH_SIZE = 100; // Process 100 vehicles at a time

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

        try {
            $vehicles = $this->apiClient->getVehicles($message->getFilters());
            $totalCount = count($vehicles);

            $this->logger->info(sprintf('Found %d vehicles', $totalCount));

            // Process in batches to avoid memory exhaustion
            $batches = array_chunk($vehicles, self::BATCH_SIZE);
            $batchCount = count($batches);

            $this->logger->info(sprintf('Processing in %d batches of %d vehicles', $batchCount, self::BATCH_SIZE));

            foreach ($batches as $batchNumber => $batch) {
                $this->processBatch($batch, $batchNumber + 1, $totalCount);
            }

            $this->logger->info('Vehicles parsing completed successfully', [
                'total' => $totalCount,
                'batches' => $batchCount,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Vehicles parsing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processBatch(array $batch, int $batchNumber, int $total): void
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
        $processed = $batchNumber * self::BATCH_SIZE;
        $processed = min($processed, $total); // Don't exceed total

        $this->logger->info(sprintf(
            'Batch %d/%d processed (%d/%d vehicles) in %.2f seconds',
            $batchNumber,
            (int)ceil($total / self::BATCH_SIZE),
            $processed,
            $total,
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
}
