<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get all allocations
    $sql = "SELECT a.*, s.student_id, u.full_name as student_name, 
            r.room_number, h.name as hostel_name
            FROM allocations a 
            JOIN students s ON a.student_id = s.id 
            JOIN users u ON s.user_id = u.id 
            JOIN rooms r ON a.room_id = r.id 
            JOIN hostels h ON r.hostel_id = h.id 
            ORDER BY a.id DESC";
    $result = $conn->query($sql);
    $allocations = $result->fetch_all(MYSQLI_ASSOC);
    jsonResponse(true, 'Allocations retrieved', $allocations);
    
} elseif ($method === 'POST') {
    // Create new allocation
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse(false, 'Unauthorized');
    }
    
    $student_id = intval($_POST['student_id']);
    $room_id = intval($_POST['room_id']);
    $bed_number = intval($_POST['bed_number']);
    $start_date = $_POST['start_date'];
    
    // Check if room is available
    $room_sql = "SELECT capacity, occupied FROM rooms WHERE id = ?";
    $room_stmt = $conn->prepare($room_sql);
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room = $room_stmt->get_result()->fetch_assoc();
    
    if ($room['occupied'] >= $room['capacity']) {
        jsonResponse(false, 'Room is full');
    }
    
    // Check if student already has active allocation
    $check_sql = "SELECT id FROM allocations WHERE student_id = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $student_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        jsonResponse(false, 'Student already has an active allocation');
    }
    
    $conn->begin_transaction();
    
    try {
        // Create allocation
        $sql1 = "INSERT INTO allocations (student_id, room_id, bed_number, start_date) VALUES (?, ?, ?, ?)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("iiis", $student_id, $room_id, $bed_number, $start_date);
        $stmt1->execute();
        
        // Update room occupancy
        $sql2 = "UPDATE rooms SET occupied = occupied + 1 WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $room_id);
        $stmt2->execute();
        
        $conn->commit();
        jsonResponse(true, 'Room allocated successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, 'Failed to allocate room');
    }
    
} elseif ($method === 'PUT') {
    // Deallocate room
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = intval($_GET['id']);
    $end_date = $_PUT['end_date'];
    
    // Get room_id
    $sql = "SELECT room_id FROM allocations WHERE id = ? AND status = 'active'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        jsonResponse(false, 'Allocation not found or already inactive');
    }
    
    $allocation = $result->fetch_assoc();
    $room_id = $allocation['room_id'];
    
    $conn->begin_transaction();
    
    try {
        // Update allocation
        $sql1 = "UPDATE allocations SET status = 'completed', end_date = ? WHERE id = ?";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("si", $end_date, $id);
        $stmt1->execute();
        
        // Update room occupancy
        $sql2 = "UPDATE rooms SET occupied = occupied - 1 WHERE id = ?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("i", $room_id);
        $stmt2->execute();
        
        $conn->commit();
        jsonResponse(true, 'Room deallocated successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, 'Failed to deallocate room');
    }
}
?>
