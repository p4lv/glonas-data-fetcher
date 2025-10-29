<?php

namespace App\Tests\Entity;

use App\Entity\Vehicle;
use App\Entity\VehicleTrack;
use App\Entity\CommandHistory;
use PHPUnit\Framework\TestCase;

class VehicleTest extends TestCase
{
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        $this->vehicle = new Vehicle();
    }

    public function testConstructorInitializesCollections(): void
    {
        $vehicle = new Vehicle();

        $this->assertCount(0, $vehicle->getTracks());
        $this->assertCount(0, $vehicle->getCommandHistories());
    }

    public function testConstructorSetsTimestamps(): void
    {
        $vehicle = new Vehicle();

        $this->assertInstanceOf(\DateTimeInterface::class, $vehicle->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $vehicle->getUpdatedAt());
    }

    public function testExternalIdGetterAndSetter(): void
    {
        $externalId = 'ext-123-456';

        $result = $this->vehicle->setExternalId($externalId);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($externalId, $this->vehicle->getExternalId());
    }

    public function testNameGetterAndSetter(): void
    {
        $name = 'Test Vehicle';

        $result = $this->vehicle->setName($name);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($name, $this->vehicle->getName());
    }

    public function testPlateNumberGetterAndSetter(): void
    {
        $plateNumber = 'ABC-123';

        $result = $this->vehicle->setPlateNumber($plateNumber);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($plateNumber, $this->vehicle->getPlateNumber());
    }

    public function testLatitudeGetterAndSetter(): void
    {
        $latitude = 55.7558;

        $result = $this->vehicle->setLatitude($latitude);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($latitude, $this->vehicle->getLatitude());
    }

    public function testLongitudeGetterAndSetter(): void
    {
        $longitude = 37.6173;

        $result = $this->vehicle->setLongitude($longitude);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($longitude, $this->vehicle->getLongitude());
    }

    public function testSpeedGetterAndSetter(): void
    {
        $speed = 65.5;

        $result = $this->vehicle->setSpeed($speed);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($speed, $this->vehicle->getSpeed());
    }

    public function testCourseGetterAndSetter(): void
    {
        $course = 180.0;

        $result = $this->vehicle->setCourse($course);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($course, $this->vehicle->getCourse());
    }

    public function testLastPositionTimeGetterAndSetter(): void
    {
        $time = new \DateTime('2024-01-15 10:30:00');

        $result = $this->vehicle->setLastPositionTime($time);

        $this->assertSame($this->vehicle, $result);
        $this->assertSame($time, $this->vehicle->getLastPositionTime());
    }

    public function testAdditionalDataGetterAndSetter(): void
    {
        $data = [
            'battery' => 85,
            'fuel' => 45.5,
            'status' => 'moving',
        ];

        $result = $this->vehicle->setAdditionalData($data);

        $this->assertSame($this->vehicle, $result);
        $this->assertEquals($data, $this->vehicle->getAdditionalData());
    }

    public function testUpdatedAtGetterAndSetter(): void
    {
        $time = new \DateTime('2024-01-20 14:45:00');

        $result = $this->vehicle->setUpdatedAt($time);

        $this->assertSame($this->vehicle, $result);
        $this->assertSame($time, $this->vehicle->getUpdatedAt());
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->vehicle->getId());
    }

    public function testSettersReturnSelfForFluentInterface(): void
    {
        $result = $this->vehicle
            ->setExternalId('test-id')
            ->setName('Test')
            ->setPlateNumber('XYZ-789')
            ->setLatitude(50.0)
            ->setLongitude(40.0)
            ->setSpeed(80.0)
            ->setCourse(90.0);

        $this->assertSame($this->vehicle, $result);
    }
}
