# MikroTik Firewall Cleanup — Ανάλυση "vise versa" rules

> **Source**: `qubber_1704.rsc` (2026-04-17 export)
> **Router**: RB5009UPr+S+ @ qubber site

---

## TL;DR

Έχεις **4 ζεύγη** "vise versa" rules. Ο router έχει `accept established,related` **μέσα στο forward chain** (γραμμή 255), οπότε **όλα τα reply packets περνάνε ήδη** μέσα από αυτόν τον κανόνα. Τα "vise" rules είναι **ενεργά** μόνο αν ο destination host θέλει να **initiate** δικιά του σύνδεση πίσω.

**Πρόταση**: Disable (όχι delete) τα 4 "vise" rules, παρακολούθηση για 7-14 ημέρες, delete ό,τι μείνει στο 0 bytes.

---

## Ο θεμελιώδης κανόνας που αλλάζει τα πάντα

**Γραμμή 255** του rsc:
```routeros
add action=accept chain=forward comment="Allow established/related" \
    connection-state=established,related
```

Αυτό σημαίνει: όταν το A initiate-άρει σύνδεση στο B (rule X ταιριάζει), **ολόκληρη η return traffic B→A** ταιριάζει αυτόματα με τον παραπάνω κανόνα, ανεξαρτήτως πόρτας/πρωτοκόλλου.

**Οπότε**: "vise" rules χρειάζονται **ΜΟΝΟ** αν ο B κάνει δικές του, αυθόρμητες εξερχόμενες συνδέσεις προς A.

---

## Ανάλυση ανά ζευγάρι

### Ζεύγος 1 — legacy ↔ PCs (γραμμές 316-319)

```routeros
# Forward:
add comment="Allow legacy to PCs" dst-address=10.0.30.0/24 src-address=192.168.1.0/24 protocol=tcp action=accept

# Vise:
add comment="vise Allow legacy to PCs" dst-address=192.168.1.0/24 src-address=10.0.30.0/24 protocol=tcp action=accept
```

**Ποιος initiate-άρει;**
- Legacy servers (192.168.1.x) → PCs (10.0.30.x): wake-on-LAN, push messaging, monitoring agents — **ίσως**
- PCs → Legacy: file shares, printing, app access — **σίγουρα**

**Υποψία**: Η legacy εφαρμογή είναι **server** και οι PCs είναι **clients**. Αν έτσι, ΟΧΙ, δεν χρειάζεται vise.

**Ρίσκο αν αφαιρεθεί**: Αν η legacy app κάνει callbacks στα PCs (π.χ. print spooler notifications, AD push), θα σπάσουν.

➜ **Disable first, monitor**

---

### Ζεύγος 2 — PCs ↔ QnapFS (VLAN10) (γραμμές 320-323)

```routeros
# Forward:
add comment="vlan 30> vlan 10 QnapSW" dst-address=10.0.10.0/24 src-address=10.0.30.0/24 protocol=tcp action=accept

# Vise:
add comment="VISE vlan 30> vlan 10 QnapSW" dst-address=10.0.30.0/24 src-address=10.0.10.0/24 protocol=tcp action=accept
```

**Ποιος initiate-άρει;**
- PCs (30) → QnapFS (10): browse shares, git, mattermost, budibase — **σίγουρα**
- QnapFS (10) → PCs (30): QNAP remote wake, ping check, SMB client notifications — **σπάνια**

**Υποψία**: QnapFS είναι NAS (response-only). ΟΧΙ, δεν χρειάζεται vise.

**Εξαίρεση**: Αν τρέχεις Qfinder-like discovery, QNAP Auto-Update push, Active Insight → μπορεί να χρειαστεί.

➜ **Disable first, monitor**

---

### Ζεύγος 3 — PCs ↔ QnapContainers (VLAN20) (γραμμές 324-327)

```routeros
# Forward:
add comment="vlan 30 > vlan20 QnapFS" dst-address=10.0.20.0/24 src-address=10.0.30.0/24 protocol=tcp action=accept

# Vise:
add comment="vise vlan 30 > vlan20 QnapFS" dst-address=10.0.30.0/24 src-address=10.0.20.0/24 protocol=tcp action=accept
```

**Ποιος initiate-άρει;**
- PCs (30) → Containers (20): RDP gateway, service access — **σίγουρα**
- Containers (20) → PCs (30): agent callbacks (π.χ. Mattermost webhooks, Budibase scheduled jobs) — **ίσως**

**Υποψία**: Τα containers είναι web services. Για HTTP/HTTPS απλό, ΟΧΙ. Αν υπάρχει **webhook back to user** ή **scheduled task** που push-άρει, ίσως.

➜ **Disable first, monitor — μπορεί να χρειαστεί** για webhooks

---

### Ζεύγος 4 — PCs ↔ Switch Admin (VLAN1/10.0.200.x) (γραμμές 328-332)

```routeros
# Forward:
add comment="vlan30 > vlan 1 for Switch Admin" dst-address=10.0.200.0/24 src-address=10.0.30.0/24 protocol=tcp action=accept

# Vise:
add comment="VISE VERSA vlan30 > vlan 1 for Switch Admin" dst-address=10.0.30.0/24 src-address=10.0.200.0/24 protocol=tcp action=accept
```

**Ποιος initiate-άρει;**
- PCs (30) → Switches (10.0.200): SSH/HTTPS/WebGUI management — **σίγουρα**
- Switches → PCs: SNMP traps (UDP — έξω από αυτό), syslog — **σπάνια**

