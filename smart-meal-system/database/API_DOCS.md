# Smart Meal System — API Documentation

All pages are server-rendered PHP. This document covers:
1. The one true JSON API endpoint (`/api/track_order.php`)
2. All form POST endpoints (the implicit internal API used by each page)

---

## Base URL

```
http://localhost/smart-meal-system
```

---

## Authentication

All endpoints require a valid PHP session. Sessions are set at login and expire when the browser is closed or the user logs out. Unauthenticated requests are redirected to `/auth/login.php`.

Role guards are enforced per-page via `requireRole([...])`. Accessing a page with the wrong role returns HTTP 403.

---

## 1. Live Delivery Tracking — JSON API

### `GET /api/track_order.php`

Returns the current GPS position and delivery status for an order.
Polled every 4 seconds by the customer's tracking page.

**Query Parameters**

| Parameter  | Type | Required | Description              |
|------------|------|----------|--------------------------|
| `order_id` | int  | Yes      | The order to track       |

**Authorization**  
Must be logged in as one of:
- The customer who placed the order
- The restaurant that owns the order
- An admin

**Success Response — tracking available**
```json
{
  "tracking_available": true,
  "order_status": "out_for_delivery",
  "delivery_status": "on_the_way",
  "delivery_person_name": "Sumon Mia",
  "delivery_person_phone": "01788888888",
  "current_lat": 23.7855,
  "current_lng": 90.4021,
  "destination_lat": 23.7937,
  "destination_lng": 90.4066,
  "estimated_minutes": 11,
  "last_updated": "2026-06-21 14:30:22"
}
```

**Success Response — tracking not yet available**
```json
{
  "tracking_available": false,
  "order_status": "pending"
}
```

**Error Responses**

| HTTP Code | Body                                          | Reason                          |
|-----------|-----------------------------------------------|---------------------------------|
| 401       | `{"error": "Not authenticated"}`              | No active session               |
| 403       | `{"error": "Not authorized to view this order"}` | Wrong user                  |
| 404       | `{"error": "Order not found"}`                | Invalid order_id                |

**Notes**
- Each call to this endpoint advances the simulated rider position by 15% of remaining distance (demo mode).
- In production, replace the position-nudge `UPDATE` with real GPS pings from a delivery app.
- The JSON shape is designed to be identical whether the position comes from polling or WebSocket push, so switching to Socket.io only requires changing the client-side transport.

---

## 2. Authentication Endpoints

### `POST /auth/register.php`

Registers a new user. All roles except `admin` can self-register.

**Form Fields**

| Field             | Type   | Required | Notes                                          |
|-------------------|--------|----------|------------------------------------------------|
| `csrf_token`      | string | Yes      | Hidden field, generated per-session            |
| `role`            | enum   | Yes      | `student` / `employee` / `public` / `restaurant` |
| `full_name`       | string | Yes      | Max 120 chars                                  |
| `email`           | string | Yes      | Must be unique                                 |
| `phone`           | string | Yes      |                                                |
| `password`        | string | Yes      | Min 6 chars                                    |
| `confirm_password`| string | Yes      | Must match `password`                          |
| `student_id_no`   | string | No       | Only for `role=student`                        |
| `employee_id_no`  | string | No       | Only for `role=employee`                       |
| `institution`     | string | No       | University / company name                      |
| `address`         | string | No       | Delivery address                               |
| `restaurant_name` | string | Cond.    | Required if `role=restaurant`                  |
| `restaurant_address` | string | Cond. | Required if `role=restaurant`                 |

**On Success**: Redirects to `/auth/login.php` with a flash success message.  
**Note**: Restaurant accounts are created with `is_approved=0` and cannot log in until an admin approves them.

---

### `POST /auth/login.php`

**Form Fields**

| Field        | Type   | Required |
|--------------|--------|----------|
| `csrf_token` | string | Yes      |
| `email`      | string | Yes      |
| `password`   | string | Yes      |

**On Success**: Redirects to the appropriate dashboard based on role:
- `student` → `/student/dashboard.php`
- `employee` → `/employee/dashboard.php`
- `public` → `/public_user/dashboard.php`
- `restaurant` → `/restaurant/dashboard.php`
- `admin` → `/admin/dashboard.php`

**Failure cases**:
- Wrong credentials → re-render login with error
- Suspended account → error message shown
- Unapproved restaurant → error message shown

---

### `GET /auth/logout.php`

