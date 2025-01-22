<?php
require 'auth.php';
require 'config.php';

// Check if editing or adding a new item
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update'])) {
        // Update the existing item
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $stmt = $pdo->prepare("UPDATE items SET name = ?, description = ? WHERE id = ?");
        $stmt->execute([$name, $description, $id]);
        header("Location: index.php");
        exit();
    } elseif (isset($_POST['name']) && !isset($_POST['id'])) {
        // Create a new item only if no ID is provided
        $name = $_POST['name'];
        $description = $_POST['description'];
        $stmt = $pdo->prepare("INSERT INTO items (name, description) VALUES (?, ?)");
        $stmt->execute([$name, $description]);
    }
}

// Read
$items = $pdo->query("SELECT * FROM items")->fetchAll(PDO::FETCH_ASSOC);

// Load item to edit
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editItem = $stmt->fetch();
}

// Delete
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("Location: index.php");
}
?>


<h2>CRUD System</h2>
<a href="logout.php">Logout</a>


<?php if (isset($_SESSION['role']) && $_SESSION['role'] == 1): ?>
    <!-- Link to Teacher's Dashboard for users with role 1 -->
    <a href="teacher_dashboard.php">Go to Teacher Dashboard</a>


<?php elseif (isset($_SESSION['role']) && $_SESSION['role'] == 2): ?>
    <!-- Link to Student's Dashboard for users with role 2 -->
    <a href="student_dashboard.php">Go to Student Dashboard</a>

<?php else: ?>
    <!-- Redirect to login page if role is not set or is invalid -->
    <script>
        window.location.href = "login.php";
    </script>
<?php endif; ?>



<!-- Form for Adding or Editing Items -->
<form method="post" action="index.php">
    <?php if (isset($editItem)): ?>
        <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
    <?php endif; ?>
    <input type="text" name="name" placeholder="Item Name" value="<?php echo isset($editItem) ? htmlspecialchars($editItem['name']) : ''; ?>" required>
    <textarea name="description" placeholder="Description"><?php echo isset($editItem) ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
    
    <?php if (isset($editItem)): ?>
        <button type="submit" name="update">Update Item</button>
    <?php else: ?>
        <button type="submit">Add Item</button>
    <?php endif; ?>
</form>

<ul>
    <?php foreach ($items as $item): ?>
        <li>
            <?php echo htmlspecialchars($item['name']); ?> - <?php echo htmlspecialchars($item['description']); ?>
            <a href="index.php?edit=<?php echo $item['id']; ?>">Edit</a>
            <a href="index.php?delete=<?php echo $item['id']; ?>">Delete</a>
        </li>
    <?php endforeach; ?>
</ul>
