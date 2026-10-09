CREATE DATABASE IF NOT EXISTS pms_v2
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE pms_v2;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (role IN ('user', 'admin')),
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    account_status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (account_status IN ('active', 'disabled', 'pending_verification')),
    failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    code_hash CHAR(64) NOT NULL,
    failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,  -- Added to match verify-email.php usage
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);

CREATE TABLE demat_accounts (
    demat_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    account_nickname VARCHAR(100) NOT NULL,
    account_holder_name VARCHAR(100) NOT NULL,
    dp_broker_name VARCHAR(150) NOT NULL,
    boid VARCHAR(16) NOT NULL UNIQUE,  -- Ensures leading zeros are preserved

    CONSTRAINT fk_demat_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);

CREATE TABLE companies (
    company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL UNIQUE,
    symbol VARCHAR(50) NOT NULL UNIQUE,
    category VARCHAR(100) NOT NULL,
    sector VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'removed'))
);

CREATE TABLE holdings (
    holding_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    demat_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(20,4) NOT NULL CHECK (quantity > 0),  -- Ensures positive quantity
    total_investment DECIMAL(20,2) NOT NULL CHECK (total_investment > 0),

    UNIQUE KEY uq_holding_demat_company (demat_id, company_id),

    CONSTRAINT fk_holding_demat
        FOREIGN KEY (demat_id)
        REFERENCES demat_accounts(demat_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_holding_company
        FOREIGN KEY (company_id)
        REFERENCES companies(company_id)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
);

CREATE TABLE news (
    news_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(500) NOT NULL,
    description TEXT NULL,
    publication_date DATETIME NOT NULL,
    source_url VARCHAR(2048) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'published', 'unpublished'))
);

CREATE TABLE password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_password_reset_token (token_hash),
    INDEX idx_password_reset_user (user_id)
);

CREATE TABLE email_change_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    new_email VARCHAR(255) NOT NULL,
    code_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_email_change_user (user_id),
    INDEX idx_email_change_code (code_hash)
);