<?php
// CreateClass_api.php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
include '../db_connect.php';
header('Content-Type: application/json');

// Session guards
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$userDept    = $_SESSION['department'] ?? '';
$userProgram = $_SESSION['program']    ?? '';
$userPos     = $_SESSION['position']   ?? '';

// ── Auto-schema checker ──────────────────────
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'requested_room_department'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE class_schedules ADD COLUMN requested_room_department VARCHAR(100) NULL AFTER requested_department");
    }
    $conn->query("ALTER TABLE class_schedules MODIFY room_id INT NULL");
    $conn->query("UPDATE class_schedules SET room_id = NULL WHERE room_id IN (SELECT id FROM rooms WHERE room_name = 'TBA Room')");
    $conn->query("DELETE FROM rooms WHERE room_name = 'TBA Room'");
} catch (Exception $e) { /* Ignore gracefully */ }

if (!function_exists('logActivity')) {
    function logActivity($conn, $module, $action, $details) {
        $u_id   = $_SESSION['user_id']    ?? 0;
        $u_name = $_SESSION['full_name']  ?? 'Unknown User';
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
            $email    = $username . "@urs.edu.ph";
            $surname  = "System";
            $sex      = "Other";
            $emp_id   = strtoupper($username);
            $sys_val  = "System";
            $insert   = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, sex, employee_id, department, position, program) VALUES (?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssssssss", $username, $email, $surname, $name, $sex, $emp_id, $sys_val, $sys_val, $sys_val);
            $insert->execute();
            return $insert->insert_id;
        }
    }

    $tba_id   = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    $action = $_GET['action'] ?? '';
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {

        if ($action === 'get_rooms') {
            // FIXED: Added University rooms access for Program Heads
            $sql = "SELECT * FROM rooms WHERE department = ? OR department = 'University' ORDER BY room_name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $userDept);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            exit;
        }

        if ($action === 'get_suggestions') {
            $sections = []; $ays = []; $programs_map = [];

            $s = $conn->prepare("SELECT DISTINCT section FROM class_schedules WHERE department = ? AND program = ? AND section IS NOT NULL AND section != '' ORDER BY section");
            $s->bind_param("ss", $userDept, $userProgram);
            $s->execute();
            $res = $s->get_result();
            while ($r = $res->fetch_assoc()) $sections[] = $r['section'];

            $a = $conn->prepare("SELECT DISTINCT academic_year FROM class_schedules WHERE department = ? AND academic_year IS NOT NULL AND academic_year != '' ORDER BY academic_year DESC");
            $a->bind_param("s", $userDept);
            $a->execute();
            $res = $a->get_result();
            while ($r = $res->fetch_assoc()) $ays[] = $r['academic_year'];

            $p = $conn->prepare("SELECT DISTINCT program, major FROM courses WHERE department = ? AND program = ? AND program IS NOT NULL AND program != '' ORDER BY program");
            $p->bind_param("ss", $userDept, $userProgram);
            $p->execute();
            $p_res = $p->get_result();
            while($r = $p_res->fetch_assoc()) {
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
            $major = $_GET['major']      ?? '';
            $year  = $_GET['year_level'] ?? '';
            $sem   = $_GET['semester']   ?? '';
            
            $sql = "SELECT * FROM courses WHERE department = ? AND program = ? AND year_level LIKE ? AND semester LIKE ?";
            $params = [$userDept, $userProgram, "%$year%", "%$sem%"];
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
            $sql  = "SELECT id, 
                            TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(middle_initial,''), ' ', COALESCE(surname,''))) AS full_name,
                            21 AS max_units
                     FROM users
                     WHERE id NOT IN ($tba_id, $gened_id)
                     AND department = ?
                     ORDER BY first_name";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $userDept);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            exit;
        }

        if ($action === 'get_schedules') {
            $ay = $_GET['ay_filter'] ?? '';
            // FIXED: Removed "AND s.program = ?" so they can see all schedules in their department visually on the calendar
            $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department,
                           COALESCE(s.units, c.units) as units,
                           COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours,
                           COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours,
                           c.course_code, c.descriptive_title, c.major,
                           TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.surname,''))) as professor,
                           r.room_name as assigned_room_name
                    FROM class_schedules s
                    JOIN courses c ON s.course_id = c.id
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id
                    WHERE s.department = ?";

            if (!empty($ay) && $ay !== 'All') $sql .= " AND s.academic_year = ?";

            $stmt = $conn->prepare($sql);
            if (!empty($ay) && $ay !== 'All') {
                $stmt->bind_param("ss", $userDept, $ay);
            } else {
                $stmt->bind_param("s", $userDept);
            }
            $stmt->execute();
            $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($schedules as &$sch) {
                if (isset($sch['approval_status']) && $sch['approval_status'] === 'Pending') {
                    if (!empty($sch['requested_room_department'])) {
                        $sch['room_id'] = 'REQ_' . $sch['requested_room_department'];
                    } elseif (empty($sch['room_id']) || $sch['room_id'] == '0') {
                        $sch['room_id'] = '0';
                    }

                    if (!empty($sch['requested_department'])) {
                        if ($sch['requested_department'] === 'General Education') {
                            $sch['faculty_id'] = '-1'; 
                            $sch['professor'] = "⏳ Req: Gen Ed";
                        } else {
                            $sch['faculty_id'] = 'REQ_' . $sch['requested_department'];
                            $sch['professor']   = "⏳ Req: " . str_replace('College of ', '', $sch['requested_department']);
                        }
                    } else {
                        if ($sch['faculty_id'] == $tba_id || empty($sch['professor'])) {
                            $sch['faculty_id'] = '0'; 
                            $sch['professor'] = "TBA";
                        }
                    }
                } else {
                    if (!empty($sch['assigned_room_name'])) {
                        $sch['assigned_room_display'] = $sch['assigned_room_name'];
                    } elseif (empty($sch['room_id']) || $sch['room_id'] == '0') {
                        $sch['room_id'] = '0';
                    }
                    
                    if ($sch['faculty_id'] == $tba_id || empty($sch['professor'])) {
                        $sch['faculty_id'] = '0'; 
                        $sch['professor'] = "TBA";
                    } elseif ($sch['faculty_id'] == $gened_id) {
                        $sch['faculty_id'] = '-1'; 
                        $sch['professor'] = "General Education";
                    }
                }
            }
            echo json_encode($schedules);
            exit;
        }

        if ($action === 'get_all_schedules') {
            $sql = "SELECT s.id, s.course_id, s.faculty_id, s.room_id, s.department, s.program, s.year_level, s.semester, s.section, s.academic_year, s.days, s.start_time, s.end_time, s.approval_status, s.requested_department, s.requested_room_department,
                           COALESCE(s.units, c.units) as units,
                           COALESCE(s.lecture_hours, c.lecture_hours) as lecture_hours,
                           COALESCE(s.laboratory_hours, c.laboratory_hours) as laboratory_hours,
                           c.course_code, c.descriptive_title, c.major,
                           TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.surname,''))) as professor,
                           r.room_name as assigned_room_name
                    FROM class_schedules s
                    JOIN courses c ON s.course_id = c.id
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id";

            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $schedules = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($schedules as &$sch) {
                if (isset($sch['approval_status']) && $sch['approval_status'] === 'Pending') {
                    if (!empty($sch['requested_room_department'])) {
                        $sch['room_id'] = 'REQ_' . $sch['requested_room_department'];
                    } elseif (empty($sch['room_id']) || $sch['room_id'] == '0') {
                        $sch['room_id'] = '0';
                    }
                    if (!empty($sch['requested_department'])) {
                        if ($sch['requested_department'] === 'General Education') {
                            $sch['faculty_id'] = '-1'; $sch['professor'] = "⏳ Pending: Gen Ed";
                        } else {
                            $sch['faculty_id'] = 'REQ_' . $sch['requested_department'];
                            $sch['professor']   = "⏳ Pending: " . $sch['requested_department'];
                        }
                    } else {
                        if ($sch['faculty_id'] == $tba_id || empty($sch['professor'])) {
                            $sch['faculty_id'] = '0'; $sch['professor'] = "⏳ Pending (TBA)";
                        }
                    }
                } else {
                    if (!empty($sch['assigned_room_name'])) {
                        $sch['assigned_room_display'] = $sch['assigned_room_name'];
                    } elseif (empty($sch['room_id']) || $sch['room_id'] == '0') {
                        $sch['room_id'] = '0';
                    }
                    if ($sch['faculty_id'] == $tba_id || empty($sch['professor'])) {
                        $sch['faculty_id'] = '0'; $sch['professor'] = "TBA";
                    } elseif ($sch['faculty_id'] == $gened_id) {
                        $sch['faculty_id'] = '-1'; $sch['professor'] = "General Education";
                    }
                }
            }
            echo json_encode($schedules);
            exit;
        }

        if ($action === 'get_latest_context') {
            $sql  = "SELECT department, program, year_level, semester, academic_year, section, created_at
                     FROM class_schedules
                     WHERE department = ? AND program = ?
                     ORDER BY created_at DESC LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $userDept, $userProgram);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) echo json_encode($res->fetch_assoc());
            else echo json_encode([]);
            exit;
        }

        if ($action === 'check_conflict') {
            $rid       = $_GET['room_id']   ?? '';
            $fid       = $_GET['faculty_id'] ?? '';
            $sec       = $_GET['section']   ?? '';
            $ay        = $_GET['ay']        ?? '';
            $days      = $_GET['days']      ?? '';
            $start     = $_GET['start']     ?? '';
            $end       = $_GET['end']       ?? '';
            $course_id = $_GET['course_id'] ?? '';
            $prog      = $_GET['program']   ?? '';

            $exclude_ids_str = $_GET['exclude_ids'] ?? '';
            $exclude_sql = "";
            if (!empty($exclude_ids_str)) {
                $ids = array_filter(array_map('intval', explode(',', $exclude_ids_str)));
                if (!empty($ids)) $exclude_sql = " AND cs.id NOT IN (" . implode(',', $ids) . ")";
            }

            if (!$days || !$start || !$end) { echo json_encode(["status" => "clear"]); exit; }

            $start_f = date("H:i:s", strtotime($start));
            $end_f   = date("H:i:s", strtotime($end));

            if (strtotime($start_f) >= strtotime($end_f)) {
                echo json_encode(["status" => "conflict", "type" => "time", "is_combinable" => false,
                    "message" => "<b>[TIME ERROR]</b> End Time must be strictly after the Start Time.<br>👉 <i>Adjust the Time.</i>"]);
                exit;
            }

            $dayArr      = explode(',', $days);
            $exempt_rooms = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];

            foreach ($dayArr as $d) {
                $d = trim($d);

                // FIXED: Global search (removed "cs.department = ?") so it detects University room conflicts correctly
                $sql  = "SELECT cs.course_id, cs.faculty_id, cs.room_id, cs.program, cs.section, c.course_code
                         FROM class_schedules cs
                         LEFT JOIN courses c ON cs.course_id = c.id
                         WHERE cs.academic_year = ?
                         AND FIND_IN_SET(?, REPLACE(cs.days, ' ', ''))
                         AND cs.start_time < ? AND cs.end_time > ?" . $exclude_sql;
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssss", $ay, $d, $end_f, $start_f);
                $stmt->execute();
                $conflicts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($conflicts as $conflict) {
                    $c_code   = $conflict['course_code'] ?? 'Unknown Course';
                    $conflict_details = "<b>{$c_code} ({$conflict['program']} {$conflict['section']})</b>";
                    $is_same_sec    = ($conflict['section'] === $sec && $conflict['program'] === $prog);
                    $is_same_course = ($conflict['course_id'] == $course_id);

                    if (!empty($rid) && $rid !== '0' && strpos($rid, 'REQ_') !== 0 && $conflict['room_id'] == $rid) {
                        $r_res    = $conn->query("SELECT room_name, status FROM rooms WHERE id=" . (int)$rid);
                        $room_data = $r_res->fetch_assoc();
                        $r_name   = $room_data['room_name'] ?? 'Room';

                        if (($room_data['status'] ?? '') === 'Under Maintenance') {
                            echo json_encode(["status" => "conflict", "type" => "room", "is_combinable" => false,
                                "message" => "<b>[ROOM UNAVAILABLE]</b> Room '$r_name' is currently Under Maintenance.<br>👉 <i>Change the Room.</i>"]);
                            exit;
                        }

                        if (!in_array(strtoupper(trim($r_name)), $exempt_rooms)) {
                            if ($is_same_course) {
                                echo json_encode(["status" => "conflict", "type" => "combine", "is_combinable" => true,
                                    "message" => "<b>[COMBINE CLASS]</b> Matches with {$conflict['program']} {$conflict['section']}.<br>👉 <i>Requires 'COMBINE CLASS' override.</i>"]);
                                exit;
                            }
                            if ($is_same_sec) {
                                echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true,
                                    "message" => "<b>[ROOM/SECTION OVERLAP]</b> Room '$r_name' is actively occupied by {$c_code}.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>"]);
                                exit;
                            } else {
                                echo json_encode(["status" => "conflict", "type" => "room", "is_combinable" => false,
                                    "message" => "<b>[ROOM CONFLICT]</b> Room '$r_name' is securely occupied by $conflict_details.<br>👉 <i>Change the Room, Day, or Time.</i>"]);
                                exit;
                            }
                        }
                    }

                    if (!empty($fid) && $fid !== '0' && $fid !== '-1' && strpos($fid, 'REQ_') !== 0 && $conflict['faculty_id'] == $fid) {
                        if ($is_same_course) {
                            if (!empty($rid) && $conflict['room_id'] != $rid) {
                                echo json_encode(["status" => "conflict", "type" => "prof", "is_combinable" => false,
                                    "message" => "<b>[STRICT BLOCK]</b> Professor cannot teach the same course in two different rooms simultaneously."]);
                                exit;
                            }
                        } else {
                            if ($is_same_sec) {
                                echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true,
                                    "message" => "<b>[PROF/SECTION OVERLAP]</b> Professor is teaching {$c_code}.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>"]);
                                exit;
                            } else {
                                echo json_encode(["status" => "conflict", "type" => "prof", "is_combinable" => false,
                                    "message" => "<b>[PROF CONFLICT]</b> Professor is already teaching $conflict_details.<br>👉 <i>Change the Professor, Day, or Time.</i>"]);
                                exit;
                            }
                        }
                    }

                    if ($is_same_sec && !$is_same_course) {
                        echo json_encode(["status" => "conflict", "type" => "section", "is_overrideable" => true,
                            "message" => "<b>[SECTION CONFLICT]</b> Section '$sec' already has {$c_code} scheduled at this time.<br>👉 <i>Change Time, OR use OVERRIDE on save.</i>"]);
                        exit;
                    }
                }
            }

            echo json_encode(["status" => "clear"]);
            exit;
        }
    }

    if ($method === 'POST') {
        
        $inputJSON = file_get_contents('php://input');
        $data      = json_decode($inputJSON, true);

        if (!$data || !isset($data['schedules'])) throw new Exception("Invalid data format received.");

        $dept  = $userDept;
        $prog  = $userProgram; // Always use session program
        $major = $data['major'] ?? ''; 
        $year  = $data['year_level'];
        $sem   = $data['semester'];
        $sec   = $data['section'];
        $ay    = $data['academic_year'];
        $override_combine = $data['override_combine'] ?? false;
        $override_section = $data['override_section'] ?? false;

        // Server-side guard: program must match session
        if (!empty($data['program']) && $data['program'] !== $userProgram) {
            throw new Exception("Unauthorized: You can only create schedules for your assigned program.");
        }

        $deletes   = $data['deletes']   ?? [];
        $schedules = $data['schedules'] ?? [];

        $conn->begin_transaction();

        try {
            if (!empty($deletes)) {
                $del_stmt = $conn->prepare("DELETE FROM class_schedules WHERE id=? AND department=? AND program=?");
                foreach ($deletes as $del_id) {
                    $del_stmt->bind_param("iss", $del_id, $dept, $prog);
                    $del_stmt->execute();
                }
            }

            $insert_stmt = $conn->prepare("INSERT INTO class_schedules (course_id, faculty_id, room_id, department, program, year_level, semester, section, academic_year, days, start_time, end_time, approval_status, requested_department, requested_room_department, units, lecture_hours, laboratory_hours) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            if (!$insert_stmt) throw new Exception("INSERT preparation failed: " . $conn->error);

            $update_stmt = $conn->prepare("UPDATE class_schedules SET course_id=?, faculty_id=?, room_id=?, department=?, program=?, year_level=?, semester=?, section=?, academic_year=?, days=?, start_time=?, end_time=?, approval_status=?, requested_department=?, requested_room_department=?, units=?, lecture_hours=?, laboratory_hours=? WHERE id=? AND program=?");
            if (!$update_stmt) throw new Exception("UPDATE preparation failed: " . $conn->error);

            $exempt_rooms = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];

            foreach ($schedules as $row) {
                if (empty($row['days']) || empty($row['start_time']) || !isset($row['room_id']) || $row['room_id'] === '' || !isset($row['faculty_id']) || $row['faculty_id'] === '') {
                    continue;
                }

                $final_fid     = $row['faculty_id'];
                $final_rid     = $row['room_id'];
                $req_dept      = NULL;
                $req_room_dept = NULL;
                
                // ── ALL SCHEDULES SAVED BY PROGRAM HEADS ARE PENDING DEAN APPROVAL ──
                $app_status    = 'Pending';

                // Handle virtual/requested profs
                if (strpos($final_fid, 'REQ_') === 0) {
                    $req_dept  = str_replace('REQ_', '', $final_fid);
                    $final_fid = $tba_id;
                } elseif ($final_fid === '-1' || $final_fid == '-1') {
                    $req_dept  = 'General Education';
                    $final_fid = $tba_id;
                } elseif ($final_fid === '0' || $final_fid == '0') {
                    $final_fid = $tba_id;
                }

                // Handle virtual/requested rooms
                if (strpos($final_rid, 'REQ_') === 0) {
                    $req_room_dept = str_replace('REQ_', '', $final_rid);
                    $final_rid     = NULL;
                } elseif ($final_rid === '0' || $final_rid == '0' || empty($final_rid)) {
                    $final_rid = NULL;
                }

                $start_f = date("H:i:s", strtotime($row['start_time']));
                $end_f   = date("H:i:s", strtotime($row['end_time']));

                if (strtotime($start_f) >= strtotime($end_f)) {
                    throw new Exception("Validation Failed: End Time must be strictly after Start Time.");
                }

                $dayArr = explode(',', $row['days']);

                foreach ($dayArr as $d) {
                    $d        = trim($d);
                    $check_id = !empty($row['schedule_id']) ? (int)$row['schedule_id'] : 0;

                    // FIXED: Global conflict check (removed department filter)
                    $sql  = "SELECT id, course_id, room_id, faculty_id, section, program
                             FROM class_schedules
                             WHERE academic_year = ?
                             AND FIND_IN_SET(?, REPLACE(days, ' ', ''))
                             AND start_time < ? AND end_time > ? AND id != ?";
                    $chk  = $conn->prepare($sql);
                    $chk->bind_param("ssssi", $ay, $d, $end_f, $start_f, $check_id);
                    $chk->execute();
                    $conflicts = $chk->get_result()->fetch_all(MYSQLI_ASSOC);

                    foreach ($conflicts as $conflict_data) {
                        $is_same_course = ($conflict_data['course_id'] == $row['course_id']);
                        $is_same_sec    = ($conflict_data['program'] === $prog && $conflict_data['section'] === $sec);

                        if (!empty($final_rid) && $conflict_data['room_id'] == $final_rid) {
                            $r_res    = $conn->query("SELECT room_name, status FROM rooms WHERE id=" . (int)$final_rid);
                            $room_data = $r_res->fetch_assoc();
                            $r_name   = $room_data['room_name'] ?? 'Room';

                            if (($room_data['status'] ?? '') === 'Under Maintenance') {
                                throw new Exception("Validation Failed: Room '$r_name' is Under Maintenance.");
                            }

                            if (!in_array(strtoupper(trim($r_name)), $exempt_rooms)) {
                                if ($is_same_course) {
                                    if ($conflict_data['faculty_id'] != $final_fid) {
                                        throw new Exception("Combine Error: The Professor must exactly match the existing class in Room $r_name.");
                                    }
                                    if (!$override_combine) throw new Exception("Combine Class Detected: Missing COMBINE CLASS Override command.");
                                    continue;
                                }
                                if ($is_same_sec) {
                                    if (!$override_section) throw new Exception("Section Conflict: Room '$r_name' is double booked. (Missing OVERRIDE)");
                                } else {
                                    throw new Exception("Strict Block: Room '$r_name' is occupied by a different subject and cannot be overridden.");
                                }
                            }
                        }

                        if ($final_fid != $tba_id && $final_fid != $gened_id && $conflict_data['faculty_id'] == $final_fid) {
                            if ($is_same_course) {
                                if (!empty($final_rid) && $conflict_data['room_id'] != $final_rid) {
                                    throw new Exception("Strict Block: Professor cannot teach the same course in two different rooms simultaneously.");
                                }
                            } else {
                                if ($is_same_sec) {
                                    if (!$override_section) throw new Exception("Section Conflict: Professor is double booked. (Missing OVERRIDE)");
                                } else {
                                    throw new Exception("Strict Block: Professor is assigned to a different subject elsewhere and cannot be overridden.");
                                }
                            }
                        }

                        if ($is_same_sec && !$is_same_course) {
                            if (!$override_section) throw new Exception("Section Conflict Detected: Section '$sec' is double booked. (Missing OVERRIDE)");
                        }
                    }
                }

                $row_units = isset($row['units'])            ? (float)$row['units']            : null;
                $row_lec   = isset($row['lecture_hours'])    ? (float)$row['lecture_hours']    : null;
                $row_lab   = isset($row['laboratory_hours']) ? (float)$row['laboratory_hours'] : null;

                if (!empty($row['schedule_id'])) {
                    $update_stmt->bind_param("iiissssssssssssdddis",
                        $row['course_id'], $final_fid, $final_rid,
                        $dept, $prog, $year, $sem, $sec, $ay,
                        $row['days'], $row['start_time'], $row['end_time'],
                        $app_status, $req_dept, $req_room_dept,
                        $row_units, $row_lec, $row_lab,
                        $row['schedule_id'], $prog
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
            logActivity($conn, 'Schedule', 'UPDATE', "Created/Modified schedule for $prog Section $sec ($sem, $ay) - Pending Approval");
            echo json_encode(["status" => "success", "message" => "Schedules saved as Pending successfully!"]);
        } catch (Exception $e) {
            $conn->rollback();
            throw new Exception("Save Aborted — " . $e->getMessage());
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>