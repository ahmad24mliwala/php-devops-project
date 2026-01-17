# PickleHub - PHP E-commerce Starter

## Setup
1. Copy files into your PHP webroot (or point your virtual host to `public/`).
2. Create a MySQL database and user.
3. Run `php helpers/create_admin_hash.php` to generate a bcrypt hash for the admin password and replace `{ADMIN_PASSWORD_HASH}` in `setup.sql`.
4. Import `setup.sql` into MySQL (e.g., `mysql -u root -p picklehub < setup.sql`).
5. Configure database credentials in `includes/db.php`.
6. Run `composer require dompdf/dompdf` in project root to enable PDF invoice generation.
7. Ensure `uploads/` and `invoices/` are writable by the webserver (chmod 775 or 777 for local testing).

## Notes
- This project is a starter kit covering the requested features (secure PDO, CSRF tokens, admin area, invoice generation support).
- For full production readiness: add input sanitization, HTTPS, email sending, background jobs, rate limiting, and stronger validation.

