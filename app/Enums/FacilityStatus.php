<?php

namespace App\Enums;

enum FacilityStatus: string
{
    case Open = 'open';
    case Maintenance = 'maintenance';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            FacilityStatus::Open => __('Open'),
            FacilityStatus::Maintenance => __('Maintenance'),
            FacilityStatus::Closed => __('Closed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            FacilityStatus::Open => 'green',
            FacilityStatus::Maintenance => 'amber',
            FacilityStatus::Closed => 'red',
        };
    }
}
