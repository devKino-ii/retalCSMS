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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Forgot Password - URS COSMOS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* BASE MOBILE-FIRST STYLES */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-tap-highlight-color: transparent; }
        
        body { background-color: #cbd5e1; min-height: 100vh; display: flex; justify-content: center; align-items: flex-start; }
        
        .mobile-wrapper { width: 100%; min-height: 100vh; background-color: #050914; background-image: radial-gradient(2px 2px at 15% 25%, #fff, transparent), radial-gradient(1px 1px at 35% 50%, #fff, transparent), radial-gradient(ellipse at bottom left, #231041 0%, transparent 50%), radial-gradient(ellipse at top right, #0b1d3a 0%, #050914 100%); color: #fff; display: flex; flex-direction: column; position: relative; }
        
        @media (min-width: 480px) { 
            body { align-items: center; overflow: hidden; } 
            .mobile-wrapper { max-width: 414px; height: 90vh; min-height: auto; max-height: 896px; border-radius: 40px; border: 10px solid #1a202c; overflow-y: auto; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); } 
            .form-container { border-radius: 30px !important; }
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .mobile-header { display: flex; flex-direction: column; align-items: center; padding: 40px 20px 20px; animation: fadeIn 1s ease-out; text-align: center; }
        .logo-img { width: 80px; height: auto; margin-bottom: 15px; filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.2)); }
        .mobile-header h1 { font-size: 1.3rem; font-weight: 700; line-height: 1.2; letter-spacing: 1px; }
        .mobile-header p { font-size: 0.9rem; color: #8fa0c0; margin-top: 5px; }
        
        .form-container { background-color: #ffffff; border-radius: 30px 30px 0 0; padding: 40px 30px; flex-grow: 1; display: flex; flex-direction: column; animation: slideUp 0.6s ease-out both; box-shadow: 0 -5px 25px rgba(0,0,0,0.5); }
        .form-container h2 { color: #0b1d3a; font-size: 2rem; margin-bottom: 10px; font-weight: 700; }
        .form-container p.desc { color: #64748b; font-size: 0.95rem; line-height: 1.5; margin-bottom: 25px; }
        
        .alert-box { padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 0.95rem; font-weight: 600; text-align: center; animation: slideUp 0.3s ease-out both; }
        .alert-error { background-color: #fff5f5; color: #e53e3e; border: 1px solid #fca5a5; }
        .alert-success { background-color: #ecfdf5; color: #059669; border: 1px solid #6ee7b7; }
        
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; color: #4a5568; margin-bottom: 8px; font-size: 0.95rem; font-weight: 600; }
        .input-group input { width: 100%; padding: 16px 20px; border-radius: 12px; border: 2px solid #edf2f7; background-color: #f8fafc; font-size: 1rem; outline: none; transition: all 0.3s; color: #0b1d3a; }
        .input-group input:focus { border-color: #0b1d3a; background-color: #fff; }
        
        .login-btn { width: 100%; padding: 18px; background-color: #0b1d3a; color: #fff; border: none; border-radius: 12px; font-size: 1.1rem; font-weight: 600; cursor: pointer; box-shadow: 0 4px 15px rgba(11, 29, 58, 0.3); transition: background-color 0.2s; margin-top: 10px; }
        .login-btn:hover { background-color: #1a3668; }
        .login-btn:active { transform: scale(0.98); }
        
        .back-link { display: block; text-align: center; margin-top: 25px; color: #64748b; text-decoration: none; font-size: 0.95rem; font-weight: 600; transition: color 0.2s; }
        .back-link:hover { color: #0b1d3a; }
        
        .mobile-footer { margin-top: auto; padding-top: 30px; text-align: center; font-size: 0.8rem; color: #a0aec0; }
        .mobile-footer p { margin-bottom: 5px; }

        /* Loading Overlay */
        .loading-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255, 255, 255, 0.9); z-index: 999; justify-content: center; align-items: center; flex-direction: column; }
    </style>
</head>
<body>

    <div id="loadingScreen" class="loading-overlay">
        <i class="fas fa-circle-notch fa-spin" style="font-size: 40px; color: #0b1d3a; margin-bottom: 15px;"></i>
        <p style="color: #0b1d3a; font-weight: 600; font-size: 1.1rem;">Sending Email...</p>
    </div>

    <div class="mobile-wrapper">
        <header class="mobile-header">
            <img src="../LOGO_URS.png" alt="URS Logo" class="logo-img" onerror="this.style.display='none'">
            <h1>UNIVERSITY OF RIZAL SYSTEM</h1>
            <p>COSMOS Portal</p>
        </header>

        <div class="form-container">
            <h2>Forgot Password?</h2>
            <p class="desc">Enter the email address associated with your account and we will send you a temporary password.</p>
            
            <?php if($message): ?>
                <div class="alert-box <?php echo $messageType === 'error' ? 'alert-error' : 'alert-success'; ?>">
                    <i class="fas <?php echo $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i> 
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

            <div class="mobile-footer">
                <p>System Developers: Lin, Biñas, Lualhati, Olea, Raquedan</p>
                <p>© 2026 URS | Adviser: Prof. Dela Cruz</p>
            </div>
        </div>
    </div>
    
    <script>
        function showLoading() {
            document.getElementById('loadingScreen').style.display = 'flex';
        }
    </script>
</body>
</html>