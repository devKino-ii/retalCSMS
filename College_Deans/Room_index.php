<?php
// ============================================================
//  ROOM MANAGEMENT — COSMOS
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
$userName     = $_SESSION['full_name']       ?? '';
$userPosition = $_SESSION['position']        ?? 'Staff';
$userDept     = $_SESSION['department']      ?? 'Department';
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
    'College of Business',
    'University'
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

// Department theme colour
$departmentColors = [
    'College of Science'               => '#28a745',
    'College of Education'             => '#0ea5e9',
    'College of Industrial Technology' => '#800000',
    'College of Engineering'           => '#fd7e14',
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
    <title>Room Management - COSMOS</title>
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
            --theme-color:        <?php echo $themeColor; ?>;
        }

        /* ── Reset ───────────────────────────────────────────── */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: var(--bg-main); color: var(--text-dark); overflow: hidden; }

        /* ════════════════════════════════════════════════════════
             SIDEBAR
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
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted);
            margin: 10px 0 4px; padding: 0 20px; white-space: nowrap; overflow: hidden; opacity: 0; max-height: 0;
            transition: opacity 0.2s ease, max-height 0.2s ease;
        }
        .sidebar:hover .menu-label { opacity: 1; max-height: 30px; }

        .nav-menu { list-style: none; padding: 0; }
        .nav-menu li a {
            display: flex; align-items: center; justify-content: center; padding: 11px 0; margin: 3px 10px;
            color: var(--text-muted); text-decoration: none; font-size: 14px; font-weight: 500;
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

        .profile-section { margin-top: auto; position: relative; padding: 36px 10px 16px; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; align-items: center; }
        .dept-logo-bubble {
            position: absolute; top: -26px; left: 50%; transform: translateX(-50%); width: 50px; height: 50px;
            background: white; border-radius: 50%; border: 2px solid var(--border-color); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12), 0 0 0 3px white;
            display: flex; align-items: center; justify-content: center; overflow: hidden; z-index: 10;
            transition: width 0.3s ease, height 0.3s ease, top 0.3s ease, box-shadow 0.3s ease;
        }
        .dept-logo-bubble::after {
            content: ''; position: absolute; bottom: -8px; left: 50%; transform: translateX(-50%);
            border-left: 7px solid transparent; border-right: 7px solid transparent; border-top: 9px solid white; filter: drop-shadow(0 2px 2px rgba(0, 0, 0, 0.08));
        }
        .sidebar:hover .dept-logo-bubble { width: 56px; height: 56px; top: -30px; box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15), 0 0 0 3px white; }
        .dept-logo-bubble img { width: 82%; height: 82%; object-fit: contain; }
        .dept-icon-fallback   { font-size: 20px; color: var(--primary-blue); }

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
        .main-content { flex: 1; min-width: 0; display: flex; flex-direction: column; overflow: hidden; }
        header { flex-shrink: 0; background: transparent; padding: 30px 40px 10px; display: flex; justify-content: space-between; align-items: center; }
        .header-left { display: flex; align-items: center; gap: 20px; }
        #menu-toggle { display: none; font-size: 24px; color: var(--text-dark); cursor: pointer; transition: color 0.2s; }
        #menu-toggle:hover { color: var(--primary-blue); }
        .header-title h1 { font-size: 28px; font-weight: 800; color: var(--text-dark); letter-spacing: -0.5px; margin-bottom: 4px; }
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
            text-transform: none;
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

        /* Registration form */
        .register-container {
            background: var(--bg-card); padding: 30px; border-radius: var(--radius-card); border: 1px solid var(--border-color);
            box-shadow: var(--shadow-card); margin-bottom: 40px; display: flex; align-items: flex-end; gap: 15px; flex-wrap: wrap;
        }
        .input-group { flex: 1; min-width: 150px; margin-bottom: 10px; }
        .input-group label { display: block; font-size: 12px; font-weight: 600; color: var(--text-muted); margin-bottom: 8px; }
        .pill-input {
            width: 100%; padding: 12px 16px; border: 1px solid var(--border-color); border-radius: var(--radius-pill);
            font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark); outline: none; transition: all 0.2s ease;
        }
        select.pill-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto;
        }
        .pill-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        .pill-input[readonly], .pill-input[disabled] { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; border-color: var(--border-color); background-image: none; }

        /* Buttons */
        .btn-register {
            background: var(--primary-blue); color: white; border: none; padding: 0 30px; border-radius: var(--radius-pill);
            font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; height: 42px; width: 100%;
            box-shadow: 0 4px 10px rgba(27, 80, 136, 0.2); transition: all 0.2s ease;
        }
        .btn-register:hover { background: var(--primary-blue-hover); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.3); }

        .btn-delete {
            background: #ef4444; color: white; border: none; padding: 12px 30px; border-radius: var(--radius-pill);
            font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease;
        }
        .btn-delete:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 6px 15px rgba(239, 68, 68, 0.2); }

        /* Legend */
        .grid-header-area { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .legend-container { display: flex; gap: 15px; background: var(--bg-card); padding: 10px 20px; border-radius: var(--radius-pill); border: 1px solid var(--border-color); box-shadow: var(--shadow-card); }
        .legend-item { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .legend-dot { width: 14px; height: 14px; border-radius: 50%; display: inline-block; border: 2px solid white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .dot-green  { background-color: #10b981; }
        .dot-orange { background-color: #f59e0b; }

        /* Room grid */
        .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 25px; }
        
        .room-card {
            background: var(--bg-card); border: 1px solid var(--border-color); 
            border-top: 5px solid var(--dept-color, var(--theme-color));
            border-radius: 20px; padding: 25px 20px; text-align: center; cursor: pointer; position: relative;
            box-shadow: var(--shadow-card); transition: all 0.2s ease;
        }
        .room-card:hover { transform: translateY(-4px); box-shadow: 0 12px 25px rgba(0, 0, 0, 0.08); border-color: var(--dept-color, var(--theme-color)); }
        
        .room-name-header { font-weight: 800; font-size: 16px; text-transform: uppercase; margin-bottom: 6px; color: var(--text-dark); line-height: 1.2; display: flex; align-items: center; justify-content: center; }
        .room-type-sub    { font-weight: 600; font-size: 12px; margin-bottom: 20px; color: var(--text-muted); }
        .room-loc-label   { font-weight: 700; font-size: 10px; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); }
        .room-loc-detail  { font-weight: 800; font-size: 14px; margin-bottom: 20px; color: var(--dept-color, var(--primary-blue)); }

        .view-schedule-btn {
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; cursor: pointer; 
            color: var(--dept-color, var(--theme-color)); display: inline-flex; align-items: center; gap: 6px; 
            padding: 8px 12px; background: #f8fafc; border-radius: 8px; border: 1px solid var(--dept-color, var(--border-color)); 
            transition: all 0.2s ease;
        }
        .view-schedule-btn:hover { background: var(--dept-color, var(--theme-color)); color: white; }

        .status-indicator { position: absolute; top: 15px; right: 15px; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        .status-indicator.available   { background-color: #10b981; }
        .status-indicator.maintenance { background-color: #f59e0b; }

        .empty-state { text-align: center; padding: 80px 20px; color: var(--text-muted); font-weight: 600; background: var(--bg-card); border-radius: var(--radius-card); border: 1px dashed var(--border-color); grid-column: 1 / -1; }
        .empty-state i     { font-size: 50px; color: #cbd5e1; margin-bottom: 15px; display: block; }
        .empty-state p     { font-size: 18px; color: var(--text-dark); }
        .empty-state small { font-size: 13px; font-weight: normal; margin-top: 8px; display: block; }

        /* ════════════════════════════════════════════════════════
             MODALS
        ════════════════════════════════════════════════════════ */
        .modal { display: none; position: fixed; z-index: 1001; inset: 0; background: rgba(15, 23, 42, 0.6); overflow: auto; padding-top: 60px; backdrop-filter: blur(4px); }
        .modal-content { background: var(--bg-card); margin: 2% auto; padding: 35px 40px; width: 95%; max-width: 600px; border-radius: var(--radius-card); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); position: relative; }
        .close-btn { position: absolute; top: 20px; right: 20px; float: right; font-size: 28px; font-weight: bold; color: var(--text-muted); cursor: pointer; transition: color 0.2s, transform 0.2s; }
        .close-btn:hover { color: #ef4444; transform: scale(1.1); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .custom-input { width: 100%; padding: 12px 16px; border-radius: var(--radius-input); border: 1px solid var(--border-color); outline: none; font-size: 13px; font-weight: 500; background: #f8fafc; color: var(--text-dark); transition: all 0.2s ease; }
        .custom-input:focus { border-color: var(--primary-blue); box-shadow: 0 0 0 3px var(--accent-blue); background: white; }
        select.custom-input {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%2364748b%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.5-12.8z%22%2F%3E%3C%2Fsvg%3E");
            background-repeat: no-repeat; background-position: right 15px top 50%; background-size: 10px auto;
        }

        .sched-filters { display: flex; gap: 10px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: var(--radius-input); border: 1px solid var(--border-color); }
        .sched-filters select { padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color); font-size: 13px; flex: 1; outline: none; }
        .schedule-table-wrapper { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-input); background: white; }
        .sched-table { width: 100%; border-collapse: collapse; font-size: 12px; text-align: left; }
        .sched-table th { background: #f8fafc; color: var(--text-muted); padding: 14px 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--border-color); }
        .sched-table td { padding: 14px 15px; border-bottom: 1px solid var(--border-color); color: var(--text-dark); font-weight: 500; }
        .sched-table tr:last-child td { border-bottom: none; }
        .sched-table tr:hover td      { background: #f8fafc; color: var(--primary-blue); }
        .sched-empty { text-align: center; color: var(--text-muted); padding: 40px !important; font-style: italic; }

        .confirm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; text-align: left; margin-bottom: 25px; background: #f8fafc; padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); }
        .confirm-grid strong { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); margin-bottom: 4px; }
        .confirm-grid span   { color: var(--text-dark); font-weight: 700; font-size: 14px; }

        /* System alert modal */
        .sys-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 3000; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .sys-modal-content { background: var(--bg-card); width: 90%; max-width: 440px; padding: 35px 30px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); text-align: center; }
        .sys-modal-icon { font-size: 45px; margin-bottom: 15px; }
        .sys-modal-msg  { font-size: 14px; color: var(--text-dark); line-height: 1.6; margin-bottom: 25px; }
        .sys-modal-btns { display: flex; gap: 10px; justify-content: center; }
        .sys-btn { flex: 1; padding: 12px 20px; border: none; border-radius: var(--radius-pill); font-size: 13px; font-weight: 700; cursor: pointer; text-transform: uppercase; letter-spacing: 0.5px; transition: all 0.2s ease; }
        .sys-btn:hover { transform: translateY(-2px); }
        .sys-btn-primary { background: var(--primary-blue); color: white; }
        .sys-btn-primary:hover { background: var(--primary-blue-hover); box-shadow: 0 6px 15px rgba(27, 80, 136, 0.2); }
        .sys-btn-danger  { background: #ef4444; color: white; }
        .sys-btn-danger:hover { background: #dc2626; }
        .sys-btn-neutral { background: #e2e8f0; color: #475569; }
        .sys-btn-neutral:hover { background: #cbd5e1; }

        .backdrop { display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.5); z-index: 999; }

        /* ════════════════════════════════════════════════════════
            RESPONSIVE
        ════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .sidebar { position: fixed; height: 100%; width: var(--sidebar-max) !important; transform: translateX(-100%); }
            .sidebar:hover { width: var(--sidebar-max) !important; box-shadow: none; }
            body.mobile-nav-active .sidebar { transform: translateX(0); box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15); }
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
            .main-content  { width: 100%; }
            .grid-header-area { flex-direction: column; align-items: flex-start; gap: 15px; }
            .form-grid     { grid-template-columns: 1fr; }
            .register-container { flex-direction: column; align-items: stretch; }
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
            <li><a href="Course_index.php"><i class="fas fa-book"></i><span>Course</span></a></li>
            <li><a href="Faculty_index.php"><i class="fas fa-chalkboard-teacher"></i><span>Faculty</span></a></li>
            <li><a href="Room_index.php" class="active"><i class="fas fa-door-open"></i><span>Rooms</span></a></li>
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
                <a href="Profile_index.php"
                   title="Go to My Profile"
                   style="display:flex; align-items:center; gap:10px; text-decoration:none; flex:1; min-width:0; transition:opacity 0.2s;"
                   onmouseover="this.style.opacity='0.7'"
                   onmouseout="this.style.opacity='1'">

                    <?php if (!empty($userPic)): ?>
                        <img src="<?php echo $userPic; ?>"
                             class="profile-img"
                             alt="Profile Picture"
                             style="border:2px solid rgba(255,255,255,0.2);">
                    <?php else: ?>
                        <div class="profile-img"
                             style="border:2px solid rgba(255,255,255,0.2);">
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
                    <p>Classroom Organization, Scheduling &amp; Monitoring Operations System</p>
                </div>
            </div>
        </header>

        <div class="container">
            <div class="page-title">
                Room Management
                <span class="dept-badge">
                    <i class="fas fa-building" style="margin-right:6px;"></i>
                    <?php echo htmlspecialchars($userDept); ?>
                </span>
            </div>

            <form id="roomForm" class="register-container">
                <div class="input-group">
                    <label>Room Name</label>
                    <input type="text" name="room_name" class="pill-input" required placeholder="e.g., GMD">
                </div>
                <div class="input-group">
                    <label>Room Type</label>
                    <select name="room_type" class="pill-input" required>
                        <option value="" disabled selected>Select Type</option>
                        <option value="Laboratory Room">Laboratory Room</option>
                        <option value="Lecture Room">Lecture Room</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Location</label>
                    <input type="text" name="location" class="pill-input" required placeholder="e.g., 3rd Floor">
                </div>
                <div class="input-group">
                    <label>Department</label>
                    <input type="text" class="pill-input" name="department"
                           value="<?php echo htmlspecialchars($userDept); ?>" readonly
                           title="Rooms are registered under your own department only.">
                </div>
                <div class="input-group">
                    <label>Status</label>
                    <select name="status" class="pill-input" required>
                        <option value="Available" selected>Available</option>
                        <option value="Under Maintenance">Under Maintenance</option>
                    </select>
                </div>
                <div class="input-group" style="flex:0 0 auto;">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-register">
                        <i class="fas fa-plus-circle" style="margin-right:8px;"></i> Register Room
                    </button>
                </div>
            </form>

            <div class="grid-header-area">
                <h2 style="color:var(--text-dark); text-transform:uppercase; margin:0; font-size:16px; font-weight:800;">
                    Registered Rooms
                </h2>
                <div class="legend-container">
                    <div class="legend-item"><span class="legend-dot dot-green"></span> Available</div>
                    <div class="legend-item"><span class="legend-dot dot-orange"></span> Under Maintenance</div>
                </div>
            </div>

            <div id="roomGrid" class="room-grid">
                <div style="text-align:center; padding: 50px; color: var(--text-muted); grid-column: 1/-1;">
                    <i class="fas fa-circle-notch fa-spin" style="font-size:30px;"></i>
                    <p style="margin-top: 15px;">Loading rooms...</p>
                </div>
            </div>

        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('editModal')">&times;</span>
            <h2 style="margin-bottom:25px; color:var(--text-dark); font-weight:800; font-size:22px;">Edit Room Details</h2>
            <form id="editForm">
                <input type="hidden" name="room_id">
                <div class="form-grid">
                    <div class="input-group">
                        <label>Room Name</label>
                        <input type="text" name="room_name" class="custom-input" required>
                    </div>
                    <div class="input-group">
                        <label>Room Type</label>
                        <select name="room_type" class="custom-input" required>
                            <option value="Laboratory Room">Laboratory Room</option>
                            <option value="Lecture Room">Lecture Room</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Location</label>
                        <input type="text" name="location" class="custom-input" required>
                    </div>
                    <div class="input-group">
                        <label>Department</label>
                        <input type="text" name="department" class="custom-input" readonly>
                    </div>
                    <div class="input-group">
                        <label>Status</label>
                        <select name="status" class="custom-input" required>
                            <option value="Available">Available</option>
                            <option value="Under Maintenance">Under Maintenance</option>
                        </select>
                    </div>
                </div>
                <div style="display:flex; gap:15px; margin-top:30px; justify-content:flex-end;">
                    <button type="button" id="btnDelete" class="btn-delete">
                        <i class="fas fa-trash-alt" style="margin-right:8px;"></i> Delete
                    </button>
                    <button type="submit" class="btn-register" style="width:auto;">Update Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="confirmModal" class="modal" style="z-index:1002;">
        <div class="modal-content" style="max-width:550px;">
            <span class="close-btn" onclick="closeModal('confirmModal')">&times;</span>
            <div style="display:flex; align-items:center; gap:15px; margin-bottom:20px;">
                <i id="confirmIcon" class="fas fa-exclamation-circle" style="font-size:30px; color:var(--primary-blue);"></i>
                <h3 id="confirmModalTitle" style="color:var(--text-dark); font-weight:800; font-size:20px; margin:0;">Confirm Action</h3>
            </div>
            <p style="margin-bottom:20px; font-size:13px; color:var(--text-muted);">
                Please double-check the details below before saving.
            </p>
            <div id="confirmContent" class="confirm-grid"></div>
            <div style="margin-top:30px; display:flex; justify-content:flex-end; gap:15px;">
                <button type="button" class="btn-delete"
                        style="background:#f1f5f9; color:var(--text-dark); width:auto;"
                        onclick="closeModal('confirmModal')">Cancel</button>
                <button type="button" id="btnConfirmRegister" class="btn-register" style="width:auto;">Confirm</button>
            </div>
        </div>
    </div>

    <div id="scheduleModal" class="modal">
        <div class="modal-content" style="max-width:900px;">
            <span class="close-btn" onclick="closeModal('scheduleModal')">&times;</span>
            <h3 style="color:var(--text-dark); border-bottom:2px solid var(--border-color);
                       padding-bottom:15px; margin-bottom:20px; font-weight:800; font-size:20px;">
                Schedule for: <span id="schedRoomName" style="color:var(--primary-blue);"></span>
            </h3>
            <div class="sched-filters">
                <select id="filter_ay"  class="custom-input" onchange="fetchRoomSchedule()"></select>
                <select id="filter_sem" class="custom-input" onchange="fetchRoomSchedule()"></select>
            </div>
            <div class="schedule-table-wrapper">
                <table class="sched-table">
                    <thead>
                        <tr>
                            <th style="width:15%;">Day</th>
                            <th style="width:20%;">Time</th>
                            <th style="width:30%;">Course</th>
                            <th style="width:15%;">Section</th>
                            <th style="width:20%;">Professor</th>
                        </tr>
                    </thead>
                    <tbody id="schedTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="sysAlert" class="sys-modal-backdrop">
        <div class="sys-modal-content">
            <i id="sysAlertIcon" class="fas fa-info-circle sys-modal-icon" style="color:var(--primary-blue);"></i>
            <div id="sysAlertMsg" class="sys-modal-msg"></div>
            <button id="sysAlertOkBtn" class="sys-btn sys-btn-primary" style="width:100%;">OK</button>
        </div>
    </div>

    <script>
        // ── PHP → JS ──────────────────────────────────────────
        const SESSION_DEPT = <?php echo json_encode($userDept); ?>;
        const IS_ADMIN = <?php echo json_encode($isAdmin); ?>;

        // ── State ─────────────────────────────────────────────
        let allRooms           = [];
        let currentSchedRoomId = null;
        let formDataToSubmit   = null;
        let pendingAction      = null;   // 'CREATE' | 'UPDATE' | 'DELETE'
        let isPolling          = false;

        const deptColors = {
            'College of Science': '#28a745',
            'College of Education': '#0ea5e9',
            'College of Industrial Technology': '#800000',
            'College of Engineering': '#fd7e14',
            'University': '#1e40af' 
        };

        // ────────────────────────────────────────────────────
        // Custom alert 
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

        // ────────────────────────────────────────────────────
        // Collaborative Fetching and Rendering
        // ────────────────────────────────────────────────────
        async function fetchRooms() {
            if (isPolling) return;
            isPolling = true;

            try {
                const res = await fetch(`Room_api.php?action=get_rooms`);
                const data = await res.json();

                if (JSON.stringify(allRooms) !== JSON.stringify(data)) {
                    allRooms = data;
                    renderRooms(allRooms);
                }
            } catch (err) {
                console.error("Polling error:", err);
            } finally {
                isPolling = false;
            }
        }

        function renderRooms(data) {
            const grid = document.getElementById('roomGrid');
            
            if (!data || data.length === 0) {
                grid.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-door-closed"></i>
                        <p>No Rooms Found</p>
                        <small>Use the form above to register rooms.</small>
                    </div>`;
                return;
            }

            const grouped = {};
            data.forEach(r => {
                const d = r.department || 'University';
                if (!grouped[d]) grouped[d] = [];
                grouped[d].push(r);
            });

            let html = '';
            
            for (const [dept, rooms] of Object.entries(grouped)) {
                const color = deptColors[dept] || 'var(--primary-blue)';
                const icon = dept === 'University' ? 'fa-globe' : 'fa-building';
                
                html += `
                <div style="grid-column: 1/-1; margin-top: 20px; margin-bottom: 5px; border-bottom: 2px solid ${color}; padding-bottom: 8px;">
                    <h3 style="color: ${color}; font-weight: 800; text-transform: uppercase; font-size: 16px;">
                        <i class="fas ${icon}" style="margin-right: 8px;"></i>${dept}
                    </h3>
                </div>
                `;

                rooms.forEach(room => {
                    const statusClass = room.status === 'Available' ? 'available' : 'maintenance';
                    const jsonInfo = JSON.stringify(room).replace(/'/g, "&apos;").replace(/"/g, "&quot;");
                    
                    // Show lock icon on University rooms for non-admins
                    const isLocked = (!IS_ADMIN && room.department === 'University');
                    const lockIcon = isLocked ? '<i class="fas fa-lock" style="color:var(--text-muted); font-size:12px; margin-left:6px;" title="View Only"></i>' : '';
                    
                    html += `
                    <div class="room-card" data-info="${jsonInfo}" style="--dept-color: ${color};">
                        <div class="status-indicator ${statusClass}" title="${room.status}"></div>

                        <div class="room-name-header">${room.room_name} ${lockIcon}</div>
                        <div class="room-type-sub">${room.room_type}</div>

                        <div class="room-loc-label">Location:</div>
                        <div class="room-loc-detail">${room.location}</div>

                        <div class="view-schedule-btn" onclick="openSchedule(event, ${room.id}, '${room.room_name.replace(/'/g, "\\'")}')">
                            <i class="fas fa-calendar-alt"></i> View Schedule
                        </div>
                    </div>`;
                });
            }
            grid.innerHTML = html;
        }

        // ────────────────────────────────────────────────────
        // Grid click — open edit modal
        // ────────────────────────────────────────────────────
        document.getElementById('roomGrid').addEventListener('click', e => {
            if (e.target.closest('.view-schedule-btn')) return;
            const card = e.target.closest('.room-card');
            if (card) {
                const info = JSON.parse(card.getAttribute('data-info').replace(/&apos;/g, "'").replace(/&quot;/g, '"'));
                
                // Block non-admins from editing University rooms
                if (!IS_ADMIN && info.department === 'University') {
                    sysAlert('University rooms are managed by Administrators. You can only view their schedules.', 'fa-lock', '#f59e0b');
                    return;
                }
                
                openEditModal(info);
            }
        });

        // ────────────────────────────────────────────────────
        // Confirm / review modal
        // ────────────────────────────────────────────────────
        function showConfirmModal(action, formData) {
            pendingAction    = action;
            formDataToSubmit = formData;

            const cfg = {
                CREATE: { title: 'Confirm Registration', btn: 'Confirm & Register', color: 'var(--primary-blue)', iconClass: 'fas fa-info-circle',          iconColor: 'var(--primary-blue)' },
                UPDATE: { title: 'Confirm Changes',      btn: 'Save Changes',       color: 'var(--primary-blue)', iconClass: 'fas fa-edit',                 iconColor: 'var(--primary-blue)' },
                DELETE: { title: 'Confirm Deletion',     btn: 'Delete Room',        color: '#ef4444',             iconClass: 'fas fa-exclamation-triangle',  iconColor: '#ef4444'             },
            };
            const c = cfg[action];

            document.getElementById('confirmModalTitle').innerText  = c.title;
            document.getElementById('confirmIcon').className         = c.iconClass;
            document.getElementById('confirmIcon').style.color       = c.iconColor;

            const btn = document.getElementById('btnConfirmRegister');
            btn.innerText              = c.btn;
            btn.style.backgroundColor  = c.color;

            const g = k => formData.get(k) || '-';

            document.getElementById('confirmContent').innerHTML = `
                <div><strong>Room Name</strong><span>${g('room_name')}</span></div>
                <div><strong>Room Type</strong><span>${g('room_type')}</span></div>
                <div><strong>Location</strong><span>${g('location')}</span></div>
                <div><strong>Department</strong><span>${g('department')}</span></div>
                <div><strong>Status</strong><span>${g('status')}</span></div>
                ${action === 'DELETE'
                    ? '<div style="color:#ef4444; margin-top:15px; grid-column:1/-1; font-weight:700; text-align:center; border-top:1px dashed var(--border-color); padding-top:15px;">⚠ This action cannot be undone.</div>'
                    : ''}`;

            document.getElementById('confirmModal').style.display = 'block';
        }

        // ────────────────────────────────────────────────────
        // Form events → show review modal
        // ────────────────────────────────────────────────────
        document.getElementById('roomForm').addEventListener('submit', e => {
            e.preventDefault();
            showConfirmModal('CREATE', new FormData(e.target));
        });

        document.getElementById('editForm').addEventListener('submit', e => {
            e.preventDefault();
            showConfirmModal('UPDATE', new FormData(e.target));
        });

        document.getElementById('btnDelete').addEventListener('click', () => {
            const form = document.getElementById('editForm');
            const fd   = new FormData(form);
            fd.append('delete_id', form.querySelector('[name="room_id"]').value);
            showConfirmModal('DELETE', fd);
        });

        // ────────────────────────────────────────────────────
        // Execute confirmed action
        // ────────────────────────────────────────────────────
        document.getElementById('btnConfirmRegister').addEventListener('click', async function () {
            if (!formDataToSubmit || !pendingAction) return;

            this.disabled  = true;
            this.innerText = 'Processing...';

            try {
                // Ensure department is included
                if(!formDataToSubmit.has('department')) formDataToSubmit.append('department', SESSION_DEPT);

                const res    = await fetch('Room_api.php', { method: 'POST', body: formDataToSubmit });
                if (!res.ok) throw new Error(`System Error (${res.status})`);
                const result = await res.json();

                if (result.status === 'success') {
                    closeModal('confirmModal');
                    closeModal('editModal');
                    
                    if (pendingAction === 'CREATE') {
                        document.getElementById('roomForm').reset();
                        document.querySelector('#roomForm [name="department"]').value = SESSION_DEPT;
                    }
                    
                    await sysAlert(result.message, 'fa-check-circle', '#10b981');
                    fetchRooms(); // Immediately re-fetch without reloading
                } else {
                    await sysAlert(result.message, 'fa-exclamation-circle', '#ef4444');
                }

            } catch (e) {
                console.error(e);
                await sysAlert('Connection Error:<br>We cannot reach the server. Please try again.', 'fa-wifi', '#ef4444');
            }

            this.disabled  = false;
            this.innerText = 'Confirm';
        });

        // ────────────────────────────────────────────────────
        // Edit modal
        // ────────────────────────────────────────────────────
        function openEditModal(room) {
            const form = document.getElementById('editForm');
            form.querySelector('[name="room_id"]').value    = room.id;
            form.querySelector('[name="room_name"]').value  = room.room_name;
            form.querySelector('[name="room_type"]').value  = room.room_type;
            form.querySelector('[name="location"]').value   = room.location;
            form.querySelector('[name="department"]').value = room.department;
            form.querySelector('[name="status"]').value     = room.status;
            document.getElementById('editModal').style.display = 'block';
        }

        // ────────────────────────────────────────────────────
        // Schedule modal
        // ────────────────────────────────────────────────────
        async function openSchedule(e, roomId, roomName) {
            e.stopPropagation();
            currentSchedRoomId = roomId;
            document.getElementById('schedRoomName').innerText    = roomName;
            document.getElementById('scheduleModal').style.display = 'block';
            await loadScheduleFilters();
            fetchRoomSchedule();
        }

        async function loadScheduleFilters() {
            const aySelect  = document.getElementById('filter_ay');
            const semSelect = document.getElementById('filter_sem');
            if (aySelect.options.length > 0) return;
            try {
                const data = await (await fetch(`Room_api.php?action=get_schedule_filters`)).json();
                aySelect.innerHTML  = data.ays.map(ay => `<option value="${ay}">${ay}</option>`).join('');
                semSelect.innerHTML = data.sems.map(s  => `<option value="${s}">${s}</option>`).join('');
            } catch (e) { console.error(e); }
        }

        async function fetchRoomSchedule() {
            if (!currentSchedRoomId) return;
            const tbody = document.getElementById('schedTableBody');
            const ay    = document.getElementById('filter_ay').value;
            const sem   = document.getElementById('filter_sem').value;

            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:40px; color:var(--text-muted);">
                <i class="fas fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; display:block;"></i> Loading...
            </td></tr>`;

            try {
                const url  = `Room_api.php?action=get_schedule&room_id=${currentSchedRoomId}&ay=${encodeURIComponent(ay)}&sem=${encodeURIComponent(sem)}`;
                const data = await (await fetch(url)).json();

                if (!data.length) {
                    tbody.innerHTML = '<tr><td colspan="5" class="sched-empty">No classes scheduled for this period.</td></tr>';
                    return;
                }

                const fmt = t => {
                    const [h, m] = t.split(':');
                    const ampm   = h >= 12 ? 'PM' : 'AM';
                    return `${h % 12 || 12}:${m} ${ampm}`;
                };

                tbody.innerHTML = data.map(row => `
                    <tr>
                        <td style="font-weight:700;">${row.days}</td>
                        <td style="white-space:nowrap;">${fmt(row.start_time)} – ${fmt(row.end_time)}</td>
                        <td>
                            <strong style="color:var(--primary-blue);">${row.course_code}</strong><br>
                            <span style="font-size:11px; color:var(--text-muted); font-weight:600;">${row.descriptive_title}</span>
                        </td>
                        <td style="font-weight:600;">${row.section}</td>
                        <td style="font-weight:600;">${row.professor || 'TBA'}</td>
                    </tr>`).join('');

            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="5" style="color:#ef4444; text-align:center; padding:40px;">Could not load schedule.</td></tr>';
            }
        }

        // ────────────────────────────────────────────────────
        // Utilities
        // ────────────────────────────────────────────────────
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        window.addEventListener('click', e => {
            if (e.target.classList.contains('modal')) e.target.style.display = 'none';
        });

        document.getElementById('menu-toggle').addEventListener('click', () => {
            document.body.classList.toggle('mobile-nav-active');
        });

        document.querySelector('.backdrop').addEventListener('click', () => {
            document.body.classList.remove('mobile-nav-active');
        });

        // ────────────────────────────────────────────────────
        // Initialize App
        // ────────────────────────────────────────────────────
        window.onload = () => {
            fetchRooms(); // Initial Fetch
            setInterval(fetchRooms, 5000); // Poll every 5 seconds
        };
    </script>

</body>
</html>