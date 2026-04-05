<?php
include 'DataBaseConnection.php';

header('Content-Type: application/json');

try {
    // Add phone column to employees table if it doesn't exist
    $alter_sql = "ALTER TABLE employees ADD COLUMN phone VARCHAR(20) DEFAULT NULL";
    
    if ($conn->query($alter_sql)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Phone column added to employees table successfully!'
        ]);
    } else {
        // Column might already exist, check error
        if (strpos($conn->error, 'Duplicate column') !== false) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Phone column already exists in employees table.'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error: ' . $conn->error
            ]);
        }
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Exception: ' . $e->getMessage()
    ]);
}

$conn->close();
?>
