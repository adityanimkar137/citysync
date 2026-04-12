<?php
/**
 * CitySync - Database Connection
 * Returns a PDO connection instance
 */

require_once __DIR__ . '/config.php';

function getDB() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $e->getMessage()
            ]);
            exit();
        }
    }

    return $pdo;
}

/**
 * Get or create a user by email
 * Returns the user row (with anonymous_id)
 */
function getOrCreateUser(string $email): array {
    $db = getDB();

    // Check if user exists
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        return $user;
    }

    // Create new user with random anonymous ID
    $anonymousId = 'User#' . rand(1000, 9999);

    // Ensure uniqueness
    do {
        $check = $db->prepare("SELECT id FROM users WHERE anonymous_id = ?");
        $check->execute([$anonymousId]);
        if ($check->fetch()) {
            $anonymousId = 'User#' . rand(1000, 9999);
        } else {
            break;
        }
    } while (true);

    $insert = $db->prepare("INSERT INTO users (email, anonymous_id) VALUES (?, ?)");
    $insert->execute([$email, $anonymousId]);

    return [
        'id'           => $db->lastInsertId(),
        'email'        => $email,
        'anonymous_id' => $anonymousId
    ];
}
