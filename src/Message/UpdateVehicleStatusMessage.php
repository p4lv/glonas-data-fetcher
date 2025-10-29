<?php

namespace App\Message;

class UpdateVehicleStatusMessage
{
    private ?int $vehicleId;
    private array $filters;

    /**
     * @param int|null $vehicleId Specific vehicle ID to update, null to update all
     * @param array $filters Filters for vehicles/find endpoint
     */
    public function __construct(?int $vehicleId = null, array $filters = [])
    {
        $this->vehicleId = $vehicleId;
        $this->filters = $filters;
    }

    public function getVehicleId(): ?int
    {
        return $this->vehicleId;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
