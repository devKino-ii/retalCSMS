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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Activity History - COSMOS</title>
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
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }

        .container { flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px; display: flex; flex-direction: column; gap: 20px; }
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; text-transform: uppercase; margin-bottom: 5px; display: flex; align-items: center; gap: 15px;}

        /* ===== CONTROLS & FILTERS ===== */
        .controls-container { 
            background: var(--bg-card); padding: 25px 30px; border-radius: var(--radius-card); 
            box-shadow: var(--shadow-card); border: 1px solid var(--border-color); margin-bottom: 5px;
            display: flex; gap: 15px; flex-wrap: wrap; align-items: center;
        }
        
        .search-box { flex: 1; position: relative; min-width: 250px; }
        .search-box input {
            width: 100%; padding: 12px 16px 12px 45px; border: 1px solid var(--border-color);
            border-radius: var(--radius-pill); font-size: 13px; font-weight: 500; outline: none; transition: all 0.2s ease;
            background: #f8fafc; color: var(--text-dark);
        }
        .search-box input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .search-box i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; }

        .filter-container { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-btn {
            padding: 12px 18px; border: 1px solid var(--border-color); border-radius: var(--radius-pill);
            font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark);
            cursor: pointer; outline: none; transition: all 0.2s ease;
            appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; padding-right: 35px;
        }
        .filter-btn:focus, .filter-btn:hover { border-color: var(--primary-blue); background-color: white; }

        /* ===== READ-ONLY DATA TABLE ===== */
        .table-container { 
            background-color: var(--bg-card); 
            border-radius: var(--radius-card); 
            box-shadow: var(--shadow-card); 
            border: 1px solid var(--border-color); 
            
            /* Fill the remaining vertical space */
            flex: 1; 
            min-height: 0; /* Crucial for flexbox scrolling to work properly */
            
            /* Enable scrolling */
            overflow-y: auto;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        th { 
            background-color: #f8fafc; 
            color: var(--text-muted); 
            padding: 16px 20px; 
            font-weight: 700; 
            border-bottom: 1px solid var(--border-color); 
            text-transform: uppercase; 
            font-size: 11px; 
            letter-spacing: 0.5px; 
            white-space: nowrap;
            
            /* Stick the header to the top */
            position: sticky;
            top: 0;
            z-index: 10;
        }
        td { 
            padding: 16px 20px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); 
            font-size: 13px; font-weight: 500; vertical-align: middle;
        }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f8fafc; }

        /* Action Badges */
        .badge { display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 800; letter-spacing: 0.5px; }
        .badge-create { background-color: #dcfce7; color: #166534; }
        .badge-update { background-color: #dbeafe; color: #1e40af; }
        .badge-delete { background-color: #fee2e2; color: #991b1b; }

        .time-col { color: var(--text-muted); font-size: 12px; white-space: nowrap; }
        .user-col { font-weight: 700; color: var(--primary-blue); }
        .dept-col { font-size: 11px; color: #475569; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: 700;}

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 14px; text-align: center; font-weight: 600; margin-bottom: 20px; outline: none; }
        .sys-modal-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; }

        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
        
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); z-index: 1000;}
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
            <li><a href="User_Account_index.php"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
            <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Room_index.php"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
            <li><a href="History_index.php" class="active"><i class="fas fa-history"></i> <span>Global History</span></a></li>
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
            <div class="page-title">
                <i class="fas fa-globe" style="color: var(--primary-blue);"></i> GLOBAL ACTIVITY LOGS
            </div>

            <div class="controls-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by User or Details...">
                </div>
                <div class="filter-container">
                    <select id="filterDept" class="filter-btn" style="font-weight: 700; color: var(--primary-blue);">
                        <option value="All">All Departments</option>
                        <option value="College of Science">College of Science</option>
                        <option value="College of Engineering">College of Engineering</option>
                        <option value="College of Industrial Technology">College of Industrial Technology</option>
                        <option value="College of Education">College of Education</option>
                    </select>
                    
                    <select id="filterModule" class="filter-btn">
                        <option value="All">All Modules</option>
                        <option value="Course">Course</option>
                        <option value="Room">Room</option>
                        <option value="Faculty">Faculty</option>
                        <option value="Schedule">Schedule</option>
                        <option value="Approval">Approval</option>
                        <option value="Profile">Profile</option>
                    </select>
                    
                    <select id="filterAction" class="filter-btn">
                        <option value="All">All Actions</option>
                        <option value="CREATE">Created</option>
                        <option value="UPDATE">Updated</option>
                        <option value="DELETE">Deleted</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Department</th>
                            <th>User Name</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Activity Details</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr><td colspan="6" style="text-align:center; padding: 40px; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading logs...</td></tr>
                    </tbody>
                </table>
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

        // Sidebar Toggle
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        // Format MySQL timestamp into readable format
        function formatDateTime(dateStr) {
            const d = new Date(dateStr);
            const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            return d.toLocaleDateString('en-US', options);
        }

        // Get Badge Style
        function getBadge(action) {
            if(action === 'CREATE') return '<span class="badge badge-create"><i class="fas fa-plus"></i> CREATE</span>';
            if(action === 'UPDATE') return '<span class="badge badge-update"><i class="fas fa-edit"></i> UPDATE</span>';
            if(action === 'DELETE') return '<span class="badge badge-delete"><i class="fas fa-trash"></i> DELETE</span>';
            return `<span class="badge" style="background:#e2e8f0; color:#334155;">${action}</span>`;
        }

        async function fetchLogs() {
            const search = document.getElementById('searchInput').value;
            const module = document.getElementById('filterModule').value;
            const action = document.getElementById('filterAction').value;
            const dept   = document.getElementById('filterDept').value; // Admin Specific
            
            const tbody = document.getElementById('tableBody');

            try {
                const res = await fetch(`History_api.php?search=${encodeURIComponent(search)}&module=${encodeURIComponent(module)}&action=${encodeURIComponent(action)}&department=${encodeURIComponent(dept)}`);
                const data = await res.json();
                
                tbody.innerHTML = '';
                
                if (data.length === 0) {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:60px 20px; color:var(--text-muted);">
                        <i class="fas fa-folder-open" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px; display:block;"></i>
                        No activity logs found.
                    </td></tr>`;
                    return;
                }

                data.forEach(log => {
                    // Extract short department name for cleaner view
                    let shortDept = log.department ? log.department.replace('College of ', '') : 'System';

                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class="time-col">${formatDateTime(log.created_at)}</td>
                        <td><span class="dept-col">${shortDept}</span></td>
                        <td class="user-col">${log.user_name}</td>
                        <td style="font-weight: 600;"><i class="fas fa-cube" style="color:var(--text-muted); margin-right:5px;"></i> ${log.module}</td>
                        <td>${getBadge(log.action)}</td>
                        <td>${log.details}</td>
                    `;
                    tbody.appendChild(tr);
                });

            } catch (error) {
                console.error("Error:", error);
                tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 40px; color: #ef4444;"><i class="fas fa-exclamation-triangle"></i> Error loading logs.</td></tr>`;
            }
        }

        // Event Listeners for Live Filtering
        document.getElementById('searchInput').addEventListener('keyup', fetchLogs);
        document.getElementById('filterModule').addEventListener('change', fetchLogs);
        document.getElementById('filterAction').addEventListener('change', fetchLogs);
        document.getElementById('filterDept').addEventListener('change', fetchLogs);

        // Initial Load
        window.onload = fetchLogs;
    </script>
</body>
</html>