<?php
require 'auth.php';
require 'config.php';

// Get assessment_id from URL
$assessment_id = $_GET['assessment_id'] ?? null;

if (!$assessment_id) {
    echo "Assessment ID is missing.";
    exit();
}

// Fetch assessment details
$stmt = $pdo->prepare("SELECT * FROM assessment_tbl WHERE assessment_id = ?");
$stmt->execute([$assessment_id]);
$assessment = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$assessment) {
    echo "Assessment not found.";
    exit();
}

// Fetch class_id (assuming it exists in the assessment_tbl)
$class_id = $assessment['class_id'] ?? null; // Ensure class_id exists

if (!$class_id) {
    echo "Class ID is missing.";
    exit();
}

// Customize dialogue message and images based on assessment type
$dialogueMessage = "";
$charaubelleGif = "images/charaubelle/C_eyesmile.webp"; // Default Charaubelle GIF
$assessmentGif = ""; // Placeholder for the assessment-specific GIF

switch (strtolower($assessment['type'])) {
    case 'essay':
        $dialogueMessage = "Hi! Are you ready to take your Essay Assessment? The guide below will walk you through the basics. Once you’re ready, you can click the 'Start Assessment' button. Good luck, and let your ideas flow!";
        $charaubelleGif = "images/charaubelle/C_teacher_eyesmile.webp";  // Writing-specific GIF
        $assessmentGif = "images/tutorials/essay_tutorial.webp";  // Essay GIF
        break;
    case 'multiple choice - individual':
        $dialogueMessage = "Hi! Are you ready to take your Multiple-Choice Assessment? The guide below will walk you through the basics. Once you’re ready, you can click the 'Start Assessment' button. Focus, think, and choose wisely. You've got this!";
        $charaubelleGif = "images/charaubelle/C_teacher_eyesmile.webp";  // Thinking-specific GIF
        $assessmentGif = "images/tutorials/mcq_tutorial.webp";  // MCQ GIF
        break;
    case 'true or false':
        $dialogueMessage = "Hi! Are you ready to take your True or False Assessment? The guide below will walk you through the basics. Once you’re ready, you can click the 'Start Assessment' button. Trust your instincts and stay sharp! Fighting!";
        $charaubelleGif = "images/charaubelle/C_teacher_eyesmile.webp";  // Truth-detecting GIF
        $assessmentGif = "images/tutorials/tf_tutorial.webp";  // True/False GIF
        break;
     case 'recitation': 
        $dialogueMessage = "Let's get ready for Recitation! You will be answering questions in real time, so stay focused and be confident. Once you're ready, click 'Start Assessment' to begin. Speak up, share your thoughts, and do your best!";
        $charaubelleGif = "images/charaubelle/C_teacher_eyesmile.webp";  // Mic or speaking-related GIF
        $assessmentGif = "images/tutorials/recitation_tutorial.webp";  // Recitation GIF
        break;
    default:
        $dialogueMessage = "Teamwork makes the dream work! Are you ready to Collaborate with your peers? The guide below will walk you through the basics. Once you’re ready, you can click the 'Start Assessment' button. Collaborate, share ideas, and make each answer count.";
        $charaubelleGif = "images/charaubelle/C_teacher_eyesmile.webp";  // Default ready GIF
        $assessmentGif = "images/tutorials/general_tutorial.webp";  // Default tutorial GIF
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Charaubelle's Dialogue</title>
    <link rel="stylesheet" href="css/c_dialogue.css">
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 
</head>
<body>
<div class="main-container">

        <!-- Charaubelle and dialogue box aligned in the center -->
        <div class="charaubelle-dialogue-container">
            <img src="<?php echo $charaubelleGif; ?>" alt="Charaubelle" class="charaubelle-image">
            <div class="dialogue-box">
                <p><?php echo htmlspecialchars($dialogueMessage); ?></p>
            </div>
            <button id="skipBtn" class="skip-btn">Skip</button>
        </div>

        <!-- Additional text and assessment GIF -->
        <div class="additional-text">How to take the assessment? (Click the gif to view)</div>

       <!-- Assessment WebP Styling -->
<div class="assessment-webp-container">
    <img src="<?php echo $assessmentGif; ?>" alt="Assessment-tutorial-GIF" class="assessment-webp">
</div>

<!-- Modal for Enlarged GIF -->
<div id="gifModal" class="modal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeGifModal()">&times;</span>
        <img id="modalGif" src="" alt="Enlarged GIF">
    </div>
</div>

        <!-- Start assessment button -->
        <a onclick="location.replace('https://peer-quest.com/take_assessment.php?assessment_id=<?php echo $assessment['assessment_id']; ?>&class_id=<?php echo $class_id; ?>')" class="start-assessment-btn">Start Assessment</a>
    </div>

    <!-- Volume Icon -->
<div class="music-icon-container">
    <img id="volume-icon" src="images/icons/volume_off.webp" alt="Volume Icon" class="music-icon">
</div>

<!-- Background Music -->
<audio id="background-music" loop>
    <source src="audio/charaubelle.mp3" type="audio/mpeg">
</audio>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
    const text = document.querySelector('.dialogue-box p').textContent;
    const dialogueBox = document.querySelector('.dialogue-box p');
    const startAssessmentBtn = document.querySelector('.start-assessment-btn');
    const skipBtn = document.querySelector('.skip-btn');
    let index = 0;
    let typingActive = true;

    dialogueBox.textContent = ''; // Clear initial text

    function typeDialogue() {
        if (index < text.length && typingActive) {
            dialogueBox.textContent += text.charAt(index);
            index++;
            setTimeout(typeDialogue, 50); // Adjust typing speed here
        } else {
            showStartAssessmentButton(); // Automatically show button when finished
        }
    }

    function showAllText() {
        typingActive = false;
        dialogueBox.textContent = text; // Show all text instantly
        showStartAssessmentButton(); // Show start assessment button instantly
        hideSkipButton(); // Hide the skip button after clicking
    }

    function showStartAssessmentButton() {
        startAssessmentBtn.classList.add('show');
    }

    function hideSkipButton() {
        skipBtn.style.display = 'none'; // Hide the skip button
    }

    // Start typing the dialogue initially
    typeDialogue();

    // Skip button event listener
    skipBtn.addEventListener('click', showAllText);

     
         // Music playback logic
         const volumeIcon = document.getElementById('volume-icon');
        const audio = document.getElementById('background-music');
        let isPlaying = false;

        function toggleMusic() {
            if (isPlaying) {
                audio.pause();
                volumeIcon.src = 'images/icons/volume_off.webp'; // Switch to muted icon
            } else {
                audio.play();
                volumeIcon.src = 'images/icons/volume_on.webp'; // Switch to volume on icon
            }
            isPlaying = !isPlaying;
        }

        volumeIcon.addEventListener('click', toggleMusic);
    
    });

    // Function to open the GIF modal
function openGifModal(gifSrc) {
    document.getElementById("modalGif").src = gifSrc;
    document.getElementById("gifModal").style.display = "flex"; // Show modal
}

// Function to close the GIF modal
function closeGifModal() {
    document.getElementById("gifModal").style.display = "none";
}

// Close modal when clicking outside the image
document.getElementById("gifModal").addEventListener("click", function(event) {
    if (event.target === this) {
        closeGifModal();
    }
});

    </script>
</body>
</html>
