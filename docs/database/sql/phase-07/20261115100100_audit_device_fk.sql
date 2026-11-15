-- Apply once after registered_devices. Native preflight must return no rows; stop otherwise.
-- SELECT a.id FROM audit_events a LEFT JOIN registered_devices d ON d.id=a.actor_device_id
-- WHERE a.actor_device_id IS NOT NULL AND (d.id IS NULL OR a.tenant_id IS NULL OR a.shop_id IS NULL OR a.tenant_id<>d.tenant_id OR a.shop_id<>d.shop_id) LIMIT 1;
-- Do not rewrite Audit or remove this FK around existing Device evidence.
ALTER TABLE audit_events ADD CONSTRAINT fk_audit_events_device FOREIGN KEY (actor_device_id) REFERENCES registered_devices(id) ON UPDATE RESTRICT ON DELETE RESTRICT;
