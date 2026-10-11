<?php
// ============================================================
//  CLASS MONITORING — COSMOS
// ============================================================
session_start();

// Prevent back-button caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");

// ----- Logout -----------------------------------------------
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
    header('Location: ../Login/Login_index.php');
    exit();
}

// ----- Auth guard -------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login/Login_index.php');
    exit();
}

// ----- User data --------------------------------------------
$userName     = $_SESSION['full_name']       ?? '';
$userPosition = $_SESSION['position']        ?? 'Staff';
$userDept     = $_SESSION['department']      ?? 'Department';
$userPic      = $_SESSION['profile_picture'] ?? null;

// Determine if Dean/Admin for Force Vacate Permission
$isDean = (stripos($userPosition, 'Dean') !== false);
$isAdmin = (stripos($userPosition, 'Admin') !== false);
$canForceVacate = ($isDean || $isAdmin);

// Clean name
$cleanName = str_ireplace(['null', 'NULL'], '', $userName);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
if (empty($cleanName) || $cleanName === '.') {
    $cleanName = 'Unknown User';
}

// Build initials
$nameParts = explode(' ', $cleanName);
if ($cleanName !== 'Unknown User') {
    $initials = count($nameParts) > 1
        ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
        : strtoupper(substr($nameParts[0], 0, 1));
} else {
    $initials = 'UU';
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
    <title>Class Monitoring - COSMOS</title>
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
            
            /* NEW HYBRID STATUS COLORS */
            --status-verified:    #3b82f6; /* Blue */
            --status-adhoc:       #f59e0b; /* Orange */
            --status-awaiting:    #94a3b8; /* Neutral Grey */
            --status-vacant:      #10b981; /* Green */
            
            --border-color:       #e2e8f0;
            --shadow-card:        0 4px 20px rgba(0, 0, 0, 0.03);
            --radius-card:        24px;
            --radius-pill:        99px;
            --radius-input:       12px;
            --sidebar-min:        72px;
            --sidebar-max:        260px;
        }

        /* ── Reset ───────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: var(--bg-main); color: var(--text-dark); overflow: hidden; }

        /* ── SIDEBAR ─────────────────────────────────────────── */
        .sidebar { width: var(--sidebar-min); background: var(--bg-card); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; flex-shrink: 0; overflow: hidden; z-index: 1000; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar:hover { width: var(--sidebar-max); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08); }
        .logo-section { padding: 20px 10px; text-align: center; flex-shrink: 0; }
        .logo-section img { width: 50px; border-radius: 12px; display: block; margin: 0 auto; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar:hover .logo-section img { width: 140px; }
        
        .menu-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin: 10px 0 4px; padding: 0 20px; white-space: nowrap; overflow: hidden; opacity: 0; max-height: 0; transition: opacity 0.2s ease, max-height 0.2s ease; }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; }
        
        .nav-menu { list-style: none; padding: 0; }
        .nav-menu li a { display: flex; align-items: center; justify-content: center; padding: 11px 0; margin: 3px 10px; color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 500; border-radius: var(--radius-input); white-space: nowrap; overflow: hidden; transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.2s; }
        .sidebar:hover .nav-menu li a { padding: 11px 14px; justify-content: flex-start; }
        .nav-menu li a:hover  { background: #f8fafc; color: var(--primary-blue); }
        .nav-menu li a.active { background: var(--accent-blue); color: var(--primary-blue); font-weight: 600; }
        .nav-menu i { font-size: 18px; width: 22px; flex-shrink: 0; text-align: center; margin-right: 0; transition: margin-right 0.3s ease; }
        .sidebar:hover .nav-menu i { margin-right: 12px; }
        .nav-menu li a span { display: inline-block; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .nav-menu li a span { width: auto; opacity: 1; }

        .profile-section { margin-top: auto; position: relative; padding: 36px 10px 16px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; }
        .dept-logo-bubble { position: absolute; top: -26px; left: 50%; transform: translateX(-50%); width: 50px; height: 50px; background: white; border-radius: 50%; border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12), 0 0 0 3px white; display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10; transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease; }
        .dept-logo-bubble::after { content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid white; filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.08)); }
        .sidebar:hover .dept-logo-bubble { width: 56px; height: 56px; top: -30px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15), 0 0 0 3px white; }
        .dept-logo-bubble img { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback { font-size: 20px; color: var(--primary-blue); }
        
        .profile-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0 2px; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; background: var(--primary-blue); color: white; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; object-fit: cover; }
        .profile-info { flex: 1; min-width: 0; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .profile-info { width: auto; opacity: 1; }
        .profile-info h4 { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; overflow: hidden; text-overflow: ellipsis; }
        .profile-info p { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; }
        .logout-btn { flex-shrink: 0; width: 0; opacity: 0; overflow: hidden; background: transparent; border: none; color: var(--text-muted); font-size: 16px; padding: 8px; border-radius: 6px; cursor: pointer; transition: all 0.2s ease; }
        .sidebar:hover .logout-btn { width: auto; opacity: 1; }
        .logout-btn:hover { color: #ef4444; background: #fef2f2; }

        /* ── MAIN CONTENT ────────────────────────────────────── */
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px; display: flex; justify-content: space-between; align-items: center; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; margin-bottom: 4px; }
        .header-title p { font-size: 14px; font-weight: 400; color: var(--text-muted); }
        .container { flex: 1; overflow-y: auto; padding: 20px 40px 40px; }

        /* ── PAGE HEADER & FILTERS ───────────────────────────── */
        .page-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 20px; margin-bottom: 30px; background: var(--bg-card); padding: 25px 30px; border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); }
        .page-title-group { display: flex; flex-direction: column; }
        .page-title { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin-bottom: 8px; }
        .current-date-display { font-size: 22px; font-weight: 900; text-transform: uppercase; letter-spacing: -0.5px; line-height: 1.2; color: var(--primary-blue); }
        .current-time-display { font-size: 15px; font-weight: 700; color: var(--text-muted); margin-top: 4px; }
        
        .status-legend { display: flex; gap: 20px; margin-top: 20px; background: #f8fafc; padding: 12px 25px; border-radius: var(--radius-pill); border: 1px solid var(--border-color); width: fit-content; flex-wrap: wrap;}
        .legend-item { display: flex; align-items: center; gap: 10px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .legend-dot { width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        
        .filter-group { display: flex; gap: 12px; }
        .filter-label { padding: 10px 20px; border-radius: var(--radius-pill); font-size: 12px; border: 1px solid var(--border-color); background: #f8fafc; color: var(--text-dark); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); }

        /* ── MONITORING GRID & CARDS ─────────────────────────── */
        .monitor-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        
        .mon-card { cursor: pointer; background: var(--bg-card); color: var(--text-dark); border-radius: 20px; padding: 25px 20px; text-align: center; box-shadow: var(--shadow-card); min-height: 320px; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; border: 1px solid var(--border-color); transition: all 0.2s ease; }
        .mon-card:hover { transform: translateY(-5px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08); }

        .mon-card.state-verified { border-top: 6px solid var(--status-verified); background: #fff; }
        .mon-card.state-adhoc    { border-top: 6px solid var(--status-adhoc);    background: #fffaf0; }
        .mon-card.state-awaiting { border-top: 6px solid var(--status-awaiting); background: #f8fafc; opacity: 0.9; }
        .mon-card.state-vacant   { border-top: 6px solid var(--status-vacant);   background: #f8fafc; }

        .schedule-banner { width: 100%; font-size: 11px; font-weight: 700; color: var(--text-muted); background: #edf2f7; padding: 6px 10px; border-radius: 8px; margin-bottom: 15px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 6px;}
        .room-title { font-size: 24px; font-weight: 900; letter-spacing: -0.5px; color: var(--text-dark); margin-bottom: 10px; }
        .prof-avatar { width: 100px; height: 100px; border-radius: 50%; background: var(--primary-blue); object-fit: cover; border: 4px solid white; display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; font-weight: 800; box-shadow: 0 8px 16px rgba(0,0,0, 0.1); margin: 10px 0 15px; }

        .prof-name    { text-transform: uppercase; font-weight: 900; font-size: 15px; color: var(--text-dark); margin-bottom: 4px; }
        .class-details { font-size: 12px; line-height: 1.5; font-weight: 600; color: var(--text-muted); }
        .time-text { display: inline-block; margin-top: 15px; padding: 8px 16px; border-radius: var(--radius-pill); font-size: 12px; font-weight: 800; color: var(--primary-blue); background: var(--accent-blue); }
        .no-class-text { font-size: 20px; font-weight: 900; text-transform: uppercase; color: var(--text-muted); opacity: 0.6; margin-top: 30px; margin-bottom: 15px; line-height: 1.2; }

        .force-vacate-btn { margin-top: 15px; background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: var(--radius-pill); font-size: 11px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); transition: all 0.2s ease; text-transform: uppercase; letter-spacing: 0.5px; z-index: 10; position: relative;}
        .force-vacate-btn:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.3); }

        /* ── MODALS AND TABLES FOR LOGS ──────────────────────── */
        .modal { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1001; align-items: center; justify-content: center; backdrop-filter: blur(4px); padding: 20px;}
        .modal-content { background: var(--bg-card); width: 100%; max-width: 850px; border-radius: var(--radius-card); padding: 30px 40px; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 90vh; display: flex; flex-direction: column;}
        .close-btn { position: absolute; right: 25px; top: 20px; font-size: 28px; font-weight: bold; color: var(--text-muted); cursor: pointer; transition: 0.2s; background: none; border: none; padding: 5px;}
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }
        
        .custom-input { padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-input); font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark); outline: none; transition: 0.2s; }
        .custom-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }

        .schedule-table-wrapper { overflow-y: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input); background: white; margin-top: 15px; flex: 1;}
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: left; }
        .schedule-table th { background: #f8fafc; color: var(--text-muted); font-weight: 700; padding: 14px 15px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; position: sticky; top: 0; z-index: 2;}
        .schedule-table td { padding: 14px 15px; border-bottom: 1px solid var(--border-color); vertical-align: middle; color: var(--text-dark); font-size: 12px; font-weight: 500; }
        .schedule-table tbody tr:last-child td { border-bottom: none; }
        .schedule-table tbody tr:hover td { background: #f8fafc; }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }
        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; width: 100%;}

        /* Mobile backdrop */
        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }

        /* ── RESPONSIVE ──────────────────────────────────────── */
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); }
            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }
            body.mobile-nav-active .sidebar { transform: translateX(0); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
            body.mobile-nav-active .sidebar .nav-menu li a              { justify-content: flex-start; padding: 11px 14px; }
            body.mobile-nav-active .sidebar .nav-menu i                { margin-right: 12px; }
            body.mobile-nav-active .sidebar .nav-menu li a span        { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .menu-label                { opacity: 1; max-height: 30px; }
            body.mobile-nav-active .sidebar .profile-info              { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logout-btn                { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logo-section img          { width: 140px; }
            .backdrop { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
            body.mobile-nav-active .backdrop { opacity: 1; pointer-events: auto; }
            #menu-toggle { display: block !important; }
            .page-header   { flex-direction: column; align-items: flex-start; }
            .modal-content { padding: 25px 20px; }
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
            <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i><span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php" class="active"><i class="fas fa-desktop"></i><span>Classroom Monitoring</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i><span>Faculty</span></a></li>
            <li><a href="Room_index.php"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
            <li><a href="Approval_index.php"><i class="fas fa-check-circle"></i><span>Approval</span></a></li>
            <li><a href="Download_index.php"><i class="fas fa-file-download"></i><span>Download</span></a></li>
            <li><a href="History_index.php"><i class="fas fa-history"></i><span>History</span></a></li>
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

                    <?php if (!empty($userPic)): ?>
                        <img src="<?php echo $userPic; ?>"
                             class="profile-img"
                             alt="Profile Picture"
                             style="border: 2px solid rgba(255,255,255,0.2);">
                    <?php else: ?>
                        <div class="profile-img"
                             style="border: 2px solid rgba(255,255,255,0.2);">
                            <?php echo htmlspecialchars($initials); ?>
                        </div>
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
                    <p>Classroom Organization, Scheduling and Monitoring Operations System</p>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-header">
                <div class="page-title-group">
                    <div class="page-title">
                        <i class="fas fa-desktop" style="color:var(--primary-blue);"></i>
                        Live Room Monitoring
                    </div>
                    <div class="current-date-display" id="dateDisplay">LOADING...</div>
                    <div class="current-time-display" id="clockDisplay">00:00:00 AM</div>

                    <div class="status-legend">
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-verified);"></div> Verified Occupied</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-adhoc);"></div> Ad-Hoc / Substitute</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-awaiting);"></div> Awaiting Professor</div>
                        <div class="legend-item"><div class="legend-dot" style="background: var(--status-vacant);"></div> Vacant Room</div>
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
                    Scanning Facility Data...
                </p>
            </div>
        </div>
    </div>

    <div id="logsModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeLogsModal()">&times;</button>
            <h2 id="logsModalTitle" style="color: var(--text-dark); margin-bottom: 20px; font-weight: 800; font-size: 22px;">Room Logs</h2>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;">
                <div style="flex: 1; position: relative; min-width: 200px;">
                    <i class="fas fa-search" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="logSearch" placeholder="Search by name, dept, or program..." class="custom-input" style="width: 100%; padding-left: 40px;">
                </div>
                <input type="date" id="logDate" class="custom-input" style="width: auto;" title="Filter by Date">
            </div>
            
            <div class="schedule-table-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th>Occupant</th>
                            <th>College / Program</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <tr><td colspan="5" style="text-align: center; padding: 30px;"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <button id="sysModalAlertBtn" class="btn-modal" style="background: var(--primary-blue);">OK</button>
        </div>
    </div>

    <div id="sysModalConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalConfirmIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalConfirmMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <div class="sys-modal-btns">
                <button id="sysModalConfirmCancelBtn" class="btn-modal" style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalConfirmOkBtn" class="btn-modal" style="flex:1; background: var(--primary-blue);">Confirm</button>
            </div>
        </div>
    </div>

    <script>
        const CAN_FORCE_VACATE = <?php echo $canForceVacate ? 'true' : 'false'; ?>;

        // ────────────────────────────────────────────────────
        // 1. SYSTEM MODAL HELPERS
        // ────────────────────────────────────────────────────
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

        // ────────────────────────────────────────────────────
        // 2. Live clock
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
        // 3. Initialise & Set Live Checking Speed
        // ────────────────────────────────────────────────────
        window.onload = async () => {
            await loadFilters();
            loadMonitorData();
            
            // Checks the database every 5 seconds for updates
            setInterval(loadMonitorData, 5000); 
        };

        async function loadFilters() {
            try {
                const data = await (await fetch('Monitoring_api.php?action=init_filters')).json();
                document.getElementById('lbl_ay').innerHTML  = `<i class="fas fa-calendar-alt" style="margin-right:6px;"></i> ${data.latest_ay  || 'N/A'}`;
                document.getElementById('lbl_sem').innerHTML = `<i class="fas fa-book-open"    style="margin-right:6px;"></i> ${data.latest_sem || 'N/A'}`;
            } catch (e) { console.error('Filter load error:', e); }
        }

        // ────────────────────────────────────────────────────
        // 4. Monitor grid & Flicker-Free Updating
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

        let lastGridHTML = '';

        async function loadMonitorData() {
            const grid = document.getElementById('monitorGrid');

            try {
                const data = await (await fetch('Monitoring_api.php')).json();
                let newHTML = ''; 

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
                                There are no physical rooms assigned to your department yet.
                            </p>
                        </div>`;
                    return;
                }

                data.forEach(item => {
                    const room = item.room_info;
                    const sched = item.scheduled_class;
                    const actual = item.actual_occupant;
                    const state = item.state;
                    
                    let stateClass = `state-${state.toLowerCase().replace('-', '')}`;
                    let cardContent = `<div class="room-title">${room.room_name}</div>`;

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
                            </div>`;

                        // Add FORCE VACATE button if user is Dean/Admin
                        if (CAN_FORCE_VACATE) {
                            cardContent += `<button class="force-vacate-btn" onclick="forceVacate(event, '${room.room_name}')"><i class="fas fa-sign-out-alt"></i> Force Vacate</button>`;
                        }

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
                            </div>`;
                            
                    } else {
                        cardContent += `
                            <div class="prof-avatar" style="background:var(--bg-main); border-color:var(--border-color); color:var(--status-vacant);"><i class="fas fa-door-open"></i></div>
                            <div class="no-class-text" style="margin-top:0;">Vacant</div>
                            <div class="time-text" style="color:var(--status-vacant); background:#ecfdf5;">
                                <i class="fas fa-check-circle" style="margin-right:4px;"></i> Available
                            </div>`;
                    }

                    newHTML += `<div class="mon-card ${stateClass}">${cardContent}</div>`;
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
        // 5. Force Vacate Logic (Dean/Admin Only)
        // ────────────────────────────────────────────────────
        async function forceVacate(event, roomName) {
            // Stop the click from opening the Room Logs modal
            event.stopPropagation();
            
            const proceed = await sysConfirm(`Are you sure you want to forcibly vacate <strong>${roomName}</strong>?`, 'fa-exclamation-triangle', '#ef4444');
            if (!proceed) return;

            const fd = new FormData();
            fd.append('action', 'force_vacate');
            fd.append('room_name', roomName);

            try {
                const res = await fetch('Monitoring_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                
                if (result.status === 'success') {
                    // Refresh instantly
                    loadMonitorData();
                } else {
                    await sysAlert(result.message, 'fa-times-circle', '#ef4444');
                }
            } catch (err) {
                console.error(err);
                await sysAlert("Error connecting to server. Please try again.", 'fa-wifi', '#ef4444');
            }
        }

        // ────────────────────────────────────────────────────
        // 6. Room Logs Modal & Filter Logic
        // ────────────────────────────────────────────────────
        let currentLogsData = [];

        document.getElementById('monitorGrid').addEventListener('click', function(e) {
            const card = e.target.closest('.mon-card');
            if (!card) return;
            
            // If they clicked the force vacate button, do nothing here.
            if (e.target.closest('.force-vacate-btn')) return;
            
            const roomName = card.querySelector('.room-title').innerText;
            openLogsModal(roomName);
        });

        async function openLogsModal(roomName) {
            document.getElementById('logsModalTitle').innerText = `${roomName} - 7 Day Activity Log`;
            document.getElementById('logsTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; display:block;"></i> Fetching records...</td></tr>';
            document.getElementById('logsModal').style.display = 'flex';
            document.getElementById('logSearch').value = '';
            document.getElementById('logDate').value = '';
            
            try {
                const res = await fetch(`Monitoring_api.php?action=get_room_logs&room_name=${encodeURIComponent(roomName)}`);
                currentLogsData = await res.json();
                renderLogs();
            } catch (err) {
                document.getElementById('logsTableBody').innerHTML = '<tr><td colspan="5" style="text-align:center; color:#ef4444; padding:30px;"><i class="fas fa-exclamation-triangle"></i> Failed to load logs.</td></tr>';
            }
        }

        function renderLogs() {
            const search = document.getElementById('logSearch').value.toLowerCase();
            const dateFilter = document.getElementById('logDate').value;
            const tbody = document.getElementById('logsTableBody');
            
            let html = '';
            let count = 0;
            
            currentLogsData.forEach(log => {
                const matchSearch = log.name.toLowerCase().includes(search) || log.department.toLowerCase().includes(search);
                const matchDate = dateFilter === '' || log.raw_date === dateFilter;
                
                if (matchSearch && matchDate) {
                    let statusPill = log.status === 'Active' 
                        ? `<span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase;">Active</span>`
                        : `<span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase;">Completed</span>`;

                    html += `
                        <tr>
                            <td style="font-weight: 700; color: var(--text-dark);">${log.name}</td>
                            <td style="color: var(--text-muted); font-size: 11px;">${log.department || 'N/A'}</td>
                            <td>${log.time_in}</td>
                            <td style="color: ${log.status === 'Active' ? '#10b981' : 'inherit'}; font-weight: ${log.status === 'Active' ? '700' : 'normal'};">${log.time_out}</td>
                            <td>${statusPill}</td>
                        </tr>
                    `;
                    count++;
                }
            });
            
            if (count === 0) {
                html = `<tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);"><i class="fas fa-search" style="font-size:30px; color:#cbd5e1; margin-bottom:15px; display:block;"></i> No records found matching your filters.</td></tr>`;
            }
            tbody.innerHTML = html;
        }

        document.getElementById('logSearch').addEventListener('input', renderLogs);
        document.getElementById('logDate').addEventListener('change', renderLogs);

        function closeLogsModal() {
            document.getElementById('logsModal').style.display = 'none';
        }

        // Close modal if clicked outside
        document.getElementById('logsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogsModal();
            }
        });

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