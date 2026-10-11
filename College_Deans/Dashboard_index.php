<?php
// ============================================================
//  DASHBOARD — COSMOS
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

// --- GEN ED DETECTOR ---
$isGenEd = ($userDept === 'General Education' || stripos($userPosition, 'Gen') !== false || (stripos($userPosition, 'Coordinator') !== false && stripos($userDept, 'Education') !== false));
$targetDept = $isGenEd ? 'General Education' : $userDept;

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

// Profile picture
$picSrc = null;
if (!empty($userData['profile_picture'])) {
    $picSrc = 'data:image/jpeg;base64,' . base64_encode($userData['profile_picture']);
    $_SESSION['profile_picture'] = $picSrc;
}

// Department → theme colour
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
    'General Education'                => '#8b5cf6', 
    'MIS'                              => '#6366f1',
    'University'                       => '#1e40af'
];
$themeColor = $departmentColors[$targetDept] ?? 'var(--primary-blue)';

// Department → logo path
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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');

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
            --sidebar-min:        72px;
            --sidebar-max:        260px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            display: flex;
            height: 100vh;
            background-color: var(--bg-main);
            color: var(--text-dark);
            overflow: hidden;
        }

        /* ── SIDEBAR ─────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-min);
            background: var(--bg-card);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow: hidden;
            z-index: 1000;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar:hover {
            width: var(--sidebar-max);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        }

        .logo-section { padding: 20px 10px; text-align: center; flex-shrink: 0; }
        .logo-section img { width: 50px; border-radius: 12px; display: block; margin: 0 auto; transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .sidebar:hover .logo-section img { width: 140px; }

        .menu-label {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
            color: var(--text-muted); margin: 10px 0 4px; padding: 0 20px;
            white-space: nowrap; overflow: hidden; opacity: 0; max-height: 0;
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; }

        .nav-menu { list-style: none; padding: 0; }
        .nav-menu li a {
            display: flex; align-items: center; justify-content: center;
            padding: 11px 0; margin: 3px 10px; color: var(--text-muted);
            text-decoration: none; font-size: 14px; font-weight: 500;
            border-radius: var(--radius-input); white-space: nowrap; overflow: hidden;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.2s;
        }
        .sidebar:hover .nav-menu li a { padding: 11px 14px; justify-content: flex-start; }
        .nav-menu li a:hover  { background: #f8fafc; color: var(--primary-blue); }
        .nav-menu li a.active { background: var(--accent-blue); color: var(--primary-blue); font-weight: 600; }

        .nav-menu i { font-size: 18px; width: 22px; flex-shrink: 0; text-align: center; margin-right: 0; transition: margin-right 0.3s ease; }
        .sidebar:hover .nav-menu i { margin-right: 12px; }

        .nav-menu li a span { display: inline-block; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .nav-menu li a span { width: auto; opacity: 1; }

        /* Profile section */
        .profile-section { margin-top: auto; position: relative; padding: 36px 10px 16px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; }

        .dept-logo-bubble {
            position: absolute; top: -26px; left: 50%; transform: translateX(-50%);
            width: 50px; height: 50px; background: white; border-radius: 50%;
            border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0,0,0,0.12), 0 0 0 3px white;
            display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10;
            transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease;
        }
        .dept-logo-bubble::after { content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%); border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid white; filter: drop-shadow(0 2px 2px rgba(0,0,0,0.08)); }
        .sidebar:hover .dept-logo-bubble { width: 56px; height: 56px; top: -30px; box-shadow: 0 6px 18px rgba(0,0,0,0.15), 0 0 0 3px white; }
        .dept-logo-bubble img { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback { font-size: 20px; color: var(--primary-blue); }

        .profile-row { display: flex; align-items: center; gap: 10px; width: 100%; padding: 0 2px; }
        .profile-img { width: 42px; height: 42px; border-radius: 50%; background: var(--primary-blue); color: white; font-size: 16px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; object-fit: cover; }

        .profile-info { flex: 1; min-width: 0; width: 0; opacity: 0; overflow: hidden; white-space: nowrap; transition: opacity 0.2s ease 0.05s, width 0.3s ease; }
        .sidebar:hover .profile-info { width: auto; opacity: 1; }
        .profile-info h4 { font-size: 13px; font-weight: 700; color: var(--text-dark); margin: 0; overflow: hidden; text-overflow: ellipsis; }
        .profile-info p  { font-size: 11px; color: var(--text-muted); margin: 2px 0 0; overflow: hidden; text-overflow: ellipsis; }

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
        .header-title p  { font-size: 14px; font-weight: 400; color: var(--text-muted); }

        .container { flex: 1; overflow: hidden; padding: 0 40px 40px; display: flex; flex-direction: column; gap: 20px; }

        .page-title-container { display: flex; align-items: center; gap: 15px; margin-bottom: 5px; }
        .page-title { font-size: 20px; font-weight: 800; text-transform: uppercase; color: var(--text-dark); margin: 0; }

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

        /* ── STAT WIDGETS ────────────────────────────────────── */
        .widgets-row { display: flex; gap: 20px; flex-shrink: 0; }
        .widget-card { flex: 1; background: var(--bg-card); border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); padding: 20px 25px; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: all 0.2s ease; }
        .clickable-widget { cursor: pointer; }
        .clickable-widget:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(27,80,136,0.1); border-color: var(--primary-blue); }
        .time-widget { flex: 1.5; align-items: flex-start; padding-left: 25px; }
        .time-date  { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 5px; }
        .time-clock { font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1; letter-spacing: -1px; }
        .stat-title { width: 100%; text-align: left; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 6px; }
        .stat-value { display: flex; justify-content: space-between; align-items: center; width: 100%; font-size: 32px; font-weight: 900; color: var(--text-dark); line-height: 1; }
        .stat-value i { font-size: 24px; opacity: 0.8; }
        .stat-sub { width: 100%; text-align: left; font-size: 10px; font-weight: 600; color: var(--text-muted); margin-top: 6px; }

        /* ── LAYOUT GRID ─────────────────────────────────────── */
        .layout-grid { display: flex; gap: 20px; flex: 1; overflow: hidden; }
        .left-column  { flex: 3; min-width: 0; display: flex; flex-direction: column; gap: 15px; overflow: hidden; }

        /* Filters */
        .controls { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex-shrink: 0; }
        .search-bar { flex: 1; position: relative; min-width: 200px; }
        .search-bar input { width: 100%; padding: 12px 16px 12px 40px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 13px; font-weight: 500; background: var(--bg-card); color: var(--text-dark); outline: none; transition: border-color 0.2s, box-shadow 0.2s; }
        .search-bar input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .search-bar i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .filter-select { padding: 12px 35px 12px 18px; border: 1px solid var(--border-color); border-radius: var(--radius-pill); font-size: 12px; font-weight: 600; background-color: var(--bg-card); color: var(--text-dark); cursor: pointer; outline: none; appearance: none; background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E"); background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto; transition: border-color 0.2s, box-shadow 0.2s; }
        .filter-select:hover, .filter-select:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }

        /* Calendar */
        .calendar-wrapper { flex: 1; overflow: auto; background: var(--bg-card); border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); }
        .cal-grid { display: grid; grid-template-rows: 50px repeat(14, 100px); width: max-content; min-width: 100%; position: relative; }
        .cal-header { background: #f8fafc; border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); position: sticky; top: 0; z-index: 100; }
        .cal-header:first-child { position: sticky; left: 0; top: 0; z-index: 120; }
        .cal-time { background: #f8fafc; border-right: 1px solid var(--border-color); border-bottom: 1px dashed var(--border-color); display: flex; align-items: flex-start; justify-content: flex-end; padding: 10px; height: 100px; font-size: 11px; font-weight: 600; color: var(--text-muted); position: sticky; left: 0; z-index: 110; }
        .cal-cell { position: relative; height: 100px; background: var(--bg-card); border-right: 1px solid var(--border-color); border-bottom: 1px dashed var(--border-color); }

        /* Cards */
        .card {
            position: absolute; width: 94%; left: 3%; border-radius: 8px;
            padding: 10px 12px; font-size: 11px; line-height: 1.3;
            border: 1px solid rgba(0,0,0,0.1); box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            z-index: 5; display: flex; flex-direction: column;
            justify-content: flex-start; align-items: flex-start;
            text-align: left; overflow-y: auto; overflow-x: hidden;
            white-space: normal; word-wrap: break-word; cursor: pointer;
            transition: all 0.2s ease; color: white;
        }
        .card::-webkit-scrollbar { width: 4px; }
        .card::-webkit-scrollbar-track { background: transparent; }
        .card::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.4); border-radius: 4px; }
        .card:hover { transform: scale(1.02) translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 50; }
        .pending-overlay { opacity: 0.95; border: 2px dashed rgba(255,255,255,0.7) !important; }
        .pending-badge-top { width: 100%; background: #f8fafc; color: #d97706; font-size: 9px; font-weight: 800; text-align: center; text-transform: uppercase; letter-spacing: 0.5px; padding: 4px 0; border-radius: 4px; margin-bottom: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }

        /* Right column */
        .right-column { width: 320px; flex-shrink: 0; display: flex; flex-direction: column; gap: 20px; }
        .side-panel { flex: 1; overflow: hidden; display: flex; flex-direction: column; background: var(--bg-card); border-radius: var(--radius-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); }
        .panel-header { flex-shrink: 0; padding: 15px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color); font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-dark); text-align: center; }
        
        /* SCROLLABLE PANEL CONTENT */
        .panel-content { 
            flex: 1; 
            overflow-y: auto; 
            padding: 15px; 
            display: flex; 
            flex-direction: column; 
            gap: 10px; 
        }
        .panel-content::-webkit-scrollbar { width: 6px; }
        .panel-content::-webkit-scrollbar-track { background: transparent; }
        .panel-content::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 4px; }
        .panel-content:hover::-webkit-scrollbar-thumb { background: var(--text-muted); }

        .ongoing-item { display: flex; align-items: center; gap: 12px; padding: 10px 12px; background: white; border: 1px solid var(--border-color); border-radius: 12px; cursor: pointer; transition: all 0.2s; }
        .ongoing-item:hover { border-color: var(--primary-blue); box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateX(-3px); }
        .ongoing-icon { flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; background: var(--accent-blue); color: var(--primary-blue); font-size: 12px; display: flex; align-items: center; justify-content: center; }

        .notif-item { padding: 10px 12px; border-left: 3px solid var(--primary-blue); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); border-radius: 0 12px 12px 0; background: white; cursor: pointer; transition: all 0.2s; }
        .notif-item:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.05); transform: translateX(-3px); }
        .notif-item.pending  { border-left-color: #d97706; background: #fef3c7; border: none; }
        .notif-item.awaiting { border-left-color: #3b82f6; background: #eff6ff; border: none; }
        .notif-item.success  { border-left-color: #10b981; }
        .notif-item.danger   { border-left-color: #ef4444; }

        /* ── MODAL ───────────────────────────────────────────── */
        .modal { display: none; position: fixed; inset: 0; z-index: 1001; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); overflow: auto; padding: 20px; }
        .modal-content { background: var(--bg-card); width: 98%; max-width: 1400px; margin: 2% auto; padding: 30px 40px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .close-btn { float: right; font-size: 28px; font-weight: bold; color: var(--text-muted); cursor: pointer; margin-top: -10px; transition: color 0.2s, transform 0.2s; }
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        .details-header-wrapper { display: flex; flex-direction: column; align-items: center; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px dashed var(--border-color); }
        .details-row { display: flex; align-items: center; justify-content: center; flex-wrap: wrap; gap: 30px; width: 100%; }
        .detail-group { display: flex; align-items: center; gap: 12px; }
        .detail-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); white-space: nowrap; }
        .detail-pill { width: 180px; padding: 10px 15px; border-radius: var(--radius-pill); border: 1px solid var(--border-color); background: #f8fafc; font-size: 13px; font-weight: 600; color: var(--text-dark); text-align: center; outline: none; cursor: default; }

        .schedule-table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input); background: white; padding-bottom: 20px; }
        .schedule-table { width: 100%; border-collapse: separate; border-spacing: 0; text-align: center; table-layout: fixed; word-wrap: break-word; }
        .schedule-table th { padding: 12px 6px; background: #f8fafc; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .schedule-table th:last-child { border-right: none; }
        .schedule-table td { padding: 8px 6px; border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color); font-size: 12px; color: var(--text-dark); vertical-align: middle; }
        .schedule-table td:last-child { border-right: none; }
        .schedule-table tbody tr:last-child td { border-bottom: none; }
        .schedule-table tbody tr:hover td { background: #f8fafc; }
        .table-input { width: 100%; padding: 8px 6px; border: 1px solid var(--border-color); border-radius: 8px; background: #f1f5f9; font-size: 11px; font-weight: 600; color: var(--text-dark); text-align: center; outline: none; cursor: not-allowed; box-sizing: border-box; }

        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 999; }

        /* ── RESPONSIVE ──────────────────────────────────────── */
        @media (max-width: 1200px) {
            .layout-grid  { flex-direction: column; }
            .left-column  { flex: none; height: 600px; }
            .right-column { width: 100%; flex-direction: row; flex-wrap: wrap; }
            .side-panel   { min-width: 300px; }
        }
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); }
            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }
            body.mobile-nav-active .sidebar { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,0.15); }
            body.mobile-nav-active .sidebar .nav-menu li a             { justify-content: flex-start; padding: 11px 14px; }
            body.mobile-nav-active .sidebar .nav-menu i                { margin-right: 12px; }
            body.mobile-nav-active .sidebar .nav-menu li a span        { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .menu-label                { opacity: 1; max-height: 30px; }
            body.mobile-nav-active .sidebar .profile-info              { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logout-btn                { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logo-section img          { width: 140px; }
            .backdrop { display: block; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
            body.mobile-nav-active .backdrop { opacity: 1; pointer-events: auto; }
            #menu-toggle { display: block !important; }
            .container   { padding: 20px; }
            .widgets-row { flex-wrap: wrap; }
            .widget-card { min-width: calc(50% - 10px); }
            .time-widget { min-width: 100%; }
        }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="logo-section">
            <img src="../logo.png" alt="URS Logo" onerror="this.style.display='none'">
        </div>

        <div class="menu-label">Menu</div>
        <ul class="nav-menu">
            <li><a href="Dashboard_index.php" class="active"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="CreateClass_index.php"><i class="fas fa-plus-circle"></i><span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i><span>Classroom Monitoring</span></a></li>
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
                <a href="Profile_index.php" title="Go to My Profile"
                   style="display:flex; align-items:center; gap:10px; text-decoration:none; flex:1; min-width:0; transition:opacity 0.2s;"
                   onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                    <?php if ($picSrc): ?>
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
                <i id="menu-toggle" class="fas fa-bars"></i>
                <div class="header-title">
                    <h1>University of Rizal System</h1>
                    <p>Classroom Organization, Scheduling and Monitoring Operations System</p>
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
                <div class="widget-card stat-widget clickable-widget" onclick="window.location.href='Faculty_index.php'" title="Manage Faculty">
                    <div class="stat-title">Faculty</div>
                    <div class="stat-value">
                        <i class="fas fa-chalkboard-teacher" style="color:var(--text-dark);"></i>
                        <span id="faculty-count">0</span>
                    </div>
                </div>
                <div class="widget-card stat-widget clickable-widget" onclick="window.location.href='Room_index.php'" title="Manage Rooms">
                    <div class="stat-title">Rooms</div>
                    <div class="stat-value">
                        <i class="fas fa-door-open" style="color:var(--text-dark);"></i>
                        <span id="rooms-count">0</span>
                    </div>
                </div>
                <div class="widget-card stat-widget clickable-widget" onclick="window.location.href='Monitoring_index.php'" title="View Live Monitoring">
                    <div class="stat-title">Utilization</div>
                    <div class="stat-value">
                        <i class="fas fa-chart-pie" style="color:var(--primary-blue);"></i>
                        <span id="util-rate">0%</span>
                    </div>
                    <div class="stat-sub"><span id="occ-count">0</span> Rooms in use right now</div>
                </div>
            </div>

            <div class="layout-grid">
                <div class="left-column">
                    <div class="controls">
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" id="filterInput" placeholder="Search subject, major, prof...">
                        </div>
                        <select id="filterAY" class="filter-select"></select>
                        <select id="filterSem" class="filter-select">
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                        <select id="filterProg" class="filter-select">
                            <option value="All">All Programs</option>
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
                        <div class="panel-header">
                            <i class="fas fa-video" style="color:var(--primary-blue); margin-right:5px;"></i>
                            Ongoing Classes
                        </div>
                        <div class="panel-content" id="ongoingContainer">
                            <p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </p>
                        </div>
                    </div>

                    <div class="side-panel">
                        <div class="panel-header">
                            <i class="fas fa-bell" style="color:var(--primary-blue); margin-right:5px;"></i>
                            Notifications
                        </div>
                        <div class="panel-content" id="notifContainer">
                            <p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="modalBulk" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalBulk')" title="Close">&times;</span>
            <h2 style="text-align:center; color:var(--text-dark); margin-bottom:25px; font-weight:800; font-size:22px;">
                Class Schedule Details
            </h2>

            <div class="details-header-wrapper">
                <div class="details-row">
                    <div class="detail-group"><span class="detail-label">Department:</span><input id="b_dept" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">Program:</span><input id="b_prog" class="detail-pill" readonly></div>
                    <div class="detail-group" id="b_major_group" style="display:none;"><span class="detail-label">Major:</span><input id="b_major" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">Year Level:</span><input id="b_year" class="detail-pill" readonly></div>
                </div>
                <div class="details-row" style="margin-top:15px;">
                    <div class="detail-group"><span class="detail-label">Semester:</span><input id="b_sem" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">Section:</span><input id="b_sec" class="detail-pill" readonly></div>
                    <div class="detail-group"><span class="detail-label">Academic Year:</span><input id="b_ay" class="detail-pill" readonly></div>
                </div>
            </div>

            <div class="schedule-table-wrapper">
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th style="width:10%;">Day</th>
                            <th style="width:16%;">Time</th>
                            <th style="width:10%;">Course Code</th>
                            <th style="width:25%;">Subject Description</th>
                            <th style="width:4%;">Unit</th>
                            <th colspan="2" style="width:8%; padding:5px;">Hours/Week<br><span style="font-size:9px; font-weight:600; color:#64748b;">Lec &nbsp;|&nbsp; Lab</span></th>
                            <th style="width:14%;">Room</th>
                            <th style="width:16%;">Professor</th>
                        </tr>
                    </thead>
                    <tbody id="bulkRowsContainer"></tbody>
                </table>
            </div>

            <div style="display:flex; justify-content:center; margin-top:25px;">
                <button type="button" onclick="closeModal('modalBulk')"
                        style="display:flex; align-items:center; gap:8px; padding:12px 30px; background:white; color:var(--text-dark); border:1px solid var(--border-color); border-radius:var(--radius-pill); font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 2px 10px rgba(0,0,0,0.05); transition:all 0.2s;"
                        onmouseover="this.style.backgroundColor='#f1f5f9'; this.style.transform='translateY(-2px)';"
                        onmouseout="this.style.backgroundColor='white'; this.style.transform='none';">
                    <i class="fas fa-times" style="color:#ef4444;"></i> Close Preview
                </button>
            </div>
        </div>
    </div>

    <div class="backdrop"></div>

    <script>
        // ── Shared state ──────────────────────────────────────
        const currentUserDept = "<?php echo addslashes($targetDept); ?>";
        let dbRoomsList     = [];
        let allData         = [];
        let totalRoomsCount = 0;
        let roomsMap        = {};
        let lastDataHash    = '';          // for change detection
        let programColorMap = {};          // program → hex color

        // ────────────────────────────────────────────────────
        // PROGRAM COLOR SYSTEM
        // Deterministic hash → picks from a palette of 18 distinct, readable colours.
        // Same program name always gets the same colour across all sessions.
        // ────────────────────────────────────────────────────
        const PROGRAM_PALETTE = [
            '#1b5088', // deep blue
            '#0e7490', // teal
            '#7c3aed', // violet
            '#be185d', // rose
            '#15803d', // forest green
            '#c2410c', // burnt orange
            '#1d4ed8', // royal blue
            '#a16207', // amber-dark
            '#0f766e', // dark teal
            '#9333ea', // purple
            '#b91c1c', // crimson
            '#0369a1', // sky blue
            '#4d7c0f', // olive green
            '#d97706', // golden
            '#0891b2', // cyan
            '#7e22ce', // dark violet
            '#047857', // emerald
            '#db2777', // pink
        ];

        function hashProgram(str) {
            // Simple but stable djb2 hash
            let hash = 5381;
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) + hash) + str.charCodeAt(i);
                hash = hash & 0xffffffff; // keep 32-bit
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

        function darkenColor(hex, ratio) {
            const r = parseInt(hex.slice(1, 3), 16);
            const g = parseInt(hex.slice(3, 5), 16);
            const b = parseInt(hex.slice(5, 7), 16);
            const dr = Math.max(0, Math.round(r * (1 - ratio)));
            const dg = Math.max(0, Math.round(g * (1 - ratio)));
            const db = Math.max(0, Math.round(b * (1 - ratio)));
            return `rgb(${dr},${dg},${db})`;
        }

        // ────────────────────────────────────────────────────
        // 1. Live clock
        // ────────────────────────────────────────────────────
        function updateClock() {
            const now    = new Date();
            const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE',
                            'JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
            const days   = ['SUNDAY','MONDAY','TUESDAY','WEDNESDAY','THURSDAY','FRIDAY','SATURDAY'];
            document.getElementById('dash-date').innerText =
                `${months[now.getMonth()]} ${now.getDate()}, ${now.getFullYear()} | ${days[now.getDay()]}`;
            document.getElementById('dash-time').innerText =
                now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).replace(' ', '');
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ────────────────────────────────────────────────────
        // 2. Init data (rooms, AY, programs, counts)
        // ────────────────────────────────────────────────────
        async function fetchInitialData() {
            try {
                const response = await fetch('Dashboard_api.php?action=init_data');
                if (!response.ok) return;
                const data = await response.json();
                if (!data || data.status === 'error') return;

                document.getElementById('faculty-count').innerText = data.counts?.faculty || 0;
                totalRoomsCount = data.counts?.rooms || 0;
                document.getElementById('rooms-count').innerText = totalRoomsCount;

                if (Array.isArray(data.academic_years) && data.academic_years.length > 0) {
                    const select = document.getElementById('filterAY');
                    select.innerHTML = '<option value="All">Academic Year</option>';
                    data.academic_years.forEach(ay => {
                        select.innerHTML += `<option value="${ay}">${ay}</option>`;
                    });
                    select.value = data.academic_years[0];
                }

                if (Array.isArray(data.programs) && data.programs.length > 0) {
                    const select = document.getElementById('filterProg');
                    select.innerHTML = '<option value="All">All Programs</option>';
                    data.programs.forEach(p => {
                        select.innerHTML += `<option value="${p}">${p}</option>`;
                        getProgramColor(p); // pre-cache colors
                    });
                }

                dbRoomsList = Array.isArray(data.rooms) ? data.rooms : [];
                dbRoomsList.forEach(r => { roomsMap[r.id] = r.room_name; });

                // Default day to today
                const dayNames  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                const todayName = dayNames[new Date().getDay()];
                document.getElementById('filterDay').value = todayName !== 'Sun' ? todayName : 'All';

                // Default semester by month
                const month = new Date().getMonth() + 1;
                const semSelect = document.getElementById('filterSem');
                if      (month >= 1 && month <= 5) semSelect.value = '2nd Semester';
                else if (month >= 6 && month <= 7) semSelect.value = 'Summer';
                else                               semSelect.value = '1st Semester';

            } catch (err) { console.error('fetchInitialData error:', err); }
        }

        // ────────────────────────────────────────────────────
        // 3. Fetch schedules
        // ────────────────────────────────────────────────────
        async function fetchSchedules(silent = false) {
            const ay   = document.getElementById('filterAY').value  || 'All';
            const prog = document.getElementById('filterProg').value || 'All';
            const sem  = document.getElementById('filterSem').value  || 'All';
            const day  = document.getElementById('filterDay').value  || 'All';

            try {
                const url  = `Dashboard_api.php?action=get_schedules&ay=${encodeURIComponent(ay)}&prog=${encodeURIComponent(prog)}&sem=${encodeURIComponent(sem)}&day=${encodeURIComponent(day)}`;
                const resp = await fetch(url);
                if (!resp.ok) return;
                const fetched = await resp.json();

                // Detect changes using a lightweight hash of JSON length + spot-check
                const newHash = JSON.stringify(fetched).length + '|' + (fetched[0]?.id ?? '') + '|' + fetched.length;

                if (!silent || newHash !== lastDataHash) {
                    lastDataHash = newHash;
                    allData = Array.isArray(fetched) ? fetched : [];
                    refreshView();

                    if (silent && newHash !== lastDataHash) {
                        flashLiveIndicator(); // visual feedback when new data arrives silently
                    }
                }
            } catch (err) {
                console.error('fetchSchedules error:', err);
                if (!silent) { allData = []; refreshView(); }
            }
        }

        function flashLiveIndicator() {
            const el = document.getElementById('liveIndicator');
            el.style.background = '#a7f3d0';
            setTimeout(() => { el.style.background = '#d1fae5'; }, 1000);
        }

        // ────────────────────────────────────────────────────
        // 4. Real-time polling — checks every 10 seconds
        // ────────────────────────────────────────────────────
        function startLiveSync() {
            setInterval(async () => {
                const modal = document.getElementById('modalBulk');
                // Pause polling if the modal is open to prevent UI disruption
                if (modal && modal.style.display === 'block') {
                    return;
                }
                
                await fetchSchedules(true);  // silent = true → only re-renders if data changed
                await fetchSidePanels();
            }, 10000); // 10 seconds
        }

        // ────────────────────────────────────────────────────
        // 5. Side panels
        // ────────────────────────────────────────────────────
        async function fetchSidePanels() {
            // Ongoing classes
            try {
                const res = await fetch('Monitoring_api.php');
                if (res.ok) {
                    const data = await res.json();
                    if (Array.isArray(data)) {
                        let html = '';
                        let occupiedCount = 0;
                        data.forEach(item => {
                            // FIX: Using the new 'state' property instead of 'status'
                            if (item.state === 'Verified' || item.state === 'Ad-Hoc') {
                                occupiedCount++;
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
                                html += `
                                    <div class="ongoing-item" onclick="window.location.href='Monitoring_index.php'" title="View in Monitoring">
                                        <div class="ongoing-icon"><i class="fas fa-video"></i></div>
                                        <div>
                                            <div style="font-size:12px; font-weight:800; color:var(--text-dark);">${item.room_info?.room_name || 'Room'}</div>
                                            <div style="font-size:11px; font-weight:600; color:var(--text-muted);">${displayDesc}</div>
                                        </div>
                                    </div>`;
                            }
                        });
                        document.getElementById('ongoingContainer').innerHTML =
                            html || '<p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">No ongoing classes right now.</p>';
                        document.getElementById('occ-count').innerText = occupiedCount;
                        document.getElementById('util-rate').innerText =
                            (totalRoomsCount > 0 ? Math.round((occupiedCount / totalRoomsCount) * 100) : 0) + '%';
                    }
                }
            } catch(e) {
                console.error("Ongoing Widget Error:", e);
                document.getElementById('ongoingContainer').innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px;">Failed to load data.</p>';
            }

            // Notifications
            try {
                let html = '';
                const incomingReq = await fetch('Approval_api.php?action=get_requests');
                if (incomingReq.ok) {
                    const incoming = await incomingReq.json();
                    if (Array.isArray(incoming)) {
                        let internalGroups = {};
                        let crossGroups = {};

                        incoming.forEach(r => {
                            if (r.department === currentUserDept && r.approval_status === 'Pending') {
                                const k = `${r.program} ${r.section}`;
                                internalGroups[k] = (internalGroups[k] || 0) + 1;
                            } 
                            else if (r.approval_status === 'Approved') {
                                const k = `${r.department} - ${r.program} ${r.section}`;
                                if (!crossGroups[k]) crossGroups[k] = { count: 0, types: new Set() };
                                crossGroups[k].count++;
                                
                                if (r.requested_room_department === currentUserDept) crossGroups[k].types.add('Room');
                                if (r.requested_department === currentUserDept) crossGroups[k].types.add('Prof');
                            }
                        });

                        for (const [sec, count] of Object.entries(internalGroups)) {
                            html += `<div class="notif-item pending" onclick="window.location.href='Approval_index.php'" title="Go to Approvals">
                                        <div style="font-size:10px; font-weight:800; color:#d97706; margin-bottom:2px; text-transform:uppercase;"><i class="fas fa-layer-group"></i> Block Schedule</div>
                                        <div style="font-size:11px; color:var(--text-dark); font-weight:600; line-height:1.3;">${sec} (${count} subjects waiting)</div>
                                    </div>`;
                        }

                        for (const [info, data] of Object.entries(crossGroups)) {
                            let typeLabel = Array.from(data.types).join(' & ');
                            html += `<div class="notif-item pending" onclick="window.location.href='Approval_index.php'" title="Go to Approvals">
                                        <div style="font-size:10px; font-weight:800; color:#d97706; margin-bottom:2px; text-transform:uppercase;"><i class="fas fa-exchange-alt"></i> ${typeLabel} Request</div>
                                        <div style="font-size:11px; color:var(--text-dark); font-weight:600; line-height:1.3;">From ${info} (${data.count} items)</div>
                                    </div>`;
                        }
                    }
                }

                const outgoingReq = await fetch('Approval_api.php?action=get_outgoing_requests');
                if (outgoingReq.ok) {
                    const outgoing = await outgoingReq.json();
                    if (Array.isArray(outgoing)) {
                        let outgoingGroups = {};
                        outgoing.forEach(r => {
                            let targets = [];
                            if (r.requested_room_department && r.requested_room_department !== currentUserDept && r.requested_room_department !== 'null') {
                                if (!targets.includes(r.requested_room_department)) targets.push(r.requested_room_department);
                            }
                            if (r.requested_department && r.requested_department !== currentUserDept && r.requested_department !== 'null') {
                                if (!targets.includes(r.requested_department)) targets.push(r.requested_department);
                            }
                            
                            let targetLabel = targets.length > 0 ? targets.join(' / ') : (r.requested_department || r.requested_room_department || 'Another Dept');
                            let key = targetLabel;
                            if (!outgoingGroups[key]) outgoingGroups[key] = 0;
                            outgoingGroups[key]++;
                        });

                        for (const [target, count] of Object.entries(outgoingGroups)) {
                            html += `<div class="notif-item awaiting" onclick="window.location.href='Approval_index.php'" title="View Status">
                                        <div style="font-size:10px; font-weight:800; color:#3b82f6; margin-bottom:2px; text-transform:uppercase;"><i class="fas fa-paper-plane"></i> Awaiting Reply</div>
                                        <div style="font-size:11px; color:var(--text-dark); font-weight:600; line-height:1.3;">Sent ${count} request(s) to ${target}</div>
                                    </div>`;
                        }
                    }
                }
                
                document.getElementById('notifContainer').innerHTML =
                    html || '<p style="text-align:center; color:var(--text-muted); font-size:12px; margin-top:20px;">No new notifications.</p>';
            } catch(e) {
                console.error("Notifications Error:", e);
                document.getElementById('notifContainer').innerHTML = '<p style="text-align:center; color:#ef4444; font-size:12px;">Failed to load data.</p>';
            }
        }

        // ────────────────────────────────────────────────────
        // 6. Day parsing — handles Mon, Mon/Wed, Mon-Wed, MonWed, Mon,Wed
        // ────────────────────────────────────────────────────
        const DAY_ALIASES = {
            'M': 'Mon', 'T': 'Tue', 'W': 'Wed', 'TH': 'Thu', 'F': 'Fri', 'S': 'Sat', 'SU': 'Sun',
            'MON': 'Mon', 'TUE': 'Tue', 'WED': 'Wed', 'THU': 'Thu', 'FRI': 'Fri', 'SAT': 'Sat', 'SUN': 'Sun',
            'MONDAY': 'Mon', 'TUESDAY': 'Tue', 'WEDNESDAY': 'Wed', 'THURSDAY': 'Thu',
            'FRIDAY': 'Fri', 'SATURDAY': 'Sat', 'SUNDAY': 'Sun',
        };

        function parseDays(daysStr) {
            if (!daysStr) return [];
            // Normalise separators: slash, dash, comma, space → comma
            const normalised = daysStr.replace(/[\/\-\s]+/g, ',');
            return normalised.split(',')
                .map(d => d.trim().toUpperCase())
                .filter(Boolean)
                .map(d => DAY_ALIASES[d] || (d.charAt(0) + d.slice(1).toLowerCase()))
                .filter(Boolean);
        }

        function scheduleMatchesDay(daysStr, filterDay) {
            if (!filterDay || filterDay === 'All') return true;
            const parsed = parseDays(daysStr);
            return parsed.includes(filterDay);
        }

        // ────────────────────────────────────────────────────
        // 7. Calendar render
        // ────────────────────────────────────────────────────
        function formatTime(timeStr) {
            if (!timeStr || !timeStr.includes(':')) return '';
            let [h, m] = timeStr.split(':');
            h = parseInt(h);
            const period = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${period}`;
        }

        function refreshView() {
            const grid = document.getElementById('calendarGrid');
            grid.innerHTML = '';

            const searchTerm = document.getElementById('filterInput').value.toLowerCase();
            const dayFilter  = document.getElementById('filterDay').value;

            // Filter with robust day matching and added Major search
            const filtered = allData.filter(d => {
                const str = `${d.course_code} ${d.descriptive_title} ${d.program} ${d.major || ''} ${d.section} ${d.professor}`.toLowerCase();
                const matchSearch = (!searchTerm || str.includes(searchTerm));
                const matchDay = scheduleMatchesDay(d.days, dayFilter);
                return matchSearch && matchDay;
            });

            // Build events
            const events = [];
            filtered.forEach(d => {
                if (!d.start_time || !d.end_time ||
                    !d.start_time.includes(':') || !d.end_time.includes(':')) return;

                const [startH, startM] = d.start_time.split(':').map(Number);
                const [endH,   endM  ] = d.end_time.split(':').map(Number);
                const startMins = startH * 60 + startM;
                const endMins   = endH   * 60 + endM;

                events.push({
                    data: d, startHour: startH, startMin: startM,
                    startMins, endMins,
                    height: (endMins - startMins) * (100 / 60),
                    roomId: String(d.room_id || ''),
                });
            });

            // Virtual rooms for pending/TBA
            let displayRooms = [...dbRoomsList];
            events.forEach(ev => {
                const rId = ev.roomId;
                if (rId.startsWith('REQ_') && !displayRooms.some(r => String(r.id) === rId)) {
                    const shortDept = rId.replace('REQ_College of ', '');
                    displayRooms.push({ id: rId, room_name: '⏳ Req: ' + shortDept, status: 'Virtual', department: 'Requested' });
                } else if (rId === '0' && !displayRooms.some(r => String(r.id) === '0')) {
                    displayRooms.push({ id: '0', room_name: '⏳ TBA Room', status: 'Virtual', department: 'Virtual' });
                }
            });

            // Sort: active rooms first
            const activeRoomIds = new Set(events.map(e => e.roomId));
            const sortedRooms   = [...displayRooms].sort((a, b) => {
                const aA = activeRoomIds.has(String(a.id));
                const bA = activeRoomIds.has(String(b.id));
                if (aA && !bA) return -1;
                if (!aA && bA) return  1;
                return String(a.room_name || '').localeCompare(String(b.room_name || ''));
            });

            if (!sortedRooms.length) {
                grid.style.gridTemplateColumns = '1fr';
                grid.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center;
                                padding:80px 20px; color:var(--text-muted); text-align:center;
                                grid-column:1/-1; min-height:400px;">
                        <i class="fas fa-door-closed" style="font-size:50px; color:#cbd5e1; margin-bottom:15px;"></i>
                        <p style="font-size:18px; color:var(--primary-blue);">No Rooms Available</p>
                    </div>`;
                return;
            }

            grid.style.gridTemplateColumns = `80px repeat(${sortedRooms.length}, 180px)`;

            let html = '<div class="cal-header">Time</div>';
            
            // Format headers to distinguish University rooms
            sortedRooms.forEach(r => {
                let displayHtml = r.room_name;
                if (r.department === 'University') {
                    displayHtml += ` <span style="font-size:9px; background:#1e40af; color:white; padding:2px 4px; border-radius:4px; margin-left:6px;">Univ</span>`;
                }
                html += `<div class="cal-header">${displayHtml}</div>`;
            });

            for (let hour = 7; hour <= 20; hour++) {
                const label = (hour > 12 ? hour - 12 : hour) + (hour >= 12 ? ' PM' : ' AM');
                html += `<div class="cal-time">${label}</div>`;
                sortedRooms.forEach(r => {
                    const safeId = String(r.id).replace(/[^a-zA-Z0-9_\-]/g, '_');
                    html += `<div class="cal-cell" id="c-${safeId}-${hour}"></div>`;
                });
            }
            grid.innerHTML = html;

            // Overlap detection
            const grouped = {};
            events.forEach(ev => {
                if (!grouped[ev.roomId]) grouped[ev.roomId] = [];
                grouped[ev.roomId].push(ev);
            });
            Object.values(grouped).forEach(group => {
                group.sort((a, b) => a.startMins - b.startMins);
                const columns = [];
                group.forEach(ev => {
                    let placed = false;
                    for (let i = 0; i < columns.length; i++) {
                        if (ev.startMins >= columns[i][columns[i].length - 1].endMins) {
                            columns[i].push(ev); ev.colIndex = i; placed = true; break;
                        }
                    }
                    if (!placed) { columns.push([ev]); ev.colIndex = columns.length - 1; }
                });
                group.forEach(ev => { ev.width = 96 / columns.length; ev.left = 2 + ev.colIndex * ev.width; });
            });

            // Render cards
            events.forEach(ev => {
                const d = ev.data;
                const safeRoomId = String(ev.roomId).replace(/[^a-zA-Z0-9_\-]/g, '_');
                const cell = document.getElementById(`c-${safeRoomId}-${ev.startHour}`);
                if (!cell) return;

                const card = document.createElement('div');
                card.className = 'card';

                // ── Color: pending gets darkened version of program color ──
                const progColor = getProgramColor(d.program);
                if (d.approval_status === 'Pending') {
                    card.classList.add('pending-overlay');
                    card.style.backgroundColor = darkenColor(progColor, 0.35);
                } else {
                    card.style.backgroundColor = progColor;
                }

                card.style.top    = `${ev.startMin * (100 / 60)}px`;
                card.style.height = `${ev.height}px`;
                card.style.left   = `${ev.left}%`;
                card.style.width  = `${ev.width}%`;
                card.onclick      = () => openReadOnlyModal(d);

                const timeRange = `${formatTime(d.start_time)} – ${formatTime(d.end_time)}`;
                let profName = (d.professor || '').toUpperCase();
                let roomDisplay = '';

                // Show room label on pending/TBA cards
                if (String(ev.roomId).startsWith('REQ_') || String(ev.roomId) === '0') {
                    const rMatch = displayRooms.find(r => String(r.id) === String(ev.roomId));
                    roomDisplay = `<span style="opacity:0.9; margin-bottom:2px; font-weight:700; display:block;">${rMatch ? rMatch.room_name : 'TBA Room'}</span>`;
                } else if (d.assigned_room_display) {
                    // Cross-department approved room — show actual room name
                    roomDisplay = `<span style="opacity:0.85; margin-bottom:2px; font-weight:600; font-size:10px; display:block;">${d.assigned_room_display}</span>`;
                }

                if (d.approval_status === 'Pending') {
                    card.innerHTML = `
                        <div class="pending-badge-top"><i class="fas fa-hourglass-half"></i> Pending</div>
                        <b style="font-size:13px; margin-bottom:4px; display:block;">${d.course_code || ''}</b>
                        <span style="font-size:10px; font-weight:700; background:rgba(0,0,0,0.15); padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:4px;">
                            ${parseDays(d.days).join('/').toUpperCase() || (d.days || '').toUpperCase()}
                        </span>
                        ${roomDisplay}
                        <span style="margin-bottom:6px; display:block; font-size:11px;">${d.program || ''}, ${d.section || ''}</span>
                        <span style="font-weight:800; margin-top:auto; display:block; font-size:12px;">${timeRange}</span>`;
                } else {
                    if (profName && profName !== 'TBA' && !profName.startsWith('PROF.') && !profName.startsWith('⏳')) {
                        profName = 'PROF. ' + profName;
                    }
                    card.innerHTML = `
                        <b style="font-size:13px; margin-bottom:4px; display:block;">${d.course_code || ''}</b>
                        <span style="font-size:10px; font-weight:700; background:rgba(0,0,0,0.15); padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:4px;">
                            ${parseDays(d.days).join('/').toUpperCase() || (d.days || '').toUpperCase()}
                        </span>
                        <span style="margin-bottom:2px; display:block; font-size:11px;">${d.program || ''}, ${d.section || ''}</span>
                        ${roomDisplay}
                        <span style="margin-bottom:4px; display:block; font-weight:600; font-size:11px;">${profName}</span>
                        <span style="font-weight:800; margin-top:auto; display:block; font-size:12px;">${timeRange}</span>`;
                }

                cell.appendChild(card);
            });
        }

        // ────────────────────────────────────────────────────
        // 8. Read-only modal
        // ────────────────────────────────────────────────────
        async function openReadOnlyModal(d) {
            document.getElementById('b_dept').value = d.department || '';
            document.getElementById('b_prog').value = d.program    || '';
            
            // Handle Major Display in Modal Header
            const bMajorGroup = document.getElementById('b_major_group');
            const bMajorInput = document.getElementById('b_major');
            if (d.major) {
                bMajorGroup.style.display = 'flex';
                bMajorInput.value = d.major;
            } else {
                bMajorGroup.style.display = 'none';
                bMajorInput.value = '';
            }

            document.getElementById('b_year').value = d.year_level || '';
            document.getElementById('b_sem').value  = d.semester   || '';
            document.getElementById('b_sec').value  = d.section    || '';
            document.getElementById('b_ay').value   = d.academic_year || '';

            try {
                const url = `CreateClass_api.php?action=find_courses`
                          + `&program=${encodeURIComponent(d.program || '')}`
                          + `&major=${encodeURIComponent(d.major || '')}`
                          + `&year_level=${encodeURIComponent(d.year_level || '')}`
                          + `&semester=${encodeURIComponent(d.semester || '')}`;
                const resp = await fetch(url);
                if (!resp.ok) return;
                const coursesData = await resp.json();
                const courses = Array.isArray(coursesData) ? coursesData : [];

                const sectionSchedules = allData.filter(s =>
                    s.program       === d.program       &&
                    s.year_level    === d.year_level    &&
                    s.section       === d.section       &&
                    s.academic_year === d.academic_year &&
                    s.semester      === d.semester
                );

                renderReadOnlyRows(courses, sectionSchedules);
                document.getElementById('modalBulk').style.display = 'block';
            } catch (err) { console.error('Failed to load section preview:', err); }
        }

        function renderReadOnlyRows(courses, schedules = []) {
            const tbody = document.getElementById('bulkRowsContainer');
            tbody.innerHTML = '';
            courses.forEach(course => {
                const matching = schedules.filter(s => s.course_id == course.id);
                if (matching.length) {
                    matching.forEach(sched => {
                        const lec   = sched.lecture_hours    !== null ? parseFloat(sched.lecture_hours)    : parseFloat(course.lecture_hours    || 0);
                        const lab   = sched.laboratory_hours !== null ? parseFloat(sched.laboratory_hours) : parseFloat(course.laboratory_hours || 0);
                        const units = sched.units            !== null ? parseFloat(sched.units)            : parseFloat(course.units            || 0);
                        tbody.insertAdjacentHTML('beforeend', buildTableRow(course, sched, lec, lab, units));
                    });
                } else {
                    tbody.insertAdjacentHTML('beforeend', buildTableRow(course, null));
                }
            });
        }

        function buildTableRow(course, sched = null, lec = null, lab = null, units = null) {
            lec   = lec   !== null ? lec   : parseFloat(course.lecture_hours    || 0);
            lab   = lab   !== null ? lab   : parseFloat(course.laboratory_hours || 0);
            units = units !== null ? units : parseFloat(course.units            || 0);

            const startDisplay = sched?.start_time ? formatTime(sched.start_time) : '';
            const endDisplay   = sched?.end_time   ? formatTime(sched.end_time)   : '';
            const daysDisplay  = sched?.days       ? parseDays(sched.days).join('/') : '';

            let roomName = '';
            if (String(sched?.room_id || '').startsWith('REQ_')) {
                roomName = '⏳ Req: ' + String(sched.room_id).replace('REQ_College of ', '');
            } else if (String(sched?.room_id) === '0' || !sched?.room_id) {
                roomName = sched?.assigned_room_display || 'TBA Room';
            } else if (sched?.room_id) {
                const room = dbRoomsList.find(r => r.id == sched.room_id);
                roomName = room ? room.room_name : (sched.assigned_room_display || '');
            }

            let profDisplay = sched?.professor || '';
            if (profDisplay && profDisplay !== 'TBA' && profDisplay !== 'General Education' &&
                !profDisplay.startsWith('PROF.') && !profDisplay.startsWith('⏳')) {
                profDisplay = 'PROF. ' + profDisplay;
            }

            const pendingBadge = (sched?.approval_status === 'Pending')
                ? `<div style="margin-top:4px; font-size:9px; font-weight:800; color:#d97706; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block;">⏳ PENDING</div>`
                : '';

            return `
                <tr>
                    <td style="text-align:center; vertical-align:middle;"><input type="text" class="table-input" value="${daysDisplay}" disabled></td>
                    <td style="text-align:center; vertical-align:middle;">
                        <input type="text" class="table-input" style="margin-bottom:6px;" value="${startDisplay}" disabled placeholder="Start Time">
                        <input type="text" class="table-input" value="${endDisplay}" disabled placeholder="End Time">
                    </td>
                    <td style="text-align:center; vertical-align:middle;"><strong style="color:var(--primary-blue); font-size:12px;">${course.course_code || ''}</strong></td>
                    <td style="text-align:left; font-size:11px; font-weight:600; padding-left:8px; line-height:1.2; vertical-align:middle;">${course.descriptive_title || ''}<br>${pendingBadge}</td>
                    <td style="text-align:center; vertical-align:middle;"><strong>${units}</strong></td>
                    <td style="width:4%; text-align:center; vertical-align:middle; border-right:1px dashed var(--border-color);">${lec}</td>
                    <td style="width:4%; text-align:center; vertical-align:middle;">${lab}</td>
                    <td style="text-align:center; vertical-align:middle;"><input type="text" class="table-input" value="${roomName}" disabled placeholder="No Room"></td>
                    <td style="text-align:center; vertical-align:middle;"><input type="text" class="table-input" value="${profDisplay}" disabled placeholder="No Professor"></td>
                </tr>`;
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // ────────────────────────────────────────────────────
        // 9. Bootstrap
        // ────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', async () => {
            await fetchInitialData();
            await fetchSchedules();
            await fetchSidePanels();

            // Start real-time sync loop
            startLiveSync();

            // Filter listeners
            document.getElementById('filterInput').addEventListener('input', refreshView);
            ['filterAY', 'filterProg', 'filterSem', 'filterDay'].forEach(id => {
                document.getElementById('filterAY').addEventListener('change', fetchSchedules);
                document.getElementById('filterProg').addEventListener('change', fetchSchedules);
                document.getElementById('filterSem').addEventListener('change', fetchSchedules);
                document.getElementById('filterDay').addEventListener('change', fetchSchedules);
            });

            // Mobile sidebar
            document.getElementById('menu-toggle').addEventListener('click', () => {
                document.body.classList.toggle('mobile-nav-active');
            });
            document.querySelector('.backdrop').addEventListener('click', () => {
                document.body.classList.remove('mobile-nav-active');
            });
        });
    </script>
</body>
</html>