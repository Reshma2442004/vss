<?php
// Database connection check and error handling
function checkDatabaseConnection($pdo) {
    try {
        $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return false;
    }
}

function executeQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query execution failed: " . $e->getMessage());
        return false;
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validateRequired($fields) {
    $errors = [];
    foreach ($fields as $field => $value) {
        if (empty($value)) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required";
        }
    }
    return $errors;
}

function showAlert($message, $type = 'info') {
    return "<div class='alert alert-{$type} alert-custom alert-dismissible fade show' role='alert'>
                <i class='fas fa-info-circle me-2'></i>{$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}
?>