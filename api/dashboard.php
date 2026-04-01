<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$profile_id = $_SESSION['profile_id'];

$response = ['user' => $_SESSION['full_name'], 'role' => $role];

if ($role === 'admin') {
    // Admin dashboard stats
    $stats = [];
    
    // Total students
    $result = $conn->query("SELECT COUNT(*) as count FROM students");
    $stats['total_students'] = $result->fetch_assoc()['count'];
    
    // Total staff
    $result = $conn->query("SELECT COUNT(*) as count FROM staff");
    $stats['total_staff'] = $result->fetch_assoc()['count'];
    
    // Total hostels
    $result = $conn->query("SELECT COUNT(*) as count FROM hostels");
    $stats['total_hostels'] = $result->fetch_assoc()['count'];
    
    // Occupancy rate
    $result = $conn->query("SELECT SUM(occupied) as occupied, SUM(capacity) as capacity FROM rooms");
    $room_stats = $result->fetch_assoc();
    $stats['occupancy_rate'] = $room_stats['capacity'] > 0 ? 
        round(($room_stats['occupied'] / $room_stats['capacity']) * 100, 2) : 0;
    
    // Pending complaints
    $result = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'");
    $stats['pending_complaints'] = $result->fetch_assoc()['count'];
    
    // Today's mess attendance
    $today = date('Y-m-d');
    $result = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM mess_attendance WHERE date = '$today'");
    $stats['today_mess'] = $result->fetch_assoc()['count'];
    
    // Recent payments
    $payments = $conn->query("
        SELECT p.*, s.student_id, u.full_name 
        FROM payments p 
        JOIN students s ON p.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        ORDER BY p.created_at DESC 
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Recent complaints
    $complaints = $conn->query("
        SELECT c.*, s.student_id, u.full_name 
        FROM complaints c 
        JOIN students s ON c.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        ORDER BY c.created_at DESC 
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    $response['stats'] = $stats;
    $response['recent_payments'] = $payments;
    $response['recent_complaints'] = $complaints;
    
} elseif ($role === 'student') {
    // Student dashboard
    // Get student details
    $student = $conn->query("
        SELECT s.*, u.full_name, u.email 
        FROM students s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.id = $profile_id
    ")->fetch_assoc();
    
    // Get current allocation
    $allocation = $conn->query("
        SELECT a.*, r.room_number, r.room_type, h.name as hostel_name 
        FROM allocations a 
        JOIN rooms r ON a.room_id = r.id 
        JOIN hostels h ON r.hostel_id = h.id 
        WHERE a.student_id = $profile_id AND a.status = 'active'
    ")->fetch_assoc();
    
    // Get pending dues
    $dues = $conn->query("
        SELECT SUM(amount) as total 
        FROM payments 
        WHERE student_id = $profile_id AND status = 'pending'
    ")->fetch_assoc();
    
    // Get recent notices
    $notices = $conn->query("
        SELECT * FROM notices 
        WHERE audience IN ('all', 'students') 
        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
        ORDER BY priority DESC, created_at DESC 
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Get pending complaints
    $my_complaints = $conn->query("
        SELECT * FROM complaints 
        WHERE student_id = $profile_id 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetch_all(MYSQLI_ASSOC);
    
    $response['student'] = $student;
    $response['current_allocation'] = $allocation;
    $response['pending_dues'] = $dues['total'] ?? 0;
    $response['notices'] = $notices;
    $response['my_complaints'] = $my_complaints;
    
} elseif ($role === 'staff') {
    // Staff dashboard
    // Get assigned tasks
    $tasks = $conn->query("
        SELECT * FROM complaints 
        WHERE assigned_to = $profile_id AND status IN ('pending', 'in_progress')
        ORDER BY priority DESC, created_at ASC
    ")->fetch_all(MYSQLI_ASSOC);
    
    // Get today's tasks count
    $today_tasks = $conn->query("
        SELECT COUNT(*) as count 
        FROM complaints 
        WHERE assigned_to = $profile_id 
        AND DATE(created_at) = CURDATE()
    ")->fetch_assoc()['count'];
    
    $response['assigned_tasks'] = $tasks;
    $response['today_tasks'] = $today_tasks;
}

jsonResponse(true, 'Dashboard data retrieved', $response);
?>
