<?php
/**
 * CitySync - Officer Login API
 * POST /backend/officer_login.php
 *
 * Expected POST:
 *   username (string)
 *   password (string)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required.']);
    exit();
}

$db   = getDB();
$stmt = $db->prepare("SELECT * FROM officers WHERE username = ?");
$stmt->execute([$username]);
$officer = $stmt->fetch();

if (!$officer || !password_verify($password, $officer['password'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    exit();
}

echo json_encode([
    'success'    => true,
    'message'    => 'Login successful',
    'officer_id' => $officer['id'],
    'username'   => $officer['username'],
    'department' => $officer['department'],
]);
