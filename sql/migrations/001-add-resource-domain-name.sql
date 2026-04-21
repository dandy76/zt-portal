-- Adds an optional domain_name to resources so the portal can auto-open
-- the correct URL (https://<domain>:<port>) after activation. When NULL,
-- the frontend falls back to https://<dst_address>:<dst_port>.

ALTER TABLE resources
    ADD COLUMN domain_name VARCHAR(255) DEFAULT NULL
    COMMENT 'Optional hostname for auto-open URL (e.g. budibase.lan). Falls back to dst_address if empty.'
    AFTER dst_port;
