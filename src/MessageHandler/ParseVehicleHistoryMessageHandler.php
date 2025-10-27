<?php

namespace App\MessageHandler;

use App\Entity\CommandHistory;
use App\Message\ParseVehicleHistoryMessage;
use App\Repository\CommandHistoryRepository;
use App\Repository\VehicleRepository;
use App\Service\GlonassApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ParseVehicleHistoryMessageHandler
{
    public function __construct(
        private readonly GlonassApiClient $apiClient,
        private readonly VehicleRepository $vehicleRepository,
        private readonly CommandHistoryRepository $commandHistoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ParseVehicleHistoryMessage $message): void
    {
        $vehicleId = $message->getVehicleId();
        $this->logger->info(sprintf('Starting command history parsing for vehicle: %s', $vehicleId));

        try {
            $vehicle = $this->vehicleRepository->findByExternalId($vehicleId);

            if (!$vehicle) {
                $this->logger->error(sprintf('Vehicle not found: %s', $vehicleId));
                return;
            }

            $commands = $this->apiClient->getVehicleCommandHistory(
                $vehicleId,
                $message->getFrom(),
                $message->getTo()
            );

            $this->logger->info(sprintf('Found %d commands for vehicle %s', count($commands), $vehicleId));

            foreach ($commands as $commandData) {
                $this->processCommand($vehicle, $commandData);
            }

            $this->entityManager->flush();

            $this->logger->info('Command history parsing completed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Command history parsing failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function processCommand($vehicle, array $commandData): void
    {
        $commandHistory = new CommandHistory();
        $commandHistory->setVehicle($vehicle);

        if (isset($commandData['Type'])) {
            $commandHistory->setCommandType($commandData['Type']);
        }

        if (isset($commandData['Command'])) {
            $commandHistory->setCommandText($commandData['Command']);
        }

        if (isset($commandData['Response'])) {
            $commandHistory->setResponse($commandData['Response']);
        }

        if (isset($commandData['Latitude'])) {
            $commandHistory->setLatitude((float)$commandData['Latitude']);
        }

        if (isset($commandData['Longitude'])) {
            $commandHistory->setLongitude((float)$commandData['Longitude']);
        }

        if (isset($commandData['SentAt'])) {
            try {
                $commandHistory->setSentAt(new \DateTime($commandData['SentAt']));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse sent date: " . $e->getMessage());
            }
        }

        if (isset($commandData['ReceivedAt'])) {
            try {
                $commandHistory->setReceivedAt(new \DateTime($commandData['ReceivedAt']));
            } catch (\Exception $e) {
                $this->logger->warning("Failed to parse received date: " . $e->getMessage());
            }
        }

        if (isset($commandData['Status'])) {
            $commandHistory->setStatus($commandData['Status']);
        }

        $commandHistory->setAdditionalData($commandData);

        $this->entityManager->persist($commandHistory);
    }
}
