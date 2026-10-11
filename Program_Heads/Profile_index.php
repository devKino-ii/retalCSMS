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
    <title>My Profile - COSMOS</title>
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
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }

        .container { flex: 1; overflow-y: auto; padding: 20px 40px 40px 40px; display: flex; flex-direction: column; gap: 20px; }
        
        .page-title {
            color: var(--text-dark); font-size: 20px; font-weight: 800; margin-bottom: 25px; 
            display: flex; align-items: center; gap: 15px; text-transform: uppercase;
        }

        /* ===== PROFILE GRID ===== */
        .profile-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }

        /* Left Card: Avatar & Summary */
        .card { background: var(--bg-card); border-radius: var(--radius-card); padding: 30px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); }
        
        .profile-hero { text-align: center; }
        .avatar-wrapper { position: relative; width: 140px; height: 140px; margin: 0 auto 20px; }
        .avatar-large {
            width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid var(--accent-blue);
            display: flex; align-items: center; justify-content: center; font-size: 48px; font-weight: 800; color: white; background: var(--primary-blue); box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .upload-btn {
            position: absolute; bottom: 0; right: 0; background: var(--primary-blue); color: white; width: 40px; height: 40px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 3px solid white; transition: 0.2s; box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .upload-btn:hover { background: var(--primary-blue-hover); transform: scale(1.05); }
        
        .hero-name { font-size: 22px; font-weight: 800; color: var(--text-dark); margin-bottom: 5px; }
        .hero-role { font-size: 13px; font-weight: 600; color: var(--primary-blue); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 15px; }
        .hero-dept { display: inline-block; padding: 6px 15px; background: var(--theme-color); color: white; border-radius: var(--radius-pill); font-size: 12px; font-weight: 700; margin-bottom: 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .hero-divider { height: 1px; background: var(--border-color); margin: 20px 0; }
        .hero-detail { display: flex; align-items: center; gap: 15px; font-size: 13px; color: var(--text-muted); margin-bottom: 12px; font-weight: 500; text-align: left;}
        .hero-detail i { width: 20px; text-align: center; color: var(--primary-blue); font-size: 16px; }

        /* Right Card: Forms */
        .section-title { font-size: 16px; font-weight: 800; color: var(--text-dark); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--border-color); text-transform: uppercase;}
        
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .input-group { display: flex; flex-direction: column; gap: 8px; }
        .input-group label { font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .input-group.full-width { grid-column: 1 / -1; }
        
        .custom-input {
            padding: 14px 18px; border: 1px solid var(--border-color); border-radius: var(--radius-input); font-size: 14px; font-weight: 500;
            background: #f8fafc; color: var(--text-dark); outline: none; transition: 0.2s; width: 100%;
        }
        .custom-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .custom-input[readonly] { background: #e2e8f0; color: #64748b; cursor: not-allowed; border-color: transparent; }
        select.custom-input {
            appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto;
        }

        .btn-save {
            background: var(--primary-blue); color: white; border: none; padding: 14px 30px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px;
            cursor: pointer; transition: 0.2s; letter-spacing: 0.5px; display: inline-flex; align-items: center; justify-content: center; width: auto; text-transform: uppercase;
        }
        .btn-save:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2); }

        /* PASSWORD TOGGLE STYLES */
        .password-container { position: relative; width: 100%; }
        .password-container .custom-input { padding-right: 45px; }
        .toggle-password {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            cursor: pointer; color: var(--text-muted); font-size: 16px; transition: color 0.2s ease;
        }
        .toggle-password:hover { color: var(--primary-blue); }

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
            
            .profile-grid { grid-template-columns: 1fr; }
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
            <li><a href="Dashboard_index.php"><i class="fas fa-th-large"></i> <span>Dashboard</span></a></li>
                <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i> <span>Create Class</span></a></li>
                <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i> <span>Class Monitoring</span></a></li>
        
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
                <li><a href="Course_index.php"><i class="fas fa-book"></i> <span>Course</span></a></li>
                <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span></a></li>
            <li><a href="Download_index.php"><i class="fas fa-file-download"></i> <span>Download</span></a></li>
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
            <div class="page-title">
                <i class="fas fa-user-circle" style="color: var(--primary-blue); font-size: 24px;"></i> MY PROFILE
            </div>

            <div class="profile-grid">
                
                <div class="card profile-hero">
                    <form id="photoForm">
                        <div class="avatar-wrapper">
                            <?php if ($picSrc): ?>
                                <img src="<?php echo $picSrc; ?>" class="avatar-large" id="heroAvatar">
                            <?php else: ?>
                                <div class="avatar-large" id="heroAvatarText"><?php echo htmlspecialchars($initials); ?></div>
                            <?php endif; ?>
                            
                            <input type="file" id="profilePicInput" name="picture" accept="image/*" style="display: none;">
                            <label for="profilePicInput" class="upload-btn" title="Change Photo">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                    </form>
                    
                    <div class="hero-name"><?php echo htmlspecialchars($cleanName); ?></div>
                    <div class="hero-role"><?php echo htmlspecialchars($userPosition); ?></div>
                    <div class="hero-dept"><?php echo htmlspecialchars($userDept); ?></div>
                    
                    <div class="hero-divider"></div>
                    
                    <div class="hero-detail"><i class="fas fa-id-badge"></i> ID: <?php echo htmlspecialchars($userData['employee_id'] ?? 'Not Set'); ?></div>
                    <div class="hero-detail"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($userEmail); ?></div>
                    <div class="hero-detail"><i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($userData['program'] ?? 'Not Set'); ?></div>
                </div>

                <div class="card">
                    <h3 class="section-title">Personal Information</h3>
                    <form id="profileForm">
                        <div class="form-grid">
                            <div class="input-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="custom-input" value="<?php echo htmlspecialchars($userData['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Surname</label>
                                <input type="text" name="surname" class="custom-input" value="<?php echo htmlspecialchars($userData['surname'] ?? ''); ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Middle Initial</label>
                                <input type="text" name="middle_initial" class="custom-input" value="<?php echo htmlspecialchars($userData['middle_initial'] ?? ''); ?>" maxlength="5">
                            </div>
                            <div class="input-group">
                                <label>Sex</label>
                                <select name="sex" class="custom-input" required>
                                    <option value="Male" <?php if(($userData['sex']??'')=='Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if(($userData['sex']??'')=='Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if(($userData['sex']??'')=='Other') echo 'selected'; ?>>Other</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label>Email Address</label>
                                <input type="email" name="email" class="custom-input" value="<?php echo htmlspecialchars($userEmail); ?>" required>
                            </div>
                            <div class="input-group">
                                <label>Username (Login)</label>
                                <input type="text" name="username" class="custom-input" value="<?php echo htmlspecialchars($userData['username'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="input-group">
                                <label>Employee ID</label>
                                <input type="text" value="<?php echo htmlspecialchars($userData['employee_id'] ?? ''); ?>" class="custom-input" readonly title="Contact admin to change Employee ID">
                            </div>
                            <div class="input-group">
                                <label>Department</label>
                                <input type="text" value="<?php echo htmlspecialchars($userDept); ?>" class="custom-input" readonly title="Contact admin to change Department">
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <button type="submit" class="btn-save" id="btnSaveInfo"><i class="fas fa-save" style="margin-right: 8px;"></i> Update Information</button>
                        </div>
                    </form>

                    <h3 class="section-title" style="margin-top: 40px;">Change Password</h3>
                    <form id="passwordForm">
                        <div class="form-grid">
                            <div class="input-group full-width">
                                <label>Current Password</label>
                                <div class="password-container">
                                    <input type="password" id="curr_pass" name="current_password" class="custom-input" placeholder="Enter current password to verify identity" required>
                                    <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('curr_pass', this)"></i>
                                </div>
                            </div>
                            <div class="input-group">
                                <label>New Password</label>
                                <div class="password-container">
                                    <input type="password" id="new_pass" name="new_password" class="custom-input" placeholder="New Password" required minlength="6">
                                    <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('new_pass', this)"></i>
                                </div>
                            </div>
                            <div class="input-group">
                                <label>Confirm New Password</label>
                                <div class="password-container">
                                    <input type="password" id="conf_pass" name="confirm_password" class="custom-input" placeholder="Confirm New Password" required minlength="6">
                                    <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('conf_pass', this)"></i>
                                </div>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <button type="submit" class="btn-save" id="btnSavePass" style="background-color: #0f172a;"><i class="fas fa-lock" style="margin-right: 8px;"></i> Update Password</button>
                        </div>
                    </form>

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

        // Sidebar Toggle
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        // Function to toggle password visibility
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

        // 1. Image Compressor Helper
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

        // 2. Handle Profile Picture Upload automatically when selected
        document.getElementById('profilePicInput').addEventListener('change', async function() {
            if (this.files.length === 0) return;
            
            const file = this.files[0];
            const btn = document.querySelector('.upload-btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            
            try {
                const compressedBlob = await compressImage(file);
                const fd = new FormData();
                fd.append('action', 'update_photo');
                fd.append('picture', compressedBlob, 'profile.jpg');
                
                const res = await fetch('Profile_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                
                if(result.status === 'success') {
                    // Update image on screen immediately
                    const newUrl = URL.createObjectURL(compressedBlob);
                    const heroAvatar = document.getElementById('heroAvatar');
                    if(heroAvatar) {
                        heroAvatar.src = newUrl;
                    } else {
                        const wrapper = document.querySelector('.avatar-wrapper');
                        const textAvatar = document.getElementById('heroAvatarText');
                        if(textAvatar) textAvatar.remove();
                        const img = document.createElement('img');
                        img.src = newUrl;
                        img.className = 'avatar-large';
                        img.id = 'heroAvatar';
                        wrapper.prepend(img);
                    }
                    await sysAlert("Profile picture updated successfully!", "fa-check-circle", "#10b981");
                    setTimeout(() => location.reload(), 1000); // Reload to update sidebar img
                } else {
                    await sysAlert("Error: " + result.message, "fa-exclamation-triangle", "#ef4444");
                }
            } catch (err) {
                await sysAlert("Failed to upload picture.", "fa-wifi", "#ef4444");
                console.error(err);
            }
            btn.innerHTML = '<i class="fas fa-camera"></i>';
        });

        // 3. Handle Profile Info Update
        document.getElementById('profileForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const proceed = await sysConfirm("Save changes to your personal information?", "fa-user-edit", "var(--primary-blue)");
            if (!proceed) return;

            const btn = document.getElementById('btnSaveInfo');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Saving...';
            btn.disabled = true;

            const fd = new FormData(this);
            fd.append('action', 'update_info');

            try {
                const res = await fetch('Profile_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                
                if(result.status === 'success') {
                    await sysAlert(result.message, "fa-check-circle", "#10b981");
                    location.reload();
                } else {
                    await sysAlert(result.message, "fa-times-circle", "#ef4444");
                }
            } catch (err) {
                await sysAlert("Network error occurred.", "fa-wifi", "#ef4444");
            }
            btn.innerHTML = '<i class="fas fa-save" style="margin-right: 8px;"></i> Update Information';
            btn.disabled = false;
        });

        // 4. Handle Password Update
        document.getElementById('passwordForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const newPass = this.querySelector('[name="new_password"]').value;
            const confPass = this.querySelector('[name="confirm_password"]').value;
            
            if(newPass !== confPass) {
                await sysAlert("New passwords do not match!", "fa-exclamation-triangle", "#f59e0b");
                return;
            }

            const proceed = await sysConfirm("Are you sure you want to change your password?", "fa-lock", "#0f172a");
            if (!proceed) return;

            const btn = document.getElementById('btnSavePass');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 8px;"></i> Verifying...';
            btn.disabled = true;

            const fd = new FormData(this);
            fd.append('action', 'update_password');

            try {
                const res = await fetch('Profile_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                
                if(result.status === 'success') {
                    await sysAlert(result.message, "fa-check-circle", "#10b981");
                    this.reset();
                } else {
                    await sysAlert(result.message, "fa-times-circle", "#ef4444");
                }
            } catch (err) {
                await sysAlert("Network error occurred.", "fa-wifi", "#ef4444");
            }
            btn.innerHTML = '<i class="fas fa-lock" style="margin-right: 8px;"></i> Update Password';
            btn.disabled = false;
        });
    </script>
</body>
</html>