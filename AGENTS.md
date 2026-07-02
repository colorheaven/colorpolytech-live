# Project Instructions

PHP/MySQL project for Namecheap shared hosting.

## Hosting
- Server: Namecheap shared hosting with cPanel
- PHP 8.1+
- MySQL/MariaDB
- No Laravel
- No Node.js required for production
- No Docker for production
- Composer-only packages require shared-hosting confirmation first

## Domains
- colorpolytech.com -> public_html/
- admin.colorpolytech.com -> admin.colorpolytech.com/
- office.colorpolytech.com -> office.colorpolytech.com/

## Database Names
- Website and Admin CMS: colojmbr_cp
- Office ERP: colojmbr_office

## Development Rules
- Keep changes small and safe.
- Keep PHP shared-hosting compatible.
- Keep existing ERP modules and data safe.
- Keep live uploaded files safe.
- Keep live-only config files on the server.
- Do not store private DB values, service keys, SMS credentials, mail credentials, or private tokens in Git.
- Do not overwrite live config/database.php.
- Put database structure changes in database/migrations/ as reviewed SQL files.
- SQL is never run automatically during deployment.
