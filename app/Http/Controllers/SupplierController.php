<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function reserve(Request $request): JsonResponse
    {
        return response()->json([
            'accepted' => true,
            'ref' => 'SUP-'.now()->getTimestampMs(),
        ]);
    }

    public function status(string $ref): JsonResponse
    {
        $statuses = ['ok', 'ok', 'ok', 'delayed', 'fail'];

        return response()->json([
            'status' => $statuses[array_rand($statuses)],
        ]);
    }
}
