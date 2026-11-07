SET time_zone = '+00:00';

CREATE TABLE sale_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 tenant_id BIGINT UNSIGNED NOT NULL, shop_id BIGINT UNSIGNED NOT NULL, sale_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NOT NULL,
 original_sale_item_id BIGINT UNSIGNED NULL, line_number SMALLINT UNSIGNED NOT NULL,
 quantity DECIMAL(19,4) NOT NULL, unit_selling_price DECIMAL(19,2) NOT NULL, line_sales_amount DECIMAL(19,2) NOT NULL,
 unit_cost_snapshot DECIMAL(19,6) NOT NULL, line_cogs_amount DECIMAL(19,2) NOT NULL,
 created_at DATETIME(6) NOT NULL, updated_at DATETIME(6) NOT NULL,
 CONSTRAINT uq_sale_items_public UNIQUE (public_id), CONSTRAINT uq_sale_items_scope UNIQUE (tenant_id,shop_id,id),
 CONSTRAINT ck_sale_items_uuid CHECK (public_id REGEXP '^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$'), CONSTRAINT uq_sale_items_line UNIQUE (sale_id,line_number),
 INDEX ix_sale_items_product (tenant_id,shop_id,product_id,sale_id),
 CONSTRAINT ck_sale_items_amount CHECK (quantity>0 AND line_number>0 AND unit_selling_price>=0 AND line_sales_amount>=0 AND unit_cost_snapshot>=0 AND line_cogs_amount>=0),
 CONSTRAINT fk_sale_items_sale FOREIGN KEY (tenant_id,shop_id,sale_id) REFERENCES sales(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sale_items_product FOREIGN KEY (tenant_id,product_id) REFERENCES products(tenant_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT,
 CONSTRAINT fk_sale_items_original FOREIGN KEY (tenant_id,shop_id,original_sale_item_id) REFERENCES sale_items(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
