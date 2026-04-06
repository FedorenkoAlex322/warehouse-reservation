<?php

declare(strict_types=1);

namespace App\Enums;

enum SupplierStatus: string
{
    case Ok = 'ok';
    case Fail = 'fail';
    case Delayed = 'delayed';
}
