<?php
include "config/db.php";

echo "Starting Database Integration...\n";

// Disable FK checks to allow dropping/creating parent tables
$conn->query("SET FOREIGN_KEY_CHECKS=0");

// PRE-CLEANUP: Drop our "new" tables to avoid constraints blocking recreation of parent tables
$newTables = ['student_test_answers', 'student_test_attempts', 'test_answers', 'test_questions', 'course_tests'];
foreach ($newTables as $tbl) {
    $conn->query("DROP TABLE IF EXISTS `$tbl`");
    echo "Dropped clean-up table $tbl\n";
}

// 1. Read and Execute online_study.sql (Base Schema & Data)
$sqlFile = "phpMyAdmin/online_study.sql";
if (file_exists($sqlFile)) {
    echo "Importing $sqlFile...\n";
    $sqlContent = file_get_contents($sqlFile);

    // Split into queries (simplistic split by delimiter lines or semicolons)
    // phpMyAdmin dumps usually have ; at end of lines.
    // We'll iterate through lines to handle comments and build queries.

    $queries = [];
    $lines = explode("\n", $sqlContent);
    $currentQuery = "";

    foreach ($lines as $line) {
        $trimLine = trim($line);
        if ($trimLine === "" || strpos($trimLine, "--") === 0 || strpos($trimLine, "/*") === 0) {
            continue;
        }

        $currentQuery .= $line . "\n";
        if (substr(trim($line), -1) === ";") {
            $queries[] = $currentQuery;
            $currentQuery = "";
        }
    }

    foreach ($queries as $sql) {
        // Detect CREATE TABLE to drop it first (for robustness)
        if (preg_match('/CREATE TABLE `?(\w+)`?/', $sql, $matches)) {
            $tableName = $matches[1];
            $conn->query("DROP TABLE IF EXISTS `$tableName`");
            echo "Dropped table $tableName\n";
        }

        if (!$conn->query($sql)) {
            // Ignore "Table already exists" if we failed to drop or other non-critical errors?
            // But usually we want to know.
            echo "Error executing query: " . substr($sql, 0, 50) . "... -> " . $conn->error . "\n";
        }
    }
    echo "Base DB Imported.\n";
} else {
    echo "Error: $sqlFile not found.\n";
}

// 2. Run db_update.php (New Tables)
// We can just include it, but we need to suppress its output or just run its queries.
// Easier to just run the queries again here or include the file.
// Let's rely on db_update.php content.
echo "Running db_update.php extensions...\n";
include "db_update.php";

// Re-enable FK checks
$conn->query("SET FOREIGN_KEY_CHECKS=1");

echo "Integration Complete.\n";
?>