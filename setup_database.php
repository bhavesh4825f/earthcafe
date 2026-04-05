<?php
include 'DataBaseConnection.php';

// Create service_requests table if it doesn't exist
$create_table_sql = "CREATE TABLE IF NOT EXISTS service_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    description LONGTEXT,
    service_fee DECIMAL(10, 2) DEFAULT 0,
    document_fee DECIMAL(10, 2) DEFAULT 0,
    consultancy_fee DECIMAL(10, 2) DEFAULT 0,
    total_fee DECIMAL(10, 2) DEFAULT 0,
    assigned_to INT,
    status VARCHAR(50) DEFAULT 'pending',
    employee_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (assigned_to) REFERENCES employees(id)
)";

if ($conn->query($create_table_sql)) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Database updated successfully! service_requests table created.'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error: ' . $conn->error
    ]);
}

$conn->close();
?>
