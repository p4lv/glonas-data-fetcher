<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:auth:check',
    description: 'Check authentication status via GET /api/v3/auth/check',
)]
class CheckAuthCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Checking Authentication Status');

        // Step 1: Authenticate
        $io->section('Step 1: Authentication');
        try {
            $authenticated = $this->apiClient->authenticate();
            if ($authenticated) {
                $io->success('Authentication successful!');

                $token = $this->apiClient->getAuthToken();
                $maskedToken = $this->maskToken($token);
                $io->writeln(sprintf('Token: %s', $maskedToken));
            } else {
                $io->error('Authentication failed! Check your credentials.');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Authentication error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->newLine();

        // Step 2: Check auth via API
        $io->section('Step 2: Calling GET /api/v3/auth/check');
        $io->writeln('Checking authentication status with the API...');

        try {
            $response = $this->apiClient->checkAuth();

            $io->success('Auth check successful!');
            $io->newLine();

            $io->writeln('<info>Response from API:</info>');
            $io->writeln(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Display key information if available
            if (!empty($response)) {
                $io->newLine();
                $io->section('Response Details');

                foreach ($response as $key => $value) {
                    if (is_array($value)) {
                        $io->writeln(sprintf('<comment>%s:</comment> %s', $key, json_encode($value, JSON_UNESCAPED_UNICODE)));
                    } else {
                        $io->writeln(sprintf('<comment>%s:</comment> %s', $key, $value));
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Auth check failed!');
            $io->writeln(sprintf('Error: %s', $e->getMessage()));

            $io->newLine();
            $io->note([
                'This could mean:',
                '1. The auth token is invalid or expired',
                '2. The /auth/check endpoint requires specific permissions',
                '3. Your account does not have access to this endpoint',
            ]);

            return Command::FAILURE;
        }
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
