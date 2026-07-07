# Color Polytech Live

PHP/MySQL project for Namecheap shared hosting with cPanel.

## Stack

- PHP 8.1+
- MySQL/MariaDB
- PDO
- Bootstrap 5
- Vanilla JavaScript/AJAX
- Shared-hosting compatible
- No Laravel
- No Node.js production dependency
- No Docker production dependency

## Domain Mapping

| Domain | cPanel folder | Repo folder |
|---|---|---|
| colorpolytech.com | `public_html/` | `public/` |
| admin.colorpolytech.com | `admin.colorpolytech.com/` | `admin/` |
| office.colorpolytech.com | `office.colorpolytech.com/` | `office/` |

## Database Mapping

| App | Database |
|---|---|
| Public website | `colojmbr_cp` |
| Admin CMS | `colojmbr_cp` |
| Office ERP | `colojmbr_office` |

## Config Files

Example files are included:

- `public/config/database.example.php`
- `admin/config/database.example.php`
- `office/config/database.example.php`

On the live server, copy the matching example file to `config/database.php` and fill the live cPanel values there only. The live config file is ignored by Git.

Office ERP also supports `OFFICE_DB_*` values from `.env`, server environment variables, or PHP constants as a fallback. For Namecheap/cPanel, `office/config/database.php` is still the recommended and easiest setup.

## Deployment Safety

- `.cpanel.yml` uses `rsync -a` only.
- It does not use `--delete`.
- Live-only config files are excluded.
- Uploaded files and media folders are excluded.
- Logs and cache folders are excluded.
- SQL files are not imported automatically.
- Database changes must be reviewed migration files under `database/migrations/`.
- Keep existing ERP modules and data safe.

## ERP Safe Development Roadmap

The master safe update plan is documented here:

- `docs/ERP_SAFE_UPDATE_PLAN.md`

The plan covers:

- Core safety foundation
- Soft delete
- Audit log
- User-wise custom permission overrides
- SMS templates/logs
- Voucher edit/delete safety
- Customer, supplier, product, CRM, order, delivery, invoice, collection, purchase, payment, ledger, ageing, cash/bank, reports, dashboard, import/export, and notification phases

A core migration foundation has been added here:

- `database/migrations/2026_07_05_core_security_audit_permissions.sql`
- `database/migrations/2026_07_07_database_connection_compatibility.sql`

Important: this migration is **not auto-run**. Review it, backup `colojmbr_office`, then import manually only if needed.

## Direct GitHub to Namecheap Auto Upload

This repo includes a GitHub Actions workflow:

- `.github/workflows/deploy-namecheap.yml`

When code is pushed to the `main` branch, GitHub Actions can upload these folders by FTP:

- `public/` to `/public_html/`
- `admin/` to `/admin.colorpolytech.com/`
- `office/` to `/office.colorpolytech.com/`

The workflow does not clean/delete the live server before upload. It also excludes live config, env files, uploads, media, logs and cache.

### Required GitHub Secrets

Add these in GitHub repo settings:

- `CPANEL_FTP_SERVER`
- `CPANEL_FTP_USERNAME`
- `CPANEL_FTP_PASSWORD`

Go to GitHub repository -> Settings -> Secrets and variables -> Actions -> New repository secret.

Use the FTP details from Namecheap cPanel. Do not put FTP or cPanel login values inside code files.

### Manual auto-upload run

After adding the secrets:

1. Open GitHub repository.
2. Go to Actions.
3. Select `Deploy to Namecheap cPanel`.
4. Click `Run workflow`.

## Office ERP Active UI Scope

The Office ERP UI is currently focused on these active modules only:

- Dashboard
- Customers
- Suppliers
- Products
- Orders
- Delivery
- Sales
- Invoice
- Collection
- Inventory
- Ledger
- Ageing Report
- Reports
- User Management
- Approval

Other old or unused module files/data must remain untouched unless a future written approval confirms removal.

## Beginner Deployment Steps

### 1. Backup first

Before deployment, backup these folders from cPanel File Manager:

- `public_html/`
- `admin.colorpolytech.com/`
- `office.colorpolytech.com/`

Then export these databases from phpMyAdmin:

- `colojmbr_cp`
- `colojmbr_office`

### 2. Pull from GitHub

In cPanel, open **Git Version Control**, connect or open this repository, then pull the latest `main` branch:

`https://github.com/colorheaven/colorpolytech-live.git`

### 3. Deploy HEAD Commit

Click **Deploy HEAD Commit** in cPanel Git Version Control. cPanel will run `.cpanel.yml` and copy:

- `public/` to `~/public_html/`
- `admin/` to `~/admin.colorpolytech.com/`
- `office/` to `~/office.colorpolytech.com/`

### 4. Create live config once

Inside each live app folder, copy `config/database.example.php` to `config/database.php` and fill the real cPanel database values on the server only.

### 5. SQL migrations

SQL is never run automatically. If a migration exists in `database/migrations/`, review it, backup the database, then import manually through phpMyAdmin only if needed.

## Testing Checklist

After deployment:

1. Open `https://colorpolytech.com`.
2. Open `https://admin.colorpolytech.com`.
3. Open `https://office.colorpolytech.com`.
4. Confirm all pages load without PHP fatal errors.
5. Confirm admin and office `robots.txt` block indexing.
6. Confirm live `config/database.php` files still exist.
7. Confirm uploaded files and media are still present.
8. Test public forms if available.
9. Test Admin CMS login if available.
10. Test Office ERP login if available.
11. Test ERP order, delivery, invoice, collection, approval, ledger, customers, suppliers, products, inventory, ageing report, and reports pages if available.
12. Check cPanel PHP error logs if any page is blank.

## Rollback Steps

If anything breaks:

1. Do not import SQL.
2. Deploy the previous working commit from cPanel Git Version Control if available.
3. Restore the backed-up folders if needed.
4. Restore database export only if a database change was imported.
5. Check cPanel PHP error logs.
6. Test the three domains again.

## Office Live Login Checklist

When localhost login works but live office login fails:

1. Confirm `office.colorpolytech.com` points to the correct folder.
2. Confirm office uses `colojmbr_office`.
3. Confirm the live `users` table exists.
4. Confirm username/email values have no leading or trailing spaces.
5. Confirm the hash column is `VARCHAR(255)` or `TEXT`.
6. Confirm old user records are compatible with the current login code.
7. Confirm PHP sessions can write on hosting.
8. Confirm HTTPS is active.
9. Confirm deployment did not replace live config.
