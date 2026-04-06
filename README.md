# Warehouse Reservation System

Event-driven warehouse inventory reservation system built on Laravel 12 (PHP 8.2+). Demonstrates production-ready async architecture: queued listeners, delayed jobs with retry logic, supplier integration via HTTP client, and a strict order status state machine.

Built as a senior Laravel developer test assignment.

## Tech Stack

| Component     | Technology                              |
|---------------|-----------------------------------------|
| Language      | PHP 8.2+                                |
| Framework     | Laravel 12                              |
| Database      | SQLite (dev/test), PostgreSQL (prod)    |
| Queue         | Laravel Queue (database driver)         |
| HTTP Client   | Laravel HTTP Client (Http::fake() in tests) |
| Testing       | Pest (PHPUnit 11)                       |
| Code Quality  | Laravel Pint (PSR-12)                   |

## Architecture

### Event Flow

```
POST /api/order
       |
  OrderController::store()
       |
  Order::create() [status=pending]
       |
  OrderCreated event dispatched (ShouldDispatchAfterCommit)
       |
       v (async, queued listener)
  ReserveInventoryListener
       |
       +-- [qty sufficient] --> InventoryService::reserve()
       |                           - lockForUpdate() on inventory
       |                           - qty_reserved += qty
       |                           - InventoryMovement created
       |                           - Order status -> "reserved"
       |
       +-- [qty insufficient] --> SupplierService::reserve()
                                       |
                                 POST /supplier/reserve
                                 { accepted: true, ref: "SUP-xxx" }
                                       |
                                 Order status -> "awaiting_restock"
                                 Order.supplier_ref = ref
                                       |
                                 CheckSupplierStatusJob::dispatch()
                                 ->delay(15 seconds)
                                       |
                             GET /supplier/status/{ref}
                                       |
                             +----------+-----------+
                            ok        fail       delayed
                             |          |            |
                        reserved     failed     retry (max 2)
                                                after 15s each
                                                -> failed after 2nd delayed
```

### Order Status State Machine

```
pending --> reserved            (enough inventory in stock)
pending --> awaiting_restock    (insufficient stock, supplier called)
pending --> failed              (supplier declined or HTTP error)
awaiting_restock --> reserved   (supplier confirmed "ok")
awaiting_restock --> failed     (supplier returned "fail")
awaiting_restock --> failed     (2 consecutive "delayed" responses)
```

Terminal states: `reserved`, `failed`. No transitions out of terminal states.

## API Endpoints

### POST /api/order

Create a new order. Triggers async inventory reservation.

**Request:**
```json
{ "sku": "WIDGET-001", "qty": 3 }
```

**Response (201):**
```json
{
  "data": {
    "id": 1,
    "sku": "WIDGET-001",
    "qty": 3,
    "status": "pending",
    "supplier_ref": null,
    "created_at": "2026-04-06T12:00:00.000000Z",
    "updated_at": "2026-04-06T12:00:00.000000Z"
  }
}
```

**Errors:** `422` if `sku` or `qty` is missing/invalid.

### GET /api/orders/{id}

Retrieve order by ID.

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "sku": "WIDGET-001",
    "qty": 3,
    "status": "reserved",
    "supplier_ref": null,
    "created_at": "2026-04-06T12:00:00.000000Z",
    "updated_at": "2026-04-06T12:00:00.000000Z"
  }
}
```

**Errors:** `404` if order not found.

### GET /api/inventory/{sku}/movements

Inventory movement audit log for a SKU, ordered by most recent first.

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "sku": "WIDGET-001",
      "type": "reservation",
      "direction": "outbound",
      "quantity": 3,
      "order_id": 1,
      "created_at": "2026-04-06T12:00:00.000000Z"
    }
  ]
}
```

## Setup & Running

```bash
# Clone and install
git clone <repo-url>
cd warehouse_reserve
composer install

# Environment
cp .env.example .env
php artisan key:generate

# Database (SQLite for local dev)
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=InventorySeeder

# Start application
php artisan serve

# Start queue worker (separate terminal)
php artisan queue:work --queue=suppliers,default
```

## Running Tests

```bash
# Run full test suite
vendor/bin/pest

# Run with coverage
vendor/bin/pest --coverage

# Code style check
vendor/bin/pint --test

# Code style fix
vendor/bin/pint
```

Test suite: 43 tests, 119 assertions covering unit tests for services/jobs/enums and feature tests for API endpoints and full end-to-end flows.

## Docker Setup (Alternative)

