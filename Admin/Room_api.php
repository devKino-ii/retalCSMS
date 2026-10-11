<?php
// ============================================================
//  ROOM MANAGEMENT API — COSMOS
// ============================================================
session_start();

// Output Buffer: Capture accidental output/warnings so they don't break JSON
ob_start();
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF); 
error_reporting(E_ALL);

require '../db_connect.php';

// --- STRICT ROLE-BASED ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? 'Staff';
$isAdmin  = (stripos($userPos, 'Admin') !== false); 

try {
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    $action = $_GET['action'] ?? '';

    // ==========================================
    // GET REQUESTS (FETCHING DATA)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        // 1. Fetch rooms
        if ($action === 'get_rooms') {
            if ($isAdmin) {
                // Admin pulls all rooms grouped by department ordering
                $sql = "SELECT * FROM rooms ORDER BY department ASC, room_name ASC";
                $stmt = $conn->prepare($sql);
            } else {
                // Regular user pulls only their department
                $sql = "SELECT * FROM rooms WHERE department = ? ORDER BY room_name ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("s", $userDept);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }

        // 2. Fetch schedule filters (AY and Semester)
        if ($action === 'get_schedule_filters') {
            $ays = []; 
            $sems = [];
            
            if ($isAdmin) {
                $resAY = $conn->query("SELECT DISTINCT academic_year FROM class_schedules WHERE academic_year IS NOT NULL ORDER BY academic_year DESC");
                $resSem = $conn->query("SELECT DISTINCT semester FROM class_schedules WHERE semester IS NOT NULL ORDER BY semester ASC");
            } else {
                $resAY = $conn->query("SELECT DISTINCT academic_year FROM class_schedules WHERE department = '" . $conn->real_escape_string($userDept) . "' AND academic_year IS NOT NULL ORDER BY academic_year DESC");
                $resSem = $conn->query("SELECT DISTINCT semester FROM class_schedules WHERE department = '" . $conn->real_escape_string($userDept) . "' AND semester IS NOT NULL ORDER BY semester ASC");
            }

            if ($resAY) {
                while ($row = $resAY->fetch_assoc()) { $ays[] = $row['academic_year']; }
            }
            if ($resSem) {
                while ($row = $resSem->fetch_assoc()) { $sems[] = $row['semester']; }
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(['ays' => $ays, 'sems' => $sems]);
            exit;
        }

        // 3. Fetch specific room schedule
        if ($action === 'get_schedule') {
            $roomId = $_GET['room_id'] ?? '';
            $ay     = $_GET['ay'] ?? '';
            $sem    = $_GET['sem'] ?? '';
            
            $sql = "SELECT cs.days, cs.start_time, cs.end_time, cs.section, c.course_code, c.descriptive_title, 
                           TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.surname, ''))) as professor
                    FROM class_schedules cs
                    LEFT JOIN courses c ON cs.course_id = c.id
                    LEFT JOIN users u ON cs.faculty_id = u.id
                    WHERE cs.room_id = ? AND cs.academic_year = ? AND cs.semester = ? AND cs.approval_status = 'Approved'";
            
            if (!$isAdmin) {
                $sql .= " AND cs.department = ?";
                $stmt = $conn->prepare($sql . " ORDER BY cs.start_time ASC");
                $stmt->bind_param("isss", $roomId, $ay, $sem, $userDept);
            } else {
                $stmt = $conn->prepare($sql . " ORDER BY cs.start_time ASC");
                $stmt->bind_param("iss", $roomId, $ay, $sem);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($data);
            exit;
        }
    }

    // ==========================================
    // POST REQUESTS (CREATE, UPDATE, DELETE)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $targetDept = $isAdmin ? ($_POST['department'] ?? $userDept) : $userDept;

        // A. DELETE ROOM
        if (isset($_POST['delete_id'])) {
            $id = (int)$_POST['delete_id'];
            $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ? AND department = ?");
            $stmt->bind_param("is", $id, $targetDept);
            
            if (!$stmt->execute()) {
                if ($conn->errno == 1451) {
                    throw new Exception("You cannot delete this room because it is currently assigned to a class schedule.");
                }
                throw new Exception("Error deleting room: " . $conn->error);
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode(["status" => "success", "message" => "Room deleted successfully."]);
            exit;
        }

        // B. COLLECT INPUTS FOR CREATE/UPDATE
        $id       = $_POST['room_id'] ?? '';
        $name     = trim($_POST['room_name'] ?? '');
        $type     = trim($_POST['room_type'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $status   = trim($_POST['status'] ?? '');

        if (empty($name)) {
            throw new Exception("Room name is required.");
        }
        if (empty($targetDept)) {
            throw new Exception("Department is required.");
        }

        // C. DUPLICATE CHECK
        $checkId = $id ? $id : -1;
        $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_name = ? AND department = ? AND id != ?");
        $stmt->bind_param("ssi", $name, $targetDept, $checkId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new Exception("A room with the name '$name' already exists in the $targetDept department.");
        }
        $stmt->close();

        // D. UPDATE OR INSERT
        if (!empty($id)) {
            $stmt = $conn->prepare("UPDATE rooms SET room_name=?, room_type=?, location=?, department=?, status=? WHERE id=?");
            // Note: department can be updated by admin now
            $stmt->bind_param("sssssi", $name, $type, $location, $targetDept, $status, $id);
            if (!$stmt->execute()) throw new Exception("Failed to update room.");
            
            $msg = "Room details updated successfully.";
        } else {
            $stmt = $conn->prepare("INSERT INTO rooms (room_name, room_type, location, department, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $type, $location, $targetDept, $status);
            if (!$stmt->execute()) throw new Exception("Failed to register room.");
            
            $msg = "New room registered successfully.";
        }

        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(["status" => "success", "message" => $msg]);
        exit;
    }

} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
?>