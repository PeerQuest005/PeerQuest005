<?php
require 'auth.php';
require 'config.php';

$assessment_id = $_GET['assessment_id'] ?? null;

// Fetch class_id associated with the assessment
$stmt = $pdo->prepare("SELECT class_id FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$class = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$class) {
    echo "Class not found for this assessment.";
    exit();
}

$class_id = $class['class_id'];

if (!$assessment_id) {
    echo "Invalid assessment ID.";
    exit();
}

// Check if the user is authorized (e.g., teacher or admin)
if ($_SESSION['role'] != 1) {
    $_SESSION['error_role'] = 'Access Denied! Authorized Teachers Only.';
    header('Location: ./student_dashboard.php');
    exit();
}

// Fetch assessment name
$stmt = $pdo->prepare("SELECT name FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

$assessment_name = $assessment['name'];

// Fetch total possible points for the assessment
$points_stmt = $pdo->prepare("SELECT SUM(points) AS total_possible_points FROM questions_mcq_tbl WHERE assessment_id = ?");
$points_stmt->execute([$assessment_id]);
$total_possible_points = $points_stmt->fetch(PDO::FETCH_ASSOC)['total_possible_points'] ?? 0;

// Handle AJAX request for chat updates
if (isset($_GET['fetch_chat']) && isset($_GET['room_id'])) {
    $room_id = $_GET['room_id'];
    $chat_stmt = $pdo->prepare("
        SELECT ch.content, ch.time_and_date, s.student_first_name, s.student_last_name 
        FROM chat_history ch
        INNER JOIN student_tbl s ON ch.student_id = s.student_id
        WHERE ch.room_id = ?
        ORDER BY ch.time_and_date DESC
    ");
    $chat_stmt->execute([$room_id]);
    $chat_history = $chat_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($chat_history);
    exit();
}

// Fetch all room_id(s) for the given assessment_id
$stmt = $pdo->prepare("SELECT DISTINCT room_id FROM room_ready_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group View for <?php echo htmlspecialchars($assessment_name); ?></title>
    <link rel="stylesheet" href="css/groups.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function fetchChatUpdates(roomId) {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: {
                    fetch_chat: 1,
                    room_id: roomId
                },
                success: function (data) {
                    const chatContainer = $('#chat-history-' + roomId);
                    chatContainer.empty(); // Clear existing chat history
                    const chatHistory = JSON.parse(data);
                    if (chatHistory.length > 0) {
                        chatHistory.forEach(chat => {
                            chatContainer.append(
                                `<li>
                                    <span class="chat-user">${chat.student_first_name} ${chat.student_last_name}:</span>
                                    <span class="chat-convo">${chat.content}</span>
                                    <div class="chat-time">${new Date(chat.time_and_date).toLocaleString()}</div>
                                </li>`
                            );
                        });
                    } else {
                        chatContainer.append('<p>No chat history found for this room.</p>');
                    }
                }
            });
        }

        function startChatPolling(roomId) {
            fetchChatUpdates(roomId);
            setInterval(function () {
                fetchChatUpdates(roomId);
            }, 2000); // Update every 2 seconds
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f7f7;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        h1 { font-size: 1.5rem; color: #24243A; }
        h2 { font-size: 1.5rem; margin-bottom: 10px; }
        h3 { font-size: 1.2rem; color: #24243A; margin-bottom: 10px; }
        .container {
            background-color: #ffffff;
            border: 1px solid #ccc;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
            width: 90%;
            margin-top: 20px;
        }
        .room-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .room-header h2 { margin: 0; }
        .button-link button {
            padding: 10px 15px;
            background-color: #47A99C;
            color: #fff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
        }
        .button-link button:hover { background-color: #24243A; }
        .grid-layout {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 20px;
        }
        .student-list {
            border-right: 1px solid #ccc;
            padding-right: 10px;
        }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { padding: 8px; font-size: 0.9rem; }
        table th { text-align: left; background-color: #f7f7f7; }
        .chat-history-scroll {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #ffffff;
        }
        .chat-history-scroll ul { list-style: none; padding: 0; margin: 0; }
        .chat-history-scroll li { padding: 5px 0; }
        .chat-user { font-weight: bold; font-size: 0.9rem; }
        .chat-time { font-size: 0.8rem; color: #888; }
        .go-back-btn {
            font-size: 1rem;
            cursor: pointer;
            padding: 13px 15px;
            margin-top: 20px;
            background-color: #6c757d;
            margin-left: 52px;
        }
        .go-back-btn:hover { background-color: #5a6268; }
        .underline { text-decoration: underline; font-weight: bold; color: #47A99C; }
        .top-bar {
            background: #F7F7F7;
            height: 60px;
            color: #000000;
            padding: 0 50px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #ddd;
            margin: 0;
            overflow: visible;
        }
        .dashboard-title {
            margin-left: 10px;
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: #24243A;
        }
    </style>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">
</head>
<body>
    <div class="top-bar">
        <h1 class="dashboard-title">Viewing Groups for <?php echo htmlspecialchars($assessment_name); ?></h1>
    </div>

    <button class="go-back-btn"
        data-url="view_assessment_teacher.php?class_id=<?php echo htmlspecialchars($class_id); ?>">Go Back to Assessments</button>

    <?php foreach ($rooms as $room): ?>
        <?php
        $room_id = $room['room_id'];

        // Fetch users in the current room_id with status "started"
        $stmt = $pdo->prepare("
            SELECT u.student_id, u.username, u.student_first_name, u.student_last_name
            FROM room_ready_tbl r
            JOIN student_tbl u ON r.student_id = u.student_id
            WHERE r.room_id = ? AND r.status = 'started'
        ");
        $stmt->execute([$room_id]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $num_students = count($students);

        // Retrieve the group's total grade similar to leaderboards_mcq.php.
        // This query sums the grades per student and then selects the highest total score among them.
        $grades_stmt = $pdo->prepare("
            SELECT COALESCE(MAX(student_total), 0) AS total_grades
            FROM (
                SELECT SUM(grades) AS student_total
                FROM answers_mcq_collab_tbl
                WHERE room_id = ? AND assessment_id = ?
                GROUP BY submitted_by
            ) t
        ");
        $grades_stmt->execute([$room_id, $assessment_id]);
        $total_grades = $grades_stmt->fetch(PDO::FETCH_ASSOC)['total_grades'] ?? 0;

        // Use the original total possible points without any modifications.
        $adjusted_total_points = $total_possible_points;
        ?>

        <div class="container">
            <div class="room-header">
                <h2>Room ID: <span class="underline"><?php echo htmlspecialchars($room_id); ?></span></h2>
                <a href="leaderboard_mcq.php?room_id=<?php echo urlencode($room_id); ?>&assessment_id=<?php echo urlencode($assessment_id); ?>" class="button-link">
                    <button>Team Leaderboard</button>
                </a>
            </div>

            <h3>Total Grade for the Group: <span class="underline"><?php echo htmlspecialchars($total_grades); ?> / <?php echo htmlspecialchars($adjusted_total_points); ?></span></h3>

            <div class="grid-layout">
                <div class="student-list">
                    <h3>Total Students: <span class="underline"><?php echo $num_students; ?></span></h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($student['username']); ?>
                                        (<?php echo htmlspecialchars($student['student_first_name'] . ' ' . $student['student_last_name']); ?>)
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="chat-history">
                    <h3>Chat History</h3>
                    <div class="chat-history-scroll">
                        <ul id="chat-history-<?php echo htmlspecialchars($room_id); ?>"></ul>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Start polling for this room
            startChatPolling('<?php echo htmlspecialchars($room_id); ?>');

            document.addEventListener("DOMContentLoaded", function () {
                document.querySelector(".go-back-btn").addEventListener("click", function () {
                    window.location.href = this.getAttribute("data-url");
                });
            });
        </script>
    <?php endforeach; ?>
</body>
</html>
