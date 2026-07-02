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

- Never commit real database passwords, API keys, SMS API credentials, or Gmail credentials.
- Use `.env.example` as a template and create real `.env` files only on the live server.
- Block admin and office private paths from search engines.

## Initial Structure

```text
public/     Public website files
admin/      Admin CMS files
office/     Office ERP files
database/   SQL schema/import files
docs/       Deployment and maintenance notes
```
