<?php
// ============================================================
//  DASHBOARD — COSMOS (Program Head)
// ============================================================
session_start();
require '../db_connect.php';

// ----- Logout -----------------------------------------------
if (isset($_GET['logout']) && $_GET['logout'] === 'true') {
    $_SESSION = [];
    session_destroy();
    header('Location: ../Login/Login_index.php');
    exit();
}

// ----- Auth guard -------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login_index.php');
    exit();
}

// ----- Fetch user from DB safely ----------------------------
$userId = $_SESSION['user_id'];
$stmt   = $conn->prepare('SELECT * FROM users WHERE id = ?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res ? $res->fetch_assoc() : null;

if (!$userData) {
    echo 'User not found.';
    exit();
}

// ----- Prepare display variables ----------------------------
$userPosition = $userData['position']   ?? 'Staff';
$userDept     = $userData['department'] ?? 'Department';
$userEmail    = $userData['email']      ?? '';
$userProgram  = $userData['program']    ?? '';
$userName     = $_SESSION['full_name']  ?? '';

$isFaculty = ($userPosition === 'Faculty');

// Clean name — strip literal "null" strings left by DB errors
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

        .profile-info p  {
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

        /* ── MAIN CONTENT ────────────────────────────────────── */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px 40px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;}
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { color: var(--text-dark); font-size: 28px; font-weight: 800; margin-bottom: 4px; letter-spacing: -0.5px; }
        .header-title p { color: var(--text-muted); font-size: 14px; font-weight: 400; }
        
        /* Program Head badge in header */
        .ph-badge {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--accent-blue); color: var(--primary-blue);
            font-size: 11px; font-weight: 700; padding: 4px 12px;
            border-radius: var(--radius-pill); border: 1px solid #bfdbfe;
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        
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
            overflow-y: auto; overflow-x: hidden; white-space: normal; word-wrap: break-word; line-height: 1.3; transition: all 0.2s ease; border: 1px solid rgba(0,0,0,0.1); cursor: pointer;
        }
        .card::-webkit-scrollbar { width: 4px; }
        .card::-webkit-scrollbar-track { background: transparent; }
        .card::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.4); border-radius: 4px; }
        .card:hover { transform: scale(1.02) translateY(-2px); box-shadow: 0 8px 16px rgba(27,80,136,0.25); z-index: 50; }
        
        .pending-overlay { opacity: 0.95; border: 2px dashed rgba(255,255,255,0.7) !important; }
        .pending-badge-top { background: #f8fafc; color: #d97706; font-size: 9px; font-weight: 800; text-align: center; padding: 4px 0; border-radius: 4px; width: 100%; margin-bottom: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-transform: uppercase; letter-spacing: 0.5px; }

        /* Right Column (Side Panels) */
        .right-column { width: 320px; display: flex; flex-direction: column; gap: 20px; flex-shrink: 0; }
        
        .side-panel { background: var(--bg-card); border-radius: var(--radius-card); box-shadow: var(--shadow-card); border: 1px solid var(--border-color); display: flex; flex-direction: column; flex: 1; overflow: hidden; }
        .panel-header { padding: 20px; font-size: 14px; font-weight: 800; color: var(--text-dark); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); text-align: center; flex-shrink: 0; background: #f8fafc;}
        
        /* SCROLLABLE PANEL CONTENT */
        .panel-content { 
            padding: 15px; 
            overflow-y: auto; 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
        }
        .panel-content::-webkit-scrollbar { width: 6px; }
        .panel-content::-webkit-scrollbar-track { background: transparent; }
        .panel-content::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        .panel-content:hover::-webkit-scrollbar-thumb { background: var(--text-muted); }

        .ongoing-item { border: 1px solid var(--border-color); border-radius: 12px; padding: 12px; display: flex; align-items: center; gap: 12px; background: white; transition: 0.2s; cursor: pointer; }
        .ongoing-item:hover { border-color: var(--primary-blue); box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateX(-3px); }
        .ongoing-icon { width: 36px; height: 36px; border-radius: 50%; background: var(--accent-blue); color: var(--primary-blue); display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0; }
        
        .notif-item { border-left: 3px solid var(--primary-blue); background: white; padding: 12px 15px; border-radius: 0 12px 12px 0; border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); transition: 0.2s; cursor: pointer; }
        .notif-item:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateX(-3px); }
        .notif-item.pending { border-left-color: #d97706; background: #fef3c7; border:none;}
        .notif-item.awaiting { border-left-color: #3b82f6; background: #eff6ff; border:none;}
        .notif-item.success { border-left-color: #10b981; }
        .notif-item.danger { border-left-color: #ef4444; }

        /* ===== READ ONLY MODAL CSS ===== */
        .modal { display: none; position: fixed; z-index: 1001; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(15, 23, 42, 0.6); overflow: auto; padding: 20px; backdrop-filter: blur(4px); }
        .modal-content { background-color: var(--bg-card); margin: 2% auto; padding: 30px 40px; border: none; width: 98%; max-width: 1400px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        
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
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2005; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }
        .btn-modal { border: none; padding: 12px; border-radius: var(--radius-pill); font-weight: 700; font-size: 13px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; color: white; }

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
            header { padding: 20px; }
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
                <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i> <span>Classroom Monitoring</span></a></li>
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
            <div class="ph-badge">
                <i class="fas fa-user-tie"></i>
                Program Head &nbsp;·&nbsp; <?php echo htmlspecialchars($userProgram); ?>
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
                <div class="widget-card stat-widget clickable-widget" onclick="window.location.href='Profile_index.php'" title="View My Profile">
                    <div class="stat-title">MY NEXT CLASS</div>
                    <div class="stat-value" style="font-size: 24px;"><i class="fas fa-chalkboard" style="color:var(--text-dark);"></i> <span id="next-class-code">--</span></div>
                    <div class="stat-sub" style="font-size: 11px;"><span id="next-class-time">--</span> &bull; Room: <span id="next-class-room">--</span></div>
                </div>
                <div class="widget-card stat-widget clickable-widget" onclick="window.location.href='Download_index.php'" title="View My Workload">
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
                        <div class="panel-header">ONGOING CLASSES</div>
                        <div class="panel-content" id="ongoingContainer">
                            <p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
                        </div>
                    </div>

                    <div class="side-panel">
                        <div class="panel-header">APPROVAL STATUS</div>
                        <div class="panel-content" id="notifContainer">
                            <p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="modalBulk" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeM('modalBulk')" title="Close Preview">&times;</span>
            <h2 style="text-align: center; color: var(--text-dark); margin-bottom: 25px; font-weight: 800; font-size: 22px;">Class Schedule Details</h2>

            <div class="details-header-wrapper">
                <div class="details-row">
                    <div class="detail-group"><span class="detail-label">DEPARTMENT:</span> <input id="b_dept" class="detail-pill readonly-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">PROGRAM:</span> <input id="b_prog" class="detail-pill readonly-pill" readonly></div>
                    <div class="detail-group" id="b_major_group" style="display:none;"><span class="detail-label">MAJOR:</span> <input id="b_major" class="detail-pill readonly-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">YEAR LEVEL:</span> <input id="b_year" class="detail-pill readonly-pill" readonly></div>
                </div>
                <div class="details-row" style="margin-top: 15px;">
                    <div class="detail-group"><span class="detail-label">SEMESTER:</span> <input id="b_sem" class="detail-pill readonly-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">SECTION:</span> <input id="b_sec" class="detail-pill readonly-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">ACADEMIC YEAR:</span> <input id="b_ay" class="detail-pill readonly-pill" readonly></div>
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

    <div id="sysModalPrompt" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalPromptIcon" class="sys-modal-icon" style="font-size: 45px; margin-bottom: 15px;"></i>
            <div id="sysModalPromptMsg" class="sys-modal-msg" style="font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px;"></div>
            <input type="text" id="sysModalPromptInput" class="sys-modal-input"
                   placeholder="Type command here..." autocomplete="off">
            <div class="sys-modal-btns">
                <button id="sysModalPromptCancelBtn" class="btn-modal" style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalPromptOkBtn" class="btn-modal" style="flex:1; background: var(--primary-blue);">Submit</button>
            </div>
        </div>
    </div>

    <script>
        let dbRoomsList = [];
        let allData = [];
        let lastDataHash = '';

        // Identify if the user is a Program Head via PHP to lock UI logic
        const SESSION_POS = "<?php echo addslashes($userPosition); ?>";
        const SESSION_PROG = "<?php echo addslashes($userProgram); ?>";
        const SESSION_USER_ID = "<?php echo addslashes($userId); ?>";

        // --- DYNAMIC COLOR SYSTEM FOR OUT-OF-PROGRAM SCHEDULES ---
        const PROGRAM_PALETTE = [
            '#1b5088', '#0e7490', '#7c3aed', '#be185d', '#15803d', '#c2410c', 
            '#1d4ed8', '#a16207', '#0f766e', '#9333ea', '#b91c1c', '#0369a1', 
            '#4d7c0f', '#d97706', '#0891b2', '#7e22ce', '#047857', '#db2777'
        ];
        let programColorMap = {};

        function hashProgram(str) {
            let hash = 5381;
            for (let i = 0; i < str.length; i++) hash = ((hash << 5) + hash) + str.charCodeAt(i);
            return Math.abs(hash);
        }

        function getProgramColor(program) {
            if (!program) return '#64748b';
            const key = program.trim().toUpperCase();
            if (programColorMap[key]) return programColorMap[key];
            const idx = hashProgram(key) % PROGRAM_PALETTE.length;
            const color = PROGRAM_PALETTE[idx];
            programColorMap[key] = color;
            return color;
        }

        function darkenColor(hex, ratio) {
            let r = parseInt(hex.slice(1, 3), 16), g = parseInt(hex.slice(3, 5), 16), b = parseInt(hex.slice(5, 7), 16);
            r = Math.max(0, Math.round(r * (1 - ratio)));
            g = Math.max(0, Math.round(g * (1 - ratio)));
            b = Math.max(0, Math.round(b * (1 - ratio)));
            return `rgb(${r},${g},${b})`;
        }

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

        function sysPrompt(msg, expectedWord, icon = 'fa-exclamation-triangle', color = '#ef4444') {
            return new Promise(resolve => {
                document.getElementById('sysModalPromptMsg').innerHTML = msg;
                const iconEl = document.getElementById('sysModalPromptIcon');
                iconEl.className = `fas ${icon}`;
                iconEl.style.color = color;
                const inputEl = document.getElementById('sysModalPromptInput');
                inputEl.value = '';
                document.getElementById('sysModalPrompt').style.display = 'flex';
                inputEl.focus();
                
                const okBtn = document.getElementById('sysModalPromptOkBtn');
                okBtn.style.backgroundColor = color;
                
                document.getElementById('sysModalPromptCancelBtn').onclick = () => {
                    document.getElementById('sysModalPrompt').style.display = 'none';
                    resolve(false);
                };
                document.getElementById('sysModalPromptOkBtn').onclick = () => {
                    if (inputEl.value === expectedWord) {
                        document.getElementById('sysModalPrompt').style.display = 'none';
                        resolve(true);
                    } else {
                        alert(`Incorrect command. You must type '${expectedWord}' exactly (case sensitive).`);
                    }
                };
            });
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
                
                // Update New Widgets
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

                // Update Dropdowns
                if (data.academic_years && data.academic_years.length > 0) {
                    const select = document.getElementById('filterAY');
                    select.innerHTML = '<option value="All">Academic Year</option>';
                    data.academic_years.forEach(ay => select.innerHTML += `<option value="${ay}">${ay}</option>`);
                    select.value = data.academic_years[0]; 
                }

                if (data.programs && data.programs.length > 0 && document.getElementById('filterProg') && document.getElementById('filterProg').tagName === 'SELECT') {
                    const select = document.getElementById('filterProg');
                    select.innerHTML = ''; 

                    if ((SESSION_POS === "Program Head" || SESSION_POS === "Coordinator") && data.programs.length === 1) {
                        select.innerHTML = `<option value="${data.programs[0]}">${data.programs[0]}</option>`;
                        select.disabled = true;
                        select.style.opacity = '0.7';
                        select.style.cursor = 'not-allowed';
                        select.title = "Access limited to your assigned program.";
                    } else {
                        select.innerHTML = '<option value="All">All Programs</option>';
                        data.programs.forEach(p => {
                            select.innerHTML += `<option value="${p}">${p}</option>`;
                            getProgramColor(p); // pre-cache colors
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
                await sysAlert("Failed to load initial dashboard data.", "fa-wifi", "#ef4444");
            }
        }

        function flashLiveIndicator() {
            const el = document.getElementById('liveIndicator');
            el.style.background = '#a7f3d0';
            setTimeout(() => { el.style.background = '#d1fae5'; }, 1000);
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
                
                // Detect changes using a lightweight hash of JSON length + spot-check
                const newHash = JSON.stringify(fetched).length + '|' + (fetched[0]?.id ?? '') + '|' + fetched.length;

                if (!silent || newHash !== lastDataHash) {
                    lastDataHash = newHash;
                    allData = Array.isArray(fetched) ? fetched : [];
                    refreshView();

                    if (silent && newHash !== lastDataHash) {
                        flashLiveIndicator(); 
                    }
                }
            } catch (error) { 
                console.error('Error fetch schedules:', error); 
                if (!silent) await sysAlert("Failed to load schedule data.", "fa-wifi", "#ef4444");
            }
        }

        // === 4. FETCH RIGHT PANELS ===
        async function fetchSidePanels() {
            // Ongoing Classes
            try {
                const res = await fetch('Monitoring_api.php');
                if (!res.ok) throw new Error('Network response was not ok');
                const data = await res.json();
                
                let oHtml = '';
                if (Array.isArray(data)) {
                    data.forEach(item => {
                        // Check for the new hybrid states instead of 'Occupied'
                        if (item.state === 'Verified' || item.state === 'Ad-Hoc') {
                            
                            let displayDesc = '';
                            if (item.scheduled_class) {
                                displayDesc = `${item.scheduled_class.course_code || ''} (${item.scheduled_class.class_section || ''})`;
                            } else if (item.actual_occupant) {
                                displayDesc = item.actual_occupant.is_guest 
                                    ? `Guest: ${item.actual_occupant.full_name}` 
                                    : `Prof: ${item.actual_occupant.full_name}`;
                            } else {
                                displayDesc = 'Occupied';
                            }

                            oHtml += `
                                <div class="ongoing-item" onclick="window.location.href='Monitoring_index.php'" title="View in Monitoring" style="cursor: pointer;">
                                    <div class="ongoing-icon"><i class="fas fa-video"></i></div>
                                    <div>
                                        <div style="font-size:12px; font-weight:800; color:var(--text-dark);">${item.room_info?.room_name || 'Room'}</div>
                                        <div style="font-size:11px; font-weight:600; color:var(--text-muted);">${displayDesc}</div>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }
                document.getElementById('ongoingContainer').innerHTML = oHtml || '<p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">No ongoing classes right now.</p>';
            } catch(e) {
                console.error("Ongoing Widget Error:", e);
                document.getElementById('ongoingContainer').innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; margin-top:20px;">Failed to load data.</p>';
            }

            // Approval Status Notifications
            try {
                let nHtml = '';
                const notifRes = await fetch('Dashboard_api.php?action=get_notifications');
                const notifData = await notifRes.json();
                
                if (notifData.length > 0) {
                    notifData.forEach(n => {
                        let icon = 'fa-info-circle';
                        if (n.color === 'pending') icon = 'fa-clock';
                        if (n.color === 'awaiting') icon = 'fa-paper-plane';
                        if (n.color === 'success') icon = 'fa-check-circle';
                        if (n.color === 'danger') icon = 'fa-times-circle';

                        nHtml += `
                            <div class="notif-item ${n.color}" style="cursor: default;">
                                <div style="font-size:10px; font-weight:800; margin-bottom:2px; text-transform: uppercase;"><i class="fas ${icon}"></i> ${n.title}</div>
                                <div style="font-size:11px; color:var(--text-dark); font-weight:600; line-height:1.3;">${n.message}</div>
                            </div>
                        `;
                    });
                }
                
                // Add History for Approved/Rejected statuses
                const histReq = await fetch('../Admin/History_api.php?module=Approval&action=All');
                if (histReq.ok) {
                    const history = await histReq.json();
                    if (Array.isArray(history)) {
                        history.slice(0, 50).forEach(h => {
                            const approved = h.action === 'UPDATE';
                            const color = approved ? '#10b981' : '#ef4444';
                            const icon  = approved ? 'fa-check' : 'fa-times';
                            const label = approved ? 'Approved' : 'Rejected';
                            const detail = (h.details || '')
                                .replace('✅ APPROVED: ', '').replace('✅ ASSIGNED: ', '').replace('❌ REJECTED: ', '');
                            nHtml += `<div class="notif-item ${approved ? 'success' : 'danger'}" onclick="window.location.href='Approval_index.php'" title="Go to Approvals">
                                        <div style="font-size:10px; font-weight:800; color:${color}; margin-bottom:2px; text-transform:uppercase;"><i class="fas ${icon}"></i> ${label}</div>
                                        <div style="font-size:11px; color:var(--text-dark); font-weight:600; line-height:1.3;">${detail}</div>
                                    </div>`;
                        });
                    }
                }
                
                document.getElementById('notifContainer').innerHTML = nHtml || '<p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">All schedules are fully approved.</p>';
            } catch(e) {
                console.error("Notifications Widget Error:", e);
                document.getElementById('notifContainer').innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px; margin-top:20px;">Failed to load notifications.</p>';
            }
        }

        // === 5. RENDER CALENDAR ===
        function refreshView() {
            const searchTerm = document.getElementById('filterInput').value.toLowerCase();
            const prog = document.getElementById('filterProg') ? document.getElementById('filterProg').value : 'All';
            const sem = document.getElementById('filterSem').value;
            const day = document.getElementById('filterDay').value;
            const visibleEvents = [];

            const filteredData = allData.filter(d => {
                const term = searchTerm.toLowerCase();
                const textMatch = (d.course_code && d.course_code.toLowerCase().includes(term)) ||
                                  (d.professor && d.professor.toLowerCase().includes(term)) ||
                                  (d.section && d.section.toLowerCase().includes(term));
                
                const matchSem = (sem === 'All' || d.semester === sem);
                const matchDay = (day === 'All' || (d.days && d.days.includes(day)));

                // STRICT PROGRAM LOCK FOR PROGRAM HEADS
                let matchProg = false;
                if (SESSION_POS === 'Program Head' || SESSION_POS === 'Coordinator') {
                    matchProg = (d.program === SESSION_PROG) || (String(d.faculty_id) === String(SESSION_USER_ID));
                } else {
                    matchProg = (prog === 'All' || d.program === prog);
                }

                return textMatch && matchProg && matchDay && matchSem;
            });

            filteredData.forEach(d => {
                if(!d.start_time || !d.end_time) return;
                const [h, m] = d.start_time.split(':').map(Number);
                const [eh, em] = d.end_time.split(':').map(Number);
                const startMins = h * 60 + m;
                const endMins = eh * 60 + em;

                visibleEvents.push({
                    data: d, startHour: h, startMin: m, startMins: startMins,
                    endMins: endMins, height: (endMins - startMins) * (100/60), roomId: String(d.room_id || '0')
                });
            });

            // --- SMART VISUAL MERGER FOR DASHBOARD ---
            const mergedEvents = [];
            visibleEvents.forEach(ev => {
                let existing = mergedEvents.find(m => 
                    m.roomId === ev.roomId && 
                    m.startMins === ev.startMins && 
                    m.endMins === ev.endMins && 
                    m.data.course_code === ev.data.course_code &&
                    m.data.section === ev.data.section &&
                    m.data.program === ev.data.program
                );
                
                if (existing) {
                    let existingDays = existing.data.days.split(',').map(d=>d.trim());
                    let newDays = ev.data.days.split(',').map(d=>d.trim());
                    let combinedDays = [...new Set([...existingDays, ...newDays])];
                    
                    const dayOrder = { 'Mon':1, 'Tue':2, 'Wed':3, 'Thu':4, 'Fri':5, 'Sat':6, 'Sun':7 };
                    combinedDays.sort((a,b) => dayOrder[a] - dayOrder[b]);
                    
                    existing.data.days = combinedDays.join(', ');
                } else {
                    mergedEvents.push({ ...ev, data: { ...ev.data } });
                }
            });

            const activeRoomIds = new Set(mergedEvents.map(ev => String(ev.roomId)));
            
            // Generate virtual rooms for "Requests", "TBA", AND "University" rooms
            let displayRooms = [...dbRoomsList];
            mergedEvents.forEach(ev => {
                const rId = String(ev.roomId);
                const d = ev.data;
                if (rId === '0' && !displayRooms.some(r => String(r.id) === '0')) {
                    displayRooms.push({ id: '0', room_name: '⏳ TBA Room', status: 'Virtual' });
                } else if (d.assigned_room_display && !displayRooms.some(r => String(r.id) === rId)) {
                    displayRooms.push({ id: rId, room_name: d.assigned_room_display, status: 'Approved External' });
                } else if (rId.startsWith('REQ_') && !displayRooms.some(r => String(r.id) === rId)) {
                    const shortDept = rId.replace('REQ_College of ', '');
                    displayRooms.push({ id: rId, room_name: '⏳ Req: ' + shortDept, status: 'Virtual' });
                }
            });

            let sortedRooms = displayRooms.sort((a, b) => {
                const aActive = activeRoomIds.has(String(a.id));
                const bActive = activeRoomIds.has(String(b.id));
                if (aActive && !bActive) return -1; 
                if (!aActive && bActive) return 1;  
                return String(a.room_name).localeCompare(String(b.room_name));
            });

            const grid = document.getElementById('calendarGrid');

            if (sortedRooms.length === 0) {
                grid.style.gridTemplateColumns = "1fr";
                grid.innerHTML = `
                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; text-align: center; grid-column: 1 / -1; min-height: 400px;">
                        <i class="fas fa-door-closed" style="font-size: 50px; color: #cbd5e1; margin-bottom: 15px;"></i>
                        <p style="font-size: 18px; color: var(--primary-blue);">No Rooms Available</p>
                    </div>
                `;
                return;
            }

            grid.style.gridTemplateColumns = `80px repeat(${sortedRooms.length}, 180px)`;

            let html = '<div class="cal-header">Time</div>';
            
            // Add University Badge to headers
            sortedRooms.forEach(r => {
                let displayHtml = r.room_name;
                if (r.department === 'University') {
                    displayHtml += ` <span style="font-size:9px; background:#1e40af; color:white; padding:2px 4px; border-radius:4px; margin-left:6px;">Univ</span>`;
                }
                html += `<div class="cal-header">${displayHtml}</div>`;
            });

            for (let h = 7; h <= 20; h++) {
                let t = (h > 12 ? h - 12 : h) + (h >= 12 ? ' PM' : ' AM');
                html += `<div class="cal-time">${t}</div>`;
                sortedRooms.forEach(r => html += `<div class="cal-cell" id="c-${String(r.id).replace(/\s+/g, '_')}-${h}"></div>`);
            }
            grid.innerHTML = html;

            let grouped = {};
            mergedEvents.forEach(ev => {
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

            mergedEvents.forEach(ev => {
                const d = ev.data;
                const safeRoomId = String(ev.roomId).replace(/\s+/g, '_');
                const cell = document.getElementById(`c-${safeRoomId}-${ev.startHour}`);

                if (cell) {
                    const div = document.createElement('div');
                    div.className = 'card';
                    
                    // --- DYNAMIC COLOR LOGIC ---
                    const isOwnProgram = (d.program === SESSION_PROG);
                    const baseColor = isOwnProgram ? 'var(--primary-blue)' : getProgramColor(d.program);

                    if (d.approval_status === 'Pending') {
                        div.classList.add('pending-overlay');
                        div.style.backgroundColor = isOwnProgram ? '#4a74a5' : darkenColor(baseColor, 0.35);
                    } else {
                        div.style.backgroundColor = baseColor;
                    }
                    // -----------------------

                    div.style.top = `${ev.startMin * (100/60)}px`;
                    div.style.height = `${ev.height}px`;
                    div.style.left = `${ev.left}%`;
                    div.style.width = `${ev.width}%`;
                    
                    div.onclick = () => openReadOnlyModal(d);

                    const timeRng = `${convertTime(d.start_time)} - ${convertTime(d.end_time)}`;
                    let profName = (d.professor || '').toUpperCase();
                    let roomDisplay = '';
                    
                    if (String(ev.roomId).startsWith('REQ_') || String(ev.roomId) === '0' || d.assigned_room_display) {
                        const rMatch = displayRooms.find(r => String(r.id) === String(ev.roomId));
                        roomDisplay = `<span style="opacity:0.9; margin-bottom:2px; font-weight:700; display:block;">${rMatch ? rMatch.room_name : 'TBA Room'}</span>`;
                    }
                    
                    if (d.approval_status === 'Pending') {
                        let pendingBadge = `<div class="pending-badge-top"><i class="fas fa-hourglass-half"></i> PENDING</div>`;
                        let reqStatus = "PENDING DEAN'S APPROVAL";
                        if(d.requested_department) {
                            reqStatus = `PENDING: ${d.requested_department.toUpperCase()}`;
                        }

                        div.title = `${d.course_code} | ${d.days.toUpperCase()} | ${d.program} ${d.section} | ${reqStatus} | ${timeRng}`;
                        div.innerHTML = `
                            ${pendingBadge}
                            <b style="font-size:13px; margin-bottom:4px; display:block; line-height:1.2; letter-spacing:0.5px;">${d.course_code}</b>
                            <span style="font-size:10px; font-weight:700; background: rgba(0,0,0,0.15); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; width: max-content;">${d.days.toUpperCase()}</span>
                            <span style="margin-bottom:6px; display:block; font-size:11px;">${d.program}, ${d.section}</span>
                            ${roomDisplay}
                            <span style="margin-bottom:4px; display:flex; align-items:flex-start; gap:4px; font-weight:600; font-size:10px; line-height:1.2; text-transform:uppercase; color: #f1f5f9;"><i class="fas fa-hourglass-half" style="margin-top:1px; color:#fcd34d;"></i> ${reqStatus}</span>
                            <span style="font-weight:800; margin-top:auto; display:block; font-size:12px;">${timeRng}</span>
                        `;
                    } else {
                        if (profName !== 'TBA' && profName !== 'GENERAL EDUCATION' && !profName.startsWith('PROF.') && !profName.startsWith('⏳')) {
                            profName = 'PROF. ' + profName;
                        }
                        div.title = `${d.course_code} | ${d.days.toUpperCase()} | ${d.program} ${d.section} | ${profName} | ${timeRng}`;
                        div.innerHTML = `
                            <b style="font-size:13px; margin-bottom:4px; display:block; line-height:1.2; letter-spacing:0.5px;">${d.course_code}</b>
                            <span style="font-size:10px; font-weight:700; background: rgba(0,0,0,0.15); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px; width: max-content;">${d.days.toUpperCase()}</span>
                            <span style="margin-bottom:2px; display:block; font-size:11px;">${d.program}, ${d.section}</span>
                            ${roomDisplay}
                            <span style="margin-bottom:4px; display:block; font-weight:600; font-size:11px;">${profName}</span>
                            <span style="font-weight:800; margin-top:auto; display:block; font-size:12px;">${timeRng}</span>
                        `;
                    }
                    cell.appendChild(div);
                }
            });
        }

        // === 6. READ-ONLY MODAL ENGINE ===
        async function openReadOnlyModal(d) {
            document.getElementById('b_dept').value = d.department;
            document.getElementById('b_prog').value = d.program;
            
            // Display Major if it exists
            const bMajorGroup = document.getElementById('b_major_group');
            const bMajorInput = document.getElementById('b_major');
            if (d.major) {
                bMajorGroup.style.display = 'flex';
                bMajorInput.value = d.major;
            } else {
                bMajorGroup.style.display = 'none';
                bMajorInput.value = '';
            }

            document.getElementById('b_year').value = d.year_level;
            document.getElementById('b_sem').value = d.semester;
            document.getElementById('b_sec').value = d.section;
            document.getElementById('b_ay').value = d.academic_year;

            try {
                const res = await fetch(`CreateClass_api.php?action=find_courses&program=${encodeURIComponent(d.program)}&major=${encodeURIComponent(d.major || '')}&year_level=${encodeURIComponent(d.year_level)}&semester=${encodeURIComponent(d.semester)}`);
                if (!res.ok) throw new Error('Network response was not ok');
                const courses = await res.json();

                const sectionSchedules = allData.filter(s => 
                    s.program === d.program && 
                    s.year_level === d.year_level && 
                    s.section === d.section && 
                    s.academic_year === d.academic_year &&
                    s.semester === d.semester
                );

                renderReadOnlyRows(courses, sectionSchedules);
                document.getElementById('modalBulk').style.display = 'block';
            } catch (error) {
                console.error("Error opening read-only modal:", error);
                await sysAlert("Failed to load schedule details.", "fa-wifi", "#ef4444");
            }
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
            let rVal = dObj ? dObj.room_id : "";
            
            let rText = ""; 
            if (dObj && dObj.assigned_room_display) {
                rText = dObj.assigned_room_display;
            } else if (String(rVal).startsWith('REQ_')) {
                const shortReq = String(rVal).replace('REQ_College of ', '');
                rText = '⏳ Req: ' + shortReq;
            } else if (String(rVal) === '0' || !rVal) {
                rText = 'TBA Room';
            } else if (rVal) { 
                const rMatch = dbRoomsList.find(r => String(r.id) === String(rVal)); 
                rText = rMatch ? rMatch.room_name : ""; 
            }
            
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

        // === INITIALIZE ===
        document.addEventListener('DOMContentLoaded', async () => {
            if (window.innerWidth <= 992) {
                document.getElementById('menu-toggle').style.display = 'block';
            }

            await fetchInitialData();
            await fetchSchedules();
            await fetchSidePanels(); 

            // Poll the server every 10 seconds for fresh data
            setInterval(async () => {
                const bulkModal = document.getElementById('modalBulk');
                
                // Only auto-update if the user isn't actively looking at the Read-Only modal
                if (bulkModal && bulkModal.style.display !== 'block') {
                    await fetchSidePanels(); 
                    await fetchSchedules(true); // <-- True enables "silent" mode
                }
            }, 10000); 

            document.getElementById('filterInput').addEventListener('input', refreshView);
            ['filterAY', 'filterProg', 'filterSem', 'filterDay'].forEach(id => {
                if (document.getElementById(id)) {
                    document.getElementById(id).addEventListener('change', fetchSchedules);
                }
            });

            document.getElementById('menu-toggle').addEventListener('click', () => {
                document.body.classList.toggle(window.innerWidth <= 992 ? 'mobile-nav-active' : 'sidebar-collapsed');
            });
            document.querySelector('.backdrop').addEventListener('click', () => {
                document.body.classList.remove('mobile-nav-active');
            });
        });
    </script>
</body>
</html>