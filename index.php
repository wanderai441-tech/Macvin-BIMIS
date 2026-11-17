
<?php
require_once "db_connect.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BIMIS | Macvin Hardware</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f1f4f8;
            color: #263238;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            background: white;
            width: 90%;
            max-width: 650px;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        h1 {
            color: #17365d;
            margin-bottom: 10px;
        }

        .system-name {
            color: #64748b;
            margin-bottom: 25px;
        }

        .status {
            background: #e8f5e9;
            color: #237a38;
            padding: 12px;
            border-radius: 6px;
            margin: 20px 0;
        }

        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #777;
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>MACVIN HARDWARE</h1>

        <p class="system-name">
            Business Integrated Management Information System (BIMIS)
        </p>

        <div class="status">
            Central database connection is active.
        </div>

        <p>
            Welcome to the BIMIS centralised application.
            This system will support sales, inventory,
            procurement, finance and human resource management.
        </p>

        <div class="footer">
            Macvin Hardware & Building Supplies Ltd<br>
            BIMIS Centralised System
        </div>
    </div>

</body>
</html>
