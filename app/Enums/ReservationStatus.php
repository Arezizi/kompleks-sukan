<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Fulfilled = 'fulfilled';

    public function label(): string
    {
        return match ($this) {
            ReservationStatus::Pending => __('Pending'),
            ReservationStatus::Approved => __('Approved'),
            ReservationStatus::Rejected => __('Rejected'),
            ReservationStatus::Cancelled => __('Cancelled'),
            ReservationStatus::Expired => __('Expired'),
            ReservationStatus::Fulfilled => __('Fulfilled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            ReservationStatus::Pending => 'amber',
            ReservationStatus::Approved => 'green',
            ReservationStatus::Rejected => 'red',
            ReservationStatus::Cancelled => 'zinc',
            ReservationStatus::Expired => 'zinc',
            ReservationStatus::Fulfilled => 'blue',
        };
    }
}
