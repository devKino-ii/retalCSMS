<?php
session_start();
require '../db_connect.php';

// === 1-CLICK DATABASE BACKUP LOGIC (STRUCTURE + DATA) ===
if (isset($_GET['action']) && $_GET['action'] == 'backup') {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="COSMOS_Backup_' . date('Y-m-d_H-i') . '.sql"');
    
    $sqlScript = "-- COSMOS Database Full Backup (Structure & Data)\n";
    $sqlScript .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n"; // Disable FK checks during import

    // 1. Get all tables dynamically from the database
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    foreach ($tables as $table) {
        $sqlScript .= "-- --------------------------------------------------------\n";
        $sqlScript .= "-- Table structure for `$table`\n";
        $sqlScript .= "-- --------------------------------------------------------\n\n";
        
        // 2. Add DROP TABLE and CREATE TABLE structure
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        $createTableRes = $conn->query("SHOW CREATE TABLE `$table`");
        $createTableRow = $createTableRes->fetch_row();
        $sqlScript .= $createTableRow[1] . ";\n\n";

        // 3. Add INSERT data
        $resultData = $conn->query("SELECT * FROM `$table`");
        if($resultData && $resultData->num_rows > 0) {
            $sqlScript .= "-- Dumping data for table `$table`\n\n";
            while ($row = $resultData->fetch_row()) {
                $rowVals = [];
                foreach ($row as $val) {
                    $rowVals[] = isset($val) ? "'" . $conn->real_escape_string($val) . "'" : "NULL";
                }
                $sqlScript .= "INSERT INTO `$table` VALUES(" . implode(", ", $rowVals) . ");\n";
            }
            $sqlScript .= "\n";
        }
    }
    
    $sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n"; // Re-enable FK checks
    echo $sqlScript;
    exit();
}

// === SELF-CONTAINED LOGOUT FUNCTION ===
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

// Admin Sidebar Info
$adminName = $_SESSION['full_name'] ?? 'System Admin';
$adminPosition = $_SESSION['position'] ?? 'Administrator';
$adminPic = $_SESSION['profile_picture'] ?? null;
$cleanAdminName = trim(str_ireplace(['null', 'NULL'], '', $adminName)) ?: 'System Admin';

// FIXED INITIALS GENERATION TO PREVENT PHP NOTICE
$nameParts = explode(' ', $cleanAdminName);
if (count($nameParts) > 1) {
    $adminInitials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
} else {
    $adminInitials = strtoupper(substr($cleanAdminName, 0, 1));
}

// ==========================================
// DATA FETCHING FOR WIDGETS AND CHARTS
// ==========================================

// 1. Total Users
$userRes = $conn->query("SELECT COUNT(*) as count FROM users WHERE department != 'System'");
$totalUsers = $userRes->fetch_assoc()['count'] ?? 0;

// 2. Global Rooms
$roomRes = $conn->query("SELECT COUNT(*) as count FROM rooms");
$totalRooms = $roomRes->fetch_assoc()['count'] ?? 0;

// 3. Rooms Under Maintenance
$maintRes = $conn->query("SELECT * FROM rooms WHERE status = 'Under Maintenance'");
$maintRooms = $maintRes ? $maintRes->fetch_all(MYSQLI_ASSOC) : [];
$maintCount = count($maintRooms);

// 4. Room Utilization by College (Chart Data)
$deptLabels = [];
$deptSchedules = [];
$utilQuery = $conn->query("SELECT department, COUNT(id) as total_schedules FROM class_schedules GROUP BY department ORDER BY total_schedules DESC");
if($utilQuery) {
    while($row = $utilQuery->fetch_assoc()){
        $deptLabels[] = str_replace('College of ', '', $row['department']); // Shorten names for chart
        $deptSchedules[] = $row['total_schedules'];
    }
}

