# Shebamiles Employee Management System

A PHP and MySQL web application for employee records, departments, attendance, leave, payroll, performance reviews and role-based administration.

## What is included

- Admin, manager and employee roles
- Prepared database statements, password hashing, CSRF protections and session controls
- First administrator provisioned from a one-shot CLI script; no shared default login

## Run locally

```sh
Install PHP 8.2+ with MySQL/MariaDB and a web server. Create the database using the schema/setup files under `database/`, configure the required `DB_*` environment variables, then serve the repository with Apache/PHP. See `docs/` for schema and local setup details.
```

## Verify

With PHP 8.4 available: `php tests/auth_test.php` and `php tests/admin_provisioning_test.php`. PHP files can be linted with `php -l`.

## Repository map

`index.php` and `login.php` are the entry points. `hr/`, `attendance/`, `admin/` and `communication/` contain feature pages. `includes/` contains shared configuration/security code. `database/`, `scripts/`, `tests/` and `docs/` contain setup and maintenance materials.

## Evidence and limitations

All PHP files lint successfully; authentication, CSRF/permission and first-admin configuration checks pass. A live MySQL integration test has not been run in this workspace.

Production use requires an explicitly configured database with least-privilege credentials, schema verification, HTTPS, backups and operational review. Keep secrets outside the repository. Create the first admin only through `php scripts/create_admin.php` with `EMS_ADMIN_USERNAME`, `EMS_ADMIN_EMAIL` and a 16+ character `EMS_ADMIN_PASSWORD`; never expose these values in a web request.

## License

No repository license file is included. Unless the project owner adds a license, do not assume the code, data or model artifacts may be redistributed or reused. Verify third-party data and model terms separately.
