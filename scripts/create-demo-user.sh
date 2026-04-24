#!/bin/bash
#
# Creates or deletes a demo user (no 2FA) for screenshots / presentations.
# NEVER use in production — credentials are hard-coded and well-known.
#
# Usage:
#   ./scripts/create-demo-user.sh           # create demo user
#   ./scripts/create-demo-user.sh --delete  # remove demo user
#

set -e

# Load .env from the project root
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."
if [ -f .env ]; then
    set -a; source .env; set +a
else
    echo "ERROR: .env not found in project root"
    exit 1
fi

DEMO_USER="demo"
DEMO_PASS="demo1234"
DEMO_DISPLAY="Demo Operator"
DEMO_IP="10.1.40.99"

DB_CONTAINER="zt-portal-db"
APP_CONTAINER="zt-portal"

if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo "ERROR: container '${DB_CONTAINER}' is not running"
    exit 1
fi

if [ "$1" = "--delete" ]; then
    echo "Deleting demo user '${DEMO_USER}'..."
    docker exec "${DB_CONTAINER}" mysql -uztportal -p"${DB_PASS}" zt_portal -e \
        "DELETE FROM users WHERE username='${DEMO_USER}';"
    echo "Done."
    exit 0
fi

echo "Creating demo user '${DEMO_USER}' (password: ${DEMO_PASS}, no 2FA)..."

HASH=$(docker exec "${APP_CONTAINER}" php -r \
    "echo password_hash('${DEMO_PASS}', PASSWORD_BCRYPT);")

docker exec "${DB_CONTAINER}" mysql -uztportal -p"${DB_PASS}" zt_portal -e "
    INSERT INTO users (username, password_hash, display_name, wireguard_ip, role, enabled, totp_enabled)
    VALUES ('${DEMO_USER}', '${HASH}', '${DEMO_DISPLAY}', '${DEMO_IP}', 'user', 1, 0)
    ON DUPLICATE KEY UPDATE
        password_hash = VALUES(password_hash),
        totp_enabled = 0,
        totp_secret = NULL,
        enabled = 1;
"

echo ""
echo "============================================"
echo "  Demo user ready"
echo "============================================"
echo "  Username: ${DEMO_USER}"
echo "  Password: ${DEMO_PASS}"
echo "  WG IP:    ${DEMO_IP}"
echo "  2FA:      DISABLED"
echo ""
echo "  ⚠  For demos only — delete before going live:"
echo "     ./scripts/create-demo-user.sh --delete"
echo "============================================"
