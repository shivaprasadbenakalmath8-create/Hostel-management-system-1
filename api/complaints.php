<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($method === 'GET') {
    if (isset($_GET['action']) && $_GET['action'] === 'my' && $role === 'student') {
        // Get student's own complaints
        $student_id = $_SESSION['profile_id'];
        
        $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM complaints c
                LEFT JOIN staff s ON c.assigned_to = s.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE c.student_id = ?
                ORDER BY c.created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        jsonResponse(true, 'Complaints retrieved', $result->fetch_all(MYSQLI_ASSOC));
        
    } elseif ($role === 'admin' || $role === 'staff') {
        // Get all complaints with filters
        $status = isset($_GET['status']) ? $_GET['status'] : '';
        $priority = isset($_GET['priority']) ? $_GET['priority'] : '';
        
        $sql = "SELECT c.*, s.student_id, u.full_name as student_name,
                CONCAT(st.first_name, ' ', st.last_name) as assigned_to_name
                FROM complaints c
                JOIN students s ON c.student_id = s.id
                JOIN users u ON s.user_id = u.id
                LEFT JOIN staff st ON c.assigned_to = st.id
                WHERE 1=1";
        
        if ($status) {
            $sql .= " AND c.status = '$status'";
        }
        if ($priority) {
            $sql .= " AND c.priority = '$priority'";
        }
        
        $sql .= " ORDER BY 
                  CASE c.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'medium' THEN 2 
                    WHEN 'low' THEN 3 
                  END,
                  c.created_at DESC";
        
        $result = $conn->query($sql);
        $complaints = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get statistics
        $stats_sql = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                        SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
                      FROM complaints";
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        
        jsonResponse(true, 'Complaints retrieved', [
            'complaints' => $complaints,
            'stats' => $stats
        ]);
    }
    
} elseif ($method === 'POST') {
    if ($role === 'student') {
        // Register new complaint
        $student_id = $_SESSION['profile_id'];
        $complaint_type = sanitize($_POST['complaint_type']);
        $description = sanitize($_POST['description']);
        $priority = sanitize($_POST['priority']);
        
        $sql = "INSERT INTO complaints (student_id, complaint_type, description, priority, status) 
                VALUES (?, ?, ?, ?, 'pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $student_id, $complaint_type, $description, $priority);
        
        if ($stmt->execute()) {
            jsonResponse(true, 'Complaint registered successfully');
        } else {
            jsonResponse(false, 'Failed to register complaint');
        }
        
    } elseif ($role === 'admin' || $role === 'staff') {
        // Update complaint status
        $complaint_id = intval($_POST['complaint_id']);
        $status = sanitize($_POST['status']);
        $resolution_text = sanitize($_POST['resolution_text']);
        $assigned_to = isset($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        
        $sql = "UPDATE complaints SET status = ?, resolution_text = ?";
        $params = [$status, $resolution_text];
        $types = "ss";
        
        if ($assigned_to) {
            $sql .= ", assigned_to = ?";
            $params[] = $assigned_to;
            $types .= "i";
        }
        
        if ($status === 'resolved') {
            $sql .= ", resolved_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $complaint_id;
        $types .= "i";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        
        if ($stmt->execute()) {
            jsonResponse(true, 'Complaint updated successfully');
        } else {
            jsonResponse(false, 'Failed to update complaint');
        }
    }
}
?>
