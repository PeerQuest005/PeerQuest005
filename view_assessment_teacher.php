<?php
require 'auth.php';
require 'config.php';

$class_id = $_GET['class_id'] ?? null;
$message = $_GET['message'] ?? '';

if (!isset($class_id) || !$class_id) {
    echo "Invalid class ID.";
    exit();
}

// Check if the user is a teacher (role 1)
if ($_SESSION['role'] != 1) {
    echo "Access denied: Teachers only.";
    exit();
}

// Handle form submission to create or update an assessment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $assessment_id = $_POST['assessment_id'] ?? null;
    $name = $_POST['name'] ?? 'Untitled Assessment';
    $type = $_POST['type'] ?? 'Essay'; // Default type
    $status = ($type === 'Recitation') ? 'Published' : 'Saved'; // Set "Published" for Recitation
    $time_limit = $_POST['time_limit'] ?? 10;
    $assessment_mode = $_POST['mode'] ?? 'Individual';
    $instructions = $_POST['instructions'] ?? '';

    if ($assessment_id) {
        // Update existing assessment
        $stmt = $pdo->prepare(
            "UPDATE assessment_tbl 
            SET name = ?, type = ?, time_limit = ?, assessment_mode = ?, instructions = ?, status = ? 
            WHERE assessment_id = ?"
        );
        $stmt->execute([$name, $type, $time_limit, $assessment_mode, $instructions, $status, $assessment_id]);
        $message = "Assessment updated successfully!";
    } else {
        $total_points = 0;

        // Insert new assessment
        $stmt = $pdo->prepare(
            "INSERT INTO assessment_tbl 
            (class_id, teacher_id, name, type, status, time_limit, assessment_mode, total_points, instructions) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([ 
            $class_id,
            $_SESSION['teacher_id'],
            $name,
            $type,
            $status,
            $time_limit,
            $assessment_mode,
            $total_points,
            $instructions
        ]);

        $assessment_id = $pdo->lastInsertId();
        $message = "Assessment created successfully!";
    }

    // Redirect to the appropriate page based on the type of assessment
    switch ($type) {
        case 'Essay':
            header("Location: assessment_essay.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Multiple Choice - Individual':
            header("Location: assessment_multiple_choice.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Multiple Choice - Collaborative':
            header("Location: assessment_multiple_choice_collab.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'Recitation':
            header("Location: assessment_recitation.php?assessment_id=$assessment_id&message=$message");
            break;
        case 'True or False':
            header("Location: assessment_true_false.php?assessment_id=$assessment_id&message=$message");
            break;
        default:
            die("Invalid assessment type.");
    }
    exit();
}

// Fetch class details
$stmt = $pdo->prepare("SELECT class_subject, class_section FROM class_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();
if (!$class) {
    echo "Class not found.";
    exit();
}

// Fetch assessments for the class
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE class_id = ?");
$stmt->execute([$class_id]);
$assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate assessments into saved and published categories
$saved_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Saved');
$published_assessments = array_filter($assessments, fn($a) => $a['status'] === 'Published');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assessments for <?php echo htmlspecialchars($class['class_section'] . ' - ' . $class['class_subject']); ?></title>
    <style>
        /* Styling for the pop-up notification */
        #popupMessage {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background-color: #4CAF50; /* Green background for success */
            color: white;
            border-radius: 5px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }
    </style>
    <script>
        function showPopupMessage(message) {
            const popup = document.getElementById('popupMessage');
            popup.textContent = message;
            popup.style.display = 'block';

            // Hide the message after 3 seconds
            setTimeout(() => {
                popup.style.display = 'none';
            }, 3000);
        }

        // Show popup message if there's a message in PHP
        <?php if ($message): ?>
            window.onload = function() {
                showPopupMessage("<?php echo htmlspecialchars($message); ?>");
            };
        <?php endif; ?>
    </script>
</head>
<body>
    <!-- Pop-up Message Element -->
    <div id="popupMessage"></div>

    <h2>Assessments for <?php echo htmlspecialchars($class['class_section'] . ' - ' . $class['class_subject']); ?></h2>

    <!-- Create New Assessment Form -->
    <h3>Create New Assessment</h3> 
    <a href="teacher_dashboard.php" class="btn btn-primary">Dashboard</a> <br><br>

    <form method="post">
        <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($class_id); ?>">
        <label for="name">Assessment Name:</label>
        <input type="text" name="name" id="name" required>

        <label for="type">Assessment Type:</label>
        <select name="type" id="type" required>
            <option value="Essay">Essay</option>
            <option value="Recitation">Recitation</option>
            <option value="True or False">True or False</option>
            <option value="Multiple Choice - Individual">Multiple Choice - Individual</option>
            <option value="Multiple Choice - Collaborative">Multiple Choice - Collaborative</option>
        </select>

        <button type="submit">Create Assessment</button>
    </form>

    <!-- Display Assessments -->
    <h3>Saved Assessments</h3>
    <ul>
        <?php if (!empty($saved_assessments)): ?>
            <?php foreach ($saved_assessments as $assessment): ?>
                <li>
                    <?php echo htmlspecialchars($assessment['name']); ?> - Type: <?php echo htmlspecialchars($assessment['type']); ?>
                    | <a href="<?php 
                        switch ($assessment['type']) {
                            case 'Essay':
                                echo "assessment_essay.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'True or False':
                                echo "assessment_true_false.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'Multiple Choice - Individual':
                                echo "assessment_multiple_choice.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'Multiple Choice - Collaborative':
                                echo "assessment_multiple_choice_collab.php?assessment_id=" . $assessment['assessment_id'];
                                break;
                            default:
                                echo "#";
                        }
                    ?>">Edit</a>
                    | <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=publish">Publish</a>
                    | <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=delete" onclick="return confirm('Are you sure you want to delete this assessment?');">Delete</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No saved assessments available.</p>
        <?php endif; ?>
    </ul>

    <h3>Published Assessments</h3>
    <ul>
        <?php if (!empty($published_assessments)): ?>
            <?php foreach ($published_assessments as $assessment): ?>
                <li>
                    <?php echo htmlspecialchars($assessment['name']); ?> - Type: <?php echo htmlspecialchars($assessment['type']); ?>
                    | <a href="<?php 
                        switch ($assessment['type']) {
                            case 'Essay':
                                echo "assessment_essay.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'True or False':
                                echo "assessment_true_false.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'Multiple Choice - Individual':
                                echo "assessment_multiple_choice.php?assessment_id=" . $assessment['assessment_id'];
                                break;

                            case 'Multiple Choice - Collaborative':
                                echo "assessment_multiple_choice_collab.php?assessment_id=" . $assessment['assessment_id'];
                                break;
                            default:
                                echo "error";
                        }
                    ?>">View/Edit</a>
                    | <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=unpublish">Unpublish</a>
                    | <a href="assessment_status.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&action=delete" onclick="return confirm('Are you sure you want to delete this assessment?');">Delete</a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No published assessments available.</p>
        <?php endif; ?>
    </ul>
</body>
</html>
