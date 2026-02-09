<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'teacher') {
    die("Unauthorized");
}

$teacher_id = $_SESSION['user']['id'];
$course_id = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

if ($course_id <= 0) {
    die("Invalid Course ID");
}

// Check ownership
$check = $conn->prepare("SELECT title FROM courses WHERE id = ? AND teacher_id = ?");
$check->bind_param("ii", $course_id, $teacher_id);
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
        'pre' => 'N/A',
        'post' => 'N/A'
    ];
}

// Get Test IDs
$test_sql = "SELECT id, test_type FROM course_tests WHERE course_id = ?";
$t_stmt = $conn->prepare($test_sql);
$t_stmt->bind_param("i", $course_id);
$t_stmt->execute();
$tests_res = $t_stmt->get_result();

$pre_test_id = 0;
$post_test_id = 0;

while ($t = $tests_res->fetch_assoc()) {
    if ($t['test_type'] === 'pre') $pre_test_id = $t['id'];
    if ($t['test_type'] === 'post') $post_test_id = $t['id'];
}

// Get Scores
// We get the latest score if there are multiple attempts (though usually 1)
// Or highest? Let's assume latest for now or just max. Max is safer for grading.
if ($pre_test_id > 0) {
    $pre_sql = "
        SELECT student_id, MAX(score) as max_score 
        FROM student_test_attempts 
        WHERE test_id = ? 
        GROUP BY student_id
    ";
    $p_stmt = $conn->prepare($pre_sql);
    $p_stmt->bind_param("i", $pre_test_id);
    $p_stmt->execute();
    $res = $p_stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if (isset($students[$r['student_id']])) {
            $students[$r['student_id']]['pre'] = $r['max_score'];
        }
    }
}

if ($post_test_id > 0) {
    $post_sql = "
        SELECT student_id, MAX(score) as max_score 
        FROM student_test_attempts 
        WHERE test_id = ? 
        GROUP BY student_id
    ";
    $p_stmt = $conn->prepare($post_sql);
    $p_stmt->bind_param("i", $post_test_id);
    $p_stmt->execute();
    $res = $p_stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        if (isset($students[$r['student_id']])) {
            $students[$r['student_id']]['post'] = $r['max_score'];
        }
    }
}

// Generate CSV
$filename = "scores_" . preg_replace('/[^a-zA-Z0-9]/', '_', $course_title) . "_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
fputcsv($output, ['Student Name', 'Email', 'Pre-test Score', 'Post-test Score']);

foreach ($students as $s) {
    fputcsv($output, [$s['name'], $s['email'], $s['pre'], $s['post']]);
}

fclose($output);
exit();
