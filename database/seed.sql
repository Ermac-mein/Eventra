```sql
-- Eventra Seed Data

USE eventra_db;

-- Seed Admin
INSERT INTO
    users (
        internal_id,
        name,
        email,
        password,
        role,
        auth_method,
        status
    )
VALUES (
        'ACC-000001',
        'Admin User',
        'admin123@gmail.com',
        '$2y$10$iPiJGuc.fOdzO109eUDsvefK44TZwvQlCICiVxbD1KHYRx1lxwrVS',
        'admin',
        'password',
        'offline'
    );
-- The password above is the hash for 'admin@@12345'