// 5. Peak Hours (Chart Data)
$hourLabels = [];
$hourCounts = [];
$peakQuery = $conn->query("SELECT substring(start_time, 1, 5) as hour_block, COUNT(id) as count FROM class_schedules GROUP BY hour_block ORDER BY hour_block ASC");
if($peakQuery) {
    while($row = $peakQuery->fetch_assoc()){
        $timeObj = DateTime::createFromFormat('H:i', $row['hour_block']);
        $hourLabels[] = $timeObj ? $timeObj->format('g:i A') : $row['hour_block'];
        $hourCounts[] = $row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
        }

        /* ── Reset ───────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-main); color: var(--text-dark); display: flex; height: 100vh; overflow: hidden; }

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
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0;}
        header { flex-shrink: 0; background: transparent; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        
        .container { padding: 0 40px 40px 40px; display: flex; flex-direction: column; gap: 20px; flex: 1; overflow-y: auto; }
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; }

        /* ===== TOP WIDGETS ROW ===== */
        .widgets-row { display: flex; gap: 20px; flex-shrink: 0; flex-wrap: wrap; }
        .widget-card { background: var(--bg-card); border-radius: var(--radius-card); padding: 25px 30px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); flex: 1; display: flex; flex-direction: column; justify-content: center; min-width: 200px; transition: all 0.2s ease;}
        .widget-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.05); border-color: var(--primary-blue);}
        
        .stat-title { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 8px; letter-spacing: 1px; }
        .stat-value { font-size: 38px; font-weight: 900; color: var(--text-dark); display: flex; justify-content: space-between; align-items: center; line-height: 1; }
        .stat-value i { font-size: 28px; color: var(--primary-blue); opacity: 0.8; }

        /* ===== DASHBOARD GRID ===== */
        .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        
        .chart-card { background: var(--bg-card); border-radius: var(--radius-card); padding: 25px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); }
        .chart-header { font-size: 14px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; display: flex; justify-content: space-between;}
        
        /* Maintenance List */
        .maint-list { display: flex; flex-direction: column; gap: 10px; }
        .maint-item { background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; padding: 12px; border-radius: 8px; display: flex; flex-direction: column; gap: 4px;}
        .maint-room { font-size: 14px; font-weight: 800; color: #b91c1c; }
        .maint-dept { font-size: 11px; color: #991b1b; font-weight: 600;}

        /* Quick Actions */
        .btn-action { background: var(--primary-blue); color: white; border: none; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 13px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%; margin-bottom: 10px; text-decoration: none;}
        .btn-action:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27,80,136,0.2); color: white;}
        .btn-backup { background: #10b981; }
        .btn-backup:hover { background: #059669; box-shadow: 0 6px 15px rgba(16,185,129,0.2); }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
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

            #menu-toggle { display: block !important; }
            .dashboard-grid { grid-template-columns: 1fr; }
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
            <li><a href="Dashboard_index.php" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
            <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i> <span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i><span>Class Monitoring</span></a></li>
        </ul>

            <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="User_Account_index.php"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
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

                        <?php if ($adminPic): ?>
                            <img src="<?php echo $adminPic; ?>" class="profile-img" alt="Profile Picture" style="border: 2px solid rgba(255,255,255,0.2);">
                        <?php else: ?>
                            <div class="profile-img" style="border: 2px solid rgba(255,255,255,0.2);"><?php echo htmlspecialchars($adminInitials); ?></div>
                        <?php endif; ?>
                        <div class="profile-info">
                            <h4><?php echo htmlspecialchars($cleanAdminName); ?></h4>
                            <p><?php echo htmlspecialchars($adminPosition); ?></p>
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
            <div class="page-title"><i class="fas fa-satellite-dish" style="color: var(--primary-blue); margin-right: 8px;"></i> System Overview</div>

            <div class="widgets-row">
                <div class="widget-card">
                    <div class="stat-title">Total Registered Users</div>
                    <div class="stat-value"><span><?php echo $totalUsers; ?></span> <i class="fas fa-users"></i></div>
                </div>
                <div class="widget-card">
                    <div class="stat-title">Global Room Count</div>
                    <div class="stat-value"><span><?php echo $totalRooms; ?></span> <i class="fas fa-door-open"></i></div>
                </div>
                <div class="widget-card" style="border-color: <?php echo $maintCount > 0 ? '#fecaca' : 'var(--border-color)'; ?>; background: <?php echo $maintCount > 0 ? '#fef2f2' : 'var(--bg-card)'; ?>;">
                    <div class="stat-title" style="color: <?php echo $maintCount > 0 ? '#b91c1c' : 'var(--text-muted)'; ?>;">Rooms Under Maintenance</div>
                    <div class="stat-value"><span style="color: <?php echo $maintCount > 0 ? '#ef4444' : 'var(--text-dark)'; ?>;"><?php echo $maintCount; ?></span> <i class="fas fa-tools" style="color: <?php echo $maintCount > 0 ? '#ef4444' : 'var(--primary-blue)'; ?>;"></i></div>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <div class="chart-card">
                        <div class="chart-header">
                            <span><i class="fas fa-chart-bar" style="color:var(--primary-blue); margin-right:5px;"></i> Class Density by College</span>
                        </div>
                        <canvas id="utilChart" height="100"></canvas>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <span><i class="fas fa-clock" style="color:var(--primary-blue); margin-right:5px;"></i> Peak Hours (Campus-Wide)</span>
                        </div>
                        <canvas id="peakChart" height="100"></canvas>
                    </div>
                </div>

                <div style="display: flex; flex-direction: column; gap: 20px;">
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <span><i class="fas fa-bolt" style="color:#d97706; margin-right:5px;"></i> Quick Actions</span>
                        </div>
                        <a href="User_Account_index.php" class="btn-action"><i class="fas fa-user-plus"></i> Register New User</a>
                        <a href="#" id="btnBackup" class="btn-action btn-backup"><i class="fas fa-database"></i> 1-Click Database Backup</a>
                        <a href="History_index.php" class="btn-action" style="background: white; color: var(--text-dark); border: 1px solid var(--border-color);"><i class="fas fa-history"></i> View Global History</a>
                    </div>

                    <div class="chart-card" style="flex: 1;">
                        <div class="chart-header" style="color: #b91c1c; border-bottom-color: #fecaca;">
                            <span><i class="fas fa-tools" style="margin-right:5px;"></i> Maintenance Overview</span>
                            <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px;"><?php echo $maintCount; ?> Active</span>
                        </div>
                        
                        <?php if($maintCount > 0): ?>
                            <div class="maint-list">
                                <?php foreach($maintRooms as $room): ?>
                                    <div class="maint-item">
                                        <span class="maint-room"><?php echo htmlspecialchars($room['room_name']); ?></span>
                                        <span class="maint-dept"><i class="fas fa-building" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($room['department']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 30px 0;">
                                <i class="fas fa-check-circle" style="font-size: 30px; color: #10b981; margin-bottom: 10px;"></i>
                                <p style="font-weight: 600; font-size: 13px;">All clear! No rooms are currently under maintenance.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

            </div>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <button id="sysModalAlertBtn" class="btn-modal" style="width:100%; background: var(--primary-blue);">OK</button>
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
        // =========================================================
        // Promise-Based System Modals
        // =========================================================
        function sysAlert(msg, icon = 'fa-info-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysModalAlertMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalAlertIcon');
                iconEl.className = `fas ${icon} sys-modal-icon`;
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
                iconEl.className = `fas ${icon} sys-modal-icon`;
                iconEl.style.color = color;
                
                const okBtn = document.getElementById('sysModalConfirmOkBtn');
                if(color === '#ef4444') {
                    okBtn.style.backgroundColor = '#ef4444';
                } else {
                    okBtn.style.backgroundColor = 'var(--primary-blue)';
                }

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

        // --- Sidebar Toggle ---
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        // --- Intercept 1-Click Database Backup ---
        document.getElementById('btnBackup').addEventListener('click', async function(e) {
            e.preventDefault();
            const proceed = await sysConfirm("Are you sure you want to generate and download a full backup of the COSMOS database?", "fa-database", "#10b981");
            if (proceed) {
                window.location.href = '?action=backup';
            }
        });

        // Initialize Chart.js Graphs
        document.addEventListener("DOMContentLoaded", function() {
            
            // 1. Utilization Chart
            const utilCtx = document.getElementById('utilChart').getContext('2d');
            new Chart(utilCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($deptLabels); ?>,
                    datasets: [{
                        label: 'Total Active Schedules',
                        data: <?php echo json_encode($deptSchedules); ?>,
                        backgroundColor: '#3b82f6',
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4] } }, x: { grid: { display: false } } }
                }
            });

            // 2. Peak Hours Chart
            const peakCtx = document.getElementById('peakChart').getContext('2d');
            new Chart(peakCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($hourLabels); ?>,
                    datasets: [{
                        label: 'Classes Starting',
                        data: <?php echo json_encode($hourCounts); ?>,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true, grid: { borderDash: [2, 4] } }, x: { grid: { display: false } } }
                }
            });
        });
    </script>
</body>
</html>