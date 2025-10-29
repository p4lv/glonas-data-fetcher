<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:rate-limit',
    description: 'Test rate limiting - make multiple requests to verify 1 second delay',
)]
class TestRateLimitCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', 'c', InputOption::VALUE_REQUIRED, 'Number of requests to make', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getOption('count');

        $io->title(sprintf('Testing Rate Limiting with %d requests', $count));

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

        $io->section(sprintf('Step 2: Making %d API requests', $count));
        $io->writeln('Each request should wait at least 1 second from the previous one.');
        $io->newLine();

        $times = [];
        $lastStartTime = null;

        for ($i = 1; $i <= $count; $i++) {
            $io->writeln(sprintf('[%d/%d] Making request...', $i, $count));

            $startTime = microtime(true);

            try {
                // Try to get vehicles (will fail with 403/429, but that's ok - we're testing timing)
                $this->apiClient->getVehicles([]);
            } catch (\Exception $e) {
                // Ignore errors - we're just testing timing
                $io->writeln(sprintf('  → Error (expected): %s', substr($e->getMessage(), 0, 100)));
            }

            $endTime = microtime(true);
            $timeSinceLast = $lastStartTime !== null ? ($startTime - $lastStartTime) : 0;
            $requestDuration = $endTime - $startTime;

            $times[] = [
                'request' => $i,
                'time_since_last' => $timeSinceLast,
                'request_duration' => $requestDuration,
            ];

            $io->writeln(sprintf(
                '  → Time since last request: %.3f seconds',
                $timeSinceLast
            ));
            $io->writeln(sprintf(
                '  → Request duration: %.3f seconds',
                $requestDuration
            ));

            if ($timeSinceLast < 1.0 && $i > 1) {
                $io->error(sprintf('⚠️  WARNING: Only %.3f seconds elapsed! Should be >= 1.0', $timeSinceLast));
            } else if ($i > 1) {
                $io->success(sprintf('✓ OK: %.3f seconds elapsed', $timeSinceLast));
            }

            $io->newLine();
            $lastStartTime = $startTime;
        }

        // Summary
        $io->section('Summary');

        $table = $io->createTable();
        $table->setHeaders(['Request #', 'Time Since Last (s)', 'Request Duration (s)', 'Status']);

        foreach ($times as $time) {
            $status = $time['request'] === 1 ? 'First' : ($time['time_since_last'] >= 1.0 ? '✓ OK' : '⚠️  TOO FAST');
            $table->addRow([
                $time['request'],
                sprintf('%.3f', $time['time_since_last']),
                sprintf('%.3f', $time['request_duration']),
                $status,
            ]);
        }

        $table->render();

        // Check if all requests (except first) had >= 1 second delay
        $violations = 0;
        foreach ($times as $time) {
            if ($time['request'] > 1 && $time['time_since_last'] < 1.0) {
                $violations++;
            }
        }

        if ($violations > 0) {
            $io->error(sprintf('Rate limiting FAILED! %d requests violated the 1-second rule.', $violations));
            return Command::FAILURE;
        } else {
            $io->success('Rate limiting works correctly! All requests had >= 1 second delay.');
            return Command::SUCCESS;
        }
    }
}
