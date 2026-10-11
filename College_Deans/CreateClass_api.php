<?php
// =========================================================
// CreateClass_api.php
// =========================================================
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
include '../db_connect.php';
header('Content-Type: application/json');

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? 'Staff'; 

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
    
    // Cleanup old TBA Rooms that were auto-created previously to stop clutter
    $conn->query("UPDATE class_schedules SET room_id = NULL WHERE room_id IN (SELECT id FROM rooms WHERE room_name = 'TBA Room')");
    $conn->query("DELETE FROM rooms WHERE room_name = 'TBA Room'");
} catch (Exception $e) { /* Ignore gracefully if no permission */ }


// =========================================================
// Helper Functions
// =========================================================
if (!function_exists('logActivity')) {
    function logActivity($conn, $module, $action, $details) {
        $u_id = $_SESSION['user_id'] ?? 0;
        $u_name = $_SESSION['full_name'] ?? 'Unknown User';
        $u_dept = $_SESSION['department'] ?? 'General';
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, department, module, action, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssss", $u_id, $u_name, $u_dept, $module, $action, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
}

try {
    function getSpecialFacultyId($conn, $name) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE first_name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows > 0) {
            return $res->fetch_assoc()['id'];
        } else {
            $username = strtolower(str_replace(' ', '', $name)) . '_sys';
            $email = $username . "@urs.edu.ph";
            $surname = "System";
            $sex = "Other";
            $emp_id = strtoupper($username);
            $sys_val = "System";
            
            $insert = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, sex, employee_id, department, position, program) VALUES (?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssssssss", $username, $email, $surname, $name, $sex, $emp_id, $sys_val, $sys_val, $sys_val);
            $insert->execute();
            return $insert->insert_id;
        }
    }

    $tba_id = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    // =========================================================
    // GET REQUESTS
    // =========================================================
    if ($method === 'GET') {
        
        if ($action === 'get_rooms') {
            $sql = "SELECT * FROM rooms WHERE department = '" . $conn->real_escape_string($userDept) . "' OR department = 'University' ORDER BY room_name";
            $res = $conn->query($sql);
            if ($res) echo json_encode($res->fetch_all(MYSQLI_ASSOC));
            else echo json_encode([]);
            exit;
        }

        if ($action === 'get_suggestions') {
            $sections = []; $ays = []; $programs_map = [];
            $s = $conn->query("SELECT DISTINCT section FROM class_schedules WHERE department = '" . $conn->real_escape_string($userDept) . "' AND section IS NOT NULL AND section != '' ORDER BY section");
            while($r = $s->fetch_assoc()) $sections[] = $r['section'];
            $a = $conn->query("SELECT DISTINCT academic_year FROM class_schedules WHERE department = '" . $conn->real_escape_string($userDept) . "' AND academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
            while($r = $a->fetch_assoc()) $ays[] = $r['academic_year'];
            
            // Generate programs map to link majors dynamically
            $p = $conn->query("SELECT DISTINCT program, major FROM courses WHERE department = '" . $conn->real_escape_string($userDept) . "' AND program IS NOT NULL AND program != '' ORDER BY program");
            while($r = $p->fetch_assoc()) {
                $prog = $r['program'];
                $maj = $r['major'];
                if (!isset($programs_map[$prog])) {
                    $programs_map[$prog] = [];
                }
                if (!empty($maj) && !in_array($maj, $programs_map[$prog])) {
                    $programs_map[$prog][] = $maj;
                }
            }
            echo json_encode(['sections' => $sections, 'ays' => $ays, 'programs_map' => $programs_map]);
            exit;
        }

        if ($action === 'find_courses') {
            $prog = $_GET['program'] ?? '';
            $major = $_GET['major'] ?? '';
            $year = $_GET['year_level'] ?? '';
            $sem  = $_GET['semester'] ?? '';

            $sql = "SELECT * FROM courses WHERE department = ? AND program = ? AND year_level = ? AND semester = ?";
            $params = [$userDept, $prog, $year, $sem];
            $types = "ssss";

            if (!empty($major)) {
                $sql .= " AND major = ?";
                $params[] = $major;
                $types .= "s";
            } else {
                $sql .= " AND (major IS NULL OR major = '')";
            }

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            exit;
        }

        if ($action === 'get_faculty') {
            $sql = "SELECT id, 
                           TRIM(CONCAT(COALESCE(first_name, ''), ' ', COALESCE(middle_initial, ''), ' ', COALESCE(surname, ''))) AS full_name, 
                           21 AS max_units 
                    FROM users 
                    WHERE id NOT IN ($tba_id, $gened_id) 
                    AND department = '" . $conn->real_escape_string($userDept) . "' 
                    ORDER BY first_name";
            $res = $conn->query($sql);
            echo json_encode($res->fetch_all(MYSQLI_ASSOC));
            exit;
        }

        if ($action === 'get_schedules') {
            $ay = $_GET['ay_filter'] ?? '';
            $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department, 
                           COALESCE(s.units, c.units) as units, 
                           COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours, 
                           COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours, 
                           c.course_code, c.descriptive_title, c.major, 
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as professor,
                           r.room_name as assigned_room_name
                    FROM class_schedules s
                    JOIN courses c ON s.course_id = c.id
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id
                    WHERE s.department = '" . $conn->real_escape_string($userDept) . "'"; 
            
            if(!empty($ay) && $ay !== 'All') $sql .= " AND s.academic_year = '$ay'";
            $res = $conn->query($sql);
            $schedules = $res->fetch_all(MYSQLI_ASSOC);
            
            foreach($schedules as &$sch) {
                $is_pending = (isset($sch['approval_status']) && $sch['approval_status'] === 'Pending');

                // --- VIRTUAL ROOM MAPPING ---
                if (empty($sch['room_id']) || $sch['room_id'] == '0') {
                    if (!empty($sch['requested_room_department'])) {
                        $sch['room_id'] = 'REQ_' . $sch['requested_room_department'];
                    } else {
                        $sch['room_id'] = '0'; // Purely Virtual TBA
                    }
                } elseif (!empty($sch['assigned_room_name'])) {
                    $sch['assigned_room_display'] = $sch['assigned_room_name'];
                }

                // --- VIRTUAL PROFESSOR MAPPING ---
                if ($sch['faculty_id'] == $tba_id || empty($sch['professor']) || $sch['faculty_id'] == '0') {
                    if (!empty($sch['requested_department'])) {
                        if ($sch['requested_department'] === 'General Education') {
                            $sch['faculty_id'] = '-1'; 
                            $sch['professor'] = "⏳ Req: Gen Ed";
                        } else {
                            $sch['faculty_id'] = 'REQ_' . $sch['requested_department'];
                            $shortReq = str_replace('College of ', '', $sch['requested_department']);
                            $sch['professor'] = "⏳ Req: " . $shortReq;
                        }
                    } else {
                        $sch['faculty_id'] = '0';
                        $sch['professor'] = $is_pending ? "⏳ Pending Dean (TBA)" : "TBA";
                    }
                } elseif ($sch['faculty_id'] == $gened_id) {
                    $sch['faculty_id'] = '-1'; 
                    $sch['professor'] = "General Education";
                }
            }
            echo json_encode($schedules);
            exit;
        }

        // Required so the frontend can check global room conflicts across departments
        if ($action === 'get_all_schedules') {
            $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department, 
                           COALESCE(s.units, c.units) as units, 
                           COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours, 
                           COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours, 
                           c.course_code, c.descriptive_title, c.major, 
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as professor,
                           r.room_name as assigned_room_name
                    FROM class_schedules s
                    JOIN courses c ON s.course_id = c.id
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id"; 
            
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach($schedules as &$sch) {
                $is_pending = (isset($sch['approval_status']) && $sch['approval_status'] === 'Pending');

                // --- VIRTUAL ROOM MAPPING ---
                if (empty($sch['room_id']) || $sch['room_id'] == '0') {
                    if (!empty($sch['requested_room_department'])) {
                        $sch['room_id'] = 'REQ_' . $sch['requested_room_department'];
                    } else {
                        $sch['room_id'] = '0'; // Purely Virtual TBA
                    }
                } elseif (!empty($sch['assigned_room_name'])) {
                    $sch['assigned_room_display'] = $sch['assigned_room_name'];
                }

                // --- VIRTUAL PROFESSOR MAPPING ---
                if ($sch['faculty_id'] == $tba_id || empty($sch['professor']) || $sch['faculty_id'] == '0') {
                    if (!empty($sch['requested_department'])) {
                        if ($sch['requested_department'] === 'General Education') {
                            $sch['faculty_id'] = '-1'; 
                            $sch['professor'] = "⏳ Req: Gen Ed";
                        } else {
                            $sch['faculty_id'] = 'REQ_' . $sch['requested_department'];
                            $shortReq = str_replace('College of ', '', $sch['requested_department']);
                            $sch['professor'] = "⏳ Req: " . $shortReq;
                        }
                    } else {
                        $sch['faculty_id'] = '0';
                        $sch['professor'] = $is_pending ? "⏳ Pending Dean (TBA)" : "TBA";
                    }
                } elseif ($sch['faculty_id'] == $gened_id) {
                    $sch['faculty_id'] = '-1'; 
                    $sch['professor'] = "General Education";
                }
            }
            echo json_encode($schedules);
            exit;
        }

        if ($action === 'get_latest_context') {
            $sql = "SELECT department, program, year_level, semester, academic_year, section, created_at 
                    FROM class_schedules 
                    WHERE department = '" . $conn->real_escape_string($userDept) . "' 
                    ORDER BY created_at DESC LIMIT 1";
            $res = $conn->query($sql);
            if ($res && $res->num_rows > 0) echo json_encode($res->fetch_assoc());
            else echo json_encode([]);
            exit;
        }

        if ($action === 'check_conflict') {
            $rid = $_GET['room_id'] ?? '';
            $fid = $_GET['faculty_id'] ?? '';
            $sec = $_GET['section'] ?? '';
            $ay = $_GET['ay'] ?? '';
            $days = $_GET['days'] ?? '';
            $start = $_GET['start'] ?? '';
            $end = $_GET['end'] ?? '';
            $course_id = $_GET['course_id'] ?? ''; 
            $prog = $_GET['program'] ?? '';
            
            $exclude_ids_str = $_GET['exclude_ids'] ?? '';
            $exclude_sql = "";
            if (!empty($exclude_ids_str)) {
                $ids = array_filter(array_map('intval', explode(',', $exclude_ids_str)));
                if (!empty($ids)) $exclude_sql = " AND cs.id NOT IN (" . implode(',', $ids) . ")";
            }

            if (!$days || !$start || !$end) { echo json_encode(["status" => "clear"]); exit; }

            $start_f = date("H:i:s", strtotime($start));
            $end_f = date("H:i:s", strtotime($end));
            
            if (strtotime($start_f) >= strtotime($end_f)) {
                echo json_encode(["status" => "conflict", "type" => "time", "is_combinable" => false, "message" => "<b>[TIME ERROR]</b> End Time must be strictly after the Start Time.<br>👉 <i>Adjust the Time.</i>"]); exit;
            }

            $dayArr = explode(',', $days);
            $exempt_rooms = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];

            foreach($dayArr as $d) {
                $d = trim($d);
                
                // GLOBAL QUERY: Does not filter by department so it perfectly detects cross-department University Room conflicts!
                $sql = "SELECT cs.course_id, cs.faculty_id, cs.room_id, cs.program, cs.section, c.course_code 
                        FROM class_schedules cs 
                        LEFT JOIN courses c ON cs.course_id = c.id 
                        WHERE cs.academic_year=? AND FIND_IN_SET(?, REPLACE(cs.days, ' ', '')) 
                        AND cs.start_time < ? AND cs.end_time > ?" . $exclude_sql;
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $ay, $d, $end_f, $start_f);
                $stmt->execute();
                $res = $stmt->get_result();
                
                $conflicts = $res->fetch_all(MYSQLI_ASSOC);

                foreach ($conflicts as $conflict) {
                    $c_code = $conflict['course_code'] ?? 'Unknown Course';
                    $conflict_details = "<b>{$c_code} ({$conflict['program']} {$conflict['section']})</b>";
                    $is_same_sec = ($conflict['section'] === $sec && $conflict['program'] === $prog);
                    $is_same_course = ($conflict['course_id'] == $course_id);

                    // Room API validation
                    if (!empty($rid) && $rid !== '0' && strpos($rid, 'REQ_') !== 0 && $conflict['room_id'] == $rid) {
                        $r_res = $conn->query("SELECT room_name, status FROM rooms WHERE id=".(int)$rid);
                        $room_data = $r_res->fetch_assoc();
                        $r_name = $room_data['room_name'] ?? 'Room';
                        
                        if (($room_data['status'] ?? '') === 'Under Maintenance') {
                            echo json_encode(["status" => "conflict", "type" => "room", "is_combinable" => false, "message" => "<b>[ROOM UNAVAILABLE]</b> Room '$r_name' is currently Under Maintenance. <br>👉 <i>Change the Room.</i>"]); exit;
                        }
                        
                        if (!in_array(strtoupper(trim($r_name)), $exempt_rooms)) {
                            if ($is_same_course) {
                                $msg = "<b>[COMBINE CLASS]</b> Matches with {$conflict['program']} {$conflict['section']}.<br>👉 <i>Requires 'COMBINE CLASS' override.</i>";
                                echo json_encode(["status" => "conflict", "type" => "combine", "is_combinable" => true, "message" => $msg]); exit;
                            }
                            if ($is_same_sec) {
                                $msg = "<b>[ROOM/SECTION OVERLAP]</b> Room '$r_name' is actively occupied by {$c_code}.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>";
                                echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true, "message" => $msg]); exit;
                            } else {
                                $msg = "<b>[ROOM CONFLICT]</b> Room '$r_name' is securely occupied by $conflict_details.<br>👉 <i>Change the Room, Day, or Time.</i>";
                                echo json_encode(["status" => "conflict", "type" => "room", "is_combinable" => false, "message" => $msg]); exit;
                            }
                        }
                    }

                    // Prof API validation
                    if (!empty($fid) && $fid !== '0' && $fid !== '-1' && strpos($fid, 'REQ_') !== 0 && $conflict['faculty_id'] == $fid) {
                        if ($is_same_course) {
                            if (!empty($rid) && $conflict['room_id'] != $rid) {
                                echo json_encode(["status" => "conflict", "type" => "prof", "is_combinable" => false, "message" => "<b>[STRICT BLOCK]</b> Professor cannot teach the same course in two different rooms simultaneously."]); exit;
                            }
                        } else {
                            if ($is_same_sec) {
                                $msg = "<b>[PROF/SECTION OVERLAP]</b> Professor is teaching {$c_code}.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>";
                                echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true, "message" => $msg]); exit;
                            } else {
                                $msg = "<b>[PROF CONFLICT]</b> Professor is already teaching $conflict_details.<br>👉 <i>Change the Professor, Day, or Time.</i>";
                                echo json_encode(["status" => "conflict", "type" => "prof", "is_combinable" => false, "message" => $msg]); exit;
                            }
                        }
                    }

                    // Section API validation
                    if ($is_same_sec && !$is_same_course) {
                        $msg = "<b>[SECTION CONFLICT]</b> Section '$sec' already has {$c_code} scheduled at this time.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>";
                        echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true, "message" => $msg]); exit;
                    }
                }
            }
            echo json_encode(["status" => "clear"]); exit;
        }
    }

    // =========================================================
    // POST REQUESTS
    // =========================================================
    if ($method === 'POST') {
        
        if ($action === 'delete_section') {
            $sec = $_POST['section'] ?? ''; $ay = $_POST['academic_year'] ?? ''; $prog = $_POST['program'] ?? ''; $sem = $_POST['semester'] ?? '';
            
            $sql = "DELETE FROM class_schedules WHERE section=? AND academic_year=? AND program=? AND semester=? AND department=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $sec, $ay, $prog, $sem, $userDept);

            if($stmt->execute()) {
                logActivity($conn, 'Schedule', 'DELETE', "Wiped entire schedule for $prog Section $sec ($sem, $ay)");
                echo json_encode(["status" => "success", "message" => "Section schedule deleted."]);
            } else { throw new Exception("Unable to delete section schedule."); }
            exit;
        }

        $inputJSON = file_get_contents('php://input');
        $data = json_decode($inputJSON, true);
        if (!$data || (!isset($data['schedules']) && !isset($data['deletes']))) throw new Exception("Invalid data format received.");

        $dept = $userDept; 
        $prog = $data['program'];
        $major = $data['major'] ?? ''; 
        $year = $data['year_level'];
        $sem = $data['semester'];
        $sec = $data['section'];
        $ay = $data['academic_year'];
        $override_combine = $data['override_combine'] ?? false; 
        $override_section = $data['override_section'] ?? false; 
        
        $deletes = $data['deletes'] ?? [];
        $schedules = $data['schedules'] ?? [];

        $conn->begin_transaction();
        
        try {
            if (!empty($deletes)) {
                $del_stmt = $conn->prepare("DELETE FROM class_schedules WHERE id=? AND department=?");
                foreach($deletes as $del_id) {
                    $del_stmt->bind_param("is", $del_id, $dept);
                    $del_stmt->execute();
                }
            }

            // Using 18 bound parameters for INSERT
            $insert_stmt = $conn->prepare("INSERT INTO class_schedules (course_id, faculty_id, room_id, department, program, year_level, semester, section, academic_year, days, start_time, end_time, approval_status, requested_department, requested_room_department, units, lecture_hours, laboratory_hours) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$insert_stmt) throw new Exception("INSERT preparation failed. Error: " . $conn->error);

            // Using 19 bound parameters for UPDATE
            $update_stmt = $conn->prepare("UPDATE class_schedules SET course_id=?, faculty_id=?, room_id=?, department=?, program=?, year_level=?, semester=?, section=?, academic_year=?, days=?, start_time=?, end_time=?, approval_status=?, requested_department=?, requested_room_department=?, units=?, lecture_hours=?, laboratory_hours=? WHERE id=?");
            if (!$update_stmt) throw new Exception("UPDATE preparation failed. Error: " . $conn->error);

            $exempt_rooms = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];

            foreach($schedules as $row) {
                // Notice we explicitly accept "0" to be a valid selection meaning TBA room
                if (empty($row['days']) || empty($row['start_time']) || !isset($row['room_id']) || $row['room_id'] === '' || !isset($row['faculty_id']) || $row['faculty_id'] === '') {
                    continue; 
                }

                $final_fid     = $row['faculty_id'];
                $final_rid     = $row['room_id'];
                
                // DEAN ACCESS: Always default to Approved
                $app_status    = 'Approved';
                $req_dept      = NULL;
                $req_room_dept = NULL;

                // Handle Requested Professors
                if (strpos($final_fid, 'REQ_') === 0) {
                    $req_dept = str_replace('REQ_', '', $final_fid);
                    $final_fid = $tba_id; 
                } 
                elseif ($final_fid === '-1' || $final_fid == '-1') {
                    $req_dept = 'General Education'; 
                    $final_fid = $tba_id;
                } 
                elseif ($final_fid === '0' || $final_fid == '0') {
                    $final_fid = $tba_id;
                }

                // Handle Requested/TBA Rooms (STORE NULL DIRECTLY IN DB, NOT A FAKE ID)
                if (strpos($final_rid, 'REQ_') === 0) {
                    $req_room_dept = str_replace('REQ_', '', $final_rid);
                    $final_rid = NULL; 
                }
                elseif ($final_rid === '0' || $final_rid == '0' || empty($final_rid)) {
                    $final_rid = NULL; // Purely Virtual TBA Room
                }

                $start_f = date("H:i:s", strtotime($row['start_time']));
                $end_f = date("H:i:s", strtotime($row['end_time']));
                
                if (strtotime($start_f) >= strtotime($end_f)) {
                    throw new Exception("Validation Failed: End Time must be strictly after Start Time.");
                }

                // Strict Backend Negative check
                $customUnits = isset($row['units']) ? (float)$row['units'] : 0;
                $customLec   = isset($row['lecture_hours']) ? (float)$row['lecture_hours'] : 0;
                $customLab   = isset($row['laboratory_hours']) ? (float)$row['laboratory_hours'] : 0;

                if ($customUnits < 0 || $customLec < 0 || $customLab < 0) {
                    throw new Exception("Validation Failed: Units and hours cannot be negative.");
                }

                $dayArr = explode(',', $row['days']);

                foreach($dayArr as $d) {
                    $d = trim($d);
                    $check_id = !empty($row['schedule_id']) ? (int)$row['schedule_id'] : 0;
                    
                    // GLOBAL CONFLICT CHECK
                    $sql = "SELECT id, course_id, room_id, faculty_id, section, program FROM class_schedules WHERE academic_year=? AND FIND_IN_SET(?, REPLACE(days, ' ', '')) AND start_time < ? AND end_time > ? AND id != ?";
                    $chk = $conn->prepare($sql);
                    $chk->bind_param("ssssi", $ay, $d, $end_f, $start_f, $check_id);
                    $chk->execute();
                    $res = $chk->get_result();
                    
                    $conflicts = $res->fetch_all(MYSQLI_ASSOC);

                    // Strict Validations
                    foreach ($conflicts as $conflict_data) {
                        $is_same_course = ($conflict_data['course_id'] == $row['course_id']);
                        $is_same_sec = ($conflict_data['program'] === $prog && $conflict_data['section'] === $sec);

                        // If it's a physical room collision (ignore requested virtual rooms and NULL/TBA)
                        if (!empty($final_rid) && $conflict_data['room_id'] == $final_rid) {
                            $r_res = $conn->query("SELECT room_name, status FROM rooms WHERE id=".(int)$final_rid);
                            $room_data = $r_res->fetch_assoc();
                            $r_name = $room_data['room_name'] ?? 'Room';
                            
                            if (($room_data['status'] ?? '') === 'Under Maintenance') throw new Exception("Validation Failed: Room '$r_name' is Under Maintenance.");
                            
                            if (!in_array(strtoupper(trim($r_name)), $exempt_rooms)) {
                                if ($is_same_course) {
                                    if ($conflict_data['faculty_id'] != $final_fid) {
                                        throw new Exception("Combine Error: The Professor must exactly match the existing class in Room " . $r_name . ".");
                                    }
                                    if (!$override_combine) throw new Exception("Combine Class Detected: Missing COMBINE CLASS Override command.");
                                    continue; // Valid combine
                                }
                                
                                if ($is_same_sec) {
                                    if (!$override_section) throw new Exception("Section Conflict: Room '$r_name' is double booked for the same section. (Missing OVERRIDE)");
                                } else {
                                    throw new Exception("Strict Block: Room '$r_name' is occupied by a different subject and cannot be overridden.");
                                }
                            }
                        }

                        // If it's a professor collision (ignore virtual requested profs)
                        if ($final_fid != $tba_id && $final_fid != $gened_id && $conflict_data['faculty_id'] == $final_fid) {
                            if ($is_same_course) {
                                if (!empty($final_rid) && $conflict_data['room_id'] != $final_rid) {
                                    throw new Exception("Strict Block: Professor cannot teach the same course in two different rooms simultaneously.");
                                }
                            } else {
                                if ($is_same_sec) {
                                    if (!$override_section) throw new Exception("Section Conflict: Professor is double booked for the same section. (Missing OVERRIDE)");
                                } else {
                                    throw new Exception("Strict Block: Professor is assigned to a different subject elsewhere and cannot be overridden.");
                                }
                            }
                        }
                        
                        // Section
                        if ($is_same_sec && !$is_same_course) {
                            if (!$override_section) throw new Exception("Section Conflict Detected: Section '$sec' is double booked. (Missing OVERRIDE)");
                        }
                    }
                }

                $row_units = isset($row['units']) ? (float)$row['units'] : null;
                $row_lec = isset($row['lecture_hours']) ? (float)$row['lecture_hours'] : null;
                $row_lab = isset($row['laboratory_hours']) ? (float)$row['laboratory_hours'] : null;

                if (!empty($row['schedule_id'])) {
                    $update_stmt->bind_param("iiissssssssssssdddi", 
                        $row['course_id'], $final_fid, $final_rid, 
                        $dept, $prog, $year, $sem, $sec, $ay, 
                        $row['days'], $row['start_time'], $row['end_time'],
                        $app_status, $req_dept, $req_room_dept, 
                        $row_units, $row_lec, $row_lab,
                        $row['schedule_id']
                    );
                    if (!$update_stmt->execute()) throw new Exception("Error updating schedule.");
                } else {
                    $insert_stmt->bind_param("iiissssssssssssddd", 
                        $row['course_id'], $final_fid, $final_rid, 
                        $dept, $prog, $year, $sem, $sec, $ay, 
                        $row['days'], $row['start_time'], $row['end_time'],
                        $app_status, $req_dept, $req_room_dept,
                        $row_units, $row_lec, $row_lab
                    );
                    if (!$insert_stmt->execute()) throw new Exception("Error inserting schedule.");
                }
            }
            
            $conn->commit();
            logActivity($conn, 'Schedule', 'UPDATE', "Created/Modified schedule for $prog Section $sec ($sem, $ay)");
            echo json_encode(["status" => "success", "message" => "Schedules saved successfully!"]);
        } catch (Exception $e) {
            $conn->rollback(); 
            throw new Exception("Save Aborted - " . $e->getMessage());
        }
        exit;
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>