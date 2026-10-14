<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

require_once '../config/database.php';
require_once '../includes/functions.php';


/*
|--------------------------------------------------------------------------
| Get QR session code
|--------------------------------------------------------------------------
*/

$session_code = trim($_GET['code'] ?? '');

if ($session_code === '') {
    die('Invalid QR Code. Session code not found.');
}


/*
|--------------------------------------------------------------------------
| Get session
|--------------------------------------------------------------------------
*/

$session = getSessionByCode($session_code);

if (!$session) {
    die('Invalid QR Code. Session not found.');
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isLoggedIn()) {

    /*
    |--------------------------------------------------------------------------
    | Save attendance URL for after login
    |--------------------------------------------------------------------------
    */

    $_SESSION['redirect_after_login'] =
        'student/scan.php?code=' . urlencode($session_code);

    /*
    |--------------------------------------------------------------------------
    | Redirect to login
    |--------------------------------------------------------------------------
    */

    redirect('../auth/login.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Verify student role
|--------------------------------------------------------------------------
*/

if (!isStudent()) {

    die(
        'Only students can mark attendance. ' .
        'Please login as a student.'
    );
}


/*
|--------------------------------------------------------------------------
| Get student record
|--------------------------------------------------------------------------
*/

$student = getStudentByUserId($_SESSION['user_id']);

if (!$student) {

    die(
        'Student profile not found. ' .
        'Please contact admin.'
    );
}


/*
|--------------------------------------------------------------------------
| IDs
|--------------------------------------------------------------------------
*/

$session_id = (int) $session['id'];

$student_id = (int) $student['id'];


/*
|--------------------------------------------------------------------------
| Process attendance POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['mark_attendance'])
) {

    /*
    |--------------------------------------------------------------------------
    | Validate status
    |--------------------------------------------------------------------------
    */

    $status = $_POST['status'] ?? 'present';

    if (!in_array($status, ['present', 'late'], true)) {
        $status = 'present';
    }


    /*
    |--------------------------------------------------------------------------
    | Check if attendance already exists
    |--------------------------------------------------------------------------
    */

    if (hasMarkedAttendance($session_id, $student_id)) {

        $_SESSION['error'] =
            'Attendance has already been marked for this session.';

        redirect('dashboard.php');

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Check whether session is active
    |--------------------------------------------------------------------------
    */

    if (!isSessionActive($session)) {

        $_SESSION['error'] =
            'This attendance session is not currently active.';

        redirect('dashboard.php');

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | Mark attendance
    |--------------------------------------------------------------------------
    */

    $result = markAttendance(
        $session_id,
        $student_id,
        $status
    );


    /*
    |--------------------------------------------------------------------------
    | Handle attendance result
    |--------------------------------------------------------------------------
    */

    if ($result === true) {

        $_SESSION['success'] =
            'Attendance marked successfully! You are ' .
            ucfirst($status) . '.';

    } elseif ($result === 'duplicate') {

        $_SESSION['error'] =
            'Attendance has already been marked for this session.';

    } else {

        $_SESSION['error'] =
            'Unable to mark attendance. Please try again.';
    }


    /*
    |--------------------------------------------------------------------------
    | Redirect to student dashboard
    |--------------------------------------------------------------------------
    */

    redirect('dashboard.php');

    exit;
}


/*
|--------------------------------------------------------------------------
| Attendance message
|--------------------------------------------------------------------------
*/

$message = '';

$message_type = '';

if (isset($_SESSION['attendance_message'])) {

    $message = $_SESSION['attendance_message'];

    $message_type =
        $_SESSION['attendance_message_type'] ?? 'success';

    unset($_SESSION['attendance_message']);
    unset($_SESSION['attendance_message_type']);
}


/*
|--------------------------------------------------------------------------
| Check attendance
|--------------------------------------------------------------------------
*/

$already_marked = hasMarkedAttendance(
    $session_id,
    $student_id
);


/*
|--------------------------------------------------------------------------
| Get attendance record
|--------------------------------------------------------------------------
*/

$attendance_record = null;

if ($already_marked) {

    $stmt = $pdo->prepare("
        SELECT
            id,
            status,
            scanned_at
        FROM attendance
        WHERE session_id = ?
          AND student_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $session_id,
        $student_id
    ]);

    $attendance_record =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Escape helper
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
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

    <title>Mark Attendance</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            padding: 20px;
            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7fb;
            color: #1f2937;
        }


        .container {
            width: 100%;
            max-width: 650px;
            margin: 30px auto;
        }


        .card {
            background: #ffffff;

            border-radius: 14px;

            padding: 30px;

            margin-bottom: 20px;

            box-shadow:
                0 5px 20px
                rgba(0, 0, 0, 0.08);
        }


        h1 {
            margin-top: 0;
            margin-bottom: 8px;

            font-size: 28px;

            text-align: center;
        }


        h2 {
            margin-top: 0;
            margin-bottom: 20px;
        }


        .subtitle {
            text-align: center;

            color: #6b7280;

            margin-bottom: 25px;
        }


        .info {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 12px;
        }


        .info-item {
            background: #f8fafc;

            border-radius: 10px;

            padding: 15px;
        }


        .label {
            display: block;

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 5px;
        }


        .value {
            font-weight: 600;

            word-break: break-word;
        }


        .message {
            padding: 15px 18px;

            border-radius: 10px;

            margin-bottom: 20px;

            font-weight: 600;
        }


        .message.success {
            background: #dcfce7;

            color: #166534;

            border:
                1px solid #86efac;
        }


        .message.error {
            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fca5a5;
        }


        .message.warning {
            background: #fef3c7;

            color: #92400e;

            border:
                1px solid #fcd34d;
        }


        .status-title {
            font-size: 18px;

            font-weight: 700;

            margin-bottom: 15px;
        }


        .status-options {
            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 15px;

            margin-bottom: 20px;
        }


        .status-option {
            position: relative;
        }


        .status-option input {
            position: absolute;

            opacity: 0;
        }


        .status-option label {
            display: block;

            padding: 18px;

            text-align: center;

            border:
                2px solid #e5e7eb;

            border-radius: 10px;

            cursor: pointer;

            font-weight: 600;

            transition: 0.2s;
        }


        .status-option input:checked + label {
            border-color: #2563eb;

            background: #eff6ff;

            color: #1d4ed8;
        }


        .submit-btn {
            width: 100%;

            border: none;

            border-radius: 10px;

            padding: 16px;

            background: #2563eb;

            color: white;

            font-size: 17px;

            font-weight: 700;

            cursor: pointer;
        }


        .submit-btn:hover {
            background: #1d4ed8;
        }


        .already-marked {
            text-align: center;

            padding: 25px;

            background: #ecfdf5;

            border:
                1px solid #86efac;

            border-radius: 12px;
        }


        .check {
            font-size: 45px;

            margin-bottom: 10px;
        }


        .status {
            font-size: 20px;

            font-weight: 700;

            margin-bottom: 8px;
        }


        .time {
            color: #6b7280;
        }


        @media (max-width: 600px) {

            body {
                padding: 12px;
            }


            .container {
                margin: 10px auto;
            }


            .card {
                padding: 20px;
            }


            .info {
                grid-template-columns: 1fr;
            }


            .status-options {
                grid-template-columns: 1fr;
            }


            h1 {
                font-size: 24px;
            }

        }

    </style>

</head>


<body>

<div class="container">


    <!-- SESSION INFORMATION -->

    <div class="card">

        <h1>
            📋 Mark Attendance
        </h1>


        <div class="subtitle">
            Scan session attendance
        </div>


        <?php if ($message !== ''): ?>

            <div class="message <?= e($message_type) ?>">

                <?= e($message) ?>

            </div>

        <?php endif; ?>


        <div class="info">

            <div class="info-item">

                <span class="label">
                    Course
                </span>

                <span class="value">

                    <?= e(
                        $session['course_code'] ?? 'N/A'
                    ) ?>

                    -

                    <?= e(
                        $session['course_name'] ?? 'N/A'
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Topic
                </span>

                <span class="value">

                    <?= e(
                        $session['topic'] ?? 'N/A'
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Date
                </span>

                <span class="value">

                    <?= e(
                        $session['session_date'] ?? 'N/A'
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Time
                </span>

                <span class="value">

                    <?= e(
                        $session['start_time'] ?? 'N/A'
                    ) ?>

                    -

                    <?= e(
                        $session['end_time'] ?? 'N/A'
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Session Code
                </span>

                <span class="value">

                    <?= e(
                        $session['session_code']
                    ) ?>

                </span>

            </div>

        </div>

    </div>


    <!-- STUDENT INFORMATION -->

    <div class="card">

        <h2>
            Student Information
        </h2>


        <div class="info">

            <div class="info-item">

                <span class="label">
                    Name
                </span>

                <span class="value">

                    <?= e(
                        $_SESSION['full_name'] ?? 'Student'
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Student ID
                </span>

                <span class="value">

                    <?= e(
                        $student['student_id']
                    ) ?>

                </span>

            </div>


            <div class="info-item">

                <span class="label">
                    Course
                </span>

                <span class="value">

                    <?= e(
                        $student['course'] ?? 'N/A'
                    ) ?>

                </span>

            </div>

        </div>

    </div>


    <!-- ATTENDANCE -->

    <div class="card">

        <?php if (
            $already_marked &&
            $attendance_record
        ): ?>


            <div class="already-marked">

                <div class="check">
                    ✅
                </div>


                <div class="status">
                    Attendance Already Marked
                </div>


                <div>

                    Status:

                    <strong>

                        <?= e(
                            ucfirst(
                                $attendance_record['status']
                            )
                        ) ?>

                    </strong>

                </div>


                <div class="time">

                    Scanned at:

                    <?= e(
                        $attendance_record['scanned_at']
                    ) ?>

                </div>

            </div>


        <?php else: ?>


            <div class="status-title">

                Select Attendance Status

            </div>


            <form
                method="POST"
                action="scan.php?code=<?= urlencode($session_code) ?>"
            >


                <div class="status-options">


                    <div class="status-option">

                        <input
                            type="radio"
                            id="present"
                            name="status"
                            value="present"
                            checked
                        >

                        <label for="present">
                            ✅ Present
                        </label>

                    </div>


                    <div class="status-option">

                        <input
                            type="radio"
                            id="late"
                            name="status"
                            value="late"
                        >

                        <label for="late">
                            ⏰ Late
                        </label>

                    </div>


                </div>


                <button
                    type="submit"
                    name="mark_attendance"
                    value="1"
                    class="submit-btn"
                >

                    Mark Attendance

                </button>


            </form>


        <?php endif; ?>

    </div>


</div>

</body>

</html>