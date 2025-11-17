<?php
require_once 'config/database.php';

try {
    // Read and execute the SQL setup file
    $sql = file_get_contents('setup_complete_database.sql');
    $statements = explode(';', $sql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "Database initialized successfully!";
} catch (Exception $e) {
    echo "Error initializing database: " . $e->getMessage();
}
?>