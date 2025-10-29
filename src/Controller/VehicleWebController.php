<?php

namespace App\Controller;

use App\Entity\Vehicle;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        // Validate perPage to allowed values
        $allowedLimits = [10, 25, 50, 100];
        if (!in_array($perPage, $allowedLimits, true)) {
            $perPage = 25;
        }

        // Validate GPS status filter
        $validStatuses = ['online', 'offline', 'unknown'];
        if (!in_array($gpsStatusFilter, $validStatuses, true)) {
            $gpsStatusFilter = null;
        }

        // Get paginated and filtered results
        $result = $vehicleRepository->findWithPaginationAndSearch(
            $searchQuery,
            $page,
            $perPage,
            $gpsStatusFilter
        );

        $vehicles = $result['results'];
        $total = $result['total'];
        $totalPages = (int) ceil($total / $perPage);

        // Get GPS status statistics
        $gpsStats = $vehicleRepository->getGpsStatusStatistics();

        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $vehicles,
            'total' => $total,
            'currentPage' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
            'searchQuery' => $searchQuery,
            'gpsStatusFilter' => $gpsStatusFilter,
            'gpsStats' => $gpsStats,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'])]
    public function show(Vehicle $vehicle): Response
    {
        return $this->render('vehicle/show.html.twig', [
            'vehicle' => $vehicle,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Vehicle $vehicle, VehicleRepository $vehicleRepository): Response
    {
        $vehicleRepository->remove($vehicle, true);
        $this->addFlash('success', 'Vehicle deleted successfully');

        return $this->redirectToRoute('vehicles_index');
    }
}
