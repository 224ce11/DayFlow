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

if ($action === 'get_status') {
    $date = date('Y-m-d');
    $stmt = $conn->prepare("SELECT check_in_time, check_out_time, status FROM attendance WHERE user_id = ? AND date = ?");
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $stmt->bind_result($check_in_time, $check_out_time, $status);
    $stmt->fetch();
    $stmt->close();

    $response = [
        'success' => true,
        'checked_in' => !empty($check_in_time) && empty($check_out_time),
        'checked_out' => !empty($check_out_time),
        'check_in_time' => $check_in_time,
        'check_out_time' => $check_out_time,
        'status' => $status
    ];
    echo json_encode($response);

} elseif ($action === 'check_in') {
    $date = date('Y-m-d');
    
    // Check for approved leave
    $leave_stmt = $conn->prepare("SELECT id FROM leave_requests WHERE user_id = ? AND status = 'Approved' AND ? BETWEEN from_date AND to_date");
    if (!$leave_stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error (Leave Check): ' . $conn->error]);
        exit;
    }
    $leave_stmt->bind_param("is", $user_id, $date);
    $leave_stmt->execute();
    $leave_stmt->store_result();
    
    if ($leave_stmt->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot check-in on approved leave day.']);
        $leave_stmt->close();
        exit;
    }
    $leave_stmt->close();

    // Attempt Check-In
    $query = "INSERT INTO attendance (user_id, date, check_in_time, status) VALUES (?, ?, CURTIME(), 'Half-day')";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error (Check-in): ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("is", $user_id, $date);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Checked in successfully.']);
    } else {
        // likely duplicate entry
        echo json_encode(['success' => false, 'message' => 'Already checked in for today or error occurred.']);
    }
    $stmt->close();

} elseif ($action === 'check_out') {
    $date = date('Y-m-d');
    
    // Update Check-Out
    // Logic: If duration > 4 hours, Present, else Half-day. 
    // We update logic inside SQL or PHP. Let's do PHP for clarity.
    
    // First get check_in time
    $q_in = $conn->prepare("SELECT check_in_time FROM attendance WHERE user_id = ? AND date = ?");
    $q_in->bind_param("is", $user_id, $date);
    $q_in->execute();
    $q_in->bind_result($check_in_val);
    if (!$q_in->fetch()) {
        echo json_encode(['success' => false, 'message' => 'No check-in record found for today.']);
        $q_in->close();
        exit;
    }
    $q_in->close();

    // Calculate status
    $check_in_dt = new DateTime($date . ' ' . $check_in_val);
    $now = new DateTime();
    $interval = $check_in_dt->diff($now);
    $hours = $interval->h + ($interval->i / 60);

    $new_status = ($hours >= 4) ? 'Present' : 'Half-day';

    $update = $conn->prepare("UPDATE attendance SET check_out_time = CURTIME(), status = ? WHERE user_id = ? AND date = ?");
    $update->bind_param("sis", $new_status, $user_id, $date);
    
    if ($update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Checked out. Status: ' . $new_status]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Check-out failed.']);
    }
    $update->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

$conn->close();
?>
