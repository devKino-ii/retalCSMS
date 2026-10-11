<?php
// ============================================================
//  DASHBOARD — COSMOS (Faculty & General User)
// ============================================================
session_start();
require '../db_connect.php';

// ----- Logout -----------------------------------------------
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array();
    session_destroy();
    header("Location: ../Login/Login_index.php");
    exit();
}

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login_index.php");
    exit();
}

// Fetch the most up-to-date user data directly from the database
$userId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

if (!$userData) {
    echo "User not found.";
    exit();
}

// Prepare display variables
$userPosition = $userData['position'] ?? 'Staff';
$userDept     = $userData['department'] ?? 'Department';
$userEmail    = $userData['email'] ?? '';
$userProgram  = $userData['program'] ?? '';

// Retrieve user session data with fallbacks
$userName     = $_SESSION['full_name']       ?? '';

$isFaculty = ($userPosition === 'Faculty');

// AGGRESSIVE CLEANUP: Remove literal "null" strings from database errors
$cleanName = str_ireplace(['null', 'NULL'], '', $userName);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));

if (empty($cleanName) || $cleanName === '.') {
    $cleanName = 'Unknown User'; // Fallback if name is totally empty
}

// Generate initials accurately based on the cleaned name
$nameParts = explode(' ', $cleanName);
$initials = 'U';
if ($cleanName !== 'Unknown User') {
    if (count($nameParts) > 1) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
    } else {
        $initials = strtoupper(substr($nameParts[0], 0, 1));
    }
} else {
    $initials = 'UU'; // Unknown User initials
}

// Handle Profile Picture
$picSrc = null;
if (!empty($userData['profile_picture'])) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userData['profile_picture']);
    $_SESSION['profile_picture'] = $picSrc; // Update session just in case
}

