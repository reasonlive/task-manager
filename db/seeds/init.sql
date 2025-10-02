-- Пример данных
INSERT INTO users (name, email, password, role) VALUES
    ('Admin', 'admin@example.com', '$2y$10$2gBzDoBwi6RZu5OZTRbaNe/F33K7MLmXI95pB3LxZHG6AnR9ipgoi', 'ADMIN'),
    ('User', 'user@example.com', '$2y$10$ubZCt/rxHBFw/k8KZNSTMuHd9tEU.lPPASMoharIOIV68ciTQ5/Q2', 'USER')
;

INSERT INTO tags (name) VALUES
    ('URGENT'),
    ('BUG'),
    ('FEATURE'),
    ('DOCUMENTATION')
;

INSERT INTO tasks (title, description, status, user_id) VALUES
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 1),
    ('Add user profile', 'Create user profile page with settings', 'IN_PROGRESS', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'READY', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'READY', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'FOR_REVIEW', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'DONE', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2),
    ('Fix login issue', 'Users cannot login with correct credentials', 'TODO', 2)
;