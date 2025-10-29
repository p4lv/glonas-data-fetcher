<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:vehicle',
    description: 'Test getting a specific vehicle by ID',
)]
class TestVehicleCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('vehicle-id', InputArgument::REQUIRED, 'Vehicle ID to test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $vehicleId = $input->getArgument('vehicle-id');

        $io->title(sprintf('Testing Glonass API - Get Vehicle %s', $vehicleId));

        // Test authentication
        $io->section('Step 1: Authentication');
        try {
            $authenticated = $this->apiClient->authenticate();
            if ($authenticated) {
                $io->success('Authentication successful!');
            } else {
                $io->error('Authentication failed!');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Authentication error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test getting specific vehicle
        $io->section(sprintf('Step 2: Getting vehicle %s', $vehicleId));
        try {
            $vehicle = $this->apiClient->getVehicle($vehicleId);

            if ($vehicle) {
                $io->success('Vehicle data retrieved successfully!');
                $io->writeln(json_encode($vehicle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $io->warning('Vehicle not found or no data returned.');
            }
        } catch (\Exception $e) {
            $io->error('Failed to get vehicle: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $this->apiClient->logout();

        return Command::SUCCESS;
    }
}
