<?php
/**
 * CitySync - Configuration File
 * Update these values before running the project
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'citysync');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', 'super');           // Change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// OpenAI Configuration
define('citysync', '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'); // Replace with your OpenAI API key
define('OPENAI_MODEL', 'gpt-3.5-turbo');

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// App Configuration
define('APP_NAME', 'CitySync');
define('SESSION_NAME', 'citysync_session');

// CORS - allow requests from frontend
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
