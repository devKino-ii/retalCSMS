<?php
// 1. STRICT CACHE CONTROL - Prevents the browser from saving this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT"); 

session_start();

// 2. THE RUTHLESS LOGOUT CATCHER
if (isset($_GET['logout'])) {
    $_SESSION = array(); 
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy(); 
    header("Location: Login_index.php"); 
    exit();
}

// 3. PREVENT DOUBLE LOGIN - If valid session exists, bounce them to app
if (isset($_SESSION['user_id'])) {
    header("Location: room_monitoring.php");
    exit();
}

// Assumes db_connect.php is one folder up
require '../db_connect.php'; 

$error = '';
$submitted_username = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- FACULTY / ADMIN LOGIN ---
    if (isset($_POST['login_type']) && $_POST['login_type'] === 'faculty') {
        $username = trim($_POST['username']);
        $submitted_username = $username; 
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, password, first_name, surname, middle_initial, profile_picture, department, position FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                
                $_SESSION['user_id'] = $row['id'];
                $mi = !empty($row['middle_initial']) ? trim($row['middle_initial']) . '. ' : '';
                $_SESSION['full_name'] = trim($row['first_name']) . ' ' . $mi . trim($row['surname']);
                $_SESSION['department'] = $row['department'];
                $_SESSION['position'] = $row['position'];

                if (!empty($row['profile_picture'])) {
                    $_SESSION['profile_picture'] = 'data:image/jpeg;base64,' . base64_encode($row['profile_picture']);
                } else {
                    $_SESSION['profile_picture'] = null;
                }

                session_write_close();
                header("Location: room_monitoring.php");
                exit();

            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with that username.";
        }
        $stmt->close();
    }
    
    // --- STUDENT ACCESS (GUEST) LOGIN ---
    elseif (isset($_POST['login_type']) && $_POST['login_type'] === 'student') {
        $_SESSION['user_id'] = rand(1000000, 9999999); 
        $_SESSION['full_name'] = trim($_POST['student_name']);
        $_SESSION['department'] = trim($_POST['student_college']);
        $_SESSION['program'] = trim($_POST['student_program']);
        $_SESSION['section'] = trim($_POST['student_section']);
        $_SESSION['position'] = 'Student'; // Identifies them as a guest
        $_SESSION['profile_picture'] = null;

        session_write_close();
        header("Location: room_monitoring.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>COSMOS Login - URS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        // JS Layer: Block desktop user agents immediately
        const isDesktopAgent = !/Mobi|Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        if (isDesktopAgent) {
            document.documentElement.classList.add('js-desktop-blocked');
        }

        function preventBack() { window.history.forward(); }
        setTimeout("preventBack()", 0);
        window.onunload = function () { null };
    </script>

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-tap-highlight-color: transparent; }
        body { background-color: #cbd5e1; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        .mobile-wrapper { width: 100%; min-height: 100vh; background-color: #050914; background-image: radial-gradient(2px 2px at 15% 25%, #fff, transparent), radial-gradient(1px 1px at 35% 50%, #fff, transparent), radial-gradient(ellipse at bottom left, #231041 0%, transparent 50%), radial-gradient(ellipse at top right, #0b1d3a 0%, #050914 100%); color: #fff; display: flex; flex-direction: column; position: relative; }
        
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .mobile-header { display: flex; flex-direction: column; align-items: center; padding: 40px 20px 20px; animation: fadeIn 1s ease-out; text-align: center; }
        .logo-img { width: 80px; height: auto; margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.2)); }
        .mobile-header h1 { font-size: 1.3rem; font-weight: 700; line-height: 1.2; letter-spacing: 1px; }
        .mobile-header p { font-size: 0.9rem; color: #8fa0c0; margin-top: 5px; }
        
        .form-container { background-color: #ffffff; border-radius: 30px 30px 0 0; padding: 35px 30px; flex-grow: 1; display: flex; flex-direction: column; animation: slideUp 0.6s ease-out both; box-shadow: 0 -5px 25px rgba(0,0,0,0.5); color: #0b1d3a;}
        .form-container h2 { color: #0b1d3a; font-size: 1.8rem; margin-bottom: 25px; font-weight: 800; text-align: center; }
        
        .error-msg { background: #fff5f5; color: #e53e3e; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 0.9rem; border-left: 4px solid #e53e3e; }
        .input-group { margin-bottom: 18px; text-align: left; }
        .input-group label { display: block; color: #4a5568; margin-bottom: 6px; font-size: 0.9rem; font-weight: 600; }
        .input-group input, .input-group select { width: 100%; padding: 14px 18px; border-radius: 12px; border: 2px solid #edf2f7; background-color: #f8fafc; font-size: 0.95rem; outline: none; transition: all 0.3s; color: #0b1d3a; }
        .input-group input:focus, .input-group select:focus { border-color: #0b1d3a; background-color: #fff; }
        .password-container { position: relative; width: 100%; }
        .password-container input { padding-right: 50px; }
        .toggle-password { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #718096; font-size: 1.1rem; padding: 10px; }
        .forgot-link { display: block; text-align: right; margin-top: -10px; margin-bottom: 25px; color: #64748b; font-size: 0.85rem; font-weight: 600; text-decoration: none; }
        
        .login-btn { width: 100%; padding: 16px; background-color: #0b1d3a; color: #fff; border: none; border-radius: 12px; font-size: 1.05rem; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(11, 29, 58, 0.3); transition: background-color 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px;}
        .login-btn:hover { background-color: #1a3668; }
        .login-btn:active { transform: scale(0.98); }
        
        .guest-btn { width: 100%; padding: 14px; background-color: transparent; color: #64748b; border: 2px dashed #cbd5e1; border-radius: 12px; font-size: 0.95rem; font-weight: 600; cursor: pointer; transition: all 0.2s; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .guest-btn:hover { background-color: #f8fafc; color: #0b1d3a; border-color: #94a3b8; }
        .guest-btn:active { transform: scale(0.98); }

        .mobile-footer { margin-top: auto; padding-top: 30px; text-align: center; font-size: 0.8rem; color: #a0aec0; }
        .mobile-footer a { color: #0b1d3a; font-weight: 600; text-decoration: none; }

        /* MODAL STYLES */
        .modal-overlay { position: fixed; inset: 0; background: rgba(5, 9, 20, 0.8); backdrop-filter: blur(4px); z-index: 9999; display: none; justify-content: center; align-items: center; padding: 20px; animation: fadeIn 0.2s ease-out forwards; }
        .modal-container { background: white; width: 100%; max-width: 400px; border-radius: 24px; padding: 30px 25px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); transform: translateY(20px); animation: slideUp 0.3s ease-out forwards; position: relative; color: #0b1d3a; }
        .modal-close { position: absolute; top: 20px; right: 20px; font-size: 1.5rem; color: #a0aec0; cursor: pointer; transition: color 0.2s; background: none; border: none; padding: 5px; }
        .modal-close:hover { color: #e53e3e; }

        /* ========================================= */
        /* LAPTOP / PC BLOCKER STYLES                */
        /* ========================================= */
        #desktop-blocker { display: none; }

        /* Trigger if JS detects a desktop User-Agent OR if device uses a mouse (pointer: fine) */
        @media (hover: hover) and (pointer: fine), (min-width: 1024px) {
            .mobile-wrapper, .modal-overlay { display: none !important; }
            body { background-color: #050914; align-items: center; }
            #desktop-blocker { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; width: 100%; height: 100vh; padding: 30px; animation: fadeIn 0.5s ease-out; }
            #desktop-blocker i { font-size: 4.5rem; color: #e53e3e; margin-bottom: 25px; }
            #desktop-blocker h1 { font-size: 2.2rem; font-weight: 800; color: #fff; margin-bottom: 15px; }
            #desktop-blocker p { font-size: 1.1rem; color: #8fa0c0; line-height: 1.5; max-width: 450px; }
        }

        /* Fallback trigger for the JS variable setup above */
        html.js-desktop-blocked .mobile-wrapper, 
        html.js-desktop-blocked .modal-overlay { display: none !important; }
        html.js-desktop-blocked body { background-color: #050914; align-items: center; }
        html.js-desktop-blocked #desktop-blocker { display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; width: 100%; height: 100vh; padding: 30px; }
    </style>
</head>
<body>
    
    <div id="desktop-blocker">
        <i class="fas fa-mobile-alt"></i>
        <h1>Mobile Access Only</h1>
        <p>The COSMOS Portal is strictly restricted to mobile devices. Please access this page using your smartphone or tablet.</p>
    </div>

    <div class="mobile-wrapper">
        <header class="mobile-header">
            <img src="../LOGO_URS.png" alt="URS Logo" class="logo-img" onerror="this.style.display='none'">
            <h1>UNIVERSITY OF RIZAL SYSTEM</h1>
            <p>COSMOS Portal</p>
        </header>

        <div class="form-container">
            <h2>Welcome Back</h2>
            
            <?php if($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="facultyForm">
                <input type="hidden" name="login_type" value="faculty">
                
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" name="username" id="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($submitted_username); ?>" required autocomplete="username">
                </div>
                
                <div class="input-group" style="margin-bottom: 15px;">
                    <label for="password">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" placeholder="Enter your password" required autocomplete="current-password">
                        <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                    </div>
                </div>

                <a href="Forgot_Password_index.php" class="forgot-link">Forgot Password?</a>
                <button type="submit" class="login-btn"><i class="fas fa-lock"></i> Secure Login</button>
                
                <button type="button" class="guest-btn" onclick="openStudentModal()">
                    <i class="fas fa-user-graduate"></i> Continue as Student Guest
                </button>
            </form>

            <div class="mobile-footer">
                <p style="margin-top: 10px; font-size: 0.7rem;">© 2026 URS | Devs: Lin, Biñas, Lualhati, Olea, Raquedan</p>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="studentModal">
        <div class="modal-container">
            <button class="modal-close" onclick="closeStudentModal()"><i class="fas fa-times"></i></button>
            
            <div style="text-align: center; margin-bottom: 20px;">
                <i class="fas fa-user-graduate" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 10px;"></i>
                <h2 style="font-size: 1.5rem; font-weight: 800; margin-bottom: 5px;">Student Access</h2>
                <p style="font-size: 0.85rem; color: #64748b;">Fill out this form to occupy rooms for activities or to monitor live statuses.</p>
            </div>

            <form method="POST" action="" id="studentForm">
                <input type="hidden" name="login_type" value="student">
                
                <div class="input-group">
                    <label>Full Name</label>
                    <input type="text" name="student_name" placeholder="e.g. Juan Dela Cruz" required>
                </div>
                
                <div class="input-group">
                    <label>College / Department</label>
                    <select name="student_college" required>
                        <option value="" disabled selected>Select your College</option>
                        <option value="College of Science">College of Science</option>
                        <option value="College of Education">College of Education</option>
                        <option value="College of Engineering">College of Engineering</option>
                        <option value="College of Industrial Technology">College of Industrial Technology</option>
                    </select>
                </div>

                <div style="display: flex; gap: 15px;">
                    <div class="input-group" style="flex: 2;">
                        <label>Program</label>
                        <input type="text" name="student_program" placeholder="e.g. BSCS" required>
                    </div>
                    <div class="input-group" style="flex: 1;">
                        <label>Section</label>
                        <input type="text" name="student_section" placeholder="e.g. 3A" required>
                    </div>
                </div>

                <button type="submit" class="login-btn" style="margin-top: 10px; background-color: #3b82f6; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);">
                    Access Portal <i class="fas fa-arrow-right" style="font-size: 0.9rem;"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
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

        function openStudentModal() {
            document.getElementById('studentModal').style.display = 'flex';
        }

        function closeStudentModal() {
            document.getElementById('studentModal').style.display = 'none';
        }

        // Close modal if clicked outside the container
        document.getElementById('studentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeStudentModal();
            }
        });
    </script>
</body>
</html>