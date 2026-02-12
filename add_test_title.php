<?php
include 'config/db.php';

echo "Adding title column to course_tests...<br>";

try {
    // Check if column exists first
    $check = $conn->query("SHOW COLUMNS FROM course_tests LIKE 'title'");
    if ($check->num_rows === 0) {
        $sql = "ALTER TABLE course_tests ADD COLUMN title VARCHAR(255) NULL AFTER test_type";
        if ($conn->query($sql) === TRUE) {
            echo "Successfully added 'title' column.<br>";
        } else {
            echo "Error adding column: " . $conn->error . "<br>";
        }
    } else {
        echo "Column 'title' already exists. Skipping.<br>";
    }
    
    echo "Database update completed.";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
