# QNAP Container Station - Zero Trust Portal Setup

## Προαπαιτούμενα
- QNAP NAS στο `192.168.1.200`
- Container Station εγκατεστημένο
- SSH πρόσβαση στο QNAP

## Μέθοδος 1: Docker Compose μέσω SSH

```bash
# SSH στο QNAP
ssh admin@192.168.1.200

# Δημιούργησε φάκελο για το project
mkdir -p /share/Container/zt-portal
cd /share/Container/zt-portal

# Αντέγραψε τα αρχεία (από τον υπολογιστή σου)
# scp -r /data/WireGuard/* admin@192.168.1.200:/share/Container/zt-portal/

# Δημιούργησε .env
cp .env.example .env
vi .env   # βάλε τα πραγματικά passwords

# Build & Start
docker-compose up -d --build

# Δες τα logs (σημαντικό: θα εμφανίσει τον κωδικό admin στο πρώτο boot)
docker-compose logs -f app
```

## Μέθοδος 2: Container Station UI (Manual)

### Container 1: MySQL Database

| Setting | Value |
|---|---|
| **Image** | `mysql:8.0` |
| **Container name** | `zt-portal-db` |
| **Restart policy** | Unless stopped |

**Environment Variables:**
| Variable | Value |
|---|---|
| MYSQL_ROOT_PASSWORD | `<δικό σου root pass>` |
| MYSQL_DATABASE | `zt_portal` |
| MYSQL_USER | `ztportal` |
| MYSQL_PASSWORD | `<δικό σου db pass>` |

**Volumes:**
| Host Path | Container Path |
|---|---|
| `/share/Container/zt-portal/db_data` | `/var/lib/mysql` |
| `/share/Container/zt-portal/sql/schema.sql` | `/docker-entrypoint-initdb.d/01-schema.sql` |

**Network:**
- Port mapping: `3307 → 3306` (ή μόνο internal)
- Βάλε σε custom network `zt-net`

---

### Container 2: PHP App (πρέπει πρώτα build)

Πρέπει να κάνεις build το image πρώτα:

```bash
# SSH στο QNAP
cd /share/Container/zt-portal
docker build -t zt-portal-app .
```

| Setting | Value |
|---|---|
| **Image** | `zt-portal-app` (local build) |
| **Container name** | `zt-portal` |
| **Restart policy** | Unless stopped |

**Environment Variables:**
| Variable | Value |
|---|---|
| DB_HOST | `zt-portal-db` |
| DB_NAME | `zt_portal` |
| DB_USER | `ztportal` |
| DB_PASS | `<ίδιο με MYSQL_PASSWORD>` |
| MT_API_URL | `https://10.0.40.1/rest` |
| MT_API_USER | `api_portal` |
| MT_API_PASS | `<mikrotik pass>` |
| APP_SECRET | `<openssl rand -hex 32>` |

**Ports:**
| Host Port | Container Port | Protocol |
|---|---|---|
| 7443 | 443 | TCP |

**Network:**
- Βάλε στο ίδιο network `zt-net` με τη MySQL

---

## Μέθοδος 3: Import docker-compose.yml στο Container Station

1. Άνοιξε Container Station → **Applications** → **Create**
2. Επίλεξε **Docker Compose / YAML**
3. Paste το περιεχόμενο του `docker-compose.yml`
4. Πρόσθεσε τα environment variables στο **Environment** tab
5. Πάτα **Create**

**ΣΗΜΕΙΩΣΗ**: Αυτή η μέθοδος μπορεί να μην υποστηρίζει `build:` directive.
Πρέπει πρώτα να κάνεις build το image μέσω SSH.

---

## Μετά την εγκατάσταση

1. **Δες τα logs** για τον admin κωδικό:
   ```bash
   docker logs zt-portal 2>&1 | grep -A 10 "FIRST RUN"
   ```

2. **Πρόσβαση**:
   `https://192.168.1.200:7443`

3. **Αλλαγή admin settings**:
   - Κάνε login με τον κωδικό από τα logs
   - Setup 2FA
   - Άλλαξε το `wireguard_ip` του admin user στο σωστό IP

4. **MikroTik firewall rule** (πρέπει να υπάρχει ήδη):
   ```routeros
   /ip firewall filter
   add chain=forward dst-address=192.168.1.200 dst-port=7443 protocol=tcp action=accept comment="ZT Portal always accessible"
   ```

---

## Troubleshooting

```bash
# Logs
docker logs zt-portal
docker logs zt-portal-db

# MySQL shell
docker exec -it zt-portal-db mysql -u ztportal -p zt_portal

# PHP shell
docker exec -it zt-portal bash

# Restart
docker-compose restart

# Rebuild after code changes
docker-compose up -d --build
```

## Backup

```bash
# Database backup
docker exec zt-portal-db mysqldump -u root -p zt_portal > backup_$(date +%Y%m%d).sql

# Restore
docker exec -i zt-portal-db mysql -u root -p zt_portal < backup_20260326.sql
```
