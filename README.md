# Shebamiles Employee Management System

A PHP and MySQL web application for employee records, departments, attendance, leave, payroll, performance reviews, and role-based administration. The application includes admin, manager, and employee workflows.

`index.php` and `login.php` are the entry points. Feature pages are grouped under `hr/`, `attendance/`, `admin/`, and `communication/`; shared database and security code is under `includes/`. `database/`, `scripts/`, `tests/`, and `docs/` contain schema, setup, and maintenance materials.

## Local setup

Install PHP 8.2 or later with MySQL/MariaDB and a web server. Create the database using the schema/setup files under `database/`, configure the `DB_*` environment variables, then serve the repository through Apache/PHP. Provision the first administrator with `php scripts/create_admin.php` and the required `EMS_ADMIN_*` environment variables.

The repository includes authentication, CSRF, permission, and first-admin checks under `tests/`.
