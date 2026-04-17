#!/bin/bash

# Start cron in background
service cron start 2>/dev/null || true

# Wait for MySQL to be ready
echo "Waiting for MySQL..."
MAX_TRIES=30
COUNT=0
while ! mysqladmin ping -h"$DB_HOST" --skip-ssl --silent 2>/dev/null; do
    COUNT=$((COUNT+1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "WARNING: MySQL not ready after ${MAX_TRIES} attempts, starting Apache anyway..."
        break
    fi
    echo "  MySQL not ready yet (attempt $COUNT/$MAX_TRIES)..."
    sleep 2
done
echo "MySQL is ready!"

# Check if this is first run (no admin user exists)
ADMIN_EXISTS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -sN -e "SELECT COUNT(*) FROM users WHERE username='admin'" 2>/dev/null || echo "0")

if [ "$ADMIN_EXISTS" = "0" ]; then
    ADMIN_PASS=$(openssl rand -base64 12)
    ADMIN_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);" 2>/dev/null)

    if [ -n "$ADMIN_HASH" ]; then
        mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -e "
            INSERT INTO users (username, password_hash, display_name, wireguard_ip, role, enabled, totp_enabled)
            VALUES ('admin', '$ADMIN_HASH', 'Administrator', '0.0.0.0', 'admin', 1, 0);
        " 2>/dev/null

        if [ $? -eq 0 ]; then
            echo ""
            echo "============================================"
            echo "  FIRST RUN - Admin Account Created"
            echo "============================================"
            echo "  Username: admin"
            echo "  Password: $ADMIN_PASS"
            echo "  "
            echo "  SAVE THIS PASSWORD! It won't be shown again."
            echo "  Change it after first login."
            echo "  Update wireguard_ip for admin user."
            echo "============================================"
            echo ""
        else
            echo "WARNING: Failed to create admin user"
        fi
    else
        echo "WARNING: Failed to generate password hash"
    fi
fi

echo "Starting Apache..."

# Execute the main command (apache2-foreground)
exec "$@"
