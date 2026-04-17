# ZT Portal - Zero Trust Network Access Portal

[![Build Status](https://github.com/dandy76/zt-portal/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/dandy76/zt-portal/actions)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Self-hosted Zero Trust Network Access (ZTNA) portal for MikroTik routers. Users authenticate with TOTP 2FA through a web portal, and their IP is dynamically added to MikroTik address-lists that gate firewall access to internal resources — all via the MikroTik REST API.

## Features

- **TOTP 2FA authentication** (compatible with Google Authenticator, Authy, FreeOTP)
- **MikroTik REST API integration** — dynamic address-list management
- **Time-limited sessions** — auto-revoke firewall access on logout or timeout
- **JS keepalive** — renews firewall entry while the user is active
- **Admin dashboard** — users, permissions, resources, active sessions, audit log
- **Audit logging** — every authentication and firewall change is recorded
- **Runs anywhere Docker runs** — tested on Ubuntu, QNAP Container Station, Synology

## Architecture

```
┌──────────┐    HTTPS    ┌─────────────┐   REST API   ┌──────────────┐
│  User    │───────────► │  ZT Portal  │─────────────►│   MikroTik   │
│ Browser  │             │  (PHP+MySQL)│              │    Router    │
└──────────┘             └─────────────┘              └──────────────┘
                                │                            │
                                ▼                            ▼
                         TOTP 2FA +                   Address-list +
                         Session mgmt                 Firewall rules
```
    
## Quick Start

### Prerequisites

- Docker + Docker Compose
- MikroTik router with REST API enabled (RouterOS 7.x)
- Dedicated API user on the MikroTik with write access to address-lists

### Deployment

```bash
# Clone
git clone https://github.com/dandy76/zt-portal.git
cd zt-portal

# Configure
cp .env.example .env
nano .env    # Set DB passwords, MikroTik API creds, APP_SECRET

# Generate APP_SECRET
openssl rand -hex 32

# Run
docker compose up -d

# Access at https://<host>:8443
```

### First login

On first run, the admin user is created from `.env` credentials. Log in and configure:
1. Users (who can authenticate)
2. Resources (which MikroTik address-lists they control)
3. Permissions (which user gets which resource)

## QNAP Deployment

See [QNAP_SETUP.md](QNAP_SETUP.md) for step-by-step QNAP Container Station setup, including multi-architecture image pull (amd64 + arm64).

## Using pre-built images (recommended for clients)

Instead of building locally, pull from GitHub Container Registry:

```yaml
# docker-compose.yml
services:
  app:
    image: ghcr.io/dandy76/zt-portal:latest
    # ... (rest of config)
```

Available tags:
- `latest` — current `main` branch
- `v1.x.x` — semantic versioned releases

## MikroTik Setup

Minimum required: a dedicated API user with `rest-api,write` policy, and one or more address-lists referenced by firewall rules.

Detailed setup and sample `.rsc` scripts are in the [wiki](https://github.com/dandy76/zt-portal/wiki).

## Security Notes

- Always run behind HTTPS (the container ships a self-signed cert; replace with proper one in production)
- `.env` contains secrets — never commit it
- Rotate `APP_SECRET` periodically
- Restrict MikroTik API access to the portal's IP only
- Enable MikroTik user-manager logging to correlate with portal audit log

## Development

```bash
# Local dev with hot-reload
docker compose -f docker-compose.yml -f docker-compose.dev.yml up

# Run migrations manually
docker exec zt-portal php scripts/migrate.php

# Tail logs
docker compose logs -f app
```

## Contributing

Pull requests welcome. For major changes, open an issue first to discuss.

## License

MIT — see [LICENSE](LICENSE).

## Author

Built by [Dimitrelias IT Services](https://dimitrelias.gr) — Volos, Greece.  
