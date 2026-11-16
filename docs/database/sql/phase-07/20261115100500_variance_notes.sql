-- Apply once in order after WP1, using migration OR SQL on each database, never both.
-- Back up and rehearse native fresh/upgrade/partial-DDL recovery. Never drop evidence-bearing tables.
CREATE TABLE reconciliation_variance_notes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, reconciliation_session_id BIGINT UNSIGNED NOT NULL,
 reconciliation_variance_id BIGINT UNSIGNED NOT NULL,
 note_type VARCHAR(24) NOT NULL, note_text VARCHAR(2000) NOT NULL,
 created_by_user_id INT UNSIGNED NOT NULL, created_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_variance_notes_public UNIQUE (public_id),
 INDEX ix_variance_notes_scope (tenant_id,shop_id,reconciliation_variance_id,created_at,id),
 INDEX ix_variance_notes_control (reconciliation_session_id,created_at),
 CONSTRAINT ck_variance_notes_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'),
 CONSTRAINT ck_variance_notes_type CHECK (note_type IN ('EXPLANATION','INVESTIGATION_FINDING','REVIEW_NOTE')),
 CONSTRAINT ck_variance_notes_text CHECK (CHAR_LENGTH(TRIM(note_text)) BETWEEN 1 AND 2000),
 CONSTRAINT fk_variance_notes_source FOREIGN KEY (tenant_id,shop_id,reconciliation_session_id,reconciliation_variance_id) REFERENCES reconciliation_variances(tenant_id,shop_id,reconciliation_session_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_variance_notes_author FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
