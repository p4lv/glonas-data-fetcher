<?php

namespace App\Controller\Api;

use App\Repository\VehicleRepository;
use App\Repository\VehicleTrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles/{vehicleId}/tracks', name: 'api_vehicle_tracks_')]
class VehicleTrackController extends AbstractController
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepository,
        private readonly VehicleTrackRepository $trackRepository
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
        $limit = $request->query->getInt('limit', 100);

        if ($from && $to) {
            $fromDate = new \DateTime($from);
            $toDate = new \DateTime($to);
            $tracks = $this->trackRepository->findByVehicleAndDateRange($vehicle, $fromDate, $toDate);
        } else {
            $tracks = $this->trackRepository->findLatestByVehicle($vehicle, $limit);
        }

        $data = array_map(fn($track) => [
            'id' => $track->getId(),
            'latitude' => $track->getLatitude(),
            'longitude' => $track->getLongitude(),
            'speed' => $track->getSpeed(),
            'course' => $track->getCourse(),
            'altitude' => $track->getAltitude(),
            'satellites' => $track->getSatellites(),
            'timestamp' => $track->getTimestamp()?->format('Y-m-d H:i:s'),
        ], $tracks);

        return $this->json([
            'vehicle' => [
                'id' => $vehicle->getId(),
                'name' => $vehicle->getName(),
            ],
            'tracks' => $data,
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

        $track = $this->trackRepository->find($id);

        if (!$track || $track->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Track not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $track->getId(),
            'latitude' => $track->getLatitude(),
            'longitude' => $track->getLongitude(),
            'speed' => $track->getSpeed(),
            'course' => $track->getCourse(),
            'altitude' => $track->getAltitude(),
            'satellites' => $track->getSatellites(),
            'timestamp' => $track->getTimestamp()?->format('Y-m-d H:i:s'),
            'additionalData' => $track->getAdditionalData(),
            'createdAt' => $track->getCreatedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $vehicleId, int $id): JsonResponse
    {
        $vehicle = $this->vehicleRepository->find($vehicleId);

        if (!$vehicle) {
            return $this->json(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        $track = $this->trackRepository->find($id);

        if (!$track || $track->getVehicle()->getId() !== $vehicleId) {
            return $this->json(['error' => 'Track not found'], Response::HTTP_NOT_FOUND);
        }

        $this->trackRepository->remove($track, true);

        return $this->json(['message' => 'Track deleted successfully']);
    }
}
