<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single staff
        $id = intval($_GET['id']);
        $sql = "SELECT s.*, u.full_name, u.email, u.status 
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            jsonResponse(true, 'Staff found', $result->fetch_assoc());
        } else {
            jsonResponse(false, 'Staff not found');
        }
    } else {
        // Get all staff
        $sql = "SELECT s.*, u.full_name, u.email, u.status 
                FROM staff s 
                JOIN users u ON s.user_id = u.id 
                ORDER BY s.id DESC";
        $result = $conn->query($sql);
        $staff = $result->fetch_all(MYSQLI_ASSOC);
        jsonResponse(true, 'Staff retrieved', $staff);
    }
    
} elseif ($method === 'POST') {
    // Add new staff
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse(false, 'Unauthorized');
    }
    
    $username = sanitize($_POST['username']);
    $password = md5($_POST['password']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $staff_id = sanitize($_POST['staff_id']);
    $designation = sanitize($_POST['designation']);
    $department = sanitize($_POST['department']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $joining_date = $_POST['joining_date'];
    
    // Check if username exists
    $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        jsonResponse(false, 'Username or email already exists');
    }
    
    $conn->begin_transaction();
    
    try {
        // Insert into users
        $sql1 = "INSERT INTO users (username, password, email, full_name, role) 
                 VALUES (?, ?, ?, ?, 'staff')";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("ssss", $username, $password, $email, $full_name);
        $stmt1->execute();
        $user_id = $stmt1->insert_id;
        
        // Insert into staff
        $sql2 = "INSERT INTO staff (user_id, staff_id, designation, department, phone, address, joining_date) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("issssss", $user_id, $staff_id, $designation, $department, $phone, $address, $joining_date);
        $stmt2->execute();
        
        $conn->commit();
        jsonResponse(true, 'Staff added successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, 'Failed to add staff');
    }
    
} elseif ($method === 'PUT') {
    // Update staff
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = intval($_GET['id']);
    $full_name = sanitize($_PUT['full_name']);
    $email = sanitize($_PUT['email']);
    $designation = sanitize($_PUT['designation']);
    $department = sanitize($_PUT['department']);
    $phone = sanitize($_PUT['phone']);
    $address = sanitize($_PUT['address']);
    $status = sanitize($_PUT['status']);
    
    $conn->begin_transaction();
    
    try {
        // Update users
        $sql1 = "UPDATE users SET full_name = ?, email = ?, status = ? 
                 WHERE id = (SELECT user_id FROM staff WHERE id = ?)";
        $stmt1 = $conn->prepare($sql1);
        $stmt1->bind_param("sssi", $full_name, $email, $status, $id);
        $stmt1->execute();
        
        // Update staff
        $sql2 = "UPDATE staff SET designation=?, department=?, phone=?, address=? WHERE id=?";
        $stmt2 = $conn->prepare($sql2);
        $stmt2->bind_param("ssssi", $designation, $department, $phone, $address, $id);
        $stmt2->execute();
        
        $conn->commit();
        jsonResponse(true, 'Staff updated successfully');
        
    } catch (Exception $e) {
        $conn->rollback();
        jsonResponse(false, 'Failed to update staff');
    }
    
} elseif ($method === 'DELETE') {
    // Delete staff
    $id = intval($_GET['id']);
    
    // Get user_id
    $sql = "SELECT user_id FROM staff WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    
    if ($staff) {
        $conn->begin_transaction();
        
        try {
            // Delete from staff
            $sql1 = "DELETE FROM staff WHERE id = ?";
            $stmt1 = $conn->prepare($sql1);
            $stmt1->bind_param("i", $id);
            $stmt1->execute();
            
            // Delete from users
            $sql2 = "DELETE FROM users WHERE id = ?";
            $stmt2 = $conn->prepare($sql2);
            $stmt2->bind_param("i", $staff['user_id']);
            $stmt2->execute();
            
            $conn->commit();
            jsonResponse(true, 'Staff deleted successfully');
            
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, 'Failed to delete staff');
        }
    } else {
        jsonResponse(false, 'Staff not found');
    }
}
?>
