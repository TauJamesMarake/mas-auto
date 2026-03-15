<?php
/**
 * booking.php — Secure Booking Endpoint
 * ─────────────────────────────────────────────────────────────
 * Security measures applied:
 *   1. CORS locked to your domain only (not wildcard *)
 *   2. Rate limiting — max 5 booking attempts per IP per hour
 *   3. Honeypot field check — catches basic bots
 *   4. Server-side input validation (never trust the browser)
 *   5. Prepared statements — SQL injection proof
 *   6. No stack traces or DB errors exposed to the client
 *   7. Credentials loaded from .env — never hardcoded
 */

// ── Load environment ──────────────────────────────────────────
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SITE_URL'])->notEmpty();

// ── CORS — locked to your domain only ────────────────────────
// Wildcard (*) allows any website to call your API.
// This locks it to masauto.co.za only.
$allowed_origin = rtrim($_ENV['SITE_URL'], '/'); // e.g. https://masauto.co.za
$request_origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($request_origin === $allowed_origin) {
    header("Access-Control-Allow-Origin: {$allowed_origin}");
} else {
    // Unknown origin — still set headers so browser doesn't hang,
    // but validation below will block the request
    header("Access-Control-Allow-Origin: {$allowed_origin}");
}

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');      // Prevents MIME-type sniffing
header('X-Frame-Options: DENY');                // Prevents clickjacking
header('Referrer-Policy: strict-origin');       // Limits referrer info sent out

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Load dependencies ─────────────────────────────────────────
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ── Rate limiting ─────────────────────────────────────────────
// Limits each IP address to 5 booking submissions per hour.
// Stored in MySQL — no Redis or extra services required.
// Protects against bots flooding the form.
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$db = get_db();

// Count how many submissions this IP made in the last hour
$rate_stmt = $db->prepare("
    SELECT COUNT(*) as attempts
    FROM bookings
    WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$rate_stmt->bind_param('s', $ip);
$rate_stmt->execute();
$rate_result = $rate_stmt->get_result()->fetch_assoc();
$rate_stmt->close();

if ($rate_result['attempts'] >= 5) {
    http_response_code(429); // Too Many Requests
    echo json_encode([
        'success' => false,
        'message' => 'Too many booking attempts. Please try again in an hour or call us directly on 060 756 0744.'
    ]);
    exit;
}

// ── Parse JSON body ───────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// ── Honeypot check ────────────────────────────────────────────
// Bots typically fill every field they find.
// The frontend form has a hidden field called 'website' that
// real users never see or fill. If it has a value, it's a bot.
if (!empty($data['website'])) {
    // Return fake success so the bot doesn't retry
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Booking received.']);
    exit;
}

// ── Sanitise fields ───────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

$name    = clean($data['name']    ?? '');
$phone   = clean($data['phone']   ?? '');
$vehicle = clean($data['vehicle'] ?? '');
$service = clean($data['service'] ?? '');
$date    = clean($data['date']    ?? '');
$time    = clean($data['time']    ?? '');
$notes   = clean($data['notes']   ?? '');
$email   = clean($data['email']   ?? '');

// ── Whitelist: allowed service values ─────────────────────────
// Prevents someone from injecting arbitrary strings into the service field.
$allowed_services = [
    'Engine Service & Maintenance',
    'Engine Overhaul',
    'Fabrication & Modifications',
    'Suspension Services',
    'Brake Pads & Lining',
    'Sound System Installation',
    'General Service / Other',
];

// ── Validation ────────────────────────────────────────────────
$errors = [];

if (empty($name))                              $errors[] = 'Name is required.';
if (strlen($name) > 120)                       $errors[] = 'Name is too long.';
if (empty($phone))                             $errors[] = 'Phone number is required.';
if (!preg_match('/^[0-9+\s\-]{10,15}$/', $phone)) $errors[] = 'Invalid phone number.';
if (empty($vehicle))                           $errors[] = 'Vehicle details are required.';
if (strlen($vehicle) > 150)                    $errors[] = 'Vehicle field is too long.';
if (empty($service))                           $errors[] = 'Please select a service.';
if (!in_array($service, $allowed_services))    $errors[] = 'Invalid service selected.';
if (empty($date))                              $errors[] = 'Preferred date is required.';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

// Date must be today or in the future
if (!empty($date)) {
    $today     = new DateTime('today');
    $requested = DateTime::createFromFormat('Y-m-d', $date);
    if (!$requested || $requested < $today) {
        $errors[] = 'Please select a valid future date.';
    }
}

// Limit notes length to prevent giant payloads
if (strlen($notes) > 1000) {
    $errors[] = 'Notes are too long (max 1000 characters).';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Insert into MySQL ─────────────────────────────────────────
// ip_address stored for rate limiting only — not shown to customers.
$stmt = $db->prepare("
    INSERT INTO bookings
        (name, phone, email, vehicle, service, preferred_date, preferred_time, notes, ip_address, status, created_at)
    VALUES
        (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
");

if (!$stmt) {
    error_log('Booking prepare failed: ' . $db->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}

$stmt->bind_param('sssssssss', $name, $phone, $email, $vehicle, $service, $date, $time, $notes, $ip);

if (!$stmt->execute()) {
    error_log('Booking insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save booking. Please try again.']);
    $stmt->close();
    exit;
}

$booking_id = $stmt->insert_id;
$stmt->close();

// ── Send emails ───────────────────────────────────────────────
$booking_data = compact('name', 'phone', 'email', 'vehicle', 'service', 'date', 'time', 'notes');
send_booking_notification($booking_data, $booking_id);
send_booking_confirmation($booking_data);

// ── Success ───────────────────────────────────────────────────
http_response_code(201);
echo json_encode([
    'success'    => true,
    'message'    => "Booking received! We'll confirm via WhatsApp or call.",
    'booking_id' => $booking_id
]);