Requires [Docker](https://docs.docker.com/get-docker/) and Docker Compose.

`.env.docker` is pre-configured and ready to use — no changes needed.

```bash
docker compose up --build
```

That's it. The entrypoint automatically:
- Creates the SQLite database file
- Runs migrations

**API is available at `http://localhost:8000`**

Both the web server (`app`) and queue worker (`queue`) start automatically and share the same SQLite volume.

> **Note:** `APP_ENV=local` is used in Docker to keep error reporting helpful during evaluation. Switch to `production` for real deployments.

## Design Decisions & Trade-offs

### Inventory Model: qty_total + qty_reserved

The inventory table stores both `qty_total` and `qty_reserved` rather than just a single available quantity. This allows computing `available = qty_total - qty_reserved` while preserving the full picture of stock levels. It makes debugging straightforward (you can see total stock vs. how much is held), supports future release/cancellation flows, and avoids ambiguity about what a single number represents.

### forceReserve Increments Both qty_total and qty_reserved

When a supplier confirms delivery ("ok"), it means physical stock has arrived and is immediately earmarked for the order. Therefore `forceReserve` increments both `qty_total` (stock received) and `qty_reserved` (stock allocated). This preserves the invariant `qty_reserved <= qty_total` at all times. If the inventory record does not exist yet, it is auto-created with both values set to the order quantity.

### HTTP Retry at Transport Layer vs. Job-Level Retries

`SupplierService` uses `Http::retry(3, 500ms)` for transient connection failures (network blips, timeouts). This is distinct from the business-level retry in `CheckSupplierStatusJob` which handles the supplier responding "delayed". Transport retries handle infrastructure problems; job retries handle business workflow states. Mixing them would conflate two different failure modes.

### ShouldDispatchAfterCommit on OrderCreated Event

The `OrderCreated` event uses `ShouldDispatchAfterCommit` to ensure the event is only dispatched after the database transaction commits. Without this, a queued listener could pick up the event and try to query the order before it exists in the database, causing a race condition.

### accepted=false Handling: Immediate Failure

When the supplier responds with `accepted: false`, the order is immediately marked as `failed` without dispatching `CheckSupplierStatusJob`. There is no point polling a supplier that has explicitly declined the reservation request.

### match Expression Over switch

PHP 8's `match` is used throughout for status handling. It provides exhaustive matching (no fall-through bugs), returns values, uses strict comparison, and produces a clear `UnhandledMatchError` if an unexpected value appears. The `default` arm handles any future unknown statuses defensively.

### Enums Over Magic Strings

`OrderStatus`, `SupplierStatus`, and `MovementType` are backed enums. This provides compile-time type safety, IDE autocompletion, safe refactoring, and prevents typo-based bugs. The `OrderStatus` enum also carries domain logic via `isTerminal()`.

### Pessimistic Locking for Inventory Reservation

`InventoryService::reserve()` uses `lockForUpdate()` inside a database transaction to prevent race conditions when multiple concurrent requests attempt to reserve the same SKU. Without this, two requests could both read sufficient stock and both succeed, over-reserving inventory.

### SQLite for Development and Testing

SQLite is used for local development and tests for simplicity (no external services needed). Note that `lockForUpdate()` in SQLite acquires a table-level lock rather than a row-level lock as in PostgreSQL. This means concurrent reservation tests on SQLite are serialized at the table level, which masks potential row-level contention issues that would appear in production with PostgreSQL.

### API Resources for Response Transformation

Controllers delegate response formatting to `OrderResource` and `InventoryMovementResource`. This separates serialization concerns from business logic, provides a consistent response envelope (`data` wrapper), and allows model accessors (like `direction` and `quantity` on `InventoryMovement`) to be used without duplicating logic in controllers.

## What Would Be Improved in Production

- **Pagination** on the movements endpoint -- currently returns all records, needs cursor or offset pagination for large datasets
- **Observer or dedicated restock event** when supplier delivers -- currently `forceReserve` handles both inventory update and order status change; a `StockReceived` event would improve decoupling
- **Database index on `orders.status`** for admin queries filtering by status (e.g., finding stuck `awaiting_restock` orders)
- **Circuit breaker pattern** for supplier HTTP calls -- if the supplier is consistently failing, stop calling it temporarily rather than failing orders one by one
- **Idempotency keys** on supplier reserve requests -- protect against duplicate reservations from retried HTTP requests
- **Laravel Horizon** for queue monitoring -- visibility into job throughput, failures, and wait times
- **Monitoring and alerting** for orders stuck in `awaiting_restock` beyond a threshold (e.g., 10 minutes) -- detect supplier integration issues early
- **Rate limiting** on the POST /api/order endpoint to prevent abuse
- **PostgreSQL in all environments** for consistent locking behavior between dev/test and production
- **Dedicated SupplierStatusEnum casting** at the HTTP client level -- `SupplierService::checkStatus()` could return `SupplierStatus` enum directly instead of a raw string (partially addressed: enum is used in the job)
