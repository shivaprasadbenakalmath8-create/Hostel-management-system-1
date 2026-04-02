<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Get user profile
    $sql = "SELECT id, username, email, full_name, role, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    // Get additional info based on role
    if ($_SESSION['role'] === 'student') {
        $sql = "SELECT phone, course, year FROM students WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $extra = $result->fetch_assoc();
        $user = array_merge($user, $extra);
    } elseif ($_SESSION['role'] === 'staff') {
        $sql = "SELECT phone, designation FROM staff WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $extra = $result->fetch_assoc();
        $user = array_merge($user, $extra);
    }
    
    jsonResponse(true, 'Profile retrieved', $user);
    
} elseif ($method === 'PUT') {
    // Update profile
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    
    // Check current password if changing
    if (!empty($new_password)) {
        $sql = "SELECT password FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user['password'] !== md5($current_password)) {
            jsonResponse(false, 'Current password is incorrect');
        }
    }
    
    // Update users table
    if (!empty($new_password)) {
        $new_password_hash = md5($new_password);
        $sql = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $full_name, $email, $new_password_hash, $user_id);
    } else {
        $sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $full_name, $email, $user_id);
    }
    $stmt->execute();
    
    // Update role-specific table
    if ($_SESSION['role'] === 'student') {
        $sql = "UPDATE students SET phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $phone, $user_id);
        $stmt->execute();
    } elseif ($_SESSION['role'] === 'staff') {
        $sql = "UPDATE staff SET phone = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $phone, $user_id);
        $stmt->execute();
    }
    
    // Update session name
    $_SESSION['full_name'] = $full_name;
    
    jsonResponse(true, 'Profile updated successfully');
}
?>
