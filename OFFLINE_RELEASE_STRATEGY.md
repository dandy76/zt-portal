# Offline Release Strategy — ghcr + tar backup

> Πώς να έχεις **independence** από το GitHub χωρίς να στήσεις registry.
> Κρατάς ghcr.io ως primary + αυτόματα tar backups στο cryptoai + offline installer για τον πελάτη.

---

## Αρχιτεκτονική

```
     DEV (cryptoai)                     PROD (client QNAP)
     ─────────────                      ───────────────────
     git push ──► GitHub ──► ghcr.io ◄── docker pull  (normal path)
                                │
                                ▼
                         docker save ◄─────── offline-install.sh
                                │             (disaster recovery)
                                ▼
                      /data/backups/images/
                      zt-portal-YYYYMMDD.tar.gz
                      (last 10 kept)
```

---

## 1. Auto-backup script στο cryptoai

### `/usr/local/bin/zt-portal-backup.sh`

```bash
#!/bin/bash
#
# Pulls the latest zt-portal image from ghcr.io and saves it as a versioned
# tar.gz for offline disaster recovery. Keeps the last 10 backups.
#
# Install:
#   sudo cp zt-portal-backup.sh /usr/local/bin/
#   sudo chmod +x /usr/local/bin/zt-portal-backup.sh
#
# Cron (weekly Sunday 03:00):
#   0 3 * * 0 /usr/local/bin/zt-portal-backup.sh >> /var/log/zt-portal-backup.log 2>&1

set -e

IMAGE="ghcr.io/dandy76/zt-portal:latest"
BACKUP_DIR="/data/backups/zt-portal-images"
KEEP=10

mkdir -p "$BACKUP_DIR"

echo "[$(date '+%F %T')] Pulling $IMAGE..."
docker pull "$IMAGE"

# Figure out the real version tag from the image labels
VERSION=$(docker inspect --format '{{ index .Config.Labels "org.opencontainers.image.version" }}' "$IMAGE" 2>/dev/null || echo "unknown")
DIGEST=$(docker inspect --format '{{ index .RepoDigests 0 }}' "$IMAGE" | sed 's/.*@sha256:\(.\{8\}\).*/\1/')
STAMP=$(date +%Y%m%d-%H%M)
OUT="$BACKUP_DIR/zt-portal-${STAMP}-${VERSION}-${DIGEST}.tar.gz"

echo "[$(date '+%F %T')] Saving to $OUT..."
docker save "$IMAGE" | gzip > "$OUT"

SIZE=$(du -h "$OUT" | cut -f1)
echo "[$(date '+%F %T')] Backup complete — $SIZE"

# Rotate: keep last $KEEP
COUNT=$(ls -1 "$BACKUP_DIR"/zt-portal-*.tar.gz 2>/dev/null | wc -l)
if [ "$COUNT" -gt "$KEEP" ]; then
    OLD=$(ls -t "$BACKUP_DIR"/zt-portal-*.tar.gz | tail -n +$((KEEP+1)))
    echo "[$(date '+%F %T')] Rotating: removing $(echo "$OLD" | wc -l) old backup(s)"
    echo "$OLD" | xargs rm -v
fi

# Summary
echo "[$(date '+%F %T')] Current backups:"
ls -lh "$BACKUP_DIR"/zt-portal-*.tar.gz | awk '{print "  ", $9, "-", $5}'
```

### Εγκατάσταση

```bash
sudo install -m 0755 zt-portal-backup.sh /usr/local/bin/
sudo mkdir -p /data/backups/zt-portal-images
sudo chown $USER:$USER /data/backups/zt-portal-images

# Πρώτο τρέξιμο για να επιβεβαιωθεί ότι δουλεύει:
/usr/local/bin/zt-portal-backup.sh
```

### Cron scheduling

```bash
# Κάθε Κυριακή 03:00
crontab -e
# Πρόσθεσε:
0 3 * * 0 /usr/local/bin/zt-portal-backup.sh >> /var/log/zt-portal-backup.log 2>&1
```

Ή αν προτιμάς systemd timer (αντί για cron) — πιο καθαρό logging:

```ini
# /etc/systemd/system/zt-portal-backup.service
[Unit]
Description=Backup zt-portal Docker image

[Service]
Type=oneshot
ExecStart=/usr/local/bin/zt-portal-backup.sh
```

```ini
# /etc/systemd/system/zt-portal-backup.timer
[Unit]
Description=Weekly zt-portal image backup

[Timer]
OnCalendar=Sun 03:00
Persistent=true

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl enable --now zt-portal-backup.timer
sudo systemctl list-timers | grep zt-portal
```

