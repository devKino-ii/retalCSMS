<?php
// Dashboard_api.php
session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
include '../db_connect.php';
header('Content-Type: application/json');

$userDept = $_SESSION['department'] ?? '';

// =========================================================
// Auto-schema: ensure requested_room_department column exists
// =========================================================
try {
    $checkCol = $conn->query("SHOW COLUMNS FROM class_schedules LIKE 'requested_room_department'");
    if ($checkCol && $checkCol->num_rows == 0) {
        $conn->query("ALTER TABLE class_schedules ADD COLUMN requested_room_department VARCHAR(100) NULL AFTER requested_department");
    }
} catch (Exception $e) { /* ignore */ }

try {
    // ── Special faculty helpers ───────────────────────────
    function getSpecialFacultyId($conn, $name) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE first_name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            return $res->fetch_assoc()['id'];
        }
        $username = strtolower(str_replace(' ', '', $name)) . '_sys';
        $email    = $username . "@urs.edu.ph";
        $surname  = "System";
        $sex      = "Other";
        $emp_id   = strtoupper($username);
        $sys_val  = "System";
        $insert   = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, sex, employee_id, department, position, program) VALUES (?, 'locked', ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($insert) {
            $insert->bind_param("sssssssss", $username, $email, $surname, $name, $sex, $emp_id, $sys_val, $sys_val, $sys_val);
            $insert->execute();
            return $insert->insert_id;
        }
        return 0;
    }

    $tba_id   = getSpecialFacultyId($conn, 'TBA');
    $gened_id = getSpecialFacultyId($conn, 'General Education');

    $action = $_GET['action'] ?? '';

    // =========================================================
    // ACTION: init_data
    // =========================================================
    if ($action === 'init_data') {
        $dept_esc = $conn->real_escape_string($userDept);

        // Fetch user's department rooms AND University rooms
        $roomsRes = $conn->query("SELECT * FROM rooms WHERE department = '{$dept_esc}' OR department = 'University' ORDER BY department ASC, room_name ASC");
        $rooms    = $roomsRes ? $roomsRes->fetch_all(MYSQLI_ASSOC) : [];

        // Academic years — from ALL schedules visible to this dept
        $ays = [];
        $q_ays = $conn->query(
            "SELECT DISTINCT academic_year FROM class_schedules
             WHERE department = '{$dept_esc}'
             ORDER BY academic_year DESC"
        );
        if ($q_ays) {
            while ($r = $q_ays->fetch_assoc()) {
                if (!empty($r['academic_year'])) $ays[] = $r['academic_year'];
            }
        }

        // Programs — all distinct programs in this dept's schedules
        $progs = [];
        $q_progs = $conn->query(
            "SELECT DISTINCT program FROM class_schedules
             WHERE department = '{$dept_esc}' AND program IS NOT NULL AND program != ''
             ORDER BY program"
        );
        if ($q_progs) {
            while ($r = $q_progs->fetch_assoc()) {
                if (!empty($r['program'])) $progs[] = $r['program'];
            }
        }

        // Widget counts (only count their own department rooms, not university rooms)
        $rooms_only_dept = $conn->query("SELECT COUNT(id) as c FROM rooms WHERE department = '{$dept_esc}'");
        $room_count = $rooms_only_dept ? ($rooms_only_dept->fetch_assoc()['c'] ?? 0) : 0;
        
        $fac_query  = $conn->query(
            "SELECT COUNT(id) as c FROM users
             WHERE department = '{$dept_esc}' AND id NOT IN ({$tba_id}, {$gened_id})"
        );
        $fac_count  = $fac_query ? ($fac_query->fetch_assoc()['c'] ?? 0) : 0;

        // Lightweight change-detection hash
        $hash_query = $conn->query(
            "SELECT COUNT(id) as cnt, MAX(created_at) as latest
             FROM class_schedules WHERE department = '{$dept_esc}'"
        );
        $hash_row  = $hash_query ? $hash_query->fetch_assoc() : [];
        $dataHash  = md5(($hash_row['cnt'] ?? 0) . '|' . ($hash_row['latest'] ?? ''));

        echo json_encode([
            'rooms'          => $rooms,
            'academic_years' => $ays,
            'programs'       => $progs,
            'counts'         => ['faculty' => $fac_count, 'rooms' => $room_count],
            'data_hash'      => $dataHash,
        ]);
        exit;
    }

    // =========================================================
    // ACTION: data_hash  (lightweight polling)
    // =========================================================
    if ($action === 'data_hash') {
        $dept_esc   = $conn->real_escape_string($userDept);
        $hash_query = $conn->query(
            "SELECT COUNT(id) as cnt, MAX(created_at) as latest
             FROM class_schedules WHERE department = '{$dept_esc}'"
        );
        $hash_row = $hash_query ? $hash_query->fetch_assoc() : [];
        $dataHash = md5(($hash_row['cnt'] ?? 0) . '|' . ($hash_row['latest'] ?? ''));
        echo json_encode(['data_hash' => $dataHash]);
        exit;
    }

    // =========================================================
    // ACTION: get_schedules
    // =========================================================
    if ($action === 'get_schedules') {
        $ay   = $_GET['ay']   ?? 'All';
        $sem  = $_GET['sem']  ?? 'All';
        $prog = $_GET['prog'] ?? 'All';
        $day  = $_GET['day']  ?? 'All';

        $dept_esc = $conn->real_escape_string($userDept);

        // ── Base query: Updated specialization to major ──
        $sql = "SELECT
                    s.id, s.course_id, s.faculty_id, s.room_id,
                    s.department, s.program, s.year_level, s.semester,
                    s.section, s.academic_year, s.days,
                    s.start_time, s.end_time, s.approval_status,
                    s.requested_department, s.requested_room_department,
                    COALESCE(s.units,             c.units)            AS units,
                    COALESCE(s.lecture_hours,     c.lecture_hours)    AS lecture_hours,
                    COALESCE(s.laboratory_hours,  c.laboratory_hours) AS laboratory_hours,
                    c.course_code, c.descriptive_title, c.major,
                    TRIM(CONCAT(
                        COALESCE(u.first_name, ''), ' ',
                        COALESCE(u.surname,     '')
                    )) AS professor,
                    r.room_name AS assigned_room_name
                FROM class_schedules s
                JOIN courses c ON s.course_id = c.id
                LEFT JOIN users u ON s.faculty_id = u.id
                LEFT JOIN rooms r ON s.room_id = r.id
                WHERE s.department = '{$dept_esc}'";

        if ($ay   !== 'All') { $sql .= " AND s.academic_year = '"  . $conn->real_escape_string($ay)   . "'"; }
        if ($sem  !== 'All') { $sql .= " AND s.semester = '"       . $conn->real_escape_string($sem)  . "'"; }
        if ($prog !== 'All') { $sql .= " AND s.program = '"        . $conn->real_escape_string($prog) . "'"; }

        if ($day !== 'All') {
            $d_esc   = $conn->real_escape_string($day);
            $aliases = [
                'Mon' => ['Mon', 'Monday', 'M'],
                'Tue' => ['Tue', 'Tuesday', 'T'],
                'Wed' => ['Wed', 'Wednesday', 'W'],
                'Thu' => ['Thu', 'Thursday', 'Th', 'TH'],
                'Fri' => ['Fri', 'Friday', 'F'],
                'Sat' => ['Sat', 'Saturday', 'S'],
                'Sun' => ['Sun', 'Sunday', 'Su', 'SU'],
            ];
            $variants = $aliases[$day] ?? [$day];
            $likes    = array_map(
                fn($v) => "s.days LIKE '%" . $conn->real_escape_string($v) . "%'",
                $variants
            );
            $sql .= " AND (" . implode(' OR ', $likes) . ")";
        }

        $res  = $conn->query($sql);
        $data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

        // ── Post-process rows ──────────────────────────────────
        foreach ($data as &$row) {
            if (($row['approval_status'] ?? '') === 'Pending') {
                if (!empty($row['requested_room_department'])) {
                    $row['room_id'] = 'REQ_' . $row['requested_room_department'];
                } elseif (empty($row['room_id']) || $row['room_id'] == '0') {
                    $row['room_id'] = '0';
                }
                
                if (!empty($row['requested_department'])) {
                    if ($row['requested_department'] === 'General Education') {
                        $row['faculty_id'] = '-1';
                        $row['professor']  = '⏳ Pending: Gen Ed';
                    } else {
                        $row['faculty_id'] = 'REQ_' . $row['requested_department'];
                        $row['professor']  = '⏳ Pending: ' . $row['requested_department'];
                    }
                } else {
                    if ($row['faculty_id'] == $tba_id || empty(trim($row['professor']))) {
                        $row['faculty_id'] = '0';
                        $row['professor']  = '⏳ Pending Dean (TBA)';
                    } elseif (empty($row['requested_room_department'])) {
                        $row['professor'] = '⏳ Pending Dean: ' . $row['professor'];
                    }
                }
            } else {
                if (!empty($row['assigned_room_name'])) {
                    $row['assigned_room_display'] = $row['assigned_room_name'];
                }
                if (empty($row['room_id']) || $row['room_id'] == '0') {
                    $row['room_id'] = '0';
                }
                if ($row['faculty_id'] == $tba_id || empty(trim($row['professor']))) {
                    $row['faculty_id'] = '0';
                    $row['professor']  = 'TBA';
                } elseif ($row['faculty_id'] == $gened_id) {
                    $row['faculty_id'] = '-1';
                    $row['professor']  = 'General Education';
                }
            }

            unset($row['assigned_room_name']);
        }
        unset($row);

        if ($day !== 'All') {
            $data = array_values(array_filter($data, function ($row) use ($day) {
                return dayMatchesPHP($row['days'] ?? '', $day);
            }));
        }

        echo json_encode($data);
        exit;
    }

    // =========================================================
    // ACTION: get_rooms
    // =========================================================
    if ($action === 'get_rooms') {
        $dept_esc = $conn->real_escape_string($userDept);
        $res = $conn->query("SELECT * FROM rooms WHERE department = '{$dept_esc}' OR department = 'University' ORDER BY department ASC, room_name ASC");
        echo json_encode($res ? $res->fetch_all(MYSQLI_ASSOC) : []);
        exit;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

// =========================================================
// Helper: PHP-side day matching
// =========================================================
function dayMatchesPHP(string $daysStr, string $filterDay): bool {
    if (empty($daysStr)) return false;

    static $aliases = [
        'M'        => 'Mon', 'T'   => 'Tue', 'W'   => 'Wed',
        'TH'       => 'Thu', 'F'   => 'Fri', 'S'   => 'Sat', 'SU' => 'Sun',
        'MON'      => 'Mon', 'TUE' => 'Tue', 'WED' => 'Wed',
        'THU'      => 'Thu', 'FRI' => 'Fri', 'SAT' => 'Sat', 'SUN' => 'Sun',
        'MONDAY'   => 'Mon', 'TUESDAY'  => 'Tue',  'WEDNESDAY' => 'Wed',
        'THURSDAY' => 'Thu', 'FRIDAY'   => 'Fri',  'SATURDAY'  => 'Sat',
        'SUNDAY'   => 'Sun',
    ];

    $normalised = preg_replace('/[\\/\\-\\s]+/', ',', $daysStr);
    $parts      = array_filter(array_map('trim', explode(',', $normalised)));

    foreach ($parts as $part) {
        $upper    = strtoupper($part);
        $resolved = $aliases[$upper] ?? ucfirst(strtolower($part));
        if ($resolved === $filterDay) return true;
    }
    return false;
}
?>