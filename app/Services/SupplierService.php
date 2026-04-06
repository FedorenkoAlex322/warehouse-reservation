<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SupplierService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.supplier.base_url'), '/');
    }

    /**
     * Request reservation from supplier.
     *
     * @return array{accepted: bool, ref: string|null}
     */
    public function reserve(string $sku, int $qty): array
    {
        $response = Http::retry(
            times: 3,
            sleepMilliseconds: 500,
            when: fn (\Throwable $e): bool => $e instanceof ConnectionException,
            throw: true,
        )->post("{$this->baseUrl}/supplier/reserve", [
            'sku' => $sku,
            'qty' => $qty,
        ]);

        return $response->json();
    }

    /**
     * Check supplier delivery status.
     *
     * @return string One of: 'ok', 'fail', 'delayed'
     */
    public function checkStatus(string $ref): string
    {
        $response = Http::retry(
            times: 3,
            sleepMilliseconds: 500,
            when: fn (\Throwable $e): bool => $e instanceof ConnectionException,
            throw: true,
        )->get("{$this->baseUrl}/supplier/status/{$ref}");

        return (string) $response->json('status');
    }
}
