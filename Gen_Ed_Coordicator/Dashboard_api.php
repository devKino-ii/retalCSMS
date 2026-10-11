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

// Set timezone for Next Class and Ongoing Classes calculation
date_default_timezone_set('Asia/Manila');

// =========================================================
// Auto-schema checker & Cleanup
// =========================================================
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'requested_room_department'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE class_schedules ADD COLUMN requested_room_department VARCHAR(100) NULL AFTER requested_department");
    }
    
    $conn->query("ALTER TABLE class_schedules MODIFY room_id INT NULL");
    $conn->query("UPDATE class_schedules SET room_id = NULL WHERE room_id IN (SELECT id FROM rooms WHERE room_name = 'TBA Room')");
    $conn->query("DELETE FROM rooms WHERE room_name = 'TBA Room'");
} catch (Exception $e) { /* Ignore gracefully */ }

try {
    $stmtUser = $conn->prepare("SELECT position, program FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userRow = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    $userPosition = $userRow['position'] ?? '';
    $userProgram = $userRow['program'] ?? '';

    // --- GEN ED DETECTOR ---
    $isGenEd = ($userDept === 'General Education' || stripos($userPosition, 'Gen') !== false || (stripos($userPosition, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));

    // --- SPECIAL ID HELPER ---
    function getSpecialFacultyId($conn, $name) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE first_name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $res->num_rows > 0) return $res->fetch_assoc()['id'];
        
        $username = strtolower(str_replace(' ', '', $name)) . '_sys';
        $email = $username . "@urs.edu.ph";
        $surname = "System"; $sex = "Other"; $emp_id = strtoupper($username); $sys_val = "System";
        
        $insert = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, sex, employee_id, department, position, program) VALUES (?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($insert) {
            $insert->bind_param("sssssssss", $username, $email, $surname, $name, $sex, $emp_id, $sys_val, $sys_val, $sys_val);
            $insert->execute();
            return $insert->insert_id;
        }
        return 0;
    }

    $tba_id = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    $action = $_GET['action'] ?? '';

    // =========================================================
    // 1. Get Initial Data
    // =========================================================
    if ($action === 'init_data') {
        
        $active_ay = ''; $active_sem = '';
        $ayQueryStr = "SELECT academic_year, semester FROM class_schedules WHERE requested_department = 'General Education' OR faculty_id = $gened_id ORDER BY id DESC LIMIT 1";
        $resAY = $conn->query($ayQueryStr);
        if($resAY && $resAY->num_rows > 0) {
            $row = $resAY->fetch_assoc();
            $active_ay = $row['academic_year'];
            $active_sem = $row['semester'];
        }
        
        $my_workload = 0;
        $stmtW = $conn->prepare("SELECT COALESCE(SUM(COALESCE(s.units, c.units)), 0) as total_units FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.faculty_id = ? AND s.academic_year = ? AND s.semester = ? AND s.approval_status != 'Pending'");
        $stmtW->bind_param("iss", $userId, $active_ay, $active_sem);
        $stmtW->execute();
        $resW = $stmtW->get_result();
        $my_workload = $resW ? ($resW->fetch_assoc()['total_units'] ?? 0) : 0;

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

        $depts = [];
        $q = $conn->query("SELECT DISTINCT s.department FROM class_schedules s JOIN users u ON s.faculty_id = u.id WHERE s.requested_department = 'General Education' OR u.department = 'General Education' ORDER BY s.department");
        if ($q) {
            while($r = $q->fetch_assoc()) $depts[] = $r['department'];
        }

        $ays = [];
        $q = $conn->query("SELECT DISTINCT academic_year FROM class_schedules WHERE requested_department = 'General Education' OR faculty_id = $gened_id ORDER BY academic_year DESC");
        if ($q) {
            while($r = $q->fetch_assoc()) $ays[] = $r['academic_year'];
        }

        $rooms = $conn->query("SELECT DISTINCT r.* FROM rooms r JOIN class_schedules s ON r.id = s.room_id WHERE s.requested_department = 'General Education' OR r.department = 'University' ORDER BY r.room_name")->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            'rooms' => $rooms,            
            'academic_years' => $ays,
            'departments' => $depts,
            'active_ay' => $active_ay,
            'active_sem' => $active_sem,
            'my_workload' => $my_workload,
            'next_class' => $next_class
        ]);
        exit;
    }

    // =========================================================
    // 2. Get Notifications
    // =========================================================
    if ($action === 'get_notifications') {
        $notifications = [];

        $sqlPending = "SELECT s.department, s.program, s.section, COUNT(*) as subject_count 
                       FROM class_schedules s 
                       WHERE s.requested_department = 'General Education' AND s.approval_status = 'Pending' 
                       GROUP BY s.department, s.program, s.section";
        $qPending = $conn->query($sqlPending);
        if ($qPending) {
            while ($r = $qPending->fetch_assoc()) {
                $notifications[] = [
                    'color' => 'pending',
                    'title' => 'ACTION REQUIRED',
                    'message' => "Schedule request for <b>" . $r['program'] . " " . $r['section'] . "</b> (" . $r['department'] . "). " . $r['subject_count'] . " subjects waiting."
                ];
            }
        }

        $sqlHist = "SELECT * FROM activity_logs WHERE department = 'General Education' AND module = 'Approval' ORDER BY created_at DESC LIMIT 5";
        $qHist = $conn->query($sqlHist);
        if ($qHist) {
            while ($r = $qHist->fetch_assoc()) {
                $approved = ($r['action'] === 'UPDATE');
                $notifications[] = [
                    'color' => $approved ? 'success' : 'danger',
                    'title' => $approved ? 'APPROVED REQUEST' : 'REJECTED REQUEST',
                    'message' => str_replace(['✅ APPROVED: ', '❌ REJECTED: '], '', $r['details']),
                    'date' => date("M j, g:i A", strtotime($r['created_at']))
                ];
            }
        }

        echo json_encode($notifications);
        exit;
    }

    // =========================================================
    // 3. Get Schedules
    // =========================================================
    if ($action === 'get_schedules') {
        $ay = $_GET['ay'] ?? 'All';
        $sem = $_GET['sem'] ?? 'All';
        $host_dept = $_GET['dept'] ?? 'All';
        $day = $_GET['day'] ?? 'All'; 

        $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department,
                       c.course_code, c.descriptive_title, c.major,
                       COALESCE(s.units, c.units) as units,
                       COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours,
                       COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours,
                       TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.middle_initial, ''), ' ', COALESCE(u.surname, ''))) as professor,
                       r.room_name as assigned_room_display
                FROM class_schedules s
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN users u ON s.faculty_id = u.id
                LEFT JOIN rooms r ON s.room_id = r.id
                WHERE (
                    s.department = 'General Education' 
                    OR (s.requested_department = 'General Education' AND s.approval_status = 'Approved')
                    OR (s.faculty_id = $gened_id AND s.approval_status = 'Approved')
                    OR s.faculty_id = " . intval($userId) . "
                )";

        if ($ay !== 'All') { $sql .= " AND s.academic_year = '" . $conn->real_escape_string($ay) . "'"; }
        if ($sem !== 'All') { $sql .= " AND s.semester = '" . $conn->real_escape_string($sem) . "'"; }
        if ($host_dept !== 'All') { $sql .= " AND s.department = '" . $conn->real_escape_string($host_dept) . "'"; }

        $res = $conn->query($sql);
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        foreach($data as &$row) {
            
            // Check if the professor is unassigned
            $is_unassigned_prof = ($row['faculty_id'] == $tba_id || $row['faculty_id'] == $gened_id || empty(trim($row['professor'])));

            if ($is_unassigned_prof) {
                $row['faculty_id'] = '0';
                $row['professor'] = "⏳ Pending Assignment";
                // Force status to pending so UI catches it as yellow and alerts you!
                $row['approval_status'] = 'Pending';
            } else {
                if (isset($row['approval_status']) && $row['approval_status'] === 'Pending') {
                    $row['professor'] = "⏳ Pending Dean";
                }
            }
            
            if (empty($row['room_id']) || $row['room_id'] == '0') {
                $row['room_id'] = '0'; 
            } elseif (!empty($row['assigned_room_display'])) {
                $row['assigned_room_display'] = $row['assigned_room_display'];
            }
        }

        if ($day !== 'All') {
            $filteredData = [];
            foreach($data as $row) {
                $daysArray = explode(',', $row['days']);
                $daysArray = array_map('trim', $daysArray);
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

    // =========================================================
    // 4. Get Ongoing Gen Ed Classes (Direct Pull)
    // =========================================================
    if ($action === 'get_ongoing_classes') {
        $currentDayShort = date('D');
        $currentDayFull = date('l');
        $dayMap = ['Mon'=>'M', 'Tue'=>'T', 'Wed'=>'W', 'Thu'=>'Th', 'Fri'=>'F', 'Sat'=>'S', 'Sun'=>'Su'];
        $currentDayLetter = $dayMap[$currentDayShort];
        $currentTime = date('H:i:s');

        // Pull any approved Gen Ed schedule that is currently within its time block
        $sql = "SELECT s.start_time, s.end_time, s.days, s.section, c.course_code, r.room_name 
                FROM class_schedules s
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN rooms r ON s.room_id = r.id
                WHERE (s.department = 'General Education' OR s.requested_department = 'General Education')
                AND s.approval_status = 'Approved'
                AND s.start_time <= ? AND s.end_time > ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $currentTime, $currentTime);
        $stmt->execute();
        $res = $stmt->get_result();

        $ongoing = [];
        while ($row = $res->fetch_assoc()) {
            $dbDays = strtoupper($row['days']);
            $isToday = false;
            
            // Match the day
            $dayArray = array_map('trim', explode(',', $dbDays));
            if (in_array(strtoupper($currentDayLetter), $dayArray) || 
                strpos($dbDays, strtoupper($currentDayFull)) !== false || 
                strpos($dbDays, strtoupper($currentDayShort)) !== false) {
                $isToday = true;
            }
            if (($currentDayLetter == 'T' || $currentDayLetter == 'Th') && in_array('TTH', $dayArray)) {
                $isToday = true;
            }

            if ($isToday) {
                $ongoing[] = [
                    'room_name' => $row['room_name'] ?? 'TBA Room',
                    'course_code' => $row['course_code'],
                    'section' => $row['section']
                ];
            }
        }
        echo json_encode($ongoing);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>