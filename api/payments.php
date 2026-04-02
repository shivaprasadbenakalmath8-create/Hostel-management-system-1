<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        // Get single payment
        $id = intval($_GET['id']);
        $sql = "SELECT p.*, s.student_id, u.full_name, s.course 
                FROM payments p 
                JOIN students s ON p.student_id = s.id 
                JOIN users u ON s.user_id = u.id 
                WHERE p.id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            jsonResponse(true, 'Payment found', $result->fetch_assoc());
        } else {
            jsonResponse(false, 'Payment not found');
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'my' && $_SESSION['role'] === 'student') {
        // Get student's payments
        $student_id = $_SESSION['profile_id'];
        $sql = "SELECT * FROM payments WHERE student_id = ? ORDER BY payment_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get summary
        $summary_sql = "SELECT 
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending
                        FROM payments WHERE student_id = ?";
        $summary_stmt = $conn->prepare($summary_sql);
        $summary_stmt->bind_param("i", $student_id);
        $summary_stmt->execute();
        $summary = $summary_stmt->get_result()->fetch_assoc();
        
        jsonResponse(true, 'Payments retrieved', [
            'payments' => $payments,
            'summary' => $summary
        ]);
    } else {
        // Get all payments
        $sql = "SELECT p.*, s.student_id, u.full_name 
                FROM payments p 
                JOIN students s ON p.student_id = s.id 
                JOIN users u ON s.user_id = u.id 
                ORDER BY p.payment_date DESC";
        $result = $conn->query($sql);
        $payments = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get summary
        $summary_sql = "SELECT 
                        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_collected,
                        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
                        SUM(CASE WHEN MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_month
                        FROM payments";
        $summary_result = $conn->query($summary_sql);
        $summary = $summary_result->fetch_assoc();
        
        jsonResponse(true, 'Payments retrieved', [
            'payments' => $payments,
            'summary' => $summary
        ]);
    }
    
} elseif ($method === 'POST') {
    // Add payment
    if ($_SESSION['role'] !== 'admin') {
        jsonResponse(false, 'Unauthorized');
    }
    
    $student_id = intval($_POST['student_id']);
    $payment_type = sanitize($_POST['payment_type']);
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $due_date = isset($_POST['due_date']) ? $_POST['due_date'] : null;
    $payment_method = sanitize($_POST['payment_method']);
    $transaction_id = isset($_POST['transaction_id']) ? sanitize($_POST['transaction_id']) : null;
    $remarks = isset($_POST['remarks']) ? sanitize($_POST['remarks']) : null;
    
    $sql = "INSERT INTO payments (student_id, payment_type, amount, payment_date, due_date, payment_method, transaction_id, remarks, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issdssss", $student_id, $payment_type, $amount, $payment_date, $due_date, $payment_method, $transaction_id, $remarks);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Payment recorded successfully');
    } else {
        jsonResponse(false, 'Failed to record payment');
    }
    
} elseif ($method === 'PUT') {
    // Update payment status
    parse_str(file_get_contents("php://input"), $_PUT);
    $id = intval($_GET['id']);
    $status = sanitize($_PUT['status']);
    $payment_date = isset($_PUT['payment_date']) ? $_PUT['payment_date'] : date('Y-m-d');
    
    $sql = "UPDATE payments SET status = ?, payment_date = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $status, $payment_date, $id);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Payment updated successfully');
    } else {
        jsonResponse(false, 'Failed to update payment');
    }
    
} elseif ($method === 'DELETE') {
    // Delete payment
    $id = intval($_GET['id']);
    
    $sql = "DELETE FROM payments WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Payment deleted successfully');
    } else {
        jsonResponse(false, 'Failed to delete payment');
    }
}
?>
