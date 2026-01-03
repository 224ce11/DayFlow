<?php
session_start();
include 'db_connect.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($action === 'check_in') {
    $date = date('Y-m-d');
    
    // Check for approved leave
    $leave_stmt = $conn->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ? BETWEEN from_date AND to_date");
    if (!$leave_stmt) { die(json_encode(['success'=>false, 'message'=>'DB Error'])); }
    $leave_stmt->bind_param("is", $user_id, $date);
    $leave_stmt->execute();
    if ($leave_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot check-in on approved leave day.']);
        exit;
    }
    
    // Check if already checked in
    $check_stmt = $conn->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
    $check_stmt->bind_param("is", $user_id, $date);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Already checked in today.']);
        exit;
    }

    // Insert Check-in
    $ins = $conn->prepare("INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, CURTIME(), 'Half-day')");
    $ins->bind_param("is", $user_id, $date);
    if ($ins->execute()) {
        echo json_encode(['success' => true, 'message' => 'Checked in successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-in failed.']);
    }

} elseif ($action === 'check_out') {
    $date = date('Y-m-d');
    
    // Get check-in time
    $q = $conn->prepare("SELECT check_in_time FROM attendance WHERE user_id = ? AND date = ?");
    $q->bind_param("is", $user_id, $date);
    $q->execute();
    $res = $q->get_result();
    
    if ($res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No check-in record found for today.']);
        exit;
    }
    
    $row = $res->fetch_assoc();
    $check_in = strtotime($row['check_in_time']);
    $now = time(); // Current time
    
    // Calculate duration in hours
    $hours_worked = ($now - $check_in) / 3600;
    
    $status = ($hours_worked >= 4) ? 'Present' : 'Half-day';
    
    $upd = $conn->prepare("UPDATE attendance SET check_out_time = CURTIME(), status = ? WHERE user_id = ? AND date = ?");
    $upd->bind_param("sis", $status, $user_id, $date);
    
    if ($upd->execute()) {
        echo json_encode(['success' => true, 'message' => 'Checked out. Status: ' . $status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-out failed.']);
    }

} elseif ($action === 'get_status') {
    $date = date('Y-m-d');
    $q = $conn->prepare("SELECT check_in_time, check_out_time, status FROM attendance WHERE user_id = ? AND date = ?");
    $q->bind_param("is", $user_id, $date);
    $q->execute();
    $res = $q->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'checked_in' => !empty($row['check_in_time']),
            'checked_out' => !empty($row['check_out_time']),
            'check_in_time' => $row['check_in_time'],
            'check_out_time' => $row['check_out_time'],
            'status' => $row['status']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'checked_in' => false,
            'checked_out' => false,
            'status' => 'Absent'
        ]);
    }

} elseif ($action === 'apply_leave') {
    $from_date = $_POST['from_date'];
    $to_date = $_POST['to_date'];
    $reason = $_POST['reason'];

    if (strtotime($from_date) > strtotime($to_date)) {
        echo json_encode(['success' => false, 'message' => 'Invalid date range.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO leave_requests (user_id, from_date, to_date, reason, status) VALUES (?, ?, ?, ?, 'Pending')");
    $stmt->bind_param("isss", $user_id, $from_date, $to_date, $reason);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Leave requested successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to apply leave.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
