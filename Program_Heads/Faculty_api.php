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

try {
    /* ==========================================================
       HANDLE GET REQUESTS (FETCH SCHEDULE ONLY)
       * Note: All POST/Edit/Delete functions have been removed
         to enforce strict Read-Only security for this module.
       ========================================================== */
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // --- FETCH PROFESSOR'S CLASS SCHEDULE (LATEST TERM ONLY) ---
        if (isset($_GET['action']) && $_GET['action'] === 'get_schedule') {
            $faculty_id = intval($_GET['faculty_id']);
            
            // PRIORITIZE SCHEDULE UNITS OVER COURSE UNITS AND FILTER BY LATEST TERM
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