---

## 2. Offline installer για τον πελάτη

Script που **ζει στο QNAP** και ξέρει να κάνει install/update χωρίς internet — τραβάει το τελευταίο tar από cryptoai (ή οπουδήποτε το βάλεις) και το φορτώνει.

### `scripts/offline-install.sh`

```bash
#!/bin/bash
#
# Offline installer for zt-portal.
# Use when ghcr.io is unreachable (no internet, account deleted, etc).
#
# Fetches the latest tar backup from a source (SSH, local path, HTTP) and
# loads it into Docker on the target machine (typically the QNAP).
#
# Usage:
#   ./offline-install.sh                           # interactive, default source
#   ./offline-install.sh --source ssh://dandy@cryptoai:/data/backups/zt-portal-images
#   ./offline-install.sh --source /mnt/usb/zt-portal-images
#   ./offline-install.sh --source https://dimitrelias.gr/zt-portal-images
#   ./offline-install.sh --file /path/to/specific.tar.gz
#   ./offline-install.sh --list                    # list available backups
#

set -e

SOURCE="${SOURCE:-ssh://dandy@cryptoai:/data/backups/zt-portal-images}"
FILE=""
ACTION="install"

while [ $# -gt 0 ]; do
    case "$1" in
        --source)  SOURCE="$2"; shift 2 ;;
        --file)    FILE="$2"; shift 2 ;;
        --list)    ACTION="list"; shift ;;
        --help|-h) grep '^#' "$0" | sed 's/^# *//'; exit 0 ;;
        *) echo "Unknown option: $1" >&2; exit 1 ;;
    esac
done

list_backups() {
    case "$SOURCE" in
        ssh://*)
            HOST_PATH="${SOURCE#ssh://}"
            HOST="${HOST_PATH%%:*}"
            PATH_REMOTE="${HOST_PATH#*:}"
            ssh "$HOST" "ls -lht $PATH_REMOTE/zt-portal-*.tar.gz 2>/dev/null"
            ;;
        https://*|http://*)
            curl -s "$SOURCE/" | grep -oE 'zt-portal-[^"]+\.tar\.gz' | sort -u
            ;;
        /*)
            ls -lht "$SOURCE"/zt-portal-*.tar.gz 2>/dev/null
            ;;
        *)
            echo "ERROR: Unsupported source: $SOURCE" >&2
            exit 1
            ;;
    esac
}

fetch_latest() {
    local dest="/tmp/zt-portal-offline.tar.gz"
    case "$SOURCE" in
        ssh://*)
            HOST_PATH="${SOURCE#ssh://}"
            HOST="${HOST_PATH%%:*}"
            PATH_REMOTE="${HOST_PATH#*:}"
            LATEST=$(ssh "$HOST" "ls -t $PATH_REMOTE/zt-portal-*.tar.gz | head -1")
            echo "Fetching latest: $LATEST"
            scp "$HOST:$LATEST" "$dest"
            ;;
        https://*|http://*)
            LATEST=$(curl -s "$SOURCE/" | grep -oE 'zt-portal-[^"]+\.tar\.gz' | sort -u | tail -1)
            echo "Fetching: $SOURCE/$LATEST"
            curl -fSL "$SOURCE/$LATEST" -o "$dest"
            ;;
        /*)
            LATEST=$(ls -t "$SOURCE"/zt-portal-*.tar.gz | head -1)
            echo "Copying: $LATEST"
            cp "$LATEST" "$dest"
            ;;
    esac
    echo "$dest"
}

if [ "$ACTION" = "list" ]; then
    echo "Available backups at $SOURCE:"
    list_backups
    exit 0
fi

# Install flow
if [ -z "$FILE" ]; then
    FILE=$(fetch_latest)
fi

echo ""
echo "============================================"
echo "  Loading image from: $FILE"
echo "============================================"
gunzip -c "$FILE" | docker load

LOADED=$(docker load -i <(gunzip -c "$FILE") 2>&1 | grep -oE 'ghcr.io/[^:]+:[^ ]+' | head -1 || echo "ghcr.io/dandy76/zt-portal:latest")

echo ""
echo "Loaded: $LOADED"
echo ""
echo "Next steps:"
echo "  1. Ensure docker-compose.yml has:"
echo "     image: $LOADED"
echo "  2. Restart services:"
echo "     docker compose down && docker compose up -d"
echo ""
echo "Offline install complete."
```

### Εγκατάσταση στο QNAP

