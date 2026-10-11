<?php
session_start();
require_once '../db_connect.php'; // Adjust path depending on where you save this file

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login/Login_index.php"); 
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$msg_type = '';

// --- 1. HANDLE PROFILE PICTURE UPLOAD ---
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['profile_photo']['tmp_name'];
    $imgData = file_get_contents($fileTmpPath);
    
    $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
    $stmt->bind_param("si", $imgData, $user_id);
    
    if ($stmt->execute()) {
        // Update the session so the new image shows up immediately everywhere
        $_SESSION['profile_picture'] = 'data:image/jpeg;base64,' . base64_encode($imgData);
        $message = "Profile picture updated successfully!";
        $msg_type = "success";
    } else {
        $message = "Failed to upload image.";
        $msg_type = "error";
    }
    $stmt->close();
}

// --- 2. HANDLE PERSONAL INFORMATION UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_info'])) {
    $first_name = trim($_POST['first_name']);
    $surname = trim($_POST['surname']);
    $middle_initial = trim($_POST['middle_initial']);
    $sex = $_POST['sex'];
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);

    $stmt = $conn->prepare("UPDATE users SET first_name=?, surname=?, middle_initial=?, sex=?, email=?, username=? WHERE id=?");
    $stmt->bind_param("ssssssi", $first_name, $surname, $middle_initial, $sex, $email, $username, $user_id);
    
    if ($stmt->execute()) {
        // Update session name instantly
        $mi = !empty($middle_initial) ? $middle_initial . '. ' : '';
        $_SESSION['full_name'] = $first_name . ' ' . $mi . $surname;
        
        $message = "Personal information updated!";
        $msg_type = "success";
    } else {
        $message = "Username or Email might already be taken.";
        $msg_type = "error";
    }
    $stmt->close();
}

// --- 3. HANDLE PASSWORD UPDATE ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // First, get the current hash from the database
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user_data = $res->fetch_assoc();
    $stmt->close();

    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_hash, $user_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $message = "Password secured and updated!";
            $msg_type = "success";
        } else {
            $message = "New passwords do not match.";
            $msg_type = "error";
        }
    } else {
        $message = "Current password is incorrect.";
        $msg_type = "error";
    }
}

