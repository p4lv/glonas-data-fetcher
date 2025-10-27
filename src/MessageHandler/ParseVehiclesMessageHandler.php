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

            $this->logger->info(sprintf('Found %d vehicles', count($vehicles)));

            foreach ($vehicles as $vehicleData) {
                $this->processVehicle($vehicleData);
            }

            $this->entityManager->flush();

            $this->logger->info('Vehicles parsing completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Vehicles parsing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processVehicle(array $vehicleData): void
    {
        $externalId = $vehicleData['Id'] ?? $vehicleData['VehicleId'] ?? null;

        if (!$externalId) {
            $this->logger->warning('Vehicle without ID found, skipping');
            return;
        }

        $vehicle = $this->vehicleRepository->findByExternalId($externalId);

        if (!$vehicle) {
            $vehicle = new Vehicle();
            $vehicle->setExternalId($externalId);
            $vehicle->setCreatedAt(new \DateTime());
        }

        // Update vehicle data
        if (isset($vehicleData['Name'])) {
            $vehicle->setName($vehicleData['Name']);
        }

        if (isset($vehicleData['PlateNumber'])) {
            $vehicle->setPlateNumber($vehicleData['PlateNumber']);
        }

        // GPS coordinates
        if (isset($vehicleData['Latitude'])) {
            $vehicle->setLatitude((float)$vehicleData['Latitude']);
        }

        if (isset($vehicleData['Longitude'])) {
            $vehicle->setLongitude((float)$vehicleData['Longitude']);
        }

        if (isset($vehicleData['Speed'])) {
            $vehicle->setSpeed((float)$vehicleData['Speed']);
        }

        if (isset($vehicleData['Course'])) {
            $vehicle->setCourse((float)$vehicleData['Course']);
        }

        if (isset($vehicleData['LastPositionTime'])) {
            try {
                $vehicle->setLastPositionTime(new \DateTime($vehicleData['LastPositionTime']));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse date: " . $e->getMessage());
            }
        }

        // Store all additional data in JSON field
        $vehicle->setAdditionalData($vehicleData);
        $vehicle->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($vehicle);

        $this->logger->debug(sprintf('Processed vehicle: %s (%s)', $vehicle->getName(), $externalId));
    }
}
