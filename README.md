# 🍽️ Smart Meal Management & Delivery System

A full-stack web application built with **PHP + HTML + CSS + JavaScript + MySQL**.  
Supports Students, Employees, General Public, Restaurant Partners, and Admins — with role-based billing cycles, real-time delivery tracking, and multiple payment methods (bKash, Nagad, Card).

---

## Tech Stack

| Layer       | Technology                                    |
|-------------|-----------------------------------------------|
| Backend     | PHP 8.0+ (PDO, no framework required)         |
| Frontend    | HTML5, CSS3, Vanilla JavaScript               |
| Database    | MySQL 5.7+ / MariaDB 10.5+                    |
| Maps        | Leaflet.js (OpenStreetMap, no API key needed) |
| Payments    | bKash / Nagad / Card (stub — swappable)       |
| Hosting     | Any LAMP / XAMPP / WAMP / cPanel server       |

---

## Quick Start (XAMPP / WAMP / Local)

### 1. Clone or extract the project

```bash
# Place the folder inside your web root
cp -r smart-meal-system/ /var/www/html/
# OR for XAMPP on Windows:
# C:\xampp\htdocs\smart-meal-system\
```

### 2. Create the database

Open **phpMyAdmin** (or any MySQL client) and run:

```bash
mysql -u root -p < database/schema.sql
```

This creates the `smart_meal_system` database with all tables, indexes, foreign keys, and sample data.

### 3. Set correct password hashes

The SQL file contains placeholder password hashes. Run the seeder to fix them:

```bash
cd /path/to/smart-meal-system
php database/seed.php
```

### 4. Configure database connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'smart_meal_system');
define('DB_USER', 'root');
define('DB_PASS', '');          // your MySQL password
define('BASE_URL', '/smart-meal-system');  // adjust if needed
```

### 5. Visit in your browser

```
http://localhost/smart-meal-system/
```

---

## Demo Accounts

After running `php database/seed.php`, log in with:

| Role           | Email                       | Password       |
|----------------|-----------------------------|----------------|
| Admin          | admin@smartmeal.com         | Admin@123      |
| Student        | student@example.com         | Student@123    |
| Employee       | employee@example.com        | Employee@123   |
| General Public | public@example.com          | Public@123     |
| Restaurant 1   | spicegarden@example.com     | Restaurant@123 |
| Restaurant 2   | dailymess@example.com       | Restaurant@123 |

---

## Project Structure

```
smart-meal-system/
├── config/
│   └── database.php              # DB connection (PDO singleton)
│
├── includes/
│   ├── auth.php                  # Session, login/logout, CSRF, flash messages
│   ├── functions.php             # Business logic: billing cycles, meal summaries, notifications
│   ├── payment_gateway.php       # bKash / Nagad / Card payment stub
│   ├── header.php                # Shared navbar (role-aware menu)
│   ├── footer.php                # Shared footer
│   └── notifications.php        # Notifications listing page (all roles)
│
├── auth/
│   ├── login.php                 # Login (all roles)
│   ├── register.php              # Registration (student/employee/public/restaurant)
│   └── logout.php                # Session destroy + redirect
│
├── student/
│   ├── dashboard.php             # Stats: daily/weekly/monthly meals + payable
│   ├── browse_meals.php          # Browse meals by type & restaurant, place order
│   ├── place_order.php           # POST: create order, sync billing, notify restaurant
│   ├── my_orders.php             # Order history + cancel + track buttons
│   ├── cancel_order.php          # POST: cancel pending order
│   ├── payments.php              # Monthly billing statements
│   ├── pay.php                   # Payment gateway UI (bKash/Nagad/Card)
│   └── track_delivery.php        # Live Leaflet map with 4s polling
│
├── employee/                     # Identical structure to student/ (monthly billing)
│
├── public_user/                  # Same structure, weekly billing cycle
│
├── restaurant/
│   ├── dashboard.php             # Order stats, open/close toggle
│   ├── toggle_availability.php   # POST: flip is_open flag
│   ├── manage_meals.php          # Add/edit/delete/toggle meals (CRUD)
│   └── manage_orders.php         # Accept/reject, pipeline status updates
│
├── admin/
│   ├── dashboard.php             # Platform-wide stats
│   ├── manage_users.php          # View all users, suspend/activate
│   ├── manage_restaurants.php    # Approve/revoke restaurant partners
│   └── reports.php               # Daily / weekly / monthly revenue by restaurant
│
├── api/
│   └── track_order.php           # JSON endpoint polled for live GPS position
│
├── assets/
│   ├── css/style.css             # Full custom design system (no Bootstrap)
│   └── js/main.js                # Confirm dialogs, UI helpers
│
├── database/
│   ├── schema.sql                # Full schema + indexes + sample data
│   └── seed.php                  # Generates correct bcrypt hashes for demo accounts
│
├── uploads/
│   └── food_images/              # Meal images (uploaded via manage_meals — extend as needed)
│
└── README.md
```

---

## Key Features

### Billing Cycles
| User Role      | Billing Cycle | Due Date          |
|----------------|---------------|-------------------|
| Student        | Monthly       | Last day of month |
| Employee       | Monthly       | Last day of month |
| General Public | Weekly        | Sunday of each week |

Billing is **automatic** — `syncCurrentPayment()` recalculates the current period total every time an order is placed, updated, or cancelled. Students/employees never pay mid-month; the public pays weekly.

### Order Pipeline
```
pending → accepted → preparing → out_for_delivery → delivered
       ↘ rejected
