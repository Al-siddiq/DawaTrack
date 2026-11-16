-- Apply once in order after WP1, using migration OR SQL on each database, never both.
-- Back up and rehearse native fresh/upgrade/partial-DDL recovery. Never drop evidence-bearing tables.
CREATE TABLE reconciliation_device_acknowledgements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, reconciliation_session_id BIGINT UNSIGNED NOT NULL,
 registered_device_id BIGINT UNSIGNED NOT NULL, readiness_status VARCHAR(16) NOT NULL,
 acknowledged_change_sequence BIGINT UNSIGNED NULL, reason VARCHAR(500) NULL,
 decided_by_user_id INT UNSIGNED NOT NULL, decided_at DATETIME(6) NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_device_ack_control UNIQUE (reconciliation_session_id,registered_device_id),
 INDEX ix_device_ack_scope (tenant_id,shop_id,readiness_status),
 CONSTRAINT ck_device_ack_status CHECK ((readiness_status='ACKNOWLEDGED' AND acknowledged_change_sequence IS NOT NULL AND reason IS NULL) OR (readiness_status='EXCLUDED' AND acknowledged_change_sequence IS NULL AND reason IS NOT NULL AND CHAR_LENGTH(TRIM(reason))>0)),
 CONSTRAINT fk_device_ack_control FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id) REFERENCES reconciliation_sessions(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_device_ack_device FOREIGN KEY (tenant_id,shop_id,registered_device_id) REFERENCES registered_devices(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_device_ack_author FOREIGN KEY (decided_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
