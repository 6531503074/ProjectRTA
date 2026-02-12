<?php
include 'config/db.php';

echo "Updating database for multiple tests...<br>";

// Drop the unique index if it exists
try {
    // Check if index exists first
    $check = $conn->query("SHOW INDEX FROM course_tests WHERE Key_name = 'unique_course_test'");
    if ($check->num_rows > 0) {
        $sql = "ALTER TABLE course_tests DROP INDEX unique_course_test";
        if ($conn->query($sql) === TRUE) {
            echo "Successfully dropped unique index 'unique_course_test'.<br>";
        } else {
            echo "Error dropping index: " . $conn->error . "<br>";
        }
    } else {
        echo "Index 'unique_course_test' does not exist. Skipping.<br>";
    }
    
    echo "Database update completed.";
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
}
?>
