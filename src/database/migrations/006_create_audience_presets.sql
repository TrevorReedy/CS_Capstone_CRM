
CREATE TABLE audience_presets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    preset_name VARCHAR(255) NOT NULL,
    segment_name VARCHAR(255) NOT NULL,
    tag_filter VARCHAR(255) NULL,
    account_ids TEXT NULL,
    contact_ids TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
);
