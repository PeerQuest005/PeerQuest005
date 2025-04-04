<?php
require 'auth.php';
require 'config.php';

// Check if the user is a student (role 2)
if ($_SESSION['role'] != 2) {
    $_SESSION['error_role'] = 'Access Denied! Students Only.';
    header('Location: ./teacher_dashboard.php');
}
// Check if student_id is set in session
if (!isset($_SESSION['student_id'])) {
    echo "Student ID is missing.";
    exit();
}
$student_id = $_SESSION['student_id'];
$message = '';

// Handle join class request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['join_class'])) {
    $class_code = trim($_POST['class_code']);
    $class_code_lower = strtolower($class_code);

    // Check if the class with the provided code exists
    $stmt = $pdo->prepare("SELECT * FROM class_tbl WHERE LOWER(class_code) = ?");
    $stmt->execute([$class_code_lower]);
    $class = $stmt->fetch();

    if ($class) {
        // Check if the student is already enrolled in this class
        $stmt = $pdo->prepare("SELECT * FROM student_classes WHERE student_id = ? AND class_id = ?");
        $stmt->execute([$student_id, $class['class_id']]);
        $existingEnrollment = $stmt->fetch();

        if (!$existingEnrollment) {
            // Insert the student into student_classes table with class_id and student_id
            $stmt = $pdo->prepare("INSERT INTO student_classes (student_id, class_id) VALUES (?, ?)");
            $stmt->execute([$student_id, $class['class_id']]);
            // echo "<script>window.location.reload();</script>";
            $_SESSION['success_join'] = 'Successfully joined the class:' . '<b> ' . $class['class_subject'] . '</b> (' . $class['class_section'] . ')';
            echo "<script>window.location.href = 'student_dashboard.php';</script>";

            exit();
        } else {
            $message = '<div id="error-message" class="error-message hidden">Error: You already have joined this class.</div><br/>';
        }
    } else {
        $message = '<div id="error-message" class="error-message hidden">Error: Class code not found.</div><br/>';
    }
}

// Fetch all classes the student has joined
$sortOrder = isset($_GET['sort']) && $_GET['sort'] == 'DESC' ? 'DESC' : 'ASC';

