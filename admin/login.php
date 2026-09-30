<?php
session_start();
include '../db.php';

// If already logged in, redirect to admin dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error = '';
$username_entered = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $username_entered = $username;
    
    if (!empty($username) && !empty($password)) {
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $admin = $result->fetch_assoc();
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_id'] = $admin['id'];

                // Clear navigation stack for fresh start
                $_SESSION['nav_stack'] = [];

                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid password. Please try again.';
            }
        } else {
            $error = 'Username not found.';
        }
        $stmt->close();
    } else {
        $error = 'Please enter both username and password.';
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - eCert BPMI</title>
    
    <!-- Bootstrap CSS - Using CDN for reliability -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a3c5e 0%, #2a5f7a 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
        }
        
        .login-box {
            background: white;
            padding: 40px 35px 30px;
            border-radius: 12px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.4);
        }
        
        .login-box .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-box .logo .lock-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        
        .login-box .logo h2 {
            color: #1a3c5e;
            margin: 0;
            font-weight: 700;
            font-size: 24px;
        }
        
        .login-box .logo p {
            color: #888;
            margin: 5px 0 0;
            font-size: 14px;
        }
        
        .login-box .form-group {
            margin-bottom: 20px;
        }
        
        .login-box .form-group label {
            font-weight: 600;
            color: #555;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }
        
        .login-box .form-control {
            height: 45px;
            border-radius: 6px;
            border: 2px solid #e1e5eb;
            font-size: 14px;
            padding: 0 15px;
            width: 100%;
        }
        
        .login-box .form-control:focus {
            border-color: #1a3c5e;
            box-shadow: 0 0 0 3px rgba(26, 60, 94, 0.1);
            outline: none;
        }
        
        .input-group {
            display: flex;
            width: 100%;
            position: relative;
        }
        
        .input-group-addon {
            background: #f8f9fa;
            border: 2px solid #e1e5eb;
            border-right: none;
            border-radius: 6px 0 0 6px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            height: 45px;
            font-size: 14px;
            color: #555;
            min-width: 45px;        /* Fixed width for icon */
            flex-shrink: 0;         /* Prevents shrinking */

            
        }
        
        .input-group .form-control {
            border-left: none;
            border-radius: 0 6px 6px 0;
            flex: 1;
            min-width: 0;           /* Allows shrinking */
            width: 100%;            /* Full width */
        }
        
        .input-group .form-control:focus {
            border-left: none;
        }
        
        /* Password toggle button */
        .password-toggle {
            position: absolute;
            right: 0;
            top: 0;
            height: 45px;
            border: 2px solid #e1e5eb;
            border-left: none;
            border-radius: 0 6px 6px 0;
            background: #f8f9fa;
            color: #888;
            padding: 0 15px;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
            display: flex;
            align-items: center;
            font-size: 18px;
        }
        
        .password-toggle:hover {
            background: #e9ecef;
            color: #1a3c5e;
        }
        
        .input-group .form-control.password-input {
            border-right: none;
            border-radius: 6px 0 0 6px;
            padding-right: 45px;
        }
        
        /* Show password checkbox */
        .show-password-label {
            font-size: 13px;
            color: #888;
            cursor: pointer;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .show-password-label input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            margin: 0;
        }
        
        .btn-login {
            width: 100%;
            padding: 12px;
            font-size: 16px;
            font-weight: 600;
            background: #1a3c5e;
            border: none;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background: #0f2a42;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 60, 94, 0.3);
        }
        
        .btn-login .glyphicon {
            margin-right: 5px;
        }
        
        .error {
            color: #d9534f;
            text-align: center;
            margin-bottom: 15px;
            padding: 10px;
            background: #f8d7da;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
            display: block;
            color: #888;
            font-size: 14px;
            text-decoration: none;
        }
        
        .back-link:hover {
            color: #1a3c5e;
            text-decoration: none;
        }
        
        .back-link .glyphicon {
            margin-right: 5px;
        }
        
        .login-hint {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 12px;
            color: #666;
            border: 1px dashed #ddd;
            text-align: center;
        }
        
        .login-hint strong {
            color: #1a3c5e;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.6);
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px;
            }
            .login-box .logo h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <span class="lock-icon">🔐</span>
                <h2>Admin Access</h2>
                <p>eCert BPMI Perikanan</p>
            </div>
            
            <?php if ($error): ?>
                <div class="error">
                    <span class="glyphicon glyphicon-exclamation-sign"></span>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-group">
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-user"></span>
                        </span>
                        <input type="text" id="username" name="username" class="form-control" 
                               placeholder="Enter username" value="<?php echo htmlspecialchars($username_entered); ?>" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group" style="position: relative;">
                        <span class="input-group-addon">
                            <span class="glyphicon glyphicon-lock"></span>
                        </span>
                        <input type="password" id="password" name="password" class="form-control password-input" 
                               placeholder="Enter password" required>
                        <button type="button" class="password-toggle" id="togglePassword" title="Show/Hide Password">
                            <span class="glyphicon glyphicon-eye-open" id="toggleIcon"></span>
                        </button>
                    </div>
                </div>
                
                <!-- Show password checkbox (Don't want to use this) -->
                
                
                <button type="submit" class="btn-login">
                    <span class="glyphicon glyphicon-log-in"></span> Login
                </button>
            </form>
            
            <a href="../index.php" class="back-link">
                <span class="glyphicon glyphicon-arrow-left"></span> Back to Certificate Search
            </a>
            
        </div>
        
        <div class="login-footer">
            &copy; 2018 BPMI Perikanan. All rights reserved.
        </div>
    </div>

    <!-- Password Toggle JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');
            
            // Toggle function
            function togglePasswordVisibility() {
                const isVisible = passwordInput.type === 'text';
                
                if (isVisible) {
                    passwordInput.type = 'password';
                    toggleIcon.className = 'glyphicon glyphicon-eye-open';
                } else {
                    passwordInput.type = 'text';
                    toggleIcon.className = 'glyphicon glyphicon-eye-close';
                }
            }
            
            // Eye button click
            toggleButton.addEventListener('click', togglePasswordVisibility);
            
        });
    </script>
</body>
</html>