<?php
require 'config.php'; // Include your database connection

// Fetch the module (assuming you're displaying one module at a time)
$stmt = $pdo->query("SELECT id, title, file_name, file_content, uploaded_at FROM make_modules_fupload_tbl LIMIT 1");
$module = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$module) {
    echo "No modules found.";
    exit;
}

// Pagination Logic
$content = $module['file_content'];
$words = explode(' ', $content); // Split content into words
$words_per_page = 210; // Words per page
$total_pages = ceil(count($words) / $words_per_page);

// Get the current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
if ($page > $total_pages) $page = $total_pages;

// Calculate Progress Percentage
$progress_percentage = ($page / $total_pages) * 100;

// Get the words for the current page
$start = ($page - 1) * $words_per_page;
$current_page_words = array_slice($words, $start, $words_per_page);
$current_page_content = implode(' ', $current_page_words);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Module</title>
    <style>
        /* Progress bar container */
        .progress-container {
            width: 25%; /* Reduced size to 25% of the display */
            background-color: #f3f3f3;
            border: 1px solid #ccc;
            border-radius: 5px;
            height: 20px;
            margin: 10px auto; /* Center-align */
        }

        /* Progress bar filler */
        .progress-bar {
            height: 100%;
            background-color: #4caf50;
            width: <?php echo $progress_percentage; ?>%;
            text-align: center;
            color: white;
            line-height: 20px;
            border-radius: 5px;
            transition: width 0.5s ease;
            font-size: 12px; /* Smaller font size */
        }
    </style>
</head>
<body>
    <!-- Progress Bar -->
    <div class="progress-container">
        <div class="progress-bar"><?php echo round($progress_percentage); ?>%</div>
    </div>

    <!-- Module Content -->
    <h2><?php echo htmlspecialchars($module['title']); ?></h2>
    <p><strong>Uploaded At:</strong> <?php echo $module['uploaded_at']; ?></p>
    <p><strong>File Name:</strong> <?php echo htmlspecialchars($module['file_name']); ?></p>
    <p><strong>Content:</strong></p>
    <div style="white-space: pre-wrap;"><?php echo htmlspecialchars($current_page_content); ?></div>

    <!-- Pagination Controls -->
    <div>
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" onclick="updateProgress(<?php echo $page - 1; ?>, <?php echo $total_pages; ?>)">Previous</a>
        <?php endif; ?>
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?>" onclick="updateProgress(<?php echo $page + 1; ?>, <?php echo $total_pages; ?>)">Next</a>
        <?php endif; ?>
    </div>

    <script>
        // JavaScript to dynamically update the progress bar
        function updateProgress(currentPage, totalPages) {
            const progressPercentage = (currentPage / totalPages) * 100;
            const progressBar = document.querySelector('.progress-bar');
            progressBar.style.width = progressPercentage + '%';
            progressBar.textContent = Math.round(progressPercentage) + '%';
        }
    </script>
</body>
</html>

