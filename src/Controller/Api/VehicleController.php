<?php

namespace App\Controller\Api;

use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles', name: 'api_vehicles_')]
class VehicleController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $vehicles = $this->vehicleRepository->findAll();

        $data = array_map(fn($vehicle) => [
            'id' => $vehicle->getId(),
            'externalId' => $vehicle->getExternalId(),
            'name' => $vehicle->getName(),
            'plateNumber' => $vehicle->getPlateNumber(),
            'latitude' => $vehicle->getLatitude(),
            'longitude' => $vehicle->getLongitude(),
            'speed' => $vehicle->getSpeed(),
            'course' => $vehicle->getCourse(),
            'lastPositionTime' => $vehicle->getLastPositionTime()?->format('Y-m-d H:i:s'),
            'updatedAt' => $vehicle->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], $vehicles);

        return $this->json($data);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $vehicle->getId(),
            'externalId' => $vehicle->getExternalId(),
            'name' => $vehicle->getName(),
            'plateNumber' => $vehicle->getPlateNumber(),
            'latitude' => $vehicle->getLatitude(),
            'longitude' => $vehicle->getLongitude(),
            'speed' => $vehicle->getSpeed(),
            'course' => $vehicle->getCourse(),
            'lastPositionTime' => $vehicle->getLastPositionTime()?->format('Y-m-d H:i:s'),
            'additionalData' => $vehicle->getAdditionalData(),
            'createdAt' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updatedAt' => $vehicle->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($id);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $this->vehicleRepository->remove($vehicle, true);

        return $this->json(['message' => 'Vehicle deleted successfully']);
    }
}