// --- 4. FETCH CURRENT USER DATA FOR THE FORM ---
$stmt = $conn->prepare("SELECT username, email, first_name, surname, middle_initial, sex, employee_id, department, position, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fallback profile picture
$display_pic = !empty($user['profile_picture']) ? 'data:image/jpeg;base64,' . base64_encode($user['profile_picture']) : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>My Profile - COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* MOBILE-FIRST BASE STYLES */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background-color: #f8fafc; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        .mobile-wrapper { width: 100%; min-height: 100vh; background-color: #f8fafc; display: flex; flex-direction: column; position: relative; }
        @media (min-width: 480px) { body { background-color: #cbd5e1; align-items: center; overflow: hidden; } .mobile-wrapper { max-width: 414px; height: 90vh; min-height: auto; max-height: 896px; border-radius: 40px; border: 10px solid #1a202c; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); } }

        /* HEADER */
        .app-header { background-color: #0b1d3a; padding: 40px 20px 20px; display: flex; justify-content: space-between; align-items: center; color: white; border-radius: 0 0 25px 25px; position: sticky; top: 0; z-index: 10; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .back-btn { color: white; text-decoration: none; font-size: 1.3rem; padding: 10px; border-radius: 50%; background: rgba(255,255,255,0.1); width: 45px; height: 45px; display: flex; justify-content: center; align-items: center; }
        .header-title { font-weight: 700; font-size: 1.2rem; letter-spacing: 1px; }

        .profile-container { padding: 25px 20px; display: flex; flex-direction: column; gap: 20px; }

        /* ALERT MESSAGES */
        .alert { padding: 15px; border-radius: 12px; font-size: 0.95rem; font-weight: 600; text-align: center; display: flex; justify-content: center; align-items: center; gap: 10px; }
        .alert-success { background-color: #f0fff4; color: #2f855a; border: 1px solid #c6f6d5; }
        .alert-error { background-color: #fff5f5; color: #c53030; border: 1px solid #fed7d7; }

        /* PROFILE PICTURE SECTION */
        .profile-hero { display: flex; flex-direction: column; align-items: center; background: white; padding: 30px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .pic-wrapper { position: relative; width: 110px; height: 110px; margin-bottom: 15px; }
        .hero-avatar { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 4px solid #edf2f7; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .fallback-hero { width: 100%; height: 100%; border-radius: 50%; background-color: #4a5568; color: white; display: flex; justify-content: center; align-items: center; font-size: 3rem; }
        .camera-btn { position: absolute; bottom: 0; right: 0; background: #1a4b8c; color: white; width: 36px; height: 36px; border-radius: 50%; display: flex; justify-content: center; align-items: center; cursor: pointer; border: 3px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transition: transform 0.2s; }
        .camera-btn:active { transform: scale(0.9); }
        
        .hero-name { color: #0b1d3a; font-size: 1.4rem; font-weight: 800; text-align: center; }
        .hero-title { color: #1a4b8c; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; margin-top: 5px; }
        .hero-badge { background: #48bb78; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; margin-top: 10px; }

        /* FORMS & CARDS */
        .card { background: white; padding: 25px 20px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); margin-bottom: 10px; }
        .card-title { color: #0b1d3a; font-size: 1.15rem; font-weight: 800; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #edf2f7; }

        .input-group { margin-bottom: 18px; }
        .input-row { display: flex; gap: 15px; }
        .input-row .input-group { flex: 1; }
        
        .input-group label { display: block; color: #4a5568; margin-bottom: 8px; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .input-group input, .input-group select { width: 100%; padding: 14px 16px; border-radius: 12px; border: 2px solid #e2e8f0; background-color: #f8fafc; font-size: 1rem; color: #1a202c; outline: none; transition: border-color 0.2s; font-weight: 500; }
        .input-group input:focus, .input-group select:focus { border-color: #1a4b8c; background-color: #fff; }
        
        /* Read Only styling */
        .input-group input[readonly] { background-color: #edf2f7; color: #718096; cursor: not-allowed; border-color: #edf2f7; }

        /* Passwords */
        .password-container { position: relative; }
        .toggle-password { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: #a0aec0; cursor: pointer; padding: 10px; }

        /* Buttons */
        .btn-submit { width: 100%; padding: 16px; background: #0b1d3a; color: white; border: none; border-radius: 12px; font-size: 1.05rem; font-weight: 700; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(11, 29, 58, 0.2); transition: transform 0.2s; margin-top: 10px; }
        .btn-submit:active { transform: scale(0.97); }

        .hidden { display: none; }
    </style>
</head>
<body>

    <div class="mobile-wrapper">
        <header class="app-header">
            <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i></a>
            <div class="header-title">MY PROFILE</div>
            <div style="width: 45px;"></div> </header>

        <div class="profile-container">

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="profile-hero">
                <div class="pic-wrapper">
                    <?php if ($display_pic): ?>
                        <img src="<?php echo $display_pic; ?>" alt="Profile" class="hero-avatar">
                    <?php else: ?>
                        <div class="fallback-hero"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    
                    <label for="profile_upload" class="camera-btn">
                        <i class="fas fa-camera"></i>
                    </label>
                </div>
                
                <h2 class="hero-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['middle_initial'] . '. ' . $user['surname']); ?></h2>
                <div class="hero-title"><?php echo htmlspecialchars($user['position']); ?></div>
                <div class="hero-badge"><?php echo htmlspecialchars($user['department']); ?></div>

                <form id="upload_form" action="" method="POST" enctype="multipart/form-data" class="hidden">
                    <input type="file" id="profile_upload" name="profile_photo" accept="image/jpeg, image/png" onchange="document.getElementById('upload_form').submit();">
                </form>
            </div>

            <form action="" method="POST" class="card">
                <div class="card-title">Personal Information</div>
                
                <div class="input-row">
                    <div class="input-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    <div class="input-group">
                        <label>Surname</label>
                        <input type="text" name="surname" value="<?php echo htmlspecialchars($user['surname']); ?>" required>
                    </div>
                </div>

                <div class="input-row">
                    <div class="input-group" style="flex: 0.5;">
                        <label>M.I.</label>
                        <input type="text" name="middle_initial" value="<?php echo htmlspecialchars($user['middle_initial']); ?>" maxlength="5">
                    </div>
                    <div class="input-group" style="flex: 1.5;">
                        <label>Sex</label>
                        <select name="sex" required>
                            <option value="Female" <?php echo $user['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Male" <?php echo $user['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Other" <?php echo $user['sex'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="input-group">
                    <label>Username (Login)</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                </div>

                <div class="input-row">
                    <div class="input-group">
                        <label>Employee ID</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['employee_id']); ?>" readonly>
                    </div>
                    <div class="input-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" readonly>
                    </div>
                </div>

                <button type="submit" name="update_info" class="btn-submit">
                    <i class="fas fa-save"></i> Update Information
                </button>
            </form>

            <form action="" method="POST" class="card" style="margin-bottom: 40px;">
                <div class="card-title">Change Password</div>
                
                <div class="input-group">
                    <label>Current Password</label>
                    <div class="password-container">
                        <input type="password" name="current_password" id="cur_pass" placeholder="Enter current password" required>
                        <i class="fas fa-eye toggle-password" onclick="toggleVis('cur_pass', this)"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label>New Password</label>
                    <div class="password-container">
                        <input type="password" name="new_password" id="new_pass" placeholder="New Password" required minlength="8">
                        <i class="fas fa-eye toggle-password" onclick="toggleVis('new_pass', this)"></i>
                    </div>
                </div>

                <div class="input-group">
                    <label>Confirm New Password</label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="con_pass" placeholder="Confirm New Password" required minlength="8">
                        <i class="fas fa-eye toggle-password" onclick="toggleVis('con_pass', this)"></i>
                    </div>
                </div>

                <button type="submit" name="update_password" class="btn-submit" style="background: #1a4b8c;">
                    <i class="fas fa-lock"></i> Update Password
                </button>
            </form>

        </div>
    </div>

    <script>
        function toggleVis(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = "password";
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>