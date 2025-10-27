<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GlonassApiClient
{
    private const API_VERSION = 'v3';
    private const RATE_LIMIT_DELAY = 1; // 1 second between requests

    private ?string $authToken = null;
    private float $lastRequestTime = 0;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $apiUrl,
        private readonly string $login,
        private readonly string $password
    ) {
    }

    /**
     * Authenticate and get auth token
     */
    public function authenticate(): bool
    {
        try {
            $response = $this->makeRequest('POST', '/auth/login', [
                'Login' => $this->login,
                'Password' => $this->password,
            ], false);

            if (isset($response['AuthId'])) {
                $this->authToken = $response['AuthId'];
                $this->logger->info('Successfully authenticated to Glonass API');
                return true;
            }

            $this->logger->error('Authentication failed: No AuthId in response');
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get list of vehicles
     */
    public function getVehicles(array $filters = []): array
    {
        $this->ensureAuthenticated();

        $response = $this->makeRequest('POST', '/vehicles/find', $filters);

        return $response['Vehicles'] ?? [];
    }

    /**
     * Get single vehicle by ID
     */
    public function getVehicle(string $vehicleId): ?array
    {
        $this->ensureAuthenticated();

        try {
            return $this->makeRequest('GET', "/vehicles/{$vehicleId}");
        } catch (\Exception $e) {
            $this->logger->error("Failed to get vehicle {$vehicleId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get vehicle command history
     */
    public function getVehicleCommandHistory(string $vehicleId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $this->ensureAuthenticated();

        $params = [];
        if ($from) {
            $params['DateFrom'] = $from->format('Y-m-d\TH:i:s');
        }
        if ($to) {
            $params['DateTo'] = $to->format('Y-m-d\TH:i:s');
        }

        $url = "/Vehicles/cmd/{$vehicleId}/history";
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        try {
            $response = $this->makeRequest('GET', $url);
            return $response['Commands'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error("Failed to get command history for vehicle {$vehicleId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get vehicle tracks (requires additional endpoint discovery from API docs)
     */
    public function getVehicleTracks(string $vehicleId, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $this->ensureAuthenticated();

        // This endpoint might vary based on actual API structure
        // Adjust according to actual Glonass API documentation
        $params = [
            'VehicleId' => $vehicleId,
            'DateFrom' => $from->format('Y-m-d\TH:i:s'),
            'DateTo' => $to->format('Y-m-d\TH:i:s'),
        ];

        try {
            $response = $this->makeRequest('POST', '/tracks/find', $params);
            return $response['Tracks'] ?? [];
        } catch (\Exception $e) {
            $this->logger->error("Failed to get tracks for vehicle {$vehicleId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Logout and invalidate token
     */
    public function logout(): void
    {
        if ($this->authToken) {
            try {
                $this->makeRequest('POST', '/auth/logout');
                $this->authToken = null;
                $this->logger->info('Successfully logged out from Glonass API');
            } catch (\Exception $e) {
                $this->logger->error('Logout error: ' . $e->getMessage());
            }
        }
    }

    /**
     * Make HTTP request to API with rate limiting
     */
    private function makeRequest(string $method, string $endpoint, array $data = [], bool $requiresAuth = true): array
    {
        // Enforce rate limiting (1 second between requests)
        $this->enforceRateLimit();

        $url = rtrim($this->apiUrl, '/') . '/api/' . self::API_VERSION . $endpoint;

        $options = [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ];

        if ($requiresAuth && $this->authToken) {
            $options['headers']['X-Auth'] = $this->authToken;
        }

        if (!empty($data)) {
            if ($method === 'GET') {
                $options['query'] = $data;
            } else {
                $options['json'] = $data;
            }
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent();

            if ($statusCode >= 200 && $statusCode < 300) {
                return json_decode($content, true) ?? [];
            }

            throw new \RuntimeException("API request failed with status {$statusCode}: {$content}");
        } catch (TransportExceptionInterface $e) {
            $this->logger->error("Transport error during API request: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Enforce rate limiting
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $timeSinceLastRequest = $now - $this->lastRequestTime;

        if ($timeSinceLastRequest < self::RATE_LIMIT_DELAY) {
            $sleepTime = self::RATE_LIMIT_DELAY - $timeSinceLastRequest;
            usleep((int)($sleepTime * 1000000));
        }

        $this->lastRequestTime = microtime(true);
    }

    /**
     * Ensure we are authenticated
     */
    private function ensureAuthenticated(): void
    {
        if (!$this->authToken) {
            if (!$this->authenticate()) {
                throw new \RuntimeException('Failed to authenticate with Glonass API');
            }
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->authToken !== null;
    }
}
