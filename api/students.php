<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'GET') {
    if ($action === 'profile' && $_SESSION['role'] === 'student') {
        // Get student profile
        $student_id = $_SESSION['profile_id'];
        
        $sql = "SELECT s.*, u.full_name, u.email, u.status 
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            jsonResponse(true, 'Profile retrieved', $result->fetch_assoc());
        } else {
            jsonResponse(false, 'Student not found');
        }
    } elseif ($_SESSION['role'] === 'admin') {
        // Get all students
        $sql = "SELECT s.*, u.full_name, u.email, u.status,
                r.room_number, h.name as hostel_name
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                LEFT JOIN allocations a ON s.id = a.student_id AND a.status = 'active'
                LEFT JOIN rooms r ON a.room_id = r.id
                LEFT JOIN hostels h ON r.hostel_id = h.id
                ORDER BY s.id DESC";
        
        $result = $conn->query($sql);
        $students = $result->fetch_all(MYSQLI_ASSOC);
        
        jsonResponse(true, 'Students retrieved', $students);
    }
} elseif ($method === 'POST') {
    if ($_SESSION['role'] === 'admin') {
        // Add new student
        $username = sanitize($_POST['username']);
        $password = md5($_POST['password']);
        $email = sanitize($_POST['email']);
        $full_name = sanitize($_POST['full_name']);
        $student_id = sanitize($_POST['student_id']);
        $course = sanitize($_POST['course']);
        $year = intval($_POST['year']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        $guardian_name = sanitize($_POST['guardian_name']);
        $guardian_phone = sanitize($_POST['guardian_phone']);
        $joining_date = $_POST['joining_date'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Insert into users table
            $sql1 = "INSERT INTO users (username, password, email, full_name, role) 
                     VALUES (?, ?, ?, ?, 'student')";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("ssss", $username, $password, $email, $full_name);
            $stmt1->execute();
            $user_id = $stmt1->insert_id;
            
            // Insert into students table
            $sql2 = "INSERT INTO students (user_id, student_id, course, year, phone, address, guardian_name, guardian_phone, joining_date) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("ississsss", $user_id, $student_id, $course, $year, $phone, $address, $guardian_name, $guardian_phone, $joining_date);
            $stmt2->execute();
            
            $conn->commit();
            jsonResponse(true, 'Student added successfully');
            
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, 'Failed to add student: ' . $e->getMessage());
        }
    }
}
?>
