Files modified or created for dual-environment fix (localhost ↔ Profreehost)

Created:
- config.php — Centralized config loader, session_start() guarded by PHP_SESSION_NONE, defines SITE_URL, MEDIA_PATH, UPLOAD_URL.

Created earlier (now removed):
- env_loader.php (root) — DELETED and merged into config/env-loader.php
- public/.htaccess — DELETED and merged into /.htaccess

Edited (major changes):
- includes/middleware/auth.php — now requires config.php, session initialization via config, redirects use SITE_URL
- includes/core/Router.php — requires config.php, session via config, redirects use SITE_URL
- includes/core/BaseController.php — requires config.php; redirect() builds absolute URL using SITE_URL
- api/payments/initialize.php — requires config.php; callback_url uses SITE_URL
- api/payments/get-order.php — requires config.php; ticket download URL uses SITE_URL
- api/clients/verify-identity.php — requires config.php; mock URL uses SITE_URL
- api/media/get-default-templates.php — uses MEDIA_PATH and UPLOAD_URL; fallback to legacy uploads path
- api/tickets/validate-ticket.php — SQL identifiers wrapped in backticks
- api/tickets/cancel-ticket.php — SQL identifiers wrapped in backticks
- includes/classes/Payment.php — fixed SQL identifier quoting and path includes (minor)
- config/session-config.php — removed direct session_start(); centralized session start in config.php
- api/otps/generate-otp.php — replaced session_start() with config include
- api/clients/get-banks.php — replaced session_start() with config include
- api/auth/check-session.php — replaced session_start() with config include
- api/auth/google-handler.php — replaced session_start() with config include
- api/auth/register.php — session init via config.php
- api/auth/login.php — session init via config.php

Other:
- config/env-loader.php — existing file retained (merged behaviour from root env_loader.php already present)
- /.htaccess — merged security directives to protect .env and env_loader.php
- FILES_MODIFIED.md — this documentation file

Notes:
- Removed duplicate files: /env_loader.php and /public/.htaccess (deleted in git commit)
- Manual steps: set uploads/media or media folder to 755 on Profreehost via FTP; confirm APP_URL and OAuth redirect URIs in .env for production domain.

Co-authored-by: Copilot <223556219+Copilot@users.noreply.github.com>