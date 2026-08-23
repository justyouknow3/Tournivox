CREATE DATABASE IF NOT EXISTS tournivox
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE tournivox;


CREATE TABLE IF NOT EXISTS users (

    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    first_name VARCHAR(75) NOT NULL,

    last_name VARCHAR(75) NOT NULL,

    username VARCHAR(50) NOT NULL UNIQUE,

    email VARCHAR(150) NOT NULL UNIQUE,

    password VARCHAR(255) NOT NULL,

    role ENUM(
        'team_captain',
        'staff',
        'broadcast_operator',
        'bracket_admin',
        'organizer',
        'admin'
    ) NOT NULL DEFAULT 'team_captain',

    status ENUM(
        'active',
        'inactive'
    ) NOT NULL DEFAULT 'active',

    created_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP

);


ALTER TABLE users

MODIFY COLUMN role ENUM(
    'user',
    'team_captain',
    'staff',
    'broadcast_operator',
    'bracket_admin',
    'organizer',
    'admin'
) NOT NULL DEFAULT 'team_captain';


UPDATE users
SET role = 'team_captain'
WHERE role = 'user';


ALTER TABLE users

MODIFY COLUMN role ENUM(
    'team_captain',
    'staff',
    'broadcast_operator',
    'bracket_admin',
    'organizer',
    'admin'
) NOT NULL DEFAULT 'team_captain';


INSERT INTO users (
    first_name,
    last_name,
    username,
    email,
    password,
    role,
    status
)
VALUES (
    'Bracket',
    'Administrator',
    'bracketadmin',
    'bracketadmin@tournivox.local',
    '$argon2id$v=19$m=65536,t=4,p=1$YzBQUC9qSTEzemlaVDl5cA$arEYIjYp1XpVwVCCqOg/PGLKOsgZy0EkVaHkLIGvE7Y',
    'bracket_admin',
    'active'
);