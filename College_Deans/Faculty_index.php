<?php
session_start();
require '../db_connect.php';

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
$userPic      = $userData['profile_picture'] ?? null;
$userName     = $_SESSION['full_name'] ?? 'Unknown User';

// Clean up literal "null" strings that may come from DB errors
$cleanName = str_ireplace(['null', 'NULL'], '', $userName);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
if (empty($cleanName) || $cleanName === '.') {
    $cleanName = 'Unknown User';
}

// Generate initials from cleaned name
$nameParts = explode(' ', $cleanName);
if ($cleanName !== 'Unknown User') {
    $initials = count($nameParts) > 1
        ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
        : strtoupper(substr($nameParts[0], 0, 1));
} else {
    $initials = 'UU';
}

if (!empty($userPic)) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userPic);
} else {
    $picSrc = null;
}

// Security Flags
$isProgramHead = (stripos($userPosition, 'Program Head') !== false || stripos($userPosition, 'Coordinator') !== false);
$isAdmin = (stripos($userPosition, 'Admin') !== false);
$isDean = (stripos($userPosition, 'Dean') !== false);

// DEAN IS READ-ONLY FOR PROFILES
$canEdit = ($isAdmin || $isProgramHead); 

// Color mapping for departments
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
    'MIS'                              => '#6366f1'
];
$themeColor = $departmentColors[$userDept] ?? 'var(--primary-blue)';