$stmt = $pdo->prepare("
    SELECT class_tbl.class_id, class_tbl.class_subject, class_tbl.class_section, class_tbl.class_code 
    FROM class_tbl 
    INNER JOIN student_classes ON class_tbl.class_id = student_classes.class_id 
    WHERE student_classes.student_id = ?
    ORDER BY class_tbl.class_subject $sortOrder
");
$stmt->execute([$student_id]);
$joined_classes = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Fetch all pending assessments across all joined classes
$todo_list = [];
foreach ($joined_classes as $class) {
    $stmt = $pdo->prepare("
        SELECT assessment_id, name, type, time_limit 
        FROM assessment_tbl 
        WHERE class_id = ? AND status = 'Published'
    ");
    $stmt->execute([$class['class_id']]);
    $assessments = $stmt->fetchAll(PDO::FETCH_ASSOC);


    foreach ($assessments as $assessment) {
        // Check if the student has attempted this assessment in any of the attempt tables
        $attempted = false;

        // Check answers_esy_tbl
        $stmt = $pdo->prepare("SELECT Attempt FROM answers_esy_tbl WHERE student_id = ? AND assessment_id = ?");
        $stmt->execute([$student_id, $assessment['assessment_id']]);
        if ($stmt->fetch()) {
            $attempted = true;
        }

        // Check answers_esy_tbl
        $stmt = $pdo->prepare("SELECT Attempt FROM answers_esy_collab_tbl WHERE student_id = ? AND assessment_id = ?");
        $stmt->execute([$student_id, $assessment['assessment_id']]);
        if ($stmt->fetch()) {
            $attempted = true;
        }

        // Check answers_mcq_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT Attempt FROM answers_mcq_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Check answers_tf_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT Attempt FROM answers_tf_tbl WHERE student_id = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Check answers_mcq_collab_tbl
        if (!$attempted) {
            $stmt = $pdo->prepare("SELECT attempt FROM answers_mcq_collab_tbl WHERE submitted_by = ? AND assessment_id = ?");
            $stmt->execute([$student_id, $assessment['assessment_id']]);
            if ($stmt->fetch()) {
                $attempted = true;
            }
        }

        // Add to To-Do List if not attempted
        if (!$attempted) {
            $todo_list[] = [
                'name' => $assessment['name'],
                'type' => $assessment['type'],
                'time_limit' => $assessment['time_limit'],
                'class_subject' => $class['class_subject'],
            ];
        }
    }
}


?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | PeerQuest</title>
    <link rel="stylesheet" href="css/student_dashboard.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">

    <link rel="icon" type="image/webp" href="images/logo/pq_logo.webp">
    <link rel="stylesheet" href="css/carousel.css">

</head>

<body>
    <!-- SIDEBAR BEGINS HERE -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="images/logo/pq_logo.webp" class="logo" alt="PeerQuest Logo">
            <img src="images/logo/pq_white_logo_txt.webp" class="logo-text" alt="PeerQuest Logo Text">
        </div>

        <ul class="nav-links">
            <li><a href="student_dashboard.php"><img src="images/Home_white_icon.png" alt="Dashboard">
                    <span>Dashboard</span></a></li>
            <li><a href="achievements.php?student_id=<?php echo $student_id; ?>"><img
                        src="images/achievements_white_icon.png" alt="Achievements"> <span>Achievements</span></a></li>
            <li><a href="quest.php?student_id=<?php echo $student_id; ?>"><img src="images/myquest_white_icon.png"
                        alt="Achievements"> <span>My Quests</span></a></li>
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
        <?php
        if (isset($_SESSION['error_role'])) {
            echo '<div id="error-message" class="error-message hidden">' . $_SESSION['error_role'] . '</div>';
            unset($_SESSION['error_role']); // Clear the error after displaying
        }
        ?>
        <div class="top-bar">

            <h1 class="dashboard-title">Student's Dashboard</h1>
            <div class="date-picker-wrapper">
                <p id="currentDate" class="hover-date"></p>
                <div class="calendar-overlay">
                    <div id="flatpickrCalendar"></div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR ENDS HERE -->


        <div class="welcome-section">
            <a href="loading.php?redirect=Charaubelle.php" class="owl-container">
                <img src="images/charaubelle/C_eyesmile.webp" alt="Welcome Owl" class="welcome-owl">
                <span class="tooltip-text">click me to know more</span>
            </a>

            <div class="welcome-box">
                <p id="welcome-text" class="welcome-text"></p>
                <p id="welcome-subtext" class="welcome-subtext"></p>
            </div>
        </div>
        <!-- Indicator Dots -->
        <div class="dots-container">
            <span class="dot active" onclick="setSlide(0)"></span>
            <span class="dot" onclick="setSlide(1)"></span>
            <span class="dot" onclick="setSlide(2)"></span>
            <span class="dot" onclick="setSlide(3)"></span>
        </div>

        <div class="dashboard-header">
        <h3 class="body-title"><img src="images/icons/assessment_icon_dark.webp" alt="How to play?"
        class="button-icon">Assessment Types</h3>
        </div>
        <div class="image-grid">
    <div class="card">
        <img src="images/dashboard_card/trueorfalse.webp" alt="True or False" data-tutorial="images/tutorials/tf_tutorial.webp">
        <div class="card-text">True or False</div>
    </div>
    <div class="card">
        <img src="images/dashboard_card/mcq.webp" alt="Multiple Choices" data-tutorial="images/tutorials/mcq_tutorial.webp">
        <div class="card-text">Multiple Choices</div>
    </div>
    <div class="card">
        <img src="images/dashboard_card/mcq-collab.webp" alt="Multiple Choices Collab" data-tutorial="images/tutorials/mcq_tutorial.webp">
        <div class="card-text">Multiple Choices Collab</div>
    </div>
    <div class="card">
        <img src="images/dashboard_card/essay.webp" alt="Essay" data-tutorial="images/tutorials/essay_tutorial.webp">
        <div class="card-text">Essay</div>
    </div>
    <div class="card">
        <img src="images/dashboard_card/essay-collab.webp" alt="Essay Collab" data-tutorial="images/tutorials/general_tutorial.webp">
        <div class="card-text">Essay Collab</div>
    </div>

        <!-- 🔹 MODAL POPUP -->
        <div id="popupModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" id="closeModal">&times;</span>
            <img src="" alt="Tutorial" id="tutorialImage">
        </div>
        </div>
</div>


        


        <div class="dashboard-header">
            <h3 class="body-title"><img src="images/icons/myclasses_dark_icon.webp" alt="My Class Icon"
                    class="button-icon">My Classes</h3>

            <!-- Success message container (hidden initially) -->
            <div id="success-message-container" style="display: none; text-align: center; margin-top: 15px;"></div>

            <!-- Create Class Button -->
            <button onclick="openJoinClassModal()" class="create-class-btn">
                Join a Class
                <img src="images/icons/createclass_icon.webp" alt="Create Class Icon" class="button-icon">
            </button>
        </div>
        <div class="sort-container">
            <label for="sortSubject">Sort by Subject:</label>
            <select id="sortSubject" onchange="sortClasses()">
                <option value="ASC" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'ASC') ? 'selected' : ''; ?>>A-Z
                </option>
                <option value="DESC" <?php echo (isset($_GET['sort']) && $_GET['sort'] == 'DESC') ? 'selected' : ''; ?>>
                    Z-A</option>
            </select>
        </div>

        <!-- Display success/error message -->
        <?php
        if (isset($_SESSION['success_join'])) {
            echo '<div id="success-message" class="success-message hidden">' . $_SESSION['success_join'] . '</div><br/>';
            unset($_SESSION['success_join']); // Clear the error after displaying
        }
        ?>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>


        <div class="popup-overlay" id="joinClassOverlay"></div>
        <div id="joinClassModal" class="popup">
            <div class="popup-content">
                <span class="close-popup" onclick="closeJoinClassModal()">&times;</span>
                <h3>Join a Class</h3>


                <form method="POST" action="student_dashboard.php">
                    <div class="input-group">
                        <label for="class_code">Class Code:</label>
                        <input type="text" id="class_code" name="class_code" placeholder="Enter Class Code" required>
                    </div>

                    <div class="popup-actions">
                        <button type="button" class="btn-cancel" onclick="closeJoinClassModal()">Cancel</button>
                        <button type="submit" name="join_class" class="btn-submit">Join Class</button>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $index = 0; // Initialize index for colors
        $headerColors = ['#357AC6'];
        $colorCount = count($headerColors);

        // Array of images
        $classImages = [
            'images/2.png',
            'images/3.png',
            'images/4.png',
            'images/5.png',
            'images/6.png',
            'images/7.png'
           
        ];
        $imageCount = count($classImages);
        ?>

        <div class="class-container">
            <?php if (!empty($joined_classes)): ?>
                <?php foreach ($joined_classes as $index => $class): ?>
                    <?php
                    // Fetch teacher details for the current class
                    $teacherStmt = $pdo->prepare("
                    SELECT t.username AS teacher_username, t.teacher_last_name AS teacher_last_name
                    FROM teacher_tbl t
                    INNER JOIN class_tbl c ON t.teacher_id = c.teacher_id
                    WHERE c.class_id = ?
                ");
                    $teacherStmt->execute([$class['class_id']]);
                    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="class-card">

                        <!-- Class Header with Dynamic Colors -->
                        <div class="class-header" style="background: <?php echo $headerColors[$index % $colorCount]; ?>">
                            <span class="class-title"><?php echo htmlspecialchars($class['class_subject']); ?></span>
                        </div>

                        <div class="class-body">
                            <!-- Dynamic Class Image -->
                            <img src="<?php echo $classImages[$index % $imageCount]; ?>" alt="Class Image" class="class-image">
                            <div class="class-content">
                                <div class="class-info">
                                    <p>Section: <?php echo htmlspecialchars($class['class_section']); ?></p>
                                    <div class="class-code-container">
                                        <p>Teacher:
                                            <?php echo htmlspecialchars($teacher['teacher_username']); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="class-actions">
                                    <a href="view_class_student.php?class_id=<?php echo $class['class_id']; ?>"
                                        class="btn-action">View Class</a>
                                </div>

                            </div>
                        </div>
                    </div>
                    <?php $index++; ?> <!-- Increase index for next iteration -->
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-classes-container">
                    <p class="no-classes">You have not joined any classes yet.</p>
                </div>
            <?php endif; ?>
        </div>



        <h3 class="quest-title">
            <img src="images/icons/myquest_icon.webp" alt="My Class Icon" class="button-icon">My Quests
        </h3>

        <div class="quest-container">
            <?php if (!empty($todo_list)): ?>
                <?php $display_limit = 3; // Limit to 3 assessments ?>
                <?php $limited_list = array_slice($todo_list, 0, $display_limit); ?>

                <?php foreach ($limited_list as $todo): ?>
                    <div class="quest-card">
                        <div class="quest-details">
                            <p class="quest-assessment">
                                <strong><?php echo htmlspecialchars($todo['type']); ?>:</strong>
                                <?php echo htmlspecialchars($todo['name']); ?>
                            </p>
                            <p class="quest-class">
                                <strong>Class:</strong>
                                <?php echo htmlspecialchars($todo['class_subject']); ?>
                            </p>
                        </div>
                        <div class="quest-timer">
                            <span><strong> Time Limit: </strong> <?php echo htmlspecialchars($todo['time_limit']); ?>
                                minutes</span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (count($todo_list) > $display_limit): ?>
                    <div style="display: flex; justify-content: center; margin-top: 20px;">
                        <!-- <button onclick="window.location.href='quest.php'" class="btn-action">View More</button> -->
                         <h3>-- Click My Quest to View More --</h3>
                    </div>

                <?php endif; ?>

            <?php else: ?>
                <p>No pending assessments at this time.</p>
            <?php endif; ?>
        </div>




        <script>
            function openJoinClassModal() {
                document.getElementById("joinClassModal").style.display = "block";
                document.getElementById("joinClassOverlay").style.display = "block";
            }

            function closeJoinClassModal() {
                document.getElementById("joinClassModal").style.display = "none";
                document.getElementById("joinClassOverlay").style.display = "none";
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

            //calendar
            document.addEventListener("DOMContentLoaded", function () {
                const currentDate = new Date();
                const options = {
                    weekday: "long",
                    month: "long",
                    day: "numeric"
                };
                document.getElementById("currentDate").textContent = currentDate.toLocaleDateString("en-PH", options);

                // Initialize Flatpickr calendar
                flatpickr("#flatpickrCalendar", {
                    inline: true,
                    onChange: function (selectedDates) {
                        const formattedDate = selectedDates[0].toLocaleDateString("en-PH", options);
                        document.getElementById("currentDate").textContent = formattedDate;
                    },
                });
            });

            // Join Class Modal Logic


            // Get the student's username from PHP
            const studentUsername = "<?php echo htmlspecialchars($_SESSION['username']); ?>";

            // Text Data for Slides (Username Included)
            const slides = [{
                text: `Welcome to your dashboard, <strong>${studentUsername}</strong>!`,
                subtext: "Ready for some learning and quest?"
            },
            {
                text: "Always check your Quests",
                subtext: "See what's pending and stay ahead of your assessments!"
            },
            {
                text: "Just a tip",
                subtext: "Remember to login everyday for your streak!"
            },
            {
                text: "You’re doing great!",
                subtext: "I'm proud of you for showing up and choosing to learn."
            }
            ];

            let currentIndex = 0;
            const textElement = document.getElementById("welcome-text");
            const subtextElement = document.getElementById("welcome-subtext");
            const dots = document.querySelectorAll(".dot");

            function updateSlide() {
                // Add fade-out effect
                textElement.classList.add("fade-out");
                subtextElement.classList.add("fade-out");

                setTimeout(() => {
                    // Change text after fade-out
                    textElement.innerHTML = slides[currentIndex].text;
                    subtextElement.textContent = slides[currentIndex].subtext;

                    // Remove fade-out and apply fade-in
                    textElement.classList.remove("fade-out");
                    subtextElement.classList.remove("fade-out");
                    textElement.classList.add("fade-in");
                    subtextElement.classList.add("fade-in");

                    // Update active dot
                    dots.forEach((dot, index) => dot.classList.toggle("active", index === currentIndex));
                }, 300); // Delay change until fade-out completes
            }

            // Function to manually set the slide
            function setSlide(index) {
                currentIndex = index;
                updateSlide();
                restartAutoSlide();
            }

            // Auto-slide function
            function nextSlide() {
                currentIndex = (currentIndex + 1) % slides.length;
                updateSlide();
            }

            // Auto-slide every 5 seconds
            let slideInterval = setInterval(nextSlide, 5000);

            // Restart auto-slide when user clicks a dot
            function restartAutoSlide() {
                clearInterval(slideInterval);
                slideInterval = setInterval(nextSlide, 5000);
            }

            // Initialize the first slide
            updateSlide();


            //sorting
            function sortClasses() {
                let sortValue = document.getElementById("sortSubject").value;
                window.location.href = "student_dashboard.php?sort=" + sortValue;
            }

        
        // ======================
        // MODAL FUNCTIONALITY
        // ======================
        document.addEventListener("DOMContentLoaded", function () {
    const popupModal = document.getElementById("popupModal");
    const closeModal = document.getElementById("closeModal");
    const tutorialImage = document.getElementById("tutorialImage");

    // Ensure all tutorial images have event listeners
    document.querySelectorAll(".card img").forEach((img) => {
        img.addEventListener("click", function () {
            // Retrieve the correct tutorial path from data-tutorial
            const tutorialPath = img.getAttribute("data-tutorial");

            // Ensure the image source updates
            if (tutorialPath) {
                tutorialImage.src = tutorialPath;
                popupModal.style.display = "block";
            }
        });
    });

    // Close the modal when clicking the close button
    closeModal.addEventListener("click", function () {
        popupModal.style.display = "none";
    });

    // Close modal when clicking outside the modal content
    window.addEventListener("click", function (e) {
        if (e.target === popupModal) {
            popupModal.style.display = "none";
        }
    });
});

        </script>

</body>

</html>