**Υποψία**: Οι switches είναι passive. ΟΧΙ, δεν χρειάζεται vise.

➜ **Σχεδόν σίγουρα αφαιρείται**

---

## Συγκεντρωτικός πίνακας

| # | Pair | Πιθανότητα να χρειάζεται η vise | Προτεινόμενη ενέργεια |
|---|---|---|---|
| 1 | legacy ↔ PCs | Μέτρια (legacy apps συχνά κάνουν callbacks) | Disable → monitor |
| 2 | PCs ↔ QnapFS | Χαμηλή | Disable → delete |
| 3 | PCs ↔ Containers | Μέτρια (αν υπάρχουν webhooks) | Disable → monitor προσεκτικά |
| 4 | PCs ↔ Switches | **Πολύ χαμηλή** | Disable → delete |

---

## Εκτέλεση (στο WinBox ή terminal)

### Βήμα 1 — Disable (όχι delete) και καταγραφή ID

Στο **WinBox → IP → Firewall → Filter Rules**, βρες κάθε "vise" rule, right-click → **Disable**.

Ή από terminal:

```routeros
/ip firewall filter
disable [find comment="vise Allow legacy to PCs"]
disable [find comment="VISE vlan 30> vlan 10 QnapSW"]
disable [find comment="vise vlan 30 > vlan20 QnapFS"]
disable [find comment="VISE VERSA vlan30 > vlan 1 for Switch Admin"]
```

### Βήμα 2 — Reset counters και monitor

```routeros
/ip firewall filter reset-counters-all
```

Άφησε το για **7-14 ημέρες** υπό κανονική χρήση.

### Βήμα 3 — Έλεγχος

Στο WinBox, για κάθε rule δες **Bytes / Packets** counters:

| Bytes | Σημασία |
|---|---|
| **0 B / 0 packets** | Δεν χρησιμοποιείται → safe to delete |
| `X B / Y packets` | **Κάτι** την χτυπάει — κράτα το rule ενεργό |

### Βήμα 4 — Παρατήρηση συμπτωμάτων

Χρήστες να αναφέρουν:
- "Δεν μπορώ να συνδεθώ σε..."
- "Το X δεν λειτουργεί από χθες"
- Διακοπή σε scheduled tasks, backups, monitoring

Αν κάτι σπάσει → re-enable το rule αμέσως.

### Βήμα 5 — Delete (μετά από επιτυχές monitoring)

```routeros
/ip firewall filter remove [find comment="vise ..." disabled=yes]
```

---

## Άλλα rules που θα πρότεινα να κοιτάξεις

### Επίσης ύποπτα (όχι "vise" αλλά παρόμοια λογική)

**Γραμμή 314-315**:
```routeros
add action=accept chain=forward comment="legacy > " dst-address=192.168.1.0/24 protocol=tcp src-address=192.168.1.0/24
```

Intra-VLAN traffic (src=dst=192.168.1.0/24). Στο forward chain αυτό δεν πρέπει κανονικά να εμφανίζεται (inter-VLAN = bridge L2, δεν περνάει από forward). Ή είναι κατάλοιπο ή υπάρχει ιδιαίτερη τοπολογία. **Έλεγξε τα counters** — αν είναι 0, αφαίρεσε.

### Disabled rules που έχουν ξεχαστεί

Σειρά 304-313: έχεις 4 rules με `disabled=yes` (Users TCP to QnapFS, DMZ UDP, SMB Users, κλπ). Αν **έχουν μείνει disabled για πολύ καιρό**, αφαίρεσέ τα — μειώνει confusion.

### Διπλή `accept established,related`

- Γραμμή 256: στο **forward** chain ✓
- Γραμμή 349: στο **input** chain ✓

Καλό, είναι **δύο διαφορετικά chains**, δεν είναι duplicate. Απλά επιβεβαιώνω.

### Possible issue: θέση του `established,related` στο forward

Στο παρόν rsc, το **γρ. 256** είναι το **πρώτο** accept rule στο forward chain — αυτό είναι **σωστό** (performance + στο χέρι του conntracker).

---

## Backup πριν κάνεις οτιδήποτε

```routeros
/export file=before_cleanup_2026-04-18
```

Ή από WinBox: **Files → Create Backup** + έξτρα **Files → Export** για text diff.

---

## Παρατήρηση για WG rules (γρ. 288-297)

Αυτό το κομμάτι είναι **zero-trust logic** και δεν χρειάζεται vise:
```routeros
add chain=forward comment="ZT: Portal always open" ... accept
add chain=forward comment="ZT: QNAP Container Station" ... src-address-list=auth_NAS_access accept
add chain=forward comment="ZT: Block all WG traffic (catch-all)" src-address=10.1.40.0/24 drop
```

Έχεις σωστή σειρά: specifics πρώτα, μετά catch-all drop. **Μην το αγγίξεις**.

---

## Σύνοψη επόμενων βημάτων

1. Backup config
2. Disable 4 vise rules (κράτα disabled, όχι delete)
3. Reset counters
4. Monitor 7-14 days
5. Ό,τι έχει 0 bytes → delete. Ό,τι έχει bytes → enable ξανά και άφησέ το.
6. Καθάρισε τα παλιά `disabled=yes` rules

Εκτιμώμενο time: 15 λεπτά για disable/backup + monitoring + 10 λεπτά για cleanup.

Μείωση firewall rules: από ~40 σε ~32-34 (ανάλογα τι χρειάζεται τελικά).
