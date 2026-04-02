<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single hostel
        $id = intval($_GET['id']);
        $sql = "SELECT * FROM hostels WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $hostel = $result->fetch_assoc();
            
            // Get room details if requested
            if (isset($_GET['details'])) {
                $rooms_sql = "SELECT * FROM rooms WHERE hostel_id = ?";
                $rooms_stmt = $conn->prepare($rooms_sql);
                $rooms_stmt->bind_param("i", $id);
                $rooms_stmt->execute();
                $hostel['rooms'] = $rooms_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            }
            
            jsonResponse(true, 'Hostel found', $hostel);
        } else {
            jsonResponse(false, 'Hostel not found');
        }
    } else {
        // Get all hostels
        $sql = "SELECT h.*, 
                (SELECT COUNT(*) FROM rooms WHERE hostel_id = h.id) as total_capacity,
                (SELECT SUM(occupied) FROM rooms WHERE hostel_id = h.id) as occupied
                FROM hostels h 
                ORDER BY h.id DESC";
        $result = $conn->query($sql);
        $hostels = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get statistics
        $stats_sql = "SELECT 
                        COUNT(*) as total,
                        SUM(total_rooms) as total_rooms,
                        (SELECT COUNT(*) FROM rooms) as total_capacity,
                        (SELECT SUM(occupied) FROM rooms) as total_occupied
                      FROM hostels";
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        $stats['occupancy_rate'] = $stats['total_capacity'] > 0 ? 
            round(($stats['total_occupied'] / $stats['total_capacity']) * 100, 2) : 0;
        
        jsonResponse(true, 'Hostels retrieved', [
            'data' => $hostels,
            'stats' => $stats
        ]);
    }
    
} elseif ($method === 'POST') {
    // Add new hostel
    $name = sanitize($_POST['name']);
    $type = sanitize($_POST['type']);
    $total_floors = intval($_POST['total_floors']);
    $total_rooms = intval($_POST['total_rooms']);
    $address = sanitize($_POST['address']);
    $warden_name = sanitize($_POST['warden_name']);
    $warden_phone = sanitize($_POST['warden_phone']);
    $status = sanitize($_POST['status']);
    
    $sql = "INSERT INTO hostels (name, type, total_floors, total_rooms, address, warden_name, warden_phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiissss", $name, $type, $total_floors, $total_rooms, $address, $warden_name, $warden_phone, $status);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Hostel added successfully');
    } else {
        jsonResponse(false, 'Failed to add hostel');
    }
    
} elseif ($method === 'PUT') {
    // Update hostel
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = intval($_GET['id']);
    $name = sanitize($_PUT['name']);
    $type = sanitize($_PUT['type']);
    $total_floors = intval($_PUT['total_floors']);
    $total_rooms = intval($_PUT['total_rooms']);
    $address = sanitize($_PUT['address']);
    $warden_name = sanitize($_PUT['warden_name']);
    $warden_phone = sanitize($_PUT['warden_phone']);
    $status = sanitize($_PUT['status']);
    
    $sql = "UPDATE hostels SET name=?, type=?, total_floors=?, total_rooms=?, address=?, warden_name=?, warden_phone=?, status=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssiissssi", $name, $type, $total_floors, $total_rooms, $address, $warden_name, $warden_phone, $status, $id);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Hostel updated successfully');
    } else {
        jsonResponse(false, 'Failed to update hostel');
    }
    
} elseif ($method === 'DELETE') {
    // Delete hostel
    $id = intval($_GET['id']);
    
    // Check if hostel has rooms
    $check_sql = "SELECT COUNT(*) as count FROM rooms WHERE hostel_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    if ($count > 0) {
        jsonResponse(false, 'Cannot delete hostel with existing rooms');
    }
    
    $sql = "DELETE FROM hostels WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Hostel deleted successfully');
    } else {
        jsonResponse(false, 'Failed to delete hostel');
    }
}
?>
