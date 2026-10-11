<?php
// ============================================================
//  COURSE MANAGEMENT — COSMOS
// ============================================================
session_start();
require '../db_connect.php';

// ----- Logout -----------------------------------------------
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
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

// ----- User data --------------------------------------------
$userName     = $_SESSION['full_name']       ?? 'Unknown User';
$userPosition = $_SESSION['position']        ?? 'Staff';
$userDept     = $_SESSION['department']      ?? 'Department';
$userProgram  = $_SESSION['program']         ?? ''; // Restored user program
$userPic      = $_SESSION['profile_picture'] ?? null;

// Determine if Admin
$isAdmin = (stripos($userPosition, 'Admin') !== false);

// List of all departments for the Admin dropdown
$allDepartments = [
    'College of Science',
    'College of Education',
    'College of Engineering',
    'College of Industrial Technology',
    'College of Arts and Letters',
    'College of Business'
];

// Clean name
$cleanName = str_ireplace('null', '', $userName);
$cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
if (empty($cleanName) || $cleanName === '.') {
    $cleanName = 'Unknown User';
}

// Build initials
$nameParts = explode(' ', $cleanName);
$initials  = count($nameParts) > 1
    ? strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1))
    : strtoupper(substr($nameParts[0], 0, 1));

// Department theme colour
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
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
    <title>Course Management - COSMOS</title>
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
            --sidebar-min:        72px;
            --sidebar-max:        260px;
        }

        /* ── Reset ───────────────────────────────────────────── */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            display: flex;
            height: 100vh;
            background-color: var(--bg-main);
            color: var(--text-dark);
            overflow: hidden;
        }

        /* ════════════════════════════════════════════════════════
             SIDEBAR — collapsed (72px) by default, expands on hover
        ════════════════════════════════════════════════════════ */
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

        .sidebar:hover .logo-section img { width: 140px; }

        /* Section labels */
        .menu-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin: 10px 0 4px;
            padding: 0 20px;
            white-space: nowrap;
            overflow: hidden;
            opacity: 0;
            max-height: 0;
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }

        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; }

        /* Nav links */
        .nav-menu { list-style: none; padding: 0; }

        .nav-menu li a {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 11px 0;
            margin: 3px 10px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            border-radius: var(--radius-input);
            white-space: nowrap;
            overflow: hidden;
            transition: background 0.2s, color 0.2s, padding 0.3s, justify-content 0.2s;
        }

        .sidebar:hover .nav-menu li a { padding: 11px 14px; justify-content: flex-start; }

        .nav-menu li a:hover  { background: #f8fafc; color: var(--primary-blue); }
        .nav-menu li a.active { background: var(--accent-blue); color: var(--primary-blue); font-weight: 600; }

        .nav-menu i {
            font-size: 18px;
            width: 22px;
            flex-shrink: 0;
            text-align: center;
            margin-right: 0;
            transition: margin-right 0.3s ease;
        }

        .sidebar:hover .nav-menu i { margin-right: 12px; }

        .nav-menu li a span {
            display: inline-block;
            width: 0;
            opacity: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 0.2s ease 0.05s, width 0.3s ease;
        }

        .sidebar:hover .nav-menu li a span { width: auto; opacity: 1; }

        /* ── Profile section ──────────────────────────────────── */
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

        .dept-logo-bubble img         { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback           { font-size: 20px; color: var(--primary-blue); }

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

        .sidebar:hover .profile-info { width: auto; opacity: 1; }

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

        .sidebar:hover .logout-btn { width: auto; opacity: 1; }
        .logout-btn:hover          { color: #ef4444; background: #fef2f2; }

        /* ════════════════════════════════════════════════════════
             MAIN CONTENT
        ════════════════════════════════════════════════════════ */
        .main-content {
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        header {
            flex-shrink: 0;
            background: transparent;
            padding: 30px 40px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left { display: flex; align-items: center; gap: 20px; }

        #menu-toggle {
            display: none;
            font-size: 24px;
            color: var(--text-dark);
            cursor: pointer;
            transition: color 0.2s;
        }

        #menu-toggle:hover { color: var(--primary-blue); }

        .header-title h1 {
            font-size: 28px;
            font-weight: 800;
            color: var(--text-dark);
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .header-title p { font-size: 14px; font-weight: 400; color: var(--text-muted); }

        .container { padding: 20px 40px 40px; overflow-y: auto; flex: 1; }

        /* ── Page title & dept badge ──────────────────────────── */
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 20px;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .dept-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 16px;
            border-radius: var(--radius-pill);
            font-size: 12px;
            font-weight: 700;
            color: white;
            background-color: <?php echo $themeColor; ?>;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Admin Department Selector specific styles */
        .dept-select {
            cursor: pointer;
            appearance: none;
            padding-right: 30px;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23ffffff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 12px top 50%;
            background-size: 10px auto;
            border: none;
            outline: none;
            transition: opacity 0.2s;
        }
        .dept-select:hover { opacity: 0.9; }
        .dept-select option { color: var(--text-dark); background: white; }

        /* ── Registration form card ───────────────────────────── */
        .register-container {
            background: var(--bg-card);
            padding: 30px;
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .form-row { display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px; }

        .input-group { flex: 1; min-width: 150px; }

        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .pill-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 500;
            background: #f8fafc;
            color: var(--text-dark);
            outline: none;
            transition: all 0.2s ease;
        }

        select.pill-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 15px top 50%;
            background-size: 10px auto;
        }

        .pill-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .pill-input[readonly],
        .pill-input[disabled] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-color: var(--border-color); background-image: none; }

        /* Autocomplete Styles */
        .suggestions-box {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            z-index: 1000;
            display: none;
            max-height: 220px;
            overflow-y: auto;
        }
        .suggestion-item {
            padding: 12px 16px;
            font-size: 13px;
            color: var(--text-dark);
            cursor: pointer;
            border-bottom: 1px solid var(--border-color);
            transition: background 0.2s, color 0.2s;
        }
        .suggestion-item:last-child { border-bottom: none; }
        .suggestion-item:hover {
            background: var(--accent-blue);
            color: var(--primary-blue);
            font-weight: 600;
        }

        /* Buttons */
        .btn-register {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-register:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2); }

        .btn-delete {
            background: #ef4444;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.2); }

        .btn-reset {
            padding: 12px 20px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 600;
            background: #f1f5f9;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-card);
        }
        .btn-reset:hover { background: #e2e8f0; color: var(--text-dark); }

        /* ── Controls & table ─────────────────────────────────── */
        .controls-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box { flex: 1; position: relative; min-width: 250px; }

        .search-box input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            outline: none;
            background: var(--bg-card);
            box-shadow: var(--shadow-card);
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .search-box input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }
        .search-box i { position: absolute; left: 18px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 16px; }

        .filter-container { display: flex; gap: 10px; flex-wrap: wrap; }

        .filter-btn {
            padding: 12px 35px 12px 18px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 500;
            background: var(--bg-card);
            color: var(--text-dark);
            cursor: pointer;
            outline: none;
            box-shadow: var(--shadow-card);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 15px top 50%;
            background-size: 10px auto;
            transition: all 0.2s ease;
        }

        .filter-btn:hover,
        .filter-btn:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); }

        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            overflow: visible; 
        }

        table { width: 100%; border-collapse: collapse; text-align: left; }

        th {
            background: #f8fafc;
            color: var(--text-muted);
            padding: 16px 20px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            position: sticky;
            top: -20px;
            z-index: 10;
            box-shadow: inset 0 -1px 0 var(--border-color);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-dark);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }

        tr:last-child td             { border-bottom: none; }
        tr:hover td                  { background: #f8fafc; color: var(--primary-blue); }

        /* ════════════════════════════════════════════════════════
             MODALS
        ════════════════════════════════════════════════════════ */
        .modal {
            display: none;
            position: fixed;
            z-index: 1001;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            overflow: auto;
            padding-top: 60px;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: var(--bg-card);
            margin: 2% auto;
            padding: 35px 40px;
            width: 95%;
            max-width: 800px;
            border-radius: var(--radius-card);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .close-btn {
            float: right;
            font-size: 28px;
            font-weight: bold;
            color: var(--text-muted);
            cursor: pointer;
            margin-top: -10px;
            transition: color 0.2s, transform 0.2s;
        }

        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        /* Confirm grid */
        .confirm-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            text-align: left;
            margin-bottom: 25px;
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .confirm-item { font-size: 13px; }
        .confirm-item.full-width { grid-column: 1 / -1; }

        .confirm-item strong {
            display: block;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 4px;
        }

        .confirm-item span { color: var(--text-dark); font-weight: 700; font-size: 14px; }
        .warning-text { color: #ef4444; font-weight: bold; text-align: center; margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px; }

        /* ── System-modal (alert / confirm) ───────────────────── */
        .sys-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            z-index: 3000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .sys-modal-content {
            background: var(--bg-card);
            width: 90%;
            max-width: 440px;
            padding: 35px 30px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
        }

        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        .sys-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.2s ease;
        }

        .sys-btn:hover { transform: translateY(-2px); }
        .sys-btn-primary  { background: var(--primary-blue); color: white; }
        .sys-btn-primary:hover  { background: var(--primary-blue-hover); box-shadow: 0 6px 15px rgba(27,80,136,0.2); }
        .sys-btn-success  { background: #10b981; color: white; }
        .sys-btn-success:hover  { background: #059669; }
        .sys-btn-danger   { background: #ef4444; color: white; }
        .sys-btn-danger:hover   { background: #dc2626; }
        .sys-btn-neutral  { background: #e2e8f0; color: #475569; }
        .sys-btn-neutral:hover  { background: #cbd5e1; }

        /* Mobile backdrop */
        .backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        /* ════════════════════════════════════════════════════════
             RESPONSIVE
        ════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                height: 100%;
                width: var(--sidebar-max) !important;
                transform: translateX(-100%);
            }

            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }

            body.mobile-nav-active .sidebar {
                transform: translateX(0);
                box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
            }

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

            header     { padding: 20px; }
            .container { padding: 20px; }
            .form-row  { flex-direction: column; }
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
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i><span>Classroom Monitoring</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="Course_index.php" class="active"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i><span>Faculty</span></a></li>
            <li><a href="Download_index.php"><i class="fas fa-file-download"></i><span>Download</span></a></li>
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

                    <?php if ($userPic): ?>
                        <img src="<?php echo $userPic; ?>" class="profile-img" alt="Profile Picture">
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

            <div class="page-title">
                Course Management
                <?php if ($isAdmin): ?>
                    <select id="global_dept" class="dept-select dept-badge" onchange="onDepartmentChange()">
                        <?php foreach($allDepartments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                <?php echo ($dept === $userDept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <span class="dept-badge" id="global_dept" data-dept="<?php echo htmlspecialchars($userDept); ?>">
                        <i class="fas fa-building" style="margin-right:6px;"></i>
                        <?php echo htmlspecialchars($userDept); ?>
                    </span>
                <?php endif; ?>
            </div>

            <form id="courseForm" class="register-container">
                <h3 style="color:var(--text-dark); margin-bottom:20px; font-size:16px; font-weight:700;">
                    Register New Course
                </h3>

                <div class="form-row">
                    <div class="input-group" style="flex:1;">
                        <label>Department</label>
                        <input type="text" id="form_dept_input" class="pill-input" name="department"
                               value="<?php echo htmlspecialchars($userDept); ?>" readonly
                               title="Registering to the selected department.">
                    </div>
                    
                    <div class="input-group autocomplete-wrapper" style="flex:1; position:relative;">
                        <label>Program</label>
                        <?php if ($userPosition === 'Program Head'): ?>
                            <input type="text" class="pill-input" name="program" value="<?php echo htmlspecialchars($userProgram); ?>" readonly style="background: #f1f5f9; color: #94a3b8; cursor: not-allowed;" title="Access restricted to your assigned program.">
                        <?php else: ?>
                            <input type="text" class="pill-input" name="program" placeholder="e.g. Computer Science" autocomplete="off" required>
                        <?php endif; ?>
                        <div class="suggestions-box"></div>
                    </div>
                    <div class="input-group autocomplete-wrapper" style="flex:1; position:relative;">
                        <label>Major</label>
                        <input type="text" class="pill-input" name="major" placeholder="e.g. Microbiology" autocomplete="off">
                        <div class="suggestions-box"></div>
                    </div>

                    <div class="input-group" style="min-width:140px;">
                        <label>Year Level</label>
                        <select name="year_level" class="pill-input" required>
                            <option value="" disabled selected>Select Year</option>
                            <option value="1st Year">1st Year</option>
                            <option value="2nd Year">2nd Year</option>
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                        </select>
                    </div>
                    <div class="input-group" style="min-width:150px;">
                        <label>Semester</label>
                        <select name="semester" class="pill-input" required>
                            <option value="" disabled selected>Select Sem</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                            <option value="Summer">Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-group" style="min-width:140px;">
                        <label>Course Code</label>
                        <input type="text" class="pill-input" name="course_code" placeholder="e.g. CS 101" required>
                    </div>
                    <div class="input-group" style="flex:2;">
                        <label>Descriptive Title</label>
                        <input type="text" class="pill-input" name="descriptive_title" placeholder="Course Description" required>
                    </div>
                    <div class="input-group" style="min-width:90px; max-width:100px;">
                        <label>Units</label>
                        <input type="number" class="pill-input" name="units" min="0" oninput="this.value = Math.abs(this.value)" onfocus="this.select()" required>
                    </div>
                    <div class="input-group" style="min-width:90px; max-width:100px;">
                        <label>Lec Hrs</label>
                        <input type="number" class="pill-input" name="lecture_hours" value="0" min="0" oninput="this.value = Math.abs(this.value)" onfocus="this.select()">
                    </div>
                    <div class="input-group" style="min-width:90px; max-width:100px;">
                        <label>Lab Hrs</label>
                        <input type="number" class="pill-input" name="laboratory_hours" value="0" min="0" oninput="this.value = Math.abs(this.value)" onfocus="this.select()">
                    </div>
                </div>

                <div style="text-align:right; margin-top:10px;">
                    <button type="submit" class="btn-register">
                        <i class="fas fa-plus-circle" style="margin-right:8px;"></i> Register Course
                    </button>
                </div>
            </form>

            <div class="controls-container">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by Code, Title, or Major...">
                </div>
                <div class="filter-container">
                    <select id="filterProgram" class="filter-btn"></select>
                    <select id="filterYear"    class="filter-btn"></select>
                    <select id="filterSem"     class="filter-btn"></select>
                    <button id="resetFiltersBtn" class="btn-reset" title="Reset Filters">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Descriptive Title</th>
                            <th>Units</th>
                            <th>Lec</th>
                            <th>Lab</th>
                            <th>Program</th>
                            <th>Major</th>
                            <th>Year Level</th>
                            <th>Sem</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>

        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h2 style="color:var(--text-dark); margin-bottom:25px; font-weight:800;">Edit Course</h2>
            <form id="editForm"></form>
        </div>
    </div>

    <div id="confirmModal" class="modal" style="z-index:1002;">
        <div class="modal-content" style="max-width:550px;">
            <span class="close-btn" onclick="closeModal('confirmModal')">&times;</span>
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
                <i id="confirmIcon" class="fas fa-exclamation-circle" style="font-size:30px; color:var(--primary-blue);"></i>
                <h3 id="confirmTitle" style="color:var(--text-dark); font-weight:800; font-size:20px; margin:0;">Confirm Action</h3>
            </div>
            <div id="confirmContent"></div>
            <div style="margin-top:30px; display:flex; justify-content:flex-end; gap:15px;">
                <button type="button" class="btn-delete"
                        style="background:#f1f5f9; color:var(--text-dark);"
                        onclick="closeModal('confirmModal')">Cancel</button>
                <button type="button" id="btnConfirmAction" class="btn-register">Confirm</button>
            </div>
        </div>
    </div>

    <div id="sysAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysAlertIcon" class="sys-modal-icon fas fa-info-circle" style="color:var(--primary-blue);"></i>
            <div id="sysAlertMsg" class="sys-modal-msg"></div>
            <button id="sysAlertOkBtn" class="sys-btn sys-btn-primary" style="width:100%;">OK</button>
        </div>
    </div>

    <div id="sysConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysConfirmIcon" class="sys-modal-icon fas fa-question-circle" style="color:var(--primary-blue);"></i>
            <div id="sysConfirmMsg" class="sys-modal-msg"></div>
            <div class="sys-modal-btns">
                <button id="sysConfirmNoBtn"  class="sys-btn sys-btn-neutral">No, go back</button>
                <button id="sysConfirmYesBtn" class="sys-btn sys-btn-success">Yes, add more</button>
            </div>
        </div>
    </div>

    <script>
        // JS Session variables for logic tracking
        const SESSION_POS  = <?php echo json_encode($userPosition); ?>;
        const SESSION_PROG = <?php echo json_encode($userProgram); ?>;

        // ── Admin Retrieval Function ──────────────────────────
        function getDept() {
            const select = document.getElementById('global_dept');
            if (select.tagName === 'SELECT') return select.value;
            return select.getAttribute('data-dept');
        }

        async function onDepartmentChange() {
            // Update the form's visible department input
            document.getElementById('form_dept_input').value = getDept();
            
            // Show loading spinner
            document.getElementById('tableBody').innerHTML = '<tr><td colspan="9" style="text-align:center; padding:40px;"><i class="fas fa-spinner fa-spin"></i> Switching Department...</td></tr>';
            
            // Reload all courses for the newly selected department
            await loadCourses();
        }

        // ── State ─────────────────────────────────────────────
        let allCourses       = [];
        let formDataToSubmit = null;
        let pendingAction    = null;   // 'CREATE' | 'UPDATE' | 'DELETE'

        // ────────────────────────────────────────────────────
        // Custom promise-based modals (no native alert/confirm)
        // ────────────────────────────────────────────────────
        function sysAlert(msg, iconClass = 'fa-info-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysAlertMsg').innerHTML = msg;
                const icon = document.getElementById('sysAlertIcon');
                icon.className   = `fas ${iconClass} sys-modal-icon`;
                icon.style.color = color;
                document.getElementById('sysAlert').style.display = 'flex';
                document.getElementById('sysAlertOkBtn').onclick = () => {
                    document.getElementById('sysAlert').style.display = 'none';
                    resolve();
                };
            });
        }

        function sysConfirm(msg, yesLabel = 'Yes', noLabel = 'No, go back',
                            iconClass = 'fa-question-circle', color = 'var(--primary-blue)') {
            return new Promise(resolve => {
                document.getElementById('sysConfirmMsg').innerHTML = msg;
                const icon = document.getElementById('sysConfirmIcon');
                icon.className   = `fas ${iconClass} sys-modal-icon`;
                icon.style.color = color;
                document.getElementById('sysConfirmYesBtn').innerText = yesLabel;
                document.getElementById('sysConfirmNoBtn').innerText  = noLabel;
                document.getElementById('sysConfirm').style.display   = 'flex';

                document.getElementById('sysConfirmYesBtn').onclick = () => {
                    document.getElementById('sysConfirm').style.display = 'none';
                    resolve(true);
                };
                document.getElementById('sysConfirmNoBtn').onclick = () => {
                    document.getElementById('sysConfirm').style.display = 'none';
                    resolve(false);
                };
            });
        }

        // ────────────────────────────────────────────────────
        // Navigation
        // ────────────────────────────────────────────────────
        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });

        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        // Close modals on outside click
        window.onclick = e => {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
        };

        // ────────────────────────────────────────────────────
        // Autocomplete Logic
        // ────────────────────────────────────────────────────
        function initAutocompleteListeners() {
            document.querySelectorAll('.autocomplete-wrapper').forEach(wrapper => {
                const input = wrapper.querySelector('input');
                const box = wrapper.querySelector('.suggestions-box');
                const isMajor = input.name === 'major';

                input.removeEventListener('input', input._updateSuggestions);
                input.removeEventListener('focus', input._updateSuggestions);
                input.removeEventListener('blur', input._hideSuggestions);

                input._updateSuggestions = function() {
                    // Do not show suggestions if field is locked
                    if (input.readOnly) return;

                    const val = input.value.toLowerCase();
                    let sourceData = [];

                    if (isMajor) {
                        const form = input.closest('form');
                        const progInput = form.querySelector('input[name="program"]');
                        const selectedProg = progInput ? progInput.value : '';
                        
                        const filtered = selectedProg ? allCourses.filter(c => c.program === selectedProg) : allCourses;
                        sourceData = [...new Set(filtered.map(c => c.major).filter(Boolean))];
                    } else {
                        sourceData = [...new Set(allCourses.map(c => c.program).filter(Boolean))];
                    }

                    let matches = sourceData.filter(item => item.toLowerCase().includes(val)).slice(0, 5);

                    box.innerHTML = '';
                    if (matches.length > 0) {
                        matches.forEach(match => {
                            const div = document.createElement('div');
                            div.className = 'suggestion-item';
                            div.innerText = match;
                            div.onmousedown = (e) => {
                                e.preventDefault();
                                input.value = match;
                                box.style.display = 'none';
                            };
                            box.appendChild(div);
                        });
                        box.style.display = 'block';
                    } else {
                        box.style.display = 'none';
                    }
                };

                input._hideSuggestions = function() {
                    box.style.display = 'none';
                };

                input.addEventListener('input', input._updateSuggestions);
                input.addEventListener('focus', input._updateSuggestions);
                input.addEventListener('blur', input._hideSuggestions);
            });
        }

        // ────────────────────────────────────────────────────
        // 1. Load courses (scoped to admin's department)
        // ────────────────────────────────────────────────────
        async function loadCourses() {
            try {
                const response = await fetch(`Course_api.php?department=${encodeURIComponent(getDept())}`);
                const data     = await response.json();
                if (data.status === 'error') throw new Error(data.message);
                allCourses = data;
                populateFilters(allCourses);
                applyFilters();
                initAutocompleteListeners();
            } catch (error) {
                console.error('Error loading courses:', error);
                document.getElementById('tableBody').innerHTML = `
                    <tr><td colspan="9" style="text-align:center; padding:40px; color:#ef4444;">
                        <i class="fas fa-exclamation-triangle" style="margin-bottom:10px; font-size:24px; display:block;"></i>
                        ${error.message || 'Error loading data.'}
                    </td></tr>`;
            }
        }

        function renderTable(courses) {
            const tbody = document.getElementById('tableBody');
            tbody.innerHTML = '';

            if (!courses || !courses.length) {
                tbody.innerHTML = `
                    <tr><td colspan="9" style="text-align:center; color:var(--text-muted); padding:60px 20px;">
                        <i class="fas fa-book-open" style="font-size:40px; color:#cbd5e1; margin-bottom:15px; display:block;"></i>
                        No courses found for this department.
                    </td></tr>`;
                return;
            }

            courses.forEach(course => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong style="color:var(--primary-blue);">${course.course_code}</strong></td>
                    <td style="font-weight:600;">${course.descriptive_title}</td>
                    <td><strong>${course.units}</strong></td>
                    <td>${course.lecture_hours}</td>
                    <td>${course.laboratory_hours}</td>
                    <td>${course.program}</td>
                    <td>${course.major || '-'}</td>
                    <td>${course.year_level}</td>
                    <td>${course.semester}</td>`;
                tr.onclick = () => openEditModal(course);
                tbody.appendChild(tr);
            });
        }

        // ────────────────────────────────────────────────────
        // 2. Filters
        // ────────────────────────────────────────────────────
        function populateFilters(data) {
            if (!data) return;

            const progSelect = document.getElementById('filterProgram');
            // If Program Head, lock dropdown to their specific program
            if (SESSION_POS === 'Program Head') {
                progSelect.innerHTML = `<option value="${SESSION_PROG}">${SESSION_PROG}</option>`;
                progSelect.disabled = true;
                progSelect.style.background = '#f1f5f9';
                progSelect.style.color = '#94a3b8';
                progSelect.style.cursor = 'not-allowed';
                progSelect.title = "View locked to your assigned program.";
            } else {
                fillSelect('filterProgram', [...new Set(data.map(c => c.program))].sort(), 'All Programs');
            }

            fillSelect('filterYear', [...new Set(data.map(c => c.year_level))].sort(), 'All Years');
            fillSelect('filterSem',  [...new Set(data.map(c => c.semester))].sort(),   'All Semesters');
        }

        function fillSelect(id, items, defaultText) {
            const select       = document.getElementById(id);
            const currentValue = select.value;
            select.innerHTML   = `<option value="All">${defaultText}</option>`;
            items.forEach(item => { if (item) select.innerHTML += `<option value="${item}">${item}</option>`; });
            if (items.includes(currentValue)) select.value = currentValue;
        }

        ['filterProgram', 'filterYear', 'filterSem'].forEach(id => {
            document.getElementById(id).addEventListener('change', applyFilters);
        });
        document.getElementById('searchInput').addEventListener('keyup', applyFilters);

        document.getElementById('resetFiltersBtn').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            
            // Only reset program if not a program head
            if (SESSION_POS !== 'Program Head') {
                document.getElementById('filterProgram').value = 'All';
            }
            
            document.getElementById('filterYear').value = 'All';
            document.getElementById('filterSem').value = 'All';
            applyFilters();
        });

        function applyFilters() {
            const prog       = document.getElementById('filterProgram').value;
            const year       = document.getElementById('filterYear').value;
            const sem        = document.getElementById('filterSem').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();

            renderTable(allCourses.filter(c =>
                (c.course_code.toLowerCase().includes(searchTerm) || 
                 c.descriptive_title.toLowerCase().includes(searchTerm) ||
                 (c.major && c.major.toLowerCase().includes(searchTerm))) &&
                (prog === 'All' || c.program === prog || SESSION_POS === 'Program Head') &&
                (year === 'All' || c.year_level === year) &&
                (sem  === 'All' || c.semester   === sem)
            ));
        }

        // ────────────────────────────────────────────────────
        // 3. Review / confirm modal
        // ────────────────────────────────────────────────────
        function showConfirmModal(action, formData) {
            pendingAction    = action;
            formDataToSubmit = formData;

            const cfg = {
                CREATE: { title: 'Verify Registration Details', btn: 'Confirm & Register', color: 'var(--primary-blue)', iconClass: 'fas fa-info-circle',          iconColor: 'var(--primary-blue)' },
                UPDATE: { title: 'Verify Update Details',       btn: 'Confirm Changes',    color: 'var(--primary-blue)', iconClass: 'fas fa-edit',                 iconColor: 'var(--primary-blue)' },
                DELETE: { title: 'Confirm Deletion',            btn: 'Confirm Delete',     color: '#ef4444',             iconClass: 'fas fa-exclamation-triangle',  iconColor: '#ef4444'             },
            };
            const c = cfg[action];

            document.getElementById('confirmTitle').innerText     = c.title;
            document.getElementById('confirmIcon').className       = c.iconClass;
            document.getElementById('confirmIcon').style.color     = c.iconColor;

            const btn = document.getElementById('btnConfirmAction');
            btn.innerText              = c.btn;
            btn.style.backgroundColor  = c.color;

            const getVal = key => formData.get(key) || '-';

            document.getElementById('confirmContent').innerHTML = `
                <div class="confirm-grid">
                    <div class="confirm-item"><strong>Department</strong><span>${getVal('department')}</span></div>
                    <div class="confirm-item"><strong>Program</strong><span>${getVal('program')}</span></div>
                    <div class="confirm-item"><strong>Major</strong><span>${getVal('major')}</span></div>
                    <div class="confirm-item"><strong>Year Level</strong><span>${getVal('year_level')}</span></div>
                    <div class="confirm-item"><strong>Semester</strong><span>${getVal('semester')}</span></div>
                    <hr style="grid-column:1/-1; border:0; border-top:1px dashed var(--border-color); margin:5px 0;">
                    <div class="confirm-item"><strong>Course Code</strong><span style="color:var(--primary-blue);">${getVal('course_code')}</span></div>
                    <div class="confirm-item"><strong>Units</strong><span>${getVal('units')}</span></div>
                    <div class="confirm-item full-width"><strong>Descriptive Title</strong><span>${getVal('descriptive_title')}</span></div>
                    <div class="confirm-item"><strong>Lec Hours</strong><span>${getVal('lecture_hours')}</span></div>
                    <div class="confirm-item"><strong>Lab Hours</strong><span>${getVal('laboratory_hours')}</span></div>
                </div>
                ${action === 'DELETE' ? '<p class="warning-text">You are about to DELETE this record. This cannot be undone.</p>' : ''}`;

            document.getElementById('confirmModal').style.display = 'block';
        }

        // ────────────────────────────────────────────────────
        // 4. Execute confirmed action
        // ────────────────────────────────────────────────────
        document.getElementById('btnConfirmAction').addEventListener('click', async function () {
            if (!formDataToSubmit || !pendingAction) return;

            this.disabled  = true;
            this.innerText = 'Processing...';

            try {
                // Ensure department is appended strictly to current view department
                if(!formDataToSubmit.has('department')) formDataToSubmit.append('department', getDept());
                
                const response = await fetch('Course_api.php', { method: 'POST', body: formDataToSubmit });
                if (!response.ok) throw new Error('Server Error');
                const result = await response.json();

                if (result.status === 'success') {

                    closeModal('confirmModal');

                    if (pendingAction === 'CREATE') {

                        const addMore = await sysConfirm(
                            `<b>${result.message}</b><br><br>Do you want to add another course to the same Program &amp; Year Level?`,
                            'Yes, add more',
                            'No, reset form',
                            'fa-check-circle',
                            '#10b981'
                        );

                        const form = document.getElementById('courseForm');
                        if (!addMore) {
                            // Full reset
                            form.reset();
                            form.querySelector('[name="department"]').value = getDept();
                            if (SESSION_POS === 'Program Head') {
                                form.querySelector('[name="program"]').value = SESSION_PROG;
                            }
                        } else {
                            // Keep program/major/year/sem, clear specific fields
                            form.querySelector('[name="course_code"]').value       = '';
                            form.querySelector('[name="descriptive_title"]').value = '';
                            form.querySelector('[name="units"]').value             = '';
                            form.querySelector('[name="lecture_hours"]').value     = '0';
                            form.querySelector('[name="laboratory_hours"]').value  = '0';
                        }

                    } else {
                        // UPDATE or DELETE
                        await sysAlert(result.message, 'fa-check-circle', '#10b981');
                        closeModal('editModal');
                    }

                    loadCourses();

                } else {
                    // Server returned status != success
                    await sysAlert(result.message, 'fa-exclamation-circle', '#ef4444');
                }

            } catch (error) {
                console.error('Submit Error:', error);
                await sysAlert('Something went wrong with the server. Please try again.', 'fa-wifi', '#ef4444');
            } finally {
                this.disabled  = false;
                this.innerText = 'Confirm';
            }
        });

        // ────────────────────────────────────────────────────
        // 5. Edit modal
        // ────────────────────────────────────────────────────
        function openEditModal(course) {
            const modal      = document.getElementById('editModal');
            const form       = document.getElementById('editForm');
            const sourceForm = document.getElementById('courseForm');

            form.innerHTML = sourceForm.innerHTML;

            const oldBtn = form.querySelector('.btn-register');
            if (oldBtn) oldBtn.closest('div[style]')?.remove() || oldBtn.parentElement.remove();
            const oldTitle = form.querySelector('h3');
            if (oldTitle) oldTitle.remove();

            const hiddenId  = document.createElement('input');
            hiddenId.type   = 'hidden';
            hiddenId.name   = 'course_id';
            hiddenId.value  = course.id;
            form.prepend(hiddenId);

            const btnGroup = document.createElement('div');
            btnGroup.style.cssText = 'display:flex; justify-content:flex-end; gap:15px; margin-top:30px;';
            btnGroup.innerHTML = `
                <button type="button" id="deleteBtn" class="btn-delete">
                    <i class="fas fa-trash-alt" style="margin-right:8px;"></i> Delete
                </button>
                <button type="submit" class="btn-register">Update Course</button>`;
            form.appendChild(btnGroup);

            for (const key in course) {
                const input = form.querySelector(`[name="${key}"]`);
                if (input) input.value = course[key];
            }

            // Lock department to the current active view
            const deptInput = form.querySelector('[name="department"]');
            if (deptInput) {
                deptInput.value             = getDept();
                deptInput.readOnly          = true;
                deptInput.style.background  = '#f1f5f9';
                deptInput.style.color       = '#94a3b8';
                deptInput.style.cursor      = 'not-allowed';
            }

            // Lock program if user is a Program Head
            const progInput = form.querySelector('[name="program"]');
            if (progInput && SESSION_POS === 'Program Head') {
                progInput.value             = SESSION_PROG;
                progInput.readOnly          = true;
                progInput.style.background  = '#f1f5f9';
                progInput.style.color       = '#94a3b8';
                progInput.style.cursor      = 'not-allowed';
            }

            // Re-bind autocomplete
            initAutocompleteListeners();

            form.onsubmit = e => {
                e.preventDefault();
                showConfirmModal('UPDATE', new FormData(form));
            };

            document.getElementById('deleteBtn').onclick = () => {
                const fd = new FormData();
                fd.append('delete_id',        course.id);
                fd.append('department',       course.department);
                fd.append('program',          course.program);
                fd.append('major',            course.major);
                fd.append('year_level',       course.year_level);
                fd.append('semester',         course.semester);
                fd.append('course_code',      course.course_code);
                fd.append('descriptive_title', course.descriptive_title);
                fd.append('units',            course.units);
                fd.append('lecture_hours',    course.lecture_hours);
                fd.append('laboratory_hours',  course.laboratory_hours);
                showConfirmModal('DELETE', fd);
            };

            modal.style.display = 'block';
        }

        // ────────────────────────────────────────────────────
        // 6. New course registration
        // ────────────────────────────────────────────────────
        document.getElementById('courseForm').addEventListener('submit', function (e) {
            e.preventDefault();
            showConfirmModal('CREATE', new FormData(this));
        });

        // ────────────────────────────────────────────────────
        // 7. Collaborative Polling (Auto-Refresh)
        // ────────────────────────────────────────────────────
        const POLL_INTERVAL = 5000;
        let isPolling = false;

        async function pollForChanges() {
            if (isPolling) return;
            isPolling = true;

            try {
                const response = await fetch(`Course_api.php?department=${encodeURIComponent(getDept())}`);
                const newData = await response.json();

                if (newData.status === 'error') throw new Error(newData.message);

                if (JSON.stringify(allCourses) !== JSON.stringify(newData)) {
                    allCourses = newData;
                    populateFilters(allCourses);
                    applyFilters(); 
                    initAutocompleteListeners();
                }
            } catch (error) {
                console.warn('Background sync failed:', error);
            } finally {
                isPolling = false;
            }
        }

        function startCollaborativeSync() {
            setInterval(pollForChanges, POLL_INTERVAL);
        }

        // Init
        window.onload = async () => {
            await loadCourses();
            startCollaborativeSync();
        };
    </script>

</body>
</html>