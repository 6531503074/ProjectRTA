<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    die("Unauthorized");
}

$teacher_id = $_SESSION['user']['id'];
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';

if ($course_id <= 0) {
    die("Invalid Course ID");
}

// Check ownership
$check = $conn->prepare("SELECT title FROM courses WHERE id = ?");
$check->bind_param("i", $course_id);
$check->execute();
$course_res = $check->get_result();

if ($course_res->num_rows === 0) {
    die("Access Denied");
}

$course = $course_res->fetch_assoc();
$course_title = $course['title'];

// Get Students
$students_sql = "
    SELECT u.id, u.name, u.email 
    FROM course_students cs 
    JOIN users u ON cs.student_id = u.id 
    WHERE cs.course_id = ? 
    ORDER BY u.name ASC
";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("i", $course_id);
$stmt->execute();
$students_res = $stmt->get_result();

$students = [];
while ($row = $students_res->fetch_assoc()) {
    $students[$row['id']] = [
        'name' => $row['name'],
        'email' => $row['email'],
        'test_scores' => [] // Map: test_id -> score
    ];
}

// Get All Tests for this Course (Filtered by type if provided)
$test_sql = "SELECT id, test_type, title FROM course_tests WHERE course_id = ?";
$params = [$course_id];
$types = "i";

if ($type_filter === 'pre' || $type_filter === 'post') {
    $test_sql .= " AND test_type = ?";
    $params[] = $type_filter;
    $types .= "s";
}

$test_sql .= " ORDER BY test_type DESC, id ASC"; // pre then post alphabetically? 'pre' > 'post'. 

$t_stmt = $conn->prepare($test_sql);
$t_stmt->bind_param($types, ...$params);
$t_stmt->execute();
$tests_res = $t_stmt->get_result();

$tests_to_export = [];
$counters = ['pre' => 0, 'post' => 0];

while ($t = $tests_res->fetch_assoc()) {
    $type = $t['test_type'];
    $counters[$type]++;
    // If filtering by type, we might still want sequence numbering or just use sequence numbering based on ALL tests of that type?
    // Let's use sequence relative to TOTAL tests of that type for consistency.
    
    // To get the correct sequence when filtering, we need to know the index among ALL tests of that type
    $seq_stmt = $conn->prepare("SELECT COUNT(*) as count FROM course_tests WHERE course_id = ? AND test_type = ? AND id <= ?");
    $seq_stmt->bind_param("isi", $course_id, $type, $t['id']);
    $seq_stmt->execute();
    $seq_num = $seq_stmt->get_result()->fetch_assoc()['count'];

    $label = !empty($t['title']) ? $t['title'] : (($type === 'pre' ? 'Pre-test' : 'Post-test') . ' ชุดที่ ' . $seq_num);
    $tests_to_export[] = [
        'id' => $t['id'],
        'label' => $label
    ];
}

// Get Scores for each test
foreach ($tests_to_export as $test) {
    $score_sql = "
        SELECT student_id, MAX(score) as max_score 
        FROM student_test_attempts 
        WHERE test_id = ? 
        GROUP BY student_id
    ";
    $p_stmt = $conn->prepare($score_sql);
    $p_stmt->bind_param("i", $test['id']);
    $p_stmt->execute();
    $res = $p_stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if (isset($students[$r['student_id']])) {
            $students[$r['student_id']]['test_scores'][$test['id']] = $r['max_score'];
        }
    }
}

// Generate CSV
$type_suffix = "";
if ($type_filter === 'pre') $type_suffix = "_Pre";
if ($type_filter === 'post') $type_suffix = "_Post";

$filename = "scores" . $type_suffix . "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $course_title) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Headers
$headers = ['ชื่อ-นามสกุล', 'อีเมล'];
foreach ($tests_to_export as $test) {
    $headers[] = $test['label'];
}
fputcsv($output, $headers);

// Rows
foreach ($students as $s) {
    $row = [$s['name'], $s['email']];
    foreach ($tests_to_export as $test) {
        $row[] = isset($s['test_scores'][$test['id']]) ? $s['test_scores'][$test['id']] : 'N/A';
    }
    fputcsv($output, $row);
}

fclose($output);
exit();
