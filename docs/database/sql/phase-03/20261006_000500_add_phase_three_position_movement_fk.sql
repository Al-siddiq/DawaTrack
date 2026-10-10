SET time_zone = '+00:00';

ALTER TABLE stock_positions ADD CONSTRAINT fk_stock_positions_last_movement FOREIGN KEY (tenant_id,shop_id,last_movement_id) REFERENCES stock_movements(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT;

-- Rollback: ALTER TABLE stock_positions DROP FOREIGN KEY fk_stock_positions_last_movement;
