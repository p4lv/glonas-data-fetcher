<?php

namespace App\Entity;

use App\Repository\VehicleTrackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleTrackRepository::class)]
#[ORM\Table(name: 'vehicle_tracks')]
#[ORM\Index(columns: ['vehicle_id', 'timestamp'], name: 'idx_vehicle_timestamp')]
class VehicleTrack
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $speed = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $course = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $altitude = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $satellites = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $timestamp = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function setSpeed(?float $speed): static
    {
        $this->speed = $speed;
        return $this;
    }

    public function getCourse(): ?float
    {
        return $this->course;
    }

    public function setCourse(?float $course): static
    {
        $this->course = $course;
        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): static
    {
        $this->altitude = $altitude;
        return $this;
    }

    public function getSatellites(): ?int
    {
        return $this->satellites;
    }

    public function setSatellites(?int $satellites): static
    {
        $this->satellites = $satellites;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeInterface $timestamp): static
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    public function setAdditionalData(?array $additionalData): static
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
