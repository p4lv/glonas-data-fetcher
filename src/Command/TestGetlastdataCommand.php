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
    name: 'app:test:getlastdata',
    description: 'Test getlastdata API endpoint with external vehicle IDs',
)]
class TestGetlastdataCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('vehicleIds', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'External vehicle IDs (space-separated)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $vehicleIds = $input->getArgument('vehicleIds');

        $io->title('Testing POST /api/v3/vehicles/getlastdata');
        $io->section('Testing with external vehicle IDs: ' . implode(', ', $vehicleIds));

        // Authenticate
        if (!$this->apiClient->isAuthenticated()) {
            $io->info('Authenticating...');
            if (!$this->apiClient->authenticate()) {
                $io->error('Authentication failed');
                return Command::FAILURE;
            }
            $io->success('Authenticated successfully');
        }

        // Convert IDs to integers
        $vehicleIds = array_map('intval', $vehicleIds);
        $io->info('Request body: ' . json_encode($vehicleIds));

        // Test getlastdata
        try {
            $data = $this->apiClient->getLastData($vehicleIds);

            if (!empty($data)) {
                $io->success('Last data retrieved successfully!');
                $io->writeln('Response:');
                $io->writeln(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                $io->section('Summary');
                $io->writeln('Number of vehicles returned: ' . count($data));
            } else {
                $io->warning('No data returned for these vehicles');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Request failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
