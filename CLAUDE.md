# ZT Portal — Claude Code Specs & Implementation Guide

> Auto-loaded από Claude Code. Για future sessions: εδώ είναι όλα όσα χρειάζεσαι για να συνεχίσεις.

---

## 1. Τι είναι

Self-hosted **Zero Trust Network Access portal** για MikroTik RouterOS 7.x. Οι χρήστες κάνουν login με κωδικό + TOTP 2FA, και το portal προσθέτει δυναμικά το IP τους σε **address-lists** που gate-άρουν firewall rules. Όταν λήξει το timeout ή κάνουν logout, η πρόσβαση αφαιρείται αυτόματα.

**Stack**: PHP 8.2 + Apache + MySQL 8.0 + Vanilla JS (no frameworks). Deployment μέσω Docker Compose.

---

## 2. Architecture

```
WireGuard client (10.1.40.x)
    ↓
MikroTik RB5009 — default DROP all, exception: portal port always open
    ↓
ZT Portal (QNAP:7443) — zt-portal container + zt-portal-db
    ↓ (on Activate)
MikroTik REST API → address-list add/remove με timeout
    ↓
Firewall rules που matchάρουν το address-list → resource unlocked
```

### Components
- `zt-portal` container (PHP 8.2 + Apache + self-signed cert)
- `zt-portal-db` container (MySQL 8.0, persistent volume)
- Shared network `zt-net`
- Cron inside container → `scripts/cleanup.php` every minute (expired sessions)

### Data model (key tables)
- `users` — username, password_hash, totp_secret, wireguard_ip (`0.0.0.0` = bypass IP check), role
- `resources` — name, address_list_name, dst_address, dst_port, protocol, timeout_minutes
- `user_permissions` — many-to-many (user ↔ resource)
- `sessions` — active resource activations (user_id, resource_id, expires_at, last_keepalive)
- `audit_log` — every action (login, 2fa, activate, revoke, admin changes)
- `settings` — key/value store (portal_title, totp_issuer, κλπ.)

---

## 3. Current state (2026-04)

### Υλοποιημένα
- ✅ Login + TOTP 2FA (RobThree/TwoFactorAuth lib)
- ✅ Per-user fixed WG IP validation (`wireguard_ip`)
- ✅ Resource activate/deactivate (calls MikroTik REST)
- ✅ JS keepalive (ανανεώνει firewall entry κάθε λεπτό όσο το tab είναι ανοιχτό)
- ✅ Admin CRUD: users, resources, permissions
- ✅ Active sessions view + force-revoke
- ✅ Audit log με filters
- ✅ Docker multi-arch build (amd64 + arm64) via GitHub Actions → `ghcr.io/dandy76/zt-portal`
- ✅ Schema bootstrap + migration runner στο entrypoint
- ✅ First-run admin auto-generation
- ✅ USER_GUIDE.md + USER_GUIDE.docx με embedded screenshots
- ✅ `TOTP_ISSUER` env για branded authenticator entries (dev vs client)
- ✅ Resources `domain_name` field → auto `window.open(https://<host>:<port>)` μετά activate

### Εκκρεμή / Known gaps
- ❌ **Settings page** — view exists (`views/admin/settings.php`) αλλά **δεν υπάρχει route** στο `Router.php`. Endpoint `/admin/settings` επιστρέφει 404.
- ❌ **Test Connection για MikroTik API** — το button στη settings view δεν έχει backend endpoint
- ❌ **Auto-create firewall rules** — το portal διαχειρίζεται μόνο address-list entries, όχι filter rules (αποφασίστηκε να μείνει manual, βλ. planning decision)
- ❌ **Password reset flow για τον τελικό user** — μόνο admin reset μέσω panel
- ❌ **Drift detection** — manual change σε address-list στο MT δεν συγχρονίζεται με DB state

### Port configuration
- Portal εξωτερικά: **7443** (άλλαξε από 8443 τον Απρ 2026)
- Container εσωτερικά: 443
- Ενημερωμένα και: docker-compose.yml, .rsc files, docs, `.claude/settings.local.json`

---

## 4. Deployment

### Local dev (cryptoai)
```bash
cd /data/WireGuard
docker compose up -d --build
# Portal at https://localhost:7443
```

### Production (QNAP)
Δύο τρόποι:

**Α. Pull από ghcr (μετά τη μετάβαση σε registry-based)**
```bash
docker compose -f docker-compose.qnap.yml pull
docker compose -f docker-compose.qnap.yml up -d
```

**Β. Build local + save/load**
```bash
# Στο dev host
docker save zt-portal:vX | gzip > zt-portal-vX.tar.gz
scp zt-portal-vX.tar.gz admin@qnap:/share/Container/zt-portal/

# Στο QNAP
gunzip -c zt-portal-vX.tar.gz | docker load
docker compose up -d
```

