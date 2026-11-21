CREATE TABLE IF NOT EXISTS platform_operators (
 user_id INT NOT NULL PRIMARY KEY,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_platform_operator_user FOREIGN KEY(user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS operator_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT NULL,
 action VARCHAR(40) NOT NULL,
 target_user_id INT NULL,
 reason VARCHAR(300) NOT NULL,
 before_status VARCHAR(20) NULL,
 after_status VARCHAR(20) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_operator_event_time(created_at,id),
 CONSTRAINT fk_operator_event_actor FOREIGN KEY(actor_user_id) REFERENCES users(id),
 CONSTRAINT fk_operator_event_target FOREIGN KEY(target_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS operator_checks (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 actor_user_id INT NOT NULL,
 status ENUM('ok','error') NOT NULL,
 model VARCHAR(150) NOT NULL,
 latency_ms INT UNSIGNED NULL,
 message VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_operator_check_actor FOREIGN KEY(actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
