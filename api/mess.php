<?php
require_once 'db_config.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Please login first');
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['day'])) {
        // Get menu for specific day
        $day = sanitize($_GET['day']);
        $sql = "SELECT * FROM mess_menu WHERE day_of_week = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $day);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            jsonResponse(true, 'Menu found', $result->fetch_assoc());
        } else {
            // Return default menu
            jsonResponse(true, 'Default menu', [
                'breakfast' => 'To be updated',
                'lunch' => 'To be updated',
                'snacks' => 'To be updated',
                'dinner' => 'To be updated'
            ]);
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'attendance') {
        // Get attendance for specific date
        $date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        
        $sql = "SELECT s.id, s.student_id, u.full_name,
                (SELECT status FROM mess_attendance WHERE student_id = s.id AND meal_type = 'breakfast' AND date = ?) as breakfast,
                (SELECT status FROM mess_attendance WHERE student_id = s.id AND meal_type = 'lunch' AND date = ?) as lunch,
                (SELECT status FROM mess_attendance WHERE student_id = s.id AND meal_type = 'snacks' AND date = ?) as snacks,
                (SELECT status FROM mess_attendance WHERE student_id = s.id AND meal_type = 'dinner' AND date = ?) as dinner
                FROM students s 
                JOIN users u ON s.user_id = u.id 
                WHERE u.status = 'active'
                ORDER BY u.full_name";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $date, $date, $date, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $attendance = $result->fetch_all(MYSQLI_ASSOC);
        
        // Convert status to boolean
        foreach ($attendance as &$a) {
            $a['breakfast'] = $a['breakfast'] === 'present';
            $a['lunch'] = $a['lunch'] === 'present';
            $a['snacks'] = $a['snacks'] === 'present';
            $a['dinner'] = $a['dinner'] === 'present';
        }
        
        jsonResponse(true, 'Attendance data', $attendance);
    } else {
        // Get all menu
        $sql = "SELECT * FROM mess_menu ORDER BY FIELD(day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday')";
        $result = $conn->query($sql);
        
        if ($result->num_rows === 0) {
            // Insert default menu if empty
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($days as $day) {
                $insert = "INSERT INTO mess_menu (day_of_week, breakfast, lunch, snacks, dinner) 
                           VALUES (?, 'To be updated', 'To be updated', 'To be updated', 'To be updated')";
                $stmt = $conn->prepare($insert);
                $stmt->bind_param("s", $day);
                $stmt->execute();
            }
            $result = $conn->query($sql);
        }
        
        $menu = $result->fetch_all(MYSQLI_ASSOC);
        
        // Get statistics
        $today = date('Y-m-d');
        $stats_sql = "SELECT 
                        (SELECT COUNT(DISTINCT student_id) FROM mess_attendance WHERE date = '$today') as today_attendance,
                        (SELECT COUNT(*) FROM students) as total_students";
        $stats_result = $conn->query($stats_sql);
        $stats = $stats_result->fetch_assoc();
        
        jsonResponse(true, 'Mess menu retrieved', [
            'data' => $menu,
            'stats' => $stats
        ]);
    }
    
} elseif ($method === 'POST') {
    // Update attendance
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (isset($data['action']) && $data['action'] === 'update_attendance') {
        $student_id = intval($data['student_id']);
        $meal_type = sanitize($data['meal_type']);
        $date = $data['date'];
        $status = sanitize($data['status']);
        
        $sql = "INSERT INTO mess_attendance (student_id, meal_type, date, status) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $student_id, $meal_type, $date, $status, $status);
        
        if ($stmt->execute()) {
            jsonResponse(true, 'Attendance updated');
        } else {
            jsonResponse(false, 'Failed to update attendance');
        }
    } elseif (isset($data['action']) && $data['action'] === 'mark_all_present') {
        $date = $data['date'];
        $students = $conn->query("SELECT id FROM students");
        
        $conn->begin_transaction();
        
        try {
            while ($student = $students->fetch_assoc()) {
                $meals = ['breakfast', 'lunch', 'snacks', 'dinner'];
                foreach ($meals as $meal) {
                    $sql = "INSERT INTO mess_attendance (student_id, meal_type, date, status) 
                            VALUES (?, ?, ?, 'present') 
                            ON DUPLICATE KEY UPDATE status = 'present'";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("iss", $student['id'], $meal, $date);
                    $stmt->execute();
                }
            }
            $conn->commit();
            jsonResponse(true, 'All students marked present');
            
        } catch (Exception $e) {
            $conn->rollback();
            jsonResponse(false, 'Failed to mark attendance');
        }
    }
    
} elseif ($method === 'PUT') {
    // Update menu
    parse_str(file_get_contents("php://input"), $_PUT);
    $day = sanitize($_PUT['day']);
    $breakfast = sanitize($_PUT['breakfast']);
    $lunch = sanitize($_PUT['lunch']);
    $snacks = sanitize($_PUT['snacks']);
    $dinner = sanitize($_PUT['dinner']);
    
    $sql = "UPDATE mess_menu SET breakfast=?, lunch=?, snacks=?, dinner=? WHERE day_of_week=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $breakfast, $lunch, $snacks, $dinner, $day);
    
    if ($stmt->execute()) {
        jsonResponse(true, 'Menu updated successfully');
    } else {
        jsonResponse(false, 'Failed to update menu');
    }
}
?>
