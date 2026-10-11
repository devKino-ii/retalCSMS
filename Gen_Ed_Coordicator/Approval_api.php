<?php
session_start();
include '../db_connect.php';
header('Content-Type: application/json');

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? '';

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'General Education' || stripos($userPos, 'Gen') !== false || (stripos($userPos, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));
$targetDept = $isGenEd ? 'General Education' : $userDept;

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

        // --- FETCH DECISION HISTORY FOR NOTIFICATIONS ---
        if ($action === 'get_history') {
            $sql = "SELECT * FROM activity_logs 
                    WHERE department = ? AND module = 'Approval' 
                    ORDER BY created_at DESC LIMIT 10";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $targetDept);
            $stmt->execute();
            echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
            $stmt->close();
            exit;
        }

        // --- FETCH FACULTY WITH CONFLICT DETECTION ---
        if ($action === 'get_faculty_load') {
            $ay = $_GET['ay'] ?? '';
            $req_days = $_GET['days'] ?? '';
            $req_start = $_GET['start_time'] ?? '';
            $req_end = $_GET['end_time'] ?? '';

            // DYNAMIC CONDITION: Gen Ed users fetch by 'program', normal Deans fetch by 'department'
            $facultyCondition = $isGenEd ? "u.program = ?" : "u.department = ?";

            $sql = "SELECT u.id, 
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.middle_initial, ''), ' ', COALESCE(u.surname, ''))) AS full_name, 
                           COALESCE(u.program, 'General') AS program,
                           21 AS max_units,
                           COALESCE(SUM(c.units), 0) AS current_load
                    FROM users u
                    LEFT JOIN class_schedules s ON u.id = s.faculty_id AND s.academic_year = ? AND s.approval_status != 'Pending'
                    LEFT JOIN courses c ON s.course_id = c.id
                    WHERE $facultyCondition 
                    AND u.first_name NOT IN ('TBA', 'General Education') 
                    AND u.position != 'System'
                    GROUP BY u.id, u.program
                    ORDER BY u.first_name ASC";
                    
            $stmt = $conn->prepare($sql);
            // $targetDept will naturally be 'General Education' if $isGenEd is true
            $stmt->bind_param("ss", $ay, $targetDept);
            $stmt->execute();
            $faculty_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $req_days_arr = array_map('trim', explode(',', $req_days));
            $req_start_f = date("H:i:s", strtotime($req_start));
            $req_end_f = date("H:i:s", strtotime($req_end));

            // 2. Loop through Faculty and Detect Overlaps
            foreach ($faculty_list as &$f) {
                $f['has_conflict'] = false;
                $f['conflict_details'] = "";
                
                if ($req_start !== '') {
                    $cs = $conn->prepare("SELECT s.days, s.start_time, s.end_time, c.course_code FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.faculty_id = ? AND s.academic_year = ? AND s.approval_status = 'Approved'");
                    $cs->bind_param("is", $f['id'], $ay);
                    $cs->execute();
                    $existing = $cs->get_result()->fetch_all(MYSQLI_ASSOC);
                    
                    foreach ($existing as $ex) {
                        $e_days = array_map('trim', explode(',', $ex['days']));
                        $intersect = array_intersect($req_days_arr, $e_days); // Check if they share a day
                        
                        if (count($intersect) > 0) {
                            // Check Time Overlap: (Start A < End B) AND (End A > Start B)
                            if ($req_start_f < $ex['end_time'] && $req_end_f > $ex['start_time']) {
                                $f['has_conflict'] = true;
                                $st = date("g:i A", strtotime($ex['start_time']));
                                $en = date("g:i A", strtotime($ex['end_time']));
                                $f['conflict_details'] = "Already teaching {$ex['course_code']} on " . implode(', ', $intersect) . " ($st - $en)";
                                break; 
                            }
                        }
                    }
                }
            }
            
            echo json_encode($faculty_list);
            exit;
        }

        // --- FETCH PENDING SCHEDULES (INCOMING) ---
        if ($action === 'get_requests') {
            $sql = "SELECT s.*, c.course_code, c.descriptive_title, c.units, c.lecture_hours, c.laboratory_hours,
                           r.room_name,
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) AS professor_name
                    FROM class_schedules s 
                    JOIN courses c ON s.course_id = c.id 
                    LEFT JOIN users u ON s.faculty_id = u.id
                    LEFT JOIN rooms r ON s.room_id = r.id
                    WHERE (s.department = ? AND s.approval_status = 'Pending') 
                       OR (s.requested_department = ? AND s.approval_status = 'Approved' AND s.faculty_id IN (?, ?))
                    ORDER BY s.created_at DESC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssii", $targetDept, $targetDept, $tba_id, $gened_id);
            $stmt->execute();
            
            $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // INJECT FLAGS SO FRONTEND KNOWS EXACTLY WHAT TYPE OF REQUEST IT IS
            foreach ($requests as &$req) {
                $req['is_cross_prof_req'] = ($req['requested_department'] === $targetDept && in_array($req['faculty_id'], [$tba_id, $gened_id]));
                $req['is_cross_room_req'] = false; // Add this if you ever expand rooms to Gen Ed
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
                    AND s.requested_department IS NOT NULL AND s.requested_department != ''
                    AND s.faculty_id IN (?, ?)
                    ORDER BY s.created_at DESC";
                    
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sii", $targetDept, $tba_id, $gened_id);
            $stmt->execute();
            $outgoing = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Inject helper flags so JS doesn't have to guess
            foreach ($outgoing as &$req) {
                $req['is_prof_waiting'] = true;
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
                logActivity($conn, 'Approval', 'UPDATE', "Approved schedule block for $groupInfo (" . count($ids) . " subjects)", $targetDept);
                echo json_encode(["status" => "success", "message" => "Entire schedule block approved successfully!"]);
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
                logActivity($conn, 'Approval', 'DELETE', "Rejected and deleted schedule block for $groupInfo", $targetDept);
                echo json_encode(["status" => "success", "message" => "Entire schedule block rejected and removed."]);
            } else {
                echo json_encode(["status" => "error", "message" => "No schedules provided."]);
            }
            exit;
        }

        // --- SINGLE ACTIONS (Cross-Department Requests) ---
        $sched_id = $_POST['schedule_id'] ?? 0;
        if ($sched_id > 0) {
            $info_stmt = $conn->prepare("SELECT s.department, s.section, s.requested_department, s.academic_year, s.semester, s.days, s.start_time, s.end_time, c.course_code FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.id = ?");
            $info_stmt->bind_param("i", $sched_id);
            $info_stmt->execute();
            $info_res = $info_stmt->get_result()->fetch_assoc();
            
            $host_dept = $info_res['department'] ?? $targetDept; 
            $req_dept = $info_res['requested_department'] ?? '';
            $course_info = ($info_res['course_code'] ?? 'Unknown') . " (Sec: " . ($info_res['section'] ?? 'Unknown') . ")";

            $isCrossReq = ($req_dept !== '' && $req_dept !== $host_dept);

            if ($action === 'approve_cross_prof') {
                $fid = $_POST['faculty_id'] ?? 0;
                if($fid == 0) { echo json_encode(["status" => "error", "message" => "Select a professor."]); exit; }
                
                // --- Backend Conflict Guard ---
                $t_days = array_map('trim', explode(',', $info_res['days']));
                $cs = $conn->prepare("SELECT s.days, s.start_time, s.end_time, c.course_code FROM class_schedules s JOIN courses c ON s.course_id = c.id WHERE s.faculty_id = ? AND s.academic_year = ? AND s.semester = ? AND s.approval_status = 'Approved'");
                $cs->bind_param("iss", $fid, $info_res['academic_year'], $info_res['semester']);
                $cs->execute();
                $existing = $cs->get_result()->fetch_all(MYSQLI_ASSOC);
                
                foreach ($existing as $ex) {
                    $e_days = array_map('trim', explode(',', $ex['days']));
                    $intersect = array_intersect($t_days, $e_days);
                    if (count($intersect) > 0) {
                        if ($info_res['start_time'] < $ex['end_time'] && $info_res['end_time'] > $ex['start_time']) {
                            echo json_encode(["status" => "error", "message" => "Strict Security: This professor has a schedule conflict and cannot be assigned!"]);
                            exit;
                        }
                    }
                }
                // --- End Guard ---

                $prof_stmt = $conn->prepare("SELECT TRIM(CONCAT(first_name, ' ', surname)) as pname FROM users WHERE id = ?");
                $prof_stmt->bind_param("i", $fid);
                $prof_stmt->execute();
                $prof_name = $prof_stmt->get_result()->fetch_assoc()['pname'] ?? 'Unknown Professor';

                $stmt = $conn->prepare("UPDATE class_schedules SET faculty_id = ?, approval_status = 'Approved' WHERE id = ?");
                $stmt->bind_param("ii", $fid, $sched_id);
                if($stmt->execute()) {
                    if ($isCrossReq) {
                        logActivity($conn, 'Approval', 'UPDATE', "✅ APPROVED: $targetDept assigned Prof. $prof_name to $course_info", $host_dept);
                        logActivity($conn, 'Approval', 'UPDATE', "✅ ASSIGNED: Prof. $prof_name to $host_dept for $course_info", $targetDept);
                    } else {
                        logActivity($conn, 'Approval', 'UPDATE', "Approved internal schedule for $course_info", $targetDept);
                    }
                    echo json_encode(["status" => "success", "message" => "Request Approved! Prof. $prof_name has been assigned to the class."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error assigning."]);
                }
                exit;
            }

            // Reject single Cross-Prof requests without deleting the schedule entirely
            if ($action === 'reject_prof') {
                $stmt = $conn->prepare("UPDATE class_schedules SET requested_department = NULL WHERE id = ?");
                $stmt->bind_param("i", $sched_id);
                
                if($stmt->execute()) {
                    if ($isCrossReq) {
                        logActivity($conn, 'Approval', 'UPDATE', "❌ REJECTED: $targetDept denied Professor request for $course_info", $host_dept);
                        logActivity($conn, 'Approval', 'UPDATE', "❌ REJECTED: Professor request from $host_dept for $course_info", $targetDept);
                    }
                    echo json_encode(["status" => "success", "message" => "Professor Request Rejected."]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Error rejecting."]);
                }
                exit;
            }
            
            // Standard internal reject
            if ($action === 'reject') {
                $stmt = $conn->prepare("DELETE FROM class_schedules WHERE id = ?");
                $stmt->bind_param("i", $sched_id);
                if($stmt->execute()) {
                    logActivity($conn, 'Approval', 'DELETE', "Rejected internal schedule for $course_info", $targetDept);
                    echo json_encode(["status" => "success", "message" => "Request Rejected and Deleted."]);
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