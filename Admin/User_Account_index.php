<?php
session_start();
require '../db_connect.php';

// === FEATURE 3: PHPMailer Manual Requires & Function ===
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// NOTE: Ensure the 'PHPMailer' folder is in the exact same directory as this file!
require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'innerforce001@gmail.com'; 
        $mail->Password   = 'bgxnwgwiefvixkct';    // Spaces removed for successful auth
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('no-reply@cosmos.ursm.edu.ph', 'URSM COSMOS');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
// =======================================================

// === FETCH PROGRAMS FROM DATABASE FOR DROPDOWN ===
$deptPrograms = [];

// 1. Get from Courses Table
$progQuery1 = $conn->query("SELECT DISTINCT department, program FROM courses WHERE program IS NOT NULL AND program != ''");
if ($progQuery1) {
    while ($row = $progQuery1->fetch_assoc()) {
        $d = trim($row['department']);
        $p = trim($row['program']);
        if (!isset($deptPrograms[$d])) $deptPrograms[$d] = [];
        if (!in_array($p, $deptPrograms[$d])) $deptPrograms[$d][] = $p;
    }
}
// 2. Get from Users Table (to catch any programs not yet in courses)
$progQuery2 = $conn->query("SELECT DISTINCT department, program FROM users WHERE program IS NOT NULL AND program != ''");
if ($progQuery2) {
    while ($row = $progQuery2->fetch_assoc()) {
        $d = trim($row['department']);
        $p = trim($row['program']);
        if (!isset($deptPrograms[$d])) $deptPrograms[$d] = [];
        if (!in_array($p, $deptPrograms[$d])) $deptPrograms[$d][] = $p;
    }
}
$deptProgramsJson = json_encode($deptPrograms);
// =======================================================

// === SELF-CONTAINED LOGOUT FUNCTION ===
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array();
    session_destroy();
    header("Location: ../Login/Login_index.php");
    exit();
}
// ======================================

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login_index.php");
    exit();
}

// Fetch the most up-to-date user data directly from the database
$userId = $_SESSION['user_id'];
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

if (!$userData) {
    echo "User not found.";
    exit();
}

// Retrieve user session data with fallbacks
$userPosition = $userData['position'] ?? 'Staff';
$userDept     = $userData['department'] ?? 'Department';
$userProgram  = $userData['program'] ?? '';
$userName     = $_SESSION['full_name'] ?? 'Unknown User';

// Clean up literal "null" strings that may come from DB errors
$cleanName = str_ireplace(['null', 'NULL'], '', $userName);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
if (empty($cleanName) || $cleanName === '.') {
    $cleanName = 'Unknown User';
}

// Generate initials from cleaned name
$nameParts = explode(' ', $cleanName);
$initials = 'U';
if ($cleanName !== 'Unknown User') {
    if (count($nameParts) > 1) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
    } else {
        $initials = strtoupper(substr($nameParts[0], 0, 1));
    }
} else {
    $initials = 'UU'; 
}

// Handle Profile Picture
$picSrc = null;
if (!empty($userData['profile_picture'])) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userData['profile_picture']);
    $_SESSION['profile_picture'] = $picSrc;
}

// Security Flags
$isProgramHead = (stripos($userPosition, 'Program Head') !== false || stripos($userPosition, 'Coordinator') !== false);
$isAdmin = (stripos($userPosition, 'Admin') !== false);
$isDean = (stripos($userPosition, 'Dean') !== false);
$isLeadership  = ($isProgramHead || $isAdmin || $isDean);

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'General Education' || stripos($userPosition, 'Gen') !== false || (stripos($userPosition, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));
$targetDept = $isGenEd ? 'General Education' : $userDept;

$isFaculty = ($userPosition === 'Faculty');

$message = '';

// === GRAB REDIRECT MESSAGES & PRESERVED FORM DATA ===
if (isset($_SESSION['action_message'])) {
    $message = $_SESSION['action_message'];
    unset($_SESSION['action_message']); // Clear it so it only shows once
}

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']); // Clear it so it doesn't persist forever
// ====================================================

