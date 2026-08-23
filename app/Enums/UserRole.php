<?php

namespace App\Enums;

enum UserRole: string
{
    case Student = 'student';
    case JskkStaff = 'jskk_staff';
    case ClinicStaff = 'clinic_staff';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            UserRole::Student => __('Student'),
            UserRole::JskkStaff => __('JSKK Staff'),
            UserRole::ClinicStaff => __('Clinic Staff'),
            UserRole::Admin => __('Administrator'),
        };
    }

    /**
     * Whether this role may operate the JSKK counter and admin area.
     */
    public function isJskkArea(): bool
    {
        return in_array($this, [UserRole::JskkStaff, UserRole::Admin], true);
    }

    /**
     * Whether this role may file and view incident reports.
     */
    public function canLogIncidents(): bool
    {
        return in_array($this, [UserRole::JskkStaff, UserRole::ClinicStaff, UserRole::Admin], true);
    }
}