Destroys the session and redirects to `/index.php`.

---

## 3. Order Endpoints (Student / Employee / Public)

All three role groups share identical logic; paths differ by role prefix.

### Browse & Filter Meals

`GET /{role}/browse_meals.php`

| Query Param     | Values                          | Description              |
|-----------------|---------------------------------|--------------------------|
| `meal_type`     | `breakfast` / `lunch` / `dinner`| Filter by type           |
| `restaurant_id` | integer                         | Filter by restaurant     |

Only returns meals where `is_available=1`, restaurant `is_open=1`, restaurant `is_approved=1`.

---

### Place Order

`POST /{role}/place_order.php`

| Field              | Type   | Required | Notes                      |
|--------------------|--------|----------|----------------------------|
| `csrf_token`       | string | Yes      |                            |
| `meal_id`          | int    | Yes      | Must exist and be available|
| `quantity`         | int    | Yes      | 1–20                       |
| `delivery_address` | string | Yes      |                            |

**Side effects on success**:
1. Inserts row into `orders` with status `pending`
2. Calls `syncCurrentPayment()` — updates or creates the user's billing period record
3. Inserts a `notification` for the restaurant owner
4. Saves delivery address to `$_SESSION['last_address']` for next-order convenience

**On Success**: Redirects to `/{role}/my_orders.php` with flash message.

---

### Cancel Order

`POST /{role}/cancel_order.php`

| Field        | Type | Required | Notes                              |
|--------------|------|----------|------------------------------------|
| `csrf_token` | str  | Yes      |                                    |
| `order_id`   | int  | Yes      | Must belong to current user        |

Only `pending` orders can be cancelled. After cancellation, billing is re-synced.

---

### Make Payment

`POST /{role}/pay.php`

| Field            | Type   | Required | Notes                                           |
|------------------|--------|----------|-------------------------------------------------|
| `csrf_token`     | string | Yes      |                                                 |
| `payment_id`     | int    | Yes      | Must belong to current user, status pending/overdue |
| `payment_method` | enum   | Yes      | `bkash` / `nagad` / `card`                     |
| `wallet_number`  | string | Cond.    | Required for bkash/nagad. Format: `01XXXXXXXXX` |
| `card_number`    | string | Cond.    | Required for card. 13–19 digits                 |
| `card_expiry`    | string | Cond.    | Required for card. Format: `MM/YY`              |
| `card_cvv`       | string | Cond.    | Required for card. 3–4 digits                   |

**On Success**: Payment row updated to `paid`, transaction ref stored, user notified. Redirects to `/{role}/payments.php`.

---

## 4. Restaurant Endpoints

### Toggle Open/Closed

`POST /restaurant/toggle_availability.php`

| Field        | Type   | Required |
|--------------|--------|----------|
| `csrf_token` | string | Yes      |

Flips `restaurants.is_open` for the current restaurant owner's restaurant.

---

### Add / Edit Meal

`POST /restaurant/manage_meals.php` with `action=save`

| Field        | Type    | Required | Notes                                     |
|--------------|---------|----------|-------------------------------------------|
| `csrf_token` | string  | Yes      |                                           |
| `action`     | string  | Yes      | Must be `save`                            |
| `meal_id`    | int     | No       | If `> 0`, updates existing meal           |
| `meal_name`  | string  | Yes      |                                           |
| `description`| string  | No       |                                           |
| `meal_type`  | enum    | Yes      | `breakfast` / `lunch` / `dinner`          |
| `price`      | decimal | Yes      | Must be > 0                               |

Ownership is verified: restaurants can only edit their own meals.

---

### Delete Meal

`GET /restaurant/manage_meals.php?delete={meal_id}`

Deletes the meal if it belongs to the current restaurant.

---

### Toggle Meal Availability

`GET /restaurant/manage_meals.php?toggle={meal_id}`

Flips `meals.is_available` for the given meal.

---

### Update Order Status

`POST /restaurant/manage_orders.php`

| Field        | Type   | Required | Notes                                                               |
|--------------|--------|----------|---------------------------------------------------------------------|
| `csrf_token` | string | Yes      |                                                                     |
| `order_id`   | int    | Yes      | Must belong to this restaurant                                      |
| `new_status` | enum   | Yes      | `accepted` / `rejected` / `preparing` / `out_for_delivery` / `delivered` |

