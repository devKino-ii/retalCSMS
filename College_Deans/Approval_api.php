<?php
session_start();
include '../db_connect.php';
header('Content-Type: application/json');

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? '';

// --- CUSTOM LOGGER FUNCTION ---
if (!function_exists('logActivity')) {
    function logActivity($conn, $module, $action, $details, $target_dept) {
        $u_id = $_SESSION['user_id'] ?? 0;
        $u_name = $_SESSION['full_name'] ?? 'Unknown User';
        
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, user_name, department, module, action, details) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isssss", $u_id, $u_name, $target_dept, $module, $action, $details);
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
        if ($res->num_rows > 0) return $res->fetch_assoc()['id'];
        return 0; // Fallback
    }

    $tba_id = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? 'get_requests';

        // --- FETCH FACULTY WITH THEIR CURRENT LOAD ---
        if ($action === 'get_faculty_load') {
            $ay = $_GET['ay'] ?? '';
            $req_days = $_GET['days'] ?? '';
            $req_start = $_GET['start_time'] ?? '';
            $req_end = $_GET['end_time'] ?? '';

            $sql = "SELECT u.id, 
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.middle_initial, ''), ' ', COALESCE(u.surname, ''))) AS full_name, 
                           u.program,
                           21 AS max_units,
                           COALESCE(SUM(c.units), 0) AS current_load
                    FROM users u
                    LEFT JOIN class_schedules s ON u.id = s.faculty_id AND s.academic_year = ? AND s.approval_status != 'Pending'
                    LEFT JOIN courses c ON s.course_id = c.id
                    WHERE u.department = ? 
                    AND u.first_name NOT IN ('TBA', 'General Education') 
                    AND u.position != 'System'
                    GROUP BY u.id, u.program
                    ORDER BY u.first_name ASC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $ay, $userDept);
            $stmt->execute();
            $faculty_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $req_days_arr = array_map('trim', explode(',', $req_days));
            $req_start_f = date("H:i:s", strtotime($req_start));
            $req_end_f = date("H:i:s", strtotime($req_end));

            foreach ($faculty_list as &$f) {
                $f['has_conflict'] = false;
                $sched_sql = "SELECT days, start_time, end_time FROM class_schedules WHERE faculty_id = ? AND academic_year = ? AND approval_status != 'Pending'";
                $s_stmt = $conn->prepare($sched_sql);
                $s_stmt->bind_param("is", $f['id'], $ay);
                $s_stmt->execute();
                $schedules = $s_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($schedules as $s) {
                    $s_days_arr = array_map('trim', explode(',', $s['days']));
                    if (!empty(array_intersect($req_days_arr, $s_days_arr))) {
                        if ($req_start_f < $s['end_time'] && $s['start_time'] < $req_end_f) {
                            $f['has_conflict'] = true; break;
                        }
                    }
                }
            }
            echo json_encode($faculty_list);
            exit;
        }

        // --- FETCH AVAILABLE ROOMS FOR CROSS-DEPT REQUESTS ---
        if ($action === 'get_available_rooms') {
            $ay = $_GET['ay'] ?? '';
            $req_days = $_GET['days'] ?? '';
            $req_start = $_GET['start_time'] ?? '';
            $req_end = $_GET['end_time'] ?? '';

            $sql = "SELECT * FROM rooms WHERE department = ? AND status != 'Under Maintenance' ORDER BY room_name ASC";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $userDept);
            $stmt->execute();
            $room_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $req_days_arr = array_map('trim', explode(',', $req_days));
            $req_start_f = date("H:i:s", strtotime($req_start));
            $req_end_f = date("H:i:s", strtotime($req_end));

            foreach ($room_list as &$room) {
                $room['has_conflict'] = false;
                $sched_sql = "SELECT days, start_time, end_time FROM class_schedules WHERE room_id = ? AND academic_year = ?";
                $s_stmt = $conn->prepare($sched_sql);
                $s_stmt->bind_param("is", $room['id'], $ay);
                $s_stmt->execute();
                $schedules = $s_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

                foreach ($schedules as $s) {
                    $s_days_arr = array_map('trim', explode(',', $s['days']));
                    if (!empty(array_intersect($req_days_arr, $s_days_arr))) {
                        if ($req_start_f < $s['end_time'] && $s['start_time'] < $req_end_f) {
                            $room['has_conflict'] = true; break;
                        }
                    }
                }
            }
            echo json_encode($room_list);
            exit;
        }

        // --- FETCH PENDING SCHEDULES (INTERNAL & INCOMING CROSS-DEPT) ---
        if ($action === 'get_requests') {
            $sql = "SELECT s.*, c.course_code, c.descriptive_title, c.units, c.lecture_hours, c.laboratory_hours,
                           r.room_name,
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) AS professor_name
                    FROM class_schedules s 
                    JOIN courses c ON s.course_id = c.id 
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id
                    WHERE 
                        (s.department = ? AND s.approval_status = 'Pending') 
                        OR 
                        (s.requested_department = ? AND s.approval_status = 'Approved' AND s.faculty_id IN (?, ?))
                        OR 
                        (s.requested_room_department = ? AND s.approval_status = 'Approved' AND (s.room_id IS NULL OR s.room_id = 0))
                    ORDER BY s.created_at DESC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssiis", $userDept, $userDept, $tba_id, $gened_id, $userDept);
            $stmt->execute();
            
            $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // INJECT FLAGS SO FRONTEND KNOWS EXACTLY WHAT TYPE OF REQUEST IT IS
            foreach ($requests as &$req) {
                $req['is_cross_prof_req'] = ($req['requested_department'] === $userDept && in_array($req['faculty_id'], [$tba_id, $gened_id]));
                $req['is_cross_room_req'] = ($req['requested_room_department'] === $userDept && (empty($req['room_id']) || $req['room_id'] == 0));
            }

            echo json_encode($requests);
            exit;
        }

        // --- FETCH OUTGOING PENDING SCHEDULES (AWAITING REPLY) ---
        if ($action === 'get_outgoing_requests') {
            $sql = "SELECT s.*, c.course_code, c.descriptive_title 
                    FROM class_schedules s 
                    JOIN courses c ON s.course_id = c.id 
                    WHERE s.department = ? 
                    AND s.approval_status = 'Approved' 
                    AND (
                        (s.requested_department IS NOT NULL AND s.requested_department != '' AND s.faculty_id IN (?, ?))
                        OR 
                        (s.requested_room_department IS NOT NULL AND s.requested_room_department != '' AND (s.room_id IS NULL OR s.room_id = 0))
                    )
                    ORDER BY s.created_at DESC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $userDept, $tba_id, $gened_id);
            $stmt->execute();
            
            $outgoing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            foreach ($outgoing as &$req) {
                $req['is_prof_waiting'] = (!empty($req['requested_department']) && in_array($req['faculty_id'], [$tba_id, $gened_id]));
                $req['is_room_waiting'] = (!empty($req['requested_room_department']) && (empty($req['room_id']) || $req['room_id'] == 0));
            }
            
            echo json_encode($outgoing);
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        
        // --- BATCH APPROVAL FOR SECTIONS ---
        if ($action === 'approve_batch') {
            $ids = json_decode($_POST['schedule_ids'], true);
            $groupInfo = $_POST['group_info'] ?? 'a section';

            if (is_array($ids) && count($ids) > 0) {
                $stmt = $conn->prepare("UPDATE class_schedules SET approval_status = 'Approved' WHERE id = ?");
                foreach ($ids as $id) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $stmt->close();
                logActivity($conn, 'Approval', 'UPDATE', "Approved schedule block for $groupInfo (" . count($ids) . " subjects)", $userDept);
                echo json_encode(["status" => "success", "message" => "Schedule approved successfully!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "No schedules provided."]);
            }
            exit;
        }

        // --- BATCH REJECTION FOR SECTIONS ---
        if ($action === 'reject_batch') {
            $ids = json_decode($_POST['schedule_ids'], true);
            $groupInfo = $_POST['group_info'] ?? 'a section';

            if (is_array($ids) && count($ids) > 0) {
                $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
                foreach ($ids as $id) {
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                }
                $stmt->close();
                logActivity($conn, 'Approval', 'DELETE', "Rejected and deleted schedule block for $groupInfo", $userDept);
                echo json_encode(["status" => "success", "message" => "Schedule rejected and removed."]);
            } else {
                echo json_encode(["status" => "error", "message" => "No schedules provided."]);
            }
            exit;
        }

        $sched_id = $_POST['schedule_id'] ?? 0;
        if ($sched_id > 0) {
            $info_stmt = $conn->prepare("SELECT s.department, s.section, s.requested_department, s.requested_room_department, c.course_code FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.id = ?");
            $info_stmt->bind_param("i", $sched_id);
            $info_stmt->execute();
            $info_res = $info_stmt->get_result()->fetch_assoc();
            
            $req_dept = $info_res['department'] ?? $userDept; 
            $course_info = ($info_res['course_code'] ?? 'Unknown') . " (Sec: " . ($info_res['section'] ?? 'Unknown') . ")";

            // --- APPROVE PROFESSOR REQUEST ---
            if ($action === 'approve_cross_prof') {
                $fid = $_POST['faculty_id'] ?? 0;
                if($fid == 0) { echo json_encode(["status" => "error", "message" => "Select a professor."]); exit; }
                
                $prof_stmt = $conn->prepare("SELECT TRIM(CONCAT(first_name, ' ', surname)) as pname FROM users WHERE id = ?");
                $prof_stmt->bind_param("i", $fid);
                $prof_stmt->execute();
                $prof_name = $prof_stmt->get_result()->fetch_assoc()['pname'] ?? 'Unknown Professor';

                $stmt = $conn->prepare("UPDATE class_schedules SET faculty_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $fid, $sched_id);
                if($stmt->execute()) {
                    logActivity($conn, 'Approval', 'UPDATE', "✅ APPROVED: $userDept assigned Prof. $prof_name to $course_info", $req_dept);
                    logActivity($conn, 'Approval', 'UPDATE', "✅ ASSIGNED: Prof. $prof_name to $req_dept for $course_info", $userDept);
                    echo json_encode(["status" => "success", "message" => "Request Approved! Prof. $prof_name has been assigned."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error assigning."]);
                }
                exit;
            }

            // --- APPROVE ROOM REQUEST ---
            if ($action === 'approve_cross_room') {
                $rid = $_POST['room_id'] ?? 0;
                if($rid == 0) { echo json_encode(["status" => "error", "message" => "Select a room."]); exit; }
                
                $room_stmt = $conn->prepare("SELECT room_name FROM rooms WHERE id = ?");
                $room_stmt->bind_param("i", $rid);
                $room_stmt->execute();
                $room_name = $room_stmt->get_result()->fetch_assoc()['room_name'] ?? 'Unknown Room';

                $stmt = $conn->prepare("UPDATE class_schedules SET room_id = ? WHERE id = ?");
                $stmt->bind_param("ii", $rid, $sched_id);
                if($stmt->execute()) {
                    logActivity($conn, 'Approval', 'UPDATE', "✅ APPROVED: $userDept assigned Room $room_name to $course_info", $req_dept);
                    logActivity($conn, 'Approval', 'UPDATE', "✅ ASSIGNED: Room $room_name to $req_dept for $course_info", $userDept);
                    echo json_encode(["status" => "success", "message" => "Request Approved! Room $room_name has been assigned."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error assigning room."]);
                }
                exit;
            }

            // --- REJECT CROSS REQUESTS INDEPENDENTLY ---
            if ($action === 'reject_prof' || $action === 'reject_room') {
                if ($action === 'reject_prof') {
                    $stmt = $conn->prepare("UPDATE class_schedules SET requested_department = NULL WHERE id = ?");
                    $log_detail = "Professor";
                } else {
                    $stmt = $conn->prepare("UPDATE class_schedules SET requested_room_department = NULL WHERE id = ?");
                    $log_detail = "Room";
                }
                
                $stmt->bind_param("i", $sched_id);
                if($stmt->execute()) {
                    logActivity($conn, 'Approval', 'UPDATE', "❌ REJECTED: $userDept denied $log_detail request for $course_info", $req_dept);
                    logActivity($conn, 'Approval', 'UPDATE', "❌ REJECTED: $log_detail request from $req_dept for $course_info", $userDept);
                    echo json_encode(["status" => "success", "message" => "$log_detail Request Rejected."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error rejecting."]);
                }
                exit;
            }
        }
    }
} catch (Exception $e) {
    http_response_code(400); echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>