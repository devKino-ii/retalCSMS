<?php
session_start();
require '../db_connect.php'; 

$error = '';
$submitted_username = ''; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $submitted_username = $username; 
    $password = $_POST['password'];

    // FIXED: Added 'program' to the SELECT statement
    $stmt = $conn->prepare("SELECT id, password, position, department, program, first_name, surname, middle_initial, profile_picture FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['position'] = $row['position']; 
            $_SESSION['department'] = $row['department'];
            
            // FIXED: Save the program to the session
            $_SESSION['program'] = $row['program'];

            $mi = !empty($row['middle_initial']) ? trim($row['middle_initial']) . '. ' : '';
            $_SESSION['full_name'] = trim($row['first_name']) . ' ' . $mi . trim($row['surname']);

            if (!empty($row['profile_picture'])) {
                $_SESSION['profile_picture'] = 'data:image/jpeg;base64,' . base64_encode($row['profile_picture']);
            } else {
                $_SESSION['profile_picture'] = null;
            }

            switch ($row['position']) {
                case 'Admin':
                    header("Location: ../Admin/Dashboard_index.php");
                    exit();
                case 'Dean':
                case 'Dean Secretary':
                    header("Location: ../College_Deans/Dashboard_index.php");
                    exit();
                case 'Program Head':
                    header("Location: ../Program_Heads/Dashboard_index.php");
                    exit();
                case 'Faculty':
                    header("Location: ../Faculty/Dashboard_index.php");
                    exit();
                case 'General Education Coordinator':
                    header("Location: ../Gen_Ed_Coordicator/Dashboard_index.php");
                    exit();
                default:
                    $error = "Access denied: Unrecognized user position.";
                    session_destroy(); 
                    break;
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "No account found with that username.";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>COSMOS Login - University of Rizal System</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <?php
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
    ?>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            height: 100vh;
            background-color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow-x: hidden;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }

        .login-container {
            display: flex;
            width: 100%;
            height: 100vh;
            overflow: hidden;
            animation: fadeIn 1s ease-out;
        }

        .left-panel {
            background-color: #050914;
            background-image: 
                radial-gradient(2px 2px at 15% 25%, #fff, transparent),
                radial-gradient(1px 1px at 35% 50%, #fff, transparent),
                radial-gradient(2px 2px at 65% 15%, #d4e8ff, transparent),
                radial-gradient(2px 2px at 25% 85%, #ffd4d4, transparent),
                radial-gradient(ellipse at bottom left, #231041 0%, transparent 50%),
                radial-gradient(ellipse at top right, #0b1d3a 0%, #050914 100%);
            
            width: 55%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 40px;
            border-radius: 0 35% 45% 0 / 0 15% 85% 0;
            z-index: 2;
            box-shadow: 15px 0 40px rgba(0, 0, 0, 0.3);
        }

        .branding {
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideUp 0.8s ease-out both;
        }

        .logo-img {
            width: 70px;
            height: auto;
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.3));
        }

        .branding h1 {
            font-size: 1.4rem;
            color: #ffffff;
            font-weight: 700;
            line-height: 1.1;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .illustration-container {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .cosmos-star {
            width: 600px;
            height: auto;
            animation: float 5s ease-in-out infinite;
            filter: drop-shadow(0 0 25px rgba(255, 223, 100, 0.4));
        }

        .left-footer p.copyright {
            font-size: 0.85rem;
            color: #8fa0c0;
            animation: slideUp 0.8s ease-out 0.2s both;
        }

        .left-footer p.credits {
            margin-top: 8px;
            font-size: 0.75rem;
            color: #5c6e8e;
            line-height: 1.5;
            animation: slideUp 0.8s ease-out 0.3s both;
        }

        .right-panel {
            width: 45%;
            background-color: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
        }

        .form-wrapper {
            width: 100%;
            max-width: 380px;
        }

        .form-wrapper h2 {
            color: #0b1d3a;
            font-size: 2.5rem;
            margin-bottom: 30px;
            font-weight: 700;
            animation: slideUp 0.6s ease-out 0.1s both;
        }

        .input-group {
            margin-bottom: 20px;
            animation: slideUp 0.6s ease-out 0.2s both;
        }

        .input-group label {
            display: block;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .input-group input {
            width: 100%;
            padding: 15px 20px;
            border-radius: 12px;
            border: 2px solid #edf2f7;
            background-color: #f8fafc;
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }

        .input-group input:focus {
            border-color: #0b1d3a;
            background-color: #fff;
            box-shadow: 0 4px 12px rgba(11, 29, 58, 0.1);
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 45px; 
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #718096;
            font-size: 1.1rem;
            transition: color 0.2s ease;
        }

        .toggle-password:hover {
            color: #0b1d3a;
        }

        .forgot-link {
            display: block;
            text-align: right;
            margin-top: -10px;
            margin-bottom: 25px;
            color: #64748b;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
            animation: slideUp 0.6s ease-out 0.3s both;
        }

        .forgot-link:hover {
            color: #0b1d3a;
            text-decoration: underline;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background-color: #0b1d3a;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, background-color 0.3s;
            animation: slideUp 0.6s ease-out 0.4s both;
        }

        .login-btn:hover {
            background-color: #1a3668;
            transform: translateY(-2px);
        }

        .right-footer {
            position: absolute;
            bottom: 30px;
            font-size: 0.8rem;
            color: #718096;
            text-align: center;
        }

        /* --- MOBILE POPUP NOTIFICATION STYLES --- */
        #mobile-blocker {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(5, 9, 20, 0.95);
            z-index: 9999;
            display: none; 
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(8px);
        }

        .mobile-modal {
            background: #ffffff;
            padding: 35px;
            border-radius: 20px;
            text-align: center;
            max-width: 90%;
            width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            animation: slideUp 0.5s ease-out;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #edf2f7;
        }

        .modal-header img {
            width: 45px;
            height: auto;
        }

        .modal-header h2 {
            font-size: 1.1rem;
            color: #0b1d3a;
            font-weight: 700;
            line-height: 1.1;
            text-align: left;
            margin: 0;
        }

        .mobile-modal .cosmos-img {
            width: 140px;
            margin-bottom: 20px;
            animation: float 4s ease-in-out infinite;
            filter: drop-shadow(0 0 15px rgba(11, 29, 58, 0.2));
        }

        .mobile-modal h3 {
            color: #e53e3e;
            font-size: 1.4rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .mobile-modal p {
            color: #4a5568;
            font-size: 0.95rem;
            line-height: 1.6;
        }

        @media (max-width: 850px) {
            .login-container { flex-direction: column; }
            .left-panel { width: 100%; height: 40%; border-radius: 0 0 20% 20%; }
            .right-panel { width: 100%; height: 60%; }
            .cosmos-star { width: 150px; }
        }
    </style>
</head>
<body>

    <div id="mobile-blocker">
        <div class="mobile-modal">
            <div class="modal-header">
                <img src="../LOGO_URS.png" alt="URS Logo">
                <h2>UNIVERSITY OF<br>RIZAL SYSTEM</h2>
            </div>
            
            <img src="../COSMOS.png" alt="COSMOS Star" class="cosmos-img">
            <h3>Access Restricted</h3>
            <p>The COSMOS system is currently not accessible on mobile devices. Please log in using a desktop or laptop computer.</p>
        </div>
    </div>

    <div class="login-container" id="main-content">
        
        <div class="left-panel">
            <header class="branding">
                <img src="../LOGO_URS.png" alt="URS Logo" class="logo-img">
                <h1>UNIVERSITY OF<br>RIZAL SYSTEM</h1>
            </header>

            <div class="illustration-container">
                <img src="../COSMOS.png" alt="COSMOS Star" class="cosmos-star">
            </div>

            <footer class="left-footer">
                <p class="copyright">© 2026 University of Rizal System</p>
                <p class="credits">
                    System Developers: Lin, Biñas, Lualhati, Olea, Raquedan<br>
                    Adviser: Prof. Dela Cruz
                </p>
            </footer>
        </div>

        <div class="right-panel">
            <div class="form-wrapper">
                <h2>Login</h2>
                
                <?php if($error): ?>
                    <div style="color: #e53e3e; margin-bottom: 15px; font-size: 0.9rem;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="input-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($submitted_username); ?>" required>
                    </div>
                    
                    <div class="input-group" style="margin-bottom: 15px;">
                        <label for="password">Password</label>
                        <div class="password-container">
                            <input type="password" name="password" id="password" placeholder="Enter your password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('password', this)"></i>
                        </div>
                    </div>

                    <a href="Forgot_Password_index.php" class="forgot-link">Forgot Password?</a>

                    <button type="submit" class="login-btn">Login</button>
                </form>
            </div>

        </div>

    </div>

    <script>
        // MOBILE DETECTION SCRIPT
        document.addEventListener("DOMContentLoaded", function() {
            // Check via User Agent string OR Screen width (adjust 850 as needed)
            const isMobileUserAgent = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            const isSmallScreen = window.innerWidth <= 850;

            if (isMobileUserAgent || isSmallScreen) {
                // Show the blocker and strictly hide the login form
                document.getElementById('mobile-blocker').style.display = 'flex';
                document.getElementById('main-content').style.display = 'none';
            }
            
            // Re-evaluate on window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 850 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
                    document.getElementById('mobile-blocker').style.display = 'flex';
                    document.getElementById('main-content').style.display = 'none';
                } else {
                    document.getElementById('mobile-blocker').style.display = 'none';
                    document.getElementById('main-content').style.display = 'flex';
                }
            });
        });

        // Push a duplicate state so the back button stays on this page
        history.pushState(null, null, location.href);

        window.addEventListener('popstate', function () {
            // Every time back is pressed, push forward again
            history.pushState(null, null, location.href);
        });

        // Toggle Password Visibility Function
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
    </script>
</body>
</html>