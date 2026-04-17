#!/bin/bash
set -e

# Start cron in background
service cron start 2>/dev/null || true

# -----------------------------------------------------------------------------
# Wait for MySQL to be ready
# -----------------------------------------------------------------------------
echo "Waiting for MySQL at $DB_HOST..."
MAX_TRIES=60
COUNT=0
while ! mysqladmin ping -h"$DB_HOST" --skip-ssl --silent 2>/dev/null; do
    COUNT=$((COUNT+1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "ERROR: MySQL not ready after ${MAX_TRIES} attempts. Aborting."
        exit 1
    fi
    echo "  MySQL not ready yet (attempt $COUNT/$MAX_TRIES)..."
    sleep 2
done
echo "MySQL is ready!"

# -----------------------------------------------------------------------------
# Schema bootstrap: check if 'users' table exists, create schema if not
# -----------------------------------------------------------------------------
SCHEMA_FILE="/var/www/html/sql/schema.sql"

if [ ! -f "$SCHEMA_FILE" ]; then
    echo "ERROR: Schema file not found at $SCHEMA_FILE"
    exit 1
fi

TABLE_EXISTS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" \
    -sN -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_name='users'" 2>/dev/null || echo "0")

if [ "$TABLE_EXISTS" = "0" ]; then
    echo ""
    echo "============================================"
    echo "  First run detected - applying schema..."
    echo "============================================"

    if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" < "$SCHEMA_FILE"; then
        echo "  Schema applied successfully."
    else
        echo "  ERROR: Failed to apply schema. Aborting."
        exit 1
    fi
    echo "============================================"
    echo ""
else
    echo "Database schema already exists, skipping bootstrap."
fi

# -----------------------------------------------------------------------------
# Run migrations (optional)
# -----------------------------------------------------------------------------
MIGRATIONS_DIR="/var/www/html/sql/migrations"
if [ -d "$MIGRATIONS_DIR" ]; then
    mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" -e "
        CREATE TABLE IF NOT EXISTS schema_migrations (
            filename VARCHAR(255) PRIMARY KEY,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    " 2>/dev/null

    for migration in $(ls -1 "$MIGRATIONS_DIR"/*.sql 2>/dev/null | sort); do
        fname=$(basename "$migration")
        applied=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" \
            -sN -e "SELECT COUNT(*) FROM schema_migrations WHERE filename='$fname'" 2>/dev/null || echo "0")

        if [ "$applied" = "0" ]; then
            echo "Applying migration: $fname"
            if mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" < "$migration"; then
                mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" \
                    -e "INSERT INTO schema_migrations (filename) VALUES ('$fname');" 2>/dev/null
                echo "  Applied: $fname"
            else
                echo "  ERROR applying $fname - aborting"
                exit 1
            fi
        fi
    done
fi

# -----------------------------------------------------------------------------
# First-run admin user creation
# -----------------------------------------------------------------------------
ADMIN_EXISTS=$(mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" --skip-ssl "$DB_NAME" \
    -sN -e "SELECT COUNT(*) FROM users WHERE username='admin'" 2>/dev/null || echo "0")

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
            echo ""
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
exec "$@"
