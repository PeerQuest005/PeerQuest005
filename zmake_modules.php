<?php
require 'config.php'; // Include your database connection

// Function to extract content from .docx
if (!function_exists('extractDocxContent')) {
    function extractDocxContent($file_path) {
        $zip = new ZipArchive;
        $content = '';

        if ($zip->open($file_path) === TRUE) {
            // Extract the text content from word/document.xml
            $xml = $zip->getFromName('word/document.xml');
            $dom = new DOMDocument;
            $dom->loadXML($xml);

            // Extract paragraphs as text
            foreach ($dom->getElementsByTagName('p') as $paragraph) {
                $content .= $paragraph->textContent . "\n";
            }

            // Extract images
            $upload_dir = 'uploads/images/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $media_dir = 'word/media/';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (strpos($entry, $media_dir) === 0) {
                    // Save the image to the uploads directory
                    $image_path = $upload_dir . basename($entry);
                    copy("zip://{$file_path}#{$entry}", $image_path);

                    // Add an <img> tag for the image to the content
                    $content .= "<img src='" . $image_path . "' alt='Image from document' />\n";
                }
            }

            $zip->close();
        }

        return $content;
    }
}

// Function to extract content from .doc
if (!function_exists('extractDocContent')) {
    function extractDocContent($file_path) {
        if (file_exists($file_path)) {
            return shell_exec("antiword " . escapeshellarg($file_path)); // Requires antiword installed
        }
        return '';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect form data
    $title = $_POST['title'];
    $file = $_FILES['file'];

    // Validate the uploaded file
    $allowed_extensions = ['doc', 'docx'];
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

    if (!in_array($file_extension, $allowed_extensions)) {
        die("Invalid file type. Only .doc and .docx are allowed.");
    }

    // Save the file to a directory
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_path = $upload_dir . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        die("Failed to upload the file.");
    }

    // Extract content from the .doc file
    $file_content = '';
    if ($file_extension === 'docx') {
        $file_content = extractDocxContent($file_path);
    } else {
        $file_content = extractDocContent($file_path);
    }

    // Insert into the database
    $stmt = $pdo->prepare("INSERT INTO make_modules_fupload_tbl (title, file_name, file_content, uploaded_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$title, $file['name'], $file_content]);

    echo "Module uploaded successfully.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Module</title>
</head>
<body>
    <h2>Upload a Module</h2>
    <form action="make_modules.php" method="POST" enctype="multipart/form-data">
        <label for="title">Module Title:</label>
        <input type="text" id="title" name="title" required>
        <br><br>
        <label for="file">Select .doc File:</label>
        <input type="file" id="file" name="file" accept=".doc,.docx" required>
        <br><br>
        <button type="submit">Upload</button>
    </form>
</body>
</html>
