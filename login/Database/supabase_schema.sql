-- Roles table
CREATE TABLE roles (
    id_rol SERIAL PRIMARY KEY,
    name VARCHAR(50)
);

-- Users table
CREATE TABLE users (
    id_user SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    lastname VARCHAR(100) NOT NULL,
    user_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL UNIQUE,
    user_active INTEGER NOT NULL DEFAULT 0,
    token_activation VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_rol INTEGER REFERENCES roles(id_rol)
);

-- Movements table
CREATE TABLE movements (
    id_movement SERIAL PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_user INTEGER REFERENCES users(id_user)
);

-- Necessary Expense table
CREATE TABLE necessary_expense (
    id_necessary SERIAL PRIMARY KEY,
    type VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    state VARCHAR(50) NOT NULL DEFAULT 'Pendiente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_user INTEGER REFERENCES users(id_user)
);

-- Debts table
CREATE TABLE debts (
    id_debt SERIAL PRIMARY KEY,
    concept VARCHAR(100) NOT NULL,
    type_debt VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    state VARCHAR(50) NOT NULL,
    id_user INTEGER REFERENCES users(id_user)
);

-- Pay Debt table
CREATE TABLE pay_debt (
    id_pay_debt SERIAL PRIMARY KEY,
    amount VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    method VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    id_debt INTEGER REFERENCES debts(id_debt)
);

-- Save table
CREATE TABLE save (
    id_save SERIAL PRIMARY KEY,
    amount VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_user INTEGER REFERENCES users(id_user)
);

-- User logs table (Audit trail)
CREATE TABLE user_logs (
    id_log SERIAL PRIMARY KEY,
    id_user INTEGER NOT NULL REFERENCES users(id_user),
    operation VARCHAR(50) NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id INTEGER NOT NULL,
    old_data TEXT,
    new_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles (optional but recommended)
INSERT INTO roles (name) VALUES ('admin'), ('user') ON CONFLICT DO NOTHING;
