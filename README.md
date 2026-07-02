# Color Polytech Live

Production-ready repository structure for the Color Polytech public website, Admin CMS, and Office ERP.

## Domains

- `colorpolytech.com` → `public/`
- `admin.colorpolytech.com` → `admin/`
- `office.colorpolytech.com` → `office/`

## Stack

- PHP 8.1+
- MySQL/MariaDB
- PDO
- Bootstrap 5
- Vanilla JavaScript/AJAX
- Namecheap shared hosting compatible

## Database Plan

- Website/Admin database: `colojmbr_cp`
- Office/ERP database: `colojmbr_office`

SQL files will be stored in `database/`.

## Security Notes

- Never commit real database credentials, API keys, SMS API credentials, or Gmail credentials.
- Use `.env.example` as a template and create real `.env` files only on the live server.
- Block admin and office private paths from search engines.
- Never overwrite live `config/database.php` during deployment.
- Never delete uploaded customer files from `uploads/` or `media/`.

## Office Live Login Checklist

Use this checklist when localhost login works but `office.colorpolytech.com` rejects the same user.

1. Confirm the office subdomain points to the correct folder: `office.colorpolytech.com/`.
2. Confirm the office app uses the correct database: `colojmbr_office`.
3. Confirm the live `users` table exists in `colojmbr_office` and contains the expected user row.
4. Confirm username/email values have no leading or trailing spaces.
5. Confirm the credential column can store modern hashes. Use `VARCHAR(255)` or `TEXT`.
6. Confirm old records are handled safely if the older system used legacy formats.
7. Confirm PHP sessions can write on Namecheap hosting.
8. Confirm HTTPS is active and session cookies use secure/httponly settings.
9. Confirm deployment does not replace the live-only database config file.
10. Do not auto-run SQL on the live server; add schema changes as reviewed migration files.

## Initial Structure

```text
public/     Public website files
admin/      Admin CMS files
office/     Office ERP files
database/   SQL schema/import files
docs/       Deployment and maintenance notes
```
