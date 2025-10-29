<?php

namespace App\Tests\Entity;

use App\Entity\CommandHistory;
use App\Entity\Vehicle;
use PHPUnit\Framework\TestCase;

class CommandHistoryTest extends TestCase
{
    private CommandHistory $commandHistory;

    protected function setUp(): void
    {
        $this->commandHistory = new CommandHistory();
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $history = new CommandHistory();

        $this->assertInstanceOf(\DateTimeInterface::class, $history->getCreatedAt());
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->commandHistory->getId());
    }

    public function testVehicleGetterAndSetter(): void
    {
        $vehicle = new Vehicle();

        $result = $this->commandHistory->setVehicle($vehicle);

        $this->assertSame($this->commandHistory, $result);
        $this->assertSame($vehicle, $this->commandHistory->getVehicle());
    }

    public function testCommandTypeGetterAndSetter(): void
    {
        $commandType = 'GPS_REQUEST';

        $result = $this->commandHistory->setCommandType($commandType);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($commandType, $this->commandHistory->getCommandType());
    }

    public function testCommandTextGetterAndSetter(): void
    {
        $commandText = 'Get current position';

        $result = $this->commandHistory->setCommandText($commandText);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($commandText, $this->commandHistory->getCommandText());
    }

    public function testResponseGetterAndSetter(): void
    {
        $response = 'Position received successfully';

        $result = $this->commandHistory->setResponse($response);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($response, $this->commandHistory->getResponse());
    }

    public function testLatitudeGetterAndSetter(): void
    {
        $latitude = 55.7558;

        $result = $this->commandHistory->setLatitude($latitude);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($latitude, $this->commandHistory->getLatitude());
    }

    public function testLongitudeGetterAndSetter(): void
    {
        $longitude = 37.6173;

        $result = $this->commandHistory->setLongitude($longitude);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($longitude, $this->commandHistory->getLongitude());
    }

    public function testSentAtGetterAndSetter(): void
    {
        $sentAt = new \DateTime('2024-01-15 10:30:00');

        $result = $this->commandHistory->setSentAt($sentAt);

        $this->assertSame($this->commandHistory, $result);
        $this->assertSame($sentAt, $this->commandHistory->getSentAt());
    }

    public function testReceivedAtGetterAndSetter(): void
    {
        $receivedAt = new \DateTime('2024-01-15 10:30:05');

        $result = $this->commandHistory->setReceivedAt($receivedAt);

        $this->assertSame($this->commandHistory, $result);
        $this->assertSame($receivedAt, $this->commandHistory->getReceivedAt());
    }

    public function testStatusGetterAndSetter(): void
    {
        $status = 'SUCCESS';

        $result = $this->commandHistory->setStatus($status);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($status, $this->commandHistory->getStatus());
    }

    public function testAdditionalDataGetterAndSetter(): void
    {
        $data = [
            'retry_count' => 1,
            'timeout' => 30,
        ];

        $result = $this->commandHistory->setAdditionalData($data);

        $this->assertSame($this->commandHistory, $result);
        $this->assertEquals($data, $this->commandHistory->getAdditionalData());
    }

    public function testSettersReturnSelfForFluentInterface(): void
    {
        $vehicle = new Vehicle();
        $sentAt = new \DateTime();
        $receivedAt = new \DateTime();

        $result = $this->commandHistory
            ->setVehicle($vehicle)
            ->setCommandType('TEST')
            ->setCommandText('Test command')
            ->setResponse('Test response')
            ->setLatitude(50.0)
            ->setLongitude(40.0)
            ->setSentAt($sentAt)
            ->setReceivedAt($receivedAt)
            ->setStatus('SUCCESS');

        $this->assertSame($this->commandHistory, $result);
    }
}
