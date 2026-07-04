# Office ERP Safe Development Plan

This plan is for step-by-step improvement of the Office ERP without breaking live data, login, database configuration, or the current workflow.

## Current hosting and stack rules

- PHP 8+, MySQL/MariaDB, PDO prepared statements, Bootstrap 5, JavaScript/AJAX.
- Must work on Namecheap shared hosting and cPanel.
- No Laravel, Node.js, Composer-only dependency, Docker, or server-level install required for production.
- Never commit real database, SMS, email, or API passwords.
- Never auto-run SQL on the live server.
- Add database changes only as migration files under `database/migrations/`.
- Keep uploaded files and live configuration files safe.

## Safety rules before each update

1. Backup the live database `colojmbr_office` from phpMyAdmin.
2. Backup the office subdomain folder from cPanel File Manager.
3. Deploy only small tested changes.
4. Do not delete old tables or old data.
5. Do not hard delete financial or stock records. Use soft delete fields when possible.
6. Approved voucher edit/delete must require special permission, reason, audit log, and SMS alert if enabled.
7. Ledger, cash/bank, and stock should update only after approval unless a clearly defined reserved-stock workflow exists.

## Development phases

### Phase 1: Core safety foundation

- Standardize soft delete columns for master and transaction tables.
- Standardize audit log structure.
- Add user-wise custom permission override table.
- Add safe voucher edit/delete reason fields.
- Add SMS event/template/log structure.
- Add notification read/unread behavior.
- Add login/session security hardening and default admin password reminder.

### Phase 2: Permission and workflow control

- Module-wise and action-wise permissions: view, add, edit, delete, bulk_delete, approve, reject, print, export, sms_send, report_view.
- Role defaults for Super Admin, Admin, Accounts, Manager, Marketer, Delivery Man.
- User-specific permission override beyond role permissions.
- Marketer data scope: own leads, customers, orders, collections, and reports by default.
- Delivery Man data scope: assigned deliveries only.
- Admin/Super Admin see all data.

### Phase 3: Master data modules

- Customer Management with profile, ledger, ageing, SMS history, code auto-generate, bulk delete, soft delete.
- Supplier Management with ledger, purchase/payment history, SMS history, code auto-generate, bulk delete, soft delete.
- Product Management with category, grade, origin, base unit, alternative unit, bag size, stock ledger, reorder level, bulk delete, soft delete.

### Phase 4: CRM and sales workflow

- Lead management with source/status/follow-up/reminder/assignment/history.
- Convert lead to customer.
- Sales order workflow: draft, pending approval, approved, rejected, forwarded to delivery, delivered, invoiced, cancelled.
- Delivery workflow: assigned, on the way, partial delivered, delivered, failed, final confirmation.
- Invoice workflow: auto-generate after delivery confirmation, approval, returns, ledger update.

### Phase 5: Accounts and ledger workflow

- Collection voucher with cash/bank/cheque/mobile banking/adjustment logic.
- Purchase and supplier payment workflow.
- Cash and bank book.
- Customer and supplier ledger with running balance.
- Customer and supplier ageing report.
- All approved vouchers must reconcile with ledger.

### Phase 6: SMS and notifications

- Configurable SMS gateway without hardcoded credentials.
- Template variables and voucher-wise SMS on/off.
- SMS log for sent/failed/pending and API response.
- Voucher edit/delete alert SMS.
- Internal notifications for approval, delivery, credit limit, cheque maturity, low stock, and follow-up reminders.

### Phase 7: Dashboard and reports

- Role-wise dashboard for Super Admin, Admin, Accounts, Marketer, Delivery Man.
- Reports: sales, collection, due, ageing, purchase, payment, inventory, stock ledger, cash book, bank book, delivery, lead, activity, deleted/edited voucher, SMS sent.
- All reports must have date range, filters, suggestions, print, PDF, CSV/Excel export, summary cards, and detail tables.

### Phase 8: Import/export and backup

- CSV/Excel import preview with validation for customers, suppliers, products, opening balances, stock opening, leads.
- CSV/PDF/print export for reports.
- Backup/export action must be logged.

## Professional printable formats

Create/maintain A4 professional printable formats for:

- Sales Order
- Delivery Challan
- Sales Invoice
- Money Receipt / Collection Voucher
- Payment Voucher
- Purchase Order
- Customer Ledger
- Supplier Ledger
- Customer Statement
- Supplier Statement

Required print elements: logo, company name, address, phone, email, website, voucher no, date, party details, product table, amount in words, prepared by, checked by, approved by, authorized signature, terms, print button, PDF download.

## Testing checklist for every release

- Login for all roles.
- Permission enforcement on pages and actions.
- Add/edit/delete/bulk delete for changed module.
- Approval workflow.
- Ledger update and running balance.
- Cash/bank update.
- Stock update and stock ledger.
- Voucher print.
- SMS template and SMS log.
- Voucher edit/delete SMS.
- Reports with date filter.
- Global search suggestion.
- Mobile responsive dashboard.
- Data backup/export.
- No existing data loss.

## Deployment checklist

1. Commit changes to GitHub.
2. In cPanel Git Version Control, click `Update from Remote`.
3. Click `Deploy HEAD Commit`.
4. Import migration manually only after database backup.
5. Test login and dashboard first.
6. Test changed module only.
7. Check PHP error log if anything fails.
8. Roll back by restoring previous file/database backup if needed.
