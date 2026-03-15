-- ═══════════════════════════════════════════════════════════════
-- schema.sql — MAS Auto MySQL Database Schema (Secure Version)
-- ═══════════════════════════════════════════════════════════════
-- HOW TO RUN:
--   cPanel → phpMyAdmin → select your database → SQL tab → paste → Go
--
-- Run ONCE on first setup. Safe to re-run (IF NOT EXISTS).
-- ═══════════════════════════════════════════════════════════════


-- ── Bookings Table ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS bookings (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name           VARCHAR(120)    NOT NULL,
    phone          VARCHAR(20)     NOT NULL,
    email          VARCHAR(180)    DEFAULT NULL,
    vehicle        VARCHAR(150)    NOT NULL,
    service        VARCHAR(100)    NOT NULL,
    preferred_date DATE            NOT NULL,
    preferred_time VARCHAR(30)     DEFAULT NULL,
    notes          TEXT            DEFAULT NULL,
    ip_address     VARCHAR(45)     DEFAULT NULL,   -- Stored for rate limiting (IPv4 + IPv6 safe)
    status         ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at     DATETIME        NOT NULL,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_bookings_status     ON bookings (status);
CREATE INDEX IF NOT EXISTS idx_bookings_date       ON bookings (preferred_date);
CREATE INDEX IF NOT EXISTS idx_bookings_ip_time    ON bookings (ip_address, created_at); -- For rate limiting query


-- ── Contact Messages Table ────────────────────────────────────
CREATE TABLE IF NOT EXISTS contact_messages (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name        VARCHAR(120)    NOT NULL,
    phone       VARCHAR(20)     NOT NULL,
    email       VARCHAR(180)    DEFAULT NULL,
    message     TEXT            NOT NULL,
    ip_address  VARCHAR(45)     DEFAULT NULL,      -- Stored for rate limiting
    is_read     TINYINT(1)      NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL,

    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS idx_messages_read       ON contact_messages (is_read);
CREATE INDEX IF NOT EXISTS idx_messages_ip_time    ON contact_messages (ip_address, created_at); -- For rate limiting query


-- ═══════════════════════════════════════════════════════════════
-- DAILY MANAGEMENT QUERIES (run in phpMyAdmin → SQL tab)
-- ═══════════════════════════════════════════════════════════════

-- All pending bookings, soonest first:
-- SELECT id, name, phone, vehicle, service, preferred_date, preferred_time FROM bookings WHERE status = 'pending' ORDER BY preferred_date ASC;

-- Today's bookings:
-- SELECT * FROM bookings WHERE preferred_date = CURDATE() ORDER BY preferred_time ASC;

-- This week's bookings:
-- SELECT * FROM bookings WHERE preferred_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY preferred_date, preferred_time;

-- Confirm a booking (replace 1 with actual id):
-- UPDATE bookings SET status = 'confirmed' WHERE id = 1;

-- Mark completed:
-- UPDATE bookings SET status = 'completed' WHERE id = 1;

-- Unread contact messages:
-- SELECT id, name, phone, message, created_at FROM contact_messages WHERE is_read = 0 ORDER BY created_at DESC;

-- Mark message as read:
-- UPDATE contact_messages SET is_read = 1 WHERE id = 1;

-- Check if an IP is rate-limited (replace the IP):
-- SELECT COUNT(*) FROM bookings WHERE ip_address = '196.x.x.x' AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR);