<?php
session_start();
include '../db_connect.php'; 

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = array(); 
    session_destroy();   
    header("Location: ../Login/Login_index.php"); 
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login_index.php");
    exit();
}

// Fetch user info from database to ensure accuracy for security
$userId = $_SESSION['user_id'];
$stmtUser = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->bind_param("i", $userId);
$stmtUser->execute();
$userData = $stmtUser->get_result()->fetch_assoc();

$userName = $_SESSION['full_name'] ?? 'Unknown User';
$userDept = $userData['department'] ?? 'Department';
$userPosition = $userData['position'] ?? 'Staff';
$userProgram = $userData['program'] ?? '';
$userPic = $userData['profile_picture'] ?? null;

$isProgramHead = ($userPosition === 'Program Head');
$isFaculty = ($userPosition === 'Faculty');

$cleanName = trim(str_ireplace(['null', 'NULL'], '', $userName));
if (empty($cleanName) || $cleanName === '.') $cleanName = 'Unknown User';

$nameParts = explode(' ', $cleanName);
$initials = count($nameParts) > 1 ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1)) : strtoupper(substr($nameParts[0], 0, 1));

if (!empty($userPic)) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userPic);
} else {
    $picSrc = null;
}

// Department color mapping
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

// Fetch Academic Years Dynamically (RESTRICTED BASED ON ROLE)
$ay_options = "";

if ($isFaculty) {
    // Faculty only see years where they have a schedule
    $ay_query = "SELECT DISTINCT academic_year FROM class_schedules WHERE faculty_id = ? ORDER BY academic_year DESC";
    $stmt = $conn->prepare($ay_query);
    $stmt->bind_param("i", $userId);
} else {
    // Program Heads/Admin see years for their department/program
    $ay_query = "SELECT DISTINCT academic_year FROM class_schedules WHERE department = ?";
    if ($isProgramHead && !empty($userProgram)) {
        $ay_query .= " AND program = ?";
    }
    $ay_query .= " ORDER BY academic_year DESC";
    
    $stmt = $conn->prepare($ay_query);
    if ($isProgramHead && !empty($userProgram)) {
        $stmt->bind_param("ss", $userDept, $userProgram);
    } else {
        $stmt->bind_param("s", $userDept);
    }
}

$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    while($row = $res->fetch_assoc()) {
        $ay_val = htmlspecialchars($row['academic_year']);
        $ay_options .= "<option value=\"$ay_val\">$ay_val</option>";
    }
} else {
    $ay_options = "<option value=\"\" disabled selected>No schedules found</option>";
}
$stmt->close();