// Department logo mapping
$departmentLogos = [
    'College of Science'               => '../Logo COS.png',
    'College of Education'             => '../Logo COE.png',
    'College of Engineering'           => '../Logo COENG.png',
    'College of Industrial Technology' => '../Logo CIT.png',
];
$deptLogo = $departmentLogos[$userDept] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Directory - COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ── Google Font ─────────────────────────────────────── */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

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
        }

        /* ── Reset ───────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: var(--bg-main); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

        /* ════════════════════════════════════════════════════════
           SIDEBAR
        ════════════════════════════════════════════════════════ */
        .sidebar {
            width: var(--sidebar-min);
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow: hidden;
            z-index: 1000;
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

        .sidebar:hover .logo-section img { width: 140px; }

        .menu-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b; 
            margin: 15px 0 5px;
            padding: 0 20px;
            white-space: nowrap;
            overflow: hidden;
            opacity: 0;
            max-height: 0;
            transition: opacity 0.2s ease, max-height 0.2s ease, padding 0.3s ease;
        }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; padding: 0 24px; }

        .nav-menu { list-style: none; padding: 0; margin: 0; } 
        .nav-menu li a {
            display: flex; align-items: center; justify-content: center;
            padding: 12px; margin: 4px 12px;
            color: var(--text-muted); text-decoration: none; font-size: 14.5px; font-weight: 600;
            border-radius: 12px; white-space: nowrap; overflow: hidden;
            transition: all 0.2s ease;
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

        .dept-logo-bubble {
            position: absolute; top: -26px; left: 50%; transform: translateX(-50%);
            width: 50px; height: 50px; background: white; border-radius: 50%;
            border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12), 0 0 0 3px white;
            display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10;
            transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease;
        }

        .dept-logo-bubble::after {
            content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid white;
            filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.08));
        }

        .sidebar:hover .dept-logo-bubble {
            width: 56px; height: 56px; top: -30px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15), 0 0 0 3px white;
        }

        .dept-logo-bubble img { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback { font-size: 20px; color: var(--primary-blue); }

        .profile-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0 2px; }

        .profile-img {
            width: 42px; height: 42px; border-radius: 50%; background: var(--primary-blue); color: white;
            font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; object-fit: cover;
        }

        .profile-info {
            flex: 1; min-width: 0; width: 0; opacity: 0; overflow: hidden; white-space: nowrap;
            transition: opacity 0.2s ease 0.05s, width 0.3s ease;
        }

        .sidebar:hover .profile-info { width: auto; opacity: 1; }

        .profile-info h4 { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; overflow: hidden; text-overflow: ellipsis; }
        .profile-info p { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; }

        .logout-btn {
            flex-shrink: 0; width: 0; opacity: 0; overflow: hidden; background: transparent; border: none;
            color: var(--text-muted); font-size: 16px; padding: 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease;
        }

        .sidebar:hover .logout-btn { width: auto; opacity: 1; }
        .logout-btn:hover { color: #ef4444; background: #fef2f2; }
        
        /* ════════════════════════════════════════════════════════
           MAIN CONTENT & CONTAINERS
        ════════════════════════════════════════════════════════ */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px; display: flex; justify-content: space-between; align-items: center; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; margin-bottom: 4px; }
        .header-title p { font-size: 14px; font-weight: 400; color: var(--text-muted); }

        .container { flex: 1; overflow-y: auto; padding: 20px 40px 40px; display: flex; flex-direction: column; gap: 20px; }

        /* Search Container */
        .search-container {
            flex-shrink: 0; background: var(--bg-card); border-radius: var(--radius-card); border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card); padding: 25px 30px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap;
        }
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 15px; }
        .dept-badge { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: var(--radius-pill); font-size: 12px; font-weight: 700; color: white; background-color: <?php echo $themeColor; ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }

        .search-bar { flex: 1; position: relative; max-width: 400px; min-width: 250px; }
        .search-bar input {
            width: 100%; padding: 12px 16px 12px 45px; border: 1px solid var(--border-color); border-radius: var(--radius-pill);
            font-size: 13px; outline: none; background: #f8fafc; color: var(--text-dark); transition: all 0.2s ease;
        }
        .search-bar input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .search-bar i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; }

        /* ── Forms & Buttons ────────────────────────── */
        .form-group { flex: 1; min-width: 120px; margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }

        .pill-input {
            width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-pill);
            font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark); outline: none; transition: all 0.2s ease;
        }
        select.pill-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto;
        }
        .pill-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background-color: white; }
        .pill-input[readonly], .pill-input[disabled] { background-image: none; background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }

        .btn-submit-large {
            background: var(--primary-blue); color: white; border: none; padding: 12px 30px; border-radius: var(--radius-pill);
            font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-submit-large:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2); }

        .btn-delete-large {
            background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: var(--radius-pill);
            font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-delete-large:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.2); }

        /* ===== FACULTY GRID & CARDS ===== */
        .program-section { margin-bottom: 30px; }
        .program-header { font-size: 16px; font-weight: 800; color: var(--text-dark); margin-bottom: 15px; padding-bottom: 5px; border-bottom: 2px solid var(--border-color); display: inline-block; }
        
        .faculty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; }

        .account-card { 
            background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-card); 
            padding: 30px 20px; text-align: center; transition: all 0.2s ease; cursor: pointer; 
            position: relative; box-shadow: var(--shadow-card); 
        }
        .account-card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); border-color: <?php echo $themeColor; ?>; }
        
        .card-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; overflow: hidden; border: 4px solid var(--accent-blue); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); font-weight: 800; }
        .card-avatar img { width: 100%; height: 100%; object-fit: cover; }
        
        .edit-icon { position: absolute; top: 15px; right: 15px; color: #cbd5e1; transition: all 0.2s ease; font-size: 16px; padding: 5px; border-radius: 50%; z-index: 2; }
        .edit-icon:hover { color: var(--primary-blue); background: var(--accent-blue); }

        .card-role { font-weight: 800; color: var(--primary-blue); font-size: 11px; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;}
        .card-name { font-weight: 800; color: var(--text-dark); font-size: 15px; margin-bottom: 6px; line-height: 1.2;}
        .card-spec { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        
        .workload-badge { display: inline-block; margin-top: 12px; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: #f8fafc; border: 1px solid var(--border-color); }

        .d-none { display: none !important; }
        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; background: var(--bg-card); border-radius: var(--radius-card); border: 1px dashed var(--border-color); }
        .empty-state i { font-size: 50px; color: #cbd5e1; margin-bottom: 15px; display: block; }
        .empty-state p { font-size: 18px; color: var(--text-dark); }
        .empty-state small { font-size: 13px; font-weight: normal; margin-top: 8px; display: block; }

        /* ── Consistent Table ────────────────────────────────── */
        .schedule-table-wrapper {
            overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input);
            background: white; padding-bottom: 20px; transition: padding 0.3s ease; box-shadow: var(--shadow-card);
        }
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: center; table-layout: fixed; word-wrap: break-word; }
        .schedule-table th {
            background: #f8fafc; color: var(--text-muted); font-weight: 700; padding: 12px 6px;
            border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color);
            text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;
        }
        .schedule-table th:last-child { border-right: none; }
        .schedule-table td {
            padding: 8px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color);
            vertical-align: middle; color: var(--text-dark); font-size: 12px;
        }
        .schedule-table td:last-child { border-right: none; }
        .schedule-table tbody tr:last-child td { border-bottom: none; }
        .schedule-table tbody tr:hover td { background: #f8fafc; }
        
        .table-input {
            width: 100%; padding: 8px 6px; border: 1px solid var(--border-color); border-radius: 8px; outline: none;
            font-size: 11px; font-weight: 600; text-align: center; color: var(--text-dark); transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box; background: #f1f5f9; cursor: not-allowed;
        }

        /* ════════════════════════════════════════════════════════
           MODALS
        ════════════════════════════════════════════════════════ */
        .modal { display: none; position: fixed; z-index: 1001; inset: 0; background: rgba(15, 23, 42, 0.6); overflow: auto; padding: 20px; backdrop-filter: blur(4px); }
        .modal-content { background: var(--bg-card); margin: 2% auto; padding: 30px 40px; width: 98%; max-width: 1400px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); }
        .close-btn { float: right; font-size: 28px; font-weight: bold; color: var(--text-muted); cursor: pointer; margin-top: -10px; transition: color 0.2s, transform 0.2s; }
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 15px; }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2000; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        
        /* ════════════════════════════════════════════════════════
           RESPONSIVE
        ════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); }
            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }
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

            #menu-toggle { display: block !important; }
            .container { padding: 20px; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="backdrop"></div>

    <aside class="sidebar">
        <div class="logo-section">
            <img src="../logo.png" alt="URS Logo" onerror="this.style.display='none'">
        </div>
        
        <div class="menu-label">Menu</div>
        <ul class="nav-menu">
            <li><a href="Dashboard_index.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i> <span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i> <span>Classroom Monitoring</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
           <li><a href="Course_index.php"><i class="fas fa-book"></i> <span>Course</span></a></li>
            <li><a href="Faculty_index.php" class="active"><i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span></a></li>
            <li><a href="Room_index.php"><i class="fas fa-door-open"></i> <span>Rooms</span></a></li>
            <li><a href="Approval_index.php"><i class="fas fa-check-circle"></i> <span>Approval</span></a></li>
            <li><a href="Download_index.php" ><i class="fas fa-file-download"></i> <span>Download</span></a></li>
            <li><a href="History_index.php"><i class="fas fa-history"></i> <span>History</span></a></li>
        </ul>

        <div class="profile-section">
            <div class="dept-logo-bubble" title="<?php echo htmlspecialchars($userDept); ?>">
                <?php if ($deptLogo): ?>
                    <img src="<?php echo htmlspecialchars($deptLogo); ?>"
                         alt="<?php echo htmlspecialchars($userDept); ?>"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <span class="dept-icon-fallback" style="display:none;"><i class="fas fa-university"></i></span>
                <?php else: ?>
                    <span class="dept-icon-fallback"><i class="fas fa-university"></i></span>
                <?php endif; ?>
            </div>

            <div class="profile-row">
                <a href="Profile_index.php"
                title="Go to My Profile"
                style="display:flex; align-items:center; gap:10px; text-decoration:none; flex:1; min-width:0; transition:opacity 0.2s;"
                onmouseover="this.style.opacity='0.7'"
                onmouseout="this.style.opacity='1'">
                    <?php if (!empty($picSrc)): ?>
                        <img src="<?php echo $picSrc; ?>" class="profile-img" alt="Profile Picture">
                    <?php else: ?>
                        <div class="profile-img"><?php echo htmlspecialchars($initials); ?></div>
                    <?php endif; ?>

                    <div class="profile-info">
                        <h4><?php echo htmlspecialchars($cleanName); ?></h4>
                        <p><?php echo htmlspecialchars($userDept); ?></p>
                    </div>
                </a>
                
                <a href="?logout=true" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
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
            <div class="search-container">
                <div class="page-title">
                    Faculty Directory
                    <span class="dept-badge">
                        <i class="fas fa-building" style="margin-right:6px;"></i>
                        <?php echo htmlspecialchars($userDept); ?>
                        <?php if($isProgramHead) echo " - " . htmlspecialchars($userProgram); ?>
                    </span>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name, ID, or program...">
                </div>
            </div>

            <div id="accountsContainer">
                <div style="text-align:center; padding: 50px; color: var(--text-muted);">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:30px;"></i>
                    <p style="margin-top: 15px;">Loading faculty data...</p>
                </div>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('scheduleModal')" title="Close Preview">&times;</span>
            <h2 style="text-align: center; margin-bottom: 5px; color: var(--text-dark); font-weight: 800; font-size:22px;" id="schedModalName">Professor Schedule</h2>
            <p style="text-align: center; margin-bottom: 25px; font-size: 13px; color: var(--text-muted);" id="schedModalLoad">Total Units: 0</p>
            
            <div class="schedule-table-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th style="width: 10%;">Day</th>
                            <th style="width: 16%;">Time</th>
                            <th style="width: 10%;">Course Code</th>
                            <th style="width: 18%;">Subject Description</th>
                            <th style="width: 4%;">Unit</th>
                            <th colspan="2" style="width: 8%; padding: 5px;">
                                Hours/Week<br>
                                <span style="font-size:9px; font-weight:600; color:#64748b;">Lec &nbsp;|&nbsp; Lab</span>
                            </th>
                            <th style="width: 14%;">Room</th>
                            <th style="width: 14%;">Professor</th>
                        </tr>
                    </thead>
                    <tbody id="scheduleTableBody">
                    </tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: flex-end; align-items: center; margin-top: 25px;">
                <button type="button" onclick="closeModal('scheduleModal')" class="btn-submit-large" style="background: #e2e8f0; color: #475569;">
                    Close Preview
                </button>
            </div>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content" style="max-width: 650px;">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h2 style="margin-bottom: 25px; color: var(--text-dark); font-weight: 800; text-align: center; font-size: 22px;">Edit Faculty Profile</h2>

            <div style="text-align: center; margin-bottom: 25px;">
                <img id="currentEditImg" src="https://via.placeholder.com/100"
                     style="width: 90px; height: 90px; border-radius: 50%; object-fit: cover; border: 4px solid var(--accent-blue); box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
            </div>

            <form id="editForm">
                <input type="hidden" name="user_id">

                <div class="form-grid">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" class="pill-input" name="username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="pill-input" name="email" required>
                    </div>
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" class="pill-input" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Surname</label>
                        <input type="text" class="pill-input" name="surname" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Initial</label>
                        <input type="text" class="pill-input" name="middle_initial" maxlength="5">
                    </div>
                    <div class="form-group">
                        <label>Sex</label>
                        <select name="sex" class="pill-input" required>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employee ID No.</label>
                        <input type="text" class="pill-input" name="employee_id" required>
                    </div>
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" class="pill-input" name="department" value="<?php echo htmlspecialchars($userDept); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Program</label>
                        <input type="text" class="pill-input" name="program" id="edit_program" required>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" class="pill-input" name="position" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 10px;">
                    <label>Upload New Picture (Optional)</label>
                    <input type="file" name="picture" accept="image/*" style="border: none; padding: 10px 0; background: transparent; width: 100%; font-size:13px; color:var(--text-muted);">
                </div>

                <div style="display: flex; gap: 15px; margin-top: 25px;">
                    <button type="button" id="btnDelete" class="btn-delete-large" style="flex:1;"><i class="fas fa-trash-alt" style="margin-right: 5px;"></i> DELETE</button>
                    <button type="submit" class="btn-submit-large" style="flex:1;">SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg"></div>
            <button id="sysModalAlertBtn" class="btn-submit-large" style="width:100%;">OK</button>
        </div>
    </div>

    <div id="sysModalConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalConfirmIcon" class="sys-modal-icon"></i>
            <div id="sysModalConfirmMsg" class="sys-modal-msg"></div>
            <div class="sys-modal-btns">
                <button id="sysModalConfirmCancelBtn" class="btn-submit-large" style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalConfirmOkBtn" class="btn-submit-large" style="flex:1;">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const SESSION_POS  = "<?php echo addslashes($userPosition); ?>";
        const SESSION_PROG = "<?php echo addslashes($userProgram); ?>";
        const CAN_EDIT     = <?php echo $canEdit ? 'true' : 'false'; ?>;
        const THEME_COLOR  = "<?php echo $themeColor; ?>";

        let allFaculty = [];
        let isPolling = false;

        // =========================================================
        // Promise-Based System Modals
        // =========================================================
        function sysAlert(msg, icon = 'fa-info-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysModalAlertMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalAlertIcon');
                iconEl.className = `fas ${icon} sys-modal-icon`;
                iconEl.style.color = color;
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
                iconEl.className = `fas ${icon} sys-modal-icon`;
                iconEl.style.color = color;
                document.getElementById('sysModalConfirm').style.display = 'flex';
                
                document.getElementById('sysModalConfirmCancelBtn').onclick = () => {
                    document.getElementById('sysModalConfirm').style.display = 'none';
                    resolve(false);
                };
                
                const okBtn = document.getElementById('sysModalConfirmOkBtn');
                if(color === '#ef4444') {
                    okBtn.style.backgroundColor = '#ef4444';
                } else {
                    okBtn.style.backgroundColor = 'var(--primary-blue)';
                }

                okBtn.onclick = () => {
                    document.getElementById('sysModalConfirm').style.display = 'none';
                    resolve(true);
                };
            });
        }

        function formatTime(timeStr) {
            if(!timeStr) return '';
            let [h, m] = timeStr.split(':');
            h = parseInt(h);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        }

        function compressImage(file) {
            return new Promise((resolve, reject) => {
                const maxWidth = 500, quality = 0.7;
                const reader = new FileReader();
                reader.readAsDataURL(file);
                reader.onload = event => {
                    const img = new Image();
                    img.src = event.target.result;
                    img.onload = () => {
                        let w = img.width, h = img.height;
                        if (w > maxWidth) { h *= maxWidth / w; w = maxWidth; }
                        const canvas = document.createElement('canvas');
                        canvas.width = w; canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        canvas.toBlob(blob => resolve(blob), 'image/jpeg', quality);
                    };
                };
                reader.onerror = error => reject(error);
            });
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // Sidebar Toggles
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => { 
            document.body.classList.remove('mobile-nav-active'); 
        });

        // =========================================================
        // AJAX: FETCH FACULTY & RENDER UI COLLABORATIVELY
        // =========================================================
        async function fetchFaculty() {
            try {
                const res = await fetch('Faculty_api.php?action=get_faculty');
                const data = await res.json();
                
                if (data.status === 'error') throw new Error(data.message);

                // Check if data changed using string comparison
                if (JSON.stringify(allFaculty) !== JSON.stringify(data)) {
                    allFaculty = data;
                    renderFaculty(allFaculty);
                    applySearchFilter(); // Re-apply search dynamically so the user isn't interrupted
                }
            } catch (err) {
                console.error("Polling error:", err);
            }
        }

        function renderFaculty(data) {
            const container = document.getElementById('accountsContainer');
            
            if (!data || data.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <p>No Faculty Found</p>
                        <small>There are currently no faculty records assigned.<br>Contact the Dean or Admin to assign faculty.</small>
                    </div>`;
                return;
            }

            // Group the data by Program explicitly in JS (They are already sorted by the SQL API)
            const grouped = {};
            data.forEach(user => {
                const prog = user.program || 'General / Unassigned';
                if (!grouped[prog]) grouped[prog] = [];
                grouped[prog].push(user);
            });

            let html = '';
            for (const [progName, users] of Object.entries(grouped)) {
                html += `
                <div class="program-section" data-program-group="${progName}">
                    <div class="program-header">${progName}</div>
                    <div class="faculty-grid">`;
                
                users.forEach(u => {
                    const fname = u.first_name || '';
                    const sname = u.surname || '';
                    const fullName = `${fname} ${sname}`.trim() || 'Unknown User';
                    
                    const i1 = fname ? fname.charAt(0) : 'U';
                    const i2 = sname ? sname.charAt(0) : '';
                    const initials = (i1 + i2).toUpperCase();

                    const avatarHtml = u.pic_base64 
                        ? `<img src="data:image/jpeg;base64,${u.pic_base64}" alt="Profile">`
                        : initials;

                    const loadColor = (u.current_load > 21) ? '#ef4444' : '#10b981';
                    const activeTermText = u.active_term || 'No Active Term';
                    
                    // JSON stringify the user info so we can pass it via data attribute securely
                    const jsonInfo = JSON.stringify(u).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
                    
                    const editBtnHtml = CAN_EDIT ? `<i class="fas fa-edit edit-icon" title="Edit Profile"></i>` : '';

                    html += `
                        <div class="account-card"
                             data-info="${jsonInfo}"
                             data-name="${fullName.toLowerCase()}"
                             data-prog="${(u.program || '').toLowerCase()}"
                             data-empid="${(u.employee_id || '').toLowerCase()}">
                            
                            ${editBtnHtml}

                            <div class="card-avatar" style="background-color: ${THEME_COLOR};">
                                ${avatarHtml}
                            </div>
                            <div class="card-role">${u.position || 'STAFF'}</div>
                            <div class="card-name">${fullName}</div>
                            <div class="card-spec">${u.program || 'N/A'}</div>
                            
                            <div class="workload-badge">
                                <div style="color:var(--text-muted); font-size:9px; margin-bottom:2px; font-weight:600; text-transform:uppercase;">
                                    ${activeTermText}
                                </div>
                                Workload: 
                                <span style="color: ${loadColor};">
                                    ${u.current_load || 0} / 21 Units
                                </span>
                            </div>
                        </div>`;
                });

                html += `</div></div>`;
            }

            container.innerHTML = html;
        }

        // =========================================================
        // SEARCH FILTER LOGIC
        // =========================================================
        function applySearchFilter() {
            const term = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.account-card').forEach(card => {
                const match = card.dataset.name.includes(term)
                           || card.dataset.prog.includes(term)
                           || card.dataset.empid.includes(term);
                card.classList.toggle('d-none', !match);
            });

            // Hide empty program sections entirely
            document.querySelectorAll('.program-section').forEach(section => {
                const visibleCards = section.querySelectorAll('.account-card:not(.d-none)').length;
                section.classList.toggle('d-none', visibleCards === 0);
            });
        }

        document.getElementById('searchInput').addEventListener('input', applySearchFilter);

        // =========================================================
        // SMART CLICK DELEGATION (Edit vs Schedule)
        // =========================================================
        document.getElementById('accountsContainer').addEventListener('click', function (e) {
            const card = e.target.closest('.account-card');
            if (!card) return;

            const p = JSON.parse(card.getAttribute('data-info').replace(/&apos;/g, "'").replace(/&quot;/g, '"'));

            // Pencil icon clicked -> Open Edit Form
            if (e.target.classList.contains('edit-icon') && CAN_EDIT) {
                const form = document.getElementById('editForm');
                form.querySelector('[name="user_id"]').value        = p.id;
                form.querySelector('[name="username"]').value       = p.username || '';
                form.querySelector('[name="email"]').value          = p.email || '';
                form.querySelector('[name="first_name"]').value     = p.first_name || '';
                form.querySelector('[name="surname"]').value        = p.surname || '';
                form.querySelector('[name="middle_initial"]').value = p.middle_initial || '';
                form.querySelector('[name="sex"]').value            = p.sex || 'Other';
                form.querySelector('[name="employee_id"]').value    = p.employee_id || '';
                form.querySelector('[name="department"]').value     = p.department || '';
                form.querySelector('[name="position"]').value       = p.position || '';
                form.querySelector('[name="picture"]').value        = '';

                // SECURITY LOCK FOR PROGRAM HEADS
                const progInput = form.querySelector('[name="program"]');
                progInput.value = p.program || '';
                if (SESSION_POS.includes('Program Head') || SESSION_POS.includes('Coordinator')) {
                    progInput.readOnly = true;
                    progInput.title = "You cannot change the program assignment.";
                } else {
                    progInput.readOnly = false;
                }

                document.getElementById('currentEditImg').src = p.pic_base64
                    ? `data:image/jpeg;base64,${p.pic_base64}`
                    : 'https://via.placeholder.com/100';

                document.getElementById('editModal').style.display = 'block';
                return;
            }

            // Card body clicked -> Open Schedule Preview
            openScheduleModal(p);
        });

        // =========================================================
        // FETCH & RENDER PROFESSOR SCHEDULE
        // =========================================================
        async function openScheduleModal(prof) {
            const cleanName = `${prof.first_name || ''} ${prof.surname || ''}`.trim();
            document.getElementById('schedModalName').innerText = `${cleanName}'s Schedule`;
            
            let loadColor = prof.current_load > 21 ? '#ef4444' : '#10b981';
            document.getElementById('schedModalLoad').innerHTML = `Total Assigned Workload (Active Term): <strong style="color:${loadColor};">${prof.current_load || 0} / 21 Units</strong>`;
            
            const tbody = document.getElementById('scheduleTableBody');
            tbody.innerHTML = '<tr><td colspan="9" style="padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin" style="font-size: 24px; margin-bottom: 10px; display:block;"></i> Loading schedule...</td></tr>';
            document.getElementById('scheduleModal').style.display = 'block';

            try {
                const res = await fetch(`Faculty_api.php?action=get_schedule&faculty_id=${prof.id}`);
                const data = await res.json();

                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" style="color: var(--text-muted); padding: 40px; font-style: italic;">This professor currently has no assigned classes in the active term.</td></tr>';
                    return;
                }

                let html = '';
                let currentGroup = '';

                data.forEach(row => {
                    let groupName = `${row.academic_year || 'Unknown A.Y.'} — ${row.semester || 'Unknown Semester'}`;
                    
                    if (groupName !== currentGroup) {
                        html += `
                            <tr style="background-color: #f8fafc;">
                                <td colspan="9" style="text-align: left; padding: 12px 15px; font-weight: 800; color: var(--primary-blue); font-size: 11px; letter-spacing: 0.5px; text-transform: uppercase; border-bottom: 1px solid var(--border-color);">
                                    <i class="fas fa-calendar-alt" style="margin-right: 6px;"></i> ${groupName}
                                </td>
                            </tr>
                        `;
                        currentGroup = groupName;
                    }

                    let startVal = row.start_time ? formatTime(row.start_time) : "";
                    let endVal = row.end_time ? formatTime(row.end_time) : "";
                    let rText = row.room_name || "TBA";
                    let fText = row.professor || cleanName || "TBA";
                    
                    if (fText !== 'TBA' && fText !== 'General Education' && !fText.startsWith('PROF.') && !fText.startsWith('⏳')) {
                        fText = 'PROF. ' + fText;
                    }
                    
                    let daysText = row.days || "";
                    let pendingBadgeHtml = row.approval_status === 'Pending' ? `<div style="margin-top:4px; font-size:9px; font-weight:800; color:#d97706; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block;">⏳ PENDING</div>` : '';

                    html += `
                        <tr>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="text" class="table-input" value="${daysText}" disabled placeholder="No Day">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="text" class="table-input" style="margin-bottom: 6px;" value="${startVal}" disabled placeholder="Start Time">
                                <input type="text" class="table-input" value="${endVal}" disabled placeholder="End Time">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <strong style="color:var(--primary-blue); font-size:12px;">${row.course_code || ''}</strong>
                                <div style="font-size:10px; font-weight:700; color:var(--text-muted); margin-top:2px;">${row.program || ''} ${row.section || ''}</div>
                            </td>
                            <td style="text-align:left; font-weight:600; font-size: 11px; padding-left: 8px; line-height: 1.2; vertical-align: middle;">
                                ${row.descriptive_title || ''}
                                <br>${pendingBadgeHtml}
                            </td>
                            <td style="text-align: center; vertical-align: middle;"><strong>${row.units || 0}</strong></td>
                            <td style="border-right: 1px dashed var(--border-color); width:4%; text-align: center; vertical-align: middle;">${row.lecture_hours || 0}</td>
                            <td style="width:4%; text-align: center; vertical-align: middle;">${row.laboratory_hours || 0}</td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="text" class="table-input" value="${rText}" disabled placeholder="No Room">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <input type="text" class="table-input" value="${fText}" disabled placeholder="No Professor">
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } catch (e) {
                console.error(e);
                tbody.innerHTML = '<tr><td colspan="9" style="color: #ef4444; padding: 30px;">Failed to load schedule from server.</td></tr>';
            }
        }

        // =========================================================
        // FORM SUBMISSIONS (Edit & Delete)
        // =========================================================
        document.getElementById('editForm').addEventListener('submit', async function (e) {
            e.preventDefault();

            const proceed = await sysConfirm("Are you sure you want to update this user's profile information?", 'fa-question-circle', 'var(--primary-blue)');
            if (!proceed) return;

            const fd = new FormData(this);
            const fileInput = this.querySelector('input[type="file"]');

            if (fileInput.files.length > 0) {
                const blob = await compressImage(fileInput.files[0]);
                fd.set('picture', blob, 'compressed.jpg');
            }

            const btn = this.querySelector('.btn-submit-large');
            btn.disabled = true;
            btn.innerText = 'PROCESSING...';

            try {
                const res = await fetch('Faculty_api.php', { method: 'POST', body: fd });
                const result = await res.json();

                if (result.status === 'success') {
                    await sysAlert(result.message, 'fa-check-circle', '#10b981');
                    closeModal('editModal');
                    fetchFaculty(); // Re-fetch seamlessly!
                } else {
                    await sysAlert('Error: ' + result.message, 'fa-exclamation-triangle', '#ef4444');
                }
            } catch (e) {
                console.error(e);
                await sysAlert('Server Error. Please try again.', 'fa-wifi', '#ef4444');
            } finally {
                btn.disabled = false;
                btn.innerText = 'SAVE CHANGES';
            }
        });

        document.getElementById('btnDelete').addEventListener('click', async function () {
            const msg = "<span style='color:#ef4444; font-weight:700;'>WARNING:</span> Are you sure you want to completely delete this user? This action cannot be undone.";
            const proceed = await sysConfirm(msg, 'fa-exclamation-triangle', '#ef4444');
            if (!proceed) return;

            const fd = new FormData();
            fd.append('delete_id', document.getElementById('editForm').querySelector('[name="user_id"]').value);

            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> DELETING...';

            try {
                const res = await fetch('Faculty_api.php', { method: 'POST', body: fd });
                const result = await res.json();

                if (result.status === 'success') {
                    await sysAlert(result.message, 'fa-check-circle', '#10b981');
                    closeModal('editModal');
                    fetchFaculty(); // Re-fetch seamlessly!
                } else {
                    await sysAlert('Error: ' + result.message, 'fa-exclamation-triangle', '#ef4444');
                }
            } catch (e) {
                console.error(e);
                await sysAlert('Server Error. Please try again.', 'fa-wifi', '#ef4444');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-trash-alt" style="margin-right: 5px;"></i> DELETE';
            }
        });

        // =========================================================
        // START THE SYSTEM ON LOAD
        // =========================================================
        window.onload = () => {
            fetchFaculty(); // Initial load
            setInterval(fetchFaculty, 5000); // Poll every 5 seconds for live collaboration
        };
    </script>
</body>
</html>