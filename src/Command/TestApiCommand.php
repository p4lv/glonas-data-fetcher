<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:api',
    description: 'Test Glonass API connection and authentication',
)]
class TestApiCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Testing Glonass API Connection');

        // Test authentication
        $io->section('Step 1: Authentication');
        try {
            $authenticated = $this->apiClient->authenticate();
            if ($authenticated) {
                $io->success('Authentication successful! Token received.');

                // Show token info
                $token = $this->apiClient->getAuthToken();
                $maskedToken = $this->maskToken($token);
                $io->writeln(sprintf('Token: %s (length: %d characters)', $maskedToken, strlen($token ?? '')));
                $io->writeln('Token will be sent in X-Auth header with subsequent requests.');
            } else {
                $io->error('Authentication failed! Check your credentials.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Authentication error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // Test getting vehicles with empty filters
        $io->section('Step 2: Testing GET vehicles with empty filters');
        try {
            $vehicles = $this->apiClient->getVehicles([]);
            $io->success(sprintf('API call successful! Found %d vehicles.', count($vehicles)));

            if (count($vehicles) > 0) {
                $io->writeln('Sample vehicle data:');
                $io->writeln(json_encode(array_slice($vehicles, 0, 2), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } catch (\Exception $e) {
            $io->error('Failed to get vehicles: ' . $e->getMessage());
            $io->note('This might be a permission issue. Your account may not have access to the vehicles/find endpoint.');
        }

        // Try alternative approach - get specific vehicle if user provides ID
        $io->section('Alternative: Try getting a specific vehicle by ID');
        $io->note('If you have a specific vehicle ID, you can try: php bin/console app:test:vehicle [VEHICLE_ID]');

        $this->apiClient->logout();

        return Command::SUCCESS;
    }

    /**
     * Mask token for display
     */
    private function maskToken(?string $token): string
    {
        if (!$token || strlen($token) <= 8) {
            return '****';
        }

        $start = substr($token, 0, 4);
        $end = substr($token, -4);
        return "{$start}...{$end}";
    }
}