// ==========================================
// Handle Account Creation
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_account'])) {
    $username = trim($_POST['username']);
    $raw_password = $_POST['password']; // Capture raw password for the email
    $password = password_hash($raw_password, PASSWORD_DEFAULT);
    $email = trim($_POST['email']);
    $surname = trim($_POST['surname']);
    $first_name = trim($_POST['first_name']);
    $middle_initial = trim($_POST['middle_initial']);
    $sex = $_POST['sex'];
    $employee_id = trim($_POST['employee_id']);
    $department = trim($_POST['department']);
    $position = trim($_POST['position']);
    $program = trim($_POST['program']);

    $profile_picture = null;
    if (!empty($_POST['profile_picture_base64'])) {
        $image_parts = explode(";base64,", $_POST['profile_picture_base64']);
        if (count($image_parts) == 2) {
            $profile_picture = base64_decode($image_parts[1]); 
        }
    }

    $stmt = $conn->prepare("INSERT INTO users (username, password, email, surname, first_name, middle_initial, sex, employee_id, department, position, program, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssss", $username, $password, $email, $surname, $first_name, $middle_initial, $sex, $employee_id, $department, $position, $program, $profile_picture);

    try {
        if ($stmt->execute()) {
            
            // === AUTOMATIC EMAIL SENDING USING YOUR FUNCTION ===
            $subject = 'Your New COSMOS Account Credentials';
            $body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #1b5088;'>Welcome to COSMOS!</h2>
                    <p>Hello <b>{$first_name} {$surname}</b>,</p>
                    <p>Your account has been successfully created. Below are your login credentials:</p>
                    <div style='background: #f4f7fb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><b>Username:</b> {$username}</p>
                        <p style='margin: 5px 0;'><b>Password:</b> {$raw_password}</p>
                        <p style='margin: 5px 0;'><b>Department:</b> {$department}</p>
                        <p style='margin: 5px 0;'><b>Position:</b> {$position}</p>
                    </div>
                    <p>For your security, please log in and change your password immediately.</p>
                    <br>
                    <p>Best Regards,<br><b>The System Administrator</b></p>
                </div>
            ";

            $mailSuccess = sendEmail($email, $subject, $body);

            if($mailSuccess){
                $_SESSION['action_message'] = "success|Account registered and credentials emailed successfully!";
            } else {
                $_SESSION['action_message'] = "success|Account registered, but failed to send the email notification.";
            }
            // ===================================================

        }
    } catch (mysqli_sql_exception $e) {
        // === SAVE FORM DATA SO IT ISN'T LOST ===
        $_SESSION['form_data'] = $_POST;

        if ($e->getCode() == 1062) {
            $_SESSION['action_message'] = "error|The Username, Email or Employee ID is already taken.";
        } else {
            $_SESSION['action_message'] = "error|Could not register account.";
        }
    }
    $stmt->close();
    
    // REDIRECT TO CLEAR POST DATA
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// Handle Account Update (Edit)
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_account'])) {
    $edit_user_id = $_POST['edit_user_id'];
    
    // Capture ALL editable fields
    $edit_username = trim($_POST['edit_username']);
    $edit_email = trim($_POST['edit_email']);
    $edit_first_name = trim($_POST['edit_first_name']);
    $edit_middle_initial = trim($_POST['edit_middle_initial']);
    $edit_surname = trim($_POST['edit_surname']);
    $edit_sex = trim($_POST['edit_sex']);
    $edit_employee_id = trim($_POST['edit_employee_id']);
    
    $edit_department = trim($_POST['edit_department']);
    $edit_program = trim($_POST['edit_program']);
    $edit_position = trim($_POST['edit_position']);

    // Update everything EXCEPT the password and profile picture
    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, first_name = ?, middle_initial = ?, surname = ?, sex = ?, employee_id = ?, department = ?, program = ?, position = ? WHERE id = ?");
    $stmt->bind_param("ssssssssssi", $edit_username, $edit_email, $edit_first_name, $edit_middle_initial, $edit_surname, $edit_sex, $edit_employee_id, $edit_department, $edit_program, $edit_position, $edit_user_id);

    try {
        if ($stmt->execute()) {
            $_SESSION['action_message'] = "success|Account updated successfully!";
        }
    } catch (mysqli_sql_exception $e) { 
        if ($e->getCode() == 1062) {
            $_SESSION['action_message'] = "error|The Username, Email or Employee ID is already taken by another user.";
        } else {
            $_SESSION['action_message'] = "error|Could not update account.";
        }
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ==========================================
// Handle Account Deletion
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_account'])) {
    $delete_user_id = $_POST['delete_user_id'];
    
    if ($delete_user_id == $_SESSION['user_id']) {
        $_SESSION['action_message'] = "error|You cannot delete your own active account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $delete_user_id);

        try {
            if ($stmt->execute()) {
                $_SESSION['action_message'] = "success|User account permanently deleted.";
            }
        } catch (mysqli_sql_exception $e) {
            $_SESSION['action_message'] = "error|Cannot delete this user because they are already assigned to class schedules in the system.";
        }
        $stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
// ==========================================

// Fetch all users grouped by Department
$usersByDepartment = [];
$result = $conn->query("SELECT * FROM users WHERE department != 'System' ORDER BY department ASC, surname ASC");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $usersByDepartment[$row['department']][] = $row;
    }
}

