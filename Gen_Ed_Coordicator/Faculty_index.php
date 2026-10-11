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
if (!empty($userPic)) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userPic);
    $_SESSION['profile_picture'] = $picSrc;
} else {
    $picSrc = null;
}

// Security Flags
$isProgramHead = (stripos($userPosition, 'Program Head') !== false || stripos($userPosition, 'Coordinator') !== false);
$isAdmin = (stripos($userPosition, 'Admin') !== false);
$isDean = (stripos($userPosition, 'Dean') !== false);
$isLeadership  = ($isProgramHead || $isAdmin || $isDean);

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'College of Education' && stripos($userPosition, 'Coordinator') !== false) || 
           ($userDept === 'General Education') || 
           (stripos($userPosition, 'Gen') !== false);

$targetDept = $isGenEd ? 'General Education' : $userDept;
$isFaculty = ($userPosition === 'Faculty');

// =========================================================
// ROLE-BASED: Fetch users & calculate workload for LATEST TERM
// =========================================================
$usersByDepartment = [];

$latestTermCondition = "AND (s.academic_year, s.semester) = (
                            SELECT academic_year, semester FROM class_schedules 
                            ORDER BY academic_year DESC, 
                                     CASE WHEN semester = 'Second Semester' THEN 2 
                                          WHEN semester = 'First Semester' THEN 1 
                                          ELSE 0 END DESC 
                            LIMIT 1
                        )";

if ($isGenEd) {
    // Gen Ed Coordinator: Only show General Education Faculty, with units computed for latest term
    $sql = "SELECT u.*, 
                   (SELECT COALESCE(SUM(COALESCE(s.units, c.units)), 0) 
                    FROM class_schedules s 
                    JOIN courses c ON s.course_id = c.id 
                    WHERE s.faculty_id = u.id AND s.approval_status = 'Approved'
                    $latestTermCondition
                   ) as current_load,
                   (SELECT CONCAT(academic_year, ' - ', semester) 
                    FROM class_schedules 
                    ORDER BY academic_year DESC, 
                             CASE WHEN semester = 'Second Semester' THEN 2 
                                  WHEN semester = 'First Semester' THEN 1 
                                  ELSE 0 END DESC 
                    LIMIT 1
                   ) as active_term
            FROM users u 
            WHERE u.department = 'General Education' OR u.program = 'General Education' OR u.program LIKE '%Gen%Ed%'
            ORDER BY u.surname ASC";
    $stmt = $conn->prepare($sql);
} else {
    // Normal Dean/Program Head: Show only their own department, compute latest term units
    $sql = "SELECT u.*, 
                   (SELECT COALESCE(SUM(COALESCE(s.units, c.units)), 0) 
                    FROM class_schedules s 
                    JOIN courses c ON s.course_id = c.id 
                    WHERE s.faculty_id = u.id AND s.approval_status = 'Approved'
                    $latestTermCondition
                   ) as current_load,
                   (SELECT CONCAT(academic_year, ' - ', semester) 
                    FROM class_schedules 
                    ORDER BY academic_year DESC, 
                             CASE WHEN semester = 'Second Semester' THEN 2 
                                  WHEN semester = 'First Semester' THEN 1 
                                  ELSE 0 END DESC 
                    LIMIT 1
                   ) as active_term
            FROM users u 
            WHERE u.department = ? AND (u.program IS NULL OR (u.program != 'General Education' AND u.program NOT LIKE '%Gen%Ed%'))
            ORDER BY u.surname ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $userDept);
}

