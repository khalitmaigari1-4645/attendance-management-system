<?php

require_once '../config/database.php';
require_once '../includes/functions.php';

/*
|--------------------------------------------------------------------------
| If already logged in
|--------------------------------------------------------------------------
|
| If the user was sent to login because they scanned an attendance QR,
| return them to the attendance page instead of the dashboard.
|
*/

if (isLoggedIn()) {

    if (!empty($_SESSION['redirect_after_login'])) {

        $redirect_after_login =
            $_SESSION['redirect_after_login'];

        unset($_SESSION['redirect_after_login']);

        redirect('../' . ltrim($redirect_after_login, '/'));
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Normal dashboard redirect
    |--------------------------------------------------------------------------
    */

    if (isAdmin()) {

        redirect('../admin/dashboard.php');
        exit;

    } elseif (isStudent()) {

        redirect('../student/dashboard.php');
        exit;

    } elseif (isLecturer()) {

        redirect('../lecturer/dashboard.php');
        exit;
    }
}

$error = '';

/*
|--------------------------------------------------------------------------
| Success message
|--------------------------------------------------------------------------
*/

$success = isset($_SESSION['login_success'])
    ? $_SESSION['login_success']
    : '';

unset($_SESSION['login_success']);

/*
|--------------------------------------------------------------------------
| Logout message
|--------------------------------------------------------------------------
*/

$logout_msg = isset($_SESSION['logout_msg'])
    ? $_SESSION['logout_msg']
    : '';

unset($_SESSION['logout_msg']);

/*
|--------------------------------------------------------------------------
| Handle login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validate fields
    |--------------------------------------------------------------------------
    */

    if ($username === '' || $password === '') {

        $error = 'Please fill in all fields';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find user
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->execute([$username]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Check password
        |--------------------------------------------------------------------------
        |
        | Your current database uses MD5 passwords, so this keeps compatibility
        | with your existing database.
        |
        */

        if ($user && $user['password'] === md5($password)) {

            /*
            |--------------------------------------------------------------------------
            | Regenerate session ID after login
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);

            /*
            |--------------------------------------------------------------------------
            | Store user session
            |--------------------------------------------------------------------------
            */

            $_SESSION['user_id'] =
                $user['id'];

            $_SESSION['username'] =
                $user['username'];

            $_SESSION['role'] =
                $user['role'];

            $_SESSION['full_name'] =
                $user['full_name'];

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT:
            | Return to QR attendance page if one was saved.
            |--------------------------------------------------------------------------
            */

            if (!empty($_SESSION['redirect_after_login'])) {

                $redirect_after_login =
                    $_SESSION['redirect_after_login'];

                unset($_SESSION['redirect_after_login']);

                redirect(
                    '../' . ltrim($redirect_after_login, '/')
                );

                exit;
            }

            /*
            |--------------------------------------------------------------------------
            | Normal role-based redirect
            |--------------------------------------------------------------------------
            */

            if ($user['role'] === 'admin') {

                redirect('../admin/dashboard.php');
                exit;

            } elseif ($user['role'] === 'student') {

                redirect('../student/dashboard.php');
                exit;

            } elseif ($user['role'] === 'lecturer') {

                redirect('../lecturer/dashboard.php');
                exit;
            }

        } else {

            $error = 'Invalid username or password';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Login - Student Attendance Management System
    </title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family:
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                Roboto,
                "Helvetica Neue",
                Arial,
                sans-serif;

            background:
                linear-gradient(
                    135deg,
                    #667eea 0%,
                    #764ba2 100%
                );

            min-height: 100vh;

            display: flex;

            justify-content: center;

            align-items: center;

            padding: 20px;
        }

        .login-container {
            width: 100%;
            max-width: 420px;
        }

        .login-box {
            background: #ffffff;

            padding: 40px 35px;

            border-radius: 12px;

            box-shadow:
                0 20px 60px
                rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;

            margin-bottom: 30px;
        }

        .login-header h1 {
            color: #2d3748;

            font-size: 28px;

            font-weight: 700;

            letter-spacing: -0.5px;
        }

        .login-header p {
            color: #718096;

            font-size: 14px;

            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;

            margin-bottom: 6px;

            color: #2d3748;

            font-weight: 600;

            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-group input {
            width: 100%;

            padding: 12px 15px;

            border:
                2px solid #e2e8f0;

            border-radius: 8px;

            font-size: 15px;

            transition: all 0.3s ease;

            background: #f7fafc;
        }

        .form-group input[type="password"],
        .form-group input[type="text"] {
            padding-right: 45px;
        }

        .form-group input:focus {
            outline: none;

            border-color: #667eea;

            background: #ffffff;

            box-shadow:
                0 0 0 3px
                rgba(102, 126, 234, 0.1);
        }

        .toggle-password {
            position: absolute;

            right: 12px;

            top: 50%;

            transform: translateY(-50%);

            background: none;

            border: none;

            cursor: pointer;

            font-size: 18px;

            color: #a0aec0;

            padding: 5px;
        }

        .toggle-password:hover {
            color: #2d3748;
        }

        .btn-login {
            width: 100%;

            padding: 14px;

            background:
                linear-gradient(
                    135deg,
                    #667eea 0%,
                    #764ba2 100%
                );

            color: white;

            border: none;

            border-radius: 8px;

            font-size: 16px;

            font-weight: 600;

            cursor: pointer;

            transition: all 0.3s ease;

            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);

            box-shadow:
                0 10px 20px
                rgba(102, 126, 234, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            padding: 12px 16px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;
        }

        .alert-error {
            background: #fff5f5;

            color: #c53030;

            border:
                1px solid #feb2b2;
        }

        .alert-success {
            background: #f0fff4;

            color: #22543d;

            border:
                1px solid #9ae6b4;
        }

        .alert-info {
            background: #ebf8ff;

            color: #2a69ac;

            border:
                1px solid #bee3f8;
        }

        .login-footer {
            margin-top: 25px;

            padding-top: 20px;

            border-top:
                1px solid #e2e8f0;

            text-align: center;

            font-size: 13px;

            color: #a0aec0;
        }

        @media (max-width: 480px) {

            .login-box {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }
        }

    </style>

</head>

<body>

<div class="login-container">

    <div class="login-box">

        <div class="login-header">

            <h1>
                Attendance System
            </h1>

            <p>
                Student Attendance Management System
            </p>

        </div>


        <?php if ($logout_msg): ?>

            <div class="alert alert-info">
                <?= htmlspecialchars($logout_msg) ?>
            </div>

        <?php endif; ?>


        <?php if ($success): ?>

            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>

        <?php endif; ?>


        <?php if ($error): ?>

            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="">

            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your username"
                    required
                    autocomplete="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <div class="input-wrapper">

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        id="togglePassword"
                        aria-label="Show password"
                    >

                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                        >

                            <path
                                d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                            ></path>

                            <circle
                                cx="12"
                                cy="12"
                                r="3"
                            ></circle>

                        </svg>

                    </button>

                </div>

            </div>


            <button
                type="submit"
                class="btn-login"
            >
                Sign In
            </button>

        </form>


        <div class="login-footer">

            <p>
                &copy;
                <?= date('Y') ?>
                Attendance System.
                All rights reserved.
            </p>

        </div>

    </div>

</div>


<script>

document
    .getElementById('togglePassword')
    .addEventListener('click', function () {

        const passwordInput =
            document.getElementById('password');

        const type =
            passwordInput.getAttribute('type') === 'password'
                ? 'text'
                : 'password';

        passwordInput.setAttribute(
            'type',
            type
        );

        if (type === 'password') {

            this.innerHTML = `
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                    ></path>

                    <circle
                        cx="12"
                        cy="12"
                        r="3"
                    ></circle>
                </svg>
            `;

        } else {

            this.innerHTML = `
                <svg
                    width="20"
                    height="20"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                >
                    <path
                        d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"
                    ></path>

                    <path
                        d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"
                    ></path>

                    <path
                        d="M14.12 14.12a3 3 0 1 1-4.24-4.24"
                    ></path>

                    <line
                        x1="1"
                        y1="1"
                        x2="23"
                        y2="23"
                    ></line>
                </svg>
            `;
        }

    });

</script>

</body>

</html>