<?php
// ============================================================
//  CLASSROOM MONITORING — COSMOS
// ============================================================
session_start();
require '../db_connect.php';

// Prevent back-button caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// === SELF-CONTAINED LOGOUT FUNCTION ===
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
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

// Determine if user is Program Head
$isProgramHead = (stripos($userPosition, 'Program Head') !== false || stripos($userPosition, 'Coordinator') !== false);

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
    <title>Classroom Monitoring - COSMOS</title>
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
            
            /* HYBRID STATUS COLORS */
            --status-verified:    #3b82f6; /* Blue */
            --status-adhoc:       #f59e0b; /* Orange */
            --status-awaiting:    #94a3b8; /* Neutral Grey */
            --status-vacant:      #10b981; /* Green */
            --status-restricted:  #475569; /* Dark Grey */

            --shadow-card:        0 4px 20px rgba(0, 0, 0, 0.03); 
            --radius-card:        24px;
            --radius-pill:        99px; 
            --radius-input:       12px;
            --sidebar-min:        80px; 
            --sidebar-max:        260px;
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

        .logo-section { padding: 20px 10px; text-align: center; flex-shrink: 0; }
        .logo-section img { width: 50px; border-radius: 12px; display: block; margin: 0 auto; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar:hover .logo-section img { width: 140px; }

        .menu-label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin: 15px 0 5px; padding: 0 20px; white-space: nowrap; overflow: hidden; opacity: 0; max-height: 0; transition: opacity 0.2s ease, max-height 0.2s ease, padding 0.3s ease; }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; padding: 0 24px; }

        .nav-menu { list-style: none; padding: 0; margin: 0; } 
        .nav-menu li a { display: flex; align-items: center; justify-content: center; padding: 12px; margin: 4px 12px; color: var(--text-muted); text-decoration: none; font-size: 14.5px; font-weight: 600; border-radius: 12px; white-space: nowrap; overflow: hidden; transition: all 0.2s ease; }
        .sidebar:hover .nav-menu li a { padding: 12px 16px; margin: 4px 16px; justify-content: flex-start; }
        .nav-menu li a:hover  { background: #f8fafc; color: var(--primary-blue); }
        .nav-menu li a.active { background: var(--accent-blue); color: var(--primary-blue); }
        .nav-menu i { font-size: 18px; width: 24px; flex-shrink: 0; text-align: center; margin-right: 0; transition: margin-right 0.3s ease; }
        .sidebar:hover .nav-menu i { margin-right: 14px; }
        .nav-menu li a span { display: inline-block; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .nav-menu li a span { width: auto; opacity: 1; }

        /* Profile section */
        .profile-section { margin-top: auto; position: relative; padding: 36px 10px 16px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; }
        .dept-logo-bubble { position: absolute; top: -26px; left: 50%; transform: translateX(-50%); width: 50px; height: 50px; background: white; border-radius: 50%; border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12), 0 0 0 3px white; display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10; transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease; }
        .dept-logo-bubble::after { content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid white; filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.08)); }
        .sidebar:hover .dept-logo-bubble { width: 56px; height: 56px; top: -30px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15), 0 0 0 3px white; }
        .dept-logo-bubble img { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback { font-size: 20px; color: var(--primary-blue); }
        .profile-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0 2px; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; background: var(--primary-blue); color: white; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; object-fit: cover; border: 2px solid rgba(255,255,255,0.2); }
        .profile-info { flex: 1; min-width: 0; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .profile-info { width: auto; opacity: 1; }
        .profile-info h4 { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; overflow: hidden; text-overflow: ellipsis; }
        .profile-info p { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; }
        .logout-btn { flex-shrink: 0; width: 0; opacity: 0; overflow: hidden; background: transparent; border: none; color: var(--text-muted); font-size: 16px; padding: 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .sidebar:hover .logout-btn { width: auto; opacity: 1; }
        .logout-btn:hover { color: #ef4444; background: #fef2f2; }

        /* ════════════════════════════════════════════════════════
            MAIN CONTENT
        ════════════════════════════════════════════════════════ */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; margin-bottom: 4px; }
        .header-title p { font-size: 14px; font-weight: 400; color: var(--text-muted); }
        .container { flex: 1; overflow-y: auto; padding: 20px 40px 40px; }

        /* ════════════════════════════════════════════════════════
            PAGE HEADER & FILTERS
        ════════════════════════════════════════════════════════ */
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 30px; background: var(--bg-card); padding: 25px 30px; border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); }
        .page-title-group { display: flex; flex-direction: column; }
        .page-title { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 8px; }
        .current-date-display { font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.5px; line-height: 1.2; color: var(--primary-blue); }
        .current-time-display { font-size: 15px; font-weight: 700; color: var(--text-muted); margin-top: 4px; }

        .status-legend { display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px; background: #f8fafc; padding: 12px 25px; border-radius: var(--radius-pill); border: 1px solid var(--border-color); width: fit-content; }
        .legend-item { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .legend-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }

        .filter-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .filter-label { padding: 10px 20px; border-radius: var(--radius-pill); font-size: 12px; border: 1px solid var(--border-color); background: #f8fafc; color: var(--text-dark); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); }

        /* ════════════════════════════════════════════════════════
            MONITORING GRID & CARDS (Hybrid Layout)
        ════════════════════════════════════════════════════════ */
        .monitor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .mon-card { background: var(--bg-card); color: var(--text-dark); border-radius: 20px; padding: 25px 20px; text-align: center; box-shadow: var(--shadow-card); min-height: 320px; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; border: 1px solid var(--border-color); transition: all 0.2s ease; cursor: pointer; }
        .mon-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08); }

        .mon-card.state-verified { border-top: 6px solid var(--status-verified); background: #fff; }
        .mon-card.state-adhoc    { border-top: 6px solid var(--status-adhoc);    background: #fffaf0; }
        .mon-card.state-awaiting { border-top: 6px solid var(--status-awaiting); background: #f8fafc; opacity: 0.9; }
        .mon-card.state-vacant   { border-top: 6px solid var(--status-vacant);   background: #f8fafc; }
        .mon-card.state-restricted { border-top: 6px solid var(--status-restricted); background: #f1f5f9; opacity: 0.95; }

        .schedule-banner { width: 100%; font-size: 11px; font-weight: 700; color: var(--text-muted); background: #edf2f7; padding: 6px 10px; border-radius: 8px; margin-bottom: 15px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 6px;}
        .room-title { font-size: 24px; font-weight: 900; letter-spacing: -0.5px; color: var(--text-dark); margin-bottom: 10px; }
        .prof-avatar { width: 100px; height: 100px; border-radius: 50%; background: var(--primary-blue); object-fit: cover; border: 4px solid white; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: 800; box-shadow: 0 8px 16px rgba(0,0,0, 0.1); margin: 10px 0 15px; }
        .prof-name    { text-transform: uppercase; font-weight: 900; font-size: 15px; color: var(--text-dark); margin-bottom: 4px; }
        
        .time-text { display: inline-block; margin-top: 15px; padding: 8px 16px; border-radius: var(--radius-pill); font-size: 12px; font-weight: 800; color: var(--primary-blue); background: var(--accent-blue); }
        .no-class-text { font-size: 20px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); opacity: 0.6; margin-top: 30px; margin-bottom: 15px; line-height: 1.2; }

        .clickable-hint { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-top: 15px; letter-spacing: 0.5px; transition: color 0.2s; }
        .mon-card:hover .clickable-hint { color: var(--primary-blue); }

        /* ===== READ-ONLY SCHEDULE MODAL ===== */
        .schedule-modal-content { background-color: var(--bg-card); margin: 2% auto; padding: 35px 40px; border: none; width: 95%; max-width: 1200px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); position: relative; }
        .close-btn { color: var(--text-muted); float: right; font-size: 28px; font-weight: bold; cursor: pointer; margin-top: -10px; transition: 0.2s;}
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }
        .details-header-wrapper { display: flex; flex-direction: column; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px dashed var(--border-color); }
        .details-row { display: flex; align-items: center; gap: 30px; flex-wrap: wrap; justify-content: center; width: 100%; }
        .detail-group { display: flex; align-items: center; gap: 12px; }
        .detail-label { font-weight: 700; font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap; }
        .detail-pill { width: 180px; padding: 10px 15px; border-radius: var(--radius-pill); font-weight: 600; color: var(--text-dark); outline: none; text-align: center; font-size: 13px; border: 1px solid var(--border-color); background-color: #f8fafc; cursor: default; }
        .schedule-table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input); background: white; padding-bottom: 20px; transition: padding 0.3s ease; }
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: center; table-layout: fixed; word-wrap: break-word; }
        .schedule-table th { background-color: #f8fafc; color: var(--text-muted); font-weight: 700; padding: 12px 6px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; border-right: 1px solid var(--border-color); }
        .schedule-table th:last-child { border-right: none; }
        .schedule-table td { padding: 8px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); vertical-align: middle; color: var(--text-dark); font-size: 12px; }
        .schedule-table td:last-child { border-right: none; }
        .schedule-table tbody tr:last-child td { border-bottom: none; }
        .schedule-table tbody tr:hover td { background-color: #f8fafc; }
        .table-input { width: 100%; border: 1px solid var(--border-color); padding: 8px 6px; border-radius: 8px; outline: none; font-size: 11px; font-weight: 600; text-align: center; color: var(--text-dark); transition: 0.2s; box-sizing: border-box; background-color: #f1f5f9; cursor: not-allowed; }

        /* System Modals */
        .modal, .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 3000; backdrop-filter: blur(4px); align-items: center; justify-content: center; overflow: auto; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        /* Mobile backdrop */
        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }

        /* ════════════════════════════════════════════════════════
            RESPONSIVE
        ════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); }
            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }
            body.mobile-nav-active .sidebar { transform: translateX(0); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
            body.mobile-nav-active .sidebar .nav-menu li a              { justify-content: flex-start; padding: 12px 16px; margin: 4px 16px; }
            body.mobile-nav-active .sidebar .nav-menu i                { margin-right: 14px; }
            body.mobile-nav-active .sidebar .nav-menu li a span        { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .menu-label                { opacity: 1; max-height: 30px; padding: 0 24px; }
            body.mobile-nav-active .sidebar .profile-info              { width: auto; opacity: 1; margin-left: 12px; }
            body.mobile-nav-active .sidebar .logout-btn                { width: auto; opacity: 1; margin-left: auto; }
            body.mobile-nav-active .sidebar .logo-section img          { width: 100px; height: 100px; }

            .backdrop { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
            body.mobile-nav-active .backdrop { opacity: 1; pointer-events: auto; }
            #menu-toggle { display: block !important; }
            .page-header   { flex-direction: column; align-items: flex-start; }
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
            <li><a href="Dashboard_index.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <?php if (!$isFaculty): ?>
                <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i><span>Create Class</span></a></li>
            <?php endif; ?>
            <li><a href="Monitoring_index.php" class="active"><i class="fas fa-desktop"></i><span>Classroom Monitoring</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
                <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
                <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i><span>Faculty</span></a></li>
            <li><a href="Download_index.php"><i class="fas fa-file-download"></i><span>Download</span></a></li>
        </ul>

        <div class="profile-section">
            <div class="dept-logo-bubble" title="<?php echo htmlspecialchars($userDept); ?>">
                <?php if ($deptLogo): ?>
                    <img src="<?php echo htmlspecialchars($deptLogo); ?>" alt="<?php echo htmlspecialchars($userDept); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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
                    <p>Classroom Organization, Scheduling &amp; Monitoring Operations System</p>
                </div>
            </div>
        </header>

        <div class="container">

            <div class="page-header">
                <div class="page-title-group">
                    <div class="page-title">
                        <i class="fas fa-desktop" style="color:var(--primary-blue);"></i>
                        Classroom Monitoring
                        <?php if($isProgramHead): ?>
                            <span style="display:inline-flex; align-items:center; padding:4px 12px; border-radius:99px; font-size:11px; font-weight:700; color:white; background:var(--primary-blue); margin-left:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                                <?php echo htmlspecialchars($userProgram); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="current-date-display" id="dateDisplay">LOADING...</div>
                    <div class="current-time-display" id="clockDisplay">00:00:00 AM</div>

                    <div class="status-legend">
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-verified);"></div> Verified Occupied</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-adhoc);"></div> Ad-Hoc / Substitute</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-awaiting);"></div> Awaiting Scan</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-vacant);"></div> Vacant Room</div>
                        <?php if($isProgramHead): ?>
                            <div class="legend-item"><div class="legend-dot" style="background: var(--status-restricted);"></div> Restricted</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="filter-group">
                    <div id="lbl_ay" class="filter-label">
                        <i class="fas fa-calendar-alt" style="margin-right:6px;"></i> Loading AY...
                    </div>
                    <div id="lbl_sem" class="filter-label">
                        <i class="fas fa-book-open" style="margin-right:6px;"></i> Loading Sem...
                    </div>
                </div>
            </div>

            <div class="monitor-grid" id="monitorGrid">
                <p style="color:var(--text-muted); text-align:center; width:100%; grid-column:1/-1; padding:40px; font-weight:600;">
                    <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; display:block;"></i>
                    Scanning Hybrid Logs...
                </p>
            </div>

        </div>
    </div>

    <div id="modalBulk" class="modal">
        <div class="schedule-modal-content">
            <span class="close-btn" onclick="closeM('modalBulk')" title="Close Preview">&times;</span>
            <h2 style="text-align: center; color: var(--text-dark); margin-bottom: 25px; font-weight: 800; font-size: 22px;">Class Schedule Details</h2>

            <div class="details-header-wrapper">
                <div class="details-row">
                    <div class="detail-group"><span class="detail-label">DEPARTMENT:</span> <input id="b_dept" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">PROGRAM:</span> <input id="b_prog" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">YEAR LEVEL:</span> <input id="b_year" class="detail-pill" readonly></div>
                </div>
                <div class="details-row" style="margin-top: 15px;">
                    <div class="detail-group"><span class="detail-label">SEMESTER:</span> <input id="b_sem" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">SECTION:</span> <input id="b_sec" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">ACADEMIC YEAR:</span> <input id="b_ay" class="detail-pill" readonly></div>
                </div>
            </div>
            
            <div id="tableWrapper">
                <div class="schedule-table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">DAY</th>
                                <th style="width: 16%;">TIME</th>
                                <th style="width: 10%;">COURSE CODE</th>
                                <th style="width: 25%;">SUBJECT DESCRIPTION</th>
                                <th style="width: 4%;">UNIT</th>
                                <th colspan="2" style="width: 8%; padding: 5px;">HOURS/WEEK<br><span style="font-size:9px; font-weight:600; color:#64748b;">Lec &nbsp;|&nbsp; Lab</span></th>
                                <th style="width: 14%;">ROOM</th>
                                <th style="width: 16%;">PROFESSOR</th>
                            </tr>
                        </thead>
                        <tbody id="bulkRowsContainer">
                        </tbody>
                    </table>
                </div>
            </div>

            <div style="display: flex; justify-content: center; align-items: center; margin-top: 25px;">
                <button type="button" onclick="closeM('modalBulk')" style="background: white; color: var(--text-dark); border: 1px solid var(--border-color); padding: 12px 30px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);" onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.borderColor='#cbd5e1'; this.style.transform='translateY(-2px)';" onmouseout="this.style.backgroundColor='white'; this.style.borderColor='var(--border-color)'; this.style.transform='none';">
                    <i class="fas fa-times" style="color: #ef4444;"></i> CLOSE PREVIEW
                </button>
            </div>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <button id="sysModalAlertBtn" style="border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; width:100%; background: var(--primary-blue);">OK</button>
        </div>
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

        // ── Global state ──────────────────────────────────────
        let currentMonitorData = [];
        let allSchedules = [];
        let activeClassData = null; // Stores data of the class currently viewed in read-only modal
        let lastGridHTML = '';      // For flicker-free updating

        // ────────────────────────────────────────────────────
        // 1. Live clock
        // ────────────────────────────────────────────────────
        function updateClock() {
            const now = new Date();
            document.getElementById('dateDisplay').innerText =
                now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }).toUpperCase();
            document.getElementById('clockDisplay').innerText =
                now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ────────────────────────────────────────────────────
        // 2. Initialise
        // ────────────────────────────────────────────────────
        window.onload = async () => {
            await loadFilters();
            await fetchAllSchedules();
            loadMonitorData();
            // Fast polling for instant NFC detection updates
            setInterval(loadMonitorData, 3000);
        };

        async function loadFilters() {
            try {
                const data = await (await fetch('Monitoring_api.php?action=init_filters')).json();
                document.getElementById('lbl_ay').innerHTML  = `<i class="fas fa-calendar-alt" style="margin-right:6px;"></i> ${data.latest_ay  || 'N/A'}`;
                document.getElementById('lbl_sem').innerHTML = `<i class="fas fa-book-open"    style="margin-right:6px;"></i> ${data.latest_sem || 'N/A'}`;
            } catch (e) { console.error('Filter load error:', e); }
        }

        async function fetchAllSchedules() {
            try {
                const res = await fetch(`Monitoring_api.php?action=get_schedules`);
                allSchedules = await res.json();
            } catch (e) { console.error("Could not fetch global schedules:", e); }
        }

        // ────────────────────────────────────────────────────
        // 3. Monitor grid (Hybrid NFC Logic)
        // ────────────────────────────────────────────────────
        function generateInitials(name) {
            if (!name || name === 'TBA') return 'TBA';
            const parts = name.replace('PROF.', '').trim().split(' ');
            return parts.length > 1
                ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
                : parts[0][0].toUpperCase();
        }

        function formatTime(timeStr) {
            if (!timeStr) return '';
            const [h, m] = timeStr.split(':');
            const hour   = parseInt(h);
            const ampm   = hour >= 12 ? 'PM' : 'AM';
            return `${hour % 12 || 12}:${m} ${ampm}`;
        }

        // Handle Card Click for Schedule Drill-Down
        window.handleCardClick = function(index) {
            const item = currentMonitorData[index];
            if (item.state === 'Restricted') {
                sysAlert("Access Denied: This room is currently occupied or scheduled by a different program.", "fa-lock", "#ef4444");
                return;
            }
            if (item.state === 'Vacant') return; 

            if (!item.scheduled_class) {
                sysAlert("This is an unscheduled Ad-Hoc class. No specific schedule details are available.", "fa-info-circle", "#f59e0b");
                return;
            }
            
            activeClassData = item.scheduled_class;
            openReadOnlyModal();
        }

        async function loadMonitorData() {
            const grid = document.getElementById('monitorGrid');

            try {
                const data = await (await fetch('Monitoring_api.php')).json();
                currentMonitorData = data;
                let newHTML = '';

                // Empty state
                if (!data.length) {
                    grid.style.display              = 'flex';
                    grid.style.alignItems           = 'center';
                    grid.style.justifyContent       = 'center';
                    grid.style.gridTemplateColumns  = 'none';
                    grid.innerHTML = `
                        <div style="text-align:center; padding:80px 20px; color:var(--text-muted); width:100%; border:1px dashed var(--border-color); border-radius:var(--radius-card); background:var(--bg-card);">
                            <i class="fas fa-door-closed" style="font-size:60px; color:#cbd5e1; margin-bottom:20px;"></i>
                            <p style="font-size:20px; color:var(--text-dark); font-weight:800;">No Rooms Registered</p>
                            <p style="font-size:14px; margin-top:10px; font-weight:500;">
                                There are no rooms assigned to your department yet.
                            </p>
                        </div>`;
                    return;
                }

                data.forEach((item, index) => {
                    const room = item.room_info;
                    const sched = item.scheduled_class;
                    const actual = item.actual_occupant;
                    const state = item.state;
                    
                    let stateClass = `state-${state.toLowerCase().replace('-', '')}`;
                    let onClickAttr = `onclick="handleCardClick(${index})"`;
                    
                    let cardContent = `<div class="room-title">${room.room_name}</div>`;

                    if (state === 'Restricted') {
                        cardContent += `
                            <div class="schedule-banner" style="background:var(--bg-main); color:var(--status-restricted);"><i class="fas fa-lock"></i> Outside Jurisdiction</div>
                            <div class="prof-avatar" style="background: #e2e8f0; border-color: #cbd5e1; color: var(--status-restricted);"><i class="fas fa-user-secret"></i></div>
                            <div class="prof-name" style="color: var(--status-restricted);">RESTRICTED</div>
                            <div style="font-size:11px; color:var(--text-muted); font-weight:700; margin-bottom:12px; text-transform:uppercase;">RESTRICTED ACTIVITY</div>
                            <span style="background:var(--status-restricted); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:800; letter-spacing:1px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">ACCESS DENIED</span>
                            <div class="time-text" style="color: var(--text-dark); background: #f1f5f9;">
                                <i class="fas fa-eye-slash" style="margin-right:4px;"></i> Details Hidden
                            </div>
                        `;
                    } 
                    else {
                        // Hybrid Layout Logic
                        if (sched) {
                            let sProf = (sched.full_name || 'TBA').toUpperCase();
                            if (sProf !== 'TBA' && !sProf.startsWith('PROF.')) sProf = 'PROF. ' + sProf;
                            cardContent += `<div class="schedule-banner" title="Expected: ${sched.descriptive_title}"><i class="fas fa-calendar-alt"></i> Sched: ${sched.course_code} | ${sProf}</div>`;
                        } else {
                            cardContent += `<div class="schedule-banner" style="background:transparent; opacity:0; user-select:none;">spacer</div>`;
                        }

                        if (state === 'Verified' || state === 'Ad-Hoc') {
                            let isGuest = actual.is_guest === true;
                            let profName = (actual.full_name || 'UNKNOWN').toUpperCase();
                            
                            if (!isGuest && !profName.startsWith('PROF.')) {
                                profName = 'PROF. ' + profName;
                            }

                            const avatarHtml = actual.photo
                                ? `<img class="prof-avatar" src="data:image/jpeg;base64,${actual.photo}" alt="Prof">`
                                : `<div class="prof-avatar" style="${isGuest ? 'background:#3b82f6;' : ''}">${generateInitials(profName)}</div>`;

                            let statusBadge = state === 'Ad-Hoc' 
                                ? `<span style="background:var(--status-adhoc); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:800; letter-spacing:1px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">AD-HOC / SUB</span>` 
                                : `<span style="background:var(--status-verified); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:800; letter-spacing:1px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">VERIFIED</span>`;

                            let timeDisplay = '';
                            if (sched) {
                                timeDisplay = `<i class="far fa-clock" style="margin-right:4px;"></i> ${formatTime(sched.start_time)} – ${formatTime(sched.end_time)}`;
                            } else {
                                timeDisplay = `<i class="fas fa-exclamation-circle" style="margin-right:4px;"></i> Unscheduled Use`;
                            }

                            cardContent += `
                                ${avatarHtml}
                                <div class="prof-name" style="${isGuest ? 'font-size:14px; color:#1e3a8a;' : ''}">${profName}</div>
                                <div style="font-size:10px; color:var(--text-muted); font-weight:700; margin-bottom:12px; text-transform:uppercase;">
                                    ${isGuest ? '<span style="color:#3b82f6;"><i class="fas fa-user-graduate"></i> STUDENT</span><br>' : ''}
                                    ${actual.prof_department}
                                </div>
                                ${statusBadge}
                                <div class="time-text">
                                    ${timeDisplay}
                                </div>
                                ${sched ? '<div class="clickable-hint"><i class="fas fa-search-plus"></i> Click for details</div>' : ''}
                            `;
                        } else if (state === 'Awaiting') {
                            let profName = (sched.full_name || 'UNKNOWN').toUpperCase();
                            if (!profName.startsWith('PROF.')) profName = 'PROF. ' + profName;

                            const avatarHtml = sched.photo
                                ? `<img class="prof-avatar" style="opacity: 0.6; border-color: var(--border-color);" src="data:image/jpeg;base64,${sched.photo}" alt="Prof">`
                                : `<div class="prof-avatar" style="opacity: 0.6; background: var(--bg-main); color: var(--text-muted); border-color: var(--border-color);">${generateInitials(profName)}</div>`;

                            cardContent += `
                                ${avatarHtml}
                                <div class="prof-name" style="color:var(--text-muted);">${profName}</div>
                                <div style="font-size:11px; color:var(--text-muted); font-weight:700; margin-bottom:12px; text-transform:uppercase;">SCHEDULED INSTRUCTOR</div>
                                <span style="background:var(--status-awaiting); color:white; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:800; letter-spacing:1px; box-shadow:0 2px 4px rgba(0,0,0,0.1);">AWAITING SCAN</span>
                                <div class="time-text" style="color:var(--text-dark); background:#e2e8f0; margin-top:15px;">
                                    <i class="far fa-clock" style="margin-right:4px;"></i> ${formatTime(sched.start_time)} – ${formatTime(sched.end_time)}
                                </div>
                                <div class="clickable-hint"><i class="fas fa-search-plus"></i> Click for details</div>
                            `;
                        } else {
                            cardContent += `
                                <div class="prof-avatar" style="background:var(--bg-main); border-color:var(--border-color); color:var(--status-vacant);"><i class="fas fa-door-open"></i></div>
                                <div class="no-class-text" style="margin-top:0;">Vacant</div>
                                <div class="time-text" style="color:var(--status-vacant); background:#ecfdf5;">
                                    <i class="fas fa-check-circle" style="margin-right:4px;"></i> Available
                                </div>
                            `;
                            onClickAttr = ''; // Remove clickability from vacant cards
                        }
                    }

                    newHTML += `<div class="mon-card ${stateClass}" ${onClickAttr}>${cardContent}</div>`;
                });

                if (lastGridHTML !== newHTML) {
                    grid.style.display = 'grid';
                    grid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(280px, 1fr))';
                    grid.innerHTML = newHTML;
                    lastGridHTML = newHTML;
                }

            } catch (e) {
                console.error(e);
            }
        }

        // ────────────────────────────────────────────────────
        // 4. Read-Only Schedule Modal (Drill Down)
        // ────────────────────────────────────────────────────
        async function openReadOnlyModal() {
            if (!activeClassData) return;
            const d = activeClassData;

            document.getElementById('b_dept').value = d.department || '<?php echo $userDept; ?>';
            document.getElementById('b_prog').value = d.program;
            document.getElementById('b_year').value = d.year_level;
            document.getElementById('b_sem').value = d.semester;
            document.getElementById('b_sec').value = d.class_section || d.section;
            document.getElementById('b_ay').value = d.academic_year;

            const res = await fetch(`Monitoring_api.php?action=find_courses&program=${encodeURIComponent(d.program)}&year_level=${encodeURIComponent(d.year_level)}&semester=${encodeURIComponent(d.semester)}`);
            const courses = await res.json();

            const sectionSchedules = allSchedules.filter(s => 
                s.program === d.program && 
                s.year_level === d.year_level && 
                s.section === (d.class_section || d.section) && 
                s.academic_year === d.academic_year &&
                s.semester === d.semester
            );

            renderReadOnlyRows(courses, sectionSchedules);
            document.getElementById('modalBulk').style.display = 'block';
        }

        function renderReadOnlyRows(coursesArray, existingSchedules = []) {
            const container = document.getElementById('bulkRowsContainer');
            container.innerHTML = '';
            
            let globalIndex = 0; 

            coursesArray.forEach((c) => {
                const matchingSchedules = existingSchedules.filter(s => s.course_id == c.id);

                if (matchingSchedules.length > 0) {
                    matchingSchedules.forEach((dObj) => {
                        const customLec = dObj.lecture_hours !== null ? parseFloat(dObj.lecture_hours) : parseFloat(c.lecture_hours || 0);
                        const customLab = dObj.laboratory_hours !== null ? parseFloat(dObj.laboratory_hours) : parseFloat(c.laboratory_hours || 0);
                        const customUnits = dObj.units !== null ? parseFloat(dObj.units) : parseFloat(c.units || 0);
                        container.insertAdjacentHTML('beforeend', createReadOnlyRowString(c, globalIndex, dObj, customLec, customLab, customUnits));
                        globalIndex++;
                    });
                } else {
                    container.insertAdjacentHTML('beforeend', createReadOnlyRowString(c, globalIndex, null));
                    globalIndex++;
                }
            });
        }

        function createReadOnlyRowString(c, i, dObj = null, lec = null, lab = null, units = null) {
            lec = lec !== null ? lec : parseFloat(c.lecture_hours || 0);
            lab = lab !== null ? lab : parseFloat(c.laboratory_hours || 0);
            units = units !== null ? units : parseFloat(c.units || 0);
            
            let startVal = dObj && dObj.start_time ? formatTimeStr(dObj.start_time) : "";
            let endVal = dObj && dObj.end_time ? formatTimeStr(dObj.end_time) : "";
            let rText = dObj ? (dObj.room_name || 'TBA') : ""; 
            
            let fText = dObj ? dObj.professor : "";
            if (fText !== 'TBA' && fText !== 'General Education' && !fText.startsWith('PROF.') && !fText.startsWith('⏳')) {
                fText = 'PROF. ' + fText;
            }

            let daysText = dObj && dObj.days ? dObj.days : "";
            let pendingBadgeHtml = dObj && dObj.approval_status === 'Pending' ? `<div style="margin-top:4px; font-size:9px; font-weight:800; color:#d97706; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block;">⏳ PENDING</div>` : '';

            return `
                <tr>
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="text" class="table-input" value="${daysText}" disabled placeholder="No Day">
                    </td>
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="text" class="table-input" style="margin-bottom: 6px;" value="${startVal}" disabled placeholder="Start Time">
                        <input type="text" class="table-input" value="${endVal}" disabled placeholder="End Time">
                    </td>
                    <td style="text-align: center; vertical-align: middle;"><strong style="color:var(--primary-blue); font-size:12px;">${c.course_code}</strong></td>
                    <td style="text-align:left; font-weight:600; font-size: 11px; padding-left: 8px; line-height: 1.2; vertical-align: middle;">
                        ${c.descriptive_title}
                        <br>${pendingBadgeHtml}
                    </td>
                    <td style="text-align: center; vertical-align: middle;"><strong>${units}</strong></td>
                    <td style="border-right: 1px dashed var(--border-color); width:4%; text-align: center; vertical-align: middle;">${lec}</td>
                    <td style="width:4%; text-align: center; vertical-align: middle;">${lab}</td>
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="text" class="table-input" value="${rText}" disabled placeholder="No Room">
                    </td>
                    <td style="text-align: center; vertical-align: middle;">
                        <input type="text" class="table-input" value="${fText}" disabled placeholder="No Professor">
                    </td>
                </tr>
            `;
        }

        function formatTimeStr(timeStr) {
            if (!timeStr) return "";
            let [h, m] = timeStr.split(':');
            h = parseInt(h);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12; h = h ? h : 12;
            return `${h}:${m} ${ampm}`;
        }

        function closeM(id) {
            document.getElementById(id).style.display = 'none'; 
        }

        // ── Navigation toggle ─────────────────────────────────
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });

        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });
    </script>

</body>
</html>