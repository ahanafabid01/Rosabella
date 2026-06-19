-- =============================================
-- Add Payment Attempts Table
-- =============================================
CREATE TABLE IF NOT EXISTS payment_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    gateway VARCHAR(32) NOT NULL,
    gateway_payment_id VARCHAR(255) NULL,
    gateway_transaction_id VARCHAR(255) NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'initiated',
    request_payload LONGTEXT NULL,
    response_payload LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payment_attempts_order_id (order_id),
    INDEX idx_payment_attempts_gateway (gateway),
    INDEX idx_payment_attempts_gateway_payment_id (gateway_payment_id),
    CONSTRAINT fk_payment_attempts_order
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
