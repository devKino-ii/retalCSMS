<?php
session_start();
require '../db_connect.php'; 

// === PHPMailer Manual Requires ===
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com'; 
        $mail->SMTPAuth   = true;
        $mail->Username   = 'innerforce001@gmail.com'; 
        $mail->Password   = 'bgxnwgwiefvixkct';    
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('no-reply@cosmos.ursm.edu.ph', 'URSM COSMOS');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

$message = '';
$messageType = ''; // 'error' or 'success'

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    // 1. Check if the email exists in the database
    $stmt = $conn->prepare("SELECT id, username, first_name, surname FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // 2. Generate a random 8-character temporary password
        $temp_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$'), 0, 8);
        $hashed_temp_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // 3. Update the database with the new hashed password
        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->bind_param("si", $hashed_temp_password, $row['id']);
        
        if ($updateStmt->execute()) {
            // 4. Send the email with the temporary credentials
            $firstName = $row['first_name'];
            $surname = $row['surname'];
            $username = $row['username'];

            $subject = "COSMOS Account Password Reset";
            $body = "
                <div style='font-family: Arial, sans-serif; color: #333;'>
                    <h2 style='color: #1b5088;'>Password Reset Request</h2>
                    <p>Hello <b>{$firstName} {$surname}</b>,</p>
                    <p>We received a request to reset the password for your COSMOS account.</p>
                    <p>Your password has been reset. Below is your account information and your new temporary password:</p>
                    <div style='background: #f4f7fb; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><b>Username:</b> {$username}</p>
                        <p style='margin: 5px 0;'><b>Temporary Password:</b> <span style='font-family: monospace; font-size: 16px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px;'>{$temp_password}</span></p>
                    </div>
                    <p style='color: #ef4444; font-weight: bold;'>For your security, please log in immediately and change this temporary password.</p>
                    <br>
                    <p>Best Regards,<br><b>The System Administrator</b></p>
                </div>
            ";

            if (sendEmail($email, $subject, $body)) {
                $message = "A temporary password has been sent to your email address.";
                $messageType = "success";
            } else {
                $message = "Password reset in database, but failed to send the email.";
                $messageType = "error";
            }
        } else {
            $message = "System error: Could not update the password.";
            $messageType = "error";
        }
        $updateStmt->close();
    } else {
        // For security, it's best not to confirm whether an email exists or not to prevent enumeration,
        // but for a closed university system, a direct error is usually preferred.
        $message = "No account found with that email address.";
        $messageType = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - URS COSMOS</title>

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

        .left-footer p {
            font-size: 0.85rem;
            color: #8fa0c0;
            animation: slideUp 0.8s ease-out 0.2s both;
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
            font-size: 2.2rem;
            margin-bottom: 10px;
            font-weight: 700;
            animation: slideUp 0.6s ease-out 0.1s both;
        }

        .form-wrapper p.desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 25px;
            animation: slideUp 0.6s ease-out 0.15s both;
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
            margin-top: 10px;
            transition: transform 0.2s, background-color 0.3s;
            animation: slideUp 0.6s ease-out 0.4s both;
        }

        .login-btn:hover {
            background-color: #1a3668;
            transform: translateY(-2px);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #64748b;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            transition: color 0.2s;
            animation: slideUp 0.6s ease-out 0.5s both;
        }

        .back-link:hover {
            color: #0b1d3a;
        }

        .alert-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            animation: slideUp 0.3s ease-out both;
        }
        
        .alert-error {
            background-color: #fef2f2;
            color: #e53e3e;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #6ee7b7;
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
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

    <div id="loadingScreen" class="loading-overlay">
        <i class="fas fa-circle-notch fa-spin" style="font-size: 40px; color: #0b1d3a; margin-bottom: 15px;"></i>
        <p style="color: #0b1d3a; font-weight: 600; font-size: 1.1rem;">Sending Email...</p>
    </div>

    <div class="login-container">
        
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
                <h2>Forgot Password?</h2>
                <p class="desc">Enter the email address associated with your account and we will send you a temporary password.</p>
                
                <?php if($message): ?>
                    <div class="alert-box <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="resetForm" onsubmit="showLoading()">
                    <div class="input-group">
                        <label for="email">Email Address</label>
                        <input type="email" name="email" id="email" placeholder="Enter your registered email" required>
                    </div>

                    <button type="submit" class="login-btn">Send Reset Email</button>
                    
                    <a href="Login_index.php" class="back-link">← Back to Login</a>
                </form>
            </div>
        </div>

    </div>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        function showLoading() {
            document.getElementById('loadingScreen').style.display = 'flex';
        }
    </script>
</body>
</html>