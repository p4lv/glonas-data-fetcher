<?php

namespace App\Tests\Entity;

use App\Entity\Vehicle;
use App\Entity\VehicleTrack;
use PHPUnit\Framework\TestCase;

class VehicleTrackTest extends TestCase
{
    private VehicleTrack $track;

    protected function setUp(): void
    {
        $this->track = new VehicleTrack();
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $track = new VehicleTrack();

        $this->assertInstanceOf(\DateTimeInterface::class, $track->getCreatedAt());
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->track->getId());
    }

    public function testVehicleGetterAndSetter(): void
    {
        $vehicle = new Vehicle();

        $result = $this->track->setVehicle($vehicle);

        $this->assertSame($this->track, $result);
        $this->assertSame($vehicle, $this->track->getVehicle());
    }

    public function testLatitudeGetterAndSetter(): void
    {
        $latitude = 55.7558;

        $result = $this->track->setLatitude($latitude);

        $this->assertSame($this->track, $result);
        $this->assertEquals($latitude, $this->track->getLatitude());
    }

    public function testLongitudeGetterAndSetter(): void
    {
        $longitude = 37.6173;

        $result = $this->track->setLongitude($longitude);

        $this->assertSame($this->track, $result);
        $this->assertEquals($longitude, $this->track->getLongitude());
    }

    public function testSpeedGetterAndSetter(): void
    {
        $speed = 80.5;

        $result = $this->track->setSpeed($speed);

        $this->assertSame($this->track, $result);
        $this->assertEquals($speed, $this->track->getSpeed());
    }

    public function testCourseGetterAndSetter(): void
    {
        $course = 270.0;

        $result = $this->track->setCourse($course);

        $this->assertSame($this->track, $result);
        $this->assertEquals($course, $this->track->getCourse());
    }

    public function testAltitudeGetterAndSetter(): void
    {
        $altitude = 150.5;

        $result = $this->track->setAltitude($altitude);

        $this->assertSame($this->track, $result);
        $this->assertEquals($altitude, $this->track->getAltitude());
    }

    public function testSatellitesGetterAndSetter(): void
    {
        $satellites = 12;

        $result = $this->track->setSatellites($satellites);

        $this->assertSame($this->track, $result);
        $this->assertEquals($satellites, $this->track->getSatellites());
    }

    public function testTimestampGetterAndSetter(): void
    {
        $timestamp = new \DateTime('2024-01-15 10:30:00');

        $result = $this->track->setTimestamp($timestamp);

        $this->assertSame($this->track, $result);
        $this->assertSame($timestamp, $this->track->getTimestamp());
    }

    public function testAdditionalDataGetterAndSetter(): void
    {
        $data = [
            'accuracy' => 5,
            'hdop' => 1.2,
        ];

        $result = $this->track->setAdditionalData($data);

        $this->assertSame($this->track, $result);
        $this->assertEquals($data, $this->track->getAdditionalData());
    }

    public function testSettersReturnSelfForFluentInterface(): void
    {
        $vehicle = new Vehicle();
        $timestamp = new \DateTime();

        $result = $this->track
            ->setVehicle($vehicle)
            ->setLatitude(50.0)
            ->setLongitude(40.0)
            ->setSpeed(60.0)
            ->setCourse(180.0)
            ->setAltitude(100.0)
            ->setSatellites(10)
            ->setTimestamp($timestamp);

        $this->assertSame($this->track, $result);
    }
}