$stmt->execute();
$result = $stmt->get_result();

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
    <title>Faculty Directory - COSMOS</title>
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

       /* ── Profile section (bottom of sidebar) ─────────────── */
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
        
        .page-title { color: var(--text-dark); font-size: 20px; font-weight: 800; display: flex; align-items: center; gap: 15px; margin-bottom: 5px; text-transform: uppercase; }
        .dept-badge { display: inline-flex; align-items: center; padding: 6px 16px; border-radius: var(--radius-pill); font-size: 12px; font-weight: 700; color: white; background-color: <?php echo $themeColor; ?>; box-shadow: 0 4px 10px rgba(0,0,0,0.1); text-transform: none;}

        .search-container { background: var(--bg-card); border-radius: var(--radius-card); padding: 25px 30px; box-shadow: var(--shadow-card); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; margin-bottom: 25px; flex-shrink: 0; }
        .search-bar { flex: 1; position: relative; max-width: 400px; min-width: 250px; }
        .search-bar input { width: 100%; padding: 12px 16px 12px 45px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 13px; outline: none; transition: all 0.2s ease; background: #f8fafc; color: var(--text-dark); font-weight: 500;}
        .search-bar input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .search-bar i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; }

        /* ===== FACULTY GRID & CARDS ===== */
        .department-section { margin-bottom: 40px; }
        .faculty-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; }

        .account-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-card); padding: 30px 20px; text-align: center; transition: all 0.2s ease; cursor: pointer; position: relative; box-shadow: var(--shadow-card); }
        .account-card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px rgba(0,0,0,0.08); border-color: <?php echo $themeColor; ?>; }
        
        .card-avatar { width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 15px; overflow: hidden; border: 4px solid var(--accent-blue); display: flex; align-items: center; justify-content: center; color: white; font-size: 28px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); font-weight: 800; }
        .card-avatar img { width: 100%; height: 100%; object-fit: cover; }

        .card-role { font-weight: 700; color: var(--primary-blue); font-size: 11px; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;}
        .card-name { font-weight: 800; color: var(--text-dark); font-size: 15px; margin-bottom: 6px; line-height: 1.2;}
        .card-spec { font-size: 12px; color: var(--text-muted); font-weight: 500; }
        
        .workload-badge { display: inline-block; margin-top: 12px; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 700; background: #f8fafc; border: 1px solid var(--border-color); }

        .d-none { display: none !important; }
        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; background: var(--bg-card); border-radius: var(--radius-card); border: 1px dashed var(--border-color); }
        .empty-state i { font-size: 50px; color: #cbd5e1; margin-bottom: 15px; display: block; }
        .empty-state p { font-size: 18px; color: var(--text-dark); }
        .empty-state small { font-size: 13px; font-weight: normal; margin-top: 8px; display: block; }

        /* ── Consistent Table ────────────────────────────────── */
        .schedule-table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input); background: white; padding-bottom: 20px; transition: padding 0.3s ease; box-shadow: var(--shadow-card); }
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: center; table-layout: fixed; word-wrap: break-word; }
        .schedule-table th { background: #f8fafc; color: var(--text-muted); font-weight: 700; padding: 12px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; position: sticky; top: 0; }
        .schedule-table th:last-child { border-right: none; }
        .schedule-table td { padding: 12px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); vertical-align: middle; color: var(--text-dark); font-size: 12px; font-weight: 500;}
        .schedule-table td:last-child { border-right: none; }
        .schedule-table tbody tr:last-child td { border-bottom: none; }
        .schedule-table tbody tr:hover td { background: #f8fafc; }
        
        .table-input { width: 100%; border: 1px solid var(--border-color); padding: 8px 6px; border-radius: 8px; outline: none; font-size: 11px; font-weight: 600; text-align: center; color: var(--text-muted); box-sizing: border-box; background-color: #f1f5f9; cursor: not-allowed; }

        /* ════════════════════════════════════════════════════════
           MODALS
        ════════════════════════════════════════════════════════ */
        .modal { display: none; position: fixed; z-index: 1001; inset: 0; background: rgba(15, 23, 42, 0.6); overflow: auto; padding: 20px; backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--bg-card); margin: 2% auto; padding: 35px 40px; border: none; width: 95%; max-width: 1200px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .close-btn { color: var(--text-muted); float: right; font-size: 28px; font-weight: bold; cursor: pointer; margin-top: -10px; transition: 0.2s;}
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; }

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
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
                <li><a href="Faculty_index.php" class="active"><i class="fas fa-chalkboard-teacher"></i> <span>Faculty</span></a></li>
                <li><a href="Approval_index.php"><i class="fas fa-check-circle"></i> <span>Approval</span></a></li>
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
                        <img src="<?php echo $picSrc; ?>" class="profile-img" alt="Profile Picture" style="border: 2px solid rgba(255,255,255,0.2);">
                    <?php else: ?>
                        <div class="profile-img" style="border: 2px solid rgba(255,255,255,0.2);"><?php echo htmlspecialchars($initials); ?></div>
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
            <div class="search-container">
                <div class="page-title">
                    Faculty Directory
                    <span class="dept-badge">
                        <i class="fas fa-building" style="margin-right:6px;"></i>
                        <?php echo htmlspecialchars($targetDept); ?>
                    </span>
                </div>
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name, ID, or program...">
                </div>
            </div>

            <div id="accountsContainer">
                <?php if (!empty($usersByDepartment)): ?>
                    <?php foreach ($usersByDepartment as $departmentName => $users): ?>
                        <div class="department-section" data-department-group="<?php echo htmlspecialchars($departmentName); ?>">
                            <div class="faculty-grid">
                                <?php foreach ($users as $u): ?>
                                    <?php
                                        $fname = $u['first_name'] ?? '';
                                        $sname = $u['surname'] ?? '';
                                        $fullName  = trim($fname . ' ' . $sname);
                                        if (empty($fullName)) $fullName = 'Unknown User';
                                        
                                        $i1 = !empty($fname) ? substr($fname, 0, 1) : 'U';
                                        $i2 = !empty($sname) ? substr($sname, 0, 1) : '';
                                        $initials2 = strtoupper($i1 . $i2);

                                        $dataInfo = $u;
                                        unset($dataInfo['profile_picture']);
                                        if (!empty($u['profile_picture'])) {
                                            $dataInfo['pic_base64'] = base64_encode($u['profile_picture']);
                                        }
                                        $jsonInfo = htmlspecialchars(json_encode($dataInfo), ENT_QUOTES, 'UTF-8');

                                        if (!empty($u['profile_picture'])) {
                                            $avatarHtml = '<img src="data:image/jpeg;base64,' . $dataInfo['pic_base64'] . '" alt="Profile">';
                                        } else {
                                            $avatarHtml = $initials2;
                                        }
                                    ?>
                                    <div class="account-card"
                                         data-info="<?php echo $jsonInfo; ?>"
                                         data-department="<?php echo htmlspecialchars($u['department'] ?? ''); ?>"
                                         data-name="<?php echo strtolower(htmlspecialchars($fullName)); ?>"
                                         data-prog="<?php echo strtolower(htmlspecialchars($u['program'] ?? '')); ?>"
                                         data-empid="<?php echo strtolower(htmlspecialchars($u['employee_id'] ?? '')); ?>">

                                        <div class="card-avatar" style="background-color: <?php echo $themeColor; ?>;">
                                            <?php echo $avatarHtml; ?>
                                        </div>
                                        <div class="card-role"><?php echo htmlspecialchars($u['position'] ?? 'STAFF'); ?></div>
                                        <div class="card-name"><?php echo htmlspecialchars($fullName); ?></div>
                                        <div class="card-spec"><?php echo htmlspecialchars($u['program'] ?? 'N/A'); ?></div>
                                        
                                        <div class="workload-badge">
                                            <div style="color:var(--text-muted); font-size:9px; margin-bottom:2px; font-weight:600; text-transform:uppercase;">
                                                <?php echo htmlspecialchars($u['active_term'] ?? 'No Active Term'); ?>
                                            </div>
                                            Workload: 
                                            <span style="color: <?php echo ($u['current_load'] > 24) ? '#ef4444' : '#10b981'; ?>;">
                                                <?php echo $u['current_load'] ?: 0; ?> / 24 Units
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash"></i>
                        <p>No Faculty Found for <?php echo htmlspecialchars($targetDept); ?></p>
                        <small>There are currently no faculty records in your department.<br>Records added to your department will appear here.</small>
                    </div>
                <?php endif; ?>
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
                <button type="button" onclick="closeModal('scheduleModal')" class="btn-modal" style="background: #e2e8f0; color: #475569; padding: 12px 30px;">
                    Close Preview
                </button>
            </div>
        </div>
    </div>

    <script>
        // --- Helper: Format Time ---
        function formatTime(timeStr) {
            if(!timeStr) return '';
            let [h, m] = timeStr.split(':');
            h = parseInt(h);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        }

        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        // --- Sidebar Toggle ---
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });
        document.querySelector('.backdrop').addEventListener('click', () => { 
            document.body.classList.remove('mobile-nav-active'); 
        });

        // --- Search Logic ---
        document.getElementById('searchInput').addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.account-card').forEach(card => {
                const match = card.dataset.name.includes(term)
                           || card.dataset.prog.includes(term)
                           || card.dataset.empid.includes(term);
                card.classList.toggle('d-none', !match);
            });

            document.querySelectorAll('.department-section').forEach(section => {
                const visible = section.querySelectorAll('.account-card:not(.d-none)').length > 0;
                section.classList.toggle('d-none', !visible);
            });
        });

        // --- Card Click Logic -> Open Read-Only Schedule Modal ---
        document.getElementById('accountsContainer').addEventListener('click', function (e) {
            const card = e.target.closest('.account-card');
            if (!card) return;

            const p = JSON.parse(card.getAttribute('data-info'));
            openScheduleModal(p);
        });

        // --- Fetch and Display Schedule with Beautiful Read-Only UI ---
        async function openScheduleModal(prof) {
            const cleanName = `${prof.first_name || ''} ${prof.surname || ''}`.trim();
            document.getElementById('schedModalName').innerText = `${cleanName}'s Schedule`;
            
            let loadColor = prof.current_load > 24 ? '#ef4444' : '#10b981';
            let activeTermText = prof.active_term || 'No Active Term';
            document.getElementById('schedModalLoad').innerHTML = `Assigned Workload (${activeTermText}): <strong style="color:${loadColor};">${prof.current_load || 0} / 24 Units</strong>`;
            
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

        /* =========================================================================
           COLLABORATIVE POLLING LOGIC (Auto-Refresh without flickering)
        ========================================================================= */
        let currentContainerHtml = document.getElementById('accountsContainer').innerHTML;
        let isPolling = false;

        async function pollForChanges() {
            if (isPolling) return;
            isPolling = true;

            try {
                const response = await fetch(window.location.href, { cache: 'no-store' });
                if (!response.ok) return;
                
                const htmlText = await response.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(htmlText, 'text/html');
                const newContainer = doc.getElementById('accountsContainer');

                if (newContainer) {
                    const newHtml = newContainer.innerHTML;
                    if (newHtml !== currentContainerHtml) {
                        document.getElementById('accountsContainer').innerHTML = newHtml;
                        currentContainerHtml = newHtml;
                        
                        const searchInput = document.getElementById('searchInput');
                        if (searchInput.value.trim() !== '') {
                            searchInput.dispatchEvent(new Event('input'));
                        }
                    }
                }
            } catch (error) {
                console.warn('Background sync failed:', error);
            } finally {
                isPolling = false;
            }
        }

        // Check for updates automatically every 5 seconds
        setInterval(pollForChanges, 5000);

    </script>
</body>
</html>