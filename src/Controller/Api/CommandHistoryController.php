<?php

namespace App\Controller\Api;

use App\Repository\CommandHistoryRepository;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles/{vehicleId}/commands', name: 'api_vehicle_commands_')]
class CommandHistoryController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly CommandHistoryRepository $commandHistoryRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $vehicleId, Request $request): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($vehicleId);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $limit = $request->query->getInt('limit', 50);

        if ($from && $to) {
            $fromDate = new \DateTime($from);
            $toDate = new \DateTime($to);
            $commands = $this->commandHistoryRepository->findByVehicleAndDateRange($vehicle, $fromDate, $toDate);
        } else {
            $commands = $this->commandHistoryRepository->findLatestByVehicle($vehicle, $limit);
        }

        $data = array_map(fn($command) => [
            'id' => $command->getId(),
            'commandType' => $command->getCommandType(),
            'commandText' => $command->getCommandText(),
            'response' => $command->getResponse(),
            'latitude' => $command->getLatitude(),
            'longitude' => $command->getLongitude(),
            'sentAt' => $command->getSentAt()?->format('Y-m-d H:i:s'),
            'receivedAt' => $command->getReceivedAt()?->format('Y-m-d H:i:s'),
            'status' => $command->getStatus(),
        ], $commands);

        return $this->json([
            'vehicle' => [
                'id' => $vehicle->getId(),
                'name' => $vehicle->getName(),
            ],
            'commands' => $data,
            'count' => count($data),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $vehicleId, int $id): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($vehicleId);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $command = $this->commandHistoryRepository->find($id);

        if (!$command || $command->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Command not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $command->getId(),
            'commandType' => $command->getCommandType(),
            'commandText' => $command->getCommandText(),
            'response' => $command->getResponse(),
            'latitude' => $command->getLatitude(),
            'longitude' => $command->getLongitude(),
            'sentAt' => $command->getSentAt()?->format('Y-m-d H:i:s'),
            'receivedAt' => $command->getReceivedAt()?->format('Y-m-d H:i:s'),
            'status' => $command->getStatus(),
            'additionalData' => $command->getAdditionalData(),
            'createdAt' => $command->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $vehicleId, int $id): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($vehicleId);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $command = $this->commandHistoryRepository->find($id);

        if (!$command || $command->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Command not found'], Response::HTTP_NOT_FOUND);
        }

        $this->commandHistoryRepository->remove($command, true);

        return $this->json(['message' => 'Command history deleted successfully']);
    }
}
