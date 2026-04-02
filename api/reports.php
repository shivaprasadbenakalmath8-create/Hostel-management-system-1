<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$type = isset($_GET['type']) ? $_GET['type'] : 'students';
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-1 month'));
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

if ($type === 'students') {
    // Student report
    $sql = "SELECT s.*, u.full_name, u.email, r.room_number 
            FROM students s 
            JOIN users u ON s.user_id = u.id 
            LEFT JOIN allocations a ON s.id = a.student_id AND a.status = 'active'
            LEFT JOIN rooms r ON a.room_id = r.id
            ORDER BY s.id DESC";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, 'Student report generated', $data);
    
} elseif ($type === 'payments') {
    // Payment report
    $sql = "SELECT p.*, s.student_id, u.full_name 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            JOIN users u ON s.user_id = u.id 
            WHERE p.payment_date BETWEEN '$from' AND '$to'
            ORDER BY p.payment_date DESC";
    $result = $conn->query($sql);
    $payments = $result->fetch_all(MYSQLI_ASSOC);
    
    // Summary
    $summary = [
        'total' => 0,
        'paid' => 0,
        'pending' => 0
    ];
    foreach ($payments as $p) {
        $summary['total'] += $p['amount'];
        if ($p['status'] === 'paid') $summary['paid'] += $p['amount'];
        if ($p['status'] === 'pending') $summary['pending'] += $p['amount'];
    }
    
    jsonResponse(true, 'Payment report generated', [
        'payments' => $payments,
        'summary' => $summary
    ]);
    
} elseif ($type === 'attendance') {
    // Attendance report
    $sql = "SELECT 
                s.id, s.student_id, u.full_name,
                SUM(CASE WHEN ma.meal_type = 'breakfast' THEN 1 ELSE 0 END) as breakfast_count,
                SUM(CASE WHEN ma.meal_type = 'lunch' THEN 1 ELSE 0 END) as lunch_count,
                SUM(CASE WHEN ma.meal_type = 'snacks' THEN 1 ELSE 0 END) as snacks_count,
                SUM(CASE WHEN ma.meal_type = 'dinner' THEN 1 ELSE 0 END) as dinner_count
            FROM students s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN mess_attendance ma ON s.id = ma.student_id 
                AND ma.date BETWEEN '$from' AND '$to'
            GROUP BY s.id
            ORDER BY u.full_name";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, 'Attendance report generated', $data);
    
} elseif ($type === 'complaints') {
    // Complaints report
    $sql = "SELECT c.*, s.student_id, u.full_name 
            FROM complaints c 
            JOIN students s ON c.student_id = s.id 
            JOIN users u ON s.user_id = u.id 
            WHERE DATE(c.created_at) BETWEEN '$from' AND '$to'
            ORDER BY c.created_at DESC";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, 'Complaints report generated', $data);
    
} elseif ($type === 'rooms') {
    // Room occupancy report
    $sql = "SELECT r.*, h.name as hostel_name 
            FROM rooms r 
            JOIN hostels h ON r.hostel_id = h.id 
            ORDER BY h.name, r.room_number";
    $result = $conn->query($sql);
    $data = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, 'Room occupancy report generated', $data);
    
} else {
    jsonResponse(false, 'Invalid report type');
}
?>
