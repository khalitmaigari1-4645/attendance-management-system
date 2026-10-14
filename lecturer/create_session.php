<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is lecturer
if (!isLoggedIn() || !isLecturer()) {
    redirect('../auth/login.php');
}

// Get all courses for dropdown
$courses = getAllCourses();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_id = sanitize($_POST['course_id']);
    $session_date = sanitize($_POST['session_date']);
    $start_time = sanitize($_POST['start_time']);
    $end_time = sanitize($_POST['end_time']);
    $topic = sanitize($_POST['topic']);
    
    $errors = array();
    
    if (empty($course_id)) $errors[] = "Please select a course";
    if (empty($session_date)) $errors[] = "Please select a date";
    if (empty($start_time)) $errors[] = "Please select start time";
    if (empty($end_time)) $errors[] = "Please select end time";
    if (empty($topic)) $errors[] = "Please enter a topic";
    
    if (empty($errors)) {
        try {
            $session_code = generateSessionCode();
            
            $stmt = $pdo->prepare("INSERT INTO sessions (course_id, session_code, session_date, start_time, end_time, topic, created_by) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$course_id, $session_code, $session_date, $start_time, $end_time, $topic, $_SESSION['user_id']]);
            $session_id = $pdo->lastInsertId();
            
            // Generate QR Code
            // $qr_data = "http://localhost/attendance/student/scan.php?code=" . $session_code;
            $qr_data = getQRUrl($session_code);
            $qr_folder = '../qr_codes/';
            $qr_filename = 'qr_' . $session_code . '.png';
            $qr_filepath = $qr_folder . $qr_filename;
            
            // Create QR code using Google API
            $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_data);
            $qr_image = file_get_contents($qr_url);
            
            if ($qr_image !== false) {
                file_put_contents($qr_filepath, $qr_image);
            }
            
            $stmt = $pdo->prepare("UPDATE sessions SET qr_image = ? WHERE id = ?");
            $stmt->execute(['qr_codes/' . $qr_filename, $session_id]);
            
            $_SESSION['success'] = "Session created successfully! Session Code: " . $session_code;
            redirect('view_session.php?id=' . $session_id);
            
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Session - Lecturer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .dashboard-container { display: flex; min-height: 100vh; }
        .sidebar {
            width: 260px;
            background: #2d3748;
            color: #ffffff;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            overflow-y: auto;
            z-index: 100;
        }
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }
        .sidebar-header h2 { font-size: 20px; font-weight: 700; }
        .sidebar-header p { font-size: 12px; opacity: 0.7; margin-top: 5px; }
        .sidebar ul { list-style: none; padding: 20px 0; }
        .sidebar ul li { margin-bottom: 2px; }
        .sidebar ul li a {
            display: block;
            padding: 12px 25px;
            color: #cbd5e0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar ul li a:hover { background: rgba(255,255,255,0.1); color: #ffffff; }
        .sidebar ul li a.active { background: rgba(255,255,255,0.1); color: #ffffff; border-right: 4px solid #ffffff; }
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 30px;
            background: #f7fafc;
            min-height: 100vh;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .header h1 { color: #2d3748; font-size: 28px; font-weight: 700; }
        .header .subtitle { color: #718096; margin-top: 4px; }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #4a5568;
            font-size: 14px;
        }
        .user-info .user-name { font-weight: 600; color: #2d3748; }
        .user-info a {
            color: #e53e3e;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        .user-info a:hover { background: #e53e3e; color: white; }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error { background: #fff5f5; color: #c53030; border: 1px solid #feb2b2; }
        .alert-success { background: #f0fff4; color: #22543d; border: 1px solid #9ae6b4; }
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 700px;
        }
        .form-container h3 { color: #2d3748; font-size: 20px; font-weight: 600; margin-bottom: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group label .required { color: #e53e3e; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2d3748;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }
        .form-group textarea { resize: vertical; min-height: 60px; }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-success { background: #38a169; color: white; }
        .btn-success:hover { background: #2f855a; transform: translateY(-2px); box-shadow: 0 8px 16px rgba(56, 161, 105, 0.3); }
        .btn-secondary { background: #718096; color: white; }
        .btn-secondary:hover { background: #4a5568; transform: translateY(-2px); }
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .info-text { color: #718096; font-size: 13px; margin-top: 5px; }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; padding: 20px; }
            .dashboard-container { flex-direction: column; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-row { grid-template-columns: 1fr; }
            .form-container { padding: 20px; }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; text-align: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .form-container { padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Attendance System</h2>
                <p>Lecturer Panel</p>
            </div>
            <ul>
    <li><a href="dashboard.php" class="active">Dashboard</a></li>
    <li><a href="courses.php">Courses</a></li>
    <li><a href="sessions.php">My Sessions</a></li>
    <li><a href="create_session.php">Create Session</a></li>
    <li><a href="attendance_report.php">Attendance Reports</a></li>
    <li><a href="../auth/logout.php">Logout</a></li>
</ul>
        </nav>

        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Create New Session</h1>
                    <p class="subtitle">Create a new class session with QR code</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (count($courses) == 0): ?>
                <div class="alert alert-error">
                    <strong>No courses found!</strong> Please contact admin to add courses.
                </div>
            <?php endif; ?>

            <div class="form-container">
                <h3>Session Details</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Course <span class="required">*</span></label>
                        <select name="course_id" required>
                            <option value="">Select a course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo $course['id']; ?>">
                                    <?php echo $course['course_code'] . ' - ' . $course['course_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Topic <span class="required">*</span></label>
                        <input type="text" name="topic" placeholder="e.g., Introduction to PHP" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="session_date" value="<?php echo $today; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Start Time <span class="required">*</span></label>
                            <input type="time" name="start_time" value="09:00" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>End Time <span class="required">*</span></label>
                            <input type="time" name="end_time" value="10:30" required>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <p class="info-text">Session code and QR code will be generated automatically</p>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Create Session & Generate QR</button>
                        <a href="sessions.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>