// Color mapping for departments
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
    'MIS'                              => '#6366f1'
];
$themeColor = $departmentColors[$targetDept] ?? 'var(--primary-blue)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ── Google Font ─────────────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');

        /* ── CSS Custom Properties ───────────────────────────── */
        :root {
            --primary-blue:       #1b5088; 
            --primary-blue-hover: #143d69;
            --accent-blue:        #eff6ff; 
            --bg-main:            #f4f7fb; 
            --bg-card:            #ffffff; 
            --text-dark:          #0f172a; 
            --text-muted:         #64748b; 
            --border-color:       #e2e8f0;
            --shadow-card:        0 4px 20px rgba(0, 0, 0, 0.03); 
            --radius-card:        24px;
            --radius-pill:        99px; 
            --radius-input:       12px;
            --sidebar-min:        80px; 
            --sidebar-max:        260px;
            --theme-color:        <?php echo $themeColor; ?>;
        }

        /* ── Reset ───────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: var(--bg-main); color: var(--text-dark); overflow: hidden; }

        /* ════════════════════════════════════════════════════════
            SIDEBAR — Master Template
        ════════════════════════════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-min); background: var(--bg-card); border-right: 1px solid var(--border-color);
            display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden; z-index: 1000;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar:hover { width: var(--sidebar-max); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08); }

         /* Logo */
        .logo-section {
            padding: 20px 10px;
            text-align: center;
            flex-shrink: 0;
        }

        .logo-section img {
            width: 50px;
            border-radius: 12px;
            display: block;
            margin: 0 auto;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar:hover .logo-section img {
            width: 140px;
        }


        .menu-label {
            font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; 
            margin: 15px 0 5px; padding: 0 20px; white-space: nowrap; overflow: hidden; opacity: 0; max-height: 0;
            transition: opacity 0.2s ease, max-height 0.2s ease, padding 0.3s ease;
        }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; padding: 0 24px; }

        .nav-menu { list-style: none; padding: 0; margin: 0; } 
        .nav-menu li a {
            display: flex; align-items: center; justify-content: center; padding: 12px; margin: 4px 12px;
            color: var(--text-muted); text-decoration: none; font-size: 14.5px; font-weight: 600;
            border-radius: 12px; white-space: nowrap; overflow: hidden; transition: all 0.2s ease;
        }
        .sidebar:hover .nav-menu li a { padding: 12px 16px; margin: 4px 16px; justify-content: flex-start; }
        
        .nav-menu li a:hover  { background: #f8fafc; color: var(--primary-blue); }
        .nav-menu li a.active { background: var(--accent-blue); color: var(--primary-blue); }
        
        .nav-menu i { font-size: 18px; width: 24px; flex-shrink: 0; text-align: center; margin-right: 0; transition: margin-right 0.3s ease; }
        .sidebar:hover .nav-menu i { margin-right: 14px; }
        
        .nav-menu li a span { display: inline-block; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .nav-menu li a span { width: auto; opacity: 1; }

       /* ── Profile section (bottom of sidebar) ─────────────── */
        .profile-section {
            margin-top: auto;
            position: relative;
            padding: 36px 10px 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* Department logo bubble — always visible */
        .dept-logo-bubble {
            position: absolute;
            top: -26px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12), 0 0 0 3px white;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            z-index: 10;
            transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease;
        }

        /* Speech-bubble tail */
        .dept-logo-bubble::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 9px solid white;
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.08));
        }

        .sidebar:hover .dept-logo-bubble {
            width: 56px;
            height: 56px;
            top: -30px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15), 0 0 0 3px white;
        }

        .dept-logo-bubble img {
            width: 82%;
            height: 82%;
            object-fit: contain;
        }

        .dept-icon-fallback {
            font-size: 20px;
            color: var(--primary-blue);
        }

        /* Profile row */
        .profile-row {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            padding: 0 2px;
        }

        .profile-img {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: var(--primary-blue);
            color: white;
            font-size: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
            min-width: 0;
            width: 0;
            opacity: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 0.2s ease 0.05s, width 0.3s ease;
        }

        .sidebar:hover .profile-info {
            width: auto;
            opacity: 1;
        }

        .profile-info h4 {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .profile-info p {
            font-size: 11px;
            color: var(--text-muted);
            margin: 2px 0 0;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            flex-shrink: 0;
            width: 0;
            opacity: 0;
            overflow: hidden;
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 16px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .sidebar:hover .logout-btn {
            width: auto;
            opacity: 1;
        }

        .logout-btn:hover {
            color: #ef4444;
            background: #fef2f2;
        }


        /* ════════════════════════════════════════════════════════
            MAIN CONTENT
        ════════════════════════════════════════════════════════ */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        
        .container { padding: 20px 40px 40px 40px; overflow-y: auto; flex: 1; }
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; text-transform: uppercase; margin-bottom: 10px; }

        /* ===== FORMS ===== */
        .card-container { background: var(--bg-card); border-radius: var(--radius-card); padding: 30px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); margin-bottom: 20px;}
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px;}
        
        .input-group { display: flex; flex-direction: column; gap: 8px; text-align: left; }
        .input-group label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .custom-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-input); font-size: 14px; font-weight: 500; background: #f8fafc; color: var(--text-dark); transition: all 0.2s ease; outline: none; }
        .custom-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        select.custom-input { appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; }
        
        /* PASSWORD TOGGLE STYLES */
        .password-container { position: relative; width: 100%; }
        .password-container .custom-input { padding-right: 45px; } 
        .toggle-password {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: var(--text-muted); font-size: 16px; transition: color 0.2s ease;
        }
        .toggle-password:hover { color: var(--primary-blue); }

        .btn-register { background: var(--primary-blue); color: white; border: none; padding: 14px 35px; border-radius: var(--radius-pill); font-weight: 700; font-size: 14px; cursor: pointer; transition: all 0.2s ease; letter-spacing: 0.5px; float: right; display: inline-flex; align-items: center; justify-content: center;}
        .btn-register:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2); }

        /* ===== CUSTOM DROPDOWN ===== */
        .custom-dropdown-wrapper { position: relative; width: 100%; }
        .custom-dropdown-list {
            display: none; position: absolute; z-index: 1000; background: white;
            width: 100%; border: 1px solid var(--border-color); border-radius: 12px;
            max-height: 220px; overflow-y: auto; padding: 8px; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            top: calc(100% + 5px); left: 0;
        }
        .opt-item {
            display: block; padding: 10px 12px; font-size: 13px; font-weight: 500;
            color: var(--text-dark); cursor: pointer; border-radius: 8px; text-align: left;
            transition: all 0.2s ease;
        }
        .opt-item:hover { background: var(--accent-blue); color: var(--primary-blue); }

        /* ===== SEARCH & FILTER ===== */
        .search-filter-wrapper { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .search-box { flex: 1; position: relative; min-width: 250px; }
        .search-box input { width: 100%; padding: 12px 16px 12px 45px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 13px; font-weight: 500; background: var(--bg-card); color: var(--text-dark); outline: none; transition: 0.2s; box-shadow: var(--shadow-card);}
        .search-box input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .search-box i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; }

        .filter-select { padding: 12px 20px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 13px; font-weight: 600; background: var(--bg-card); color: var(--text-dark); cursor: pointer; outline: none; transition: 0.2s; appearance: none; padding-right: 35px; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; box-shadow: var(--shadow-card);}
        .filter-select:focus, .filter-select:hover { border-color: var(--primary-blue); }

        /* ===== FACULTY GRID ===== */
        .department-section { margin-bottom: 40px; }
        .section-header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid var(--border-color); }
        .section-header h2 { font-size: 18px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.5px;}
        
        .faculty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        
        .user-card { background: var(--bg-card); border-radius: 16px; padding: 25px 20px; text-align: center; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); transition: all 0.2s ease; border-top: 6px solid var(--primary-blue); cursor: pointer; position: relative; }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08); }
        .user-card:hover::after { content: '\f044 Edit'; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; top: 10px; right: 10px; font-size: 11px; background: rgba(0,0,0,0.05); padding: 4px 8px; border-radius: 4px; color: var(--primary-blue); }
        
        .card-avatar { width: 70px; height: 70px; border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 800; overflow: hidden; background: var(--primary-blue); box-shadow: 0 4px 10px rgba(0,0,0,0.1);}
        .card-role { font-weight: 800; color: var(--primary-blue); font-size: 11px; text-transform: uppercase; margin-bottom: 5px; letter-spacing: 0.5px;}
        .card-name { font-weight: 800; color: var(--text-dark); font-size: 15px; margin-bottom: 10px; line-height: 1.2;}
        
        .card-detail { font-size: 12px; color: var(--text-muted); margin-bottom: 4px; display: flex; justify-content: space-between; border-bottom: 1px dashed var(--border-color); padding-bottom: 4px;}
        .card-detail span { font-weight: 700; color: var(--text-dark); text-align: right; }

        .d-none { display: none !important; }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; background: var(--bg-card); border-radius: var(--radius-card); border: 1px dashed var(--border-color); }
        .empty-state i { font-size: 50px; color: #cbd5e1; margin-bottom: 15px; display: block; }
        .empty-state p { font-size: 18px; color: var(--text-dark); }
        .empty-state small { font-size: 13px; font-weight: normal; margin-top: 8px; display: block; }

        /* ===== MODAL STYLES ===== */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); padding: 20px; backdrop-filter: blur(4px); overflow-y: auto; }
        .modal-content { background: white; margin: 5% auto; padding: 35px 40px; border-radius: 24px; max-width: 650px; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; }
        .close-btn { position: absolute; top: 20px; right: 25px; color: var(--text-muted); font-size: 24px; font-weight: bold; cursor: pointer; transition: 0.2s; }
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content-alert { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; }

        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; transform: translateX(-100%); z-index: 1000; }
            body.mobile-nav-active .sidebar { transform: translateX(0); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
            body.mobile-nav-active .sidebar .nav-menu li a              { justify-content: flex-start; padding: 12px 16px; margin: 4px 16px; }
            body.mobile-nav-active .sidebar .nav-menu i                 { margin-right: 14px; }
            body.mobile-nav-active .sidebar .nav-menu li a span         { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .menu-label                 { opacity: 1; max-height: 30px; padding: 0 24px; }
            body.mobile-nav-active .sidebar .profile-info               { width: auto; opacity: 1; margin-left: 12px; }
            body.mobile-nav-active .sidebar .logout-btn                 { width: auto; opacity: 1; margin-left: auto; }
            body.mobile-nav-active .sidebar .logo-section img           { width: 100px; height: 100px; }
            
            .backdrop { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
            body.mobile-nav-active .backdrop { opacity: 1; pointer-events: auto; }

            header { padding: 20px; }
            .container { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="backdrop"></div>
    
    <aside class="sidebar">
        <div class="logo-section">
            <img src="../logo.png" alt="URS Logo" onerror="this.src='logo.png'">
        </div>
       <div class="menu-label">Menu</div>
        <ul class="nav-menu">
            <li><a href="Dashboard_index.php" ><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i> <span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i><span>Class Monitoring</span></a></li>
        </ul>

            <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="User_Account_index.php" class="active"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
            <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Room_index.php"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
            <li><a href="History_index.php"><i class="fas fa-history"></i> <span>Global History</span></a></li>
        </ul>
        <div style="margin-top: auto;">
            <div class="profile-section">
                <div class="dept-logo-bubble" title="System Administrator">
                    <span class="dept-icon-fallback"><i class="fas fa-shield-alt"></i></span>
                </div>
                <div class="profile-row">
                <a href="Profile_index.php"
                   title="Go to My Profile"
                   style="display:flex; align-items:center; gap:10px; text-decoration:none; flex:1; min-width:0; transition:opacity 0.2s;"
                   onmouseover="this.style.opacity='0.7'"
                   onmouseout="this.style.opacity='1'">
                        <?php if ($picSrc): ?>
                            <img src="<?php echo $picSrc; ?>" class="profile-img" alt="Profile Picture" style="border: 2px solid rgba(255,255,255,0.2);">
                        <?php else: ?>
                            <div class="profile-img" style="border: 2px solid rgba(255,255,255,0.2);"><?php echo htmlspecialchars($initials); ?></div>
                        <?php endif; ?>
                        <div class="profile-info">
                            <h4><?php echo htmlspecialchars($cleanName); ?></h4>
                            <p><?php echo htmlspecialchars($userPosition); ?></p>
                        </div>
                    </a>
                    <a href="?logout=true" class="logout-btn" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </aside>

    <div class="main-content">
        <header>
            <div class="header-left">
                <i class="fas fa-bars" id="menu-toggle"></i>
                <div class="header-title">
                     <h1>University of Rizal System</h1>
                    <p>Classroom Organization, Scheduling & Monitoring Operations System</p>
              </div>
            </div>
        </header>

        <div class="container">

            <div class="page-title"><i class="fas fa-users-cog" style="color: var(--primary-blue); margin-right: 8px;"></i> Account Management</div>

            <div class="card-container">
                <form method="POST" action="" enctype="multipart/form-data" id="createAccountForm">
                    <div class="form-grid">
                        <div class="input-group">
                            <label>Username</label>
                            <input type="text" name="username" class="custom-input" value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Password</label>
                            <div class="password-container">
                                <input type="password" id="reg_password" name="password" class="custom-input" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('reg_password', this)"></i>
                            </div>
                        </div>

                        <div class="input-group">
                            <label>Email Address</label>
                            <input type="email" name="email" class="custom-input" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <label>Surname</label>
                            <input type="text" name="surname" class="custom-input" value="<?php echo htmlspecialchars($formData['surname'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <label>First Name</label>
                            <input type="text" name="first_name" class="custom-input" value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="input-group">
                            <label>Middle Initial</label>
                            <input type="text" name="middle_initial" class="custom-input" maxlength="5" value="<?php echo htmlspecialchars($formData['middle_initial'] ?? ''); ?>">
                        </div>
                        <div class="input-group">
                            <label>Sex</label>
                            <?php $selectedSex = $formData['sex'] ?? ''; ?>
                            <select name="sex" class="custom-input filter-select" style="padding-right: 35px;" required>
                                <option value="" disabled <?php echo empty($selectedSex) ? 'selected' : ''; ?>>Select Sex</option>
                                <option value="Male" <?php echo $selectedSex === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $selectedSex === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Employee ID No.</label>
                            <input type="text" name="employee_id" class="custom-input" value="<?php echo htmlspecialchars($formData['employee_id'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="input-group">
                            <label>Department</label>
                            <?php $selectedDept = $formData['department'] ?? ''; ?>
                            <select name="department" id="create_department" class="custom-input filter-select" style="padding-right: 35px;" required>
                                <option value="" disabled <?php echo empty($selectedDept) ? 'selected' : ''; ?>>Select Department</option>
                                <option value="College of Science" <?php echo $selectedDept === 'College of Science' ? 'selected' : ''; ?>>College of Science</option>
                                <option value="College of Engineering" <?php echo $selectedDept === 'College of Engineering' ? 'selected' : ''; ?>>College of Engineering</option>
                                <option value="College of Industrial Technology" <?php echo $selectedDept === 'College of Industrial Technology' ? 'selected' : ''; ?>>College of Industrial Technology</option>
                                <option value="College of Education" <?php echo $selectedDept === 'College of Education' ? 'selected' : ''; ?>>College of Education</option>
                                <option value="MIS" <?php echo $selectedDept === 'MIS' ? 'selected' : ''; ?>>MIS</option> 
                            </select>
                        </div>
                        
                        <div class="input-group">
                            <label>Program</label>
                            <div class="custom-dropdown-wrapper">
                                <input type="text" name="program" id="create_program" class="custom-input" placeholder="Select or type program..." value="<?php echo htmlspecialchars($formData['program'] ?? ''); ?>" required autocomplete="off" onclick="toggleProgDrop('create')" onkeyup="filterProgDrop('create')">
                                <div id="drop_create_program" class="custom-dropdown-list"></div>
                            </div>
                        </div>
                        
                        <div class="input-group">
                            <label>Position</label>
                            <?php $selectedPos = $formData['position'] ?? ''; ?>
                            <select name="position" id="create_position" class="custom-input filter-select" style="padding-right: 35px;" required>
                                <option value="" disabled <?php echo empty($selectedPos) ? 'selected' : ''; ?>>Select Position</option>
                                <option value="Admin" <?php echo $selectedPos === 'Admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="Dean" <?php echo $selectedPos === 'Dean' ? 'selected' : ''; ?>>Dean</option>
                                <option value="Dean Secretary" <?php echo $selectedPos === 'Dean Secretary' ? 'selected' : ''; ?>>Dean Secretary</option>
                                <option value="Program Head" <?php echo $selectedPos === 'Program Head' ? 'selected' : ''; ?>>Program Head</option>
                                <option value="Faculty" <?php echo $selectedPos === 'Faculty' ? 'selected' : ''; ?>>Faculty</option>
                                <option value="General Education Coordinator" <?php echo $selectedPos === 'General Education Coordinator' ? 'selected' : ''; ?>>General Education Coordinator</option>
                            </select>
                        </div>

                        <div class="input-group">
                            <label>Profile Picture (Optional)</label>
                            <input type="file" id="pic_upload" accept="image/*" class="custom-input" style="padding: 9px 16px;">
                            <input type="hidden" name="profile_picture_base64" id="pic_base64">
                        </div>
                    </div>
                    <div style="clear:both; overflow:hidden;">
                        <input type="hidden" name="create_account" value="1">
                        <button type="button" class="btn-register" onclick="handleCreateSubmit()"><i class="fas fa-user-plus" style="margin-right: 8px;"></i> CREATE ACCOUNT</button>
                    </div>
                </form>
            </div>

            <div class="search-filter-wrapper">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search accounts by name, ID, or program...">
                </div>
                <select id="departmentFilter" class="filter-select">
                    <option value="All">All Departments</option>
                    <option value="College of Science">College of Science</option>
                    <option value="College of Engineering">College of Engineering</option>
                    <option value="College of Industrial Technology">College of Industrial Technology</option>
                    <option value="College of Education">College of Education</option>
                    <option value="MIS">MIS</option> 
                </select>
            </div>

            <div id="accountsContainer">
                <?php
                if (!empty($usersByDepartment)) {
                    foreach ($usersByDepartment as $departmentName => $users) {
                        $themeColor = isset($departmentColors[$departmentName]) ? $departmentColors[$departmentName] : 'var(--primary-blue)';

                        echo '<div class="department-section" data-department-group="' . htmlspecialchars($departmentName) . '">';
                        echo '  <div class="section-header" style="border-bottom-color: ' . $themeColor . ';">';
                        echo '      <h2 style="color: ' . $themeColor . ';">' . htmlspecialchars($departmentName) . '</h2>';
                        echo '  </div>';
                        
                        echo '  <div class="faculty-grid">';
                        foreach ($users as $u) {
                            $fullName = $u['first_name'] . ' ' . $u['surname'];
                            $initials = strtoupper(substr($u['first_name'], 0, 1) . substr($u['surname'], 0, 1));
                            
                            if (!empty($u['profile_picture'])) {
                                $base64 = base64_encode($u['profile_picture']);
                                $avatarHtml = '<img src="data:image/jpeg;base64,' . $base64 . '" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
                            } else {
                                $avatarHtml = $initials;
                            }
                            
                            echo '      <div class="user-card account-card" style="border-top-color: ' . $themeColor . ';" 
                                          data-userid="' . htmlspecialchars($u['id']) . '"
                                          data-username="' . htmlspecialchars($u['username']) . '"
                                          data-department="' . htmlspecialchars($u['department']) . '" 
                                          data-rawprog="' . htmlspecialchars($u['program']) . '"
                                          data-rawpos="' . htmlspecialchars($u['position']) . '"
                                          data-name="' . strtolower(htmlspecialchars($fullName)) . '" 
                                          data-firstname="' . htmlspecialchars($u['first_name']) . '" 
                                          data-surname="' . htmlspecialchars($u['surname']) . '" 
                                          data-mi="' . htmlspecialchars($u['middle_initial']) . '" 
                                          data-email="' . htmlspecialchars($u['email']) . '" 
                                          data-sex="' . htmlspecialchars($u['sex']) . '" 
                                          data-prog="' . strtolower(htmlspecialchars($u['program'])) . '" 
                                          data-empid="' . strtolower(htmlspecialchars($u['employee_id'])) . '"
                                          onclick="openEditModal(this)">';
                            
                            echo '          <div class="card-avatar" style="background-color: ' . $themeColor . ';">' . $avatarHtml . '</div>';
                            echo '          <div class="card-role">' . htmlspecialchars($u['position']) . '</div>';
                            echo '          <div class="card-name">' . htmlspecialchars($fullName) . '</div>';
                            
                            echo '          <div class="card-detail">Program: <span>' . htmlspecialchars($u['program']) . '</span></div>';
                            echo '          <div class="card-detail" style="border:none; padding-bottom:0;">Emp ID: <span>' . htmlspecialchars($u['employee_id']) . '</span></div>';
                            echo '      </div>';
                        }
                        echo '  </div>';
                        echo '</div>';
                    }
                } else {
                    echo "<div class='empty-state'><i class='fas fa-users-slash'></i><p>No user accounts found in the system.</p></div>";
                }
                ?>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <span class="close-btn" onclick="closeEditModal()">&times;</span>
            <h3 style="color: var(--primary-blue); font-weight: 800; font-size: 20px; margin-bottom: 20px; text-align: center;"><i class="fas fa-user-edit"></i> Edit User Account</h3>
            
            <form method="POST" action="" id="deleteAccountForm" style="display:none;">
                <input type="hidden" name="delete_account" value="1">
                <input type="hidden" name="delete_user_id" id="delete_user_id">
            </form>

            <form method="POST" action="" id="editAccountForm">
                <input type="hidden" name="edit_user_id" id="edit_user_id">
                <input type="hidden" name="update_account" value="1">
                
                <div class="form-grid">
                    <div class="input-group">
                        <label>Username</label>
                        <input type="text" name="edit_username" id="edit_username" class="custom-input" required>
                    </div>

                    <div class="input-group">
                        <label>Email Address</label>
                        <input type="email" name="edit_email" id="edit_email" class="custom-input" required>
                    </div>

                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="edit_first_name" id="edit_first_name" class="custom-input" required>
                    </div>

                    <div class="input-group">
                        <label>Middle Initial</label>
                        <input type="text" name="edit_middle_initial" id="edit_middle_initial" class="custom-input" maxlength="5">
                    </div>

                    <div class="input-group">
                        <label>Surname</label>
                        <input type="text" name="edit_surname" id="edit_surname" class="custom-input" required>
                    </div>

                    <div class="input-group">
                        <label>Sex</label>
                        <select name="edit_sex" id="edit_sex" class="custom-input filter-select" style="padding-right: 35px;" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>

                    <div class="input-group">
                        <label>Employee ID No.</label>
                        <input type="text" name="edit_employee_id" id="edit_employee_id" class="custom-input" required>
                    </div>

                    <div class="input-group">
                        <label>Department</label>
                        <select name="edit_department" id="edit_department" class="custom-input filter-select" style="padding-right: 35px;" required>
                            <option value="College of Science">College of Science</option>
                            <option value="College of Engineering">College of Engineering</option>
                            <option value="College of Industrial Technology">College of Industrial Technology</option>
                            <option value="College of Education">College of Education</option>
                            <option value="MIS">MIS</option> 
                        </select>
                    </div>
                    
                    <div class="input-group">
                        <label>Program</label>
                        <div class="custom-dropdown-wrapper">
                            <input type="text" name="edit_program" id="edit_program" class="custom-input" placeholder="Select or type program..." required autocomplete="off" onclick="toggleProgDrop('edit')" onkeyup="filterProgDrop('edit')">
                            <div id="drop_edit_program" class="custom-dropdown-list"></div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Position</label>
                        <select name="edit_position" id="edit_position" class="custom-input filter-select" style="padding-right: 35px;" required>
                            <option value="Admin">Admin</option>
                            <option value="Dean">Dean</option>
                            <option value="Dean Secretary">Dean Secretary</option>
                            <option value="Program Head">Program Head</option>
                            <option value="Faculty">Faculty</option>
                            <option value="General Education Coordinator">General Education Coordinator</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 10px;">
                    <button type="button" class="btn-register" style="flex: 1; background: #ef4444; float: none;" onclick="handleDeleteSubmit()"><i class="fas fa-trash-alt"></i> DELETE</button>
                    <button type="button" class="btn-register" style="flex: 2; float: none;" onclick="handleEditSubmit()"><i class="fas fa-save"></i> SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content-alert">
            <i id="sysModalAlertIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <button id="sysModalAlertBtn" class="btn-modal" style="width:100%; background: var(--primary-blue);">OK</button>
        </div>
    </div>

    <div id="sysModalConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content-alert">
            <i id="sysModalConfirmIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalConfirmMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <div class="sys-modal-btns">
                <button id="sysModalConfirmCancelBtn" class="btn-modal" style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalConfirmOkBtn" class="btn-modal" style="flex:1; background: var(--primary-blue);">Confirm</button>
            </div>
        </div>
    </div>

    <div id="sysModalPrompt" class="sys-modal-backdrop">
        <div class="sys-modal-content-alert">
            <i class="fas fa-exclamation-triangle sys-modal-icon" style="color: #ef4444; font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalPromptMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 15px;"></div>
            <input type="text" id="sysModalPromptInput" class="custom-input" style="text-align: center; margin-bottom: 25px; font-weight: 800; font-size: 16px; border-color: #ef4444;" placeholder="Type DELETE here" autocomplete="off">
            <div class="sys-modal-btns">
                <button id="sysModalPromptCancelBtn" class="btn-modal" style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalPromptOkBtn" class="btn-modal" style="flex:1; background: #ef4444;">Confirm</button>
            </div>
        </div>
    </div>

    <div id="sysLoadingOverlay" class="sys-modal-backdrop" style="z-index: 9999; flex-direction: column;">
        <i class="fas fa-circle-notch fa-spin" style="font-size: 55px; color: var(--primary-blue); margin-bottom: 20px;"></i>
        <h3 style="color: white; font-weight: 800; font-size: 22px;">Processing...</h3>
        <p style="color: #cbd5e1; font-size: 14px; margin-top: 8px;">Please wait while the system processes your request.</p>
    </div>

    <script>
        // =========================================================
        // Promise-Based System Modals
        // =========================================================
        function sysAlert(msg, icon = 'fa-info-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysModalAlertMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalAlertIcon');
                iconEl.className = `fas ${icon}`;
                iconEl.style.color = color;
                
                const okBtn = document.getElementById('sysModalAlertBtn');
                okBtn.style.backgroundColor = color;

                document.getElementById('sysModalAlert').style.display = 'flex';
                document.getElementById('sysModalAlertBtn').onclick = () => {
                    document.getElementById('sysModalAlert').style.display = 'none';
                    resolve(true);
                };
            });
        }

        function sysConfirm(msg, icon = 'fa-question-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysModalConfirmMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalConfirmIcon');
                iconEl.className = `fas ${icon}`;
                iconEl.style.color = color;
                
                const okBtn = document.getElementById('sysModalConfirmOkBtn');
                okBtn.style.backgroundColor = color;

                document.getElementById('sysModalConfirm').style.display = 'flex';
                
                document.getElementById('sysModalConfirmCancelBtn').onclick = () => {
                    document.getElementById('sysModalConfirm').style.display = 'none';
                    resolve(false);
                };
                
                okBtn.onclick = () => {
                    document.getElementById('sysModalConfirm').style.display = 'none';
                    resolve(true);
                };
            });
        }

        function sysPrompt(msg) {
            return new Promise(resolve => {
                document.getElementById('sysModalPromptMsg').innerHTML = msg;
                const inputEl = document.getElementById('sysModalPromptInput');
                inputEl.value = ''; // Reset input
                
                document.getElementById('sysModalPrompt').style.display = 'flex';
                inputEl.focus();
                
                document.getElementById('sysModalPromptCancelBtn').onclick = () => {
                    document.getElementById('sysModalPrompt').style.display = 'none';
                    resolve(null);
                };
                
                document.getElementById('sysModalPromptOkBtn').onclick = () => {
                    document.getElementById('sysModalPrompt').style.display = 'none';
                    resolve(inputEl.value);
                };
            });
        }

        function showLoading() {
            document.getElementById('sysLoadingOverlay').style.display = 'flex';
        }

        // =========================================================
        // PROGRAM DROPDOWN LOGIC
        // =========================================================
        const deptPrograms = <?php echo $deptProgramsJson; ?>;

        function populatePrograms(type) {
            const deptId = type === 'create' ? 'create_department' : 'edit_department';
            const dropId = type === 'create' ? 'drop_create_program' : 'drop_edit_program';
            
            const dept = document.getElementById(deptId).value;
            const list = document.getElementById(dropId);
            const input = document.getElementById(type === 'create' ? 'create_program' : 'edit_program');
            
            if (input.readOnly) return; // Don't show dropdown if locked by MIS

            let programs = [];
            if (dept && deptPrograms[dept]) {
                programs = [...deptPrograms[dept]];
            }
            
            // ALWAYS ADD GENERAL EDUCATION
            if (!programs.includes('General Education')) {
                programs.push('General Education');
            }
            
            list.innerHTML = programs.map(p => 
                `<div class="opt-item" onclick="selectProgram('${type}', '${p.replace(/'/g, "\\'")}')">${p}</div>`
            ).join('');
        }

        function toggleProgDrop(type) {
            const input = document.getElementById(type === 'create' ? 'create_program' : 'edit_program');
            if (input.readOnly) return;
            
            populatePrograms(type);
            const list = document.getElementById(type === 'create' ? 'drop_create_program' : 'drop_edit_program');
            list.style.display = list.style.display === 'block' ? 'none' : 'block';
        }

        function filterProgDrop(type) {
            const input = document.getElementById(type === 'create' ? 'create_program' : 'edit_program');
            if (input.readOnly) return;

            const list = document.getElementById(type === 'create' ? 'drop_create_program' : 'drop_edit_program');
            list.style.display = 'block';
            
            const filter = input.value.toLowerCase();
            list.querySelectorAll('.opt-item').forEach(item => {
                const txt = item.innerText.toLowerCase();
                item.style.display = txt.includes(filter) ? 'block' : 'none';
            });
        }

        function selectProgram(type, val) {
            const input = document.getElementById(type === 'create' ? 'create_program' : 'edit_program');
            const list = document.getElementById(type === 'create' ? 'drop_create_program' : 'drop_edit_program');
            input.value = val;
            list.style.display = 'none';
        }

        // Hide dropdowns on outside click
        document.addEventListener('click', e => {
            if (!e.target.closest('.custom-dropdown-wrapper')) {
                document.querySelectorAll('.custom-dropdown-list').forEach(el => el.style.display = 'none');
            }
        });

        // Trigger updates when department changes
        document.getElementById('create_department').addEventListener('change', () => {
            document.getElementById('create_program').value = '';
            populatePrograms('create');
        });

        document.getElementById('edit_department').addEventListener('change', (e) => {
            // Only clear the input if it was manually triggered by the user changing the dropdown
            if(e.isTrusted) {
                document.getElementById('edit_program').value = '';
            }
            populatePrograms('edit');
        });
        // =========================================================

        // Display PHP Session Messages via sysAlert
        document.addEventListener('DOMContentLoaded', async () => {
            const serverMsg = "<?php echo addslashes($message); ?>";
            if (serverMsg) {
                const parts = serverMsg.split('|');
                if (parts.length === 2) {
                    if (parts[0] === 'success') {
                        await sysAlert(parts[1], "fa-check-circle", "#10b981");
                    } else {
                        await sysAlert(parts[1], "fa-exclamation-triangle", "#ef4444");
                    }
                }
            }
            
            // Re-apply MIS lock if the form reloaded with preserved MIS data
            document.getElementById('create_department').dispatchEvent(new Event('change'));
        });

        // Submit Form Interceptors
        async function handleCreateSubmit() {
            const form = document.getElementById('createAccountForm');
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const proceed = await sysConfirm("Are you sure you want to register this new user account? An email will be sent to the provided address.", "fa-user-plus", "var(--primary-blue)");
            if(proceed) {
                showLoading(); // SHOW LOADING SCREEN
                form.submit();
            }
        }

        async function handleEditSubmit() {
            const form = document.getElementById('editAccountForm');
            if(!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const proceed = await sysConfirm("Are you sure you want to save these changes to the user's account?", "fa-user-edit", "var(--primary-blue)");
            if(proceed) {
                showLoading(); // SHOW LOADING SCREEN
                form.submit();
            }
        }

        // === DELETE ACCOUNT LOGIC WITH MODAL ===
        async function handleDeleteSubmit() {
            const confirmText = await sysPrompt("<b>WARNING:</b> This action cannot be undone.<br><br>Type <b>DELETE</b> to confirm account deletion:");
            
            if (confirmText === "DELETE") {
                // Hide the edit modal so it doesn't overlap the loading screen
                document.getElementById('editModal').style.display = 'none';
                showLoading();
                
                // Copy the user ID from the edit form to the hidden delete form
                document.getElementById('delete_user_id').value = document.getElementById('edit_user_id').value;
                document.getElementById('deleteAccountForm').submit();
            } else if (confirmText !== null) {
                // They clicked Confirm but typed it wrong
                sysAlert("Deletion cancelled. You did not type <b>DELETE</b> correctly.", "fa-times-circle", "#ef4444");
            }
        }
        // ==========================================

        // === FEATURE 1: MIS DEPARTMENT LOCK LOGIC ===
        function setupMisLock(deptId, progId, posId) {
            const deptSelect = document.getElementById(deptId);
            const progInput = document.getElementById(progId);
            const posSelect = document.getElementById(posId);

            if(!deptSelect || !progInput || !posSelect) return;

            deptSelect.addEventListener('change', function() {
                if(this.value === 'MIS') {
                    // Lock Program to MIS
                    progInput.value = 'MIS';
                    progInput.readOnly = true;
                    progInput.style.backgroundColor = '#e2e8f0';
                    progInput.style.cursor = 'not-allowed';

                    // Lock Position to Admin
                    posSelect.value = 'Admin';
                    posSelect.style.pointerEvents = 'none'; // Prevents mouse interaction
                    posSelect.style.backgroundColor = '#e2e8f0';
                    posSelect.setAttribute('tabindex', '-1'); // Prevents keyboard tabbing
                } else {
                    // Unlock Program
                    progInput.readOnly = false;
                    progInput.style.backgroundColor = '';
                    progInput.style.cursor = 'text';

                    // Unlock Position
                    posSelect.style.pointerEvents = 'auto';
                    posSelect.style.backgroundColor = '';
                    posSelect.removeAttribute('tabindex');
                }
            });
        }

        // Apply lock logic to both forms
        document.addEventListener('DOMContentLoaded', () => {
            setupMisLock('create_department', 'create_program', 'create_position');
            setupMisLock('edit_department', 'edit_program', 'edit_position');
        });
        // ==========================================

        // Password Visibility Toggle
        function togglePasswordVisibility(inputId, iconElement) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                iconElement.classList.remove('fa-eye');
                iconElement.classList.add('fa-eye-slash');
            } else {
                input.type = "password";
                iconElement.classList.remove('fa-eye-slash');
                iconElement.classList.add('fa-eye');
            }
        }

        // Sidebar Toggle Logic
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle(window.innerWidth <= 992 ? 'mobile-nav-active' : 'sidebar-collapsed');
        });
        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        // 1. Image Compressor
        const fileInput = document.getElementById('pic_upload');
        const hiddenInput = document.getElementById('pic_base64');

        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (!file) {
                hiddenInput.value = ""; 
                return;
            }

            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    const canvas = document.createElement('canvas');
                    const MAX_WIDTH = 400;
                    const scaleSize = MAX_WIDTH / img.width;
                    canvas.width = MAX_WIDTH;
                    canvas.height = img.height * scaleSize;

                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    const dataUrl = canvas.toDataURL('image/jpeg', 0.7); 
                    hiddenInput.value = dataUrl;
                }
                img.src = e.target.result;
            }
            reader.readAsDataURL(file);
        });

        // 2. Search and Filter Logic
        const searchInput = document.getElementById('searchInput');
        const departmentFilter = document.getElementById('departmentFilter');
        const cards = document.querySelectorAll('.account-card');
        const departmentSections = document.querySelectorAll('.department-section');

        function filterAccounts() {
            const searchTerm = searchInput.value.toLowerCase();
            const selectedDepartment = departmentFilter.value;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const prog = card.getAttribute('data-prog');
                const empid = card.getAttribute('data-empid');
                const department = card.getAttribute('data-department');

                const matchesSearch = name.includes(searchTerm) || prog.includes(searchTerm) || empid.includes(searchTerm);
                const matchesFilter = selectedDepartment === 'All' || department === selectedDepartment;

                if (matchesSearch && matchesFilter) {
                    card.classList.remove('d-none');
                } else {
                    card.classList.add('d-none');
                }
            });

            departmentSections.forEach(section => {
                const sectionCards = section.querySelectorAll('.account-card');
                let hasVisibleCards = false;
                sectionCards.forEach(c => {
                    if (!c.classList.contains('d-none')) {
                        hasVisibleCards = true;
                    }
                });

                if (hasVisibleCards) {
                    section.classList.remove('d-none');
                } else {
                    section.classList.add('d-none');
                }
            });
        }

        searchInput.addEventListener('input', filterAccounts);
        departmentFilter.addEventListener('change', filterAccounts);

        // 3. EDIT MODAL LOGIC
        function openEditModal(card) {
            // Set form hidden input & edit fields
            document.getElementById('edit_user_id').value = card.getAttribute('data-userid');
            document.getElementById('edit_username').value = card.getAttribute('data-username');
            document.getElementById('edit_email').value = card.getAttribute('data-email');
            document.getElementById('edit_first_name').value = card.getAttribute('data-firstname');
            document.getElementById('edit_middle_initial').value = card.getAttribute('data-mi');
            document.getElementById('edit_surname').value = card.getAttribute('data-surname');
            document.getElementById('edit_sex').value = card.getAttribute('data-sex');
            document.getElementById('edit_employee_id').value = card.getAttribute('data-empid').toUpperCase();
            
            document.getElementById('edit_department').value = card.getAttribute('data-department');
            document.getElementById('edit_program').value = card.getAttribute('data-rawprog');
            document.getElementById('edit_position').value = card.getAttribute('data-rawpos');
            
            // Trigger the change event so the MIS lock logic applies immediately if they are an MIS user
            document.getElementById('edit_department').dispatchEvent(new Event('change'));
            
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            let modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>