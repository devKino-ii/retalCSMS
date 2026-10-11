<?php
// Monitoring_api.php

// 🔥 STRICT CACHE CONTROL
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

session_start();

// Prevent HTML error output to avoid breaking JSON
ini_set('display_errors', 0);
error_reporting(0);

include '../db_connect.php';
header('Content-Type: application/json');

// --- STRICT ROLE-BASED ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
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

$userPos = $userRow['position'] ?? '';
$userProgram = $userRow['program'] ?? '';

// Check if user is a Program Head
$isProgramHead = (stripos($userPos, 'Program Head') !== false || stripos($userPos, 'Coordinator') !== false);

// Set Timezone
date_default_timezone_set('Asia/Manila');

try {
    $action = $_GET['action'] ?? '';

    // --- ACTION: GET FILTERS (LATEST AY & SEM ONLY) ---
    if ($action === 'init_filters') {
        $sql = "SELECT academic_year, semester FROM class_schedules WHERE department = ?";
        if ($isProgramHead && !empty($userProgram)) {
            $sql .= " AND program = '" . $conn->real_escape_string($userProgram) . "'";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 1";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $userDept);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if($r = $res->fetch_assoc()) {
            echo json_encode(['latest_ay' => $r['academic_year'], 'latest_sem' => $r['semester']]);
        } else {
            echo json_encode(['latest_ay' => 'N/A', 'latest_sem' => 'N/A']);
        }
        exit;
    }

    // --- ACTION: FETCH ALL SCHEDULES FOR THE MODAL TABLE ---
    if ($action === 'get_schedules') {
        $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department,
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
                WHERE s.department = ? AND s.approval_status = 'Approved'";
        
        // SECURITY LOCK: Only fetch schedules for their assigned program or their personal load
        if ($isProgramHead && !empty($userProgram)) {
            $progEsc = $conn->real_escape_string($userProgram);
            $sql .= " AND (s.program = '$progEsc' OR s.faculty_id = " . intval($userId) . ")";
        }
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $userDept);
        $stmt->execute();
        $result = $stmt->get_result();
        
        echo json_encode($result->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    // --- ACTION: FETCH COURSES FOR THE MODAL TABLE ---
    if ($action === 'find_courses') {
        $prog = $_GET['program'] ?? '';
        $year = $_GET['year_level'] ?? '';
        $sem  = $_GET['semester'] ?? '';

        $stmt = $conn->prepare("SELECT * FROM courses WHERE department = ? AND program LIKE ? AND year_level LIKE ? AND semester LIKE ?");
        $p="%$prog%"; $y="%$year%"; $s="%$sem%";
        $stmt->bind_param("ssss", $userDept, $p, $y, $s);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit;
    }

    // --- MAIN LOGIC: GET HYBRID MONITORING DATA ---
    $currentTime = date('H:i:s'); 
    $bufferTime = date('H:i:s', strtotime('+45 minutes')); 
    
    $currentDayFull = date('l'); 
    $currentDayShort = date('D'); 
    $dayMap = ['Mon'=>'M', 'Tue'=>'T', 'Wed'=>'W', 'Thu'=>'Th', 'Fri'=>'F', 'Sat'=>'S', 'Sun'=>'Su'];
    $currentDayLetter = $dayMap[$currentDayShort];
    
    $filterAY = '';
    $filterSem = '';

    // Fetch latest DB entry for the specific department
    $sqlAY = "SELECT academic_year, semester FROM class_schedules WHERE department = ?";
    if ($isProgramHead && !empty($userProgram)) {
        $sqlAY .= " AND program = '" . $conn->real_escape_string($userProgram) . "'";
    }
    $sqlAY .= " ORDER BY created_at DESC LIMIT 1";

    $stmtAY = $conn->prepare($sqlAY);
    $stmtAY->bind_param("s", $userDept);
    $stmtAY->execute();
    $resAY = $stmtAY->get_result();
    if($resAY && $resAY->num_rows > 0){
        $ayRow = $resAY->fetch_assoc();
        $filterAY = $ayRow['academic_year'];
        $filterSem = $ayRow['semester'];
    }

    // 2. Fetch ALL Rooms for the Department (Excluding ONLINE rooms)
    $stmtRooms = $conn->prepare("SELECT * FROM rooms WHERE department = ? AND UPPER(room_name) NOT LIKE '%ONLINE%' ORDER BY room_name");
    $stmtRooms->bind_param("s", $userDept);
    $stmtRooms->execute();
    $rooms = $stmtRooms->get_result();
    
    $data = [];

    if($rooms){
        while ($room = $rooms->fetch_assoc()) {
            $roomId = $room['id'];
            
            $scheduledClass = null;
            $actualOccupant = null;

            // QUERY 1: Who SHOULD be here? 
            $sqlSched = "SELECT s.*, c.course_code, c.descriptive_title, s.section as class_section, s.program as sched_program,
                             TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as full_name,
                             u.profile_picture as photo, u.department as prof_department
                          FROM class_schedules s
                          LEFT JOIN courses c ON s.course_id = c.id
                          LEFT JOIN users u ON s.faculty_id = u.id
                          WHERE s.room_id = ? AND s.department = ? AND s.academic_year = ? AND s.semester = ?
                          AND s.start_time <= ? AND s.end_time > ? 
                          AND s.approval_status = 'Approved' 
                          ORDER BY s.start_time ASC";
            
            $stmt1 = $conn->prepare($sqlSched);
            $stmt1->bind_param("isssss", $roomId, $userDept, $filterAY, $filterSem, $bufferTime, $currentTime);
            
            if($stmt1->execute()){
                $res1 = $stmt1->get_result();
                while ($row1 = $res1->fetch_assoc()) {
                    $dbDays = strtoupper($row1['days']); 
                    $isToday = false;
                    
                    if (strpos($dbDays, strtoupper($currentDayFull)) !== false || strpos($dbDays, strtoupper($currentDayShort)) !== false) {
                        $isToday = true;
                    } else {
                        $dayArray = array_map('trim', explode(',', $dbDays));
                        if (in_array(strtoupper($currentDayLetter), $dayArray, true)) {
                            $isToday = true;
                        }
                        if ($currentDayLetter == 'T' || $currentDayLetter == 'Th') {
                            if (in_array('TTH', $dayArray, true) || strpos(str_replace(' ', '', $dbDays), 'TTH') !== false) {
                                 $isToday = true;
                            }
                        }
                    }

                    if ($isToday) {
                        if (!empty($row1['photo'])) {
                            $row1['photo'] = base64_encode($row1['photo']);
                        }
                        $scheduledClass = $row1;
                        break; 
                    }
                }
            }
            $stmt1->close();

            // QUERY 2: Who IS ACTUALLY here? (LEFT JOIN accommodates GUESTS)
            $sqlActual = "SELECT rl.faculty_id, rl.time_in, rl.guest_name, rl.guest_college, rl.guest_program, rl.guest_section,
                             TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as full_name,
                             u.profile_picture as photo, u.department as prof_department, u.program as prof_program
                          FROM room_logs rl
                          LEFT JOIN users u ON rl.faculty_id = u.id
                          WHERE rl.room_id = ? AND rl.status = 'Active'
                          ORDER BY rl.time_in DESC LIMIT 1";
            
            $stmt2 = $conn->prepare($sqlActual);
            $stmt2->bind_param("s", $room['room_name']); 
            if($stmt2->execute()){
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    
                    // If faculty_id is NULL, inject the guest info into the JSON object
                    if ($row2['faculty_id'] === null && !empty($row2['guest_name'])) {
                        $row2['full_name'] = $row2['guest_name'];
                        $row2['prof_department'] = $row2['guest_college'];
                        $row2['prof_program'] = $row2['guest_program'] . ' - ' . $row2['guest_section'];
                        $row2['is_guest'] = true;
                    } else {
                        $row2['is_guest'] = false;
                    }
                    
                    if (!empty($row2['photo'])) {
                        $row2['photo'] = base64_encode($row2['photo']);
                    }
                    $actualOccupant = $row2;
                }
            }
            $stmt2->close();

            // DETERMINE THE HYBRID STATE & APPLY RESTRICTIONS
            $state = 'Vacant';
            $isRestricted = false;

            if ($isProgramHead) {
                $hasSched = !empty($scheduledClass);
                $hasOcc = !empty($actualOccupant);

                // Does the schedule belong to their program OR their personal load?
                $schedIsMine = $hasSched && ($scheduledClass['sched_program'] === $userProgram || $scheduledClass['faculty_id'] == $userId);
                
                // Does the Ad-Hoc occupant belong to their program OR is it them personally?
                $occupantIsMine = $hasOcc && (
                    (isset($actualOccupant['prof_program']) && strpos($actualOccupant['prof_program'], $userProgram) !== false) || 
                    ($actualOccupant['faculty_id'] == $userId)
                );

                // If there is ANY activity in the room, but NEITHER the schedule NOR the occupant belong to this Program Head -> Lock it down
                if ($hasSched || $hasOcc) {
                    if (!$schedIsMine && !$occupantIsMine) {
                        $isRestricted = true;
                    }
                }
            }

            if ($isRestricted) {
                $state = 'Restricted';
                $scheduledClass = null; // Hide the schedule data for safety
                $actualOccupant = null; // Hide the occupant data for safety
            } else {
                if ($actualOccupant) {
                    if ($scheduledClass && $actualOccupant['faculty_id'] !== null && $actualOccupant['faculty_id'] == $scheduledClass['faculty_id']) {
                        $state = 'Verified'; 
                    } else {
                        $state = 'Ad-Hoc'; 
                    }
                } else {
                    if ($scheduledClass) {
                        $state = 'Awaiting'; 
                    }
                }
            }

            $data[] = [
                'room_info' => $room,
                'state' => $state,
                'scheduled_class' => $scheduledClass,
                'actual_occupant' => $actualOccupant
            ];
        }
    }

    // Sort Priorities: Restricted -> Verified -> Ad-Hoc -> Awaiting -> Vacant
    usort($data, function($a, $b) {
        $priority = ['Restricted' => 1, 'Verified' => 2, 'Ad-Hoc' => 3, 'Awaiting' => 4, 'Vacant' => 5];
        if ($priority[$a['state']] === $priority[$b['state']]) {
            return strcmp($a['room_info']['room_name'], $b['room_info']['room_name']);
        }
        return $priority[$a['state']] <=> $priority[$b['state']];
    });

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>