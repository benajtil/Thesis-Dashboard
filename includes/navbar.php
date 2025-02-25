<?php
$loggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>


    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <style>
        /* Sidebar Styles */
        .sidebar {
            width: 80px; /* Default collapsed size */
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background-color: #1E1E2F;
            padding-top: 20px;
            transition: all 0.3s ease-in-out;
            z-index: 1000;
            box-shadow: 5px 0 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .sidebar.open {
            width: 250px; /* Expanded size */
        }

        .sidebar a {
            width: 100%;
            padding: 15px;
            text-decoration: none;
            font-size: 18px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center; /* Center icon */
            gap: 12px;
            transition: 0.3s;
            font-weight: bold;
        }

        .sidebar.open a {
            justify-content: flex-start;
            padding-left: 20px; /* Align icons with text */
        }

        .sidebar a i {
            font-size: 20px;
        }

        .sidebar a:hover {
            background-color: #252542;
        }

        .sidebar a span {
            display: none; /* Hide text by default */
        }

        .sidebar.open a span {
            display: inline; /* Show text when expanded */
        }

        .sidebar .logout {
            position: absolute;
            bottom: 20px;
            width: 100%;
        }

        /* Sidebar Toggle Button */
        .sidebar-toggle {
            position: fixed;
            top: 15px;
            left: 15px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1100;
            box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.2);
        }

        .sidebar-toggle:hover {
            background: #0056b3;
        }

        /* Adjust Main Content */
        .content {
            margin-left: 80px; /* Collapsed sidebar width */
            padding: 20px;
            transition: all 0.3s ease-in-out;
        }

        .content.shift {
            margin-left: 250px; /* Adjusted when sidebar expands */
        }
    </style>
</head>

<body>

    <!-- Sidebar -->

    <div class="sidebar" id="sidebar">
        <a href="#"><i class="fas fa-chart-line"></i> <span>Dashboard</span></a>
        <a href="customer_segmentation.php"><i class="fas fa-users"></i> <span>Customer Segmentation</span></a>
        <a href="upload.php"><i class="fas fa-upload"></i> <span>Upload</span></a>

        <!-- Show Login if not logged in, Logout if logged in -->
        <?php if ($loggedIn): ?>
            <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        <?php else: ?>
            <a href="login.php" class="login"><i class="fas fa-sign-in-alt"></i> <span>Login</span></a>
        <?php endif; ?>
    </div>

    <!-- Sidebar Toggle Button -->



    <script>
    document.getElementById("sidebarToggle").addEventListener("click", function () {
        let sidebar = document.getElementById("sidebar");
        let content = document.getElementById("mainContent");
        let toggleButton = document.getElementById("sidebarToggle");

        sidebar.classList.toggle("open");
        content.classList.toggle("shift");

        // Move the toggle button when sidebar expands or collapses
        if (sidebar.classList.contains("open")) {
            toggleButton.style.left = "260px"; // Moves the button to the right
        } else {
            toggleButton.style.left = "15px"; // Moves it back to original position
        }
    });
</script>


</body>
</html>
