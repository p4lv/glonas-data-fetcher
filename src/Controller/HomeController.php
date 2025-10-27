<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(VehicleRepository $vehicleRepository): Response
    {
        $vehicles = $vehicleRepository->findAll();
        $totalVehicles = count($vehicles);
        $vehiclesWithPosition = count($vehicleRepository->findAllWithRecentPosition());

        return $this->render('home/index.html.twig', [
            'totalVehicles' => $totalVehicles,
            'vehiclesWithPosition' => $vehiclesWithPosition,
            'recentVehicles' => array_slice($vehicles, 0, 10),
        ]);
    }
}
