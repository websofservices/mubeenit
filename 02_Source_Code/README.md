# MTNMANGOES – Online Mango Sales and Management System

**Final Year Project – BS Information Technology**  
**Govt. Graduate College Muzaffargarh · Department of Information Technology**

| Field | Detail |
|-------|--------|
| Student | Muhammad Mubeen (2022-GCBM-559) |
| Supervisor | Arslan Munir |
| Title | MTNMANGOES – Online Mango Sales and Management System |

---

## Approved Synopsis – Technology Stack

| Layer | Technology |
|-------|------------|
| SDLC | Waterfall Model |
| Frontend | HTML5, CSS3, JavaScript |
| Backend | PHP (Laravel Framework) |
| Database | MySQL |
| Payments | Stripe, PayPal |
| Tools | XAMPP, VS Code, phpMyAdmin, GitHub, Postman |

> **Implementation note:** This package includes a fully working **PHP + Bootstrap 5** application that implements all functional requirements from the synopsis. SQLite is used by default for zero-config demonstration; MySQL is supported by changing `config/database.php`. The architecture mirrors Laravel-style routing, roles, and modules for easy mapping to the planned Laravel stack.

---

## Functional Requirements Implemented

- Customer registration, login, product browsing, cart, ordering, payment (COD / Stripe / PayPal demo)
- Farmer product listing, stock management, order view
- Admin dashboard (users, products, orders, revenue stats)
- Real-time inventory tracking and deduction
- Role-based access control (Customer / Farmer / Admin)
- Order tracking and history

---

## How to Run

### Quick demo (PHP built-in server)
```bash
cd 02_Source_Code
php database/init.php
cd public
php -S localhost:8000
```
Open: http://localhost:8000

### XAMPP
1. Place folder under `htdocs/mtnmangoes`
2. Run `php database/init.php`
3. Visit `http://localhost/mtnmangoes/public/`

### Switch to MySQL
Edit `config/database.php`:
```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mtnmangoes');
define('DB_USER', 'root');
define('DB_PASS', '');
```
Create the database in phpMyAdmin, then run `php database/init.php`.

---

## Demo Accounts

Password for all accounts: **password123**

| Role | Email |
|------|-------|
| Admin | admin@mtnmangoes.com |
| Farmer | ali@farmer.com |
| Customer | ahmed@customer.com |

---

## Project Structure

```
02_Source_Code/
├── config/database.php      # SQLite / MySQL configuration
├── database/
│   ├── schema.sql           # Table definitions
│   ├── init.php             # Create tables + seed data
│   └── mtnmangoes.db        # SQLite database (auto-created)
├── public/index.php         # Main application (router + views)
└── README.md
```

---

## Expected Outcomes (Synopsis)

- Fully functional online mango selling and management system
- Increased farmer profit through elimination of middlemen
- Digital marketplace with customer ordering and payment features
- Automated inventory and order management
- Enhanced transparency in mango trading
- Centralized reporting and analytics dashboard
