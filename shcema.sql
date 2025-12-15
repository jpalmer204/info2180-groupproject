
CREATE DATABASE IF NOT EXISTS dolphin_crm;
USE dolphin_crm;


CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role ENUM('Admin', 'Member') DEFAULT 'Member',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title ENUM('Mr', 'Mrs', 'Ms', 'Dr', 'Prof') NOT NULL,
    firstname VARCHAR(100) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    telephone VARCHAR(20),
    company VARCHAR(100),
    type ENUM('Sales Lead', 'Support') DEFAULT 'Sales Lead',
    assigned_to INT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);


CREATE TABLE notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    contact_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
INSERT INTO users (firstname, lastname, email, password, role) 
VALUES (
    'Admin', 
    'User', 
    'admin@project2.com', 
    '$2y$10$t41lK8C1L33x46esklNb8OHDJzxhM.yQudJtVemAuQwmJ/2fY2xyq', 
    'Admin'
);