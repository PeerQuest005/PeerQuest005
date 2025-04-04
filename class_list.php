<?php 
// Include database configuration 
require 'config.php'; 
 
// Fetch class data from the database 
try { 
    $stmt = $pdo->query("SELECT * FROM `class_tbl`"); 
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC); 
} catch (PDOException $e) { 
    die("Error fetching class data: " . $e->getMessage()); 
} 
?> 
 
<!DOCTYPE html> 
<html lang="en"> 
<head> 
    <meta charset="UTF-8"> 
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>Class List</title> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"> 
    <style> 
        .class-container { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 20px; 
            justify-content: center; 
        } 
        .class-card { 
            background-color: black; 
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            width: 200px; 
            text-align: center; 
        } 
        .icon-buttons { 
            display: flex; 
            justify-content: space-between; 
        } 
    </style> 
<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

<link rel="icon" type="image/webp" href="images/logo/pq_logo.webp"> 

</head> 
<body> 
    <div class="container my-5"> 
        <h1 class="text-center mb-4">Class List</h1> 
        <div class="class-container"> 
            <?php foreach ($classes as $class): ?> 
                <div class="class-card"> 
                    <div class="icon-buttons"> 
                        <a href="edit_class.php?id=<?php echo $class['id']; ?>" class="text-white"> 
                            <ion-icon name="create-outline"></ion-icon> 
                        </a> 
                        <a href="delete_class.php?id=<?php echo $class['id']; ?>" class="text-white"> 
                            <ion-icon name="trash-outline"></ion-icon> 
                        </a> 
                    </div> 
                    <h5><?php echo htmlspecialchars($class['section_name']); ?></h5> 
                    <p><?php echo htmlspecialchars($class['subject']); ?></p> 
                    <a href="view_assessment.php?id=<?php echo $class['id']; ?>" class="btn btn-light">Assessment</a> 
                    <a href="view_class_list.php?id=<?php echo $class['id']; ?>" class="btn btn-light">Class List</a> 
                </div> 
            <?php endforeach; ?> 
        </div> 
    </div> 
 
    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script> 
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script> 
</body> 
</html>