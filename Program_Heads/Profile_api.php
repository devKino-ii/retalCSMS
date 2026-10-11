<?php
session_start();
header('Content-Type: application/json');

// Include your database connection
require '../db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ==========================================
    // ACTION 1: UPDATE PERSONAL INFO
    // ==========================================
    if ($action === 'update_info') {
        $first_name = trim($_POST['first_name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $middle_initial = trim($_POST['middle_initial'] ?? '');
        $sex = $_POST['sex'] ?? 'Other';
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');

        // Basic validation
        if (empty($first_name) || empty($surname) || empty($email) || empty($username)) {
            echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields.']);
            exit();
        }

        // Check if username or email is already taken by ANOTHER user
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $checkStmt->bind_param("ssi", $username, $email, $user_id);
        $checkStmt->execute();
        if ($checkStmt->get_result()->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Username or Email is already in use by another account.']);
            exit();
        }

        // Update database
        $updateStmt = $conn->prepare("UPDATE users SET first_name=?, surname=?, middle_initial=?, sex=?, email=?, username=? WHERE id=?");
        $updateStmt->bind_param("ssssssi", $first_name, $surname, $middle_initial, $sex, $email, $username, $user_id);
        
        if ($updateStmt->execute()) {
            // Update session name for the UI
            $_SESSION['full_name'] = trim($first_name . ' ' . $surname);
            echo json_encode(['status' => 'success', 'message' => 'Profile information updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error. Could not update.']);
        }
        exit();
    }

    // ==========================================
    // ACTION 2: UPDATE PASSWORD
    // ==========================================
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            echo json_encode(['status' => 'error', 'message' => 'Passwords cannot be empty.']);
            exit();
        }

        // Fetch current hashed password from DB
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        // Verify old password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            
            $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->bind_param("si", $new_hashed, $user_id);
            
            if ($updateStmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Password updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update password in database.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'The current password you entered is incorrect.']);
        }
        exit();
    }

    // ==========================================
    // ACTION 3: UPDATE PHOTO
    // ==========================================
    if ($action === 'update_photo') {
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
            $fileData = file_get_contents($_FILES['picture']['tmp_name']);
            
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("bi", $null, $user_id);
            $stmt->send_long_data(0, $fileData);
            
            if ($stmt->execute()) {
                // Update session so sidebar reflects change immediately
                $_SESSION['profile_picture'] = 'data:image/jpeg;base64,' . base64_encode($fileData);
                echo json_encode(['status' => 'success', 'message' => 'Photo updated successfully.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to save photo to database.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No valid image file received.']);
        }
        exit();
    }

    echo json_encode(['status' => 'error', 'message' => 'Invalid action.']);
    exit();
}
?>