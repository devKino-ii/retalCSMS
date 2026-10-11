<?php
// 1. Start the session securely
session_start();

// 2. Output Buffer: Start capturing any accidental output or PHP warnings
ob_start();

// Disable error displaying to screen so it doesn't break JSON
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF); 
error_reporting(E_ALL);

include '../db_connect.php'; 

// --- STRICT ROLE-BASED ACCESS CONTROL ---
if (!isset($_SESSION['user_id'])) {
    ob_clean(); // Wipes the buffer so ONLY json is returned
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => "Unauthorized access. Please log in."]);
    exit;
}

$userId = $_SESSION['user_id'];
$userDept = $_SESSION['department'] ?? '';

// ==========================================
// REUSABLE LOGGER FUNCTION
// ==========================================
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
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // --- SECURITY: GET USER POSITION AND PROGRAM ---
    $stmtUser = $conn->prepare("SELECT position, program FROM users WHERE id = ?");
    $stmtUser->bind_param("i", $userId);
    $stmtUser->execute();
    $userRow = $stmtUser->get_result()->fetch_assoc();
    $stmtUser->close();

    $userPosition = $userRow['position'] ?? '';
    $userProgram = $userRow['program'] ?? '';
    $isProgramHead = ($userPosition === 'Program Head');

    // --- HANDLE POST REQUESTS ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // A. DELETE ACTION
        if (isset($_POST['delete_id'])) {
            $del_id = (int)$_POST['delete_id'];
            
            // Fetch course details before deleting so we know what to log
            $q = $conn->query("SELECT course_code, descriptive_title FROM courses WHERE id=$del_id");
            $course_data = $q->fetch_assoc();
            $c_info = $course_data ? $course_data['course_code'] . " - " . $course_data['descriptive_title'] : "ID:$del_id";

            if ($isProgramHead) {
                $stmt = $conn->prepare("DELETE FROM courses WHERE id=? AND department=? AND program=?");
                $stmt->bind_param("iss", $del_id, $userDept, $userProgram);
            } else {
                $stmt = $conn->prepare("DELETE FROM courses WHERE id=? AND department=?");
                $stmt->bind_param("is", $del_id, $userDept);
            }
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    // --- LOG ACTIVITY ---
                    logActivity($conn, 'Course', 'DELETE', "Deleted course: $c_info");
                    $response = ["status" => "success", "message" => "The course was successfully deleted."];
                } else {
                    throw new Exception("Course not found or you do not have permission to delete it.");
                }
            } else {
                if ($conn->errno == 1451) {
                    throw new Exception("You cannot delete this Course because it is currently being used in a Class Schedule. Please remove it from the schedule first.");
                } else {
                    throw new Exception("System Error: " . $conn->error);
                }
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // B. COLLECT INPUTS
        $id = $_POST['course_id'] ?? null;
        $dept = $userDept; 
        
        // If Program Head, FORCE the program to be their assigned program
        $prog = $isProgramHead ? $userProgram : trim($_POST['program'] ?? '');
        $major = trim($_POST['major'] ?? '');
        
        $year = trim($_POST['year_level'] ?? '');
        $sem = trim($_POST['semester'] ?? '');
        $code = trim($_POST['course_code'] ?? '');
        $title = trim($_POST['descriptive_title'] ?? '');
        $units = (int)($_POST['units'] ?? 0);
        $lec = (int)($_POST['lecture_hours'] ?? 0);
        $lab = (int)($_POST['laboratory_hours'] ?? 0);

        // --- STRICT NEGATIVE NUMBER VALIDATION ---
        if ($units < 0 || $lec < 0 || $lab < 0) {
            throw new Exception("Units and hours cannot be negative values.");
        }

        if (empty($code) || empty($title)) {
            throw new Exception("Please fill in the Course Code and Descriptive Title.");
        }

        // C. DUPLICATE CHECK (Strict filter by Program AND Major)
        $checkId = $id ? $id : -1; 
        $checkSql = "SELECT id FROM courses WHERE (course_code = ? OR descriptive_title = ?) AND department = ? AND program = ? AND major = ? AND id != ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("sssssi", $code, $title, $dept, $prog, $major, $checkId);
        $checkStmt->execute();
        $checkStmt->store_result();

        if ($checkStmt->num_rows > 0) {
            $majorText = !empty($major) ? " (Major in " . htmlspecialchars($major) . ")" : "";
            throw new Exception("This Course Code or Title is already registered under " . htmlspecialchars($prog) . $majorText . ".");
        }
        $checkStmt->close();

        // D. UPDATE
        if (!empty($id)) {
            if ($isProgramHead) {
                // Ensure they only update courses that belong to their program
                $sql = "UPDATE courses SET department=?, program=?, major=?, year_level=?, semester=?, course_code=?, descriptive_title=?, units=?, lecture_hours=?, laboratory_hours=? WHERE id=? AND department=? AND program=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssiiiiss", $dept, $prog, $major, $year, $sem, $code, $title, $units, $lec, $lab, $id, $dept, $userProgram);
            } else {
                $sql = "UPDATE courses SET department=?, program=?, major=?, year_level=?, semester=?, course_code=?, descriptive_title=?, units=?, lecture_hours=?, laboratory_hours=? WHERE id=? AND department=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssssiiiis", $dept, $prog, $major, $year, $sem, $code, $title, $units, $lec, $lab, $id, $dept);
            }
            
            if ($stmt->execute()) {
                if($stmt->affected_rows === 0 && $isProgramHead) {
                     // Failsafe in case a program head attempts to update another program's ID via inspection tools
                     throw new Exception("No changes made. You may not have permission to edit this course.");
                }
                // --- LOG ACTIVITY ---
                logActivity($conn, 'Course', 'UPDATE', "Updated course details for: $code - $title");
                $response = ["status" => "success", "message" => "Course details updated successfully."];
            } else {
                throw new Exception("Unable to update course details. " . $stmt->error);
            }
            
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        // E. INSERT (REGISTER)
        $sql = "INSERT INTO courses (department, program, major, year_level, semester, course_code, descriptive_title, units, lecture_hours, laboratory_hours) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssiii", $dept, $prog, $major, $year, $sem, $code, $title, $units, $lec, $lab);
        
        if ($stmt->execute()) {
            // --- LOG ACTIVITY ---
            logActivity($conn, 'Course', 'CREATE', "Registered new course: $code - $title");
            $response = ["status" => "success", "message" => "New course registered successfully."];
        } else {
            throw new Exception("Unable to register the course. " . $stmt->error);
        }
        
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // --- HANDLE GET REQUESTS ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($isProgramHead) {
            $stmt = $conn->prepare("SELECT * FROM courses WHERE department = ? AND program = ? ORDER BY id DESC");
            $stmt->bind_param("ss", $userDept, $userProgram);
        } else {
            $stmt = $conn->prepare("SELECT * FROM courses WHERE department = ? ORDER BY id DESC");
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

} catch (Exception $e) {
    if ($e->getCode() == 1451 || strpos($e->getMessage(), '1451') !== false) {
        $msg = "You cannot delete this Course because it is currently being used in a Class Schedule.";
    } else {
        $msg = $e->getMessage();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(["status" => "error", "message" => $msg]);
}
?>