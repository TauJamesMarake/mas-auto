<?php
/**
 * contact.php — Secure Contact Form Endpoint
 * ─────────────────────────────────────────────────────────────
 * Security measures applied:
 *   1. CORS locked to your domain only
 *   2. Rate limiting — 10 messages per IP per hour
 *   3. Honeypot field — catches bots
 *   4. Spam keyword filter — blocks common spam patterns
 *   5. Server-side validation
 *   6. Prepared statements
 *   7. No internal errors exposed to client
 *   8. Credentials from .env
 */

// ── Load environment ──────────────────────────────────────────
require_once __DIR__ . '/../../vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'SITE_URL'])->notEmpty();

// ── CORS ──────────────────────────────────────────────────────
$allowed_origin = rtrim($_ENV['SITE_URL'], '/');
header("Access-Control-Allow-Origin: {$allowed_origin}");
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['success' => false, 'message' => 'Method not allowed.']); exit; }

// ── Dependencies ──────────────────────────────────────────────
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

// ── Rate limiting — 10 messages per IP per hour ───────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$db = get_db();

$rate_stmt = $db->prepare("
    SELECT COUNT(*) as attempts FROM contact_messages
    WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
");
$rate_stmt->bind_param('s', $ip);
$rate_stmt->execute();
$rate_result = $rate_stmt->get_result()->fetch_assoc();
$rate_stmt->close();

if ($rate_result['attempts'] >= 10) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many messages. Please call us directly on 060 756 0744.']);
    exit;
}

// ── Parse JSON ────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

// ── Honeypot ──────────────────────────────────────────────────
if (!empty($data['website'])) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Message sent.']);
    exit;
}

// ── Sanitise ──────────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

$name    = clean($data['name']    ?? '');
$phone   = clean($data['phone']   ?? '');
$email   = clean($data['email']   ?? '');
$message = clean($data['message'] ?? '');

// ── Spam keyword filter ───────────────────────────────────────
// Blocks common spam phrases before they hit the database.
$spam_keywords = ['casino', 'viagra', 'crypto', 'bitcoin', 'loan offer', 'click here', 'buy now', 'free money', 'make money fast'];
$message_lower = strtolower($message);
foreach ($spam_keywords as $keyword) {
    if (str_contains($message_lower, $keyword)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Message flagged. Please contact us by phone if this is an error.']);
        exit;
    }
}

// Block messages with excessive URLs (more than 2)
if (preg_match_all('/https?:\/\//', $message) > 2) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message contains too many links.']);
    exit;
}

// ── Validation ────────────────────────────────────────────────
$errors = [];
if (empty($name))                                    $errors[] = 'Name is required.';
if (strlen($name) > 120)                             $errors[] = 'Name is too long.';
if (empty($phone))                                   $errors[] = 'Phone number is required.';
if (!preg_match('/^[0-9+\s\-]{10,15}$/', $phone))   $errors[] = 'Invalid phone number.';
if (empty($message))                                 $errors[] = 'Message cannot be empty.';
if (strlen($message) < 10)                           $errors[] = 'Message is too short.';
if (strlen($message) > 2000)                         $errors[] = 'Message is too long (max 2000 characters).';
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── Insert into MySQL ─────────────────────────────────────────
$stmt = $db->prepare("
    INSERT INTO contact_messages (name, phone, email, message, ip_address, created_at)
    VALUES (?, ?, ?, ?, ?, NOW())
");

if (!$stmt) {
    error_log('Contact prepare failed: ' . $db->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    exit;
}

$stmt->bind_param('sssss', $name, $phone, $email, $message, $ip);

if (!$stmt->execute()) {
    error_log('Contact insert failed: ' . $stmt->error);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send message. Please try again.']);
    $stmt->close();
    exit;
}

$message_id = $stmt->insert_id;
$stmt->close();

// ── Notify staff ──────────────────────────────────────────────
$contact_data = compact('name', 'phone', 'email', 'message');
send_contact_notification($contact_data, $message_id);

// ── Success ───────────────────────────────────────────────────
http_response_code(201);
echo json_encode(['success' => true, 'message' => "Message received! We'll be in touch shortly."]);