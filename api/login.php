<?php
require_once 'db_config.php';

// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        jsonResponse(false, 'Username and password are required');
    }
    
    // Check in database
    $sql = "SELECT u.*, 
            CASE 
                WHEN u.role = 'student' THEN s.id 
                WHEN u.role = 'staff' THEN st.id 
                ELSE NULL 
            END as profile_id 
            FROM users u 
            LEFT JOIN students s ON u.id = s.user_id 
            LEFT JOIN staff st ON u.id = st.user_id 
            WHERE u.username = ? AND u.status = 'active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password (using MD5 for simplicity - in production use password_hash)
        if ($user['password'] === md5($password)) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['profile_id'] = $user['profile_id'];
            
            // Log the login
            $log_sql = "INSERT INTO login_logs (user_id, ip_address) VALUES (?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("is", $user['id'], $ip);
            $log_stmt->execute();
            
            jsonResponse(true, 'Login successful', [
                'role' => $user['role'],
                'name' => $user['full_name']
            ]);
        } else {
            jsonResponse(false, 'Invalid password');
        }
    } else {
        jsonResponse(false, 'User not found or inactive');
    }
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['logout'])) {
    session_destroy();
    jsonResponse(true, 'Logout successful');
}
?>
