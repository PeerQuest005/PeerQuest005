<?php
require 'auth.php'; // Ensure student authentication
require 'config.php';

if ($_SESSION['role'] !== 2) { // Assuming 2 is the role ID for students
    header("Location: login.php");
    exit();
}

// Increment ach_modules_read when View Content is accessed
if (isset($_GET['module_id'])) {
    $module_id = intval($_GET['module_id']);
    $student_id = intval($_SESSION['student_id']);

    // Increment the count in the database
    $updateStmt = $pdo->prepare("UPDATE student_tbl SET ach_modules_read = ach_modules_read + 1 WHERE student_id = ?");
    $updateStmt->execute([$student_id]);

    // Redirect to module view page
    header("Location: student_view_module.php?module_id=$module_id");
    exit();
}

// Fetch the student's classes and their corresponding modules
$stmt = $pdo->prepare("SELECT 
    m.module_id, 
    m.title, 
    m.content, 
    m.file_name, 
    m.file_path, 
    m.created_at, 
    m.status, 
    c.class_section, 
    c.class_subject
FROM 
    student_classes sc
INNER JOIN 
    class_tbl c ON sc.class_id = c.class_id
INNER JOIN 
    modules_tbl m ON c.class_id = m.class_id
WHERE 
    sc.student_id = ? 
AND 
    m.status = 'Published'");
$stmt->execute([$_SESSION['student_id']]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Modules</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2>Available Modules</h2>
    <?php if (empty($modules)): ?>
        <p class="text-center text-muted">No modules available at the moment.</p>
    <?php else: ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Module Title</th>
                    <th>Class Section</th>
                    <th>Class Subject</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($modules as $module): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($module['title']); ?></td>
                        <td><?php echo htmlspecialchars($module['class_section']); ?></td>
                        <td><?php echo htmlspecialchars($module['class_subject']); ?></td>
                        <td><?php echo htmlspecialchars($module['created_at']); ?></td>
                        <td>
                            <!-- View Content Button -->
                            <?php if (!empty($module['content'])): ?>
                                <a href="?module_id=<?php echo $module['module_id']; ?>" class="btn btn-primary btn-sm">
                                    View Content
                                </a>
                            <?php endif; ?>
                            <!-- Download File Button -->
                            <?php if (!empty($module['file_path'])): ?>
                                <a href="<?php echo htmlspecialchars($module['file_path']); ?>" class="btn btn-success btn-sm" download>
                                    Download File
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Modal for Viewing Content -->
                    <div class="modal fade" id="contentModal<?php echo $module['module_id']; ?>" tabindex="-1" aria-labelledby="contentModalLabel<?php echo $module['module_id']; ?>" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="contentModalLabel<?php echo $module['module_id']; ?>">
                                        <?php echo htmlspecialchars($module['title']); ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p><?php echo nl2br(htmlspecialchars($module['content'])); ?></p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <a href="student_dashboard.php" class="btn btn-secondary mt-3 float-end">Back to Dashboard</a>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>