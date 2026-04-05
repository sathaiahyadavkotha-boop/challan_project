CREATE TABLE IF NOT EXISTS gov_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(255) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,   -- changed from password_hash
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin with constant password
INSERT IGNORE INTO gov_users (username, password)
VALUES ('admin', 'admin123');
