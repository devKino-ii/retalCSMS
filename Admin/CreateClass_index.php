<?php
// ============================================================
//  CREATE CLASS — COSMOS (ADMIN)
// ============================================================
session_start();

if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    $_SESSION = [];
    session_destroy();
    header('Location: ../Login/Login_index.php');
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../Login_index.php');
    exit();
}

$userName      = $_SESSION['full_name']        ?? '';
$userPosition  = $_SESSION['position']         ?? 'Staff';
$userDept      = $_SESSION['department']       ?? 'Department';
$userPic       = $_SESSION['profile_picture'] ?? null;

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
    <title>Create Class - COSMOS</title>
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
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-main);
            color: var(--text-dark);
            display: flex;
            height: 100vh;
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

        .sidebar:hover .logo-section img {
            width: 140px;
        }

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

        .sidebar:hover .menu-label {
            opacity: 1;
            max-height: 30px;
        }

        /* Nav links */
        .nav-menu {
            list-style: none;
            padding: 0;
        }

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

        .sidebar:hover .nav-menu li a {
            padding: 11px 14px;
            justify-content: flex-start;
        }

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

        .sidebar:hover .nav-menu i {
            margin-right: 12px;
        }

        /* Nav label text */
        .nav-menu li a span {
            display: inline-block;
            width: 0;
            opacity: 0;
            overflow: hidden;
            white-space: nowrap;
            transition: opacity 0.2s ease 0.05s, width 0.3s ease;
        }

        .sidebar:hover .nav-menu li a span {
            width: auto;
            opacity: 1;
        }

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

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

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

        .header-title p {
            font-size: 14px;
            font-weight: 400;
            color: var(--text-muted);
        }

        .container {
            flex: 1;
            overflow: hidden;
            padding: 20px 40px 40px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* ── Search / filter bar ──────────────────────────────── */
        .search-container {
            flex-shrink: 0;
            background: var(--bg-card);
            border-radius: var(--radius-card);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card);
            padding: 25px 30px;
            display: flex;
            align-items: flex-end;
            gap: 15px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 150px;
        }

        .form-group label {
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
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 15px top 50%;
            background-size: 10px auto;
            transition: all 0.2s ease;
        }

        .pill-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--accent-blue);
            background-color: white;
        }

        .pill-input[disabled] {
            background-image: none;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-find {
            background: var(--primary-blue);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: var(--radius-pill);
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .btn-find:hover {
            background: var(--primary-blue-hover);
            transform: translateY(-1px);
        }

        /* ── Calendar controls ────────────────────────────────── */
        .cal-controls {
            flex-shrink: 0;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-bar {
            flex: 1;
            position: relative;
            min-width: 250px;
        }

        .search-bar input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            outline: none;
            background: var(--bg-card);
            box-shadow: var(--shadow-card);
            transition: all 0.2s ease;
        }

        .search-bar input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--accent-blue);
        }

        .search-bar i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 16px;
        }

        .filter-select {
            padding: 12px 35px 12px 18px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 500;
            background: var(--bg-card);
            color: var(--text-dark);
            cursor: pointer;
            outline: none;
            min-width: 140px;
            box-shadow: var(--shadow-card);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat;
            background-position: right 15px top 50%;
            background-size: 10px auto;
            transition: all 0.2s ease;
        }

        .filter-select:hover,
        .filter-select:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--accent-blue);
        }

        /* ── Calendar ─────────────────────────────────────────── */
        .calendar-wrapper {
            flex: 1;
            overflow: auto;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-card);
        }

        .cal-grid {
            display: grid;
            grid-template-rows: 50px repeat(14, 100px);
            width: max-content;
            min-width: 100%;
            position: relative;
        }

        .cal-header {
            background: #fafbfc;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: var(--text-dark);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .cal-header:first-child { position: sticky; left: 0; top: 0; z-index: 120; }

        .cal-time {
            background: #fafbfc;
            border-right: 1px solid var(--border-color);
            border-bottom: 1px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-muted);
            height: 100px;
            position: sticky;
            left: 0;
            z-index: 110;
        }

        .cal-cell {
            border-right: 1px solid var(--border-color);
            border-bottom: 1px dashed var(--border-color);
            position: relative;
            height: 100px;
            background: var(--bg-card);
        }

        .card {
            position: absolute;
            width: 94%;
            left: 3%;
            color: white;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 11px;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            z-index: 5;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            line-height: 1.4;
            overflow-y: auto;
            overflow-x: hidden;
            word-wrap: break-word;
            white-space: normal;
            transition: all 0.2s ease;
        }

        .card::-webkit-scrollbar        { width: 4px; }
        .card::-webkit-scrollbar-track { background: transparent; }
        .card::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.4); border-radius: 4px; }

        .card:hover {
            transform: scale(1.02) translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
            z-index: 50;
        }

        .pending-overlay {
            opacity: 0.95;
            border: 2px dashed rgba(255, 255, 255, 0.7);
        }

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
            padding: 20px;
            backdrop-filter: blur(4px);
        }

        .modal-content {
            background: var(--bg-card);
            margin: 2% auto;
            padding: 30px 40px;
            width: 98%;
            max-width: 1400px;
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

        .details-header-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px dashed var(--border-color);
        }

        .details-row {
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            justify-content: center;
            width: 100%;
        }

        .detail-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-label {
            font-weight: 700;
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }

        .detail-pill-wrapper {
            width: 180px;
            position: relative;
        }

        .detail-pill {
            width: 100%;
            padding: 10px 15px;
            border-radius: var(--radius-pill);
            font-weight: 600;
            color: var(--text-dark);
            outline: none;
            text-align: center;
            font-size: 13px;
            border: 1px solid var(--border-color);
        }

        .detail-pill.readonly-pill  { background: #f8fafc; cursor: default; }
        .detail-pill.editable-pill  { background: var(--accent-blue); border-color: var(--primary-blue); color: var(--primary-blue); cursor: pointer; transition: 0.2s; }
        .detail-pill.editable-pill:hover { background: white; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27, 80, 136, 0.1); }

        /* ── Schedule table ───────────────────────────────────── */
        .schedule-table-wrapper {
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-input);
            background: white;
            padding-bottom: 20px;
            transition: padding 0.3s ease;
        }

        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: center;
            table-layout: fixed;
            word-wrap: break-word;
        }

        .schedule-table th {
            background: #f8fafc;
            color: var(--text-muted);
            font-weight: 700;
            padding: 12px 6px;
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        .schedule-table th:last-child { border-right: none; }

        .schedule-table td {
            padding: 8px 6px;
            border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            vertical-align: middle;
            color: var(--text-dark);
            font-size: 12px;
        }

        .schedule-table td:last-child              { border-right: none; }
        .schedule-table tbody tr:last-child td     { border-bottom: none; }
        .schedule-table tbody tr:hover td          { background: #f8fafc; }
        .schedule-table tr.conflict-row td         { background: #fef2f2 !important; border-bottom-color: #fecaca; border-right-color: #fecaca; }
        .schedule-table tr.conflict-row td:first-child { border-left: 4px solid #ef4444; }
        .schedule-table tr.combinable-row td       { background: #fffbeb !important; border-bottom-color: #fde68a; border-right-color: #fde68a; }
        .schedule-table tr.combinable-row td:first-child { border-left: 4px solid #f59e0b; }

        .table-input {
            width: 100%;
            padding: 8px 6px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            outline: none;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            color: var(--text-dark);
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .table-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 2px var(--accent-blue); }
        .table-input.clickable { cursor: pointer; background: white; }
        .table-input.clickable:hover { background: #f8fafc; border-color: var(--primary-blue); }
        .table-input:disabled { background: #f1f5f9 !important; color: #94a3b8 !important; cursor: not-allowed !important; border-color: var(--border-color) !important; }

        .input-conflict   { border-color: #ef4444 !important; background: #fef2f2 !important; color: #b91c1c !important; box-shadow: 0 0 0 2px #fecaca !important; }
        .input-combinable { border-color: #f59e0b !important; background: #fffbeb !important; color: #b45309 !important; box-shadow: 0 0 0 2px #fde68a !important; }
        .input-warning    { border-color: #f59e0b !important; background: #fffbeb !important; color: #b45309 !important; }

        /* ── Action buttons ───────────────────────────────────── */
        .btn-submit-large {
            background: var(--primary-blue);
            color: white;
            font-size: 14px;
            font-weight: 700;
            padding: 12px 40px;
            border: none;
            border-radius: var(--radius-pill);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.2s;
        }

        .btn-submit-large:hover {
            background: var(--primary-blue-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2);
        }

        .btn-clear-row {
            background: #fee2e2;
            color: #ef4444;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-clear-row:hover { background: #ef4444; color: white; }

        .btn-duplicate-row {
            background: #e0f2fe;
            color: #0284c7;
            border: none;
            width: 28px;
            height: 28px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .btn-duplicate-row:hover { background: #0284c7; color: white; }

        /* ── Custom dropdowns ─────────────────────────────────── */
        .custom-dropdown-wrapper { position: relative; width: 100%; }

        .custom-dropdown-list {
            display: none;
            position: absolute;
            z-index: 1000;
            background: white;
            width: max-content;
            min-width: 100%;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            max-height: 220px;
            overflow-y: auto;
            padding: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            top: calc(100% + 5px);
            left: 0;
        }

        .custom-dropdown-list.drop-up { top: auto !important; bottom: calc(100% + 5px) !important; box-shadow: 0 -10px 25px rgba(0, 0, 0, 0.15) !important; }

        .opt-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: var(--text-dark);
            padding: 10px 12px;
            cursor: pointer;
            border-radius: 8px;
            text-align: left;
            transition: all 0.2s ease;
        }

        .opt-item:hover { background: var(--accent-blue); color: var(--primary-blue); }

        .chk-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            padding: 8px 10px;
            cursor: pointer;
            border-radius: 8px;
            color: var(--text-dark);
            transition: all 0.2s ease;
        }

        .chk-label:hover { background: var(--accent-blue); color: var(--primary-blue); }

        .opt-item.conflicted-opt  { background: #fef2f2; color: #ef4444; }
        .opt-item.conflicted-opt:hover { background: #fee2e2; color: #b91c1c; }
        .chk-label.conflicted-opt { background: #fef2f2; color: #ef4444; cursor: not-allowed; }
        .chk-label.conflicted-opt:hover { background: #fee2e2; color: #b91c1c; }
        .opt-item.combine-opt  { background: #fffbeb; color: #b45309; }
        .opt-item.combine-opt:hover { background: #fde68a; color: #92400e; }

        /* ── System modals ────────────────────────────────────── */
        .sys-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.7);
            z-index: 2000;
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

        .sys-modal-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-size: 14px;
            text-align: center;
            font-weight: 600;
            margin-bottom: 20px;
            outline: none;
        }

        .sys-modal-input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px var(--accent-blue);
        }

        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }

        #modalSplit .sys-modal-content { max-width: 550px; }

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

            body.mobile-nav-active .sidebar .nav-menu li a              { justify-content: flex-start; padding: 11px 14px; }
            body.mobile-nav-active .sidebar .nav-menu i                 { margin-right: 12px; }
            body.mobile-nav-active .sidebar .nav-menu li a span         { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .menu-label                 { opacity: 1; max-height: 30px; }
            body.mobile-nav-active .sidebar .profile-info               { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logout-btn                 { width: auto; opacity: 1; }
            body.mobile-nav-active .sidebar .logo-section img           { width: 140px; }

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
            <li><a href="Dashboard_index.php"><i class="fas fa-th-large"></i><span>Dashboard</span></a></li>
            <li><a href="CreateClass_index.php" class="active"><i class="fas fa-plus-circle"></i><span>Create Class</span></a></li>
            <li><a href="Monitoring_index.php"><i class="fas fa-desktop"></i><span>Class Monitoring</span></a></li>
        </ul>

        <div class="menu-label">Records</div>
        <ul class="nav-menu">
            <li><a href="User_Account_index.php"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
            <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Room_index.php"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
            <li><a href="History_index.php"><i class="fas fa-history"></i><span>Global History</span></a></li>
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

            <div class="search-container">
                <div style="width:100%; display:flex; justify-content:space-between; align-items:baseline; margin-bottom:5px;">
                    <h2 style="color:var(--text-dark); font-size:20px; font-weight:800;">Create Class Schedule</h2>
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <?php if ($isAdmin): ?>
                        <select id="s_dept" class="pill-input" onchange="onDepartmentChange()">
                            <?php foreach($allDepartments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" 
                                    <?php echo ($dept === $userDept) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input type="text" id="disp_s_dept" class="pill-input"
                               value="<?php echo htmlspecialchars($userDept); ?>" disabled>
                        <input type="hidden" id="s_dept" value="<?php echo htmlspecialchars($userDept); ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Program</label>
                    <div class="custom-dropdown-wrapper">
                        <input type="text" id="s_prog" class="pill-input" placeholder="e.g., Computer Science"
                               autocomplete="off" required
                               onclick="toggleStandaloneDrop('drop_s_prog')"
                               onkeyup="filterDrop(this, 'drop_s_prog')">
                        <div id="drop_s_prog" class="custom-dropdown-list" style="top:45px;"></div>
                    </div>
                </div>

                <div class="form-group" id="group_s_major" style="display: none;">
                    <label>Major</label>
                    <div class="custom-dropdown-wrapper">
                        <input type="text" id="s_major" class="pill-input" placeholder="Select Major" 
                               autocomplete="off" 
                               onclick="toggleStandaloneDrop('drop_s_major')" 
                               onkeyup="filterDrop(this, 'drop_s_major')">
                        <div id="drop_s_major" class="custom-dropdown-list" style="top:45px;"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Year</label>
                    <select id="s_year" class="pill-input" required>
                        <option value="" disabled selected hidden>Select Year</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <select id="s_sem" class="pill-input">
                        <option value="1st Semester">1st Semester</option>
                        <option value="2nd Semester">2nd Semester</option>
                    </select>
                </div>

                <button onclick="findCourses()" class="btn-find">
                    <i class="fas fa-search" style="margin-right:8px;"></i> Find Courses
                </button>
            </div>

            <div class="cal-controls">
                <div class="search-bar">
                    <input type="text" id="filterInput" placeholder="Search subject, major, prof...">
                    <i class="fas fa-search"></i>
                </div>
                <select id="filterAY" class="filter-select"
                        style="background-color:var(--primary-blue); color:white; border-color:var(--primary-blue);
                               background-image:url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23ffffff%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E');"></select>
                <select id="filterSem" class="filter-select">
                    <option value="1st Semester">1st Semester</option>
                    <option value="2nd Semester">2nd Semester</option>
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
                <div id="calGrid" class="cal-grid"></div>
            </div>

        </div>
    </div>

    <div id="modalBulk" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeM('modalBulk', false)">&times;</span>
            <h2 style="text-align:center; color:var(--text-dark); margin-bottom:25px; font-weight:800; font-size:22px;">
                Create Class Schedule
            </h2>

            <div class="details-header-wrapper">
                <div class="details-row">
                    <div class="detail-group">
                        <span class="detail-label">Department:</span>
                        <input id="b_dept" class="detail-pill readonly-pill" readonly>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Program:</span>
                        <input id="b_prog" class="detail-pill readonly-pill" readonly>
                    </div>
                    <div class="detail-group" id="b_major_group" style="display:none;">
                        <span class="detail-label">Major:</span>
                        <input id="b_major" class="detail-pill readonly-pill" readonly>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Year Level:</span>
                        <input id="b_year" class="detail-pill readonly-pill" readonly>
                    </div>
                </div>
                <div class="details-row" style="margin-top:15px;">
                    <div class="detail-group">
                        <span class="detail-label">Semester:</span>
                        <input id="b_sem" class="detail-pill readonly-pill" readonly>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Section:</span>
                        <div class="custom-dropdown-wrapper detail-pill-wrapper">
                            <input type="text" id="b_sec" class="detail-pill editable-pill"
                                   placeholder="Select Section" autocomplete="off"
                                   onclick="toggleStandaloneDrop('drop_b_sec')"
                                   onkeyup="filterDrop(this, 'drop_b_sec'); delayedCheckUnlock();"
                                   onblur="delayedCheckUnlock()">
                            <div id="drop_b_sec" class="custom-dropdown-list"></div>
                        </div>
                    </div>
                    <div class="detail-group">
                        <span class="detail-label">Academic Year:</span>
                        <div class="custom-dropdown-wrapper detail-pill-wrapper">
                            <input type="text" id="b_ay" class="detail-pill editable-pill"
                                   placeholder="Select AY" autocomplete="off"
                                   onclick="toggleStandaloneDrop('drop_b_ay')"
                                   onkeyup="filterDrop(this, 'drop_b_ay'); delayedCheckUnlock();"
                                   onblur="delayedCheckUnlock()">
                            <div id="drop_b_ay" class="custom-dropdown-list"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="tableWrapper" style="pointer-events:none; opacity:0.4; transition:opacity 0.3s ease;">

                <div style="display:flex; justify-content:flex-end; align-items:center; gap:20px; margin-bottom:12px; padding:0 5px;">
                    <span style="font-size:11px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px;">Legend:</span>
                    <div style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:var(--text-dark);">
                        <div style="width:14px; height:14px; border-radius:4px; background:white; border:1px solid var(--border-color);"></div> Clear
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#b45309;">
                        <div style="width:14px; height:14px; border-radius:4px; background:#fffbeb; border:1px solid #fde68a;"></div> Combinable / Warning
                    </div>
                    <div style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; color:#b91c1c;">
                        <div style="width:14px; height:14px; border-radius:4px; background:#fef2f2; border:1px solid #fecaca;"></div> Hard Conflict
                    </div>
                </div>

                <div class="schedule-table-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th style="width:10%;">Day</th>
                                <th style="width:16%;">Time</th>
                                <th style="width:10%;">Course Code</th>
                                <th style="width:18%;">Subject Description</th>
                                <th style="width:4%;">Unit</th>
                                <th colspan="2" style="width:8%; padding:5px;">
                                    Hours/Week<br>
                                    <span style="font-size:9px; font-weight:600; color:#64748b;">Lec &nbsp;|&nbsp; Lab</span>
                                </th>
                                <th style="width:14%;">Room</th>
                                <th style="width:14%;">Professor</th>
                                <th style="width:6%;"><i class="fas fa-cog"></i></th>
                            </tr>
                        </thead>
                        <tbody id="bulkRowsContainer"></tbody>
                    </table>
                </div>
            </div>

            <div style="display:flex; justify-content:flex-end; align-items:center; margin-top:25px; gap:15px;">
                <button type="button" id="btnDelete" onclick="deleteSectionSchedule()"
                        style="display:none; background:#ef4444; color:white; border:none;
                               padding:12px 25px; border-radius:99px; cursor:pointer;
                               font-weight:700; font-size:13px; transition:0.2s;">
                    <i class="fas fa-trash-alt" style="margin-right:5px;"></i> Delete Schedule
                </button>
                <button onclick="saveBulkClass()" class="btn-submit-large">Save Schedule</button>
            </div>
        </div>
    </div>

    <div id="modalSplit" class="sys-modal-backdrop" style="z-index:2005;">
        <div class="sys-modal-content">
            <i class="fas fa-code-branch sys-modal-icon" style="color:var(--primary-blue);"></i>
            <h3 style="margin-bottom:10px; color:var(--text-dark); font-weight:800; font-size:20px;">Add Schedule Row</h3>
            <p class="sys-modal-msg" style="text-align:justify;">
                How would you like to add another row for this subject?<br><br>
                Click <b>'Continuation'</b> if this is just another day for the same class. This sets Units/Hours to <b>0</b>
                so it doesn't double-count on the faculty workload.<br><br>
                Click <b>'Exact Copy'</b> to duplicate the units and hours completely.
            </p>
            <div class="sys-modal-btns" style="flex-wrap:wrap;">
                <button onclick="closeSplitModal()" class="btn-submit-large"
                        style="background:#e2e8f0; color:#475569; padding:10px 20px; font-size:13px;">Cancel</button>
                <button onclick="executeDuplicate('copy')" class="btn-submit-large"
                        style="background:#3b82f6; padding:10px 20px; font-size:13px;">Exact Copy</button>
                <button onclick="executeDuplicate('continue')" class="btn-submit-large"
                        style="background:#10b981; padding:10px 20px; font-size:13px;">Continuation (0 Units)</button>
            </div>
        </div>
    </div>

    <div id="sysModalAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalAlertIcon" class="sys-modal-icon"></i>
            <div id="sysModalAlertMsg" class="sys-modal-msg"></div>
            <button id="sysModalAlertBtn" class="btn-submit-large" style="width:100%;">OK</button>
        </div>
    </div>

    <div id="sysModalConfirm" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalConfirmIcon" class="sys-modal-icon"></i>
            <div id="sysModalConfirmMsg" class="sys-modal-msg"></div>
            <div class="sys-modal-btns">
                <button id="sysModalConfirmCancelBtn" class="btn-submit-large"
                        style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalConfirmOkBtn" class="btn-submit-large" style="flex:1;">Confirm</button>
            </div>
        </div>
    </div>

    <div id="sysModalPrompt" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysModalPromptIcon" class="sys-modal-icon"></i>
            <div id="sysModalPromptMsg" class="sys-modal-msg"></div>
            <input type="text" id="sysModalPromptInput" class="sys-modal-input"
                   placeholder="Type command here..." autocomplete="off">
            <div class="sys-modal-btns">
                <button id="sysModalPromptCancelBtn" class="btn-submit-large"
                        style="flex:1; background:#e2e8f0; color:#475569;">Cancel</button>
                <button id="sysModalPromptOkBtn" class="btn-submit-large" style="flex:1;">Submit</button>
            </div>
        </div>
    </div>

    <script>
        let rooms = [];
        let facultyData = [];
        let globalEvents = [];
        let globalAllSchedules = [];
        let currentCoursesData = [];
        let currentLoadedSection = '';
        let currentLoadedAY = '';
        let splitTargetIdx = null;
        let programColorMap = {}; // synchronized cache
        let programsMap = {}; 

        // ── Select Program Function to Dynamically Toggle Major ──
        window.selectProgram = function(p) {
            setStandaloneSelect('s_prog', p);
            
            const majors = programsMap[p] || [];
            const majorGroup = document.getElementById('group_s_major');
            const majorInput = document.getElementById('s_major');
            const majorDrop = document.getElementById('drop_s_major');

            if (majors.length > 0) {
                majorGroup.style.display = 'block';
                majorInput.value = '';
                majorDrop.innerHTML = majors.map(m =>
                    `<div class="opt-item" onclick="setStandaloneSelect('s_major','${m}')">${m}</div>`
                ).join('');
            } else {
                majorGroup.style.display = 'none';
                majorInput.value = '';
            }
        };

        // ── Deterministic Color Palette (Matched to Dashboard) ──
        const PROGRAM_PALETTE = [
            '#1b5088', '#0e7490', '#7c3aed', '#be185d', '#15803d', '#c2410c', 
            '#1d4ed8', '#a16207', '#0f766e', '#9333ea', '#b91c1c', '#0369a1', 
            '#4d7c0f', '#d97706', '#0891b2', '#7e22ce', '#047857', '#db2777'
        ];

        // ── Admin Department Retrieval ──
        function getDept() {
            return document.getElementById('s_dept').value;
        }

        async function onDepartmentChange() {
            // Update the hidden input used in the Bulk Modal
            const bDept = document.getElementById('b_dept');
            if(bDept) bDept.value = getDept();
            
            // Clear current selections
            document.getElementById('s_prog').value = '';
            document.getElementById('s_year').value = '';
            document.getElementById('calGrid').innerHTML = '';
            
            // Reload everything for the new department
            await loadRooms();
            await loadSuggs();
            await fetchFaculty();
            await fetchAllSchedules();
            await applyLatestContext();
            await loadCal();
        }

        // ── Deterministic Hash (Matched to Dashboard) ──
        function hashProgram(str) {
            let hash = 5381;
            for (let i = 0; i < str.length; i++) {
                hash = ((hash << 5) + hash) + str.charCodeAt(i);
                hash = hash & 0xffffffff;
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

        // ── Promise-based system modals ───────────────────────
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
                document.getElementById('sysModalConfirm').style.display = 'flex';
                const okBtn = document.getElementById('sysModalConfirmOkBtn');
                okBtn.style.backgroundColor = color;
                document.getElementById('sysModalConfirmCancelBtn').onclick = () => {
                    document.getElementById('sysModalConfirm').style.display = 'none';
                    resolve(false);
                };
                document.getElementById('sysModalConfirmOkBtn').onclick = () => {
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

        // ── Initialise ────────────────────────────────────────
        window.onload = async () => {
            await loadRooms();
            await loadSuggs();
            await fetchFaculty();
            await applyLatestContext();
            await fetchAllSchedules();
            await loadCal();

            // Real-time polling
            setInterval(async () => {
                const bulkModal = document.getElementById('modalBulk');
                if (bulkModal && bulkModal.style.display !== 'block') {
                    await fetchAllSchedules();
                    await loadCal();
                }
            }, 10000);
        };

        function adjustDropdownPosition(listEl, wrapperEl) {
            listEl.classList.remove('drop-up');
        }

        async function fetchAllSchedules() {
            try {
                const res = await fetch(`CreateClass_api.php?action=get_schedules&ay_filter=All&department=${encodeURIComponent(getDept())}`);
                globalAllSchedules = await res.json();
            } catch (e) { console.error('Could not fetch global schedules:', e); }
        }

        async function applyLatestContext() {
            try {
                const res    = await fetch(`CreateClass_api.php?action=get_latest_context&department=${encodeURIComponent(getDept())}`);
                const latest = await res.json();
                if (latest && Object.keys(latest).length > 0) {
                    if (latest.academic_year) {
                        const ayFilter = document.getElementById('filterAY');
                        if ([...ayFilter.options].some(o => o.value === latest.academic_year)) {
                            ayFilter.value = latest.academic_year;
                        }
                    }
                    if (latest.semester) document.getElementById('filterSem').value = latest.semester;
                }
            } catch (e) { console.error('Context error:', e); }
        }

        async function loadRooms() {
            const res = await fetch(`CreateClass_api.php?action=get_rooms&department=${encodeURIComponent(getDept())}`);
            rooms = await res.json();
        }

        async function loadSuggs() {
            const res  = await fetch(`CreateClass_api.php?action=get_suggestions&department=${encodeURIComponent(getDept())}`);
            const data = await res.json();

            const ayFilter   = document.getElementById('filterAY');
            const progFilter = document.getElementById('filterProg');
            
            const currentAy = ayFilter.value;
            
            ayFilter.innerHTML   = '';
            progFilter.innerHTML = '<option value="All">All Programs</option>';

            data.ays.forEach(a => ayFilter.innerHTML += `<option value="${a}">${a}</option>`);
            
            programsMap = data.programs_map || {};
            const progList = Object.keys(programsMap);
            progList.forEach(p => progFilter.innerHTML += `<option value="${p}">${p}</option>`);
            
            if (currentAy && [...ayFilter.options].some(o => o.value === currentAy)) {
                ayFilter.value = currentAy;
            }

            document.getElementById('drop_b_sec').innerHTML = data.sections.map(s =>
                `<div class="opt-item" onclick="setStandaloneSelect('b_sec','${s}')">${s}</div>`
            ).join('');
            document.getElementById('drop_b_ay').innerHTML = data.ays.map(a =>
                `<div class="opt-item" onclick="setStandaloneSelect('b_ay','${a}')">${a}</div>`
            ).join('');
            
            document.getElementById('drop_s_prog').innerHTML = progList.map(p =>
                `<div class="opt-item" onclick="window.selectProgram('${p}')">${p}</div>`
            ).join('');
        }

        async function fetchFaculty() {
            const res  = await fetch(`CreateClass_api.php?action=get_faculty&department=${encodeURIComponent(getDept())}`);
            facultyData = await res.json();
        }

        async function loadCal() {
            const ayFilter = document.getElementById('filterAY').value;
            const res      = await fetch(`CreateClass_api.php?action=get_schedules&ay_filter=${encodeURIComponent(ayFilter)}&department=${encodeURIComponent(getDept())}`);
            const schedules = await res.json();

            globalEvents = schedules.map(d => {
                const [sh, sm] = d.start_time.split(':').map(Number);
                const [eh, em] = d.end_time.split(':').map(Number);
                const startMins = sh * 60 + sm;
                const endMins   = eh * 60 + em;
                return {
                    data: d, startMins, endMins,
                    startHour: sh, startMin: sm,
                    height: (endMins - startMins) * (100 / 60),
                    roomId: d.room_id,
                };
            });

            filterGrid();
        }

        document.getElementById('filterAY').addEventListener('change', loadCal);
        ['filterInput', 'filterProg', 'filterSem', 'filterDay'].forEach(id => {
            document.getElementById(id).addEventListener(id === 'filterInput' ? 'keyup' : 'change', filterGrid);
        });

        function filterGrid() {
            const term = document.getElementById('filterInput').value.toLowerCase();
            const prog = document.getElementById('filterProg').value;
            const sem  = document.getElementById('filterSem').value;
            const day  = document.getElementById('filterDay').value;

            const visibleEvents = globalEvents.filter(ev => {
                const d = ev.data;
                const str = `${d.course_code} ${d.descriptive_title} ${d.program} ${d.major || ''} ${d.section} ${d.professor}`.toLowerCase();
                return (!term || str.includes(term))
                    && (prog === 'All' || d.program === prog)
                    && (sem  === 'All' || d.semester === sem)
                    && (day  === 'All' || (d.days && d.days.includes(day)));
            });

            const activeRoomIds = new Set(visibleEvents.map(ev => String(ev.roomId)));
            
            let displayRooms = [...rooms];
            visibleEvents.forEach(ev => {
                const rId = String(ev.roomId);
                const d = ev.data;
                if (rId.startsWith('REQ_') && !displayRooms.some(r => String(r.id) === rId)) {
                    const shortDept = rId.replace('REQ_College of ', '');
                    displayRooms.push({ id: rId, room_name: '⏳ Req: ' + shortDept, status: 'Virtual', department: 'Requested' });
                } else if (rId === '0' && !displayRooms.some(r => String(r.id) === '0')) {
                    displayRooms.push({ id: '0', room_name: '⏳ TBA Room', status: 'Virtual', department: 'Virtual' });
                } else if (d.assigned_room_display && !displayRooms.some(r => String(r.id) === rId)) {
                    displayRooms.push({ id: rId, room_name: d.assigned_room_display, status: 'Approved External', department: 'External' });
                }
            });

            const sortedRooms = displayRooms.sort((a, b) => {
                const aa = activeRoomIds.has(String(a.id));
                const ba = activeRoomIds.has(String(b.id));
                return aa && !ba ? -1 : !aa && ba ? 1 : a.room_name.localeCompare(b.room_name);
            });

            const grid = document.getElementById('calGrid');

            if (!sortedRooms.length) {
                grid.style.gridTemplateColumns = '1fr';
                grid.innerHTML = `
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center;
                                padding:80px 20px; color:#64748b; font-weight:600; text-align:center;
                                grid-column:1/-1; min-height:400px;">
                        <i class="fas fa-door-closed" style="font-size:50px; color:#cbd5e1; margin-bottom:15px;"></i>
                        <p style="font-size:18px; color:var(--primary-blue);">No Rooms Available</p>
                    </div>`;
                return;
            }

            grid.style.gridTemplateColumns = `80px repeat(${sortedRooms.length}, 200px)`;

            let html = '<div class="cal-header">Time</div>';
            
            // Format headers to distinguish University rooms
            sortedRooms.forEach(r => {
                let displayHtml = r.room_name;
                if (r.department === 'University') {
                    displayHtml += ` <span style="font-size:9px; background:#1e40af; color:white; padding:2px 4px; border-radius:4px; margin-left:6px;">Univ</span>`;
                }
                html += `<div class="cal-header">${displayHtml}</div>`;
            });

            for (let h = 7; h <= 20; h++) {
                const label = (h > 12 ? h - 12 : h) + (h >= 12 ? ' PM' : ' AM');
                html += `<div class="cal-time">${label}</div>`;
                sortedRooms.forEach(r => html += `<div class="cal-cell" id="c-${String(r.id).replace(/\s+/g, '_')}-${h}"></div>`);
            }
            grid.innerHTML = html;

            const grouped = {};
            visibleEvents.forEach(ev => {
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

            visibleEvents.forEach(ev => {
                const d    = ev.data;
                const safeRoomId = String(ev.roomId).replace(/\s+/g, '_');
                const cell = document.getElementById(`c-${safeRoomId}-${ev.startHour}`);
                if (!cell) return;

                const group = grouped[ev.roomId] || [];
                const uniqueRoomProgs = new Set(group.map(e => e.data.program));
                const isSharedRoom = uniqueRoomProgs.size > 1;

                const card = document.createElement('div');
                card.className = 'card';
                card.onclick = () => confirmEditSection(d);
                card.style.top    = `${ev.startMin * (100 / 60)}px`;
                card.style.height = `${ev.height}px`;
                card.style.left   = `${ev.left}%`;
                card.style.width  = `${ev.width}%`;

                // ── Dynamic Color Logic (Matched to Dashboard) ──
                const progColor = getProgramColor(d.program);
                let conflictBadge = '';
                
                if (d.approval_status === 'Pending') {
                    card.style.backgroundColor = darkenColor(progColor, 0.35); // 35% darken for pending
                } else {
                    card.style.backgroundColor = progColor;
                    if (isSharedRoom) {
                        card.style.borderLeft = '4px solid #f59e0b'; // Amber for shared
                    }
                }

                const timeRng = `${convertTime(d.start_time)} - ${convertTime(d.end_time)}`;
                let profName  = (d.professor || '').toUpperCase();
                if (profName !== 'TBA' && profName !== 'GENERAL EDUCATION' && !profName.startsWith('PROF.') && !profName.startsWith('⏳')) {
                    profName = 'PROF. ' + profName;
                }
                
                let roomDisplay = '';
                if (String(ev.roomId).startsWith('REQ_') || String(ev.roomId) === '0' || d.assigned_room_display) {
                    const rMatch = displayRooms.find(r => String(r.id) === String(ev.roomId));
                    roomDisplay = `<span style="opacity:0.9; margin-bottom:2px; font-weight:700;">${rMatch ? rMatch.room_name : 'TBA Room'}</span>`;
                }

                const isPending    = d.approval_status === 'Pending';
                const pendingBadge = isPending
                    ? `<div style="background:rgba(255,255,255,0.9); color:#d97706; font-size:9px; font-weight:800; padding:2px 4px; border-radius:4px; margin-bottom:4px; text-align:center; box-shadow:0 2px 4px rgba(0,0,0,0.1);">⏳ PENDING</div>` : '';

                if (isPending) card.classList.add('pending-overlay');

                card.innerHTML = `
                    ${conflictBadge}
                    ${pendingBadge}
                    <b style="font-size:12px; margin-bottom:4px;">${d.course_code}</b>
                    <span style="font-size:10px; font-weight:700; background:rgba(0,0,0,0.15); padding:2px 6px; border-radius:4px; display:inline-block; margin-bottom:4px; width:max-content;">${d.days.toUpperCase()}</span>
                    <span style="opacity:0.9; margin-bottom:2px;">${d.program}, ${d.section}</span>
                    ${roomDisplay}
                    <span style="opacity:0.9; margin-bottom:2px;">${profName}</span>
                    <span style="font-weight:600; margin-top:auto;">${timeRng}</span>`;

                cell.appendChild(card);
            });
        }

        function delayedCheckUnlock() {
            setTimeout(checkUnlockLower, 200);
        }

        function checkUnlockLower() {
            const sec       = document.getElementById('b_sec').value.trim();
            const ay        = document.getElementById('b_ay').value.trim();
            const container = document.getElementById('tableWrapper');
            if (sec !== '' && ay !== '') {
                container.style.pointerEvents = 'auto';
                container.style.opacity       = '1';
                autoLoadExistingSchedule();
            } else {
                container.style.pointerEvents = 'none';
                container.style.opacity       = '0.4';
            }
        }

        function autoLoadExistingSchedule() {
            const p   = document.getElementById('b_prog').value;
            const y   = document.getElementById('b_year').value;
            const s   = document.getElementById('b_sem').value;
            const sec = document.getElementById('b_sec').value;
            const ay  = document.getElementById('b_ay').value;

            if (sec === currentLoadedSection && ay === currentLoadedAY) return;

            currentLoadedSection = sec;
            currentLoadedAY      = ay;

            const sectionSchedules = globalAllSchedules.filter(sch =>
                sch.program       === p   &&
                sch.year_level    === y   &&
                sch.section       === sec &&
                sch.academic_year === ay  &&
                sch.semester      === s
            );

            renderRows(currentCoursesData, sectionSchedules);
            document.getElementById('btnDelete').style.display =
                sectionSchedules.length > 0 ? 'inline-block' : 'none';
        }

        async function findCourses() {
            const d      = getDept();
            const pInput = document.getElementById('s_prog');
            const mInput = document.getElementById('s_major');
            const yInput = document.getElementById('s_year');
            const s      = document.getElementById('s_sem').value;

            if (!pInput.value.trim()) {
                await sysAlert('Please enter or select a Program.', 'fa-exclamation-circle');
                pInput.focus(); return;
            }
            if (!yInput.value) {
                await sysAlert('Please select a Year Level.', 'fa-exclamation-circle');
                yInput.focus(); return;
            }

            const pValue = pInput.value.trim();
            const mValue = (document.getElementById('group_s_major').style.display !== 'none') ? mInput.value.trim() : '';
            const y      = yInput.value;

            const res      = await fetch(`CreateClass_api.php?action=find_courses&program=${encodeURIComponent(pValue)}&major=${encodeURIComponent(mValue)}&year_level=${encodeURIComponent(y)}&semester=${encodeURIComponent(s)}&department=${encodeURIComponent(getDept())}`);
            const courses = await res.json();

            if (!courses.length) {
                const majorText = mValue ? ` (Major in ${mValue})` : '';
                await sysAlert(`No subjects found for ${pValue}${majorText} - ${y} (${s}).`, 'fa-folder-open');
                return;
            }

            currentCoursesData   = courses;
            currentLoadedSection = '';
            currentLoadedAY      = '';

            document.getElementById('btnDelete').style.display = 'none';
            document.getElementById('b_dept').value = d;
            document.getElementById('b_prog').value = pValue;
            
            // Set Major in Modal Header
            const bMajorGroup = document.getElementById('b_major_group');
            const bMajorInput = document.getElementById('b_major');
            if (mValue) {
                bMajorGroup.style.display = 'flex';
                bMajorInput.value = mValue;
            } else {
                bMajorGroup.style.display = 'none';
                bMajorInput.value = '';
            }

            document.getElementById('b_year').value = y;
            document.getElementById('b_sem').value  = s;
            document.getElementById('b_sec').value  = '';
            document.getElementById('b_ay').value   = '';

            checkUnlockLower();
            renderRows(courses, []);
            document.getElementById('modalBulk').style.display = 'block';
        }

        async function confirmEditSection(d) {
            const msg = `Open and edit the entire schedule for ${d.program} ${d.year_level}, Section ${d.section}?`;
            if (!await sysConfirm(msg, 'fa-edit')) return;

            currentLoadedSection = d.section;
            currentLoadedAY      = d.academic_year;

            document.getElementById('b_dept').value = d.department;
            document.getElementById('b_prog').value = d.program;
            
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

            document.getElementById('b_year').value = d.year_level;
            document.getElementById('b_sem').value  = d.semester;
            document.getElementById('b_sec').value  = d.section;
            document.getElementById('b_ay').value   = d.academic_year;

            checkUnlockLower();

            const res      = await fetch(`CreateClass_api.php?action=find_courses&program=${encodeURIComponent(d.program)}&major=${encodeURIComponent(d.major || '')}&year_level=${encodeURIComponent(d.year_level)}&semester=${encodeURIComponent(d.semester)}&department=${encodeURIComponent(getDept())}`);
            const courses = await res.json();
            currentCoursesData = courses;

            const sectionSchedules = globalAllSchedules.filter(s =>
                s.program       === d.program       &&
                s.year_level    === d.year_level    &&
                s.section       === d.section       &&
                s.academic_year === d.academic_year &&
                s.semester      === d.semester
            );

            renderRows(courses, sectionSchedules);
            document.getElementById('btnDelete').style.display = 'inline-block';
            document.getElementById('modalBulk').style.display = 'block';
        }

        function refreshAllProfessorUnits() {
            const ay         = document.getElementById('b_ay').value.trim();
            const currentSec = document.getElementById('b_sec').value;
            const currentProg = document.getElementById('b_prog').value;
            const profLoads  = {};

            facultyData.forEach(f => {
                profLoads[f.id] = 0;
                globalAllSchedules.forEach(sch => {
                    if (sch.faculty_id == f.id && sch.academic_year === ay && sch.approval_status !== 'Pending') {
                        if (!(sch.section === currentSec && sch.program === currentProg)) {
                            profLoads[f.id] += parseFloat(sch.units || 0);
                        }
                    }
                });
            });

            document.querySelectorAll('.course-row').forEach(row => {
                const rIdx    = row.dataset.idx;
                const selProf = document.getElementById(`prof_${rIdx}`).value;
                if (selProf && selProf !== '0' && selProf !== '-1' && !selProf.startsWith('REQ_') && profLoads[selProf] !== undefined) {
                    profLoads[selProf] += parseFloat(row.dataset.units || 0);
                }
            });

            document.querySelectorAll('.course-row').forEach(row => {
                const idx = row.dataset.idx;
                facultyData.forEach(f => {
                    const opt = document.querySelector(`.prof-opt-${idx}[data-val="${f.id}"]`);
                    if (opt) {
                        const load      = profLoads[f.id];
                        const maxUnits = parseFloat(f.max_units) || 21;
                        const color    = load > 21 ? '#ef4444' : '#10b981';
                        const badge    = opt.querySelector('.unit-badge');
                        if (badge) {
                            badge.innerText    = `(${load} / ${maxUnits} Units)`;
                            badge.style.color  = color;
                            badge.style.fontWeight = 'bold';
                        }
                    }
                });
            });
        }

        let recheckTimeout;
        function queueGlobalRecheck() {
            clearTimeout(recheckTimeout);
            recheckTimeout = setTimeout(() => {
                document.querySelectorAll('.course-row').forEach(row => {
                    const idx      = row.dataset.idx;
                    const checked = Array.from(row.querySelectorAll(`.chk-day-${idx}:checked`));
                    if (checked.length > 0) {
                        const start  = document.getElementById(`start_${idx}`).value;
                        const endDisp = document.getElementById(`disp_end_${idx}`);
                        const end    = document.getElementById(`end_${idx}`).value || (endDisp ? endDisp.dataset.military : null);
                        const rid    = document.getElementById(`room_${idx}`).value;
                        const fid    = document.getElementById(`prof_${idx}`).value;
                        if (start && end && rid && fid) liveConflictCheck(idx, true);
                    } else {
                        const warn = document.getElementById(`warn_${idx}`);
                        if (warn && !warn.innerHTML.includes('Note: Scheduled')) warn.innerHTML = '';
                        row.classList.remove('conflict-row', 'combinable-row');
                        ['day_disp_', 'disp_start_', 'disp_end_', 'disp_room_', 'disp_prof_'].forEach(prefix => {
                            const el = document.getElementById(prefix + idx);
                            if (el) el.classList.remove('input-conflict', 'input-combinable');
                        });
                    }
                });
            }, 300);
        }

        async function handleOptionSelect(el, idx, type, hiddenVal, displayText) {
            if (el.classList.contains('conflicted-opt')) {
                const conflictMsg   = el.dataset.conflictMsg;
                const conflictType = el.dataset.conflictType;
                if (conflictType === 'combine') {
                    const proceed = await sysConfirm(`${conflictMsg}<br><br>Select this room and assign <b>COMBINE</b> classes?`, 'fa-code-branch', '#f59e0b');
                    if (!proceed) return;
                    if (type === 'room' && el.dataset.combineProf) {
                        const pId   = el.dataset.combineProf;
                        const pName = el.dataset.combineProfName;
                        if (pId && pId !== '0') setCustomSelect(idx, 'prof', pId, pName);
                    }
                } else if (conflictType === 'section') {
                    const proceed = await sysConfirm(`${conflictMsg}<br><br>Select this and <b>OVERRIDE</b>?`, 'fa-exclamation-triangle', '#f59e0b');
                    if (!proceed) return;
                } else {
                    await sysAlert(conflictMsg, 'fa-ban', '#ef4444');
                    return;
                }
            }
            setCustomSelect(idx, type, hiddenVal, displayText);
            if (type === 'prof') refreshAllProfessorUnits();
        }

        async function handleTimeSelect(el, idx, hiddenVal, displayText) {
            if (el.classList.contains('conflicted-opt')) {
                const conflictType = el.dataset.conflictType;
                if (conflictType === 'section') {
                    const proceed = await sysConfirm(`${el.dataset.conflictMsg}<br><br>Select this time and <b>OVERRIDE</b>?`, 'fa-exclamation-triangle', '#f59e0b');
                    if (!proceed) return;
                } else {
                    await sysAlert(el.dataset.conflictMsg, 'fa-clock', '#ef4444');
                    return;
                }
            }
            setCustomSelect(idx, 'start', hiddenVal, displayText);
            evalRow(idx);
        }

        async function handleEndTimeSelect(el, idx, hiddenVal, displayText) {
            if (el.classList.contains('conflicted-opt')) {
                const conflictType = el.dataset.conflictType;
                if (conflictType === 'section') {
                    const proceed = await sysConfirm(`${el.dataset.conflictMsg}<br><br>Select this time and <b>OVERRIDE</b>?`, 'fa-exclamation-triangle', '#f59e0b');
                    if (!proceed) return;
                } else {
                    await sysAlert(el.dataset.conflictMsg, 'fa-clock', '#ef4444');
                    return;
                }
            }
            document.getElementById(`disp_end_${idx}`).value            = displayText;
            document.getElementById(`end_${idx}`).value                 = hiddenVal;
            document.getElementById(`disp_end_${idx}`).dataset.military = hiddenVal;
            document.getElementById(`drop_end_${idx}`).style.display    = 'none';
            const tw = document.querySelector('.schedule-table-wrapper');
            if (tw) tw.style.paddingBottom = '20px';
            evalRow(idx);
        }

        async function clearRow(idx) {
            if (!await sysConfirm('Clear the schedule for this subject?', 'fa-eraser', '#ef4444')) return;

            document.querySelectorAll(`.chk-day-${idx}`).forEach(cb => cb.checked = false);

            ['day_disp_', 'disp_start_', 'disp_end_', 'disp_room_', 'disp_prof_'].forEach(prefix => {
                const el = document.getElementById(prefix + idx);
                if (el) { el.value = ''; el.classList.remove('input-conflict', 'input-combinable', 'input-warning'); }
            });

            document.getElementById(`start_${idx}`).value = '';
            const endDisp = document.getElementById(`disp_end_${idx}`);
            if (endDisp) endDisp.dataset.military = '';
            document.getElementById(`end_${idx}`).value   = '';
            document.getElementById(`room_${idx}`).value  = '';
            document.getElementById(`prof_${idx}`).value  = '';

            const warn = document.getElementById(`warn_${idx}`);
            if (warn) warn.innerHTML = '';

            const rowCont = document.getElementById(`row_container_${idx}`);
            rowCont.classList.remove('conflict-row', 'combinable-row');
            rowCont.dataset.sectionConflict = 'false';
            rowCont.dataset.combinable      = 'false';

            evalRow(idx);
            refreshAllProfessorUnits();
        }

        async function handleDayClick(e, idx, dayStr) {
            e.preventDefault();
            const label = e.currentTarget;
            if (label.classList.contains('conflicted-opt')) {
                const conflictType = label.dataset.conflictType;
                if (conflictType === 'section') {
                    const proceed = await sysConfirm(`${label.dataset.conflictMsg}<br><br>Select this day and <b>OVERRIDE</b>?`, 'fa-exclamation-triangle', '#f59e0b');
                    if (!proceed) return;
                } else {
                    await sysAlert(label.dataset.conflictMsg, 'fa-ban', '#ef4444');
                    return;
                }
            }
            const cb    = label.querySelector('input[type="checkbox"]');
            cb.checked  = !cb.checked;
            evalRow(idx);
        }

       function createRowString(c, i, dObj = null, customLec = null, customLab = null, customUnits = null) {
            const lec   = customLec    !== null ? customLec    : parseFloat(c.lecture_hours    || 0);
            const lab   = customLab    !== null ? customLab    : parseFloat(c.laboratory_hours || 0);
            const units = customUnits !== null ? customUnits : parseFloat(c.units            || 0);
            const baseHrs = parseFloat(c.lecture_hours || 0) + parseFloat(c.laboratory_hours || 0);

            const isContinuation = (lec === 0 && lab === 0 && units === 0);
            let isManual = isContinuation;

            if (!isContinuation && dObj && dObj.days && dObj.start_time && dObj.end_time) {
                const daysCount = dObj.days.split(',').filter(d => d.trim() !== '').length;
                if (daysCount > 0) {
                    const sMins     = timeToMins(dObj.start_time.substring(0, 5));
                    const eMins     = timeToMins(dObj.end_time.substring(0, 5));
                    const actualDur = eMins - sMins;
                    const expectedDur = Math.round(((lec + lab) / daysCount) * 60);
                    if (Math.abs(actualDur - expectedDur) > 2) isManual = true;
                }
            }

            const manualClass = isManual ? 'clickable' : '';
            const manualBg    = isManual ? 'white'      : '#f1f5f9';
            const btnBg       = isManual ? '#10b981'    : '#e2e8f0';
            const btnColor    = isManual ? 'white'      : '#475569';
            const btnIcon     = isManual ? 'fa-hand-pointer' : 'fa-lock';

            let schedId    = dObj ? dObj.id : '';
            let startVal   = dObj ? dObj.start_time.substring(0, 5) : '';
            let endVal     = dObj ? convertTime(dObj.end_time) : '';
            let endMil     = dObj ? dObj.end_time.substring(0, 5) : '';
            
            let rVal       = dObj ? dObj.room_id : '';
            let rText      = '';
            if (dObj && dObj.assigned_room_display) {
                rText = dObj.assigned_room_display;
            } else if (rVal && String(rVal).startsWith('REQ_')) {
                const shortReq = String(rVal).replace('REQ_College of ', '');
                rText = '⏳ Req: ' + shortReq;
            } else if (String(rVal) === '0' || !rVal) { 
                rVal = ''; // Wipes the hidden ID to trigger your "Required" validation
                rText = ''; // Wipes the text so the placeholder shows
            } else if (rVal) { 
                const rm = rooms.find(r => r.id == rVal); 
                rText = rm ? rm.room_name : ''; 
            }
            
            // --- UPDATED PROFESSOR MAPPING ---
            let fVal       = dObj ? (dObj.faculty_id !== null ? String(dObj.faculty_id) : '0') : '';
            let fText      = dObj ? dObj.professor : '';
            
            // Failsafe: Force the input text to exactly match the dropdown options
            if (fVal === '-1') {
                fText = '⏳ Req: Gen Ed';
            } else if (fVal && String(fVal).startsWith('REQ_')) {
                const shortReq = String(fVal).replace('REQ_College of ', '');
                fText = '⏳ Req: ' + shortReq;
            } else if (fVal === '0' || !fVal) {
                fText = 'TBA';
            }
            // ---------------------------------
            
            let daysArr    = dObj && dObj.days ? dObj.days.split(',').map(s => s.trim()) : [];

            const hasDay  = daysArr.length > 0;
            const hasTime = startVal !== '' && endMil !== '';

            let listStartHtml = '';
            let startValText  = '';
            for (let h = 7; h <= 20; h++) {
                for (let m = 0; m <= 30; m += 30) {
                    if (h === 20 && m === 30) continue;
                    const timeVal  = `${h.toString().padStart(2, '0')}:${m === 0 ? '00' : '30'}`;
                    const displayH = h > 12 ? h - 12 : h;
                    const text     = `${displayH}:${m === 0 ? '00' : '30'} ${h >= 12 ? 'PM' : 'AM'}`;
                    if (startVal === timeVal) startValText = text;
                    listStartHtml += `<div class="opt-item" data-val="${timeVal}" data-name="${text}" data-search="${text}"
                        onclick="handleTimeSelect(this,${i},'${timeVal}','${text}')">${text}</div>`;
                }
            }

            let listRoomHtml = '';
            
            if (dObj && dObj.assigned_room_display && !rooms.some(r => String(r.id) === String(rVal))) {
                listRoomHtml += `<div class="opt-item room-opt-${i}" data-val="${rVal}" data-name="${dObj.assigned_room_display}" data-search="${dObj.assigned_room_display}"
                    onclick="handleOptionSelect(this,${i},'room','${rVal}','${dObj.assigned_room_display.replace(/'/g, "\\'")}')">${dObj.assigned_room_display} <span style="font-size:10px; opacity:0.8;">(Approved)</span></div>`;
            }

            listRoomHtml += rooms.map(r => {
                const isMaint      = r.status === 'Under Maintenance';
                const extraClass   = isMaint ? 'conflicted-opt' : '';
                const conflictData = isMaint ? `data-conflict-type="room" data-conflict-msg="<b>[ROOM UNAVAILABLE]</b><br>Currently Under Maintenance.<br>👉 <i>Change the Room.</i>"` : '';
                
                let displayHtml = isMaint ? `${r.room_name} <span style="font-size:10px; opacity:0.8;">(Maintenance)</span>` : r.room_name;
                if (r.department === 'University') {
                    displayHtml += ` <span style="font-size:9px; background:#1e40af; color:white; padding:2px 4px; border-radius:4px; margin-left:6px;">Univ</span>`;
                }

                return `<div class="opt-item room-opt-${i} ${extraClass}" data-val="${r.id}" data-name="${r.room_name}" data-search="${r.room_name}" ${conflictData}
                    onclick="handleOptionSelect(this,${i},'room','${r.id}','${r.room_name.replace(/'/g, "\\'")}')">${displayHtml}</div>`;
            }).join('');

            const currentDept    = document.getElementById('b_dept').value;
            const externalDepts  = ['College of Science', 'College of Education', 'College of Industrial Technology', 'College of Engineering'];
            
            externalDepts.forEach(ext => {
                if (ext !== currentDept && currentDept !== '') {
                    const shortExt = ext.replace('College of ', '');
                    const reqText  = `⏳ Req: ${shortExt}`;
                    listRoomHtml += `<div class="opt-item room-opt-${i}" data-val="REQ_${ext}" data-name="${reqText}" data-search="Request Room ${ext} ${shortExt}"
                        onclick="handleOptionSelect(this,${i},'room','REQ_${ext}','${reqText}')">
                        <span style="font-weight:800; color:#d97706;"><i class="fas fa-paper-plane" style="margin-right:5px;"></i> Request: ${shortExt}</span>
                    </div>`;
                }
            });

            let listProfHtml = `
                <div class="opt-item prof-opt-${i}" data-val="0" data-name="TBA" data-search="TBA"
                     onclick="handleOptionSelect(this,${i},'prof','0','TBA')"><span style="font-weight:700;">TBA</span></div>
                <div class="opt-item prof-opt-${i}" data-val="-1" data-name="⏳ Req: Gen Ed" data-search="Request General Education Gen Ed"
                     onclick="handleOptionSelect(this,${i},'prof','-1','⏳ Req: Gen Ed')">
                    <span style="font-weight:800; color:#d97706;"><i class="fas fa-paper-plane" style="margin-right:5px;"></i> Request: Gen Ed</span>
                </div>`;

            externalDepts.forEach(ext => {
                if (ext !== currentDept && currentDept !== '') {
                    const shortExt = ext.replace('College of ', '');
                    const reqText  = `⏳ Req: ${shortExt}`;
                    listProfHtml += `<div class="opt-item prof-opt-${i}" data-val="REQ_${ext}" data-name="${reqText}" data-search="Request ${ext} ${shortExt}"
                        onclick="handleOptionSelect(this,${i},'prof','REQ_${ext}','${reqText}')">
                        <span style="font-weight:800; color:#d97706;"><i class="fas fa-paper-plane" style="margin-right:5px;"></i> Request: ${shortExt}</span>
                    </div>`;
                }
            });

            if (dObj && fVal !== '0' && fVal !== '-1' && !String(fVal).startsWith('REQ_') && !facultyData.some(f => String(f.id) === String(fVal))) {
                listProfHtml += `<div class="opt-item prof-opt-${i}" data-val="${fVal}" data-name="${fText}" data-search="${fText}"
                    onclick="handleOptionSelect(this,${i},'prof','${fVal}','${fText.replace(/'/g, "\\'")}')">
                    <div style="display:flex; align-items:center; overflow:hidden;">
                        <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;" title="${fText}">${fText}</div>
                        <span style="font-size:9px; background:#10b981; color:white; padding:2px 6px; border-radius:4px; margin-left:6px; flex-shrink:0;">Approved</span>
                    </div>
                    <span class="unit-badge" style="font-size:10px; color:var(--text-muted); white-space:nowrap; flex-shrink:0;"></span>
                </div>`;
            }

            listProfHtml += facultyData.map(f => {
                let cleanFName = (f.full_name || '').replace(/null/ig, '').replace(/\s+/g, ' ').trim() || 'Unknown User';
                let deptBadge  = '';
                let shortDept  = '';
                if (f.department && f.department !== currentDept) {
                    shortDept = f.department.replace('College of ', '');
                    deptBadge = `<span style="font-size:9px; background:#e2e8f0; color:#475569; padding:2px 6px; border-radius:4px; margin-left:6px; flex-shrink:0;">${shortDept}</span>`;
                }
                const cleanNameStr = cleanFName.replace(/'/g, "\\'");
                return `<div class="opt-item prof-opt-${i}" data-val="${f.id}" data-name="${cleanNameStr}" data-search="${cleanFName} ${f.department} ${shortDept}"
                    onclick="handleOptionSelect(this,${i},'prof','${f.id}','${cleanNameStr}')">
                    <div style="display:flex; align-items:center; overflow:hidden;">
                        <div style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:120px;" title="${cleanFName}">${cleanFName}</div>
                        ${deptBadge}
                    </div>
                    <span class="unit-badge" style="font-size:10px; color:var(--text-muted); white-space:nowrap; flex-shrink:0;">(0 / 21 Units)</span>
                </div>`;
            }).join('');

            const pendingBadgeHtml = dObj && dObj.approval_status === 'Pending'
                ? `<div style="margin-top:4px; font-size:9px; font-weight:800; color:#d97706; background:#fef3c7; padding:2px 6px; border-radius:4px; display:inline-block;">⏳ PENDING</div>` : '';

            return `
                <tr class="course-row" data-id="${c.id}" data-sched-id="${schedId}" data-idx="${i}"
                    data-units="${units}" id="row_container_${i}" data-lec="${lec}" data-lab="${lab}" data-base-hrs="${baseHrs}">

                    <td style="text-align:center; vertical-align:middle;">
                        <div class="custom-dropdown-wrapper">
                            <input readonly type="text" class="table-input clickable" id="day_disp_${i}"
                                   value="${daysArr.length > 0 ? daysArr.join(', ') : ''}"
                                   onclick="toggleDrop(${i},'day')" placeholder="Select Day">
                            <div id="drop_day_${i}" class="custom-dropdown-list" style="text-align:left;">
                                ${['Mon','Tue','Wed','Thu','Fri','Sat'].map(d => `
                                    <label class="chk-label day-opt-${i}" data-day="${d}" onclick="handleDayClick(event,${i},'${d}')">
                                        <input type="checkbox" value="${d}" class="chk-day-${i}" style="pointer-events:none;" ${daysArr.includes(d) ? 'checked' : ''}> ${d}
                                    </label>`).join('')}
                            </div>
                        </div>
                    </td>

                    <td style="text-align:center; vertical-align:middle;">
                        <div class="custom-dropdown-wrapper" style="margin-bottom:6px;">
                            <input type="text" id="disp_start_${i}" class="table-input clickable"
                                   placeholder="Start Time" value="${startValText}" readonly
                                   onclick="if(!this.disabled) toggleDrop(${i},'start')" ${hasDay ? '' : 'disabled'}>
                            <input type="hidden" id="start_${i}" value="${startVal}">
                            <div id="drop_start_${i}" class="custom-dropdown-list" style="text-align:left;">${listStartHtml}</div>
                        </div>
                        <div style="display:flex; gap:5px; align-items:center;">
                            <div class="custom-dropdown-wrapper" style="flex:1;">
                                <input id="disp_end_${i}" class="table-input ${manualClass}"
                                       style="background-color:${manualBg}; color:var(--text-dark);"
                                       readonly placeholder="End Time (Auto)" value="${endVal}" data-military="${endMil}"
                                       onclick="if(this.classList.contains('clickable')&&!this.disabled) toggleDrop(${i},'end')"
                                       ${hasDay ? '' : 'disabled'}>
                                <input type="hidden" id="end_${i}" value="${endMil}">
                                <div id="drop_end_${i}" class="custom-dropdown-list" style="text-align:left;">
                                    ${listStartHtml.replace(/handleTimeSelect\(/g, 'handleEndTimeSelect(')}
                                </div>
                            </div>
                            <button type="button" onclick="toggleManualTime(${i})" id="btn_manual_time_${i}"
                                    class="btn-clear-row" style="background:${btnBg}; color:${btnColor};"
                                    title="Toggle Manual End Time">
                                <i class="fas ${btnIcon}"></i>
                            </button>
                        </div>
                    </td>

                    <td style="text-align:center; vertical-align:middle;">
                        <strong style="color:var(--primary-blue); font-size:12px;">${c.course_code}</strong>
                    </td>

                    <td style="text-align:left; font-weight:600; font-size:11px; padding-left:8px; line-height:1.2; vertical-align:middle;">
                        ${c.descriptive_title}<br>${pendingBadgeHtml}
                    </td>

                    <td style="text-align:center; vertical-align:middle;">
                        <strong id="unit_disp_${i}">${units}</strong>
                    </td>
                    <td id="lec_disp_${i}" style="border-right:1px dashed var(--border-color); width:4%; text-align:center; vertical-align:middle;">${lec}</td>
                    <td id="lab_disp_${i}" style="width:4%; text-align:center; vertical-align:middle;">${lab}</td>

                    <td style="text-align:center; vertical-align:middle;">
                        <div class="custom-dropdown-wrapper">
                            <input type="text" id="disp_room_${i}" class="table-input clickable"
                                   placeholder="Search Room" value="${rText}" autocomplete="off"
                                   onclick="if(!this.disabled) toggleDrop(${i},'room')"
                                   onkeyup="filterDrop(this,'drop_room_${i}')"
                                   onblur="verifySelection(this,'room_${i}')" ${hasDay && hasTime ? '' : 'disabled'}>
                            <input type="hidden" id="room_${i}" value="${rVal}">
                            <div id="drop_room_${i}" class="custom-dropdown-list" style="text-align:left; min-width:140px;">${listRoomHtml}</div>
                        </div>
                    </td>

                    <td style="text-align:center; vertical-align:middle;">
                        <div class="custom-dropdown-wrapper">
                            <input type="text" id="disp_prof_${i}" class="table-input clickable"
                                   placeholder="Search Prof" value="${fText}" autocomplete="off"
                                   onclick="if(!this.disabled) toggleDrop(${i},'prof')"
                                   onkeyup="filterDrop(this,'drop_prof_${i}')"
                                   onblur="verifySelection(this,'prof_${i}')" ${hasDay && hasTime ? '' : 'disabled'}>
                            <input type="hidden" id="prof_${i}" value="${fVal}">
                            <div id="drop_prof_${i}" class="custom-dropdown-list" style="text-align:left; right:0;">${listProfHtml}</div>
                        </div>
                        <div id="warn_${i}" style="color:#ef4444; font-size:10px; margin-top:6px; font-weight:700; min-height:12px;"></div>
                    </td>

                    <td style="text-align:center; vertical-align:middle;">
                        <div style="display:flex; flex-direction:column; gap:6px; align-items:center; justify-content:center;">
                            <button type="button" class="btn-clear-row" onclick="clearRow(${i})" title="Clear row"><i class="fas fa-eraser"></i></button>
                            <button type="button" class="btn-duplicate-row" onclick="duplicateRow(${i})" title="Add row"><i class="fas fa-plus"></i></button>
                        </div>
                    </td>
                </tr>`;
        }

        function renderRows(coursesArray, existingSchedules = []) {
            const container = document.getElementById('bulkRowsContainer');
            container.innerHTML = '';
            let globalIndex = 0;

            coursesArray.forEach(c => {
                const matching = existingSchedules.filter(s => s.course_id == c.id);
                if (matching.length) {
                    matching.forEach(dObj => {
                        const cLec   = dObj.lecture_hours    !== null && dObj.lecture_hours    !== undefined ? parseFloat(dObj.lecture_hours)    : parseFloat(c.lecture_hours    || 0);
                        const cLab   = dObj.laboratory_hours !== null && dObj.laboratory_hours !== undefined ? parseFloat(dObj.laboratory_hours) : parseFloat(c.laboratory_hours || 0);
                        const cUnits = dObj.units            !== null && dObj.units            !== undefined ? parseFloat(dObj.units)            : parseFloat(c.units            || 0);
                        container.insertAdjacentHTML('beforeend', createRowString(c, globalIndex, dObj, cLec, cLab, cUnits));
                        globalIndex++;
                    });
                } else {
                    container.insertAdjacentHTML('beforeend', createRowString(c, globalIndex, null));
                    globalIndex++;
                }
            });

            for (let i = 0; i < globalIndex; i++) evalRow(i, true);
            refreshAllProfessorUnits();
        }

        function duplicateRow(idx) {
            splitTargetIdx = idx;
            document.getElementById('modalSplit').style.display = 'flex';
        }

        function closeSplitModal() {
            document.getElementById('modalSplit').style.display = 'none';
            splitTargetIdx = null;
        }

        function executeDuplicate(action) {
            if (splitTargetIdx === null) return;
            const idx     = splitTargetIdx;
            const origRow = document.querySelector(`.course-row[data-idx="${idx}"]`);
            const c       = currentCoursesData.find(course => course.id == origRow.dataset.id);

            let newLec   = parseFloat(origRow.dataset.lec  || 0);
            let newLab   = parseFloat(origRow.dataset.lab  || 0);
            let newUnits = parseFloat(origRow.dataset.units || 0);

            if (action === 'continue') { newLec = 0; newLab = 0; newUnits = 0; }

            const newIdx  = Date.now();
            const newHTML = createRowString(c, newIdx, null, newLec, newLab, newUnits);
            origRow.insertAdjacentHTML('afterend', newHTML);

            if (action === 'continue') {
                const dispInput = document.getElementById(`disp_end_${newIdx}`);
                const btn       = document.getElementById(`btn_manual_time_${newIdx}`);
                if (dispInput && btn) {
                    dispInput.classList.add('clickable');
                    dispInput.style.backgroundColor = 'white';
                    btn.style.background = '#10b981';
                    btn.style.color      = 'white';
                    btn.innerHTML        = '<i class="fas fa-hand-pointer"></i>';
                }
            }

            evalRow(newIdx);
            refreshAllProfessorUnits();
            closeSplitModal();
        }

        function toggleManualTime(idx) {
            const startDisp = document.getElementById(`disp_start_${idx}`);
            if (startDisp && startDisp.disabled) return;

            const dispInput = document.getElementById(`disp_end_${idx}`);
            const btn       = document.getElementById(`btn_manual_time_${idx}`);

            if (dispInput.classList.contains('clickable')) {
                dispInput.classList.remove('clickable');
                dispInput.style.backgroundColor = '#f1f5f9';
                btn.style.background = '#e2e8f0'; btn.style.color = '#475569';
                btn.innerHTML        = '<i class="fas fa-lock"></i>';
                evalRow(idx);
            } else {
                dispInput.classList.add('clickable');
                dispInput.style.backgroundColor = 'white';
                btn.style.background = '#10b981'; btn.style.color = 'white';
                btn.innerHTML        = '<i class="fas fa-hand-pointer"></i>';
                if (!dispInput.disabled) toggleDrop(idx, 'end');
            }
        }

        function toggleStandaloneDrop(listId) {
            const el      = document.getElementById(listId);
            const wrapper = el.closest('.custom-dropdown-wrapper');
            const visible = el.style.display === 'block';
            document.querySelectorAll('.custom-dropdown-list').forEach(d => { d.style.display = 'none'; d.classList.remove('drop-up'); });
            if (!visible) { el.style.display = 'block'; adjustDropdownPosition(el, wrapper); }
        }

        function setStandaloneSelect(inputId, val) {
            document.getElementById(inputId).value                       = val;
            document.getElementById(`drop_${inputId}`).style.display    = 'none';
            checkUnlockLower();
        }

        function toggleDrop(idx, type) {
            const el = document.getElementById(`drop_${type}_${idx}`);
            const tw = document.querySelector('.schedule-table-wrapper');
            const visible = el.style.display === 'block';

            document.querySelectorAll('.custom-dropdown-list').forEach(d => d.style.display = 'none');
            if (tw) tw.style.paddingBottom = '20px';

            if (!visible) {
                if (type === 'start') updateTimeDropdownStatus(idx);
                if (type === 'end')   filterEndTimeDropdown(idx);
                if (type === 'day')   updateDayDropdownStatus(idx);
                el.style.display = 'block';
                if (tw) tw.style.paddingBottom = '250px';
            }
        }

        function filterDrop(inputEl, listId) {
            const el      = document.getElementById(listId);
            const wrapper = el.closest('.custom-dropdown-wrapper');
            el.style.display = 'block';
            const filter = inputEl.value.toUpperCase();
            el.querySelectorAll('.opt-item').forEach(item => {
                const txt = item.getAttribute('data-search') || item.getAttribute('data-name') || item.textContent || '';
                item.style.display = txt.toUpperCase().includes(filter) ? '' : 'none';
            });
            adjustDropdownPosition(el, wrapper);
        }

        function setCustomSelect(idx, type, hiddenVal, displayText) {
            document.getElementById(`disp_${type}_${idx}`).value           = displayText;
            document.getElementById(`${type}_${idx}`).value                = hiddenVal;
            document.getElementById(`drop_${type}_${idx}`).style.display   = 'none';
            const tw = document.querySelector('.schedule-table-wrapper');
            if (tw) tw.style.paddingBottom = '20px';
            evalRow(idx);
        }

        function verifySelection(displayInput, hiddenInputId) {
            setTimeout(() => {
                const hiddenInput = document.getElementById(hiddenInputId);
                const list        = document.getElementById(displayInput.id.replace('disp_', 'drop_'));
                let   match       = false;
                const typedVal    = displayInput.value.toLowerCase().trim();
                list.querySelectorAll('.opt-item').forEach(item => {
                    let itemName = item.getAttribute('data-name');
                    if (!itemName) itemName = item.innerText.replace(/ \(\d+.*\)$/, '').replace(/\n\(\d+.*\)$/, '').replace(' (Conflict)', '').replace(' (Maintenance)', '').trim();
                    if (!item.classList.contains('conflicted-opt') && itemName.toLowerCase() === typedVal) {
                        match = true; hiddenInput.value = item.getAttribute('data-val'); displayInput.value = itemName;
                    }
                });
                if (!match) { displayInput.value = ''; hiddenInput.value = ''; }
                const idx = hiddenInputId.split('_')[1];
                evalRow(idx);
                if (hiddenInputId.includes('prof')) refreshAllProfessorUnits();
            }, 200);
        }

        document.addEventListener('click', e => {
            if (!e.target.closest('.custom-dropdown-wrapper')) {
                document.querySelectorAll('.custom-dropdown-list').forEach(el => el.style.display = 'none');
                const tw = document.querySelector('.schedule-table-wrapper');
                if (tw) tw.style.paddingBottom = '20px';
            }
        });

        function evalRow(idx, skipGlobal = false) {
            const row = document.querySelector(`.course-row[data-idx="${idx}"]`);
            if (!row) return;

            let totalHours = parseFloat(row.dataset.lec) + parseFloat(row.dataset.lab);
            if (totalHours === 0) totalHours = parseFloat(row.dataset.baseHrs || 0);

            const checked = Array.from(document.querySelectorAll(`.chk-day-${idx}:checked`)).map(cb => cb.value);
            document.getElementById(`day_disp_${idx}`).value = checked.length > 0 ? checked.join(', ') : '';

            const startDisp  = document.getElementById(`disp_start_${idx}`);
            const startInput = document.getElementById(`start_${idx}`);
            const endDisp    = document.getElementById(`disp_end_${idx}`);
            const endHidden  = document.getElementById(`end_${idx}`);
            const roomDisp   = document.getElementById(`disp_room_${idx}`);
            const roomInput  = document.getElementById(`room_${idx}`);
            const profDisp   = document.getElementById(`disp_prof_${idx}`);
            const profInput  = document.getElementById(`prof_${idx}`);
            const warn       = document.getElementById(`warn_${idx}`);

            if (warn && !warn.innerHTML.includes('Note: Scheduled')) warn.innerHTML = '';
            row.classList.remove('conflict-row', 'combinable-row');
            row.dataset.sectionConflict = 'false';
            row.dataset.combinable      = 'false';

            ['day_disp_', 'disp_start_', 'disp_end_', 'disp_room_', 'disp_prof_'].forEach(prefix => {
                const el = document.getElementById(prefix + idx);
                if (el) el.classList.remove('input-conflict', 'input-combinable');
            });

            if (checked.length === 0) {
                if (startDisp) { startDisp.disabled = true; startDisp.value = ''; }
                if (startInput) startInput.value = '';
                if (endDisp) {
                    endDisp.disabled = true;
                    if (!endDisp.classList.contains('clickable')) { endDisp.value = ''; endDisp.dataset.military = ''; }
                }
                if (endHidden) endHidden.value = '';
            } else {
                if (startDisp) startDisp.disabled = false;
                if (endDisp)   endDisp.disabled   = false;
            }

            const startVal = startInput ? startInput.value : '';

            if (endDisp && endDisp.classList.contains('clickable') && startVal && endHidden.value) {
                if (timeToMins(startVal) >= timeToMins(endHidden.value)) {
                    endDisp.value = ''; endHidden.value = ''; endDisp.dataset.military = '';
                }
            }

            calculateEndTime(idx, totalHours, checked.length);
            const currentEnd = endHidden ? endHidden.value : '';

            if (checked.length === 0 || !startVal || !currentEnd) {
                if (roomDisp) { roomDisp.disabled = true; roomDisp.value = ''; }
                if (roomInput) roomInput.value = '';
                if (profDisp) { profDisp.disabled = true; profDisp.value = ''; }
                if (profInput) profInput.value = '';
            } else {
                if (roomDisp) roomDisp.disabled = false;
                if (profDisp) profDisp.disabled = false;
            }

            updateDropdownsStatus(idx);

            if (checked.length > 0 && (!startVal || !currentEnd || !roomInput.value || !profInput.value)) {
                if (warn) {
                    warn.innerHTML  = `<i class="fas fa-info-circle"></i> Waiting for Time, Room, and Prof...`;
                    warn.style.color = '#94a3b8';
                }
                if (!skipGlobal) queueGlobalRecheck();
                return;
            }

            if (checked.length > 0) liveConflictCheck(idx, skipGlobal);
        }

        function calculateEndTime(idx, totalHours, daysCount) {
            const startVal  = document.getElementById(`start_${idx}`).value;
            const endInput  = document.getElementById(`disp_end_${idx}`);
            const endHidden = document.getElementById(`end_${idx}`);

            if (endInput && endInput.classList.contains('clickable')) return;
            if (!startVal || daysCount === 0 || totalHours === 0) {
                if (endInput)  { endInput.value = ''; endInput.dataset.military = ''; }
                if (endHidden) endHidden.value = '';
                return;
            }

            const hoursPerDay   = totalHours / daysCount;
            const [hh, mm]      = startVal.split(':').map(Number);
            const totalMinutes  = hh * 60 + mm + Math.round(hoursPerDay * 60);
            const endH          = Math.floor(totalMinutes / 60);
            const endM          = totalMinutes % 60;
            const ampm          = endH >= 12 ? 'PM' : 'AM';
            let displayH        = endH > 12 ? endH - 12 : endH;
            if (displayH === 0) displayH = 12;

            const militaryStr   = `${endH.toString().padStart(2, '0')}:${endM.toString().padStart(2, '0')}`;
            endInput.value             = `${displayH}:${endM.toString().padStart(2, '0')} ${ampm}`;
            endInput.dataset.military  = militaryStr;
            endHidden.value            = militaryStr;
        }

        function timeToMins(timeStr) {
            if (!timeStr) return 0;
            const [h, m] = timeStr.split(':').map(Number);
            return h * 60 + m;
        }

        function checkItemConflict(checkType, itemId, daysArr, sMins, eMins, currentIdx) {
            let conflictMsg  = null;
            let conflictType = null;
            let combineProf  = null;
            let combineProfName = null;

            const ay           = document.getElementById('b_ay').value;
            const sec           = document.getElementById('b_sec').value;
            const prog         = document.getElementById('b_prog').value;
            const currentCourseId = document.querySelector(`.course-row[data-idx="${currentIdx}"]`).dataset.id;

            if (checkType === 'room' && (String(itemId) === '0' || String(itemId).startsWith('REQ_') || !itemId)) return null;
            if (checkType === 'prof' && (String(itemId) === '0' || String(itemId) === '-1' || String(itemId).startsWith('REQ_'))) return null;

            if (checkType === 'room') {
                const rMatch = rooms.find(r => r.id == itemId);
                if (rMatch && rMatch.status === 'Under Maintenance') {
                    return { type: 'room', msg: '<b>[ROOM UNAVAILABLE]</b><br>Currently Under Maintenance.<br>👉 <i>Change the Room.</i>' };
                }
            }
            
            for (const otherRow of document.querySelectorAll('.course-row')) {
                const oIdx = otherRow.dataset.idx;
                if (oIdx == currentIdx) continue;

                const oCourseId = otherRow.dataset.id;
                const oRid       = document.getElementById(`room_${oIdx}`).value;
                const oFid       = document.getElementById(`prof_${oIdx}`).value;
                const oDaysArr   = Array.from(document.querySelectorAll(`.chk-day-${oIdx}:checked`)).map(cb => cb.value);
                const oStart     = document.getElementById(`start_${oIdx}`).value;
                const oEndDisp   = document.getElementById(`disp_end_${oIdx}`);
                const oEnd       = document.getElementById(`end_${oIdx}`).value || (oEndDisp ? oEndDisp.dataset.military : null);

                if (!oStart || !oEnd || oDaysArr.length === 0) continue;

                const oSMins = timeToMins(oStart);
                const oEMins = timeToMins(oEnd);

                if (sMins < oEMins && eMins > oSMins) {
                    const overlap = daysArr.filter(d => oDaysArr.includes(d));
                    if (overlap.length > 0) {
                        const sameCourse = oCourseId == currentCourseId;
                        if (checkType === 'room' && oRid == itemId) {
                            if (sameCourse) { return { type: 'combine', msg: '<b>[COMBINE CLASS]</b><br>Room used by same subject above.<br>👉 <i>Select to Combine.</i>', combineProf: oFid, combineProfName: document.getElementById(`disp_prof_${oIdx}`).value }; }
                            else { conflictType = 'room'; conflictMsg = '<b>[ROOM CONFLICT]</b><br>Occupied by another subject in this form.<br>👉 <i>Change Room, Day, or Time.</i>'; break; }
                        }
                        if (checkType === 'prof' && oFid == itemId) {
                            if (sameCourse) { return { type: 'combine', msg: '<b>[COMBINE CLASS]</b><br>Prof teaching same subject above.<br>👉 <i>Select to Combine.</i>' }; }
                            else { conflictType = 'prof'; conflictMsg = '<b>[PROF CONFLICT]</b><br>Already assigned in this form.<br>👉 <i>Change Prof, Day, or Time.</i>'; break; }
                        }
                    }
                }
            }

            if (conflictMsg && conflictType !== 'combine') return { type: conflictType, msg: conflictMsg };

            const activeEditIds = Array.from(document.querySelectorAll('.course-row')).map(r => String(r.dataset.schedId)).filter(id => id && id !== 'undefined');

            for (const dbSch of globalAllSchedules) {
                if (activeEditIds.includes(String(dbSch.id))) continue;
                if (ay && dbSch.academic_year !== ay) continue;

                const dbDays    = dbSch.days.split(',').map(d => d.trim());
                const overlap   = daysArr.filter(d => dbDays.includes(d));
                if (!overlap.length) continue;

                const dbSMins = timeToMins(dbSch.start_time.substring(0, 5));
                const dbEMins = timeToMins(dbSch.end_time.substring(0, 5));

                if (sMins < dbEMins && eMins > dbSMins) {
                    const sameCourse = dbSch.course_id == currentCourseId;
                    const exempted   = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];

                    if (checkType === 'room' && dbSch.room_id == itemId) {
                        const rMatch = rooms.find(r => r.id == dbSch.room_id);
                        const rName  = rMatch ? rMatch.room_name.toUpperCase() : '';
                        if (!exempted.includes(rName)) {
                            if (sameCourse) { return { type: 'combine', msg: `<b>[COMBINE CLASS]</b><br>Matches ${dbSch.program} ${dbSch.section}.<br>👉 <i>Select to Combine.</i>`, combineProf: dbSch.faculty_id, combineProfName: dbSch.professor }; }
                            else { conflictType = 'room'; conflictMsg = `<b>[ROOM CONFLICT]</b><br>Booked for ${dbSch.course_code} (${dbSch.section} - ${dbSch.program}) on ${overlap.join(', ')}.<br>👉 <i>Change Room, Day, or Time.</i>`; break; }
                        }
                    }
                    if (checkType === 'prof' && dbSch.faculty_id == itemId && dbSch.faculty_id != 0 && dbSch.faculty_id != -1) {
                        if (sameCourse) { return { type: 'combine', msg: `<b>[COMBINE CLASS]</b><br>Matches ${dbSch.program} ${dbSch.section}.<br>👉 <i>Select to Combine.</i>` }; }
                        else { conflictType = 'prof'; conflictMsg = `<b>[PROF CONFLICT]</b><br>Teaches ${dbSch.course_code} (${dbSch.section} - ${dbSch.program}) on ${overlap.join(', ')}.<br>👉 <i>Change Prof, Day, or Time.</i>`; break; }
                    }
                }
            }

            if (conflictMsg) return { type: conflictType, msg: conflictMsg };
            return null;
        }

        function updateDropdownsStatus(idx) {
            const startStr = document.getElementById(`start_${idx}`).value;
            const endDisp  = document.getElementById(`disp_end_${idx}`);
            const endStr   = document.getElementById(`end_${idx}`).value || (endDisp ? endDisp.dataset.military : null);
            const daysArr  = Array.from(document.querySelectorAll(`.chk-day-${idx}:checked`)).map(cb => cb.value);

            const roomOpts = document.querySelectorAll(`.room-opt-${idx}`);
            const profOpts = document.querySelectorAll(`.prof-opt-${idx}`);

            if (!startStr || !endStr || daysArr.length === 0) {
                roomOpts.forEach(opt => { if (!opt.dataset.conflictMaint) { opt.classList.remove('conflicted-opt', 'combine-opt'); opt.dataset.conflictMsg = ''; opt.dataset.conflictType = ''; } });
                profOpts.forEach(opt => { opt.classList.remove('conflicted-opt', 'combine-opt'); opt.dataset.conflictMsg = ''; opt.dataset.conflictType = ''; });
                return;
            }

            const sMins = timeToMins(startStr);
            const eMins = timeToMins(endStr);

            roomOpts.forEach(opt => {
                if (opt.dataset.conflictMaint) return;
                const conflict = checkItemConflict('room', opt.dataset.val, daysArr, sMins, eMins, idx);
                if (conflict) {
                    opt.classList.toggle('combine-opt', conflict.type === 'combine');
                    opt.classList.add('conflicted-opt');
                    if (conflict.combineProf)     opt.dataset.combineProf     = conflict.combineProf;
                    if (conflict.combineProfName) opt.dataset.combineProfName = conflict.combineProfName;
                    opt.dataset.conflictMsg  = conflict.msg;
                    opt.dataset.conflictType = conflict.type;
                } else {
                    opt.classList.remove('conflicted-opt', 'combine-opt');
                    opt.dataset.conflictMsg = ''; opt.dataset.conflictType = '';
                    opt.dataset.combineProf = ''; opt.dataset.combineProfName = '';
                }
            });

            profOpts.forEach(opt => {
                const conflict = checkItemConflict('prof', opt.dataset.val, daysArr, sMins, eMins, idx);
                if (conflict) {
                    opt.classList.toggle('combine-opt', conflict.type === 'combine');
                    opt.classList.add('conflicted-opt');
                    opt.dataset.conflictMsg = conflict.msg; opt.dataset.conflictType = conflict.type;
                } else {
                    opt.classList.remove('conflicted-opt', 'combine-opt');
                    opt.dataset.conflictMsg = ''; opt.dataset.conflictType = '';
                }
            });
        }

        function updateTimeDropdownStatus(idx) {
            const startList = document.getElementById(`drop_start_${idx}`);
            if (!startList) return;

            const daysArr = Array.from(document.querySelectorAll(`.chk-day-${idx}:checked`)).map(cb => cb.value);
            if (!daysArr.length) return;

            const rid = document.getElementById(`room_${idx}`).value;
            const fid = document.getElementById(`prof_${idx}`).value;
            const sec = document.getElementById('b_sec').value;
            const row = document.querySelector(`.course-row[data-idx="${idx}"]`);

            let totalHours = parseFloat(row.dataset.lec) + parseFloat(row.dataset.lab);
            if (totalHours === 0) totalHours = parseFloat(row.dataset.baseHrs || 0);
            const hoursPerDay = daysArr.length > 0 ? totalHours / daysArr.length : 0;

            startList.querySelectorAll('.opt-item').forEach(opt => {
                const sMins   = timeToMins(opt.dataset.val);
                const eMins   = sMins + Math.round(hoursPerDay * 60);
                let conflict  = null;
                if (rid && rid !== '0' && !String(rid).startsWith('REQ_')) conflict = checkItemConflict('room', rid, daysArr, sMins, eMins, idx);
                if (!conflict && fid && fid !== '0' && fid !== '-1' && !String(fid).startsWith('REQ_')) {
                    conflict = checkItemConflict('prof', fid, daysArr, sMins, eMins, idx);
                }
                if (!conflict) conflict = checkItemConflict('section', sec, daysArr, sMins, eMins, idx);

                if (conflict) {
                    opt.classList.toggle('combine-opt', conflict.type === 'combine');
                    opt.classList.add('conflicted-opt');
                    opt.dataset.conflictMsg = conflict.msg; opt.dataset.conflictType = conflict.type;
                } else {
                    opt.classList.remove('conflicted-opt', 'combine-opt');
                    opt.dataset.conflictMsg = ''; opt.dataset.conflictType = '';
                }
            });
        }

        function filterEndTimeDropdown(idx) {
            const startVal = document.getElementById(`start_${idx}`).value;
            const endList  = document.getElementById(`drop_end_${idx}`);
            if (!endList) return;
            const startMins = timeToMins(startVal);
            endList.querySelectorAll('.opt-item').forEach(opt => {
                opt.style.display = (startMins && timeToMins(opt.dataset.val) <= startMins) ? 'none' : 'flex';
            });
        }

        function updateDayDropdownStatus(idx) {
            const startStr = document.getElementById(`start_${idx}`).value;
            const endDisp  = document.getElementById(`disp_end_${idx}`);
            const endStr   = document.getElementById(`end_${idx}`).value || (endDisp ? endDisp.dataset.military : null);
            const rid      = document.getElementById(`room_${idx}`).value;
            const fid      = document.getElementById(`prof_${idx}`).value;
            const sec      = document.getElementById('b_sec').value;

            document.querySelectorAll(`.day-opt-${idx}`).forEach(lbl => {
                if (!startStr || !endStr) {
                    lbl.classList.remove('conflicted-opt', 'combine-opt');
                    lbl.dataset.conflictMsg = ''; lbl.dataset.conflictType = '';
                    return;
                }
                const sMins   = timeToMins(startStr);
                const eMins   = timeToMins(endStr);
                const testDay = lbl.dataset.day;
                let conflict  = null;

                if (rid && rid !== '0' && !String(rid).startsWith('REQ_')) conflict = checkItemConflict('room', rid, [testDay], sMins, eMins, idx);
                if (!conflict && fid && fid !== '0' && fid !== '-1' && !String(fid).startsWith('REQ_')) {
                    conflict = checkItemConflict('prof', fid, [testDay], sMins, eMins, idx);
                }
                if (!conflict) conflict = checkItemConflict('section', sec, [testDay], sMins, eMins, idx);

                if (conflict) {
                    lbl.classList.toggle('combine-opt', conflict.type === 'combine');
                    lbl.classList.add('conflicted-opt');
                    lbl.dataset.conflictMsg = conflict.msg; lbl.dataset.conflictType = conflict.type;
                } else {
                    lbl.classList.remove('conflicted-opt', 'combine-opt');
                    lbl.dataset.conflictMsg = ''; lbl.dataset.conflictType = '';
                }
            });
        }

        async function liveConflictCheck(idx, skipQueue = false) {
            const warn         = document.getElementById(`warn_${idx}`);
            const rowContainer = document.getElementById(`row_container_${idx}`);
            const dayDisp      = document.getElementById(`day_disp_${idx}`);
            const startDisp    = document.getElementById(`disp_start_${idx}`);
            const endDisp      = document.getElementById(`disp_end_${idx}`);
            const roomDisp     = document.getElementById(`disp_room_${idx}`);
            const profDisp     = document.getElementById(`disp_prof_${idx}`);

            const rid     = document.getElementById(`room_${idx}`).value;
            const fid     = document.getElementById(`prof_${idx}`).value;
            const daysArr = Array.from(document.querySelectorAll(`.chk-day-${idx}:checked`)).map(cb => cb.value);
            const days    = daysArr.join(',');
            const start   = document.getElementById(`start_${idx}`).value;
            const end     = document.getElementById(`end_${idx}`).value || (endDisp ? endDisp.dataset.military : null);
            const ay      = document.getElementById('b_ay').value;
            const sec     = document.getElementById('b_sec').value;
            const prog    = document.getElementById('b_prog').value;
            const courseId = rowContainer.dataset.id;

            const sMins = timeToMins(start);
            const eMins = timeToMins(end);

            let localConflict = null;
            for (const otherRow of document.querySelectorAll('.course-row')) {
                const oIdx = otherRow.dataset.idx;
                if (oIdx == idx) continue;

                const oCourseId = otherRow.dataset.id;
                const oRid       = document.getElementById(`room_${oIdx}`).value;
                const oFid       = document.getElementById(`prof_${oIdx}`).value;
                const oDaysArr   = Array.from(document.querySelectorAll(`.chk-day-${oIdx}:checked`)).map(cb => cb.value);
                const oStart     = document.getElementById(`start_${oIdx}`).value;
                const oEndDisp   = document.getElementById(`disp_end_${oIdx}`);
                const oEnd       = document.getElementById(`end_${oIdx}`).value || (oEndDisp ? oEndDisp.dataset.military : null);

                if (!oStart || !oEnd || !oDaysArr.length) continue;

                const oSMins = timeToMins(oStart);
                const oEMins = timeToMins(oEnd);

                if (sMins < oEMins && eMins > oSMins) {
                    const overlap = daysArr.filter(d => oDaysArr.includes(d));
                    if (overlap.length > 0) {
                        const isExactMatch = (oCourseId == courseId && oRid == rid && oFid == fid);
                        if (isExactMatch) { localConflict = { type: 'combine', is_combinable: true, msg: '<b>[COMBINE CLASS]</b><br>Will merge with matching schedule above.<br>👉 <i>Requires COMBINE CLASS override.</i>' }; break; }
                        const exempted = ['ONLINE', 'GYM', 'STUDENT ACTIVITY CENTER'];
                        const rMatch   = rooms.find(r => r.id == rid);
                        const rName    = rMatch ? rMatch.room_name.toUpperCase() : '';
                        if (rid && rid !== '0' && !String(rid).startsWith('REQ_') && rid === oRid && !exempted.includes(rName)) { localConflict = { type: 'section', is_combinable: false, msg: '<b>[ROOM/SECTION OVERLAP]</b><br>Room used by another subject in this form.<br>👉 <i>Change Time, or use OVERRIDE on save.</i>' }; break; }
                        if (fid && fid === oFid && fid !== '0' && fid !== '-1' && !String(fid).startsWith('REQ_')) { localConflict = { type: 'section', is_combinable: false, msg: '<b>[PROF/SECTION OVERLAP]</b><br>Professor used by another subject in this form.<br>👉 <i>Change Time, or use OVERRIDE on save.</i>' }; break; }
                        localConflict = { type: 'section', is_combinable: false, msg: '<b>[SECTION CONFLICT]</b><br>Section already scheduled in this form.<br>👉 <i>Change Time, or use OVERRIDE on save.</i>' };
                        break;
                    }
                }
            }

            function applyConflictHighlight(conflict) {
                if (warn) {
                    warn.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${conflict.msg}`;
                    warn.style.color = conflict.is_combinable ? '#d97706' : '#ef4444';
                }
                rowContainer.classList.add(conflict.is_combinable ? 'combinable-row' : 'conflict-row');
                rowContainer.dataset.combinable      = conflict.is_combinable ? 'true' : 'false';
                rowContainer.dataset.sectionConflict = conflict.type === 'section' ? 'true' : 'false';

                const hc = conflict.is_combinable ? 'input-combinable' : 'input-conflict';
                if (conflict.type === 'room') {
                    [roomDisp, dayDisp, startDisp, endDisp].forEach(el => el && el.classList.add(hc));
                } else if (conflict.type === 'prof') {
                    [profDisp, dayDisp, startDisp, endDisp].forEach(el => el && el.classList.add(hc));
                } else if (conflict.type === 'section') {
                    [dayDisp, startDisp, endDisp].forEach(el => el && el.classList.add(hc));
                } else {
                    if (dayDisp) dayDisp.classList.add(hc);
                }
            }

            if (localConflict) {
                applyConflictHighlight(localConflict);
                checkOverscheduling(courseId);
                if (!skipQueue) queueGlobalRecheck();
                return;
            }

            const activeEditIds = Array.from(document.querySelectorAll('.course-row')).map(r => String(r.dataset.schedId)).filter(id => id && id !== 'undefined').join(',');

            try {
                const url  = `CreateClass_api.php?action=check_conflict&room_id=${encodeURIComponent(rid)}&faculty_id=${encodeURIComponent(fid)}&days=${encodeURIComponent(days)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}&ay=${encodeURIComponent(ay)}&section=${encodeURIComponent(sec)}&course_id=${encodeURIComponent(courseId)}&exclude_ids=${encodeURIComponent(activeEditIds)}&program=${encodeURIComponent(prog)}&department=${encodeURIComponent(getDept())}`;
                const data = await (await fetch(url)).json();

                if (data.status === 'conflict') {
                    // FIX FOR THE UNDEFINED ERROR
                    applyConflictHighlight({ 
                        ...data, 
                        msg: data.message || data.msg, 
                        is_combinable: data.is_combinable || false 
                    });
                } else {
                    if (warn) warn.innerHTML = '';
                    rowContainer.classList.remove('conflict-row', 'combinable-row');
                    rowContainer.dataset.sectionConflict = 'false';
                    rowContainer.dataset.combinable      = 'false';
                    [dayDisp, startDisp, endDisp, roomDisp, profDisp].forEach(el => {
                        if (el) el.classList.remove('input-conflict', 'input-combinable');
                    });
                }
            } catch (e) { console.error('Validation sync failed:', e); }

            checkOverscheduling(courseId);
            if (!skipQueue) queueGlobalRecheck();
        }

        function checkOverscheduling(courseId) {
            const rows    = document.querySelectorAll(`.course-row[data-id="${courseId}"]`);
            if (!rows.length) return;

            const baseHrs  = parseFloat(rows[0].dataset.baseHrs || 0);
            const baseMins = baseHrs * 60;
            let   totalMinsScheduled = 0;

            rows.forEach(r => {
                const rIdx    = r.dataset.idx;
                const rStart  = document.getElementById(`start_${rIdx}`).value;
                const rEndD   = document.getElementById(`disp_end_${rIdx}`);
                const rEnd     = document.getElementById(`end_${rIdx}`).value || (rEndD ? rEndD.dataset.military : null);
                const rDays   = Array.from(r.querySelectorAll(`.chk-day-${rIdx}:checked`)).length;
                if (rStart && rEnd && rDays > 0) totalMinsScheduled += (timeToMins(rEnd) - timeToMins(rStart)) * rDays;
            });

            rows.forEach(r => {
                const rIdx = r.dataset.idx;
                const warn = document.getElementById(`warn_${rIdx}`);
                if (!r.classList.contains('conflict-row') && !r.classList.contains('combinable-row')) {
                    if (totalMinsScheduled > baseMins) {
                        const scheduledHrs = (totalMinsScheduled / 60).toFixed(1);
                        if (warn) { warn.innerHTML = `<i class="fas fa-info-circle"></i> Note: Scheduled ${scheduledHrs} hrs (Requires ${baseHrs} hrs)`; warn.style.color = '#eab308'; }
                        ['day_disp_', 'disp_start_', 'disp_end_', 'disp_room_', 'disp_prof_'].forEach(prefix => {
                            const el = document.getElementById(prefix + rIdx);
                            if (el) { el.classList.remove('input-conflict'); el.classList.add('input-warning'); }
                        });
                    } else {
                        if (warn && warn.innerHTML.includes('Note: Scheduled')) warn.innerHTML = '';
                        ['day_disp_', 'disp_start_', 'disp_end_', 'disp_room_', 'disp_prof_'].forEach(prefix => {
                            const el = document.getElementById(prefix + rIdx);
                            if (el) { el.classList.remove('input-conflict', 'input-warning'); }
                        });
                    }
                }
            });
        }

        async function saveBulkClass() {
            const btn      = document.querySelector('.btn-submit-large');
            const origText = btn.innerText;
            const prog     = document.getElementById('b_prog').value;
            const major    = document.getElementById('b_major').value || '';
            const year     = document.getElementById('b_year').value;
            const sec      = document.getElementById('b_sec').value;

            if (!sec) { await sysAlert('Please pick a section before saving.', 'fa-exclamation-circle'); return; }

            const payload = {
                department: getDept(),
                program: prog, 
                major: major, 
                year_level: year,
                semester: document.getElementById('b_sem').value,
                section: sec,
                academic_year: document.getElementById('b_ay').value,
                schedules: [], deletes: [],
                override_combine: false, override_section: false,
            };

            let hasHardWarnings        = false;
            let hasCombinableWarnings = false;
            let hasSectionWarnings   = false;

            document.querySelectorAll('.course-row').forEach(row => {
                const idx      = row.dataset.idx;
                const schedId = row.dataset.schedId || null;
                const rid      = document.getElementById(`room_${idx}`).value;
                const fid      = document.getElementById(`prof_${idx}`).value;
                const start    = document.getElementById(`start_${idx}`).value;
                const endDisp = document.getElementById(`disp_end_${idx}`);
                const end      = document.getElementById(`end_${idx}`).value || (endDisp ? endDisp.dataset.military : null);
                const days     = Array.from(document.querySelectorAll(`.chk-day-${idx}:checked`)).map(cb => cb.value).join(',');

                const customUnits = parseFloat(row.dataset.units || 0);
                const customLec   = parseFloat(row.dataset.lec   || 0);
                const customLab   = parseFloat(row.dataset.lab   || 0);

                const isRowEmpty = (days === '' && !start && !end && rid === '' && fid === '');
                if (isRowEmpty && !schedId) { row.remove(); return; }

                if (row.classList.contains('conflict-row')) {
                    if (row.dataset.sectionConflict === 'true') hasSectionWarnings   = true;
                    else                                        hasHardWarnings      = true;
                } else if (row.classList.contains('combinable-row')) {
                    hasCombinableWarnings = true;
                }

                if (days !== '') {
                    if (!start || !end || rid === '' || fid === '') {
                        hasHardWarnings = true;
                        document.getElementById(`warn_${idx}`).innerHTML = `<i class="fas fa-exclamation-triangle"></i> Time, Room, and Professor are REQUIRED.`;
                        row.classList.add('conflict-row');
                        const startD = document.getElementById(`disp_start_${idx}`);
                        const roomD  = document.getElementById(`disp_room_${idx}`);
                        const profD  = document.getElementById(`disp_prof_${idx}`);
                        if (!start && startD) startD.classList.add('input-conflict');
                        if (!end   && endDisp) endDisp.classList.add('input-conflict');
                        if (rid === '' && roomD) roomD.classList.add('input-conflict');
                        if (fid === '' && profD) profD.classList.add('input-conflict');
                    } else if (timeToMins(start) >= timeToMins(end)) {
                        hasHardWarnings = true;
                        document.getElementById(`warn_${idx}`).innerHTML = `<i class="fas fa-exclamation-triangle"></i> <b>[TIME ERROR]</b> End time must be after Start time.`;
                        row.classList.add('conflict-row');
                        if (endDisp) endDisp.classList.add('input-conflict');
                    } else {
                        payload.schedules.push({ schedule_id: schedId, course_id: row.dataset.id, room_id: rid, faculty_id: fid, start_time: start, end_time: end, days, units: customUnits, lecture_hours: customLec, laboratory_hours: customLab });
                    }
                } else if (days === '' && schedId) {
                    payload.deletes.push(schedId);
                }
            });

            if (hasHardWarnings) {
                await sysAlert('Hard conflicts or missing fields detected (highlighted in red).<br><br>Please fix them before saving.', 'fa-times-circle', '#ef4444');
                return;
            }

            if (hasCombinableWarnings) {
                const ok = await sysPrompt('Identical schedule combination detected!<br><br>To merge schedules, type <b>COMBINE CLASS</b> exactly:', 'COMBINE CLASS', 'fa-code-branch', '#f59e0b');
                if (!ok) { await sysAlert('Save cancelled. Resolve the schedule or use the exact command to override.', 'fa-info-circle', '#3b82f6'); return; }
                payload.override_combine = true;
            }

            if (hasSectionWarnings) {
                const ok = await sysPrompt('<b>[SECTION CONFLICT]</b> Detected!<br>Section already scheduled at this time.<br><br>To override, type <b>OVERRIDE</b> exactly:', 'OVERRIDE', 'fa-exclamation-triangle', '#ef4444');
                if (!ok) { await sysAlert('Save cancelled. Resolve the conflict or use the exact command to override.', 'fa-info-circle', '#3b82f6'); return; }
                payload.override_section = true;
            }

            if (!payload.schedules.length && !payload.deletes.length) {
                await sysAlert('Nothing to save. Fill out at least one row.', 'fa-folder-open');
                return;
            }

            if (!await sysConfirm('<b>Review Information</b><br><br>Please double-check Day, Time, Room, and Professor.<br><br>Save this schedule?', 'fa-question-circle', 'var(--primary-blue)')) return;

            btn.innerText = 'PROCESSING...'; btn.disabled = true;

            try {
                const res    = await fetch('CreateClass_api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                const result = await res.json();
                if (result.status === 'success') {
                    await sysAlert(`Success!<br><br>${result.message}`, 'fa-check-circle', '#10b981');
                    closeM('modalBulk', true);
                    await fetchAllSchedules();
                    await loadSuggs();          
                    await applyLatestContext();
                    await loadCal();
                } else {
                    await sysAlert(`Database Issue:<br><br>${result.message}`, 'fa-exclamation-circle', '#ef4444');
                }
            } catch (e) {
                await sysAlert('Connection failed. Try again.', 'fa-wifi', '#ef4444');
            }

            btn.innerText = origText; btn.disabled = false;
        }

        async function deleteSectionSchedule() {
            const prog = document.getElementById('b_prog').value;
            const sec  = document.getElementById('b_sec').value;
            const sem  = document.getElementById('b_sem').value;
            const ay   = document.getElementById('b_ay').value;

            const msg = `<b>WARNING:</b><br>You are about to completely delete the ENTIRE schedule for <b>${prog} Section ${sec}</b>.<br><br>This cannot be undone.<br><br>Proceed?`;
            if (!await sysConfirm(msg, 'fa-trash-alt', '#ef4444')) return;

            const fd = new FormData();
            fd.append('section', sec); fd.append('academic_year', ay);
            fd.append('program', prog); fd.append('semester', sem);
            fd.append('department', getDept()); 

            const res  = await fetch('CreateClass_api.php?action=delete_section', { method: 'POST', body: fd });
            const json = await res.json();

            if (res.ok) {
                await sysAlert(`Schedule for Section ${sec} deleted successfully.`, 'fa-trash-restore', '#10b981');
                closeM('modalBulk', true);
                await fetchAllSchedules();
                loadCal(); loadSuggs();
            } else {
                await sysAlert(`Could not delete:<br>${json.message}`, 'fa-exclamation-triangle', '#ef4444');
            }
        }

        function convertTime(timeStr) {
            if (!timeStr) return '';
            let [h, m] = timeStr.split(':');
            h = parseInt(h);
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        }

        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });

        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        async function closeM(id, force = false) {
            if (!force) {
                const prog = document.getElementById('b_prog').value || 'this class';
                if (!await sysConfirm(`Close this window?<br><br>Any unsaved schedule for <b>${prog}</b> will be lost.`, 'fa-times-circle', '#ef4444')) return;
            }
            document.getElementById(id).style.display = 'none';
            if (id === 'modalBulk') {
                const isProgramHead = SESSION_POS === 'Program Head';
                if (!isProgramHead) {
                    document.getElementById('s_prog').value = '';
                }
                document.getElementById('s_major').value = '';
                document.getElementById('group_s_major').style.display = 'none';
                
                document.getElementById('s_year').value = '';
                document.getElementById('s_sem').value  = '1st Semester';
                
                // Resetting back to either the dropdown's selected value or the locked value
                if (document.getElementById('s_dept')) {
                    const deptSelect = document.getElementById('s_dept');
                    if (deptSelect.tagName === 'SELECT') {
                        // Keep whatever the admin currently has selected
                    } else {
                        // Reset back to user's native department
                        document.getElementById('s_dept').value = '<?php echo addslashes($userDept); ?>';
                    }
                }
                
                currentLoadedSection = '';
                currentLoadedAY      = '';
            }
        }
    </script>
</body>
</html>