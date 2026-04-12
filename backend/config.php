<?php
/**
 * CitySync - Configuration File
 * Update these values before running the project
 */

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'citysync');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '@parth07.');           // Change to your MySQL password
define('DB_CHARSET ', 'utf8mb4');

// OpenAI Configuration
define('OPENAI_API_KEY', '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'); // Replace with your OpenAI API key
define('OPENAI_MODEL', 'gpt-3.5-turbo');

// Cloudinary Configuration
define('CLOUDINARY_CLOUD_NAME', 'dozgn9jxn');      // Replace with your Cloudinary cloud name
define('CLOUDINARY_UPLOAD_PRESET', 'citysync');    // Replace with your upload preset
define('CLOUDINARY_USE_LOCAL_FALLBACK', false);    // Set to true to fallback to local uploads if Cloudinary fails

// File Upload Configuration
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// App Configuration
define('APP_NAME', 'CitySync');
define('SESSION_NAME', 'citysync_session');

// CORS - allow requests from frontend
if (PHP_SAPI !== 'cli') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json; charset=utf-8');

    // Handle preflight
    if (($_SERVER['REQUEST_METHOD'] ?? null) === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// Create uploads directory if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