// Fetch ALL schedules for this specific faculty member to populate the A.1 Preview dynamically
$sched_query = $conn->prepare("
    SELECT s.academic_year, s.semester, s.start_time, s.end_time, s.days, s.section, s.units, s.lecture_hours, s.laboratory_hours,
           c.course_code, c.descriptive_title, c.program, r.room_name 
    FROM class_schedules s 
    JOIN courses c ON s.course_id = c.id 
    LEFT JOIN rooms r ON s.room_id = r.id 
    WHERE s.faculty_id = ? AND s.approval_status = 'Approved'
");
$sched_query->bind_param("i", $userId);
$sched_query->execute();
$faculty_schedules = $sched_query->get_result()->fetch_all(MYSQLI_ASSOC);
$sched_query->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Reports - COSMOS</title>
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
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        
        .container { padding: 20px 40px 40px 40px; flex: 1; overflow-y: auto; }
        
        /* ── Tabs & Form ────────────────────────── */
        .tabs { display: flex; gap: 10px; margin-bottom: 25px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px; }
        .tab-btn { background: none; border: none; font-size: 15px; font-weight: 700; color: var(--text-muted); cursor: pointer; padding: 10px 20px; transition: 0.2s; text-transform: uppercase; letter-spacing: 0.5px; }
        .tab-btn:hover { color: var(--primary-blue); }
        .tab-btn.active { color: var(--primary-blue); border-bottom: 3px solid var(--primary-blue); margin-bottom: -12px; }

        .download-card { background: var(--bg-card); border-radius: var(--radius-card); padding: 40px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); max-width: 1000px; margin: 0 auto; }
        .form-group { margin-bottom: 20px; }
        .form-row { display: flex; gap: 20px; margin-bottom: 15px;}
        .form-row .form-group { flex: 1; margin-bottom: 0;}
        
        .form-group label { display: block; font-weight: 700; font-size: 12px; color: var(--text-muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-control { width: 100%; padding: 12px 18px; border: 1px solid var(--border-color); border-radius: var(--radius-input); font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark); outline: none; transition: 0.2s; appearance: none; }
        .form-control:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        select.form-control { background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; }
        
        .format-selector { display: flex; gap: 15px; }
        .format-option { flex: 1; position: relative; }
        .format-option input { display: none; }
        .format-box { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; border: 2px solid var(--border-color); border-radius: var(--radius-input); cursor: pointer; font-weight: 700; color: var(--text-muted); transition: 0.2s; }
        .format-option input:checked + .format-box { border-color: var(--primary-blue); background: var(--accent-blue); color: var(--primary-blue); }
        
        .btn-download { display: block; width: 100%; background: var(--primary-blue); color: white; border: none; padding: 16px; border-radius: var(--radius-pill); font-weight: 800; font-size: 14px; cursor: pointer; transition: 0.2s; margin-top: 30px; text-transform: uppercase; letter-spacing: 1px; }
        .btn-download:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(27, 80, 136, 0.2); }

        .workload-extra { display: none; background: #ffffff; padding: 25px; border-radius: var(--radius-input); margin-top: 20px; border: 1px solid var(--border-color); }
        .section-heading { font-size: 14px; font-weight: 800; color: var(--primary-blue); margin-top: 30px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid var(--border-color); padding-bottom: 5px;}
        
        .toggle-switch { display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 700; color: var(--primary-blue); margin-top: 20px;}
        .toggle-switch input { display: none; }
        .slider { width: 44px; height: 24px; background: var(--border-color); border-radius: 34px; position: relative; transition: 0.3s; }
        .slider::before { content: ""; position: absolute; width: 18px; height: 18px; border-radius: 50%; background: white; top: 3px; left: 3px; transition: 0.3s; }
        .toggle-switch input:checked + .slider { background: var(--primary-blue); }
        .toggle-switch input:checked + .slider::before { transform: translateX(20px); }

        /* Dynamic Table Styles */
        .dyn-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .dyn-table th { background: #f8fafc; font-size: 11px; text-transform: uppercase; color: var(--text-muted); padding: 10px 8px; border: 1px solid var(--border-color); text-align: left; }
        .dyn-table td { padding: 4px; border: 1px solid var(--border-color); }
        .dyn-table input { width: 100%; border: none; outline: none; padding: 8px 6px; font-size: 12px; background: transparent; font-weight: 500; color: var(--text-dark);}
        .dyn-table input:focus { background: var(--accent-blue); }
        .btn-add-row { background: var(--accent-blue); color: var(--primary-blue); border: 1px dashed var(--primary-blue); padding: 8px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: 0.2s;}
        .btn-add-row:hover { background: var(--primary-blue); color: white; border-style: solid; }
        .btn-remove { background: #fee2e2; color: #ef4444; border: none; padding: 8px; border-radius: 6px; cursor: pointer; font-size: 12px; transition: 0.2s; }
        .btn-remove:hover { background: #ef4444; color: white; }

        /* A.1 Read Only Table */
        #a1-preview-table td { background: #f8fafc; font-size: 12px; padding: 10px 8px; color: var(--text-dark); font-weight: 500; }
        .tc { text-align: center !important; }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; }

        /* Mobile responsive */
        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }
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
            .form-row { flex-direction: column; gap: 0; }
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
            <li><a href="Dashboard_index.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="Download_index.php" class="active"><i class="fas fa-file-download"></i> <span>Download</span></a></li>
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
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('general')">Schedule Reports</button>
                <button class="tab-btn" onclick="switchTab('workload')">My Faculty Workload</button>
            </div>

            <div class="download-card" id="form-general">
                <h2 style="margin-bottom: 25px; color: var(--text-dark);">Download Master Schedules</h2>
                <form action="Download_action.php" method="POST" target="_blank" onsubmit="return handleGeneralSubmit(this);">
                    <input type="hidden" name="report_type" value="general">
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($userDept); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select name="academic_year" class="form-control" required><?php echo $ay_options; ?></select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" class="form-control" required>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Sort Report By</label>
                        <select name="sort_by" class="form-control" required>
                            <option value="Faculty">My Schedule</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-top: 30px;">
                        <label>Select Format</label>
                        <div class="format-selector">
                            <label class="format-option">
                                <input type="radio" name="format" value="excel" checked>
                                <div class="format-box"><i class="fas fa-file-excel" style="color: #10b981; font-size: 20px;"></i> MS Excel</div>
                            </label>
                            <label class="format-option">
                                <input type="radio" name="format" value="pdf">
                                <div class="format-box"><i class="fas fa-file-pdf" style="color: #ef4444; font-size: 20px;"></i> PDF Document</div>
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn-download"><i class="fas fa-download" style="margin-right: 8px;"></i> Generate Download</button>
                </form>
            </div>

            <div class="download-card" id="form-workload" style="display: none; max-width: 1000px;">
                <h2 style="margin-bottom: 5px; color: var(--text-dark);">Official Faculty Workload</h2>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 25px;">Your official teaching load will be automatically retrieved and populated into the URS-AA-F-05 format.</p>
                
                <form action="Download_action.php" method="POST" target="_blank" onsubmit="return handleWorkloadSubmit(this);">
                    <input type="hidden" name="report_type" value="workload">
                    <input type="hidden" name="format" value="pdf"> 
                    <input type="hidden" name="department" value="<?php echo htmlspecialchars($userDept); ?>">
                    <input type="hidden" name="faculty_id" value="<?php echo htmlspecialchars($userId); ?>">
                    <input type="hidden" name="faculty_name" value="<?php echo htmlspecialchars($cleanName); ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Academic Year</label>
                            <select id="wl_ay" name="academic_year" class="form-control" required><?php echo $ay_options; ?></select>
                        </div>
                        <div class="form-group">
                            <label>Semester</label>
                            <select id="wl_sem" name="semester" class="form-control" required>
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>

                    <div class="section-heading" style="margin-top: 20px; font-size: 12px; color: var(--text-muted);">A.1 Instruction Preview (Read-Only)</div>
                    <div style="overflow-x: auto;">
                        <table class="dyn-table" id="a1-preview-table">
                            <thead>
                                <tr><th>Time</th><th class="tc">Day</th><th class="tc">Course Code</th><th>Descriptive Title</th><th class="tc">Lec</th><th class="tc">Lab</th><th class="tc">Units</th><th class="tc">Room</th><th class="tc">Sec</th></tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>

                    <label class="toggle-switch">
                        <input type="checkbox" id="fillOnlineToggle" name="fill_online" value="yes" onchange="toggleWorkloadExtra()">
                        <span class="slider"></span>
                        Fill out additional form details online
                    </label>

                    <div class="workload-extra" id="workloadExtraFields">
                        
                        <div class="section-heading" style="margin-top: 0;">Personal & Employment Info</div>
                        <div class="form-row">
                            <div class="form-group"><label>Sex</label>
                                <select name="sex" class="form-control"><option value="Male">Male</option><option value="Female">Female</option></select>
                            </div>
                            <div class="form-group"><label>Employee ID No.</label><input type="text" name="emp_id" class="form-control"></div>
                            <div class="form-group"><label>Academic Rank</label><input type="text" name="acad_rank" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Nature of Appt.</label><input type="text" name="nature_appt" class="form-control"></div>
                            <div class="form-group" style="display:flex; flex-direction:row; align-items:center; gap:20px; padding-top:20px;">
                                <label style="display:flex; align-items:center; gap:8px; text-transform:none; margin:0; cursor:pointer; font-weight:600; color:var(--text-dark);">
                                    <input type="checkbox" name="vsl" value="yes" style="width:18px; height:18px; cursor:pointer; accent-color: var(--primary-blue);"> VSL (Vacation/Sick Leave)
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; text-transform:none; margin:0; cursor:pointer; font-weight:600; color:var(--text-dark);">
                                    <input type="checkbox" name="tl" value="yes" style="width:18px; height:18px; cursor:pointer; accent-color: var(--primary-blue);"> TL (Teacher's Leave)
                                </label>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Monthly Salary</label><input type="text" name="salary_monthly" class="form-control"></div>
                            <div class="form-group"><label>Annual Salary</label><input type="text" name="salary_annual" class="form-control"></div>
                            <div class="form-group"><label>Salary Grade & Step</label><input type="text" name="salary_grade_step" class="form-control" placeholder="e.g. SG 12 Step 1"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Years in URS</label><input type="text" name="years_urs" class="form-control"></div>
                            <div class="form-group"><label>Years Teaching in URS</label><input type="text" name="years_teaching" class="form-control"></div>
                            <div class="form-group"><label>Other School (Years)</label><input type="text" name="years_other" class="form-control"></div>
                        </div>

                        <div class="section-heading">Degrees & Eligibility</div>
                        <div class="form-row">
                            <div class="form-group"><label>Undergraduate Degree</label><input type="text" name="deg_undergrad" class="form-control"></div>
                            <div class="form-group"><label>Major</label><input type="text" name="ug_major" class="form-control"></div>
                            <div class="form-group"><label>Minor</label><input type="text" name="ug_minor" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Masteral Degree</label><input type="text" name="deg_masteral" class="form-control"></div>
                            <div class="form-group"><label>Major</label><input type="text" name="ma_major" class="form-control"></div>
                            <div class="form-group"><label>Minor</label><input type="text" name="ma_minor" class="form-control"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Doctoral Degree</label><input type="text" name="deg_doctoral" class="form-control"></div>
                            <div class="form-group"><label>Eligibility</label><input type="text" name="eligibility" class="form-control"></div>
                            <div class="form-group"><label>Year Taken</label><input type="text" name="eligibility_year" class="form-control"></div>
                        </div>
                        <div class="form-group"><label>Special Training/ Scholarship/ Study Grant</label><input type="text" name="special_training" class="form-control"></div>

                        <div class="section-heading">A.2 INSTRUCTION</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-a2" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="30%">Type of Activity</th><th width="25%">Target</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="a2_time[]"></td><td><input type="text" name="a2_day[]"></td>
                                        <td><input type="text" name="a2_act[]"></td><td><input type="text" name="a2_target[]"></td>
                                        <td><input type="text" name="a2_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-a2')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">B. ADMIN / OFFICE WORK DESIGNATION</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-b" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="35%">Designation</th><th width="25%">Place</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="b_time[]"></td><td><input type="text" name="b_day[]"></td>
                                        <td><input type="text" name="b_desig[]"></td><td><input type="text" name="b_place[]"></td>
                                        <td><input type="text" name="b_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-b')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">C. RESEARCH / INVENTION / CREATIVE WORK</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-c" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="35%">Activity / Topic</th><th width="25%">Assignment Target</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="c_time[]"></td><td><input type="text" name="c_day[]"></td>
                                        <td><input type="text" name="c_topic[]"></td><td><input type="text" name="c_target[]"></td>
                                        <td><input type="text" name="c_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-c')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">D. EXTENSION / PRODUCTION</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-d" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="35%">Type of Extension</th><th width="25%">Target</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="d_time[]"></td><td><input type="text" name="d_day[]"></td>
                                        <td><input type="text" name="d_type[]"></td><td><input type="text" name="d_target[]"></td>
                                        <td><input type="text" name="d_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-d')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">E. PROFESSIONAL DEVELOPMENT</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-e" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="35%">Type of Prof. Dev</th><th width="25%">Target</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="e_time[]"></td><td><input type="text" name="e_day[]"></td>
                                        <td><input type="text" name="e_type[]"></td><td><input type="text" name="e_target[]"></td>
                                        <td><input type="text" name="e_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-e')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">OFFICIAL TIME (ON-SITE and OFF-SITE)</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" style="min-width: 700px;">
                                <thead><tr><th width="15%">Day</th><th>AM</th><th>Break</th><th>PM</th><th>Hrs On-site</th><th>Sched On-site</th><th>Hrs Off-site</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Adjustment'];
                                    foreach($days as $day): ?>
                                    <tr>
                                        <td style="font-size: 11px; font-weight: 700; background: #f8fafc; padding-left:10px; color: var(--text-dark);"><?php echo $day; ?></td>
                                        <td><input type="text" name="ot_am[]"></td><td><input type="text" name="ot_brk[]"></td>
                                        <td><input type="text" name="ot_pm[]"></td><td><input type="text" name="ot_onsite_hrs[]"></td>
                                        <td><input type="text" name="ot_onsite_sched[]"></td><td><input type="text" name="ot_offsite_hrs[]"></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="section-heading">F. URS GRADUATE SCHOOL TEACHING LOAD</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-f" style="min-width: 800px;">
                                <thead><tr><th>Time</th><th>Day</th><th>Course Code</th><th>Title</th><th>Units/Hrs</th><th>Room</th><th>Sec</th><th>Size</th><th></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="f_time[]"></td><td><input type="text" name="f_day[]"></td>
                                        <td><input type="text" name="f_code[]"></td><td><input type="text" name="f_title[]"></td>
                                        <td><input type="text" name="f_units[]"></td><td><input type="text" name="f_room[]"></td>
                                        <td><input type="text" name="f_sec[]"></td><td><input type="text" name="f_size[]"></td>
                                        <td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-f')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">G. TEACHING LOAD OUTSIDE URS</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-g" style="min-width: 700px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="35%">Subject & Title</th><th width="25%">School</th><th width="10%">Hrs/Wk</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="g_time[]"></td><td><input type="text" name="g_day[]"></td>
                                        <td><input type="text" name="g_title[]"></td><td><input type="text" name="g_school[]"></td>
                                        <td><input type="text" name="g_hrs[]"></td><td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-g')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading">H. PRACTICE OF PROFESSION / BUSINESS</div>
                        <div style="overflow-x: auto;">
                            <table class="dyn-table" id="table-h" style="min-width: 600px;">
                                <thead><tr><th width="15%">Time</th><th width="15%">Day</th><th width="40%">Location of Practice</th><th width="25%">Nature of Business</th><th width="5%"></th></tr></thead>
                                <tbody>
                                    <tr>
                                        <td><input type="text" name="h_time[]"></td><td><input type="text" name="h_day[]"></td>
                                        <td><input type="text" name="h_loc[]"></td><td><input type="text" name="h_nature[]"></td>
                                        <td><button type="button" class="btn-remove" onclick="removeRow(this)"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn-add-row" onclick="addRow('table-h')"><i class="fas fa-plus"></i> Add Row</button>

                        <div class="section-heading" style="margin-top: 15px;">Signatories</div>
                        <div class="form-row">
                            <div class="form-group"><label>College/Department Name</label><input type="text" name="college_dept" class="form-control" placeholder="e.g., College of Science"></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Dean Name</label><input type="text" name="dean_name" class="form-control" placeholder="Name of Dean"></div>
                            <div class="form-group"><label>Campus Director Name</label><input type="text" name="director_name" class="form-control" placeholder="Name of Campus Director"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-download" style="background-color: #ef4444;"><i class="fas fa-file-pdf" style="margin-right: 8px;"></i> Download PDF Workload Form</button>
                </form>
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
        // Fetch the schedules mapped in PHP
        const allFacultySchedules = <?php echo json_encode($faculty_schedules); ?>;

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

        // --- Sidebar Toggle ---
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => { 
            document.body.classList.remove('mobile-nav-active'); 
        });

        // --- Tab Switching ---
        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.download-card').forEach(c => c.style.display = 'none');
            
            if (tab === 'general') {
                document.querySelector('.tab-btn:nth-child(1)').classList.add('active');
                document.getElementById('form-general').style.display = 'block';
            } else {
                document.querySelector('.tab-btn:nth-child(2)').classList.add('active');
                document.getElementById('form-workload').style.display = 'block';
                updateA1Preview(); 
            }
        }

        function toggleWorkloadExtra() {
            const extra = document.getElementById('workloadExtraFields');
            const checkbox = document.getElementById('fillOnlineToggle');
            extra.style.display = checkbox.checked ? 'block' : 'none';
        }

        // --- Form Handlers ---
        function handleGeneralSubmit(form) {
            // Can interject with a sysConfirm if needed
            return true;
        }

        function handleWorkloadSubmit(form) {
            // Can interject with a sysConfirm if needed
            return true;
        }

        // --- A.1 Dynamic Preview Logic ---
        function formatTime(timeStr) {
            if (!timeStr) return '';
            const [h, m] = timeStr.split(':');
            let hours = parseInt(h);
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            return hours + ':' + m + ' ' + ampm;
        }

        function updateA1Preview() {
            const ay = document.getElementById('wl_ay').value;
            const sem = document.getElementById('wl_sem').value;
            const tbody = document.querySelector('#a1-preview-table tbody');
            tbody.innerHTML = '';
            
            const filtered = allFacultySchedules.filter(s => s.academic_year === ay && s.semester === sem);
            
            if (filtered.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="tc" style="color:#ef4444; font-weight:600;">No schedule found for this academic period.</td></tr>';
                return;
            }

            filtered.forEach(s => {
                const timeStr = formatTime(s.start_time) + ' - ' + formatTime(s.end_time);
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${timeStr}</td>
                    <td class="tc" style="text-transform:uppercase;">${s.days || ''}</td>
                    <td class="tc" style="font-weight:700; color:var(--primary-blue);">${s.course_code || ''}</td>
                    <td>${s.descriptive_title || ''}</td>
                    <td class="tc">${parseFloat(s.lecture_hours) || ''}</td>
                    <td class="tc">${parseFloat(s.laboratory_hours) || ''}</td>
                    <td class="tc" style="font-weight:700;">${parseFloat(s.units) || ''}</td>
                    <td class="tc">${s.room_name || ''}</td>
                    <td class="tc">${s.program || ''} ${s.section || ''}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        // Listen for changes in the Workload dropdowns
        document.getElementById('wl_ay').addEventListener('change', updateA1Preview);
        document.getElementById('wl_sem').addEventListener('change', updateA1Preview);

        // --- JS Logic for Dynamic Tables (Add/Remove Rows) ---
        function addRow(tableId) {
            const tbody = document.querySelector(`#${tableId} tbody`);
            const row = tbody.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(inp => inp.value = '');
            tbody.appendChild(row);
        }

        function removeRow(btn) {
            const tbody = btn.closest('tbody');
            if (tbody.children.length > 1) {
                btn.closest('tr').remove();
            } else {
                btn.closest('tr').querySelectorAll('input').forEach(inp => inp.value = '');
            }
        }
    </script>
</body>
</html>