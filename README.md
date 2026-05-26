# Goodness Payroll Management System

A web-based payroll management system that automates salary computation, enforces data integrity through database transactions and concurrency control, maintains a complete audit trail of all user actions, and provides analytical reporting via a star schema data warehouse.

---

## System Requirements

| Requirement | Details |
|---|---|
| **PHP Version** | PHP 8.0 or higher |
| **Database** | MySQL 8.0+ or MariaDB 10.4+ |
| **Web Server** | Apache 2.4+ (included in XAMPP) |
| **Development Environment** | XAMPP (recommended) |
| **Browser** | Any modern browser (Chrome, Firefox, Edge) |
| **Tools** | phpMyAdmin (included in XAMPP), Visual Studio Code (optional) |

> **Note:** This system does **not** use Laravel or Composer. It is built with plain PHP 8 and PDO — no framework dependencies.

---

## Installation Steps

### 1. Install XAMPP

Download and install XAMPP from [https://www.apachefriends.org](https://www.apachefriends.org). Ensure both **Apache** and **MySQL** services are started from the XAMPP Control Panel.

### 2. Clone or Copy the Project

Place the project folder inside the XAMPP `htdocs` directory:

```
C:\xampp\htdocs\goodness-payroll\
```

The folder structure should look like:

```
goodness-payroll/
├── config/
│   ├── database.php
│   └── auth.php
├── includes/
│   ├── header.php
│   └── log_activity.php
├── modules/
│   ├── employees.php
│   ├── process_payroll.php
│   ├── data_warehouse.php
│   └── ...
├── api/
├── index.php
└── payroll_system.sql
```

### 3. Configure the Database Connection

Open `config/database.php` and verify or update the connection credentials:

```php
private $host = 'localhost';
private $dbname = 'payroll_system';
private $username = 'root';      // your MySQL username
private $password = '';           // your MySQL password (blank by default in XAMPP)
```

---

## Database Setup

### Option A — Via phpMyAdmin (Recommended)

1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **New** in the left sidebar and create a database named `payroll_system`
3. Select the `payroll_system` database
4. Click the **Import** tab
5. Click **Choose File** and select `payroll_system.sql` from the project root
6. Click **Go** to execute

### Option B — Via MySQL Command Line

```bash
mysql -u root -p -e "CREATE DATABASE payroll_system;"
mysql -u root -p payroll_system < payroll_system.sql
```

The SQL file creates all 10 tables, 3 stored procedures (`sp_process_payroll`, `sp_run_etl`, `sp_add_employee`), 7 SQL views, and inserts sample data for 10 employees across 3 payroll periods.

---

## Running the Application

1. Make sure **Apache** and **MySQL** are running in the XAMPP Control Panel
2. Open your browser and navigate to:

```
http://localhost/goodness-payroll/
```

3. The login page will load. Use the test accounts below to access the system.

---

## Test Accounts

| Role | Username | Password | Access Level |
|---|---|---|---|
| **Admin** | `admin` | `admin` | Full access — all modules including Payroll Processing, Data Warehouse, User Management, and Audit Logs |
| **Staff** | `hr_staff` | `staff123` | Limited access — Dashboard, Employee Management (view/add), and Payroll History (view only) |

> Passwords are stored using **bcrypt** hashing (`password_hash()` / `password_verify()`). The plain-text passwords above only work through the login form against the hashed values seeded by the SQL file.

---

## Module Overview

| Module | Path | Description |
|---|---|---|
| Dashboard | `?page=dashboard` | Real-time summary: active employees, total payroll disbursed, recent payroll date |
| Employee Management | `?page=employees` | Full CRUD with optimistic concurrency control and duplicate prevention |
| Payroll Processing | `?page=process_payroll` | Automated salary computation with transaction management (Admin only) |
| Payroll History | `?page=payroll_history` | Filterable payroll records with window function analytics tab |
| Data Warehouse | `?page=data_warehouse` | Star schema ETL pipeline and department-level aggregate reports (Admin only) |
| User Management | `?page=users` | Create and manage user accounts (Admin only) |
| Audit Logs | `?page=audit_logs` | Chronological log of all system actions (Admin only) |

---

## Known Limitations

- **Fixed deduction rates:** Allowances (10%), overtime (5%), and income tax (10%) are hardcoded constants. The actual BIR Tax Table brackets are not implemented.
- **No attendance integration:** Overtime is a flat percentage of basic salary, not based on actual time records.
- **No payslip PDF export:** Payroll summaries are viewable on-screen only. PDF generation (e.g., via TCPDF or Dompdf) is not yet implemented.
- **Single-organization only:** Multi-company or multi-branch configurations are not supported.
- **SCD Type 1 warehouse:** The `dim_employee` table uses overwrite (SCD Type 1). Historical department changes prior to an ETL run are not preserved.
- **Incomplete REST API:** Endpoint skeletons exist under `/api/` (login, employees, payroll, dashboard) but the API layer is not the primary interface and is not fully secured.
- **No scheduled ETL:** The ETL pipeline (`sp_run_etl`) must be triggered manually via the Data Warehouse module's **Run ETL** button.

---

## Payroll Computation Formula

| Component | Computation |
|---|---|
| Allowances | 10% of Basic Salary |
| Overtime | 5% of Basic Salary |
| Gross Pay | Basic Salary + Allowances + Overtime |
| SSS | PHP 1,350.00 (fixed) |
| PhilHealth | PHP 450.00 (fixed) |
| Pag-IBIG | PHP 200.00 (fixed) |
| Income Tax | 10% of Basic Salary |
| Total Deductions | SSS + PhilHealth + Pag-IBIG + Income Tax |
| **Net Pay** | Gross Pay − Total Deductions |

---

## Key Technical Features

- **Transaction Management** — All payroll INSERTs are wrapped in `BEGIN / COMMIT / ROLLBACK` to guarantee atomicity
- **Optimistic Concurrency Control** — Employee edits use a `version_number` column to detect and reject mid-session conflicts
- **Pessimistic Locking** — `sp_process_payroll` uses `SELECT FOR UPDATE` on employee rows during payroll runs
- **Referential Integrity** — Foreign keys with `RESTRICT`, `CASCADE`, and `SET NULL` rules protect data consistency
- **Audit Trail** — Every action is logged to `activity_logs` with a denormalized `username` snapshot, preserved even after account deletion
- **SQL Window Functions** — `RANK`, `DENSE_RANK`, `LAG`, `LEAD`, `NTILE`, and rolling `AVG OVER` used in the analytics tab
- **Star Schema ETL** — `sp_run_etl` loads `fact_payroll` and three dimension tables (`dim_employee`, `dim_time`, `dim_department`) in a single transactional pipeline

---

## References

- [MySQL 8.0 Reference Manual](https://dev.mysql.com/doc/refman/8.0/en/)
- [PHP Manual — PDO](https://www.php.net/manual/en/book.pdo.php)
- [Bootstrap 5 Documentation](https://getbootstrap.com/docs/5.0/)
- Kimball, R. & Ross, M. (2013). *The Data Warehouse Toolkit* (3rd ed.). Wiley.
- Silberschatz, A., Korth, H. F., & Sudarshan, S. (2020). *Database System Concepts* (7th ed.). McGraw-Hill.