pending → cancelled (by customer, before acceptance only)
```
Each status transition:
- Updates the `orders` table
- Updates or creates a `delivery_tracking` row
- Recalculates the customer's billing record
- Sends a `notifications` row to the customer

### Live Delivery Tracking
- Customer opens `track_delivery.php` → a Leaflet.js map loads
- JavaScript polls `/api/track_order.php?order_id=X` every **4 seconds**
- The API simulates GPS movement (moves the rider 15% closer to destination per poll)
- **Upgrade path**: replace polling with Socket.io WebSocket push — the JSON shape from the API stays the same, so frontend JS barely changes

### Payment Flow
1. User views `payments.php` → sees pending billing periods
2. Clicks **Pay Now** → `pay.php` loads with method selector
3. Selects bKash / Nagad / Card → fills in phone or card details
4. PHP calls `processPayment()` in `includes/payment_gateway.php`
5. On success: `payments` row updated to `paid`, transaction ref stored, user notified

**To go live with bKash**: open `includes/payment_gateway.php` and replace `processBkashPayment()` internals with the official bKash Tokenized Checkout API call. The rest of the app doesn't change.

### Security
- All user passwords hashed with `password_hash()` / `password_verify()` (bcrypt)
- All DB queries use **PDO prepared statements** — no SQL injection possible
- Every POST form includes a **CSRF token** (verified server-side)
- `requireRole([...])` guards every page — role mismatches return HTTP 403
- Output escaped with `htmlspecialchars()` via the `e()` helper — no XSS possible
- Session regenerated on login to prevent session fixation
- Restaurant approval gate: restaurant users can't log in until an admin approves them

---

## Payment Gateway Integration (Going Live)

### bKash
1. Apply at https://developer.bka.sh for sandbox credentials
2. Obtain: `app_key`, `app_secret`, `username`, `password`
3. In `includes/payment_gateway.php`, replace `processBkashPayment()` with:
   - Step 1: POST to `/tokenized/checkout/token/grant` to get access token
   - Step 2: POST to `/tokenized/checkout/create` to initiate payment
   - Step 3: Redirect user to `bkashURL` returned in response
   - Step 4: Handle callback at a new `api/bkash_callback.php`

### Nagad
1. Register at https://nagad.com.bd/merchant
2. Obtain: Merchant ID + RSA public/private key pair
3. All Nagad API requests require request body signing with your private key
4. Replace `processNagadPayment()` in `payment_gateway.php`

### Card (Stripe — recommended)
```bash
composer require stripe/stripe-php
```
Replace `processCardPayment()` with Stripe's `PaymentIntent` API for PCI-safe card handling.

---

## Upgrading to Real-Time WebSockets

Current implementation: AJAX polling every 4 seconds (works on any PHP host).

To upgrade to Socket.io:

```bash
npm install express socket.io
```

```js
// socket-server/index.js
const io = require('socket.io')(3000);
io.on('connection', socket => {
  socket.on('join_order', orderId => socket.join(`order_${orderId}`));
  socket.on('location_update', ({ orderId, lat, lng, eta }) => {
    io.to(`order_${orderId}`).emit('position', { lat, lng, eta });
  });
});
```

Then in the delivery person's mobile app (React Native / Flutter), emit `location_update` every few seconds. Replace `setInterval(pollTracking, 4000)` in `track_delivery.php` with a Socket.io client connection.

---

## Database Tables Summary

| Table              | Purpose                                        |
|--------------------|------------------------------------------------|
| `users`            | All user accounts (role-based, one table)      |
| `restaurants`      | Restaurant profiles linked to user accounts    |
| `meals`            | Food items added by restaurants                |
| `orders`           | Every meal order with status pipeline          |
| `payments`         | Billing periods (weekly/monthly) with status   |
| `delivery_tracking`| GPS coordinates + ETA for active deliveries   |
| `notifications`    | In-app messages sent to users on order events  |

---

## Running on a Live Server (cPanel / VPS)

1. Upload the project to `public_html/smart-meal-system/`
2. In cPanel → MySQL Databases → create `smart_meal_system` DB + user
3. Import `database/schema.sql` via phpMyAdmin
4. SSH in and run `php database/seed.php`
5. Update `config/database.php` with your live DB credentials
6. Update `BASE_URL` in `config/database.php` to match your domain path
7. Visit `https://yourdomain.com/smart-meal-system/`

---

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.5+
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `openssl`
- Web server: Apache (with `mod_rewrite`) or Nginx

No Composer packages required for the core application.
