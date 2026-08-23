<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            IncidentSeverity::Low => __('Low'),
            IncidentSeverity::Medium => __('Medium'),
            IncidentSeverity::High => __('High'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            IncidentSeverity::Low => 'green',
            IncidentSeverity::Medium => 'amber',
            IncidentSeverity::High => 'red',
        };
    }
}
