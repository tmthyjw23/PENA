CREATE TABLE IF NOT EXISTS letter_signatures (
 id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
 version_id INT NOT NULL,
 slot_key VARCHAR(80) NOT NULL,
 content_hash CHAR(64) NOT NULL,
 png MEDIUMBLOB NOT NULL,
 image_sha256 CHAR(64) NOT NULL,
 user_id INT NULL,
 request_id INT NULL,
 auth_method ENUM('account','link') NOT NULL,
 consented_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY signature_per_slot (version_id,slot_key),
 CONSTRAINT signature_version FOREIGN KEY (version_id) REFERENCES letter_versions(id) ON DELETE CASCADE,
 CONSTRAINT signature_request FOREIGN KEY (request_id) REFERENCES approval_requests(id),
 CONSTRAINT signature_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