```bash
# SSH στο QNAP
cd /share/Container/zt-portal/scripts/
# (copy the script)
chmod +x offline-install.sh

# Τεστ ότι βλέπει το cryptoai (SSH key-based auth χρειάζεται)
ssh-copy-id dandy@cryptoai   # μία φορά
./offline-install.sh --list
```

### Χρήση σε emergency

```bash
# Σενάριο: έχασες ghcr, QNAP δεν μπορεί να κάνει pull
./offline-install.sh
# → Τραβάει latest από cryptoai, κάνει docker load, έτοιμο για restart
docker compose down && docker compose up -d
```

---

## 3. Αυτοματοποίηση σε "νέα deploy" workflow

Αν θες κάθε φορά που κάνεις release να γίνεται αυτόματα ο κύκλος:

### `scripts/release.sh` (στο dev)

```bash
#!/bin/bash
# One-command release: bump version, tag, push, wait for build, backup
set -e

VERSION="${1:-patch}"  # major | minor | patch | X.Y.Z

# 1. Bump version (χρησιμοποίησε το δικό σου versioning tool ή package.json/composer.json)
# π.χ.:
# npm version "$VERSION"

# 2. Commit & tag
git add .
git commit -m "Release $(git describe --tags --abbrev=0)"
git tag -a "v$VERSION" -m "Release v$VERSION"
git push origin main --tags

# 3. Περίμενε το GitHub Actions να τελειώσει
echo "Waiting for GitHub Actions build..."
gh run watch

# 4. Auto-backup
/usr/local/bin/zt-portal-backup.sh

echo "Release v$VERSION complete."
echo "Backup saved to /data/backups/zt-portal-images/"
```

---

## 4. Testing disaster recovery (κάνε το μία φορά, μόνο για σιγουριά)

Σε ένα ελεύθερο μηχάνημα (ή σε docker-in-docker), προσομοίωσε το "ghcr χάθηκε":

```bash
# 1. Καθάρισε τοπικά images
docker rmi ghcr.io/dandy76/zt-portal:latest 2>/dev/null || true

# 2. Βεβαιώσου ότι το docker δεν μπορεί να πιάσει το ghcr (edit /etc/hosts ή block)
echo "127.0.0.1 ghcr.io" | sudo tee -a /etc/hosts

# 3. Τρέξε offline installer
./offline-install.sh --source /data/backups/zt-portal-images

# 4. Επιβεβαίωση
docker images | grep zt-portal

# 5. Cleanup
sudo sed -i '/ghcr.io/d' /etc/hosts
```

Αν αυτό περνάει → έχεις **έτοιμη disaster recovery** διαδικασία.

---

## 5. Backup storage considerations

### Πόσος χώρος
- Ένα image ~ 500 MB – 1 GB gzipped
- 10 backups ≈ 5-10 GB
- Acceptable για σχεδόν οποιοδήποτε server

### Redundancy του backup
Το `/data/backups/zt-portal-images/` **είναι και αυτό single point of failure**. Αν ο δίσκος του cryptoai χαλάσει, χάνεις όλα τα tar. Πρόσθεσε:

- **rsync off-site** (π.χ. σε άλλο NAS / USB ή S3-compatible bucket):
  ```bash
  # Στο cron μετά το backup
  rsync -av /data/backups/zt-portal-images/ \
      backup@other-nas:/volume1/backups/zt-portal/
  ```
- Ή: **B2/S3 bucket** μέσω `rclone` — πολύ φθηνό, απλό:
  ```bash
  rclone sync /data/backups/zt-portal-images/ b2:mycompany-backups/zt-portal/
  ```

### Integrity checks
Κάθε τόσο επιβεβαίωσε ότι τα tar δουλεύουν:

```bash
# Test ότι το gzip είναι έγκυρο
for f in /data/backups/zt-portal-images/*.tar.gz; do
    gzip -t "$f" && echo "OK: $f" || echo "CORRUPT: $f"
done
```

---

## Σύνοψη: τι κάνεις σήμερα

1. **Δημιούργησε** `zt-portal-backup.sh` (βλ. §1) και βάλ' το σε cron/systemd timer
2. **Τρέξε** τουλάχιστον μία φορά χειροκίνητα για πρώτο backup
3. **Αντίγραψε** το `offline-install.sh` στο QNAP (βλ. §2)
4. **Κάνε setup SSH key** από QNAP → cryptoai για να μπορεί να τραβάει tar
5. **Δοκίμασε disaster recovery** (βλ. §4) σε test env

Μετά μπορείς να κοιμηθείς ήσυχος: και το GitHub να εξαφανιστεί, έχεις χρόνο εβδομάδων να μεταναστεύσεις σε άλλη λύση.
