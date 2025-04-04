<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php"); // Prevent unauthorized access
    exit();
}

$redirectPage = isset($_GET['redirect']) ? $_GET['redirect'] : "login.php";

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> PeerQuest </title>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

    <style>
        /* Reset default margin & padding */
        html, body {
            margin: 0;
            padding: 0;
            height: 100vh;
            width: 100vw;
            overflow: hidden; /* No scrollbars */
            background: black; /* Fallback in case video doesn't load */
        }

        /* Fullscreen Loading Screen */
        .loading-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        /* Fullscreen Video */
        .loading-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 1;
        }

        /* White Overlay for Smooth Fade */
        .fade-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: white;
            z-index: 10000; /* Ensures it covers the video */
            opacity: 1;
            animation: fadeIn 1.5s ease-in forwards; /* Smooth fade-in */
        }

        /* White Fade In (Start fully white, then disappear) */
        @keyframes fadeIn {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        /* White Fade Out (End with white screen) */
        @keyframes fadeOut {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    </style>
</head>
<body class="loading-screen">

    <!-- White Overlay for Smooth Transition -->
    <div class="fade-overlay"></div>

    <!-- Background Video -->
    <video autoplay muted playsinline class="loading-video">
        <source src="videos/loading.mp4" type="video/mp4">
        Your browser does not support the video tag.
    </video>

    <!-- Sound Effect -->
    <audio id="loading-audio" autoplay>
        <source src="audio/loading-page-sfx.mov" type="audio/mp3">
        Your browser does not support the audio tag.
    </audio>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const fadeOverlay = document.querySelector('.fade-overlay');
            const audio = document.getElementById('loading-audio');

            // Ensure audio plays without interaction
            audio.volume = 1.0;
            audio.play().catch(error => {
                console.log("Autoplay blocked, retrying...");
                setTimeout(() => {
                    audio.play();
                }, 1000);
            });

            // Delay before fade-out transition starts
            setTimeout(function () {
                fadeOverlay.style.animation = "fadeOut 1.5s ease-in forwards";

                // Redirect after fade-out is complete
                setTimeout(() => {
                    window.location.href = <?php echo json_encode($redirectPage); ?>;
                }, 1500); // Matches fade-out duration
            }, 3000); // Wait for video to play before fade-out starts
        });
    </script>

</body>
</html>
