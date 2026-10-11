<?php
// Prevent back-button caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../db_connect.php'; 

// Identify if current user is a Student Guest
$isStudent = (isset($_SESSION['position']) && $_SESSION['position'] === 'Student');
date_default_timezone_set('Asia/Manila');

// --- 1. AJAX LIVE-SYNC ENGINE ---
if (isset($_GET['ajax_get_current_state'])) {
    ob_clean(); // Prevent invisible characters from breaking the JSON
    header('Content-Type: application/json');
    
    $check_room = $_GET['ajax_get_current_state'];
    
    $stmt = $conn->prepare("SELECT faculty_id, guest_name FROM room_logs WHERE room_id = ? AND status = 'Active' ORDER BY time_in DESC LIMIT 1");
    $stmt->bind_param("s", $check_room);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'occupied' => true, 
            'faculty_id' => (string)$row['faculty_id'], 
            'guest_name' => (string)$row['guest_name']
        ]);
    } else {
        echo json_encode([
            'occupied' => false, 
            'faculty_id' => '', 
            'guest_name' => ''
        ]);
    }
    $stmt->close();
    exit(); 
}

// --- 2. SECURITY & AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['room_id']) && trim($_GET['room_id']) !== '') {
        $_SESSION['pending_room'] = trim($_GET['room_id']);
    }
    session_write_close();
    header("Location: Login_index.php"); 
    exit();
}

$faculty_id = $_SESSION['user_id'];
$faculty_name = $_SESSION['full_name'] ?? 'Faculty Member';
$userDept = $_SESSION['department'] ?? '';
$profile_pic = $_SESSION['profile_picture'] ?? null;

// --- 3. GLOBAL ACTIVE ROOM CHECK ---
$my_active_room = null;
if ($isStudent) {
    $check_mine = $conn->prepare("SELECT room_id FROM room_logs WHERE guest_name = ? AND status = 'Active' LIMIT 1");
    $check_mine->bind_param("s", $_SESSION['full_name']);
} else {
    $check_mine = $conn->prepare("SELECT room_id FROM room_logs WHERE faculty_id = ? AND status = 'Active' LIMIT 1");
    $check_mine->bind_param("i", $faculty_id);
}
$check_mine->execute();
$res_mine = $check_mine->get_result();
if ($row_mine = $res_mine->fetch_assoc()) {
    $my_active_room = $row_mine['room_id'];
}
$check_mine->close();

// --- 4. CLEAR SCAN FUNCTION ---
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    if ($my_active_room === null) {
        unset($_SESSION['current_active_room']);
    }
    session_write_close();
    header("Location: room_monitoring.php");
    exit();
}

// --- 5. URL SCRUBBER & AUTO-CHECK-IN TRIGGER ---
$redirect_needed = false;
if ($my_active_room !== null) {
    if (!isset($_SESSION['current_active_room']) || $_SESSION['current_active_room'] !== $my_active_room) {
        $_SESSION['current_active_room'] = $my_active_room;
        $redirect_needed = true;
    }
    if (isset($_GET['room_id']) || isset($_SESSION['pending_room'])) {
        unset($_SESSION['pending_room']);
        $redirect_needed = true;
    }
} else {
    if (isset($_SESSION['pending_room'])) {
        $_SESSION['current_active_room'] = htmlspecialchars(trim($_SESSION['pending_room']));
        $_SESSION['trigger_auto_checkin'] = true; 
        unset($_SESSION['pending_room']);
        $redirect_needed = true;
    }
    if (isset($_GET['room_id']) && trim($_GET['room_id']) !== '') {
        $_SESSION['current_active_room'] = htmlspecialchars(trim($_GET['room_id']));
        $_SESSION['trigger_auto_checkin'] = true; 
        $redirect_needed = true;
    }
}

if ($redirect_needed) {
    session_write_close();
    header("Location: room_monitoring.php");
    exit();
}

$room_id = $_SESSION['current_active_room'] ?? '';
$has_room = ($room_id !== '');

// --- 6. FETCH ROOM DETAILS & LOGO ---
$room_dept = '';
$room_logo = '';
$room_actual_id = 0;