### Απαιτούμενα `.env` keys
```
DB_PASS=<strong-random>
DB_ROOT_PASS=<strong-random>
MT_API_URL=https://<mt-ip>/rest
MT_API_USER=<api-user>
MT_API_PASS=<api-pass>
APP_SECRET=<openssl rand -hex 32>
```

### MikroTik prereq
- `/ip service enable www-ssl` με valid certificate (βλ. Troubleshooting)
- API user με policies: `api,rest-api,read,write`
- Firewall rules για portal access + one-per-resource address-list rules (manual)

---

## 5. Development workflow

### Προσθήκη νέου feature
1. Δοκίμασε στο cryptoai (`docker compose up -d --build`)
2. Τεστ στο browser (https://localhost:7443)
3. Commit + push σε main → GitHub Actions κάνει build + push σε ghcr
4. Στο QNAP: `docker compose pull && docker compose up -d`

### Προσθήκη νέου resource (π.χ. για nginx-backed service)
1. `./scripts/seed-nginx-resources.sh` (template για budibase/gitea/mattermost — tweakable)
2. Ή χειροκίνητα από Admin → Resources UI
3. Admin → Permissions για να δώσεις σε users
4. **Manual**: πρόσθεσε το firewall filter rule στο MikroTik (βλ. Known gap #3)

### Προσθήκη route
- `src/Router.php` — routes map
- `views/<area>/<name>.php` — view template
- `src/<ModelClass>.php` — business logic
- Audit κάθε write action μέσω `AuditLog::log(...)`

### Conventions
- PHP 8.2+, `declare(strict_types=1)` σε κάθε file
- Database queries: **μόνο** μέσω `Database::fetchOne|fetchAll|insert|update|delete` (PDO prepared)
- CSRF token σε κάθε POST (`Auth::csrfToken()`)
- Session fingerprint check (`src/Auth.php`) — IP binding προαιρετικό ανά user
- Sensitive values (secrets, tokens) **ΠΟΤΕ** στο audit log — log action type + IDs μόνο
- Frontend: vanilla JS, no bundlers. CSS custom properties για theming (light/dark).

### Testing σε browser automation
Το project έχει `glance` MCP server configured. Για re-capture screenshots:
```bash
# Stage data πρώτα
./scripts/create-demo-user.sh
./scripts/seed-nginx-resources.sh
# Navigate + screenshot μέσω Claude Code με glance MCP
```

---

## 6. Troubleshooting recipes

### "Cannot reach MikroTik API"
```bash
# 1. TCP
docker exec zt-portal bash -c 'timeout 3 bash -c "</dev/tcp/$MT_IP/443" && echo OPEN'
# 2. HTTPS
docker exec zt-portal curl -ksv --max-time 5 https://$MT_IP/rest/system/identity -u $MT_USER:$MT_PASS
```
Πίνακας συμπτωμάτων → διάγνωση:
| Σύμπτωμα | Αιτία | Λύση |
|---|---|---|
| `connection refused` | www-ssl disabled | `/ip service enable www-ssl` |
| `TLS handshake failure` | No cert στο www-ssl | Create CA + server cert, assign |
| `401 Unauthorized` | Wrong password | Reset στο MT |
| `403 Forbidden` | Missing API user policies | Add `rest-api,write` |

### Admin locked out (forgot password / 2FA)
```bash
# Reset password
NEW=$(docker exec zt-portal php -r "echo password_hash('newpass', PASSWORD_BCRYPT);")
docker exec zt-portal-db mysql -uztportal -p$DB_PASS zt_portal -e \
  "UPDATE users SET password_hash='$NEW', totp_enabled=0, totp_secret=NULL WHERE username='admin';"
```

### Image απώλεια (ghcr account deleted)
- Running container: δεν πέφτει
- Restart: δουλεύει (local cache)
- Fresh install: χρειάζεται backup tar (βλ. §4.Β)
- **Πρόληψη**: κράτα `.tar.gz` backup ανά version

### Περιοχή fix μετά από port change
Αλλαγή portal port εκτός από docker-compose.yml απαιτεί επίσης:
- MikroTik filter rule με `dst-port=<νέο port>`
- MikroTik NAT masquerade exception `dst-port=!<νέο port>`
- QNAP port forwarding (αν εξωτερικά)
- Client bookmarks

---

## 7. File map

```
zt-portal/
├── Dockerfile                    # PHP 8.2 + Apache + ext + cron + entrypoint
├── docker-compose.yml            # Local/dev (με build:)
├── docker-compose.qnap.yml       # Production (με image:)
├── .github/workflows/
│   └── docker-publish.yml        # Build + push στο ghcr.io σε κάθε main push
├── docker/
│   ├── entrypoint.sh             # Wait MySQL + first-run admin + migrations
│   ├── apache-ssl.conf
│   └── apache-redirect.conf
├── sql/
│   ├── schema.sql                # Αρχικό schema (runs μόνο σε fresh DB)
│   └── migrations/               # Incremental schema changes
├── src/                          # Business logic
│   ├── Auth.php                  # Login, 2FA, session mgmt
│   ├── Database.php              # PDO wrapper
│   ├── MikroTikAPI.php           # REST calls
│   ├── AccessManager.php         # Resource activate/revoke
│   ├── TOTP.php                  # RobThree wrapper
│   ├── AuditLog.php
│   ├── Router.php                # URL → controller
│   ├── Settings.php
│   └── Config.php
├── public/
│   ├── index.php                 # Entry (routes to Router::dispatch)
│   └── assets/                   # CSS, JS, fonts
├── views/
│   ├── login.php, setup-2fa.php, verify-2fa.php, portal.php, 404.php
│   ├── layout.php, partials/
│   └── admin/                    # dashboard, users, resources, permissions, sessions, audit, settings
├── scripts/
│   ├── cleanup.php               # Cron: expire old sessions, refresh MT entries
│   ├── create-demo-user.sh       # Demo operator setup (για screenshots)
│   ├── seed-nginx-resources.sh   # 3 resources: budibase/gitea/mattermost
│   └── build-docx.py             # USER_GUIDE.md → USER_GUIDE.docx με images
├── docs/
│   └── screenshots/              # 10 PNGs από automated capture
├── USER_GUIDE.md, USER_GUIDE.docx
├── PROJECT_SPECS.md              # Architecture detail
├── QNAP_SETUP.md                 # QNAP-specific setup
├── README.md
└── CLAUDE.md                     # Αυτό το αρχείο
```

---

## 8. Security model

- **Authentication**: bcrypt password + TOTP (6-digit, 30sec, ±1 window drift)
- **Authorization**: role (`user`/`admin`) + per-user `user_permissions` για resources
- **Session**: PHP session με fingerprint (IP + UA binding optional)
- **CSRF**: token σε κάθε state-changing request
- **Rate limiting**: 5 failed logins → block IP 15min (`src/Auth.php::isRateLimited`)
- **Source IP binding**: `wireguard_ip` field — αν δεν είναι `0.0.0.0`, απαιτείται match με `REMOTE_ADDR`
- **Secrets**: στο `.env`, **ποτέ** σε git, **ποτέ** στο audit log

### Threat model
- ✅ Brute force password (rate limit)
- ✅ Brute force 2FA (window drift ±1 = μόνο ~3 κωδικοί valid σε 90sec)
- ✅ Session hijack (fingerprint + IP bind optional)
- ✅ CSRF
- ⚠️ Compromised admin → full access σε όλα τα resources (no MFA-for-critical actions)
- ⚠️ Compromised QNAP → portal container έχει write access στο MT firewall
- ❌ Network-layer attacks (υποθέτουμε WireGuard = trusted perimeter)

---

## 9. Για μελλοντικούς Claude agents

Αν σου ζητηθεί feature που δεν υπάρχει:
1. **Διάβασε** τον υπάρχοντα `Router.php` + αντίστοιχο view + business class για να καταλάβεις το pattern
2. **Μην** εισάγεις framework ή dependency χωρίς discussion
3. **Migration** για DB αλλαγές → `sql/migrations/NNN-description.sql` (o runner στο entrypoint εκτελεί σειριακά)
4. **Audit log** για κάθε νέο action type
5. **CSRF token** σε κάθε POST route
6. **Permission check** — αν είναι admin-only, `Auth::requireAdmin()` στην αρχή του handler
7. **Frontend** — κράτα vanilla JS, χωρίς bundler. Μην εισάγεις React/Vue.

### Αν σου ζητηθεί "κάνε zero-trust enforcement per-service"
Υπάρχει ήδη plan: nginx listen σε ξεχωριστά ports ανά vhost (9443/9444/9445), resources στο portal ανά service, MT firewall rules ανά address-list. Βλ. conversation history ή `scripts/seed-nginx-resources.sh`.

### Αν σου ζητηθεί "κάνε το app να δημιουργεί firewall rules αυτόματα"
Συζητήθηκε, **απορρίφθηκε** από τον user (rare use, manual WinBox αρκεί). Αν ζητηθεί ξανά με διαφορετικό context, βλ. `.claude/plans/*.md` για notes.

---

## 10. Contact

- Maintainer: Dimitrelias IT Services (Volos, Greece)
- Repo: https://github.com/dandy76/zt-portal
- Image: ghcr.io/dandy76/zt-portal:latest (+ versioned tags)
