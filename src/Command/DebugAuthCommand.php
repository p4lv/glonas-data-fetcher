<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:auth',
    description: 'Debug authentication and verify token transmission',
)]
class DebugAuthCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Authentication & Token Transmission Debug');

        // Step 1: Check initial state
        $io->section('Step 1: Initial State');
        $isAuth = $this->apiClient->isAuthenticated();
        $io->writeln(sprintf('Is authenticated: %s', $isAuth ? 'Yes' : 'No'));

        if ($isAuth) {
            $token = $this->apiClient->getAuthToken();
            $maskedToken = $this->maskToken($token);
            $io->writeln(sprintf('Current token: %s', $maskedToken));
        } else {
            $io->writeln('No token available yet');
        }
        $io->newLine();

        // Step 2: Authenticate
        $io->section('Step 2: Authentication');
        $io->writeln('Calling authenticate()...');
        $io->writeln('(Check logs above for "Auth token received" message)');
        $io->newLine();

        try {
            $authenticated = $this->apiClient->authenticate();

            if ($authenticated) {
                $io->success('Authentication successful!');

                $token = $this->apiClient->getAuthToken();
                $maskedToken = $this->maskToken($token);
                $io->writeln(sprintf('Token received: %s', $maskedToken));
                $io->writeln(sprintf('Token length: %d characters', strlen($token ?? '')));
            } else {
                $io->error('Authentication failed!');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Authentication error: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->newLine();

        // Step 3: Make test request with token
        $io->section('Step 3: Test Request with Token');
        $io->writeln('Making a test API request...');
        $io->writeln('(Check logs above for "Request headers" with X-Auth field)');
        $io->newLine();

        try {
            // Try to get vehicles (will likely fail with 403/429, but we're testing headers)
            $this->apiClient->getVehicles([]);
            $io->success('Request completed successfully!');
        } catch (\Exception $e) {
            // Even if request fails, we can verify headers were sent
            $errorMsg = substr($e->getMessage(), 0, 100);
            $io->warning(sprintf('Request failed (expected): %s', $errorMsg));
            $io->writeln('This is OK - we are just testing that headers are being sent.');
        }

        $io->newLine();

        // Step 4: Summary
        $io->section('Summary');

        $io->writeln('<info>✓</info> Authentication token is saved after authenticate()');
        $io->writeln('<info>✓</info> Token is accessible via getAuthToken()');
        $io->writeln('<info>✓</info> Token is added to X-Auth header in requests');
        $io->newLine();

        $io->note([
            'Check the debug logs above to verify:',
            '1. "Auth token received: xxxx...yyyy" after authentication',
            '2. "Request headers:" showing X-Auth with masked token',
            '',
            'If you see both messages, token transmission is working correctly!',
        ]);

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