if ($has_room) {
    $dept_stmt = $conn->prepare("SELECT id, department FROM rooms WHERE room_name = ?");
    $dept_stmt->bind_param("s", $room_id);
    $dept_stmt->execute();
    $dept_res = $dept_stmt->get_result();
    
    if ($dept_row = $dept_res->fetch_assoc()) {
        $room_actual_id = $dept_row['id'];
        $room_dept = $dept_row['department'];
        
        $logos = [
            'College of Science'               => '../Logo COS.png',
            'College of Education'             => '../Logo COE.png',
            'College of Engineering'           => '../Logo COENG.png',
            'College of Industrial Technology' => '../Logo CIT.png'
        ];
        
        if (isset($logos[$room_dept]) && file_exists($logos[$room_dept])) {
            $room_logo = $logos[$room_dept];
        }
    }
    $dept_stmt->close();
}

// --- 7. DETERMINE CURRENT ROOM STATE & HANDLING TAKEOVERS ---
$active_faculty_id = null;
$active_guest_name = null;
$active_faculty_name = '';
$time_in_display = '';
$show_takeover_prompt = false;

if ($has_room) {
    $check_active = $conn->prepare("
        SELECT rl.faculty_id, rl.guest_name, rl.time_in, u.first_name, u.surname 
        FROM room_logs rl 
        LEFT JOIN users u ON rl.faculty_id = u.id 
        WHERE rl.room_id = ? AND rl.status = 'Active' 
        ORDER BY rl.time_in DESC LIMIT 1
    ");
    $check_active->bind_param("s", $room_id);
    $check_active->execute();
    $res_active = $check_active->get_result();

    if ($row_active = $res_active->fetch_assoc()) {
        $active_faculty_id = $row_active['faculty_id'];
        $active_guest_name = $row_active['guest_name'];
        if ($active_faculty_id !== null) {
            $active_faculty_name = trim(($row_active['first_name'] ?? '') . ' ' . ($row_active['surname'] ?? ''));
        } else {
            $active_faculty_name = ($active_guest_name ?? 'Guest') . " (Student Guest)";
        }
        $time_in_display = date('h:i A', strtotime($row_active['time_in']));
    }
    $check_active->close();

    if (isset($_SESSION['trigger_auto_checkin'])) {
        unset($_SESSION['trigger_auto_checkin']); 
        
        if ($active_faculty_id === null && $active_guest_name === null) {
            if ($isStudent) {
                $insert_stmt = $conn->prepare("INSERT INTO room_logs (faculty_id, room_id, time_in, status, guest_name, guest_college, guest_program, guest_section) VALUES (NULL, ?, NOW(), 'Active', ?, ?, ?, ?)");
                $insert_stmt->bind_param("sssss", $room_id, $_SESSION['full_name'], $_SESSION['department'], $_SESSION['program'], $_SESSION['section']);
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO room_logs (faculty_id, room_id, time_in, status) VALUES (?, ?, NOW(), 'Active')");
                $insert_stmt->bind_param("is", $faculty_id, $room_id);
            }
            $insert_stmt->execute();
            $insert_stmt->close();
            
            $_SESSION['play_success_sound'] = true;
            session_write_close();
            header("Location: room_monitoring.php"); 
            exit();
        } else {
            $is_mine = $isStudent ? ($active_guest_name === $_SESSION['full_name']) : ($active_faculty_id == $faculty_id);
            if (!$is_mine && !$isStudent) {
                $show_takeover_prompt = true;
            }
        }
    } else {
        $is_mine = $isStudent ? ($active_guest_name === $_SESSION['full_name']) : ($active_faculty_id == $faculty_id);
        if (($active_faculty_id !== null || $active_guest_name !== null) && !$is_mine && !$isStudent) {
            $show_takeover_prompt = true;
        }
    }
}

if ($isStudent) {
    $is_currently_logged_in = ($active_guest_name === $_SESSION['full_name']);
} else {
    $is_currently_logged_in = ($active_faculty_id == $faculty_id);
}

// --- 8. FETCH CURRENT CLASS SCHEDULE ---
$current_schedule = null;
if ($has_room && $room_actual_id > 0) {
    $bufferTime = date('H:i:s', strtotime('+45 minutes')); 
    $nowTime = date('H:i:s'); 
    $currentDayFull = date('l'); 
    $currentDayShort = date('D'); 
    $dayMap = ['Mon'=>'M', 'Tue'=>'T', 'Wed'=>'W', 'Thu'=>'Th', 'Fri'=>'F', 'Sat'=>'S', 'Sun'=>'Su'];
    $currentDayLetter = $dayMap[$currentDayShort];

    $query_fac_id = $active_faculty_id ?: $faculty_id;
    if ($isStudent || (!$active_faculty_id && $active_guest_name)) {
        $sqlSched = "SELECT s.section, s.start_time, s.end_time, s.days, c.course_code, c.descriptive_title FROM class_schedules s LEFT JOIN courses c ON s.course_id = c.id WHERE s.room_id = ? AND s.start_time <= ? AND s.end_time > ? ORDER BY s.start_time ASC";
        $stmtSched = $conn->prepare($sqlSched);
        $stmtSched->bind_param("iss", $room_actual_id, $bufferTime, $nowTime);
    } else {
        $sqlSched = "SELECT s.section, s.start_time, s.end_time, s.days, c.course_code, c.descriptive_title FROM class_schedules s LEFT JOIN courses c ON s.course_id = c.id WHERE s.room_id = ? AND s.faculty_id = ? AND s.start_time <= ? ORDER BY s.start_time ASC";
        $stmtSched = $conn->prepare($sqlSched);
        $stmtSched->bind_param("iis", $room_actual_id, $query_fac_id, $bufferTime);
    }
    
    if ($stmtSched->execute()) {
        $resSched = $stmtSched->get_result();
        while ($rowSched = $resSched->fetch_assoc()) {
            $dbDays = strtoupper($rowSched['days']); 
            $isToday = false;
            if (strpos($dbDays, strtoupper($currentDayFull)) !== false || strpos($dbDays, strtoupper($currentDayShort)) !== false) $isToday = true;
            else {
                $dayArray = array_map('trim', explode(',', $dbDays));
                if (in_array(strtoupper($currentDayLetter), $dayArray, true)) $isToday = true;
                if ($currentDayLetter == 'T' || $currentDayLetter == 'Th') {
                    if (in_array('TTH', $dayArray, true) || strpos(str_replace(' ', '', $dbDays), 'TTH') !== false) $isToday = true;
                }
            }
            if ($isToday) { $current_schedule = $rowSched; break; }
        }
    }
    $stmtSched->close();
}

// --- 9. POST HANDLERS ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $has_room) {
    if ($_POST['action'] === 'check_out') {
        if ($isStudent) {
            $update_stmt = $conn->prepare("UPDATE room_logs SET time_out = NOW(), status = 'Completed' WHERE guest_name = ? AND room_id = ? AND status = 'Active'");
            $update_stmt->bind_param("ss", $_SESSION['full_name'], $room_id);
        } else {
            $update_stmt = $conn->prepare("UPDATE room_logs SET time_out = NOW(), status = 'Completed' WHERE faculty_id = ? AND room_id = ? AND status = 'Active'");
            $update_stmt->bind_param("is", $faculty_id, $room_id);
        }
        $update_stmt->execute();
        $update_stmt->close();
        unset($_SESSION['current_active_room']); 
    } elseif ($_POST['action'] === 'takeover' || $_POST['action'] === 'log_in') {
        if ($_POST['action'] === 'takeover' && $isStudent) {
            session_write_close();
            header("Location: room_monitoring.php");
            exit();
        }

        if ($_POST['action'] === 'takeover') {
            $kick_stmt = $conn->prepare("UPDATE room_logs SET time_out = NOW(), status = 'Completed' WHERE room_id = ? AND status = 'Active'");
            $kick_stmt->bind_param("s", $room_id);
            $kick_stmt->execute();
            $kick_stmt->close();
        }
        
        if ($isStudent) {
            $insert_stmt = $conn->prepare("INSERT INTO room_logs (faculty_id, room_id, time_in, status, guest_name, guest_college, guest_program, guest_section) VALUES (NULL, ?, NOW(), 'Active', ?, ?, ?, ?)");
            $insert_stmt->bind_param("sssss", $room_id, $_SESSION['full_name'], $_SESSION['department'], $_SESSION['program'], $_SESSION['section']);
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO room_logs (faculty_id, room_id, time_in, status) VALUES (?, ?, NOW(), 'Active')");
            $insert_stmt->bind_param("is", $faculty_id, $room_id);
        }
        $insert_stmt->execute();
        $insert_stmt->close();
        $_SESSION['play_success_sound'] = true;
    }
    session_write_close();
    header("Location: room_monitoring.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Room Monitoring - COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* General Layout */
        ::-webkit-scrollbar { display: none; }
        * { -ms-overflow-style: none; scrollbar-width: none; margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; -webkit-tap-highlight-color: transparent; }
        
        body { background-color: #f8fafc; min-height: 100vh; display: flex; justify-content: center; }
        .mobile-wrapper { width: 100%; min-height: 100vh; display: flex; flex-direction: column; background-color: #fafcff; position: relative; }
        @media (min-width: 480px) { body { background-color: #cbd5e1; align-items: center; } .mobile-wrapper { max-width: 414px; height: 90vh; border-radius: 40px; border: 10px solid #1a202c; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); } }
        
        .main-content { flex-grow: 1; padding: 20px 25px 40px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; }

        /* Header Match */
        .app-header { background-color: <?php echo $isStudent ? '#1e3a8a' : '#0f1c3f'; ?>; padding: 30px 25px 25px; display: flex; justify-content: space-between; align-items: center; border-radius: 0 0 25px 25px; position: sticky; top: 0; z-index: 10; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .back-btn { background-color: rgba(255, 255, 255, 0.1); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; justify-content: center; align-items: center; text-decoration: none; font-size: 1.1rem; transition: background 0.2s; }
        .back-btn:active { background-color: rgba(255, 255, 255, 0.2); }
        
        /* Clickable User Profile Match */
        .user-profile { display: flex; align-items: center; gap: 15px; color: white; text-decoration: none; cursor: pointer; }
        .profile-text { text-align: right; }
        .profile-text .role { font-size: 0.7rem; color: #94a3b8; letter-spacing: 1px; text-transform: uppercase; font-weight: 600; display: block; margin-bottom: 2px; }
        .profile-text .name { font-weight: 700; font-size: 1.05rem; display: block; }
        .avatar-container { width: 50px; height: 50px; border-radius: 50%; background: #4a5568; display: flex; align-items: center; justify-content: center; border: 2px solid white; overflow: hidden; }
        .avatar-container img { width: 100%; height: 100%; object-fit: cover; }

        /* ========================================= */
        /* NO SCAN STATE UI                          */
        /* ========================================= */
        .nfc-no-scan-circle { position: relative; width: 90px; height: 90px; background-color: #0f1c3f; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 30px auto 20px; box-shadow: 0 8px 20px rgba(15, 28, 63, 0.3); z-index: 1; }
        .nfc-no-scan-circle .fa-wifi { color: #ffffff; font-size: 2.8rem; transform: rotate(45deg); z-index: 10; }
        .no-scan-pulse { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border-radius: 50%; border: 2px solid #0f1c3f; opacity: 0; animation: expandWave 2.5s linear infinite; z-index: 0; }
        .no-scan-pulse:nth-child(2) { animation-delay: 0.8s; }
        .no-scan-pulse:nth-child(3) { animation-delay: 1.6s; }

        .no-scan-title { font-size: 3.2rem; color: #0f1c3f; font-weight: 900; margin-bottom: 5px; text-align: center; letter-spacing: -1px; }
        .no-scan-subtitle { font-size: 1.15rem; color: #64748b; margin-bottom: 40px; text-align: center; }

        .locked-box { background-color: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 20px; padding: 30px 25px; text-align: center; width: 100%; max-width: 350px; margin: 0 auto; }
        .locked-box .fa-lock { font-size: 2.8rem; color: #94a3b8; margin-bottom: 15px; }
        .locked-box h3 { color: #334155; font-size: 1.3rem; font-weight: 800; margin-bottom: 10px; }
        .locked-box p { color: #64748b; font-size: 1rem; line-height: 1.5; font-weight: 500; }

        /* ========================================= */
        /* SCANNED STATE UI                          */
        /* ========================================= */
        .scanned-logo-container {
            position: relative; width: 100px; height: 100px; margin: 20px auto;
            border-radius: 50%; background: white; border: 1px solid #e2e8f0;
            display: flex; justify-content: center; align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05); z-index: 1;
        }
        .scanned-logo-container img { width: 65%; height: auto; object-fit: contain; z-index: 10; position: relative; }
        
        .logo-pulse {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            border-radius: 50%; border: 3px solid #cbd5e1; opacity: 0;
            animation: expandWave 2.5s linear infinite; z-index: 0;
        }
        .logo-pulse:nth-child(2) { animation-delay: 1.25s; }

        @keyframes expandWave { 0% { transform: scale(1); opacity: 0.6; border-width: 4px; } 100% { transform: scale(1.5); opacity: 0; border-width: 1px; } }

        .scanned-room-title { font-size: 3rem; color: #0f1c3f; font-weight: 900; text-align: center; margin-bottom: 5px; letter-spacing: -1px; }
        .scanned-dept-title { font-size: 1.05rem; text-align: center; text-transform: uppercase; color: #64748b; font-weight: 800; letter-spacing: 1.5px; margin-bottom: 30px; }

        .status-occupied-box {
            background-color: #f0fdf4; border: 1px solid #86efac; border-radius: 20px; padding: 20px 25px;
            display: flex; align-items: center; gap: 15px; margin-bottom: 40px; width: 100%; max-width: 360px;
        }
        .status-dot {
            width: 14px; height: 14px; background-color: #22c55e; border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.2); 
        }
        .status-text { display: flex; flex-direction: column; text-align: left; }
        .status-text h3 { color: #166534; font-size: 1.2rem; font-weight: 700; margin-bottom: 3px; }
        .status-text p { color: #64748b; font-size: 0.95rem; font-weight: 500; }

        .status-vacant-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 20px; padding: 20px 25px; display: flex; align-items: center; gap: 15px; margin-bottom: 40px; width: 100%; max-width: 360px; }
        .status-vacant-box .fa-door-open { font-size: 1.8rem; color: #94a3b8; }
        .status-vacant-box h3 { color: #334155; font-size: 1.2rem; font-weight: 700; margin-bottom: 3px; }

        .action-btn { width: 100%; max-width: 360px; padding: 20px; border: none; border-radius: 18px; font-size: 1.2rem; font-weight: 800; color: white; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; transition: transform 0.1s; letter-spacing: 0.5px; }
        .action-btn:active { transform: scale(0.97); }
        
        .btn-danger { background-color: #dc2626; box-shadow: 0 4px 15px rgba(220, 38, 38, 0.25); }
        .btn-primary { background-color: #0f1c3f; box-shadow: 0 4px 15px rgba(15, 28, 63, 0.25); }
        .btn-warning { background-color: #ea580c; box-shadow: 0 4px 15px rgba(234, 88, 12, 0.25); }
        
        .helper-text { text-align: center; color: #64748b; font-size: 0.95rem; margin-top: 15px; font-weight: 500; }
        .clear-link { display: inline-block; margin-top: 25px; color: #94a3b8; font-weight: 700; text-decoration: none; font-size: 0.95rem; }
        
        .cosmos-footer { text-align: center; padding: 20px; color: #94a3b8; font-weight: 600; font-size: 0.9rem; margin-top: auto; }

        .custom-modal { position: fixed; inset: 0; background: rgba(15, 28, 63, 0.7); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: center; align-items: center; padding: 20px; }
        .custom-modal-content { background: white; width: 100%; max-width: 340px; border-radius: 24px; padding: 30px 20px; text-align: center; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
    </style>
</head>
<body>
    <div class="mobile-wrapper">
        
        <header class="app-header">
            <a href="Login_index.php?logout=true" class="back-btn"><i class="fas fa-sign-out-alt" style="margin-left: 5px;"></i></a>
            
            <a href="<?php echo $isStudent ? '#' : 'Profile_index.php'; ?>" class="user-profile">
                <div class="profile-text">
                    <span class="role"><?php echo $isStudent ? 'STUDENT' : 'FACULTY'; ?></span>
                    <span class="name"><?php echo htmlspecialchars($faculty_name); ?></span>
                </div>
                <div class="avatar-container">
                    <?php if($profile_pic): ?> 
                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" alt="Profile"> 
                    <?php else: ?> 
                        <i class="fas <?php echo $isStudent ? 'fa-user-graduate' : 'fa-user'; ?>" style="font-size: 1.5rem;"></i> 
                    <?php endif; ?>
                </div>
            </a>
        </header>

        <main class="main-content">
            
            <?php if (!$has_room): ?>
                
                <div class="nfc-no-scan-circle">
                    <i class="fas fa-wifi"></i>
                    <div class="no-scan-pulse"></div>
                    <div class="no-scan-pulse"></div>
                </div>

                <h2 class="no-scan-title">No Scan</h2>
                <p class="no-scan-subtitle">Awaiting NFC Tag</p>

                <div class="locked-box">
                    <i class="fas fa-lock"></i>
                    <h3>Scanner Locked</h3>
                    <p>Please tap your phone to the NFC tag located on the classroom door to access the monitoring controls.</p>
                </div>
                
            <?php else: ?>
                
                <div class="scanned-logo-container">
                    <?php if ($room_logo): ?>
                        <img src="<?php echo htmlspecialchars($room_logo); ?>" alt="Department Logo" onerror="this.style.display='none'">
                    <?php else: ?>
                        <i class="fas fa-wifi fa-rotate-90" style="font-size: 2rem; color: #0f1c3f; z-index: 10;"></i>
                    <?php endif; ?>
                    <div class="logo-pulse"></div>
                    <div class="logo-pulse"></div>
                </div>

                <h2 class="scanned-room-title">Room <?php echo htmlspecialchars($room_id); ?></h2>
                <p class="scanned-dept-title"><?php echo htmlspecialchars($room_dept ?: 'Facility Details'); ?></p>

                <form action="" method="POST" id="occupyForm" style="width:100%; display: flex; flex-direction: column; align-items: center;">
                    <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($room_id); ?>">

                    <?php if ($is_currently_logged_in): ?>
                        <div class="status-occupied-box">
                            <div class="status-dot"></div>
                            <div class="status-text">
                                <h3>Active Session</h3>
                                <p>You are currently occupying this room</p>
                                <p>
                                <i class="fas fa-clock" style="margin-right: 5px; color: #94a3b8;"></i>
                                Since <?php echo $time_in_display; ?>
                            </p>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="check_out">
                        <button type="submit" class="action-btn btn-danger">
                            <i class="fas fa-door-open"></i> VACATE ROOM
                        </button>
                        <p class="helper-text">Don't forget to Vacate when finished.</p>
                        <p class="helper-text" style="margin-top:5px;">Please ensure the room is secured when leaving.</p>

                    <?php elseif ($show_takeover_prompt): ?>
                        <div class="status-occupied-box" style="background-color: #fff7ed; border-color: #fdba74; display: flex; flex-direction: column; align-items: flex-start; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div class="status-dot" style="background-color: #ea580c; box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.2);"></div>
                                <h3 style="color: #9a3412; font-size: 1.2rem; margin:0; font-weight: 700;">Room in Use</h3>
                            </div>
                            <div class="status-text" style="width: 100%;">
                                <p style="color: #334155; font-size: 0.95rem; line-height: 1.5; margin-bottom: 5px;">
                                    <i class="fas fa-user-circle" style="color: #94a3b8; margin-right: 4px;"></i> <strong><?php echo htmlspecialchars($active_faculty_name); ?></strong> is already occupying this room.
                                </p>
                                <p style="color: #ea580c; font-weight: 800; font-size: 1.05rem; margin-top: 10px; text-align: center;">
                                    Are you now taking over?
                                </p>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="takeover">
                        <button type="submit" class="action-btn btn-warning">
                            <i class="fas fa-exchange-alt"></i> FORCE TAKEOVER
                        </button>

                    <?php elseif ($active_faculty_id !== null || $active_guest_name !== null): ?>
                        <div class="status-occupied-box" style="background-color: #fef2f2; border-color: #fca5a5;">
                            <div class="status-dot" style="background-color: #ef4444; box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);"></div>
                            <div class="status-text">
                                <h3 style="color: #991b1b;">Room Occupied</h3>
                                <p>This facility is currently in use.</p>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="status-vacant-box">
                            <i class="fas fa-door-open"></i>
                            <div class="status-text">
                                <h3>Room Vacant</h3>
                                <p style="color: #64748b; font-size: 0.95rem;">Ready for occupation</p>
                            </div>
                        </div>
                        <input type="hidden" name="action" value="log_in">
                        <button type="submit" class="action-btn btn-primary" onclick="return handleOccupyClick(event, <?php echo $current_schedule ? 'true' : 'false'; ?>)">
                            <i class="fas fa-door-closed"></i> OCCUPY ROOM
                        </button>
                    <?php endif; ?>
                </form>

                <?php if (!$is_currently_logged_in): ?>
                    <a href="room_monitoring.php?action=clear" class="clear-link"><i class="fas fa-times-circle"></i> Clear Interface</a>
                <?php endif; ?>
            <?php endif; ?>
            
        </main>
        
        <div class="cosmos-footer">
            COSMOS Monitoring System
        </div>
    </div>

    <div id="adHocModal" class="custom-modal">
        <div class="custom-modal-content">
            <i class="fas fa-exclamation-triangle" style="font-size:3rem; color:#f59e0b; margin-bottom:15px;"></i>
            <h3 style="color:#1e293b; font-weight:800; font-size:1.3rem; margin-bottom:10px;">Unscheduled Usage</h3>
            <p style="color:#64748b; font-size:0.95rem; line-height:1.5; margin-bottom:25px;">You do not have an official schedule for this room. Do you have permission to occupy?</p>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="document.getElementById('adHocModal').style.display='none'" style="flex:1; padding:15px; border:none; border-radius:12px; background:#f1f5f9; color:#475569; font-weight:700; cursor:pointer;">CANCEL</button>
                <button type="button" onclick="document.getElementById('occupyForm').action.value='log_in'; document.getElementById('occupyForm').submit()" style="flex:1; padding:15px; border:none; border-radius:12px; background:#0f1c3f; color:white; font-weight:700; cursor:pointer;">OCCUPY</button>
            </div>
        </div>
    </div>

    <script>
        function handleOccupyClick(e, hasSched) {
            if(!hasSched) { 
                e.preventDefault(); 
                document.getElementById('adHocModal').style.display='flex'; 
                return false; 
            }
            return true;
        }

        <?php if ($has_room): ?>
        // 1. Capture the exact state of the room when the page first loads
        const pageState = {
            occupied: <?php echo ($active_faculty_id !== null || $active_guest_name !== null) ? 'true' : 'false'; ?>,
            faculty_id: "<?php echo $active_faculty_id ?? ''; ?>",
            guest_name: "<?php echo $active_guest_name ?? ''; ?>"
        };
        const isCurrentlyLoggedIn = <?php echo $is_currently_logged_in ? 'true' : 'false'; ?>;

        // 2. Poll the server every 3 seconds
        setInterval(function() {
            // Adding Date.now() forces the browser to bypass its cache and pull fresh DB data
            fetch(`room_monitoring.php?ajax_get_current_state=<?php echo urlencode($room_id); ?>&_nocache=${Date.now()}`, {
                method: 'GET',
                headers: { 'Cache-Control': 'no-store' }
            })
            .then(res => res.json())
            .then(data => {
                // 3. Check if anything in the room has changed since the page loaded
                let stateChanged = (
                    data.occupied !== pageState.occupied || 
                    data.faculty_id !== pageState.faculty_id || 
                    data.guest_name !== pageState.guest_name
                );
                
                if (stateChanged) {
                    if (isCurrentlyLoggedIn && !data.occupied) {
                        // Scenario A: You were the occupant, but Admin just vacated you. 
                        // Clear your interface and send you back to "No Scan" lock screen.
                        window.location.href = 'room_monitoring.php?action=clear';
                    } else {
                        // Scenario B: You are a student/admin observing, and someone vacated OR occupied.
                        // Refresh the page so the UI updates to show the new occupant/vacancy.
                        window.location.reload();
                    }
                }
            })
            .catch(err => console.error('Live Sync Error:', err));
        }, 3000); 
        <?php endif; ?>
    </script>
</body>
</html>