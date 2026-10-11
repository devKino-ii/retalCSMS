<?php
session_start();
include '../db_connect.php';

// Disable default error display to prevent breaking JSON
error_reporting(0); 
ini_set('display_errors', 0);

header('Content-Type: application/json');

// --- STRICT ROLE-BASED ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$userId = $_SESSION['user_id'];
$userDept = $_SESSION['department'] ?? '';

// --- SECURITY: GET USER POSITION AND PROGRAM ---
$stmtUser = $conn->prepare("SELECT position, program FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$userPosition = $userRow['position'] ?? '';
$userProgram = $userRow['program'] ?? '';
$isProgramHead = (stripos($userPosition, 'Program Head') !== false || stripos($userPosition, 'Coordinator') !== false);
$isDean = (stripos($userPosition, 'Dean') !== false);
$isLeadership  = ($isProgramHead || stripos($userPosition, 'Admin') !== false || $isDean);

try {
    /* ==========================================================
       HANDLE POST REQUESTS (UPDATE / DELETE TO `USERS` TABLE)
       ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // SECURITY: Block Deans from making edits
        if ($isDean) {
            throw new Exception("Unauthorized: Deans have read-only access to Faculty profiles.");
        }

        // --- DELETE ---
        if (isset($_POST['delete_id'])) {
            $del_id = (int)$_POST['delete_id'];

            if ($isProgramHead) {
                // Program Head: Can only delete faculty in their program
                $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND department=? AND program=?");
                $stmt->bind_param("iss", $del_id, $userDept, $userProgram);
            } else {
                // Admin: Can delete anyone in the department
                $stmt = $conn->prepare("DELETE FROM users WHERE id=? AND department=?");
                $stmt->bind_param("is", $del_id, $userDept);
            }

            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo json_encode(["status" => "success", "message" => "User profile deleted successfully."]);
                } else {
                    throw new Exception("User not found or you do not have permission to delete them.");
                }
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
        $department     = $userDept; // Lock to session department
        $position       = trim($_POST['position'] ?? '');
        
        // SECURITY: If Program Head, force the program to remain theirs
        $program        = $isProgramHead ? $userProgram : trim($_POST['program'] ?? '');

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
        $sql = "UPDATE users SET username=?, email=?, first_name=?, surname=?, middle_initial=?, sex=?, employee_id=?, department=?, program=?, position=?";
        if ($imgContent) {
            $sql .= ", profile_picture=?";
        }
        
        // Security Lock for WHERE clause
        if ($isProgramHead) {
            $sql .= " WHERE id=? AND department=? AND program=?";
        } else {
            $sql .= " WHERE id=? AND department=?";
        }

        $stmt = $conn->prepare($sql);
        
        if ($imgContent) {
            $null = NULL;
            if ($isProgramHead) {
                $stmt->bind_param("ssssssssssbiss", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $null, $id, $userDept, $userProgram);
            } else {
                $stmt->bind_param("ssssssssssbis", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $null, $id, $userDept);
            }
            $stmt->send_long_data(10, $imgContent);
        } else {
            if ($isProgramHead) {
                $stmt->bind_param("ssssssssssiss", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $id, $userDept, $userProgram);
            } else {
                $stmt->bind_param("ssssssssssis", $username, $email, $first_name, $surname, $middle_initial, $sex, $employee_id, $department, $program, $position, $id, $userDept);
            }
        }
        
        if($stmt->execute()) {
            if($stmt->affected_rows === 0 && $isProgramHead) {
                // Failsafe in case a program head attempts to update someone outside their program
                throw new Exception("No changes made. You may not have permission to edit this user.");
            }
            echo json_encode(["status" => "success", "message" => "User profile updated successfully!"]);
        } else {
            throw new Exception("Update failed: " . $conn->error);
        }
        
        exit;
    }

    /* ==========================================================
       HANDLE GET REQUESTS
       ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // --- FETCH FACULTY LIST (COLLABORATIVE POLLING) ---
        if (isset($_GET['action']) && $_GET['action'] === 'get_faculty') {
            $sql = "SELECT u.*, 
                           (SELECT COALESCE(SUM(COALESCE(s.units, c.units)), 0) 
                            FROM class_schedules s 
                            JOIN courses c ON s.course_id = c.id 
                            WHERE s.faculty_id = u.id AND s.approval_status != 'Pending'
                            AND (s.academic_year, s.semester) = (
                                SELECT academic_year, semester FROM class_schedules 
                                ORDER BY academic_year DESC, 
                                         CASE WHEN semester = 'Second Semester' THEN 2 
                                              WHEN semester = 'First Semester' THEN 1 
                                              ELSE 0 END DESC 
                                LIMIT 1
                            )
                           ) as current_load,
                           (SELECT CONCAT(academic_year, ' - ', semester) 
                            FROM class_schedules 
                            ORDER BY academic_year DESC, 
                                     CASE WHEN semester = 'Second Semester' THEN 2 
                                          WHEN semester = 'First Semester' THEN 1 
                                          ELSE 0 END DESC 
                            LIMIT 1
                           ) as active_term
                    FROM users u 
                    WHERE u.department = ?";

            // Filter by user access level
            if (!$isLeadership) {
                $sql .= " AND u.id = ?";
            } else if ($isProgramHead && !empty($userProgram)) {
                $sql .= " AND u.program = ?";
            }
            
            // SORTING LOGIC: Order by Program first. Then force Program Heads to the top. Then by surname.
            $sql .= " ORDER BY 
                        u.program ASC,
                        CASE 
                            WHEN u.position LIKE '%Program Head%' THEN 1 
                            WHEN u.position LIKE '%Coordinator%' THEN 1 
                            WHEN u.position LIKE '%Dean%' THEN 2
                            ELSE 3 
                        END ASC,
                        u.surname ASC";

            $stmt = $conn->prepare($sql);

            if (!$isLeadership) {
                $stmt->bind_param("si", $userDept, $userId);
            } else if ($isProgramHead && !empty($userProgram)) {
                $stmt->bind_param("ss", $userDept, $userProgram);
            } else {
                $stmt->bind_param("s", $userDept);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $faculty = [];
            while ($row = $result->fetch_assoc()) {
                // Process image to base64 so JS can render it without making a billion requests
                if (!empty($row['profile_picture'])) {
                    $row['pic_base64'] = base64_encode($row['profile_picture']);
                } else {
                    $row['pic_base64'] = null;
                }
                // Unset raw blob to save massive amounts of network bandwidth
                unset($row['profile_picture']); 
                
                $faculty[] = $row;
            }

            echo json_encode($faculty);
            exit;
        }

        // --- FETCH PROFESSOR'S CLASS SCHEDULE (LATEST TERM ONLY) ---
        if (isset($_GET['action']) && $_GET['action'] === 'get_schedule') {
            $faculty_id = intval($_GET['faculty_id']);
            
            // Only fetch the schedule for the newest active term
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
                    WHERE s.faculty_id = ?
                    AND (s.academic_year, s.semester) = (
                        SELECT academic_year, semester FROM class_schedules 
                        ORDER BY academic_year DESC, 
                                 CASE WHEN semester = 'Second Semester' THEN 2 
                                      WHEN semester = 'First Semester' THEN 1 
                                      ELSE 0 END DESC 
                        LIMIT 1
                    )
                    ORDER BY s.start_time ASC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $faculty_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $schedules = $result->fetch_all(MYSQLI_ASSOC);
            
            echo json_encode($schedules);
            exit;
        }

    }

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>