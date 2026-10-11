<?php
// Dashboard_api.php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
include '../db_connect.php'; 
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$userId = $_SESSION['user_id'];
$userDept = $_SESSION['department'] ?? '';

// Set timezone for Next Class calculation
date_default_timezone_set('Asia/Manila');

// =========================================================
// Auto-schema checker & Cleanup
// =========================================================
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'requested_room_department'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE class_schedules ADD COLUMN requested_room_department VARCHAR(100) NULL AFTER requested_department");
    }
    
    // Ensure room_id is nullable so we don't need physical TBA Rooms
    $conn->query("ALTER TABLE class_schedules MODIFY room_id INT NULL");
    
    // Cleanup old TBA Rooms
    $conn->query("UPDATE class_schedules SET room_id = NULL WHERE room_id IN (SELECT id FROM rooms WHERE room_name = 'TBA Room')");
    $conn->query("DELETE FROM rooms WHERE room_name = 'TBA Room'");
} catch (Exception $e) { /* Ignore gracefully if no permission */ }

try {
    // --- SECURITY: GET USER POSITION AND PROGRAM ---
    $stmtUser = $conn->prepare("SELECT position, program FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userRow = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    $userPosition = $userRow['position'] ?? '';
    $userProgram = $userRow['program'] ?? '';
    $isProgramHead = ($userPosition === 'Program Head' || $userPosition === 'Coordinator' || $userPosition === 'Faculty');
    $isFaculty = ($userPosition === 'Faculty');

    // --- SPECIAL ID HELPER ---
    function getSpecialFacultyId($conn, $name) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE first_name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc()['id'];
        } else {
            $username = strtolower(str_replace(' ', '', $name)) . '_sys';
            $email = $username . "@urs.edu.ph";
            $surname = "System";
            $sex = "Other";
            $emp_id = strtoupper($username);
            $sys_val = "System";
            
            $insert = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, sex, employee_id, department, position, program) VALUES (?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($insert) {
                $insert->bind_param("sssssssss", $username, $email, $surname, $name, $sex, $emp_id, $sys_val, $sys_val, $sys_val);
                $insert->execute();
                return $insert->insert_id;
            }
            return 0; // Fallback
        }
    }

    $tba_id = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    $action = $_GET['action'] ?? '';

    // 1. Get Initial Data 
    if ($action === 'init_data') {
        
        // Fetch all rooms (Own dept + University)
        $roomsRes = $conn->query("SELECT * FROM rooms WHERE department = '" . $conn->real_escape_string($userDept) . "' OR department = 'University' ORDER BY room_name");
        $rooms = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];

        // Find Active Academic Year & Semester
        $active_ay = ''; $active_sem = '';
        $stmtAY = $conn->prepare("SELECT academic_year, semester FROM class_schedules WHERE department = ? ORDER BY id DESC LIMIT 1");
        $stmtAY->bind_param("s", $userDept);
        $stmtAY->execute();
        $resAY = $stmtAY->get_result();
        if($resAY && $resAY->num_rows > 0) {
            $row = $resAY->fetch_assoc();
            $active_ay = $row['academic_year'];
            $active_sem = $row['semester'];
        }
        
        // Calculate "My Workload"
        $my_workload = 0;
        $stmtW = $conn->prepare("SELECT COALESCE(SUM(COALESCE(s.units, c.units)), 0) as total_units FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.faculty_id = ? AND s.academic_year = ? AND s.semester = ? AND s.approval_status != 'Pending'");
        $stmtW->bind_param("iss", $userId, $active_ay, $active_sem);
        $stmtW->execute();
        $resW = $stmtW->get_result();
        $my_workload = $resW ? ($resW->fetch_assoc()['total_units'] ?? 0) : 0;

        // Find "My Next Class" (Class today that hasn't ended yet)
        $currentDay = date('D');
        $currentTime = date('H:i:s');
        $next_class = null;
        
        $stmtNC = $conn->prepare("
            SELECT s.start_time, s.end_time, c.course_code, r.room_name, s.section 
            FROM class_schedules s 
            JOIN courses c ON s.course_id = c.id 
            LEFT JOIN rooms r ON s.room_id = r.id 
            WHERE s.faculty_id = ? AND s.academic_year = ? AND s.semester = ? 
            AND FIND_IN_SET(?, REPLACE(s.days, ' ', '')) > 0 
            AND s.end_time > ? 
            ORDER BY s.start_time ASC LIMIT 1
        ");
        $stmtNC->bind_param("issss", $userId, $active_ay, $active_sem, $currentDay, $currentTime);
        $stmtNC->execute();
        $resNC = $stmtNC->get_result();
        if($resNC && $resNC->num_rows > 0) {
            $next_class = $resNC->fetch_assoc();
        }

        // Fetch Dropdown Filters
        $ays = [];
        $ayQuery = "SELECT DISTINCT s.academic_year FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE c.department = '" . $conn->real_escape_string($userDept) . "'";
        if ($isProgramHead && !empty($userProgram)) {
            $ayQuery .= " AND s.program = '" . $conn->real_escape_string($userProgram) . "'";
        }
        $ayQuery .= " ORDER BY s.academic_year DESC";
        $q = $conn->query($ayQuery);
        if ($q) {
            while($r = $q->fetch_assoc()) {
                if(!empty($r['academic_year'])) $ays[] = $r['academic_year'];
            }
        }

        $progs = [];
        if ($isProgramHead && !empty($userProgram)) {
            $progs[] = $userProgram; // Lock to ONLY their program
        } else {
            $q = $conn->query("SELECT DISTINCT s.program FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE c.department = '" . $conn->real_escape_string($userDept) . "' ORDER BY s.program");
            if ($q) {
                while($r = $q->fetch_assoc()) {
                    if(!empty($r['program'])) $progs[] = $r['program'];
                }
            }
        }

        // GET COUNTS FOR DASHBOARD WIDGETS
        $rooms_only_dept = $conn->query("SELECT COUNT(id) as c FROM rooms WHERE department = '" . $conn->real_escape_string($userDept) . "'");
        $room_count = $rooms_only_dept ? ($rooms_only_dept->fetch_assoc()['c'] ?? 0) : 0;
        
        $fac_query = $conn->query("SELECT COUNT(id) as c FROM users WHERE department = '" . $conn->real_escape_string($userDept) . "' AND id NOT IN ($tba_id, $gened_id)");
        $fac_count = $fac_query ? ($fac_query->fetch_assoc()['c'] ?? 0) : 0;

        echo json_encode([
            'rooms' => $rooms,            
            'academic_years' => $ays,
            'programs' => $progs,
            'counts' => [
                'faculty' => $fac_count,
                'rooms' => $room_count
            ],
            'my_workload' => $my_workload,
            'next_class' => $next_class,
            'active_ay' => $active_ay,     // Sent to frontend for Quick Download
            'active_sem' => $active_sem    // Sent to frontend for Quick Download
        ]);
        exit;
    }

    // 2. Get Program Head Notifications
    if ($action === 'get_notifications') {
        $notifications = [];

        // Notification A: Internal Pending Sections (Waiting for Dean)
        $sqlSec = "SELECT section, academic_year, semester, COUNT(*) as class_count 
                   FROM class_schedules 
                   WHERE department = ? AND program = ? 
                   AND approval_status = 'Pending' 
                   AND (requested_department IS NULL OR requested_department = '') 
                   GROUP BY section, academic_year, semester
                   ORDER BY MAX(id) DESC LIMIT 10"; 
        $stmt = $conn->prepare($sqlSec);
        if ($stmt) {
            $stmt->bind_param("ss", $userDept, $userProgram);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) {
                $notifications[] = [
                    'color' => 'pending',
                    'title' => 'WAITING FOR DEAN',
                    'message' => "Schedule for Section " . $r['section'] . " (" . $r['class_count'] . " subjects) is pending Dean's approval."
                ];
            }
            $stmt->close();
        }

        // Notification B: Cross-Department Request Statuses
        $sqlCross = "SELECT c.course_code, s.section, s.requested_department, s.approval_status 
                     FROM class_schedules s
                     JOIN courses c ON s.course_id = c.id
                     WHERE s.department = ? AND s.program = ? 
                     AND s.requested_department IS NOT NULL AND s.requested_department != ''
                     ORDER BY s.id DESC LIMIT 10";
        $stmt = $conn->prepare($sqlCross);
        if ($stmt) {
            $stmt->bind_param("ss", $userDept, $userProgram);
            $stmt->execute();
            $res = $stmt->get_result();
            while($r = $res->fetch_assoc()) {
                if ($r['approval_status'] === 'Pending') {
                    $notifications[] = [
                        'color' => 'awaiting',
                        'title' => 'AWAITING REPLY',
                        'message' => "Requested Prof from " . $r['requested_department'] . " for " . $r['course_code'] . " (" . $r['section'] . ")"
                    ];
                } else if ($r['approval_status'] === 'Approved') {
                    $notifications[] = [
                        'color' => 'success',
                        'title' => 'APPROVED REQUEST',
                        'message' => $r['requested_department'] . " approved your request for " . $r['course_code'] . " (" . $r['section'] . ")"
                    ];
                } else if ($r['approval_status'] === 'Rejected') {
                    $notifications[] = [
                        'color' => 'danger',
                        'title' => 'REJECTED REQUEST',
                        'message' => $r['requested_department'] . " rejected your request for " . $r['course_code'] . " (" . $r['section'] . ")"
                    ];
                }
            }
            $stmt->close();
        }

        echo json_encode($notifications);
        exit;
    }

    // 3. Get Schedules 
    if ($action === 'get_schedules') {
        $ay = $_GET['ay'] ?? 'All';
        $sem = $_GET['sem'] ?? 'All';
        $prog = $_GET['prog'] ?? 'All';
        $day = $_GET['day'] ?? 'All'; 

        // Base Query - pulling units from schedules over courses, fixed specialization to major
        $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department,
                       c.course_code, c.descriptive_title, c.major,
                       COALESCE(s.units, c.units) as units,
                       COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours,
                       COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours,
                       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as professor,
                       r.room_name as assigned_room_display
                FROM class_schedules s
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN users u ON s.faculty_id = u.id
                LEFT JOIN rooms r ON s.room_id = r.id
                WHERE c.department = '" . $conn->real_escape_string($userDept) . "' 
                AND s.approval_status = 'Approved'"; // <--- Only show Approved

        // === ACCESS CONTROL LOGIC ===
        // If the user is a regular Faculty, they can ONLY see their own schedule
        if ($isFaculty) {
            $sql .= " AND s.faculty_id = " . intval($userId);
        } 
        // If Program Head, they see their program's approved schedules
        else if ($isProgramHead && !empty($userProgram)) {
            $sql .= " AND s.program = '" . $conn->real_escape_string($userProgram) . "'";
        }

        // Apply filters from dropdowns
        if ($ay !== 'All') { $sql .= " AND s.academic_year = '" . $conn->real_escape_string($ay) . "'"; }
        if ($sem !== 'All') { $sql .= " AND s.semester = '" . $conn->real_escape_string($sem) . "'"; }
        
        // Allow Program Heads to filter by specific program, otherwise lock Faculty to their own
        if (!$isFaculty && $prog !== 'All') { 
            $sql .= " AND s.program = '" . $conn->real_escape_string($prog) . "'"; 
        }

        $res = $conn->query($sql);
        $data = $res->fetch_all(MYSQLI_ASSOC);

        foreach($data as &$row) {
            if ($row['faculty_id'] == $tba_id || empty(trim($row['professor']))) {
                $row['professor'] = "TBA";
            } elseif ($row['faculty_id'] == $gened_id) {
                $row['professor'] = "General Education";
            }
        }
        unset($row);

        // Filter by Day in PHP
        if ($day !== 'All') {
            $filteredData = [];
            foreach($data as $row) {
                // Safely explode days string
                $daysString = $row['days'] ?? '';
                $daysArray = array_map('trim', explode(',', $daysString));
                if(in_array($day, $daysArray)) {
                    $filteredData[] = $row;
                }
            }
            echo json_encode($filteredData);
        } else {
            echo json_encode($data);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>