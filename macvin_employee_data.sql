
START TRANSACTION;

-- 1. Update the six existing employee records.
-- These IDs match the existing BIMIS database records.

UPDATE employees
SET full_name = 'Wandera Innocent',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Management'),
    job_title = 'General Manager / CEO',
    phone = '0758648757',
    email = 'wanderai441@gmail.com'
WHERE employee_id = 1;

UPDATE employees
SET full_name = 'Employee 02',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Sales'),
    job_title = 'Sales Attendant',
    phone = '0786325649',
    email = 'trenah256@gmail.com'
WHERE employee_id = 2;

UPDATE employees
SET full_name = 'Employee 09',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Inventory'),
    job_title = 'Inventory Officer',
    phone = '0708753222',
    email = 'uwera@gmail.com'
WHERE employee_id = 3;

UPDATE employees
SET full_name = 'Employee 14',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Procurement'),
    job_title = 'Procurement Officer',
    phone = '0768543290',
    email = 'nancy56@gmail.com'
WHERE employee_id = 4;

UPDATE employees
SET full_name = 'Employee 15',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Finance'),
    job_title = 'Finance Officer',
    phone = '0776368902',
    email = 'trampetin202@gmail.com'
WHERE employee_id = 5;

UPDATE employees
SET full_name = 'Employee 18',
    department_id = (SELECT department_id FROM departments WHERE department_name = 'Administration & Human Resource'),
    job_title = 'HR Officer',
    phone = '0786543780',
    email = 'febrin@gmail.com'
WHERE employee_id = 6;


-- 2. Insert the remaining 14 employees.

INSERT INTO employees
(department_id, full_name, job_title, phone, email, employment_status)

SELECT d.department_id, x.full_name, x.job_title,
       x.phone, x.email, 'Active'
FROM (
    SELECT 'Sales' AS department_name, 'Employee 03' AS full_name,
           'Sales Attendant' AS job_title, '0778967051' AS phone,
           'vibezcartel@gmail.com' AS email
    UNION ALL
    SELECT 'Sales', 'Employee 04', 'Sales Attendant',
           '0785432890', 'yasin770@gmail.com'
    UNION ALL
    SELECT 'Sales', 'Employee 05', 'Sales Attendant',
           '0768943210', 'apadet2@gmail.com'
    UNION ALL
    SELECT 'Sales', 'Employee 06', 'Sales Attendant',
           '0700632790', 'florence560@gmail.com'
    UNION ALL
    SELECT 'Sales', 'Employee 07', 'Sales Attendant',
           '0746464773', 'sebastian11@gmail.com'
    UNION ALL
    SELECT 'Sales', 'Employee 08', 'Sales Attendant',
           '0786543997', 'mxwell554@gmail.com'
    UNION ALL
    SELECT 'Inventory', 'Employee 10', 'Storekeeper',
           '0700563219', 'sewanyo221@gmail.com'
    UNION ALL
    SELECT 'Inventory', 'Employee 11', 'Storekeeper',
           '0708532156', 'ternin001@gmail.com'
    UNION ALL
    SELECT 'Inventory', 'Employee 12', 'Storekeeper',
           '0745321789', 'introvant660@gmail.com'
    UNION ALL
    SELECT 'Inventory', 'Employee 13', 'Storekeeper',
           '0787890657', 'kasambeko@gmail.com'
    UNION ALL
    SELECT 'Finance', 'Employee 16', 'Accounts Assistant',
           '0776431889', 'wecanist450@gmail.com'
    UNION ALL
    SELECT 'Finance', 'Employee 17', 'Cashier',
           '0709864289', 'lastborn@gmail.com'
    UNION ALL
    SELECT 'Administration & Human Resource', 'Employee 19', 'HR Assistant',
           '0787654327', 'saction2@gmail.com'
    UNION ALL
    SELECT 'Administration & Human Resource', 'Employee 20', 'Administration Assistant',
           '0785776336', 'adobet441@gmail.com'
) AS x
JOIN departments d
ON d.department_name = x.department_name
WHERE NOT EXISTS (
    SELECT 1
    FROM employees e
    WHERE e.full_name = x.full_name
);

COMMIT;
