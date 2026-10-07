<?php
include '../db.php';
include 'logger.php';

// If already logged in, redirect based on role
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if (($_SESSION['admin_role'] ?? 'admin') === 'developer') {
        header('Location: developer/index.php');
    } else {
        header('Location: index.php');
    }
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
                $_SESSION['admin_role'] = $admin['role'] ?? 'admin';
                
                log_activity($conn, 'auth', 'login', 'success',
                    "User '{$admin['username']}' logged in as {$admin['role']}",
                    $admin['username'],
                    ['role' => $admin['role']]);
                
                if (($_SESSION['admin_role']) === 'developer') {
                    header('Location: developer/index.php');
                } else {
                    header('Location: index.php');
                }
                exit;
            } else {
                log_activity($conn, 'auth', 'login', 'failure',
                    "Failed login attempt (wrong password)",
                    $username,
                    ['attempted_username' => $username]);
                
                $error = 'Invalid password. Please try again.';
            }
        } else {
            log_activity($conn, 'auth', 'login', 'failure',
                "Failed login attempt (username not found)",
                $username,
                ['attempted_username' => $username]);
            
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
    <title>Login - eCert BPMI</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Arial, sans-serif;
        }

        /* ============================================================
           Background: forest image with a dark blue overlay
           Replace the URL below with your own forest image if you
           want a different scene.
           ============================================================ */
        body {
            min-height: 100vh;
            background:
                linear-gradient(180deg, rgba(15,35,60,0.55) 0%, rgba(20,50,80,0.35) 40%, rgba(60,90,120,0.35) 100%),
                url('../images/background.jpg')
                center center / cover no-repeat fixed;
            background-color: #0e2a44;
            color: #fff;
            overflow-x: hidden;
        }

        /* ============================================================
           Top navigation bar
           ============================================================ */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
            background: linear-gradient(180deg, rgba(0,0,0,0.35) 0%, rgba(0,0,0,0) 100%);
        }

        .top-nav .brand {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: 0.5px;
            text-shadow: 0 2px 8px rgba(0,0,0,0.5);
            text-decoration: none;
        }

        .top-nav .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
            list-style: none;
        }

        .top-nav .nav-links a {
            color: #ffffff;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            opacity: 0.95;
            transition: opacity 0.2s ease;
            text-shadow: 0 1px 4px rgba(0,0,0,0.4);
        }

        .top-nav .nav-links a:hover { opacity: 0.75; }

        /* ============================================================
           Login card (frosted glass)
           ============================================================ */
        .login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 100px 20px 40px;
        }

        .login-card {
            position: relative;
            width: 100%;
            max-width: 460px;
            background: rgba(240, 245, 252, 0.92);
            border-radius: 14px;
            padding: 40px 40px 32px;
            box-shadow:
                0 25px 60px rgba(0, 0, 0, 0.45),
                0 0 0 1px rgba(255, 255, 255, 0.4) inset;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: #1a2c3e;
        }

        /* Close (X) button top-right of card */
        .login-card .close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            background: #1a3c5e;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s ease;
            line-height: 1;
        }

        .login-card .close-btn:hover {
            background: #0f2a42;
            color: #ffffff;
            text-decoration: none;
        }

        /* Title */
        .login-card h2 {
            text-align: center;
            font-size: 30px;
            font-weight: 800;
            color: #16283d;
            margin: 0 0 30px 0;
            letter-spacing: 0.3px;
        }

        /* Error banner */
        .login-card .error {
            background: #fde8e8;
            color: #b02a37;
            border: 1px solid #f5c2c7;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 13.5px;
            margin-bottom: 18px;
            text-align: center;
        }

        /* Form groups */
        .login-card .form-group {
            margin-bottom: 18px;
        }

        .login-card label {
            display: block;
            font-size: 13.5px;
            font-weight: 500;
            color: #4a5a6a;
            margin-bottom: 6px;
        }

        /* Input row (icon inside the field) */
        .field-wrap {
            position: relative;
            width: 100%;
        }

        .field-wrap input {
            width: 100%;
            height: 46px;
            padding: 0 44px 0 14px; /* right space for icon */
            font-size: 14px;
            color: #1a2c3e;
            background: transparent;
            border: none;
            border-bottom: 2px solid #8fa3b8;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .field-wrap input::placeholder {
            color: #9aabbd;
        }

        .field-wrap input:focus {
            border-bottom-color: #1a3c5e;
        }

        /* Icon inside the field */
        .field-wrap .field-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            color: #6a7d93;
            pointer-events: none;
            line-height: 1;
        }

        /* Password eye is clickable */
        .field-wrap .field-icon.clickable {
            pointer-events: auto;
            cursor: pointer;
            background: none;
            border: none;
            padding: 4px;
        }

        .field-wrap .field-icon.clickable:hover {
            color: #1a3c5e;
        }

        /* Login button */
        .btn-login-new {
            width: 100%;
            height: 48px;
            border: none;
            border-radius: 8px;
            background: #16283d;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.3px;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.15s ease;
        }

        .btn-login-new:hover {
            background: #0f1e2e;
            transform: translateY(-1px);
        }

        .btn-login-new:active {
            transform: translateY(0);
        }

        /* Footer inside card */
        .card-footer {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #4a5a6a;
        }

        .card-footer a {
            color: #1a3c5e;
            font-weight: 600;
            text-decoration: none;
        }

        .card-footer a:hover {
            text-decoration: underline;
        }

        /* ============================================================
           Responsive tweaks
           ============================================================ */
        @media (max-width: 640px) {
            .top-nav { padding: 14px 20px; }
            .login-card { padding: 32px 24px 26px; }
            .login-card h2 { font-size: 26px; }
        }
    </style>
</head>
<body>

    <!-- ============================================================
         Top navigation bar
         ============================================================ -->
    <nav class="top-nav">
        <a href="../index.php" class="brand">eCert BPMI</a>
    </nav>

    <!-- ============================================================
         Login card
         ============================================================ -->
    <div class="login-wrapper">
        <div class="login-card">

            <!-- Close (X) — goes back to public search -->
            <a href="../index.php" class="close-btn" title="Close">&times;</a>

            <h2>Admin Login</h2>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off">
                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="field-wrap">
                        <input type="text" id="username" name="username"
                               placeholder="Enter username"
                               value="<?php echo htmlspecialchars($username_entered); ?>"
                               required autofocus>
                        <span class="field-icon glyphicon glyphicon-user"></span>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="field-wrap">
                        <input type="password" id="password" name="password"
                               placeholder="Enter password" required>
                        <button type="button" class="field-icon clickable" id="togglePassword"
                                title="Show/Hide password" aria-label="Show or hide password">
                            <span class="glyphicon glyphicon-eye-open" id="toggleIcon"></span>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn-login-new">Login</button>
            </form>

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password');
            const toggleButton  = document.getElementById('togglePassword');
            const toggleIcon    = document.getElementById('toggleIcon');

            toggleButton.addEventListener('click', function () {
                const isVisible = passwordInput.type === 'text';
                if (isVisible) {
                    passwordInput.type = 'password';
                    toggleIcon.className = 'glyphicon glyphicon-eye-open';
                } else {
                    passwordInput.type = 'text';
                    toggleIcon.className = 'glyphicon glyphicon-eye-close';
                }
            });
        });
    </script>
</body>
</html>
