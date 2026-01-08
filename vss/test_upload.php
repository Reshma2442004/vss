<?php
// Simple test to check upload functionality
echo "<h3>Upload Test</h3>";

if ($_POST && isset($_FILES['test_file'])) {
    echo "<h4>Upload Attempt Detected</h4>";
    echo "<pre>";
    echo "POST data: " . print_r($_POST, true) . "\n";
    echo "FILES data: " . print_r($_FILES, true) . "\n";
    echo "</pre>";
    
    $file = $_FILES['test_file'];
    echo "<p>File error code: " . $file['error'] . "</p>";
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        echo "<p style='color: green;'>✅ File uploaded successfully!</p>";
        echo "<p>File name: " . $file['name'] . "</p>";
        echo "<p>File size: " . $file['size'] . " bytes</p>";
        echo "<p>Temp file: " . $file['tmp_name'] . "</p>";
    } else {
        echo "<p style='color: red;'>❌ Upload error: " . $file['error'] . "</p>";
    }
} else {
    echo "<p>No upload detected</p>";
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file" accept=".csv" required>
    <button type="submit">Test Upload</button>
</form>