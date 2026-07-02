# Project Instructions

PHP/MySQL project for Namecheap shared hosting.

Domains:
- colorpolytech.com -> public_html/
- admin.colorpolytech.com -> admin.colorpolytech.com/
- office.colorpolytech.com -> office.colorpolytech.com/

Production stack:
- PHP 8.1+
- MySQL/MariaDB
- cPanel shared hosting
- No Laravel, Node production dependency, or Docker

Database names:
- Website and Admin: colojmbr_cp
- Office ERP: colojmbr_office

Development rules:
- Keep changes small and safe.
- Keep ERP modules and data safe.
- Keep live uploaded files safe.
- Keep live-only config files on the server.
- Put database structure changes in database/migrations/ as reviewed SQL files.
- SQL is never run automatically during deployment.
