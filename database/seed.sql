```sql
-- Eventra Seed Data

USE eventra_db;

-- Seed Admin
INSERT INTO
    users (
        name,
        email,
        password,
        role,
        status
    )
VALUES (
        'Admin User',
        'admin123@gmail.com',
        '$2y$10$iPiJGuc.fOdzO109eUDsvefK44TZwvQlCICiVxbD1KHYRx1lxwrVS',
        'admin',
        'offline'
    );
-- The password above is the hash for 'admin@@12345'

-- Seed a sample client
INSERT INTO
    users (name, email, role, status)
VALUES (
        'Sample Client',
        'client@example.com',
        'client',
        'offline'
    );

-- Seed a sample user
INSERT INTO
    users (name, email, role, status)
VALUES (
        'Sample User',
        'user@example.com',
        'user',
        'offline'
    );