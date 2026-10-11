<?php
// Monitoring_api.php

// 🔥 STRICT CACHE CONTROL
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

session_start();
ini_set('display_errors', 0);
error_reporting(0);

include '../db_connect.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? 'Staff'; 

// OVERRIDE DEPARTMENT IF ADMIN
$isAdmin  = (stripos($userPos, 'Admin') !== false); 
$isDean   = (stripos($userPos, 'Dean') !== false);
if ($isAdmin && !empty($_GET['department'])) {
    $userDept = $_GET['department'];
}

date_default_timezone_set('Asia/Manila');
session_write_close();

try {
    // =========================================================
    // ACTION: FORCE VACATE (POST REQUEST)
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = $_POST['action'] ?? '';
        
        if ($postAction === 'force_vacate') {
            // Security Lock: Only Deans & Admins can force vacate
            if (!$isDean && !$isAdmin) {
                echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Only Deans and Admins can force vacate.']);
                exit;
            }
            
            $room_name = trim($_POST['room_name'] ?? '');
            if (empty($room_name)) {
                echo json_encode(['status' => 'error', 'message' => 'Room name is required.']);
                exit;
            }
            
            // Force checkout active log for this room
            $stmt = $conn->prepare("UPDATE room_logs SET time_out = NOW(), status = 'Completed' WHERE room_id = ? AND status = 'Active'");
            $stmt->bind_param("s", $room_name);
            
            if ($stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => "$room_name has been forcibly vacated."]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to vacate room. Database error.']);
            }
            $stmt->close();
            exit;
        }
    }

    $action = $_GET['action'] ?? '';

    // --- ACTION: GET 7-DAY ROOM LOGS FOR MODAL ---
    if ($action === 'get_room_logs') {
        $room_name = $_GET['room_name'] ?? '';
        
        $sql = "SELECT rl.time_in, rl.time_out, rl.status, rl.guest_name, rl.guest_college, rl.guest_program, rl.guest_section,
                       u.first_name, u.surname, u.department as prof_department
                FROM room_logs rl
                LEFT JOIN users u ON rl.faculty_id = u.id
                WHERE rl.room_id = ? 
                AND rl.time_in >= NOW() - INTERVAL 7 DAY
                ORDER BY rl.time_in DESC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $room_name);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $logs = [];
        while ($row = $res->fetch_assoc()) {
            if (!empty($row['guest_name'])) {
                $name = htmlspecialchars($row['guest_name']) . ' <br><span style="color:#3b82f6; font-size:9px; text-transform:uppercase;"><i class="fas fa-user-graduate"></i> Student Guest</span>';
                $dept = htmlspecialchars($row['guest_program']) . ' - ' . htmlspecialchars($row['guest_section']);
            } else {
                $name = 'PROF. ' . htmlspecialchars(strtoupper(trim($row['first_name'] . ' ' . $row['surname'])));
                $dept = htmlspecialchars($row['prof_department']);
            }
            
            $logs[] = [
                'name' => $name,
                'department' => $dept,
                'time_in' => date('M d, Y h:i A', strtotime($row['time_in'])),
                'time_out' => $row['time_out'] ? date('M d, Y h:i A', strtotime($row['time_out'])) : 'Active',
                'status' => $row['status'],
                'raw_date' => date('Y-m-d', strtotime($row['time_in']))
            ];
        }
        echo json_encode($logs);
        exit;
    }

    // --- ACTION: GET FILTERS (LATEST AY & SEM ONLY) ---
    if ($action === 'init_filters') {
        $stmt = $conn->prepare("SELECT academic_year, semester FROM class_schedules WHERE department = ? ORDER BY created_at DESC LIMIT 1");
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

    // --- MAIN LOGIC: GET HYBRID MONITORING DATA ---
    // Setup Flexible Time Buffers
    $currentTime = date('H:i:s'); 
    $bufferTime = date('H:i:s', strtotime('+45 minutes')); 
    
    // Setup Flexible Day Matching
    $currentDayFull = date('l'); 
    $currentDayShort = date('D'); 
    $dayMap = ['Mon'=>'M', 'Tue'=>'T', 'Wed'=>'W', 'Thu'=>'Th', 'Fri'=>'F', 'Sat'=>'S', 'Sun'=>'Su'];
    $currentDayLetter = $dayMap[$currentDayShort];
    
    $filterAY = '';
    $filterSem = '';

    $stmtAY = $conn->prepare("SELECT academic_year, semester FROM class_schedules WHERE department = ? ORDER BY created_at DESC LIMIT 1");
    $stmtAY->bind_param("s", $userDept);
    $stmtAY->execute();
    $resAY = $stmtAY->get_result();
    if($resAY && $resAY->num_rows > 0){
        $ayRow = $resAY->fetch_assoc();
        $filterAY = $ayRow['academic_year'];
        $filterSem = $ayRow['semester'];
    }

    // 🔥 FILTERS OUT "ONLINE" ROOMS
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
            $sqlSched = "SELECT s.*, c.course_code, c.descriptive_title, s.section as class_section,
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

            // QUERY 2: Who IS ACTUALLY here? (LEFT JOIN for Student Guests)
            $sqlActual = "SELECT rl.faculty_id, rl.time_in, rl.guest_name, rl.guest_college, rl.guest_program, rl.guest_section,
                             TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as full_name,
                             u.profile_picture as photo, u.department as prof_department
                          FROM room_logs rl
                          LEFT JOIN users u ON rl.faculty_id = u.id
                          WHERE rl.room_id = ? AND rl.status = 'Active'
                          ORDER BY rl.time_in DESC LIMIT 1";
            
            $stmt2 = $conn->prepare($sqlActual);
            $stmt2->bind_param("s", $room['room_name']); 
            if($stmt2->execute()){
                $res2 = $stmt2->get_result();
                if ($row2 = $res2->fetch_assoc()) {
                    if ($row2['faculty_id'] === null && !empty($row2['guest_name'])) {
                        $row2['full_name'] = $row2['guest_name'];
                        $row2['prof_department'] = $row2['guest_college'];
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

            // DETERMINE THE HYBRID STATE
            $state = 'Vacant';
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

            $data[] = [
                'room_info' => $room,
                'state' => $state,
                'scheduled_class' => $scheduledClass,
                'actual_occupant' => $actualOccupant
            ];
        }
    }

    // Sort Priorities: Verified -> Ad-Hoc -> Awaiting -> Vacant
    usort($data, function($a, $b) {
        $priority = ['Verified' => 1, 'Ad-Hoc' => 2, 'Awaiting' => 3, 'Vacant' => 4];
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