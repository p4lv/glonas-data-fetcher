<?php

namespace App\Message;

class ParseVehiclesMessage
{
    public function __construct(
        private readonly array $filters = []
    ) {
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
