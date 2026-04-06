<?php

declare(strict_types=1);

namespace App\Enums;

enum MovementType: string
{
    case Reservation = 'reservation';
    case SupplierReservation = 'supplier_reservation';
}
