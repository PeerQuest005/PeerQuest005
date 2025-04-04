<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;

// Fetch assessment data
$assessment = null;
if ($assessment_id) {
    $stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
    $stmt->execute([$assessment_id]);
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assessment) {
        die("Assessment not found.");
    }
} else {
    die("No assessment selected.");
}


// Fetch existing questions
$stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dynamically calculate total points
$total_points = array_sum(array_column($questions, 'points'));

// Update total points in the assessment table
$stmt = $pdo->prepare("UPDATE assessment_tbl SET total_points = ? WHERE assessment_id = ?");
$stmt->execute([$total_points, $assessment_id]);


// Initialize messages
$success_messages = [];
$error_messages = [];

// Handle Save/Publish/Update/Unpublish actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $time_limit = $_POST['time_limit'] ?? $assessment['time_limit'];
    $instructions = $_POST['instructions'] ?? $assessment['instructions'];
    $assessment_title = trim($_POST['assessment_name'] ?? $assessment['name']);

    if (isset($_POST['save_assessment']) || isset($_POST['publish_assessment'])) {
        if (count($questions) === 0) {
            $error_messages[] = "Please add at least one question before saving or publishing.";
        } else {
            $status = isset($_POST['publish_assessment']) ? 'Published' : 'Saved';
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET name = ?, status = ?, time_limit = ?, total_points = ? WHERE assessment_id = ?");
            $stmt->execute([$assessment_title, $status, $time_limit, $total_points, $assessment_id]);

            $assessment['name'] = $assessment_title;
            $assessment['time_limit'] = $time_limit;
            $assessment['status'] = $status;

            $success_messages[] = isset($_POST['save_assessment']) ? "Assessment saved successfully!" : "";
            $success_messages[] = isset($_POST['publish_assessment']) ? "Assessment published successfully!" : "";
        }
    }

    //updating 
    if (isset($_POST['update_assessment'])) {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET name = ?, instructions = ?, time_limit = ? WHERE assessment_id = ?");
        $stmt->execute([$assessment_title, $instructions, $time_limit, $assessment_id]);

        $assessment['name'] = $assessment_title;
        $assessment['time_limit'] = $time_limit;
        $assessment['instructions'] = $instructions;

        $success_messages[] = "Assessment updated successfully!";
    }

    //unpublish
    if (isset($_POST['unpublish_assessment'])) {
        $stmt = $pdo->prepare("UPDATE assessment_tbl SET status = 'Saved' WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);

        $assessment['status'] = 'Saved';
        $success_messages[] = "Assessment unpublished successfully!";
    }

    // Handle adding a new essay question
    if (isset($_POST['add_question'])) {
        $question_text = $_POST['question'] ?? '';
        $points = $_POST['points'] ?? 0;

        if (empty($question_text)) {
            $error_messages[] = "Question text cannot be empty.";
        } else {
            // Insert the new question
            $stmt = $pdo->prepare("INSERT INTO questions_esy_tbl (assessment_id, question_text, points) VALUES (?, ?, ?)");
            $stmt->execute([$assessment_id, $question_text, $points]);

            // Get last inserted question ID
            $last_question_id = $pdo->lastInsertId();

            // Update total points dynamically
            $stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
            $stmt->execute([$assessment_id]);
            $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total_points = array_sum(array_column($questions, 'points'));
            $stmt = $pdo->prepare("UPDATE assessment_tbl SET total_points = ? WHERE assessment_id = ?");
            $stmt->execute([$total_points, $assessment_id]);

              // Store in session
            $_SESSION['last_added_question'] = $last_question_id;

            // Refresh page with anchor
            header("Location: assessment_essay.php?assessment_id=$assessment_id#question-$last_question_id");
            exit();

        }
    }

    // Handle removing a question
    if (isset($_POST['remove_question'])) {
        $question_id = $_POST['question_id'] ?? null;
        if ($question_id) {
            $stmt = $pdo->prepare("DELETE FROM questions_esy_tbl WHERE question_id = ?");
            $stmt->execute([$question_id]);
        }

        header("Location: assessment_essay.php?assessment_id=$assessment_id");
        exit();
    }
}
    if (isset($_POST['update_question'])) {
        $question_id = $_POST['question_id'];
        $question_text = trim($_POST['question_text']);
        $points = $_POST['points'];
    
        // Update the question with the correct answer
        $stmt = $pdo->prepare("UPDATE questions_esy_tbl SET question_text = ?, points = ? WHERE question_id = ?");
        $stmt->execute([$question_text, $points, $question_id]);
    
        // Re-fetch the updated questions
        $stmt = $pdo->prepare("SELECT * FROM questions_esy_tbl WHERE assessment_id = ?");
        $stmt->execute([$assessment_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Essay | PeerQuest</title>
    <link rel="stylesheet" href="css/assessment_essay.css">
    
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<script src="https://cdn.jsdelivr.net/npm/lucide@0.162.0/dist/umd/lucide.min.js"></script>
<script>
    window.onload = function() {
        lucide.createIcons();
    };
</script>
</head>
<body>
<!-- SIDEBAR BEGINS HERE -->
<div class="sidebar">
    <div class="logo-container">
        <img src="images/logo/pq_logo.webp" class="logo" alt="PeerQuest Logo">
        <img src="images/logo/pq_white_logo_txt.webp" class="logo-text" alt="PeerQuest Logo Text">
    </div>

        <ul class="nav-links">
            <li><a href="teacher_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard"> <span>Dashboard</span></a></li>
           
             
        <?php if (isset($_GET['class_id']) || isset($assessment['class_id'])): ?>
            <li><a href="view_classlist.php?class_id=<?php echo $_GET['class_id'] ?? $assessment['class_id']; ?>">
                <img src="images/icons/class_icon.png" alt="Class List"> <span>Class List</span></a></li>
            
            <li><a href="teacher_modules.php?class_id=<?php echo $_GET['class_id'] ?? $assessment['class_id']; ?>">
                <img src="images/icons/module_icon.png" alt="Modules"> <span>Modules</span></a></li>

            <li><a href="view_assessment_teacher.php?class_id=<?php echo $_GET['class_id'] ?? $assessment['class_id']; ?>">
                <img src="images/icons/assessment_icon.png" alt="Assessments"> <span>Assessments</span></a></li>
        <?php endif; ?>

        </ul>

        <div class="logout">
            <a href="logout.php" class="logout-btn">
                <img src="images/logout_white_icon.png" alt="Logout"> <span>LOG OUT</span>
            </a>
          </div>
         </div>

        <button class="toggle-btn" onclick="toggleSidebar()">
            <img src="images/sidebar_close_icon.png" id="toggleIcon" alt="Toggle Sidebar">
        </button>


        <div class="content">
        <div class="top-bar">
        <h1 class="dashboard-title"> Edit Assessment - ESSAY</h1>
        </div>

<!-- SIDEBAR ENDS HERE -->

<!-- Display Messages -->
        <!-- Display Messages -->
        <div class="message-container">
    <?php if (!empty($success_messages)): ?>
        <div class="success-message">
            <?php foreach ($success_messages as $message): ?>
                <p><?php echo $message; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_messages)): ?>
        <div class="error-messages">
            <?php foreach ($error_messages as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<form method="post">

<!-- Save and Publish Buttons -->
<div class="top-buttons">
        <?php if ($assessment['status'] === 'Published'): ?>
                <button type="submit" name="update_assessment" class="btn btn-update">Update</button>
                <button type="submit" name="unpublish_assessment" class="btn btn-unpublish">Unpublish</button>
        <?php else: ?>
                <button type="submit" name="save_assessment" class="btn btn-save">Save</button>
                <button type="submit" name="publish_assessment" class="btn btn-publish">Publish</button>
           
        <?php endif; ?>
    </div>

    <div class="container">

        <form method="post">
        <div class="header-section">
        
        <div class="card">
        <h3 class="editable-heading">Assessment Name
        <img src="images/icons/edit_icon.webp" alt="Edit Icon" class="edit-icon" />
        </h3>
             <input type="text" id="assessment-name" name="assessment_name" value="<?php echo htmlspecialchars($assessment['name']); ?>" required>
        </div>
        </div>
    </form>

    <div class="card settings-card">
    <div class="settings-row">
        <div class="form-group">
            <h3 class="editable-heading">Time Limit
                 <img src="images/icons/clock_icon.webp" alt="Edit Icon" class="edit-icon" />
            </h3>
             <label>(set the time in minutes) </label>
            <input type="number" id="time-limit" value="<?php echo $assessment['time_limit']; ?>" onchange="updateTimeLimit()" />
        </div>

        <div class="form-group">
            <h3 class="editable-heading">Total Points
                 <img src="images/icons/points_icon.webp" alt="Edit Icon" class="edit-icon" />
            </h3>
            <label>(Points automatically increase as questions are added.) </label>
            <div class="total-points-badge">
                <?php echo htmlspecialchars($total_points); ?>
            </div>
        </div>
    </div>

    <div class="form-group">
    <h3 class="editable-heading">Instructions
                 <img src="images/icons/instructions_icon.webp" alt="Edit Icon" class="edit-icon" />
            </h3>
        <label for="instructions">(Important Notes for Students)</label>
        <textarea id="instructions" onchange="updateInstructions()" required><?php echo htmlspecialchars($assessment['instructions']); ?></textarea>
    </div>
</div>
   


<div class="card">
    <!-- Add Questions to Assessment -->
    <h3 class="editable-heading">Add Questions
                 <img src="images/icons/questions_icon.webp" alt="Edit Icon" class="edit-icon" />
            </h3>
    <form method="post">
        <label>Question:</label>
        <input type="text" name="question" required>
        <label>Points:</label>
        <input type="number" name="points" min="1" required>

        <button type="submit" name="add_question">Add Question</button>
    </form>
    </div>

    <div class="card">
    <!-- Display Current Questions -->
    <h3 class="editable-heading">Current Questions
                 <img src="images/icons/questions_icon.webp" alt="Edit Icon" class="edit-icon" />
            </h3>
    <ol>
        <?php foreach ($questions as $question): ?>
            <li id="question-<?php echo $question['question_id']; ?>">
                <label>Question:</label>
                <input type="text" id="question-text-<?php echo $question['question_id']; ?>" value="<?php echo htmlspecialchars($question['question_text']); ?>" onchange="updateQuestion('<?php echo $question['question_id']; ?>')">
                
                <label>Points:</label>
                <input type="number" id="question-points-<?php echo $question['question_id']; ?>" value="<?php echo $question['points']; ?>" min="0" onchange="updateQuestion('<?php echo $question['question_id']; ?>')">
                
                <button type="button" onclick="removeQuestion('<?php echo $question['question_id']; ?>')">Remove</button>
            </li>
        <?php endforeach; ?>
    </ol>
        </div>
        </div>

        <button class="back-to-top" onclick="scrollToTop()">
    <svg class="lucide-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="4 10 12 4 20 10"></polyline>
        <line x1="12" y1="20" x2="12" y2="4"></line>
    </svg>
    Back to Top
</button>
    <script>

        function updateName() {
            const name = document.getElementById('name').value;
            updateField('', { update_assessment: 1, name });
        }

        function updateInstructions() {
            const instructions = document.getElementById('instructions').value;
            updateField('', { update_assessment: 1, instructions });
        }

        function updateTimeLimit() {
            const timeLimit = document.getElementById('time-limit').value;
            updateField('', { update_assessment: 1, time_limit: timeLimit });
        }

        function updateQuestion(questionId) {
            const questionText = document.getElementById(`question-text-${questionId}`).value;
            const points = document.getElementById(`question-points-${questionId}`).value;

            updateField('', {
                update_question: 1,
                question_id: questionId,
                question_text: questionText,
                points,
            });
        }

        async function removeQuestion(questionId) {
        if (confirm("Are you sure you want to remove this question?")) {
            const formData = new FormData();
            formData.append('remove_question', 1);
            formData.append('question_id', questionId);

            try {
            const response = await fetch('assessment_essay.php?assessment_id=<?php echo $assessment_id; ?>', {
                method: 'POST',
                body: formData
            });

            if (response.ok) {
                location.reload();  // Reload the page after successful deletion
            } else {
                alert("Failed to remove the question. Please try again.");
            }
        } catch (error) {
            alert("An error occurred while removing the question.");
        }
    }
}


async function updateField(url, data) {
    const formData = new FormData();
    for (const key in data) {
        formData.append(key, data[key]);
    }

    try {
        const response = await fetch('assessment_essay.php?assessment_id=<?php echo $assessment_id; ?>', {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            throw new Error("Network response was not ok");
        }
        console.log("Question updated successfully.");
    } catch (error) {
        console.error("Error updating question:", error);
    }
}
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('collapsed');
            document.querySelector('.content').classList.toggle('expanded');
            document.querySelector('.top-bar').classList.toggle('expanded');
            const toggleIcon = document.getElementById('toggleIcon');
                    if (document.querySelector('.sidebar').classList.contains('collapsed')) {
                        toggleIcon.src = "images/sidebar_open_icon.png";
                    } else {
                        toggleIcon.src = "images/sidebar_close_icon.png";
                    }
                }

    
                window.onscroll = function() {
        const backToTopButton = document.querySelector('.back-to-top');
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            backToTopButton.style.display = "block";
        } else {
            backToTopButton.style.display = "none";
        }
    };

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.addEventListener("DOMContentLoaded", function () {
    // Check if there's a last added question stored in PHP session
    <?php if (!empty($_SESSION['last_added_question'])): ?>
        let lastQuestionId = "<?php echo $_SESSION['last_added_question']; ?>";
        let lastQuestionElement = document.getElementById("question-" + lastQuestionId);
        
        if (lastQuestionElement) {
            lastQuestionElement.scrollIntoView({ behavior: "smooth", block: "center" });
        }

        // Clear the session variable so it doesn't scroll again on the next reload
        <?php unset($_SESSION['last_added_question']); ?>
    <?php endif; ?>
});

    </script>
</body>
</html>
