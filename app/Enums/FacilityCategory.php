<?php

namespace App\Enums;

enum FacilityCategory: string
{
    case Indoor = 'indoor';
    case Outdoor = 'outdoor';

    public function label(): string
    {
        return match ($this) {
            FacilityCategory::Indoor => __('Indoor'),
            FacilityCategory::Outdoor => __('Outdoor'),
        };
    }
}
