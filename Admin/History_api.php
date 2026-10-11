<?php
session_start();
include '../db_connect.php';

error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$userDept = $_SESSION['department'] ?? '';
$userPos  = $_SESSION['position'] ?? ''; // Get user position

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $module = isset($_GET['module']) ? trim($_GET['module']) : 'All';
        $action = isset($_GET['action']) ? trim($_GET['action']) : 'All';
        
        // NEW FOR ADMIN: Filter by department if requested (only Admins will send this)
        $filterDept = isset($_GET['department']) ? trim($_GET['department']) : 'All';

        $sql = "SELECT * FROM activity_logs WHERE 1=1";
        $params = [];
        $types = "";

        // Determine Department Filtering based on Role
        if (strtolower($userPos) === 'admin') {
            // If they are an Admin and selected a specific department to filter by
            if ($filterDept !== 'All') {
                $sql .= " AND department = ?";
                $params[] = $filterDept;
                $types .= "s";
            }
        } else {
            // Normal user: Lock them to their own department
            $sql .= " AND department = ?";
            $params[] = $userDept;
            $types .= "s";
        }

        // Apply Module Filter
        if ($module !== 'All') {
            $sql .= " AND module = ?";
            $params[] = $module;
            $types .= "s";
        }

        // Apply Action Filter
        if ($action !== 'All') {
            $sql .= " AND action = ?";
            $params[] = $action;
            $types .= "s";
        }

        // Apply Search Filter
        if (!empty($search)) {
            $sql .= " AND (user_name LIKE ? OR details LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types .= "ss";
        }

        $sql .= " ORDER BY created_at DESC LIMIT 500"; 

        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }

        echo json_encode($logs);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Failed to fetch logs."]);
}
?>