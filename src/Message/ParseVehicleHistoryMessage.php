<?php

namespace App\Message;

class ParseVehicleHistoryMessage
{
    public function __construct(
        private readonly string $vehicleId,
        private readonly ?\DateTimeInterface $from = null,
        private readonly ?\DateTimeInterface $to = null
    ) {
    }

    public function getVehicleId(): string
    {
        return $this->vehicleId;
    }

    public function getFrom(): ?\DateTimeInterface
    {
        return $this->from;
    }

    public function getTo(): ?\DateTimeInterface
    {
        return $this->to;
    }
}
