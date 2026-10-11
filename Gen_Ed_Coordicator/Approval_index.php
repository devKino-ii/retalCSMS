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

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'General Education' || stripos($userPosition, 'Gen') !== false || (stripos($userPosition, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));
$targetDept = $isGenEd ? 'General Education' : $userDept;

$isFaculty = ($userPosition === 'Faculty');

// Department color mapping
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
    'General Education'                => '#8b5cf6', 
    'MIS'                              => '#6366f1'
];
$themeColor = $departmentColors[$targetDept] ?? 'var(--primary-blue)';

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
    <title>Approvals & Notifications - COSMOS</title>
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
        body { display: flex; height: 100vh; background: var(--bg-main); color: var(--text-dark); overflow: hidden; }
        
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

        /* ════════════════════════════════════════════════════════
           MAIN CONTENT & SCROLLABLE FRAMES
        ════════════════════════════════════════════════════════ */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        .container { padding: 20px 40px 40px 40px; overflow-y: auto; flex: 1; }
        
        .card { background: white; border-radius: var(--radius-card); padding: 30px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); }
        .req-grid { display: grid; grid-template-columns: 1fr; gap: 15px; align-content: start; }
        .req-row { display: flex; justify-content: space-between; align-items: center; padding: 20px; border: 1px solid var(--border-color); border-radius: 16px; background: #f8fafc; transition: all 0.2s ease;}
        .req-row:hover { border-color: var(--primary-blue); box-shadow: 0 4px 12px rgba(27, 80, 136, 0.05); transform: translateY(-2px); }
        
        .req-info h4 { font-size: 18px; font-weight: 800; color: var(--primary-blue); margin-bottom: 5px; }
        .req-info p { font-size: 13px; color: var(--text-muted); font-weight: 500; margin-bottom: 4px;}
        .req-badge { font-size: 10px; font-weight: 800; padding: 4px 8px; border-radius: 6px; background: #fef3c7; color: #d97706; display: inline-block; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
        
        .btn-approve, .btn-reject, .btn-view { display: inline-flex; align-items: center; justify-content: center; color: white; border: none; padding: 10px 20px; border-radius: var(--radius-pill); font-weight: 700; cursor: pointer; transition: 0.2s; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;}
        .btn-approve { background: #10b981; }
        .btn-reject { background: #ef4444; }
        .btn-view { background: #3b82f6; }
        .btn-approve:hover:not(:disabled) { background: #059669; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); } 
        .btn-approve:disabled { background: #cbd5e1; cursor: not-allowed; }
        .btn-reject:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); } 
        .btn-view:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }

        /* Notification Styling */
        .notif-row { padding: 15px 20px; border: 1px solid; border-radius: 16px; display: flex; align-items: center; gap: 15px; margin-bottom: 12px; transition: all 0.2s ease;}
        .notif-row:hover { transform: translateX(4px); }
        .notif-warning { background: #fef8e1; border-color: #fde68a; }
        .notif-awaiting { background: #eff6ff; border-color: #bfdbfe; }
        .notif-success { background: #f0fdf4; border-color: #bbf7d0; }
        .notif-danger { background: #fef2f2; border-color: #fecaca; }

        /* FIXED HEIGHT SCROLLABLE FRAMES */
        .scroll-frame {
            max-height: 45vh; 
            min-height: 200px;
            overflow-y: auto; 
            overflow-x: hidden;
            padding-right: 5px;
        }
        .scroll-frame::-webkit-scrollbar { width: 6px; }
        .scroll-frame::-webkit-scrollbar-track { background: transparent; border-radius: 10px;}
        .scroll-frame::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .scroll-frame::-webkit-scrollbar-thumb:hover { background: var(--primary-blue); }

        /* Search Bar Styling */
        .search-input { 
            width: 100%; padding: 12px 16px 12px 45px; border: 1px solid var(--border-color); 
            border-radius: var(--radius-pill); font-size: 13px; font-weight: 500; outline: none; transition: 0.2s; background: var(--bg-card); color: var(--text-dark); box-shadow: var(--shadow-card);
        }
        .search-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.6); padding: 20px; backdrop-filter: blur(4px); overflow: auto; }
        .modal-content { background: var(--bg-card); margin: 3% auto; padding: 35px 40px; border-radius: var(--radius-card); max-width: 500px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .close-btn { color: var(--text-muted); float: right; font-size: 28px; font-weight: bold; cursor: pointer; margin-top: -10px; transition: 0.2s;}
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }
        
        /* Faculty Card Selection Styling */
        .faculty-card { border: 2px solid var(--border-color); padding: 15px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: 0.2s; text-align: left; background: white; }
        .faculty-card:hover:not([style*="not-allowed"]) { border-color: var(--primary-blue); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .faculty-card.selected { border-color: var(--primary-blue); background: var(--accent-blue); box-shadow: 0 0 0 3px var(--accent-blue); }

        /* Modern Table inside View Modal */
        .styled-table-wrapper { width: 100%; border: 1px solid var(--border-color); border-radius: var(--radius-input); overflow-x: auto; background: white; box-shadow: var(--shadow-card); }
        .styled-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; text-align: center;}
        .styled-table th { background: #f8fafc; padding: 12px 6px; text-transform: uppercase; font-size: 11px; color: var(--text-muted); font-weight: 700; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); position: sticky; top: 0; z-index: 10; }
        .styled-table th:last-child { border-right: none; }
        .styled-table td { padding: 12px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); vertical-align: middle; color: var(--text-dark); font-weight: 500;}
        .styled-table td:last-child { border-right: none; }
        .styled-table tr:last-child td { border-bottom: none; }
        .styled-table tr:hover td { background: #f8fafc; }
        
        .pill { border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 8px; font-weight: 600; display: inline-block; background: white; color: var(--text-dark); font-size: 11px; }
        .time-pill { border: 1px solid var(--border-color); padding: 4px 8px; border-radius: 6px; font-weight: 600; display: block; background: white; color: var(--text-dark); font-size: 11px; margin: 2px auto; width: max-content; }
        .time-pill.end { background: #f8fafc; color: var(--text-muted); }

        /* System Modals */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-input { width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; text-align: center; font-weight: 600; margin-bottom: 20px; outline: none; }
        .sys-modal-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .backdrop { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999; }
        
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

            header { padding: 20px; }
            .container { padding: 20px; }
            .req-row { flex-direction: column; align-items: flex-start; gap: 15px; }
            .req-row > div:last-child { width: 100%; }
            .req-row > div:last-child button { width: 100%; }
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
            <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span></a></li>
            <li><a href="Approval_index.php" class="active"><i class="fas fa-check-circle"></i> <span>Approval</span></a></li>
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
                        <p>
                            <?php 
                            if ($isGenEd) {
                                echo '<span style="background: #8b5cf6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 800; text-transform: uppercase;">Gen Ed Coordinator</span>';
                            } else {
                                echo htmlspecialchars($userDept); 
                            }
                            ?>
                        </p>
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
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                <h2 style="font-size: 20px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; margin: 0;">
                    <i class="fas fa-check-circle" style="color:var(--primary-blue); margin-right: 8px;"></i> Pending Approvals
                </h2>
                
                <div style="position: relative; width: 100%; max-width: 350px;">
                    <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
                    <input type="text" id="searchInput" onkeyup="filterApprovals()" placeholder="Search by course code, program, section..." class="search-input">
                </div>
            </div>

            <div class="card req-grid scroll-frame" id="approvalContainer" style="padding: 20px; align-content: start;">
                <p style="text-align: center; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading requests...</p>
            </div>

            <h2 style="margin-bottom: 20px; margin-top: 40px; font-size: 20px; font-weight: 800; color: var(--text-dark); text-transform: uppercase;">
                <i class="fas fa-bell" style="color:var(--primary-blue); margin-right: 8px;"></i> Decision Notifications
            </h2>
            <div class="card scroll-frame" id="notificationContainer" style="padding: 20px; display: flex; flex-direction: column; gap: 10px;">
                <p style="text-align: center; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</p>
            </div>
        </div>
    </div>

    <div id="viewScheduleModal" class="modal">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h3 id="vs_title" style="color: var(--text-dark); font-weight: 800; font-size: 24px; margin-bottom: 5px;">Review Block Schedule</h3>
                    <p id="vs_subtitle" style="font-size: 14px; color: var(--text-muted); font-weight: 500;"></p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-reject" style="background-color: #f1f5f9; color: var(--text-dark);" onclick="document.getElementById('viewScheduleModal').style.display='none'">CLOSE</button>
                </div>
            </div>
            
            <div class="scroll-frame">
                <div class="styled-table-wrapper">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Day</th>
                                <th style="width: 16%;">Time</th>
                                <th style="width: 10%;">Course Code</th>
                                <th style="width: 18%;">Subject Description</th>
                                <th style="width: 4%;">Unit</th>
                                <th style="width: 8%; padding: 5px;">
                                    Hours/Week<br>
                                    <span style="font-size:9px; color:#94a3b8; font-weight:600;">LEC | LAB</span>
                                </th>
                                <th style="width: 12%;">Room</th>
                                <th style="width: 14%;">Professor</th>
                                <th style="width: 8%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="vs_tbody"></tbody>
                    </table>
                </div>
            </div>

            <div id="vs_footer_btns" style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 20px;">
            </div>
        </div>
    </div>

    <div id="assignModal" class="modal">
        <div class="modal-content" style="max-width: 1200px; width: 95%;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                <div>
                    <h3 style="color: var(--text-dark); font-weight: 800; font-size: 24px; margin-bottom: 5px;">Review & Assign Professor</h3>
                    <p style="font-size: 14px; color: var(--text-muted); font-weight: 500;">Select an available professor from your department to fulfill this request.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn-reject" style="background-color: #f1f5f9; color: var(--text-dark);" onclick="document.getElementById('assignModal').style.display='none'">CLOSE</button>
                </div>
            </div>
            
            <div class="scroll-frame" style="margin-bottom: 20px;">
                <div class="styled-table-wrapper">
                    <table class="styled-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Day</th>
                                <th style="width: 16%;">Time</th>
                                <th style="width: 10%;">Course Code</th>
                                <th style="width: 18%;">Subject Description</th>
                                <th style="width: 4%;">Unit</th>
                                <th style="width: 8%; padding: 5px;">
                                    Hours/Week<br>
                                    <span style="font-size:9px; color:#94a3b8; font-weight:600;">LEC | LAB</span>
                                </th>
                                <th style="width: 14%;">Room</th>
                                <th style="width: 16%;">Professor</th>
                            </tr>
                        </thead>
                        <tbody id="am_tbody"></tbody>
                    </table>
                </div>
            </div>

            <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: 16px; padding: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <h4 style="font-size: 14px; color: var(--text-dark); font-weight: 700; margin: 0; text-transform: uppercase;">
                        <i class="fas fa-users" style="color:var(--primary-blue); margin-right:5px;"></i> Select Available Professor
                    </h4>
                    
                    <div style="position: relative; width: 100%; max-width: 280px;">
                        <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 12px;"></i>
                        <input type="text" id="profSearchInput" onkeyup="filterProfessors()" placeholder="Search name or program..." class="search-input" style="padding: 10px 12px 10px 40px; border-radius: 8px; font-size: 12px;">
                    </div>
                </div>
                
                <input type="hidden" id="m_sched_id">
                <input type="hidden" id="m_selected_faculty">

                <div id="m_faculty_list" class="scroll-frame" style="display: flex; flex-direction: column; gap: 8px; padding-right: 5px;">
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 25px;">
                <button class="btn-approve" id="btn_submit_assignment" onclick="submitAssignment()" disabled><i class="fas fa-check" style="margin-right: 6px;"></i> CONFIRM ASSIGNMENT</button>
            </div>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <button id="sysModalAlertBtn" class="btn-approve" style="width:100%; padding: 12px; font-size: 13px;">OK</button>
        </div>
    </div>

    <div id="sysModalConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalConfirmIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalConfirmMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <div class="sys-modal-btns">
                <button id="sysModalConfirmCancelBtn" class="btn-reject" style="flex:1; background:#e2e8f0; color:#475569; padding: 12px; font-size: 13px;">Cancel</button>
                <button id="sysModalConfirmOkBtn" class="btn-approve" style="flex:1; padding: 12px; font-size: 13px;">Confirm</button>
            </div>
        </div>
    </div>

    <div id="sysModalPrompt" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalPromptIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalPromptMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <input type="text" id="sysModalPromptInput" class="table-input" style="padding: 12px; font-size: 14px; background: white; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 20px; font-weight: 700; width: 100%; box-sizing: border-box;" placeholder="Type command here..." autocomplete="off">
            <div class="sys-modal-btns">
                <button id="sysModalPromptCancelBtn" class="btn-reject" style="flex:1; background:#e2e8f0; color:#475569; padding: 12px; font-size: 13px;">Cancel</button>
                <button id="sysModalPromptOkBtn" class="btn-approve" style="flex:1; padding: 12px; font-size: 13px;">Submit</button>
            </div>
        </div>
    </div>

    <script>
        const userDept = "<?php echo addslashes($targetDept); ?>";
        let globalInternalGroups = {}; 
        let globalCrossRequests = {};  

        // Smart Polling States
        let lastApprovalsState = null;
        let lastNotifState = null;
        let isPollingApprovals = false;
        let isPollingNotifs = false;

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

        function sysPrompt(msg, expectedWord, icon = 'fa-exclamation-triangle', color = '#ef4444') {
            return new Promise(resolve => {
                document.getElementById('sysModalPromptMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalPromptIcon');
                iconEl.className = `fas ${icon} sys-modal-icon`;
                iconEl.style.color = color;
                
                const okBtn = document.getElementById('sysModalPromptOkBtn');
                okBtn.style.backgroundColor = color;

                const inputEl = document.getElementById('sysModalPromptInput');
                inputEl.value = '';
                
                document.getElementById('sysModalPrompt').style.display = 'flex';
                inputEl.focus();
                
                document.getElementById('sysModalPromptCancelBtn').onclick = () => {
                    document.getElementById('sysModalPrompt').style.display = 'none';
                    resolve(false);
                };
                
                okBtn.onclick = () => {
                    if (inputEl.value === expectedWord) {
                        document.getElementById('sysModalPrompt').style.display = 'none';
                        resolve(true);
                    } else {
                        sysAlert(`Incorrect command. You must type '${expectedWord}' exactly (case sensitive).`, "fa-times-circle", "#ef4444");
                    }
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

        // --- Helper: Convert Time to AM/PM ---
        function formatAMPM(timeStr) {
            if (!timeStr) return '';
            let [hours, minutes] = timeStr.split(':');
            hours = parseInt(hours, 10);
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; 
            return `${hours}:${minutes} ${ampm}`;
        }

        // --- Live Search Functions ---
        function filterApprovals() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#approvalContainer .req-row');
            
            let hasVisible = false;
            rows.forEach(row => {
                const textContent = row.innerText.toLowerCase();
                if (textContent.includes(input)) {
                    row.style.display = 'flex';
                    hasVisible = true;
                } else {
                    row.style.display = 'none';
                }
            });
            
            let noResultsMsg = document.getElementById('noResultsMsg');
            if (!hasVisible && input !== "") {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noResultsMsg';
                    noResultsMsg.innerHTML = `<p style="text-align: center; color: var(--text-muted); padding: 40px; width: 100%;"><i class="fas fa-search" style="font-size: 40px; color: #cbd5e1; margin-bottom: 10px; display:block;"></i>No requests match your search.</p>`;
                    document.getElementById('approvalContainer').appendChild(noResultsMsg);
                }
                noResultsMsg.style.display = 'block';
            } else if (noResultsMsg) {
                noResultsMsg.style.display = 'none';
            }
        }

        function filterProfessors() {
            const input = document.getElementById('profSearchInput').value.toLowerCase();
            const cards = document.querySelectorAll('#m_faculty_list .faculty-card');
            
            let hasVisible = false;
            cards.forEach(card => {
                const searchText = card.getAttribute('data-search');
                if (searchText.includes(input)) {
                    card.style.display = 'flex';
                    hasVisible = true;
                } else {
                    card.style.display = 'none';
                }
            });

            let noProfMsg = document.getElementById('noProfMsg');
            if (!hasVisible && input !== "") {
                if (!noProfMsg) {
                    noProfMsg = document.createElement('p');
                    noProfMsg.id = 'noProfMsg';
                    noProfMsg.style = "text-align:center; padding: 20px; color:#ef4444; font-weight:600;";
                    noProfMsg.innerText = "No professor matches your search.";
                    document.getElementById('m_faculty_list').appendChild(noProfMsg);
                }
                noProfMsg.style.display = 'block';
            } else if (noProfMsg) {
                noProfMsg.style.display = 'none';
            }
        }

        window.onload = async () => {
            loadApprovals();
            loadNotifications();

            // Start collaborative polling every 5 seconds
            setInterval(() => {
                loadApprovals(true);
                loadNotifications(true);
            }, 5000);
        };

        async function loadApprovals(isBackground = false) {
            if (isPollingApprovals) return;
            isPollingApprovals = true;

            const container = document.getElementById('approvalContainer');
            if (!isBackground) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading requests...</p>';
            }

            try {
                const res = await fetch('Approval_api.php?action=get_requests');
                const data = await res.json();
                const currentState = JSON.stringify(data);

                if (currentState !== lastApprovalsState) {
                    lastApprovalsState = currentState;

                    if(data.length === 0) {
                        container.innerHTML = `<div style="text-align:center; padding: 60px; color:var(--text-muted); font-weight: 600;"><i class="fas fa-inbox" style="font-size:50px; color: #cbd5e1; margin-bottom:15px; display:block;"></i>All caught up! No pending approvals.</div>`;
                        isPollingApprovals = false;
                        return;
                    }

                    const internalGroups = {};
                    const crossGroups = {};
                    globalCrossRequests = {}; 

                    let htmlBuffer = '';

                    data.forEach(req => {
                        const isCrossProf = (req.requested_department === userDept && req.approval_status === 'Approved');
                        
                        if (isCrossProf) {
                            globalCrossRequests[req.id] = req; 
                            const key = `${req.department}|${req.academic_year}|${req.semester}|${req.program}|${req.section}`;
                            if (!crossGroups[key]) crossGroups[key] = [];
                            crossGroups[key].push({ ...req, isCrossProf });
                        } else if (req.department === userDept && req.approval_status === 'Pending') {
                            const groupKey = `${req.academic_year}|${req.semester}|${req.program}|${req.section}`;
                            if (!internalGroups[groupKey]) {
                                internalGroups[groupKey] = [];
                            }
                            internalGroups[groupKey].push(req);
                        }
                    });

                    globalInternalGroups = internalGroups;

                    // 1. Render Grouped Internal Schedules
                    for (const [key, group] of Object.entries(internalGroups)) {
                        const sample = group[0];

                        htmlBuffer += `
                            <div class="req-row" style="cursor: pointer;" onclick="openViewModal('${key}')">
                                <div class="req-info">
                                    <div class="req-badge"><i class="fas fa-layer-group" style="margin-right:4px;"></i> Block Schedule Approval</div>
                                    <h4>${sample.program} - Section ${sample.section}</h4>
                                    <p style="margin-bottom: 4px;"><strong>Academic Term:</strong> ${sample.semester}, ${sample.academic_year}</p>
                                    <p><strong>Total Classes:</strong> <span style="color:var(--primary-blue); font-weight: 700;">${group.length} Subjects Pending</span></p>
                                </div>
                                <div style="display:flex; align-items:center;">
                                    <button class="btn-view" style="pointer-events: none;"><i class="fas fa-eye" style="margin-right: 6px;"></i> REVIEW & APPROVE</button>
                                </div>
                            </div>
                        `;
                    }

                    // 2. Render Grouped Cross-Department Requests
                    for (const [key, group] of Object.entries(crossGroups)) {
                        const sample = group[0];
                        
                        let subItemsHtml = group.map(req => {
                            const timeStr = `${req.days} | ${formatAMPM(req.start_time)} - ${formatAMPM(req.end_time)}`;
                            let typeBadge = `<span style="color:#3b82f6; background:#eff6ff; padding:4px 8px; border-radius:6px; font-size:10px; font-weight:800;"><i class="fas fa-user-tie"></i> PROF</span>`;
                            let actionBtn = `<button class="btn-approve" onclick="openAssignModal(${req.id})" style="padding: 8px 16px;"><i class="fas fa-search" style="margin-right:4px;"></i> ASSIGN</button>`;

                            return `
                                <div style="display:flex; justify-content:space-between; align-items:center; background:white; padding:15px; border-radius:12px; border:1px solid var(--border-color); margin-bottom:10px; box-shadow: 0 2px 8px rgba(0,0,0,0.02);">
                                    <div>
                                        <div style="margin-bottom:6px; display:flex; align-items:center; gap:8px;">${typeBadge} <strong style="color:var(--primary-blue); font-size:14px;">${req.course_code} - ${req.descriptive_title}</strong></div>
                                        <div style="font-size:12px; color:var(--text-muted); font-weight: 600;"><i class="far fa-clock"></i> ${timeStr}</div>
                                    </div>
                                    <div style="display:flex; gap:8px;">
                                        ${actionBtn}
                                        <button class="btn-reject" onclick="handleAction('reject', ${req.id})" title="Reject Request"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            `;
                        }).join('');

                        htmlBuffer += `
                            <div class="req-row" style="flex-direction:column; align-items:stretch; border-left: 4px solid #3b82f6; padding: 0;">
                                <div style="display:flex; justify-content:space-between; align-items:center; padding: 20px;">
                                    <div class="req-info">
                                        <div class="req-badge" style="background:#eff6ff; color:#3b82f6;"><i class="fas fa-exchange-alt" style="margin-right:4px;"></i> Prof Request from ${sample.department}</div>
                                        <h4 style="font-size:18px;">${sample.program} - Section ${sample.section}</h4>
                                        <p style="margin-top:4px;"><strong>Academic Term:</strong> ${sample.semester}, ${sample.academic_year}</p>
                                    </div>
                                </div>
                                <div style="background:#f8fafc; padding: 20px; border-top: 1px solid var(--border-color); border-bottom-left-radius: 16px; border-bottom-right-radius: 16px;">
                                    <div style="font-size:11px; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin-bottom:12px; letter-spacing: 0.5px;">Requested Resources (${group.length})</div>
                                    ${subItemsHtml}
                                </div>
                            </div>
                        `;
                    }

                    container.innerHTML = htmlBuffer;
                    filterApprovals(); // Re-apply search filter seamlessly
                }

            } catch(e) { 
                if (!isBackground) container.innerHTML = "<p style='color:red; text-align:center;'>Error loading requests.</p>"; 
            } finally {
                isPollingApprovals = false;
            }
        }

        function openViewModal(groupKey) {
            const group = [...globalInternalGroups[groupKey]];
            if (!group) return;

            const dayWeights = { 'Mon': 1, 'Tue': 2, 'Wed': 3, 'Thu': 4, 'Fri': 5, 'Sat': 6, 'Sun': 7 };
            
            group.sort((a, b) => {
                let dayA = a.days ? a.days.split(',')[0].trim().substring(0, 3) : '';
                let dayB = b.days ? b.days.split(',')[0].trim().substring(0, 3) : '';
                
                let weightA = dayWeights[dayA] || 99;
                let weightB = dayWeights[dayB] || 99;
                
                if (weightA !== weightB) return weightA - weightB; 
                
                let timeA = a.start_time || "23:59:59";
                let timeB = b.start_time || "23:59:59";
                return timeA.localeCompare(timeB);
            });

            const sample = group[0];
            document.getElementById('vs_title').innerText = `${sample.program} - Section ${sample.section}`;
            document.getElementById('vs_subtitle').innerText = `Academic Year ${sample.academic_year} | ${sample.semester}`;

            const tbody = document.getElementById('vs_tbody');
            tbody.innerHTML = '';

            group.forEach(subj => {
                const startTimeStr = formatAMPM(subj.start_time);
                const endTimeStr = formatAMPM(subj.end_time);
                
                let profName = subj.professor_name ? subj.professor_name : 'TBA';
                if(subj.requested_department) profName = "⏳ Req: " + subj.requested_department;
                else if(profName !== 'TBA' && !profName.startsWith('Request')) profName = 'Prof. ' + profName;
                
                let roomName = subj.room_name ? subj.room_name : 'TBA';
                if(subj.requested_room_department) roomName = "⏳ Req: " + subj.requested_room_department;

                tbody.innerHTML += `
                    <tr id="vs_row_${subj.id}">
                        <td><span class="pill">${subj.days}</span></td>
                        <td>
                            <span class="time-pill">${startTimeStr}</span>
                            <span class="time-pill end">${endTimeStr}</span>
                        </td>
                        <td style="color: var(--primary-blue); font-weight: 800;">${subj.course_code}</td>
                        <td style="text-align: left; font-weight: 600;">${subj.descriptive_title}</td>
                        <td style="font-weight: 800;">${subj.units}</td>
                        <td>${subj.lecture_hours} <span style="color:var(--border-color); margin: 0 4px;">|</span> ${subj.laboratory_hours}</td>
                        <td><span class="pill" style="min-width: 80px;">${roomName}</span></td>
                        <td><span class="pill" style="min-width: 140px; text-align: left;">${profName}</span></td>
                        <td>
                            <div style="display:flex; gap:8px; justify-content:center;">
                                <button class="btn-approve" style="padding: 8px 12px;" onclick="approveSingleItem(${subj.id}, '${groupKey}', this)" title="Approve Subject"><i class="fas fa-check"></i></button>
                                <button class="btn-reject" style="padding: 8px 12px;" onclick="rejectSingleItem(${subj.id}, '${groupKey}', this)" title="Reject Subject"><i class="fas fa-times"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            // Set up Modal Footer Batch Buttons
            const idsArray = group.map(g => g.id);
            const idsJson = JSON.stringify(idsArray).replace(/"/g, '&quot;');
            const groupInfoStr = `${sample.program} - ${sample.section}`;
            
            document.getElementById('vs_footer_btns').innerHTML = `
                <button class="btn-reject" onclick="handleBatchAction('reject_batch', '${idsJson}', '${groupInfoStr}')"><i class="fas fa-times" style="margin-right: 6px;"></i> REJECT ALL</button>
                <button class="btn-approve" onclick="handleBatchAction('approve_batch', '${idsJson}', '${groupInfoStr}')"><i class="fas fa-check-double" style="margin-right: 6px;"></i> APPROVE ALL</button>
            `;

            document.getElementById('viewScheduleModal').style.display = 'block';
        }

        // --- New Individual Inline Actions ---
        async function approveSingleItem(id, groupKey, btn) {
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'approve_batch');
            fd.append('schedule_ids', JSON.stringify([id]));
            fd.append('group_info', 'specific subject');

            try {
                const res = await fetch('Approval_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.status === 'success') {
                    document.getElementById(`vs_row_${id}`).remove();
                    removeFromInternalGroup(groupKey, id);
                } else {
                    sysAlert(result.message, 'fa-exclamation-triangle', '#ef4444');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch(e) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        async function rejectSingleItem(id, groupKey, btn) {
            const proceed = await sysConfirm("Are you sure you want to reject this individual subject?", 'fa-times-circle', '#ef4444');
            if(!proceed) return;

            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            btn.disabled = true;

            const fd = new FormData();
            fd.append('action', 'reject_batch');
            fd.append('schedule_ids', JSON.stringify([id]));
            fd.append('group_info', 'specific subject');

            try {
                const res = await fetch('Approval_api.php', { method: 'POST', body: fd });
                const result = await res.json();
                if (result.status === 'success') {
                    document.getElementById(`vs_row_${id}`).remove();
                    removeFromInternalGroup(groupKey, id);
                } else {
                    sysAlert(result.message, 'fa-exclamation-triangle', '#ef4444');
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            } catch(e) {
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            }
        }

        function removeFromInternalGroup(groupKey, id) {
            // Remove from local memory tracking
            globalInternalGroups[groupKey] = globalInternalGroups[groupKey].filter(req => req.id !== id);
            
            if (globalInternalGroups[groupKey].length === 0) {
                // All items processed, close modal automatically
                document.getElementById('viewScheduleModal').style.display = 'none';
            } else {
                // Update footer buttons with remaining IDs
                const group = globalInternalGroups[groupKey];
                const sample = group[0];
                const idsArray = group.map(g => g.id);
                const idsJson = JSON.stringify(idsArray).replace(/"/g, '&quot;');
                const groupInfoStr = `${sample.program} - ${sample.section}`;
                document.getElementById('vs_footer_btns').innerHTML = `
                    <button class="btn-reject" onclick="handleBatchAction('reject_batch', '${idsJson}', '${groupInfoStr}')"><i class="fas fa-times" style="margin-right: 6px;"></i> REJECT ALL</button>
                    <button class="btn-approve" onclick="handleBatchAction('approve_batch', '${idsJson}', '${groupInfoStr}')"><i class="fas fa-check-double" style="margin-right: 6px;"></i> APPROVE ALL</button>
                `;
            }
            
            // Background refresh to keep dashboard counts accurate
            loadApprovals(true);
            loadNotifications(true);
        }

        async function handleBatchAction(action, idsJson, groupInfo) {
            const actionText = action === 'approve_batch' ? 'APPROVE' : 'REJECT';
            const icon = action === 'approve_batch' ? 'fa-check-double' : 'fa-trash-alt';
            const color = action === 'approve_batch' ? '#10b981' : '#ef4444';

            const proceed = await sysConfirm(`Are you sure you want to <b>${actionText}</b> the remaining schedule for <b>${groupInfo}</b>?`, icon, color);
            if (!proceed) return;
            
            const fd = new FormData();
            fd.append('action', action);
            fd.append('schedule_ids', idsJson);
            fd.append('group_info', groupInfo);
            
            try {
                const res = await fetch('Approval_api.php', { method:'POST', body: fd });
                const result = await res.json();
                
                if(result.status === 'success') {
                    await sysAlert(result.message, 'fa-check-circle', '#10b981');
                    document.getElementById('viewScheduleModal').style.display = 'none';
                } else {
                    await sysAlert(result.message, 'fa-exclamation-triangle', '#ef4444');
                }
                
                loadApprovals(true);
                loadNotifications(true);
            } catch(e) {
                await sysAlert("Network error processing batch request.", 'fa-wifi', '#ef4444');
            }
        }

        async function handleAction(action, id) {
            const proceed = await sysConfirm("Are you sure you want to reject this request? It will be sent back to the requesting department.", 'fa-times-circle', '#ef4444');
            if(!proceed) return;

            const fd = new FormData();
            fd.append('action', action);
            fd.append('schedule_id', id);
            
            try {
                const res = await fetch('Approval_api.php', { method:'POST', body: fd });
                const result = await res.json();

                if(result.status === 'success') {
                    await sysAlert(result.message, 'fa-check-circle', '#10b981');
                } else {
                    await sysAlert(result.message, 'fa-exclamation-triangle', '#ef4444');
                }
                
                loadApprovals(true);
                loadNotifications(true); 
            } catch(e) {
                await sysAlert("Network error processing request.", 'fa-wifi', '#ef4444');
            }
        }

        window.selectFaculty = function(id, cardElement) {
            document.getElementById('m_selected_faculty').value = id;
            
            document.querySelectorAll('.faculty-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            cardElement.classList.add('selected');
            document.getElementById('btn_submit_assignment').disabled = false;
        };

        async function openAssignModal(sched_id) {
            const req = globalCrossRequests[sched_id];
            if (!req) return;

            document.getElementById('m_sched_id').value = sched_id;
            document.getElementById('m_selected_faculty').value = '';
            document.getElementById('btn_submit_assignment').disabled = true;
            document.getElementById('profSearchInput').value = ''; 
            
            const tbody = document.getElementById('am_tbody');
            const startTimeStr = formatAMPM(req.start_time);
            const endTimeStr = formatAMPM(req.end_time);
            let roomName = req.room_name ? req.room_name : 'TBA';

            tbody.innerHTML = `
                <tr>
                    <td>
                        <span class="pill">${req.days}</span>
                    </td>
                    <td>
                        <span class="time-pill">${startTimeStr}</span>
                        <span class="time-pill end">${endTimeStr}</span>
                    </td>
                    <td style="color: var(--primary-blue); font-weight: 800;">
                        ${req.course_code}
                    </td>
                    <td style="text-align: left; font-weight: 600;">
                        ${req.descriptive_title}
                    </td>
                    <td style="font-weight: 800;">
                        ${req.units}
                    </td>
                    <td>
                        ${req.lecture_hours} <span style="color:var(--border-color); margin: 0 4px;">|</span> ${req.laboratory_hours}
                    </td>
                    <td>
                        <span class="pill" style="min-width: 80px;">${roomName}</span>
                    </td>
                    <td>
                        <span class="pill" style="min-width: 140px; text-align: left; color: #d97706; background: #fef3c7; border: none;">Waiting for Assignment...</span>
                    </td>
                </tr>
            `;

            const listContainer = document.getElementById('m_faculty_list');
            listContainer.innerHTML = '<p style="text-align:center; padding: 20px; color:var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading professors...</p>';
            
            document.getElementById('assignModal').style.display = 'block';

            try {
                const url = `Approval_api.php?action=get_faculty_load&ay=${encodeURIComponent(req.academic_year)}&sched_id=${sched_id}`;
                const res = await fetch(url);
                const faculty = await res.json();
                
                listContainer.innerHTML = '';
                if(faculty.length === 0) {
                    listContainer.innerHTML = '<p style="text-align:center; padding: 20px; color:#ef4444; font-weight:600;">No available professors found.</p>';
                    return;
                }

                faculty.forEach(f => {
                    let hasConflict = f.has_conflict;
                    let load = parseFloat(f.current_load);
                    let max = parseFloat(f.max_units);
                    let isOverload = load >= max;
                    
                    let statusBadge = "";
                    let colorCode = "#10b981"; 
                    let cardClick = `onclick="selectFaculty(${f.id}, this)"`;
                    let opacity = "1";
                    
                    // CONFLICT UI LOGIC
                    if (hasConflict) {
                        statusBadge = `<span style="background:#fef2f2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800;" title="${f.conflict_details}">🔴 TIME CONFLICT</span>`;
                        colorCode = "#ef4444";
                        opacity = "0.5";
                        cardClick = `onclick="sysAlert('Cannot select this professor. ${f.conflict_details}', 'fa-ban', '#ef4444')" style="cursor: not-allowed;"`;
                    } else if (isOverload) {
                        statusBadge = `<span style="background:#fef2f2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800;">OVERLOAD</span>`;
                        colorCode = "#ef4444";
                    } else {
                        statusBadge = `<span style="background:#f0fdf4; color:#10b981; padding:2px 6px; border-radius:4px; font-size:10px; font-weight:800;">AVAILABLE</span>`;
                    }

                    let programText = f.program ? f.program : 'General';
                    
                    listContainer.innerHTML += `
                        <div class="faculty-card" ${cardClick} data-search="${f.full_name.toLowerCase()} ${programText.toLowerCase()}" style="opacity: ${opacity};">
                            <div>
                                <div style="font-weight: 700; font-size: 13px; color: var(--text-dark); margin-bottom: 3px;">
                                    ${f.full_name} 
                                    <span style="font-size:10px; color:var(--text-muted); font-weight:500; margin-left:5px; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${programText}</span>
                                </div>
                                <div style="font-size: 11px; color: var(--text-muted);">Current Load: <b style="color:${colorCode}">${load} / ${max} Units</b></div>
                            </div>
                            <div>${statusBadge}</div>
                        </div>
                    `;
                });
            } catch(e) {
                listContainer.innerHTML = '<p style="text-align:center; padding: 20px; color:#ef4444;">Error fetching professors.</p>';
            }
        }

        async function submitAssignment() {
            const fid = document.getElementById('m_selected_faculty').value;
            const sid = document.getElementById('m_sched_id').value;
            
            if(!fid) { 
                await sysAlert("Please select a professor from the list first.", "fa-exclamation-triangle", "#f59e0b"); 
                return; 
            }
            
            const btn = document.getElementById('btn_submit_assignment');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 6px;"></i> PROCESSING...';

            const fd = new FormData();
            fd.append('action', 'approve_cross_prof');
            fd.append('schedule_id', sid);
            fd.append('faculty_id', fid);
            
            try {
                const res = await fetch('Approval_api.php', { method:'POST', body: fd });
                const result = await res.json();
                
                if(result.status === 'error') {
                    await sysAlert(result.message, "fa-times-circle", "#ef4444");
                } else {
                    await sysAlert(result.message, "fa-check-circle", "#10b981");
                    document.getElementById('assignModal').style.display = 'none';
                    loadApprovals(true);
                    loadNotifications(true); 
                }
            } catch (e) {
                await sysAlert("Network error processing assignment.", "fa-wifi", "#ef4444");
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

        async function loadNotifications(isBackground = false) {
            if (isPollingNotifs) return;
            isPollingNotifs = true;

            const container = document.getElementById('notificationContainer');
            if (!isBackground) {
                container.innerHTML = '<p style="text-align: center; color: var(--text-muted);"><i class="fas fa-spinner fa-spin"></i> Loading notifications...</p>';
            }

            try {
                const [reqRes, outRes, histRes] = await Promise.all([
                    fetch('Approval_api.php?action=get_requests'),
                    fetch('Approval_api.php?action=get_outgoing_requests'),
                    fetch('Approval_api.php?action=get_history')
                ]);

                const reqData = await reqRes.json();
                const outData = await outRes.json();
                const histData = await histRes.json();

                const currentState = JSON.stringify({ reqData, outData, histData });

                if (currentState !== lastNotifState) {
                    lastNotifState = currentState;
                    let nHtml = '';

                    let incomingGroups = {};
                    let crossCount = 0;

                    reqData.forEach(req => {
                        const isCrossProf = (req.requested_department === userDept && req.approval_status === 'Approved');
                        if (isCrossProf) {
                            crossCount++;
                        } else if (req.department === userDept && req.approval_status === 'Pending') {
                            const k = `${req.program} ${req.section}`;
                            incomingGroups[k] = (incomingGroups[k] || 0) + 1;
                        }
                    });

                    if (crossCount > 0) {
                        nHtml += `
                            <div class="notif-row notif-warning">
                                <i class="fas fa-clock" style="font-size: 28px; color: #d97706;"></i>
                                <div>
                                    <div style="font-size: 13px; font-weight: 800; color: #d97706; text-transform: uppercase;">ACTION REQUIRED</div>
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text-dark); line-height: 1.4; margin-top:2px;">You have ${crossCount} incoming cross-department professor requests.</div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><i class="far fa-clock"></i> View in Pending Approvals above</div>
                                </div>
                            </div>
                        `;
                    }

                    for (const [sec, count] of Object.entries(incomingGroups)) {
                         nHtml += `
                            <div class="notif-row notif-warning">
                                <i class="fas fa-clock" style="font-size: 28px; color: #d97706;"></i>
                                <div>
                                    <div style="font-size: 13px; font-weight: 800; color: #d97706; text-transform: uppercase;">ACTION REQUIRED</div>
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text-dark); line-height: 1.4; margin-top:2px;">Schedule for ${sec} (${count} subjects) is waiting for your approval.</div>
                                </div>
                            </div>
                        `;
                    }
                    
                    outData.forEach(req => {
                        if (req.is_prof_waiting) {
                            nHtml += `
                                <div class="notif-row notif-awaiting">
                                    <i class="fas fa-paper-plane" style="font-size: 28px; color: #3b82f6;"></i>
                                    <div>
                                        <div style="font-size: 13px; font-weight: 800; color: #3b82f6; text-transform: uppercase;">AWAITING REPLY</div>
                                        <div style="font-size: 12px; font-weight: 600; color: var(--text-dark); line-height: 1.4; margin-top:2px;">Sent to ${req.requested_department}: ${req.course_code} - ${req.descriptive_title}</div>
                                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><i class="fas fa-spinner fa-spin"></i> Waiting for their approval...</div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                    
                    const topHist = Array.isArray(histData) ? histData.slice(0, 5) : []; 
                    
                    topHist.forEach(log => {
                        let isSuccess = log.action === 'UPDATE'; 
                        let iconClass = isSuccess ? 'fa-check-circle' : 'fa-times-circle';
                        let boxClass = isSuccess ? 'notif-success' : 'notif-danger';
                        let iconColor = isSuccess ? '#10b981' : '#ef4444';
                        let labelText = isSuccess ? 'APPROVED' : 'REJECTED';
                        
                        const dateFormatted = new Date(log.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

                        let detailsText = log.details.replace('✅ APPROVED: ', '').replace('✅ ASSIGNED: ', '').replace('❌ REJECTED: ', '');

                        nHtml += `
                            <div class="notif-row ${boxClass}">
                                <i class="fas ${iconClass}" style="font-size: 28px; color: ${iconColor};"></i>
                                <div>
                                    <div style="font-size: 13px; font-weight: 800; color: ${iconColor}; text-transform: uppercase;">${labelText}</div>
                                    <div style="font-size: 12px; font-weight: 600; color: var(--text-dark); line-height: 1.4; margin-top:2px;">${detailsText}</div>
                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;"><i class="far fa-clock"></i> ${dateFormatted} • Processed by: ${log.user_name}</div>
                                </div>
                            </div>
                        `;
                    });

                    if (nHtml === '') {
                        container.innerHTML = `<div style="text-align:center; padding: 30px; color:var(--text-muted);"><i class="fas fa-check-circle" style="font-size: 40px; color: #cbd5e1; margin-bottom: 15px; display:block;"></i>No recent decision notifications.</div>`;
                    } else {
                        container.innerHTML = nHtml;
                    }
                }
            } catch(e) {
                if (!isBackground) container.innerHTML = `<p style='color:red; text-align:center;'><i class="fas fa-wifi" style="margin-right:5px;"></i> Error loading notifications.</p>`;
            } finally {
                isPollingNotifs = false;
            }
        }
    </script>
</body>
</html>