<?php

namespace App\Command;

use App\Service\GlonassApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:pagination',
    description: 'Test if Glonass API supports pagination parameters',
)]
class TestPaginationCommand extends Command
{
    public function __construct(
        private readonly GlonassApiClient $apiClient
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Temporarily increase memory for testing
        $originalLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        $io->text("Memory limit: {$originalLimit} → " . ini_get('memory_limit'));

        $io->title('Testing Glonass API Pagination Support');

        try {
            // Test 1: Get vehicles without pagination
            $io->section('Test 1: No pagination parameters');
            $allVehicles = $this->apiClient->getVehicles([]);
            $totalCount = count($allVehicles);
            $io->text([
                "Response count: <info>{$totalCount}</info> vehicles",
                "Memory usage: <info>" . $this->formatBytes(memory_get_usage(true)) . "</info>",
            ]);

            if ($totalCount === 0) {
                $io->error('No vehicles returned. Cannot test pagination.');
                return Command::FAILURE;
            }

            // Extract some IDs for comparison
            $allIds = array_column($allVehicles, 'vehicleId')
                   ?: array_column($allVehicles, 'Id')
                   ?: array_column($allVehicles, 'VehicleId');

            if (empty($allIds)) {
                $io->warning('Could not extract vehicle IDs. Trying first 3 vehicles:');
                $allIds = array_map(fn($v) => json_encode($v), array_slice($allVehicles, 0, 3));
            }

            $io->text("First 3 IDs: " . implode(', ', array_slice($allIds, 0, 3)));

            // Test 2: Get first 10 with offset=0, limit=10
            $io->section('Test 2: offset=0, limit=10');
            $page1 = $this->apiClient->getVehicles([
                'offset' => 0,
                'limit' => 10,
            ]);
            $page1Count = count($page1);

            $page1Ids = array_column($page1, 'vehicleId')
                     ?: array_column($page1, 'Id')
                     ?: array_column($page1, 'VehicleId');

            if (empty($page1Ids)) {
                $page1Ids = array_map(fn($v) => json_encode($v), array_slice($page1, 0, 3));
            }

            $io->text([
                "Response count: <info>{$page1Count}</info> vehicles",
                "First 3 IDs: " . implode(', ', array_slice($page1Ids, 0, 3)),
                "Memory usage: <info>" . $this->formatBytes(memory_get_usage(true)) . "</info>",
            ]);

            // Test 3: Get next 10 with offset=10, limit=10
            $io->section('Test 3: offset=10, limit=10');
            $page2 = $this->apiClient->getVehicles([
                'offset' => 10,
                'limit' => 10,
            ]);
            $page2Count = count($page2);

            $page2Ids = array_column($page2, 'vehicleId')
                     ?: array_column($page2, 'Id')
                     ?: array_column($page2, 'VehicleId');

            if (empty($page2Ids)) {
                $page2Ids = array_map(fn($v) => json_encode($v), array_slice($page2, 0, 3));
            }

            $io->text([
                "Response count: <info>{$page2Count}</info> vehicles",
                "First 3 IDs: " . implode(', ', array_slice($page2Ids, 0, 3)),
                "Memory usage: <info>" . $this->formatBytes(memory_get_usage(true)) . "</info>",
            ]);

            // Analysis
            $io->section('Analysis');

            $paginationWorks = false;
            $reasons = [];

            // Check 1: Response size changed
            if ($page1Count !== $totalCount) {
                $reasons[] = "✓ Response size changed with pagination ({$page1Count} vs {$totalCount})";
                $paginationWorks = true;
            } else {
                $reasons[] = "✗ Response size unchanged ({$page1Count} = {$totalCount})";
            }

            // Check 2: Limited to requested size
            if ($page1Count <= 10) {
                $reasons[] = "✓ First page respects limit (≤10 vehicles)";
                $paginationWorks = true;
            } else {
                $reasons[] = "✗ First page exceeds limit ({$page1Count} > 10)";
            }

            // Check 3: Different results between pages
            $page1IdsSet = array_slice($page1Ids, 0, 3);
            $page2IdsSet = array_slice($page2Ids, 0, 3);
            $overlap = array_intersect($page1IdsSet, $page2IdsSet);

            if (empty($overlap) && !empty($page1Ids) && !empty($page2Ids)) {
                $reasons[] = "✓ Different IDs between page 1 and page 2";
                $paginationWorks = true;
            } else {
                $reasons[] = "✗ Same IDs in both pages (overlap found)";
            }

            $io->listing($reasons);

            if ($paginationWorks) {
                $io->success([
                    'Pagination WORKS! ✓',
                    'The API correctly responds to offset/limit parameters.',
                    'You can use paginated requests to reduce memory usage.',
                ]);
            } else {
                $io->warning([
                    'Pagination does NOT work ✗',
                    'The API ignores offset/limit parameters.',
                    'You will need to use streaming JSON parsing or increase memory limit.',
                ]);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error([
                'Test failed with error:',
                $e->getMessage(),
                '',
                'Peak memory: ' . $this->formatBytes(memory_get_peak_usage(true)),
            ]);
            return Command::FAILURE;
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
