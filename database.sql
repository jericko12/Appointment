-- First, drop the database if it exists to start fresh
DROP DATABASE IF EXISTS appointment_system;

CREATE DATABASE appointment_system;
USE appointment_system;

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('client', 'moderator', 'admin') NOT NULL,
    work_count INT DEFAULT 0
);

CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    client_id INT,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Create admin account
INSERT INTO users (username, password, role) VALUES 
('admin', '$2y$10$rGNWZbZKKNbJWQX5tpNyYOIuAWxX4o7ZVqk.YJo8Q8OOpNz/WGh3O', 'admin');