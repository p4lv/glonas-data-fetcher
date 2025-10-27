<?php

namespace App\MessageHandler;

use App\Entity\VehicleTrack;
use App\Message\ParseVehicleTracksMessage;
use App\Repository\VehicleRepository;
use App\Repository\VehicleTrackRepository;
use App\Service\GlonassApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ParseVehicleTracksMessageHandler
{
    public function __construct(
        private readonly GlonassApiClient $apiClient,
        private readonly VehicleRepository $vehicleRepository,
        private readonly VehicleTrackRepository $trackRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ParseVehicleTracksMessage $message): void
    {
        $vehicleId = $message->getVehicleId();
        $this->logger->info(sprintf('Starting tracks parsing for vehicle: %s', $vehicleId));

        try {
            $vehicle = $this->vehicleRepository->findByExternalId($vehicleId);

            if (!$vehicle) {
                $this->logger->error(sprintf('Vehicle not found: %s', $vehicleId));
                return;
            }

            $tracks = $this->apiClient->getVehicleTracks(
                $vehicleId,
                $message->getFrom(),
                $message->getTo()
            );

            $this->logger->info(sprintf('Found %d track points for vehicle %s', count($tracks), $vehicleId));

            foreach ($tracks as $trackData) {
                $this->processTrack($vehicle, $trackData);
            }

            $this->entityManager->flush();

            $this->logger->info('Tracks parsing completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Tracks parsing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processTrack($vehicle, array $trackData): void
    {
        $track = new VehicleTrack();
        $track->setVehicle($vehicle);

        if (isset($trackData['Latitude'])) {
            $track->setLatitude((float)$trackData['Latitude']);
        }

        if (isset($trackData['Longitude'])) {
            $track->setLongitude((float)$trackData['Longitude']);
        }

        if (isset($trackData['Speed'])) {
            $track->setSpeed((float)$trackData['Speed']);
        }

        if (isset($trackData['Course'])) {
            $track->setCourse((float)$trackData['Course']);
        }

        if (isset($trackData['Altitude'])) {
            $track->setAltitude((float)$trackData['Altitude']);
        }

        if (isset($trackData['Satellites'])) {
            $track->setSatellites((int)$trackData['Satellites']);
        }

        if (isset($trackData['Timestamp']) || isset($trackData['Time'])) {
            try {
                $timeString = $trackData['Timestamp'] ?? $trackData['Time'];
                $track->setTimestamp(new \DateTime($timeString));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse timestamp: " . $e->getMessage());
                $track->setTimestamp(new \DateTime());
            }
        } else {
            $track->setTimestamp(new \DateTime());
        }

        $track->setAdditionalData($trackData);

        $this->entityManager->persist($track);
    }
}
