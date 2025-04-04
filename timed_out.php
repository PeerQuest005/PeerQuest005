<?php 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assessment Submitted!</title>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">
    <style>
        /* Import Font */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');

        /* General Styling */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #24243A;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            text-align: center;
        }

        /* Submission Success Container */
        .submission-container {
            background: #ffffff;
            color: #24243A;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0px 8px 20px rgba(255, 255, 255, 0.2);
            width: 450px;
            animation: fadeIn 0.8s ease-in-out;
        }

        /* GIF */
        .celebration-gif {
            width: 300px;
            margin-bottom: 20px;
        }

        /* Message */
        .message {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .subtext {
            font-size: 1rem;
            color: #555;
            margin-bottom: 20px;
        }

        /* Modified Back Button (Now inside the container) */
        .back-button {
            display: inline-block;
            background-color: #ccc;
            color: #24243A;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            margin-top: 15px;
        }

        .back-button:hover {
            background-color: #24243A;
            color: white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

    <!-- Submission Success Container -->
    <div class="submission-container">
        <img src="images/timer.webp" alt="Celebration" class="celebration-gif">
        <div class="message">TIME'S UP!</div>
        <p class="subtext">Your assessment has been submitted successfully.</p>
        
        <!-- Back Button Inside the Container -->
        <a href="student_dashboard.php" class="back-button">Back to Dashboard</a>
    </div>

</body>
</html>