// Department color mapping
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
    'MIS'                              => '#6366f1',
    'University'                       => '#1e40af'
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
    <title>Dashboard - COSMOS</title>
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

        /* ===== MAIN CONTENT ===== */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        
        .container { padding: 20px 40px 40px 40px; display: flex; flex-direction: column; gap: 20px; flex: 1; overflow: hidden; }
        
        .page-title-container { display: flex; align-items: center; gap: 15px; margin-bottom: 5px; }
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; text-transform: uppercase; margin: 0; }

        /* Live indicator */
        .live-indicator {
            display: flex; align-items: center; gap: 6px;
            font-size: 11px; font-weight: 700; color: #10b981;
            background: #d1fae5; padding: 4px 10px; border-radius: 99px;
        }
        .live-dot {
            width: 7px; height: 7px; border-radius: 50%; background: #10b981;
            animation: livePulse 1.8s ease-in-out infinite;
        }
        @keyframes livePulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%        { opacity: 0.4; transform: scale(0.7); }
        }

        /* ===== TOP WIDGETS ROW ===== */
        .widgets-row { display: flex; gap: 20px; flex-shrink: 0; }
        .widget-card { background: var(--bg-card); border-radius: var(--radius-card); padding: 20px 25px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: all 0.2s ease; }
        
        .clickable-widget { cursor: pointer; }
        .clickable-widget:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(27, 80, 136, 0.1); border-color: var(--primary-blue); }

        .time-widget { flex: 1.5; align-items: flex-start; padding-left: 25px;}
        .time-date { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 5px; letter-spacing: 1px;}
        .time-clock { font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1; letter-spacing: -1px;}

        .stat-widget { flex: 1; position: relative;}
        .stat-title { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px; width: 100%; text-align: left; }
        .stat-value { font-size: 32px; font-weight: 900; color: var(--text-dark); display: flex; justify-content: space-between; align-items: center; width: 100%; line-height: 1; }
        .stat-value i { font-size: 24px; color: var(--primary-blue); opacity: 0.8; }
        .stat-sub { font-size: 10px; color: var(--text-muted); font-weight: 600; width: 100%; text-align: left; margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}

        /* ===== MAIN LAYOUT GRID ===== */
        .layout-grid { display: flex; gap: 20px; flex: 1; overflow: hidden; }
        
        /* Left Column (Calendar) */
        .left-column { flex: 3; display: flex; flex-direction: column; gap: 15px; overflow: hidden; min-width: 0;}
        
        .controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex-shrink: 0;}
        .search-bar { flex: 1; position: relative; min-width: 200px; }
        .search-bar input { width: 100%; padding: 12px 16px 12px 40px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 13px; font-weight: 500; background: var(--bg-card); color: var(--text-dark); outline: none; transition: 0.2s; }
        .search-bar input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .search-bar i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        
        .filter-select { 
            padding: 12px 18px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); 
            font-size: 12px; font-weight: 600; background: var(--bg-card); color: var(--text-dark); 
            cursor: pointer; outline: none; transition: 0.2s; appearance: none; padding-right: 35px; 
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); 
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; 
        }
        .filter-select:hover, .filter-select:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }

        #filterAY { background-color: var(--primary-blue); color: white; border-color: var(--primary-blue); background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23ffffff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); }

        .calendar-wrapper { background: var(--bg-card); border-radius: var(--radius-card); overflow: auto; flex: 1; box-shadow: var(--shadow-card); border: 1px solid var(--border-color);}
        .cal-grid { display: grid; grid-template-rows: 50px repeat(14, 100px); width: max-content; min-width: 100%; position: relative; }
        .cal-header { background: #f8fafc; border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; text-transform: uppercase; color: var(--text-muted); position: sticky; top: 0; z-index: 100; letter-spacing: 0.5px;}
        .cal-time { background: #f8fafc; border-right: 1px solid var(--border-color); border-bottom: 1px dashed var(--border-color); display: flex; align-items: flex-start; justify-content: flex-end; padding: 10px 10px; font-size: 11px; color: var(--text-muted); font-weight: 600; height: 100px; position: sticky; left: 0; z-index: 110; }
        .cal-header:first-child { position: sticky; left: 0; top: 0; z-index: 120; }
        .cal-cell { border-right: 1px solid var(--border-color); border-bottom: 1px dashed var(--border-color); position: relative; height: 100px; background: var(--bg-card); }

        .card { 
            position: absolute; width: 94%; left: 3%; background: var(--primary-blue); color: white; border-radius: 8px; padding: 10px 12px; 
            font-size: 11px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); z-index: 5; 
            display: flex; flex-direction: column; justify-content: flex-start; align-items: flex-start; text-align: left; 
            overflow: hidden; white-space: normal; line-height: 1.3; transition: all 0.2s ease; border: 1px solid rgba(0,0,0,0.1); cursor: pointer;
        }
        .card:hover { transform: scale(1.02) translateY(-2px); box-shadow: 0 8px 16px rgba(27,80,136,0.25); z-index: 50; }

        /* Right Column (Side Panels) */
        .right-column { width: 320px; display: flex; flex-direction: column; gap: 20px; flex-shrink: 0; }
        
        .side-panel { background: var(--bg-card); border-radius: var(--radius-card); box-shadow: var(--shadow-card); border: 1px solid var(--border-color); display: flex; flex-direction: column; flex: 1; overflow: hidden; }
        .panel-header { padding: 20px; font-size: 14px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); text-align: center; flex-shrink: 0; background: #f8fafc;}
        .panel-content { padding: 15px; overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 10px; }

        /* Button Styling for Quick Download */
        .btn-download { background: var(--primary-blue); color: white; border: none; padding: 12px 16px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-download:hover:not(:disabled) { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 4px 10px rgba(27, 80, 136, 0.2); }
        .btn-download:disabled { background: #cbd5e1; cursor: not-allowed; }

        .backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 999; }

        @media (max-width: 1200px) { .layout-grid { flex-direction: column; } .left-column { flex: none; height: 500px; } .right-column { width: 100%; flex-direction: row; } }
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; transform: translateX(-100%); z-index: 1000; }
            body.mobile-nav-active .sidebar { transform: translateX(0); }
            .backdrop { display: block; }
            body.mobile-nav-active .backdrop { display: block; }
            .container { flex-direction: column; padding: 20px; }
            .widgets-row { flex-direction: column; height: auto; }
            .right-column { flex-direction: column; }
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
            <li><a href="Dashboard_index.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <?php if (!$isFaculty): ?>
                <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i> <span>Create Class</span></a></li>
                <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i> <span>Class Monitoring</span></a></li>
            <?php endif; ?>
        </ul>
        <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <?php if (!$isFaculty): ?>
                <li><a href="Course_index.php"><i class="fas fa-book"></i> <span>Course</span></a></li>
                <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span></a></li>
                <li><a href="Room_index.php"><i class="fas fa-door-open"></i> <span>Rooms</span></a></li>
                <li><a href="Approval_index.php"><i class="fas fa-check-circle"></i> <span>Approval</span></a></li>
            <?php endif; ?>
            <li><a href="Download_index.php"><i class="fas fa-file-download"></i> <span>Download</span></a></li>
            <?php if (!$isFaculty): ?>
                <li><a href="History_index.php"><i class="fas fa-history"></i> <span>History</span></a></li>
            <?php endif; ?>
        </ul>
        <div style="margin-top: auto;">
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
            
            <div class="page-title-container">
                <h2 class="page-title">Dashboard</h2>
                <div class="live-indicator" id="liveIndicator" title="Auto-syncing every 10 seconds">
                    <div class="live-dot"></div>
                    <span>LIVE</span>
                </div>
            </div>

            <div class="widgets-row">
                <div class="widget-card time-widget">
                    <div class="time-date" id="dash-date">LOADING DATE...</div>
                    <div class="time-clock" id="dash-time">--:--</div>
                </div>
                <div class="widget-card stat-widget" title="My Next Class">
                    <div class="stat-title">MY NEXT CLASS</div>
                    <div class="stat-value" style="font-size: 24px;"><i class="fas fa-chalkboard" style="color:var(--text-dark);"></i> <span id="next-class-code">--</span></div>
                    <div class="stat-sub" style="font-size: 11px;"><span id="next-class-time">--</span> &bull; Room: <span id="next-class-room">--</span></div>
                </div>
                <div class="widget-card stat-widget" title="My Workload">
                    <div class="stat-title">MY WORKLOAD</div>
                    <div class="stat-value"><i class="fas fa-briefcase" style="color:var(--text-dark);"></i> <span id="workload-units">0</span></div>
                    <div class="stat-sub">Total Units this Semester</div>
                </div>
            </div>

            <div class="layout-grid">
                
                <div class="left-column">
                    <div class="controls">
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" id="filterInput" placeholder="Search my subjects...">
                        </div>
                        <select id="filterAY" class="filter-select"></select>
                        
                        <?php if (!$isFaculty): ?>
                        <select id="filterProg" class="filter-select">
                            <option value="All">All Programs</option>
                        </select>
                        <?php else: ?>
                            <input type="hidden" id="filterProg" value="All">
                        <?php endif; ?>

                        <select id="filterSem" class="filter-select">
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                        <select id="filterDay" class="filter-select">
                            <option value="All">All Days</option>
                            <option value="Mon">Monday</option>
                            <option value="Tue">Tuesday</option>
                            <option value="Wed">Wednesday</option>
                            <option value="Thu">Thursday</option>
                            <option value="Fri">Friday</option>
                            <option value="Sat">Saturday</option>
                        </select>
                    </div>

                    <div class="calendar-wrapper">
                        <div id="calendarGrid" class="cal-grid"></div>
                    </div>
                </div>

                <div class="right-column">
                    <div class="side-panel">
                        <div class="panel-header">QUICK ACTIONS</div>
                        <div class="panel-content" style="padding: 15px; gap: 15px;">
                            
                            <form id="quickWorkloadForm" action="Download_action.php" method="POST" target="_blank" style="width: 100%; border: 1px solid var(--border-color); padding: 15px; border-radius: 12px; text-align: center; background: white;">
                                <input type="hidden" name="report_type" value="workload">
                                <input type="hidden" name="department" value="<?php echo htmlspecialchars($userDept); ?>">
                                <input type="hidden" name="faculty_id" value="<?php echo htmlspecialchars($userId); ?>">
                                <input type="hidden" name="faculty_name" value="<?php echo htmlspecialchars($cleanName); ?>">
                                <input type="hidden" name="format" value="pdf">
                                <input type="hidden" name="academic_year" id="qd_ay" value="">
                                <input type="hidden" name="semester" id="qd_sem" value="">
                                
                                <i class="fas fa-briefcase" style="font-size: 24px; color: var(--primary-blue); margin-bottom: 8px;"></i>
                                <div style="font-size: 13px; color: var(--text-dark); font-weight: 700;">My Workload</div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 12px;" id="qd_sublabel">Loading...</div>

                                <button type="submit" class="btn-download" style="width: 100%; padding: 10px; font-size: 12px;" id="qd_btn" disabled>
                                    <i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF
                                </button>
                            </form>

                            <form id="quickScheduleForm" action="Download_action.php" method="POST" target="_blank" style="width: 100%; border: 1px solid var(--border-color); padding: 15px; border-radius: 12px; text-align: center; background: white;">
                                <input type="hidden" name="report_type" value="general">
                                <input type="hidden" name="department" value="<?php echo htmlspecialchars($userDept); ?>">
                                <input type="hidden" name="sort_by" value="Faculty">
                                <input type="hidden" name="format" value="pdf">
                                <input type="hidden" name="academic_year" id="qd_sched_ay" value="">
                                <input type="hidden" name="semester" id="qd_sched_sem" value="">
                                
                                <i class="fas fa-calendar-alt" style="font-size: 24px; color: #10b981; margin-bottom: 8px;"></i>
                                <div style="font-size: 13px; color: var(--text-dark); font-weight: 700;">My Schedule</div>
                                <div style="font-size: 11px; color: var(--text-muted); margin-bottom: 12px;" id="qd_sched_sublabel">Loading...</div>

                                <button type="submit" class="btn-download" style="width: 100%; padding: 10px; font-size: 12px; background-color: #10b981;" id="qd_sched_btn" disabled>
                                    <i class="fas fa-download" style="margin-right: 5px;"></i> Download PDF
                                </button>
                            </form>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

<script>
        let dbRoomsList = [];
        let allData = [];
        let lastDataHash = '';
        let programColorMap = {}; 

        const currentUserPosition = "<?php echo addslashes($userPosition); ?>";

        // ── Deterministic Color Palette ──
        const PROGRAM_PALETTE = [
            '#1b5088', '#0e7490', '#7c3aed', '#be185d', '#15803d', '#c2410c', 
            '#1d4ed8', '#a16207', '#0f766e', '#9333ea', '#b91c1c', '#0369a1', 
            '#4d7c0f', '#d97706', '#0891b2', '#7e22ce', '#047857', '#db2777'
        ];

        function hashProgram(str) {
            let hash = 5381;
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) + hash) + str.charCodeAt(i);
                hash = hash & 0xffffffff;
            }
            return Math.abs(hash);
        }

        function getProgramColor(program) {
            if (!program) return '#64748b';
            const key = program.trim().toUpperCase();
            if (programColorMap[key]) return programColorMap[key];

            const idx   = hashProgram(key) % PROGRAM_PALETTE.length;
            const color = PROGRAM_PALETTE[idx];
            programColorMap[key] = color;
            return color;
        }

        // === 1. CLOCK LOGIC ===
        function updateClock() {
            const now = new Date();
            const months = ["JANUARY", "FEBRUARY", "MARCH", "APRIL", "MAY", "JUNE", "JULY", "AUGUST", "SEPTEMBER", "OCTOBER", "NOVEMBER", "DECEMBER"];
            const days = ["SUNDAY", "MONDAY", "TUESDAY", "WEDNESDAY", "THURSDAY", "FRIDAY", "SATURDAY"];
            
            document.getElementById('dash-date').innerText = `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()} | ${days[now.getDay()]}`;
            document.getElementById('dash-time').innerText = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).replace(' ', '');
        }
        setInterval(updateClock, 1000);
        updateClock();

        // === 2. FETCH INITIAL DATA ===
        async function fetchInitialData() {
            try {
                const res = await fetch('Dashboard_api.php?action=init_data');
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();
                
                document.getElementById('workload-units').innerText = data.my_workload || '0';
                
                if (data.next_class) {
                    const nc = data.next_class;
                    const [h, m, s] = nc.start_time.split(':');
                    let ampm = h >= 12 ? 'PM' : 'AM';
                    let hour = h % 12 || 12;
                    let timeStr = `${hour}:${m} ${ampm}`;
                    
                    document.getElementById('next-class-code').innerText = nc.course_code;
                    document.getElementById('next-class-room').innerText = nc.room_name || 'TBA';
                    document.getElementById('next-class-time').innerText = timeStr;
                } else {
                    document.getElementById('next-class-code').innerText = "NO CLASSES";
                    document.getElementById('next-class-room').innerText = "--";
                    document.getElementById('next-class-time').innerText = "--";
                }

                document.getElementById('qd_ay').value = data.active_ay || '';
                document.getElementById('qd_sem').value = data.active_sem || '';
                document.getElementById('qd_sched_ay').value = data.active_ay || '';
                document.getElementById('qd_sched_sem').value = data.active_sem || '';
                
                if (data.active_ay && data.active_sem) {
                    const termLabel = `${data.active_sem}, ${data.active_ay}`;
                    document.getElementById('qd_sublabel').innerText = termLabel;
                    document.getElementById('qd_sched_sublabel').innerText = termLabel;
                    document.getElementById('qd_btn').disabled = false;
                    document.getElementById('qd_sched_btn').disabled = false;
                } else {
                    document.getElementById('qd_sublabel').innerText = `No active schedule found`;
                    document.getElementById('qd_sched_sublabel').innerText = `No active schedule found`;
                }

                if (data.academic_years && data.academic_years.length > 0) {
                    const select = document.getElementById('filterAY');
                    select.innerHTML = '<option value="All">Academic Year</option>';
                    data.academic_years.forEach(ay => select.innerHTML += `<option value="${ay}">${ay}</option>`);
                    select.value = data.academic_years[0]; 
                }

                if (data.programs && data.programs.length > 0 && document.getElementById('filterProg') && document.getElementById('filterProg').tagName === 'SELECT') {
                    const select = document.getElementById('filterProg');
                    select.innerHTML = ''; 

                    if (currentUserPosition === "Program Head" && data.programs.length === 1) {
                        select.innerHTML = `<option value="${data.programs[0]}">${data.programs[0]}</option>`;
                        select.disabled = true;
                        select.style.opacity = '0.7';
                        select.style.cursor = 'not-allowed';
                        select.title = "Access limited to your assigned program.";
                    } else {
                        select.innerHTML = '<option value="All">All Programs</option>';
                        data.programs.forEach(p => {
                            select.innerHTML += `<option value="${p}">${p}</option>`;
                            getProgramColor(p); // Pre-cache color
                        });
                    }
                }

                dbRoomsList = data.rooms || []; 

                const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                const currentDay = daysOfWeek[new Date().getDay()]; 
                document.getElementById('filterDay').value = (currentDay !== 'Sun') ? currentDay : 'All';
                
                const currentMonth = new Date().getMonth() + 1;
                const semSelect = document.getElementById('filterSem');
                if (currentMonth >= 1 && currentMonth <= 5) semSelect.value = '2nd Semester';
                else if (currentMonth >= 6 && currentMonth <= 7) semSelect.value = 'Summer';
                else semSelect.value = '1st Semester';

            } catch (error) { 
                console.error('Error fetching initial data:', error); 
            }
        }

        function flashLiveIndicator() {
            const el = document.getElementById('liveIndicator');
            if(el) {
                el.style.background = '#a7f3d0';
                setTimeout(() => { el.style.background = '#d1fae5'; }, 1000);
            }
        }

        // === 3. FETCH SCHEDULES ===
        async function fetchSchedules(silent = false) {
            const ay = document.getElementById('filterAY').value || 'All';
            const prog = document.getElementById('filterProg') ? document.getElementById('filterProg').value : 'All';
            const sem = document.getElementById('filterSem').value || 'All';
            const day = document.getElementById('filterDay').value || 'All';

            try {
                const res = await fetch(`Dashboard_api.php?action=get_schedules&ay=${ay}&prog=${prog}&sem=${sem}&day=${day}`);
                if (!res.ok) throw new Error('Network response was not ok');
                const fetched = await res.json();
                
                const newHash = JSON.stringify(fetched).length + '|' + (fetched[0]?.id ?? '') + '|' + fetched.length;

                if (!silent || newHash !== lastDataHash) {
                    lastDataHash = newHash;
                    allData = Array.isArray(fetched) ? fetched : [];
                    refreshView();

                    if (silent && newHash !== lastDataHash) {
                        flashLiveIndicator(); 
                    }
                }
            } catch (error) { console.error('Error fetch schedules:', error); }
        }

        // === 4. START LIVE SYNC (10s) ===
        function startLiveSync() {
            setInterval(async () => {
                await fetchSchedules(true); 
            }, 10000); // 10 seconds
        }

        // === 5. RENDER CALENDAR (FILTERED FOR FACULTY) ===
        function refreshView() {
            const searchTerm = document.getElementById('filterInput').value.toLowerCase();
            const visibleEvents = [];

            const filteredData = allData.filter(d => {
                const term = searchTerm.toLowerCase();
                return (d.course_code && d.course_code.toLowerCase().includes(term)) ||
                       (d.professor && d.professor.toLowerCase().includes(term)) ||
                       (d.section && d.section.toLowerCase().includes(term));
            });

            filteredData.forEach(d => {
                if(!d.start_time || !d.end_time) return;
                const [h, m] = d.start_time.split(':').map(Number);
                const [eh, em] = d.end_time.split(':').map(Number);
                const startMins = h * 60 + m;
                const endMins = eh * 60 + em;
                
                let rId = d.room_id;
                if (!rId || rId === '0' || rId === 'null') rId = 'TBA';

                visibleEvents.push({
                    data: d, startHour: h, startMin: m, startMins: startMins,
                    endMins: endMins, height: (endMins - startMins) * (100/60), roomId: rId
                });
            });

            const activeRoomIds = new Set(visibleEvents.map(ev => String(ev.roomId)));

            let sortedRooms = dbRoomsList.filter(r => activeRoomIds.has(String(r.id)));
            
            // Bring in external rooms or virtual rooms that were approved
            visibleEvents.forEach(ev => {
                if(ev.data.assigned_room_display && !sortedRooms.some(r => String(r.id) === String(ev.roomId))) {
                    sortedRooms.push({ id: ev.roomId, room_name: ev.data.assigned_room_display });
                }
            });

            if (activeRoomIds.has('TBA')) {
                sortedRooms.push({ id: 'TBA', room_name: 'TBA / Unassigned' });
            }

            sortedRooms.sort((a, b) => String(a.room_name).localeCompare(String(b.room_name)));

            const grid = document.getElementById('calendarGrid');

            if (sortedRooms.length === 0) {
                grid.style.gridTemplateColumns = "1fr";
                grid.innerHTML = `
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; text-align: center; grid-column: 1 / -1; min-height: 400px;">
                        <i class="fas fa-calendar-times" style="font-size: 50px; color: #cbd5e1; margin-bottom: 15px;"></i>
                        <p style="font-size: 18px; color: var(--primary-blue);">No Classes Scheduled</p>
                        <p style="font-size: 12px; font-weight: 400; margin-top: 5px;">Your approved classes will appear here.</p>
                    </div>
                `;
                return;
            }

            grid.style.gridTemplateColumns = `80px repeat(${sortedRooms.length}, minmax(180px, 1fr))`;

            let html = '<div class="cal-header">Time</div>';
            sortedRooms.forEach(r => html += `<div class="cal-header">${r.room_name}</div>`);

            for (let h = 7; h <= 20; h++) {
                let t = (h > 12 ? h - 12 : h) + (h >= 12 ? ' PM' : ' AM');
                html += `<div class="cal-time">${t}</div>`;
                sortedRooms.forEach(r => html += `<div class="cal-cell" id="c-${String(r.id).replace(/\s+/g, '_')}-${h}"></div>`);
            }
            grid.innerHTML = html;

            let grouped = {};
            visibleEvents.forEach(ev => {
                const key = ev.roomId;
                if (!grouped[key]) grouped[key] = [];
                grouped[key].push(ev);
            });

            Object.values(grouped).forEach(group => {
                group.sort((a, b) => a.startMins - b.startMins);
                let columns = [];
                group.forEach(ev => {
                    let placed = false;
                    for (let i = 0; i < columns.length; i++) {
                        if (ev.startMins >= columns[i][columns[i].length - 1].endMins) {
                            columns[i].push(ev); ev.colIndex = i; placed = true; break;
                        }
                    }
                    if (!placed) { columns.push([ev]); ev.colIndex = columns.length - 1; }
                });
                group.forEach(ev => { ev.width = 96 / columns.length; ev.left = 2 + (ev.colIndex * ev.width); });
            });

            function convertTime(timeStr) {
                if (!timeStr) return "";
                let [h, m] = timeStr.split(':');
                h = parseInt(h);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12; h = h ? h : 12;
                return `${h}:${m} ${ampm}`;
            }

            visibleEvents.forEach(ev => {
                const d = ev.data;
                const safeRoomId = String(ev.roomId).replace(/\s+/g, '_');
                const cell = document.getElementById(`c-${safeRoomId}-${ev.startHour}`);

                if (cell) {
                    const div = document.createElement('div');
                    div.className = 'card';
                    
                    div.style.backgroundColor = getProgramColor(d.program);
                    div.style.top = `${ev.startMin * (100/60)}px`;
                    div.style.height = `${ev.height}px`;
                    div.style.left = `${ev.left}%`;
                    div.style.width = `${ev.width}%`;

                    const timeRng = `${convertTime(d.start_time)} - ${convertTime(d.end_time)}`;
                    let profName = (d.professor || '').toUpperCase();
                    
                    if (profName !== 'TBA' && !profName.startsWith('PROF.')) profName = 'PROF. ' + profName;
                    div.title = `${d.course_code} | ${d.days} | ${d.program} ${d.section} | ${profName} | ${timeRng}`;
                    div.innerHTML = `
                        <b style="font-size:13px; margin-bottom:4px; display:block; line-height:1.2; letter-spacing:0.5px;">${d.course_code}</b>
                        <span style="margin-bottom:2px; display:block; font-size:11px;">${d.program}, ${d.section}</span>
                        <span style="font-weight:800; margin-top:auto; display:block; font-size:12px;">${d.days} &bull; ${timeRng}</span>
                    `;
                    cell.appendChild(div);
                }
            });
        }

        // === INITIALIZE ===
        document.addEventListener('DOMContentLoaded', async () => {
            if (window.innerWidth <= 992) {
                document.getElementById('menu-toggle').style.display = 'block';
            }

            await fetchInitialData();
            await fetchSchedules();
            
            startLiveSync();

            document.getElementById('filterInput').addEventListener('input', refreshView);
            ['filterAY', 'filterProg', 'filterSem', 'filterDay'].forEach(id => {
                if(document.getElementById(id)) {
                    document.getElementById(id).addEventListener('change', fetchSchedules);
                }
            });

            document.getElementById('menu-toggle').addEventListener('click', () => {
                if (window.innerWidth <= 992) document.body.classList.toggle('mobile-nav-active');
                else document.body.classList.toggle('sidebar-collapsed');
            });
            
            document.querySelector('.backdrop').addEventListener('click', () => {
                document.body.classList.remove('mobile-nav-active');
            });
        });
    </script>
</body>
</html>