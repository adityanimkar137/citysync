-- CitySync Database Schema
-- Run this in your MySQL client or phpMyAdmin

CREATE DATABASE IF NOT EXISTS citysync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE citysync;

-- Users table (anonymous user system)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    anonymous_id VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Complaints table
CREATE TABLE IF NOT EXISTS complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(500) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'Other',
    priority VARCHAR(20) DEFAULT 'Medium',
    department VARCHAR(100) DEFAULT 'General',
    city VARCHAR(100) DEFAULT 'Nagpur',
    status VARCHAR(50) DEFAULT 'Pending',
    location VARCHAR(255) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    latitude DECIMAL(10, 8) DEFAULT NULL,
    longitude DECIMAL(11, 8) DEFAULT NULL,
    is_duplicate TINYINT(1) DEFAULT 0,
    duplicate_of INT DEFAULT NULL,
    image_hash VARCHAR(64) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (latitude, longitude),
    INDEX (image_hash),
    FULLTEXT INDEX (address)
);

-- Officers table (simple login)
CREATE TABLE IF NOT EXISTS officers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    department VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default officers (password: officer123)
-- Hash generated with PHP password_hash('officer123', PASSWORD_DEFAULT)
INSERT INTO officers (username, password, department) VALUES
('pwd_officer', '$2y$10$MjPK35Wx55Chea/PK63pcOd7./p8QdhJLADWlQ0YddFd6bWj59asq', 'PWD'),
('sanitation_officer', '$2y$10$MjPK35Wx55Chea/PK63pcOd7./p8QdhJLADWlQ0YddFd6bWj59asq', 'Sanitation'),
('water_officer', '$2y$10$MjPK35Wx55Chea/PK63pcOd7./p8QdhJLADWlQ0YddFd6bWj59asq', 'Water Department'),
('electricity_officer', '$2y$10$MjPK35Wx55Chea/PK63pcOd7./p8QdhJLADWlQ0YddFd6bWj59asq', 'Electrical Department'),
('general_officer', '$2y$10$MjPK35Wx55Chea/PK63pcOd7./p8QdhJLADWlQ0YddFd6bWj59asq', 'General');

-- NOTE: Default password for all officers is: officer123
-- Change these passwords in production!
