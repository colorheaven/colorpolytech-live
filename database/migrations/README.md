# Database Migrations

Add reviewed SQL migration files in this folder when the database structure must change.

Rules:
- Use one migration file per change.
- Name files with a date prefix, for example `2026_07_02_add_customer_indexes.sql`.
- Review each file before importing.
- Do not run SQL automatically during deployment.
- Take a full database backup before importing any migration on the live server.
