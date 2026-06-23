-- =====================================================================
-- Smart Meal Management & Delivery System
-- Full Database Schema (MySQL 5.7+ / 8.0)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS smart_meal_system
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE smart_meal_system;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. USERS TABLE (role-based: student, employee, public, restaurant, admin)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    user_id        INT AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(120)  NOT NULL,
    email          VARCHAR(150)  NOT NULL UNIQUE,
    phone          VARCHAR(20)   NOT NULL,
    password_hash  VARCHAR(255)  NOT NULL,
    role           ENUM('student','employee','public','restaurant','admin') NOT NULL DEFAULT 'public',
    -- role-specific optional fields
    student_id_no  VARCHAR(50)   DEFAULT NULL,
    employee_id_no VARCHAR(50)   DEFAULT NULL,
    institution    VARCHAR(150)  DEFAULT NULL,  -- university / company name
    address        VARCHAR(255)  DEFAULT NULL,
    profile_image  VARCHAR(255)  DEFAULT NULL,
    status         ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. RESTAURANTS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS restaurants;
CREATE TABLE restaurants (
    restaurant_id   INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,                 -- link to users table (role='restaurant')
    restaurant_name VARCHAR(150) NOT NULL,
    description     TEXT DEFAULT NULL,
    license_no      VARCHAR(100) DEFAULT NULL,
    address         VARCHAR(255) NOT NULL,
    city            VARCHAR(100) DEFAULT NULL,
    latitude        DECIMAL(10,7) DEFAULT NULL,
    longitude       DECIMAL(10,7) DEFAULT NULL,
    logo_image      VARCHAR(255) DEFAULT NULL,
    is_approved     TINYINT(1) NOT NULL DEFAULT 0, -- admin must approve
    is_open         TINYINT(1) NOT NULL DEFAULT 1, -- restaurant toggles availability
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_restaurant_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. MEALS TABLE (food items added by restaurants)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS meals;
CREATE TABLE meals (
    meal_id        INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id  INT NOT NULL,
    meal_name      VARCHAR(150) NOT NULL,
    description    TEXT DEFAULT NULL,
    meal_type      ENUM('breakfast','lunch','dinner') NOT NULL,
    price          DECIMAL(10,2) NOT NULL,
    image_path     VARCHAR(255) DEFAULT NULL,
    is_available   TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_meal_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. ORDERS TABLE (meal requests)
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS orders;
CREATE TABLE orders (
    order_id        INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    restaurant_id   INT NOT NULL,
    meal_id         INT NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price      DECIMAL(10,2) NOT NULL,        -- snapshot of price at order time
    total_price     DECIMAL(10,2) NOT NULL,
    delivery_address VARCHAR(255) DEFAULT NULL,
    order_status    ENUM('pending','accepted','rejected','preparing','out_for_delivery','delivered','cancelled')
                     NOT NULL DEFAULT 'pending',
    order_date      DATE NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_user       FOREIGN KEY (user_id)       REFERENCES users(user_id)             ON DELETE CASCADE,
    CONSTRAINT fk_order_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id)  ON DELETE CASCADE,
    CONSTRAINT fk_order_meal       FOREIGN KEY (meal_id)       REFERENCES meals(meal_id)              ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. PAYMENTS TABLE
--    Students & Employees -> monthly billing cycle
--    General Public       -> weekly billing cycle
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
    payment_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    billing_cycle   ENUM('weekly','monthly') NOT NULL,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    total_meals     INT NOT NULL DEFAULT 0,
    total_amount    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    due_date        DATE NOT NULL,
    payment_method  ENUM('bkash','nagad','card','cash','unpaid') NOT NULL DEFAULT 'unpaid',
    transaction_ref VARCHAR(120) DEFAULT NULL,     -- gateway transaction id
    status          ENUM('pending','paid','overdue','failed') NOT NULL DEFAULT 'pending',
    paid_at         TIMESTAMP NULL DEFAULT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. DELIVERY TRACKING TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS delivery_tracking;
CREATE TABLE delivery_tracking (
    tracking_id      INT AUTO_INCREMENT PRIMARY KEY,
    order_id         INT NOT NULL UNIQUE,
    delivery_person_name  VARCHAR(120) DEFAULT NULL,
    delivery_person_phone VARCHAR(20)  DEFAULT NULL,
    current_lat      DECIMAL(10,7) DEFAULT NULL,
    current_lng      DECIMAL(10,7) DEFAULT NULL,
    destination_lat   DECIMAL(10,7) DEFAULT NULL,
    destination_lng   DECIMAL(10,7) DEFAULT NULL,
    estimated_minutes INT DEFAULT NULL,
    delivery_status  ENUM('assigned','picked_up','on_the_way','delivered') NOT NULL DEFAULT 'assigned',
    last_updated     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tracking_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. NOTIFICATIONS TABLE
-- ---------------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(150) NOT NULL,
    message         TEXT NOT NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- INDEXES for performance
-- =====================================================================
CREATE INDEX idx_orders_user        ON orders(user_id);
CREATE INDEX idx_orders_restaurant  ON orders(restaurant_id);
CREATE INDEX idx_orders_status      ON orders(order_status);
CREATE INDEX idx_orders_date        ON orders(order_date);
CREATE INDEX idx_meals_restaurant   ON meals(restaurant_id);
CREATE INDEX idx_meals_type         ON meals(meal_type);
CREATE INDEX idx_payments_user      ON payments(user_id);
CREATE INDEX idx_payments_status    ON payments(status);
CREATE INDEX idx_users_role         ON users(role);

-- =====================================================================
-- SAMPLE DATA
-- =====================================================================

-- Admin (password: Admin@123)
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('System Admin', 'admin@smartmeal.com', '01700000000',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
-- NOTE: this hash is a placeholder; real hashes are generated by seed.php (run it instead of trusting this).

-- Sample Student (password: Student@123)
INSERT INTO users (full_name, email, phone, password_hash, role, student_id_no, institution, address) VALUES
('Rafiq Islam', 'student@example.com', '01711111111',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'CSE-2021-045', 'Dhaka University', 'Hall 3, Dhaka University');

-- Sample Employee (password: Employee@123)
INSERT INTO users (full_name, email, phone, password_hash, role, employee_id_no, institution, address) VALUES
('Nasrin Akter', 'employee@example.com', '01722222222',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employee', 'EMP-2233', 'Tech Solutions Ltd', 'Gulshan-2, Dhaka');

-- Sample General Public (password: Public@123)
INSERT INTO users (full_name, email, phone, password_hash, role, address) VALUES
('Karim Sheikh', 'public@example.com', '01733333333',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'public', 'Mirpur-10, Dhaka');

-- Sample Restaurant owner users
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('Spice Garden Owner', 'spicegarden@example.com', '01744444444',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'restaurant'),
('Daily Mess Owner', 'dailymess@example.com', '01755555555',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'restaurant');

-- Restaurants (linked to user_id 5 and 6)
INSERT INTO restaurants (user_id, restaurant_name, description, address, city, latitude, longitude, is_approved, is_open) VALUES
(5, 'Spice Garden Restaurant', 'Authentic Bengali and Indian cuisine', 'Banani, Dhaka', 'Dhaka', 23.7937, 90.4066, 1, 1),
(6, 'Daily Mess Kitchen', 'Affordable daily meals for students & employees', 'Dhanmondi, Dhaka', 'Dhaka', 23.7461, 90.3742, 1, 1);

-- Meals
INSERT INTO meals (restaurant_id, meal_name, description, meal_type, price, is_available) VALUES
(1, 'Vegetable Khichuri', 'Rice and lentils cooked with mixed vegetables', 'breakfast', 60.00, 1),
(1, 'Paratha & Egg Curry', 'Flatbread with spicy egg curry', 'breakfast', 50.00, 1),
(1, 'Chicken Biryani', 'Aromatic basmati rice with chicken', 'lunch', 150.00, 1),
(1, 'Beef Bhuna with Rice', 'Slow cooked beef curry with steamed rice', 'lunch', 180.00, 1),
(1, 'Fish Curry with Rice', 'Traditional fish curry with rice', 'dinner', 140.00, 1),
(2, 'Plain Paratha & Dal', 'Simple paratha with lentil curry', 'breakfast', 35.00, 1),
(2, 'Mixed Vegetable Rice', 'Rice with seasonal mixed vegetables', 'lunch', 80.00, 1),
(2, 'Chicken Curry with Rice', 'Home-style chicken curry', 'lunch', 110.00, 1),
(2, 'Egg Curry with Rice', 'Boiled egg curry with rice', 'dinner', 70.00, 1),
(2, 'Lentil Soup & Bread', 'Light dinner option', 'dinner', 55.00, 1);

-- Sample Orders
INSERT INTO orders (user_id, restaurant_id, meal_id, quantity, unit_price, total_price, delivery_address, order_status, order_date) VALUES
(2, 1, 3, 1, 150.00, 150.00, 'Hall 3, Dhaka University', 'delivered', CURDATE() - INTERVAL 2 DAY),
(2, 2, 7, 2, 80.00, 160.00, 'Hall 3, Dhaka University', 'delivered', CURDATE() - INTERVAL 1 DAY),
(3, 1, 5, 1, 140.00, 140.00, 'Gulshan-2, Dhaka', 'preparing', CURDATE()),
(4, 2, 9, 1, 70.00, 70.00, 'Mirpur-10, Dhaka', 'pending', CURDATE());

-- Sample Payments
INSERT INTO payments (user_id, billing_cycle, period_start, period_end, total_meals, total_amount, due_date, payment_method, status) VALUES
(2, 'monthly', DATE_FORMAT(CURDATE(),'%Y-%m-01'), LAST_DAY(CURDATE()), 3, 310.00, LAST_DAY(CURDATE()), 'unpaid', 'pending'),
(3, 'monthly', DATE_FORMAT(CURDATE(),'%Y-%m-01'), LAST_DAY(CURDATE()), 1, 140.00, LAST_DAY(CURDATE()), 'unpaid', 'pending'),
(4, 'weekly', CURDATE() - INTERVAL WEEKDAY(CURDATE()) DAY, CURDATE() + INTERVAL (6-WEEKDAY(CURDATE())) DAY, 1, 70.00, CURDATE() + INTERVAL (6-WEEKDAY(CURDATE())) DAY, 'unpaid', 'pending');

-- Sample delivery tracking
INSERT INTO delivery_tracking (order_id, delivery_person_name, delivery_person_phone, current_lat, current_lng, destination_lat, destination_lng, estimated_minutes, delivery_status) VALUES
(3, 'Sumon Mia', '01788888888', 23.7800, 90.4000, 23.7937, 90.4066, 12, 'on_the_way'),
(4, 'Jasim Uddin', '01799999999', 23.7500, 90.3700, 23.7461, 90.3742, 8, 'picked_up');
