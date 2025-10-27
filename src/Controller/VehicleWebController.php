<?php

namespace App\Controller;

use App\Entity\Vehicle;
use App\Repository\VehicleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vehicles', name: 'vehicles_')]
class VehicleWebController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(VehicleRepository $vehicleRepository): Response
    {
        return $this->render('vehicle/index.html.twig', [
            'vehicles' => $vehicleRepository->findAll(),
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
