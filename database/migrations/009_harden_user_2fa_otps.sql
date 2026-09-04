-- Harden login 2FA OTP storage for hashed codes and attempt tracking
ALTER TABLE user_2fa_otps
    MODIFY otp_code VARCHAR(255) NOT NULL;

ALTER TABLE user_2fa_otps
    ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER expires_at;

ALTER TABLE user_2fa_otps
    ADD UNIQUE KEY uniq_user_2fa (user_id);
