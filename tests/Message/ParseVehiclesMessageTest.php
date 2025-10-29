<?php

namespace App\Tests\Message;

use App\Message\ParseVehiclesMessage;
use PHPUnit\Framework\TestCase;

class ParseVehiclesMessageTest extends TestCase
{
    public function testConstructorWithEmptyFilters(): void
    {
        $message = new ParseVehiclesMessage();

        $this->assertIsArray($message->getFilters());
        $this->assertEmpty($message->getFilters());
    }

    public function testConstructorWithFilters(): void
    {
        $filters = [
            'status' => 'active',
            'type' => 'truck',
        ];

        $message = new ParseVehiclesMessage($filters);

        $this->assertSame($filters, $message->getFilters());
    }

    public function testGetFiltersReturnsCorrectData(): void
    {
        $filters = ['key' => 'value'];
        $message = new ParseVehiclesMessage($filters);

        $result = $message->getFilters();

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertEquals('value', $result['key']);
    }
}
