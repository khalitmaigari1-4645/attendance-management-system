<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is lecturer
if (!isLoggedIn() || !isLecturer()) {
    redirect('../auth/login.php');
}

// Handle delete session
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        // Check if this session belongs to this lecturer
        $stmt = $pdo->prepare("SELECT qr_image FROM sessions WHERE id = ? AND created_by = ?");
        $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
        $session = $stmt->fetch();
        
        if ($session) {
            // Delete QR image file if exists
            if ($session['qr_image'] && file_exists('../' . $session['qr_image'])) {
                unlink('../' . $session['qr_image']);
            }
            
            // Delete session from database
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE id = ? AND created_by = ?");
            $stmt->execute([$_GET['delete'], $_SESSION['user_id']]);
            $_SESSION['success'] = "Session deleted successfully!";
        } else {
            $_SESSION['error'] = "Session not found or you don't have permission.";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error deleting session: " . $e->getMessage();
    }
    redirect('sessions.php');
}

// Get lecturer's sessions
$sessions = getSessionsByLecturer($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Sessions - Lecturer</title>
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
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-success { background: #38a169; color: white; }
        .btn-success:hover { background: #2f855a; transform: translateY(-1px); }
        .btn-info { background: #3182ce; color: white; }
        .btn-info:hover { background: #2b6cb0; transform: translateY(-1px); }
        .btn-primary { background: #2d3748; color: white; }
        .btn-primary:hover { background: #1a202c; transform: translateY(-1px); }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-danger:hover { background: #c53030; transform: translateY(-1px); }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .table-container {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .table-header h3 { color: #2d3748; font-size: 18px; font-weight: 600; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        table th {
            background: #f7fafc;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }
        table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f4f8;
            color: #2d3748;
        }
        table tr:hover { background: #f7fafc; }
        .text-center { text-align: center; }
        .text-muted { color: #718096; }
        .py-3 { padding: 30px 0; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .qr-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; padding: 20px; }
            .dashboard-container { flex-direction: column; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .actions { flex-direction: column; }
            .actions .btn { width: 100%; text-align: center; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .table-container { padding: 15px; overflow-x: auto; }
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
                    <h1>My Sessions</h1>
                    <p class="subtitle">View all your created sessions</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="table-container">
                <div class="table-header">
                    <h3>All Sessions</h3>
                    <a href="create_session.php" class="btn btn-success">+ Create New Session</a>
                </div>
                <?php if (count($sessions) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Session Code</th>
                            <th>Course</th>
                            <th>Topic</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>QR</th>
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><strong><?php echo $session['session_code']; ?></strong></td>
                            <td><?php echo $session['course_code']; ?></td>
                            <td><?php echo $session['topic']; ?></td>
                            <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($session['start_time'])) . ' - ' . date('h:i A', strtotime($session['end_time'])); ?></td>
                            <td>
                                <?php if ($session['qr_image'] && file_exists('../' . $session['qr_image'])): ?>
                                    <img src="../<?php echo $session['qr_image']; ?>" class="qr-thumb" alt="QR">
                                <?php else: ?>
                                    <span class="text-muted">No QR</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo getAttendanceCount($session['id']); ?> students</td>
                            <td>
                                <div class="actions">
                                    <a href="view_session.php?id=<?php echo $session['id']; ?>" class="btn btn-info btn-sm">View</a>
                                    <a href="generate_qr.php?id=<?php echo $session['id']; ?>" class="btn btn-primary btn-sm">QR</a>
                                    <a href="sessions.php?delete=<?php echo $session['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this session?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-muted py-3">No sessions created yet. <a href="create_session.php">Create your first session</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>