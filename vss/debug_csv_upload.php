<?php
// Debug CSV upload to see what's happening with room_no
session_start();
require_once '../config/database.php';

if ($_POST && isset($_FILES['debug_file'])) {
    $file = $_FILES['debug_file'];
    
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        echo "<h3>CSV Debug Output:</h3>";
        $row = 0;
        while (($data = fgetcsv($handle)) !== FALSE && $row < 5) {
            echo "<p>Row $row: ";
            for ($i = 0; $i < count($data); $i++) {
                echo "[$i]='" . htmlspecialchars($data[$i]) . "' ";
            }
            echo "</p>";
            
            if ($row > 0) {
                $room_no = trim($data[5] ?? '');
                echo "<p>Room No for this row: '" . htmlspecialchars($room_no) . "' (length: " . strlen($room_no) . ")</p>";
            }
            $row++;
        }
        fclose($handle);
    }
}
?>

<form method="POST" enctype="multipart/form-data">
    <input type="file" name="debug_file" accept=".csv" required>
    <button type="submit">Debug CSV</button>
</form>