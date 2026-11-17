
# MACVIN BIMIS
## Business Integrated Management Information System

### 1. Project Overview
Macvin Business Integrated Management Information System (BIMIS) is a centralised information management system designed for Macvin Hardware & Building Supplies Ltd, a medium-sized hardware business in Kampala, Uganda.

The system helps the organisation manage its daily business activities through a centralised database and role-based access.

### 2. Project Objectives
- Improve accuracy of business information.
- Manage sales and generate customer receipts.
- Monitor stock levels and identify low-stock products.
- Improve supplier and procurement management.
- Record business expenses and generate financial reports.
- Manage employee information and user accounts.
- Provide management with timely business reports.

### 3. System Modules
The system contains the following modules:

1. Management Overview
2. Sales Management
3. Inventory Management
4. Procurement Management
5. Finance Management
6. Human Resource Management
7. Reports
8. User Management

### 4. Technologies Used
- Frontend: HTML, CSS and JavaScript
- Backend: PHP
- Database: MySQL
- Server: Apache
- Development environment: XAMPP

### 5. Database Information
Database name: `macvin_bimis`

The database structure is provided in:

`macvin_bimis_structure.sql`

Employee records are provided separately in:

`macvin_employee_data.sql`

### 6. Installation Instructions

#### Requirements
- XAMPP
- PHP
- MySQL or MariaDB
- Web browser

#### Installation Steps

1. Install and open XAMPP.
2. Start Apache and MySQL.
3. Copy the `macvin_bimis` project folder into:

   `C:\xampp\htdocs\`

4. Open phpMyAdmin:

   `http://localhost/phpmyadmin/`

5. Create a database named:

   `macvin_bimis`

6. Select the database and import `macvin_bimis_structure.sql`.
7. Open the SQL tab and execute `macvin_employee_data.sql` to insert the employee records, using a fresh database with the six initial employee records assumed by the script.
8. Create and configure the local `db_connect.php` file using the settings in `db_connect.example.php`.
9. Open the system in a browser:

   `http://localhost/macvin_bimis/`

10. Log in using an authorised system account.

### 7. Project Structure

- `index.php` – System entry point
- `login.php` – User authentication
- `dashboard.php` – Role-based dashboard
- `sales.php` – Sales management
- `inventory.php` – Inventory management
- `procurement.php` – Procurement management
- `finance.php` – Finance management
- `hr.php` – Human resource management
- `reports.php` – Business reports
- `management_overview.php` – Management overview
- `user_management.php` – User account management
- `auth.php` – Authentication and access control
- `macvin_bimis_structure.sql` – Database structure
- `macvin_employee_data.sql` – Employee data setup
- `db_connect.example.php` – Sample database connection configuration
- `Prototype/` – Original HTML system prototype

### 8. User Access and Security
The system uses role-based access control to restrict users to authorised modules.

The General Manager has administrative privileges, while departmental users access functions relevant to their responsibilities.

Database connection settings and local setup files should not be uploaded to the public repository.

### 9. Project Repository
GitHub Repository:

https://github.com/wanderai441-tech/Macvin-BIMIS

### 10. Important Note
The HTML prototype and the PHP/MySQL implementation represent separate development stages of the project.

The PHP/MySQL implementation is the centralised system connected to the database.
