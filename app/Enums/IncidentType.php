<?php

namespace App\Enums;

enum IncidentType: string
{
    case Injury = 'injury';
    case FirstAid = 'first_aid';
    case Damage = 'damage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            IncidentType::Injury => __('Injury'),
            IncidentType::FirstAid => __('First Aid'),
            IncidentType::Damage => __('Equipment / Facility Damage'),
            IncidentType::Other => __('Other'),
        };
    }
}