**Side effects**:
- `accepted`: creates a `delivery_tracking` row (idempotent — no duplicate if already exists)
- `out_for_delivery`: sets `delivery_tracking.delivery_status = 'on_the_way'`
- `delivered`: sets `delivery_tracking.delivery_status = 'delivered'`
- All transitions: re-sync customer's billing via `syncCurrentPayment()`
- All transitions: send a `notification` to the customer

---

## 5. Admin Endpoints

### Toggle User Status (Suspend / Activate)

`POST /admin/manage_users.php` with `action=toggle_status`

| Field        | Type   | Required |
|--------------|--------|----------|
| `csrf_token` | string | Yes      |
| `action`     | string | Yes      | `toggle_status`          |
| `user_id`    | int    | Yes      | Cannot target own account |

---

### Approve / Revoke Restaurant

`POST /admin/manage_restaurants.php`

| Field           | Type   | Required | Notes                          |
|-----------------|--------|----------|--------------------------------|
| `csrf_token`    | string | Yes      |                                |
| `restaurant_id` | int    | Yes      |                                |
| `action`        | enum   | Yes      | `approve` / `revoke`           |

On `approve`: sets `is_approved=1, is_open=1` and sends notification to restaurant owner.  
On `revoke`: sets `is_approved=0, is_open=0`.

---

## 6. Business Logic Functions Reference

Defined in `includes/functions.php`:

| Function                          | Description                                                             |
|-----------------------------------|-------------------------------------------------------------------------|
| `billingCycleForRole($role)`      | Returns `'weekly'` for public, `'monthly'` for student/employee         |
| `currentBillingPeriod($role)`     | Returns `{start, end, due}` dates for the active billing window         |
| `syncCurrentPayment($pdo, $uid, $role)` | Recalculates and upserts the user's current billing period row   |
| `getMealSummary($pdo, $uid)`      | Returns daily/weekly/monthly count+amount + total payable               |
| `notify($pdo, $uid, $title, $msg)`| Inserts a notification row for a user                                   |
| `unreadNotificationCount($pdo, $uid)` | Returns count of unread notifications (for navbar badge)            |
| `formatMoney($amount)`            | Formats a float as `৳1,234.56`                                          |
| `processPayment($method, $amount, $postData)` | Gateway abstraction returning `{success, transaction_ref, message}` |

---

## 7. Database Queries Reference

Key SQL patterns used throughout the system:

### Meal availability check (before placing order)
```sql
SELECT m.*, r.is_open, r.is_approved
FROM meals m
JOIN restaurants r ON r.restaurant_id = m.restaurant_id
WHERE m.meal_id = ? AND m.is_available = 1 AND r.is_open = 1 AND r.is_approved = 1
```

### Billing period calculation (monthly example)
```sql
SELECT COUNT(*) AS meal_count, COALESCE(SUM(total_price), 0) AS total_amount
FROM orders
WHERE user_id = ?
  AND order_date BETWEEN DATE_FORMAT(CURDATE(),'%Y-%m-01') AND LAST_DAY(CURDATE())
  AND order_status NOT IN ('rejected', 'cancelled')
```

### Revenue by restaurant (admin reports)
```sql
SELECT r.restaurant_name,
       COUNT(o.order_id) AS order_count,
       COALESCE(SUM(o.total_price), 0) AS revenue
FROM restaurants r
LEFT JOIN orders o ON o.restaurant_id = r.restaurant_id
  AND o.order_status NOT IN ('rejected', 'cancelled')
GROUP BY r.restaurant_id
ORDER BY revenue DESC
```

### Overdue payment detection
```sql
UPDATE payments SET status = 'overdue'
WHERE status = 'pending' AND due_date < CURDATE()
```
> Run this as a daily cron job:
> ```bash
> 0 1 * * * php /path/to/smart-meal-system/database/cron_overdue.php
> ```

---

## 8. Cron Job (Recommended)

Create `database/cron_overdue.php` to auto-flag overdue payments:

```php
<?php
require_once __DIR__ . '/../config/database.php';
$pdo = getDB();
$stmt = $pdo->prepare("UPDATE payments SET status = 'overdue' WHERE status = 'pending' AND due_date < CURDATE()");
$stmt->execute();
echo "Marked " . $stmt->rowCount() . " payments as overdue.\n";
```

Add to crontab:
```
0 1 * * * /usr/bin/php /var/www/html/smart-meal-system/database/cron_overdue.php
```
