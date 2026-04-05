ALTER TABLE users
  ADD COLUMN terms_accepted_at DATETIME NULL AFTER is_active,
  ADD COLUMN privacy_accepted_at DATETIME NULL AFTER terms_accepted_at,
  ADD COLUMN consent_ip VARCHAR(45) NULL AFTER privacy_accepted_at;