<?php

namespace App\Message;

class ParseVehicleTracksMessage
{
    public function __construct(
        private readonly string $vehicleId,
        private readonly \DateTimeInterface $from,
        private readonly \DateTimeInterface $to
    ) {
    }

    public function getVehicleId(): string
    {
        return $this->vehicleId;
    }

    public function getFrom(): \DateTimeInterface
    {
        return $this->from;
    }

    public function getTo(): \DateTimeInterface
    {
        return $this->to;
    }
}
