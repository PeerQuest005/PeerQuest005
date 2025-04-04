<?php
session_start();

// Store the previous page (Only when the user first lands on logout.php)
if (!isset($_SESSION['previous_page']) && isset($_GET['prev_page'])) {
    $_SESSION['previous_page'] = $_GET['prev_page'];
}

// If logout is confirmed, destroy the session and redirect to login page
if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
    session_destroy();
    unset($_SESSION['previous_page']); // Remove stored previous page
    header("Location: login.php");
    exit();
}

// If "No" is clicked, redirect back based on role
if (isset($_GET['confirm']) && $_GET['confirm'] == 'no') {
    if (isset($_SESSION['role']) && $_SESSION['role'] == 2) {
        // Redirect to student dashboard if role is 2 (Student)
        $previous_page = $_SESSION['previous_page'] ?? 'student_dashboard.php';
    } else {
        // Default to teacher dashboard if role is 1 (Teacher) or missing
        $previous_page = $_SESSION['previous_page'] ?? 'teacher_dashboard.php';
    }

    unset($_SESSION['previous_page']); // Prevent looping
    header("Location: $previous_page");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout Confirmation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/teacher_dashboard.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #24243A;
        }

        /* Full-screen modal background */
        .modal {
            display: flex;
            position: fixed;
            z-index: 999;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.3);
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        /* Modal content (Centered Box) */
        .modal-content {
            background: white;
            padding: 20px;
            border-radius: 20px;
            text-align: center;
            width: 300px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        /* Close Button (X) */
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 1.5rem;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            transition: color 0.3s ease-in-out;
        }

        .close-btn:hover {
            color: #000;
        }

        /* Text spacing */
        .modal-content p {
            margin-bottom: 20px;
        }

        /* Buttons */
        .modal-content button {
            margin: 5px;
            padding: 15px 30px;
            border: none;
            cursor: pointer;
            border-radius: 15px;
            font-size: 1rem;
        }

        /* Yes Button */
        .btn-yes {
            background-color: #de3b3b;
            color: white;
        }

        /* No Button */
        .btn-no {
            background-color: #dcdcdcb4;
            color:#24243A;;
            border: 1px solid #24243A;
        }

        /* Hover Effects */
        .btn-yes:hover {
            background-color: #24243A;
        }

        .btn-no:hover {
            background-color: #24243A;
            color: white;
            
        }
    </style>
</head>
<body>

<!-- Logout Confirmation Modal -->
<div class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="cancelLogout()">&times;</span> <!-- X Button -->
        <p>Are you sure you want to log out?</p>
        
        <button class="btn-yes" onclick="confirmLogout()">Yes</button>
        <button class="btn-no" onclick="cancelLogout()">No</button>
    </div>
</div>

<script>
    // Redirect to confirm logout
    function confirmLogout() {
        window.location.href = "logout.php?confirm=yes";
    }

    // Redirect back to the previous page if "No" or "X" is clicked
    function cancelLogout() {
        window.location.href = "logout.php?confirm=no";
    }
</script>

</body>
</html>
