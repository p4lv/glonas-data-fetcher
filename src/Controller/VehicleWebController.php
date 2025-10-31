<?php

namespace App\Controller;

use App\Entity\Vehicle;
use App\Message\UpdateVehicleStatusMessage;
use App\Repository\VehicleRepository;
use App\Service\GlonassApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles', name: 'vehicles_')]
class VehicleWebController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(Request $request, VehicleRepository $vehicleRepository): Response
    {
        // Get pagination and search parameters
        $searchQuery = $request->query->get('q', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('limit', 25);
        $gpsStatusFilter = $request->query->get('status', '');
        $deviceTypeFilter = $request->query->get('device_type', '');

        // Validate perPage to allowed values
        $allowedLimits = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedLimits, true)) {
            $perPage = 25;
        }

        // Validate GPS status filter
        $validStatuses = ['online', 'offline', 'no_data', 'unknown'];
        if (!in_array($gpsStatusFilter, $validStatuses, true)) {
            $gpsStatusFilter = null;
        }

        // Validate device type filter
        $validDeviceTypes = ['gps_tracker', 'beacon'];
        if (!in_array($deviceTypeFilter, $validDeviceTypes, true)) {
            $deviceTypeFilter = null;
        }

        // Get paginated and filtered results
        $result = $vehicleRepository->findWithPaginationAndSearch(
            $searchQuery,
            $page,
            $perPage,
            $gpsStatusFilter,
            $deviceTypeFilter
        );

        $vehicles = $result['results'];
        $total = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        // Get GPS status statistics
        $gpsStats = $vehicleRepository->getGpsStatusStatistics();

        // Get device type statistics
        $deviceTypeStats = $vehicleRepository->getDeviceTypeStatistics();

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $vehicles,
            'total' => $total,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'gpsStatusFilter' => $gpsStatusFilter,
            'deviceTypeFilter' => $deviceTypeFilter,
            'gpsStats' => $gpsStats,
            'deviceTypeStats' => $deviceTypeStats,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Vehicle $vehicle): Response
    {
        return $this->render('vehicle/show.html.twig', [
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/{id}/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(
        Vehicle $vehicle,
        GlonassApiClient $apiClient,
        EntityManagerInterface $entityManager
    ): Response {
        try {
            // Get latest data from API
            $lastData = $apiClient->getLastDataForVehicle($vehicle->getExternalId());

            if (!$lastData) {
                // Set GPS status to no_data when API returns no data
                $vehicle->setGpsStatus('no_data');
                $vehicle->setConnectionStatus('no_data');
                $vehicle->setStatusCheckedAt(new \DateTime());
                $vehicle->setUpdatedAt(new \DateTime());

                $entityManager->flush();

                $this->addFlash('warning', 'No data returned from API for this vehicle. Status set to NO_DATA.');
                return $this->redirectToRoute('vehicles_show', ['id' => $vehicle->getId()]);
            }

            // Check if API returned data but all GPS fields are null
            $hasGpsData = (isset($lastData['latitude']) && $lastData['latitude'] !== null) ||
                          (isset($lastData['longitude']) && $lastData['longitude'] !== null) ||
                          (isset($lastData['recordTime']) && $lastData['recordTime'] !== null);

            if (!$hasGpsData) {
                // Vehicle exists in API but has no GPS data
                $vehicle->setGpsStatus('no_data');
                $vehicle->setConnectionStatus('no_data');
                $vehicle->setStatusCheckedAt(new \DateTime());
                $vehicle->setUpdatedAt(new \DateTime());

                $entityManager->flush();

                $this->addFlash('warning', 'Vehicle exists in API but has no GPS data. Status set to NO_DATA.');
                return $this->redirectToRoute('vehicles_show', ['id' => $vehicle->getId()]);
            }

            // Update vehicle with fresh data
            if (isset($lastData['latitude']) && $lastData['latitude'] !== null) {
                $vehicle->setLatitude((float)$lastData['latitude']);
            }

            if (isset($lastData['longitude']) && $lastData['longitude'] !== null) {
                $vehicle->setLongitude((float)$lastData['longitude']);
            }

            if (isset($lastData['speed']) && $lastData['speed'] !== null) {
                $vehicle->setSpeed((float)$lastData['speed']);
            }

            if (isset($lastData['course']) && $lastData['course'] !== null) {
                $vehicle->setCourse((float)$lastData['course']);
            }

            if (isset($lastData['recordTime']) && $lastData['recordTime'] !== null) {
                try {
                    $vehicle->setLastPositionTime(new \DateTime($lastData['recordTime']));
                } catch (\Exception $e) {
                    // Ignore date parsing errors
                }
            }

            // Update GPS status based on last position time
            $vehicle->updateGpsStatus();
            $vehicle->setUpdatedAt(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', sprintf(
                'Vehicle data refreshed successfully! GPS Status: %s, Speed: %s km/h',
                $vehicle->getGpsStatus(),
                $lastData['speed'] ?? 'N/A'
            ));
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to refresh vehicle data: ' . $e->getMessage());
        }

        return $this->redirectToRoute('vehicles_show', ['id' => $vehicle->getId()]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Vehicle $vehicle, VehicleRepository $vehicleRepository): Response
    {
        $vehicleRepository->remove($vehicle, true);
        $this->addFlash('success', 'Vehicle deleted successfully');

        return $this->redirectToRoute('vehicles_index');
    }
}
