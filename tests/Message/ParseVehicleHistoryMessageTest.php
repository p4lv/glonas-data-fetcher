<?php

namespace App\Tests\Message;

use App\Message\ParseVehicleHistoryMessage;
use PHPUnit\Framework\TestCase;

class ParseVehicleHistoryMessageTest extends TestCase
{
    public function testConstructorWithRequiredFieldsOnly(): void
    {
        $vehicleId = 'vehicle-123';

        $message = new ParseVehicleHistoryMessage($vehicleId);

        $this->assertEquals($vehicleId, $message->getVehicleId());
        $this->assertNull($message->getFrom());
        $this->assertNull($message->getTo());
    }

    public function testConstructorWithAllFields(): void
    {
        $vehicleId = 'vehicle-456';
        $from = new \DateTime('2024-01-01');
        $to = new \DateTime('2024-01-31');

        $message = new ParseVehicleHistoryMessage($vehicleId, $from, $to);

        $this->assertEquals($vehicleId, $message->getVehicleId());
        $this->assertSame($from, $message->getFrom());
        $this->assertSame($to, $message->getTo());
    }

    public function testGetVehicleIdReturnsCorrectValue(): void
    {
        $vehicleId = 'test-vehicle';
        $message = new ParseVehicleHistoryMessage($vehicleId);

        $this->assertIsString($message->getVehicleId());
        $this->assertEquals($vehicleId, $message->getVehicleId());
    }

    public function testDateTimeInterfacesAreOptional(): void
    {
        $message = new ParseVehicleHistoryMessage('vehicle-789', null, null);

        $this->assertNull($message->getFrom());
        $this->assertNull($message->getTo());
    }
}
