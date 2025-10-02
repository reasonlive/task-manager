CREATE DATABASE IF NOT EXISTS task_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS task_manager.users (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       name VARCHAR(255) NOT NULL,
                       email VARCHAR(255) UNIQUE NOT NULL,
                       password VARCHAR(255) NOT NULL,
                       role ENUM('ADMIN', 'USER', 'MODERATOR') DEFAULT 'USER',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       is_active BOOLEAN DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS task_manager.tags (
                      id INT AUTO_INCREMENT PRIMARY KEY,
                      name VARCHAR(100) UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS task_manager.tasks (
                       id INT AUTO_INCREMENT PRIMARY KEY,
                       title VARCHAR(255) NOT NULL,
                       description TEXT,
                       status ENUM('TODO', 'IN_PROGRESS', 'READY', 'FOR_REVIEW', 'DONE') DEFAULT 'TODO',
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       user_id INT NOT NULL,
                       FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS task_manager.replies (
                         id INT AUTO_INCREMENT PRIMARY KEY,
                         text TEXT NOT NULL,
                         task_id INT NOT NULL,
                         user_id INT NOT NULL,
                         created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                         updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                         FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                         FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS task_manager.task_tags (
                           task_id INT,
                           tag_id INT,
                           PRIMARY KEY (task_id, tag_id),
                           FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                           FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
);