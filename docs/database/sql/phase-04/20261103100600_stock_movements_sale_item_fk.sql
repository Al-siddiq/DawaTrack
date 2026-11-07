ALTER TABLE stock_movements ADD CONSTRAINT fk_stock_movements_sale_item FOREIGN KEY (tenant_id,shop_id,sale_item_id) REFERENCES sale_items(tenant_id,shop_id,id) ON UPDATE RESTRICT ON DELETE RESTRICT;
