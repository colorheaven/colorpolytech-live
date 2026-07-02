# Deployment Notes

## Target Hosting

Namecheap shared hosting / cPanel.

## Domain Mapping

| Domain | Upload/Deploy Folder |
|---|---|
| colorpolytech.com | `public/` |
| admin.colorpolytech.com | `admin/` |
| office.colorpolytech.com | `office/` |

## Recommended cPanel Document Roots

- Main domain `colorpolytech.com`: `public_html`
- Subdomain `admin.colorpolytech.com`: `admin.colorpolytech.com`
- Subdomain `office.colorpolytech.com`: `office.colorpolytech.com`

## Safe Deployment Process

1. Backup current live files.
2. Export both databases from phpMyAdmin.
3. Upload only the correct folder to the correct document root.
4. Create `.env` on the server from `.env.example`.
5. Add real database username/password only on server.
6. Import SQL files through phpMyAdmin.
7. Test login and dashboard before replacing production data.
8. Confirm admin and office subdomains return `noindex` robots rules.

## GitHub to Hosting Options

### Option 1: Manual ZIP upload

1. Download repository ZIP from GitHub.
2. Extract locally.
3. Upload `public/` files to `public_html`.
4. Upload `admin/` files to `admin.colorpolytech.com`.
5. Upload `office/` files to `office.colorpolytech.com`.

### Option 2: SSH Git pull

Use this only if hosting SSH is enabled.

```bash
cd ~/repositories
git clone https://github.com/colorheaven/colorpolytech-live.git
```

Then copy/sync each folder to the correct document root.

## Important

Do not put one full repository directly inside `public_html` unless the hosting document root points only to the correct public folder. Private files like `.env`, database exports, and docs must not be publicly accessible.
