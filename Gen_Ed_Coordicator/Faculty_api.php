<?php
session_start();
include '../db_connect.php';

// Disable default error display to prevent breaking JSON
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json');

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? '';

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'General Education' || stripos($userPos, 'Gen') !== false || (stripos($userPos, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));

try {
    /* ==========================================================
       HANDLE POST REQUESTS (UPDATE / DELETE TO `USERS` TABLE)
       ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // --- SECURITY LOCKOUT FOR GEN ED COORDINATOR & DEAN ---
        if ($isGenEd) {
            throw new Exception("Access Denied: General Education Coordinators have read-only access and cannot edit or delete faculty profiles.");
        }
        if (stripos($userPos, 'Dean') !== false) {
            throw new Exception("Access Denied: Deans have read-only access to faculty profiles.");
        }

        // --- DELETE ---
        if (isset($_POST['delete_id'])) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i", $_POST['delete_id']);
            if ($stmt->execute()) {
                echo json_encode(["status" => "success", "message" => "User profile deleted successfully."]);
            } else {
                throw new Exception("Database busy or user has linked records preventing deletion.");
            }
            exit;
        }

        // --- PREPARE DATA FOR UPDATE ---
        $id             = $_POST['user_id'] ?? null;
        $username       = trim($_POST['username'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $first_name     = trim($_POST['first_name'] ?? '');
        $surname        = trim($_POST['surname'] ?? '');
        $middle_initial = trim($_POST['middle_initial'] ?? '');
        $sex            = trim($_POST['sex'] ?? 'Other');
        $employee_id    = trim($_POST['employee_id'] ?? '');
        $department     = trim($_POST['department'] ?? '');
        $program        = trim($_POST['program'] ?? '');
        $position       = trim($_POST['position'] ?? '');

        if (!$id || empty($first_name) || empty($surname) || empty($username)) {
            throw new Exception("First name, surname, and username are required.");
        }

        // Check Duplicates (Exclude self)
        $checkSQL = "SELECT id FROM users WHERE username = ? AND id != ?";
        $checkStmt = $conn->prepare($checkSQL);
        $checkStmt->bind_param("si", $username, $id);
        $checkStmt->execute();
        if($checkStmt->get_result()->num_rows > 0) throw new Exception("Username '$username' is already taken.");

        // Handle File Upload (Simple Read)
        $imgContent = null;
        if (isset($_FILES['picture']) && $_FILES['picture']['error'] === UPLOAD_ERR_OK) {
             $imgContent = file_get_contents($_FILES['picture']['tmp_name']);
        }

        // --- UPDATE ---
        if ($imgContent) {
            $sql = "UPDATE users SET username=?, email=?, first_name=?, surname=?, middle_initial=?, sex=?, employee_id=?, department=?, program=?, position=?, profile_picture=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $null = NULL;
            $stmt->bind_param("ssssssssssbi", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $null, $id);
            $stmt->send_long_data(10, $imgContent);
        } else {
            $sql = "UPDATE users SET username=?, email=?, first_name=?, surname=?, middle_initial=?, sex=?, employee_id=?, department=?, program=?, position=? WHERE id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssssssssi", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $id);
        }
        
        if($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "User profile updated successfully!"]);
        } else {
            throw new Exception("Update failed: " . $conn->error);
        }
        
        exit;
    }

    /* ==========================================================
       HANDLE GET REQUESTS (FETCH SCHEDULE)
       ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // --- FETCH PROFESSOR'S CLASS SCHEDULE ---
        if (isset($_GET['action']) && $_GET['action'] === 'get_schedule') {
            $faculty_id = intval($_GET['faculty_id']);
            
            // PRIORITIZE SCHEDULE UNITS OVER COURSE UNITS
            $sql = "SELECT s.days, s.start_time, s.end_time, s.section, s.program, s.academic_year, s.semester, s.approval_status, s.requested_department,
                           c.course_code, c.descriptive_title, 
                           COALESCE(s.units, c.units) as units, 
                           COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours, 
                           COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours,
                           r.room_name,
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as professor 
                    FROM class_schedules s
                    JOIN courses c ON s.course_id = c.id
                    LEFT JOIN rooms r ON s.room_id = r.id
                    LEFT JOIN users u ON s.faculty_id = u.id
                    WHERE s.faculty_id = ? AND s.approval_status = 'Approved'
                    ORDER BY s.academic_year DESC, s.semester ASC, s.start_time ASC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            echo json_encode($result->fetch_all(MYSQLI_ASSOC));
            exit;
        }
    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>