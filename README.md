# Warehouse Reservation System

Event-driven warehouse inventory reservation system on Laravel 12 (PHP 8.2+).
Senior Laravel developer test assignment.

## Tech Stack

| Component    | Technology                           |
|--------------|--------------------------------------|
| Framework    | Laravel 12, PHP 8.2+                 |
| Database     | SQLite (dev/test), PostgreSQL (prod) |
| Queue        | Laravel Queue — database driver      |
| Testing      | Pest 3 — 43 tests, 119 assertions    |
| Code Quality | Laravel Pint (PSR-12)                |

## Event Flow

```
POST /api/order
       |
  Order::create() [status=pending]  →  OrderCreated (ShouldDispatchAfterCommit)
       |
       ▼  async, queue=default
  ReserveInventoryListener
       |
       ├─ [stock sufficient]
       │     DB::transaction + lockForUpdate
       │     qty_reserved += qty  →  InventoryMovement  →  status=reserved
       │
       └─ [stock insufficient]
             SupplierService::reserve()  Http::retry(3, 500ms)
             POST /supplier/reserve  →  { accepted, ref }
                  |
                  ├─ accepted=false  →  status=failed
                  │
                  └─ accepted=true   →  status=awaiting_restock
                                        CheckSupplierStatusJob::dispatch(delay=15s, queue=suppliers)
                                             |
                                        GET /supplier/status/{ref}
                                             |
                                    ok       fail      delayed
                                     |        |           |
                                 reserved  failed   attempt_count++
                                                    if >= 2 → failed
                                                    else re-dispatch(+15s)
```

## Order Status State Machine

```
pending ──────────────────────────────► reserved       (stock sufficient)
pending ──────────────────────────────► failed         (supplier declined / HTTP error)
pending ──────────────────────────────► awaiting_restock
awaiting_restock ─────────────────────► reserved       (supplier: ok)
awaiting_restock ─────────────────────► failed         (supplier: fail / 2× delayed)
```

Terminal states: `reserved`, `failed` — no further transitions.

## API Endpoints

| Method | Endpoint                          | Success | Description              |
|--------|-----------------------------------|---------|--------------------------|
| POST   | `/api/order`                      | 201     | Create order             |
| GET    | `/api/orders/{id}`                | 200     | Get order by ID          |
| GET    | `/api/inventory/{sku}/movements`  | 200     | Inventory movement log   |

**POST /api/order** — `{ "sku": "WIDGET-001", "qty": 3 }`
Returns created order with `status: "pending"`. Reservation happens asynchronously.

**GET /api/inventory/{sku}/movements** — returns array of movements:
```json
{ "id": 1, "sku": "WIDGET-001", "type": "reservation",
  "direction": "outbound", "quantity": 3, "order_id": 1, "created_at": "..." }
```
`direction` is derived: `outbound` (reservation) or `inbound` (restock).

**Fake supplier endpoints** (local dev only — used by `SupplierService`):
- `POST /supplier/reserve` → `{ "accepted": true, "ref": "SUP-{timestamp}" }`
- `GET /supplier/status/{ref}` → `{ "status": "ok" | "delayed" | "fail" }` (random)

## Error Handling Strategy

| Scenario                              | Handling                                      |
|---------------------------------------|-----------------------------------------------|
| Invalid request (missing sku/qty)     | 422 from `CreateOrderRequest`                 |
| Order not found                       | 404 via route model binding                   |
| Stock insufficient                    | Supplier fallback in `ReserveInventoryListener` |
| Supplier `accepted: false`            | `order → failed` immediately, no retry        |
| Supplier HTTP error (after 3 retries) | `order → failed`                              |
| Supplier status `fail`                | `order → failed`                              |
| Supplier status `delayed` × 2        | `order → failed` (tracked via `attempt_count`) |
| Supplier HTTP error on status check   | `order → failed`                              |
| DB deadlock on reservation            | Caught by `lockForUpdate` + transaction retry  |

Guards in `ReserveInventoryListener` and `CheckSupplierStatusJob` check current order status before acting — safe against duplicate job delivery.

## Setup & Running

### Option A — Docker (zero setup)

```bash
git clone https://github.com/FedorenkoAlex322/warehouse-reservation.git
cd warehouse-reservation
docker compose up --build
```

`.env.docker` is pre-configured. Entrypoint creates the SQLite file and runs migrations automatically.
**API at `http://localhost:8000`**

### Option B — Local (PHP 8.2+)

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate
php artisan serve                                         # terminal 1
php artisan queue:work --queue=suppliers,default          # terminal 2
```

## Running Tests

```bash
vendor/bin/pest
vendor/bin/pest --coverage
vendor/bin/pint --test
```

## Design Decisions

**Inventory model (qty_total + qty_reserved)** — separating total stock from reserved stock allows computing `available = qty_total - qty_reserved`, supports future cancellation/release flows, and keeps audit data clear. On supplier delivery (`forceReserve`), both values increment — stock arrives and is immediately allocated, preserving the invariant `qty_reserved ≤ qty_total`.

**HTTP retry vs job retry** — `Http::retry(3, 500ms)` handles transient network failures at the transport layer. Business-level retries (supplier "delayed") are tracked separately via `attempt_count`. Conflating the two would make failure reasons ambiguous.

**`ShouldDispatchAfterCommit`** — ensures `OrderCreated` fires only after the order row is committed. Without this, the queued listener could query the order before it exists, causing a race condition.

**Pessimistic locking** — `reserve()` uses `lockForUpdate()` inside a transaction to serialize concurrent reservations of the same SKU. Note: SQLite locks at the table level; PostgreSQL provides row-level locks as intended for production.

**Enums everywhere** — `OrderStatus`, `SupplierStatus`, `MovementType` — no magic strings, IDE autocompletion, safe refactoring. `OrderStatus::isTerminal()` encapsulates domain logic on the enum itself.

## What Would Be Improved in Production

- **Pagination** on the movements endpoint (currently returns all records)
- **Index on `orders.status`** for queries filtering by status in admin/ops tooling
- **Circuit breaker** for supplier HTTP calls to stop hammering a failing service
- **Idempotency keys** on supplier reserve requests to prevent duplicate reservations on retry
- **Stuck order sweeper** — a scheduled job to detect orders stuck in `awaiting_restock` beyond a threshold
- **Laravel Horizon** for queue monitoring, job failure visibility, and throughput metrics
