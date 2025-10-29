<?php

namespace App\Tests\Message;

use App\Message\ParseVehicleTracksMessage;
use PHPUnit\Framework\TestCase;

class ParseVehicleTracksMessageTest extends TestCase
{
    public function testConstructorSetsAllFields(): void
    {
        $vehicleId = 'vehicle-123';
        $from = new \DateTime('2024-01-01 00:00:00');
        $to = new \DateTime('2024-01-31 23:59:59');

        $message = new ParseVehicleTracksMessage($vehicleId, $from, $to);

        $this->assertEquals($vehicleId, $message->getVehicleId());
        $this->assertSame($from, $message->getFrom());
        $this->assertSame($to, $message->getTo());
    }

    public function testGetVehicleIdReturnsCorrectValue(): void
    {
        $vehicleId = 'test-vehicle-456';
        $from = new \DateTime('2024-01-01');
        $to = new \DateTime('2024-01-31');

        $message = new ParseVehicleTracksMessage($vehicleId, $from, $to);

        $this->assertIsString($message->getVehicleId());
        $this->assertEquals($vehicleId, $message->getVehicleId());
    }

    public function testGetFromReturnsCorrectDateTime(): void
    {
        $vehicleId = 'vehicle-789';
        $from = new \DateTime('2024-02-01 10:30:00');
        $to = new \DateTime('2024-02-28 18:45:00');

        $message = new ParseVehicleTracksMessage($vehicleId, $from, $to);

        $this->assertInstanceOf(\DateTimeInterface::class, $message->getFrom());
        $this->assertEquals('2024-02-01 10:30:00', $message->getFrom()->format('Y-m-d H:i:s'));
    }

    public function testGetToReturnsCorrectDateTime(): void
    {
        $vehicleId = 'vehicle-999';
        $from = new \DateTime('2024-03-01');
        $to = new \DateTime('2024-03-31');

        $message = new ParseVehicleTracksMessage($vehicleId, $from, $to);

        $this->assertInstanceOf(\DateTimeInterface::class, $message->getTo());
        $this->assertEquals('2024-03-31', $message->getTo()->format('Y-m-d'));
    }
}
