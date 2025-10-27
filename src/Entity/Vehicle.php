<?php

namespace App\Entity;

use App\Repository\VehicleRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: VehicleRepository::class)]
#[ORM\Table(name: 'vehicles')]
#[ORM\Index(columns: ['external_id'], name: 'idx_external_id')]
class Vehicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $plateNumber = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $speed = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $course = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastPositionTime = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(targetEntity: VehicleTrack::class, mappedBy: 'vehicle', orphanRemoval: true)]
    private Collection $tracks;

    #[ORM\OneToMany(targetEntity: CommandHistory::class, mappedBy: 'vehicle', orphanRemoval: true)]
    private Collection $commandHistories;

    public function __construct()
    {
        $this->tracks = new ArrayCollection();
        $this->commandHistories = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(string $externalId): static
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getPlateNumber(): ?string
    {
        return $this->plateNumber;
    }

    public function setPlateNumber(?string $plateNumber): static
    {
        $this->plateNumber = $plateNumber;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
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

    public function getLastPositionTime(): ?\DateTimeInterface
    {
        return $this->lastPositionTime;
    }

    public function setLastPositionTime(?\DateTimeInterface $lastPositionTime): static
    {
        $this->lastPositionTime = $lastPositionTime;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function addTrack(VehicleTrack $track): static
    {
        if (!$this->tracks->contains($track)) {
            $this->tracks->add($track);
            $track->setVehicle($this);
        }

        return $this;
    }

    public function removeTrack(VehicleTrack $track): static
    {
        if ($this->tracks->removeElement($track)) {
            if ($track->getVehicle() === $this) {
                $track->setVehicle(null);
            }
        }

        return $this;
    }

    public function getCommandHistories(): Collection
    {
        return $this->commandHistories;
    }

    public function addCommandHistory(CommandHistory $commandHistory): static
    {
        if (!$this->commandHistories->contains($commandHistory)) {
            $this->commandHistories->add($commandHistory);
            $commandHistory->setVehicle($this);
        }

        return $this;
    }

    public function removeCommandHistory(CommandHistory $commandHistory): static
    {
        if ($this->commandHistories->removeElement($commandHistory)) {
            if ($commandHistory->getVehicle() === $this) {
                $commandHistory->setVehicle(null);
            }
        }

        return $this;
    }
}
