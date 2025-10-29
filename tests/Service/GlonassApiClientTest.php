<?php

namespace App\Tests\Service;

use App\Service\GlonassApiClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class GlonassApiClientTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private GlonassApiClient $apiClient;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->apiClient = new GlonassApiClient(
            $this->httpClient,
            $this->logger,
            'https://api.test.com',
            'test_login',
            'test_password'
        );
    }

    public function testAuthenticateSuccess(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode(['AuthId' => 'test-token-123']));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://api.test.com/api/v3/auth/login',
                $this->callback(function ($options) {
                    return isset($options['json']['Login']) &&
                           $options['json']['Login'] === 'test_login' &&
                           isset($options['json']['Password']) &&
                           $options['json']['Password'] === 'test_password';
                })
            )
            ->willReturn($response);

        $result = $this->apiClient->authenticate();

        $this->assertTrue($result);
        $this->assertTrue($this->apiClient->isAuthenticated());
        $this->assertNotNull($this->apiClient->getAuthToken());
    }

    public function testAuthenticateFailureNoAuthId(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getContent')->willReturn(json_encode(['error' => 'Invalid credentials']));

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $result = $this->apiClient->authenticate();

        $this->assertFalse($result);
        $this->assertFalse($this->apiClient->isAuthenticated());
    }

    public function testMaskTokenWithValidToken(): void
    {
        $reflection = new \ReflectionClass($this->apiClient);
        $method = $reflection->getMethod('maskToken');
        $method->setAccessible(true);

        $token = 'abcdefgh1234567890';
        $masked = $method->invoke($this->apiClient, $token);

        $this->assertEquals('abcd...7890', $masked);
        $this->assertStringStartsWith('abcd', $masked);
        $this->assertStringEndsWith('7890', $masked);
        $this->assertStringContainsString('...', $masked);
    }

    public function testMaskTokenWithShortToken(): void
    {
        $reflection = new \ReflectionClass($this->apiClient);
        $method = $reflection->getMethod('maskToken');
        $method->setAccessible(true);

        $token = 'short';
        $masked = $method->invoke($this->apiClient, $token);

        $this->assertEquals('****', $masked);
    }

    public function testMaskTokenWithNullToken(): void
    {
        $reflection = new \ReflectionClass($this->apiClient);
        $method = $reflection->getMethod('maskToken');
        $method->setAccessible(true);

        $masked = $method->invoke($this->apiClient, null);

        $this->assertEquals('****', $masked);
    }

    public function testIsAuthenticatedReturnsFalseInitially(): void
    {
        $this->assertFalse($this->apiClient->isAuthenticated());
    }

    public function testGetAuthTokenReturnsNullInitially(): void
    {
        $this->assertNull($this->apiClient->getAuthToken());
    }

    public function testCheckAuthRequiresAuthentication(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to authenticate with Glonass API');

        // Mock failed authentication
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getContent')->willReturn('{}');

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $this->apiClient->checkAuth();
    }

    public function testGetVehiclesRequiresAuthentication(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to authenticate with Glonass API');

        // Mock failed authentication
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(401);
        $response->method('getContent')->willReturn('{}');

        $this->httpClient
            ->method('request')
            ->willReturn($response);

        $this->apiClient->getVehicles();
    }
}
