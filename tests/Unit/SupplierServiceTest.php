<?php

declare(strict_types=1);

use App\Services\SupplierService;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->service = new SupplierService;
});

it('returns accepted response from supplier reserve', function (): void {
    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => true, 'ref' => 'SUP-123'], 200),
    ]);

    $result = $this->service->reserve('ABC', 3);

    expect($result['accepted'])->toBeTrue()
        ->and($result['ref'])->toBe('SUP-123');
});

it('returns not accepted when supplier declines', function (): void {
    Http::fake([
        '*/supplier/reserve' => Http::response(['accepted' => false, 'ref' => null], 200),
    ]);

    $result = $this->service->reserve('ABC', 3);

    expect($result['accepted'])->toBeFalse();
});

it('returns status string from check status', function (): void {
    Http::fake([
        '*/supplier/status/*' => Http::response(['status' => 'ok'], 200),
    ]);

    expect($this->service->checkStatus('SUP-123'))->toBe('ok');
});

it('returns delayed status from check status', function (): void {
    Http::fake([
        '*/supplier/status/*' => Http::response(['status' => 'delayed'], 200),
    ]);

    expect($this->service->checkStatus('SUP-456'))->toBe('delayed');
});

it('returns fail status from check status', function (): void {
    Http::fake([
        '*/supplier/status/*' => Http::response(['status' => 'fail'], 200),
    ]);

    expect($this->service->checkStatus('SUP-789'))->toBe('fail');
});
