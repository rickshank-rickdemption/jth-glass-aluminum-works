# Railway Deploy Checklist

## 1) Service
- Create a new Railway project from your GitHub repo.
- Root directory: `public`
- Start command is already in `Procfile`:
  - `php -S 0.0.0.0:${PORT:-8080} -t .`

## 2) Environment Variables
- Copy values from `.env.example`.
- Minimum required:
  - `FIREBASE_URL`
  - `ADMIN_USER`
  - `ADMIN_PASS_HASH`
  - `SMTP_USER`
  - `SMTP_PASS`
  - `RECAPTCHA_SECRET_KEY`

## 3) Persistent Storage (recommended)
- Add a Railway volume and mount to `/data`.
- Set:
  - `ADMIN_AUTH_STORE=/data/admin_auth.json`
  - `INQUIRY_UPLOAD_DIR=/data/inquiry_uploads`
  - `WS_EVENT_LOG=/data/ws-events.log`

## 4) Domain
- After first successful deploy, connect custom domain:
  - `jthglass-aluminumworks.xyz`
- Keep HTTPS enabled (`ADMIN_ENFORCE_HTTPS=true`).

## 5) Optional Realtime Worker
- If you need websocket realtime independent from polling:
  - Add another service/worker command: `php backend/ws-server.php`
  - Set same env vars (especially `FIREBASE_URL`, `WS_TOKEN_SECRET`).

