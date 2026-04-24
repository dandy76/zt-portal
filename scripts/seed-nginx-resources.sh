#!/bin/bash
#
# Seeds the 3 nginx-backed resources (Budibase, Gitea, Mattermost) into the portal DB.
# Safe to re-run: uses ON DUPLICATE KEY UPDATE, won't create duplicates.
#
# Usage:
#   ./scripts/seed-nginx-resources.sh              # create
#   ./scripts/seed-nginx-resources.sh --delete     # remove them
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR/.."

# Load .env or fall back to container env
if [ -f .env ]; then
    set -a; source .env; set +a
else
    DB_PASS=$(docker exec zt-portal env | grep '^DB_PASS=' | cut -d= -f2)
fi

DB_CONTAINER="zt-portal-db"
DST_HOST="10.0.10.130"

if ! docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
    echo "ERROR: container '${DB_CONTAINER}' is not running"
    exit 1
fi

SQL_MYSQL="docker exec ${DB_CONTAINER} mysql -uztportal -p${DB_PASS} zt_portal -e"

if [ "$1" = "--delete" ]; then
    echo "Removing nginx resources (Budibase, Gitea, Mattermost)..."
    $SQL_MYSQL "
        DELETE FROM user_permissions WHERE resource_id IN
            (SELECT id FROM resources WHERE address_list_name IN ('auth_budibase','auth_gitea','auth_mattermost'));
        DELETE FROM resources WHERE address_list_name IN ('auth_budibase','auth_gitea','auth_mattermost');
    " 2>&1 | grep -v Warning
    echo "Done."
    exit 0
fi

echo "Seeding nginx-backed resources..."

$SQL_MYSQL "
    INSERT INTO resources (name, description, address_list_name, dst_address, dst_port, protocol, timeout_minutes, enabled) VALUES
        ('Budibase',   'Internal low-code platform (budibase.lan)',     'auth_budibase',   '${DST_HOST}', '9443', 'tcp',  60, 1),
        ('Gitea',      'Git server (gitea.lan)',                        'auth_gitea',      '${DST_HOST}', '9444', 'tcp', 120, 1),
        ('Mattermost', 'Team chat (mattermost.lan)',                    'auth_mattermost', '${DST_HOST}', '9445', 'tcp', 480, 1)
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        dst_address = VALUES(dst_address),
        dst_port = VALUES(dst_port),
        protocol = VALUES(protocol),
        timeout_minutes = VALUES(timeout_minutes),
        enabled = 1;
" 2>&1 | grep -v Warning

echo ""
echo "============================================"
echo "  Resources seeded:"
echo "============================================"
$SQL_MYSQL "
    SELECT id, name, address_list_name, CONCAT(dst_address,':',dst_port) AS target, CONCAT(timeout_minutes,'min') AS timeout
    FROM resources WHERE address_list_name IN ('auth_budibase','auth_gitea','auth_mattermost')
    ORDER BY id;
" 2>&1 | grep -v Warning

echo ""
echo "Next steps:"
echo "  1. Grant permissions: Admin → Permissions → Grant Permission"
echo "     (or use SQL: INSERT INTO user_permissions(user_id, resource_id) VALUES (...);)"
echo "  2. Reconfigure nginx to listen on ports 9443/9444/9445 per vhost"
echo "  3. Add MikroTik firewall rules:"
echo "     /ip firewall filter add chain=forward src-address-list=auth_budibase \\"
echo "         dst-address=${DST_HOST} dst-port=9443 protocol=tcp action=accept"
echo "     # (same for auth_gitea:9444 and auth_mattermost:9445)"
