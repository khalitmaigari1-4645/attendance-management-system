<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is lecturer
if (!isLoggedIn() || !isLecturer()) {
    redirect('../auth/login.php');
}

// Get lecturer's sessions
$sessions = getSessionsByLecturer($_SESSION['user_id']);

// Get statistics
$total_sessions = count($sessions);
$total_students = getTotalStudents();
$total_courses = getTotalCourses();

// Get total attendance for lecturer's sessions
$total_attendance = 0;
foreach ($sessions as $session) {
    $total_attendance += getAttendanceCount($session['id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard - Attendance System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }
        .stat-card .stat-number { font-size: 32px; font-weight: 700; color: #2d3748; }
        .stat-card .stat-label {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        .stat-card.primary .stat-number { color: #2b6cb0; }
        .stat-card.success .stat-number { color: #38a169; }
        .stat-card.warning .stat-number { color: #d69e2e; }
        .stat-card.danger .stat-number { color: #e53e3e; }
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
        .btn-success:hover { background: #2f855a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3); }
        .btn-info { background: #3182ce; color: white; }
        .btn-info:hover { background: #2b6cb0; }
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
        @media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; padding: 20px; }
            .dashboard-container { flex-direction: column; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .table-container { padding: 15px; overflow-x: auto; }
            .stats-grid { grid-template-columns: 1fr; }
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
                    <h1>Lecturer Dashboard</h1>
                    <p class="subtitle">Welcome, <?php echo $_SESSION['full_name']; ?></p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-number"><?php echo $total_sessions; ?></div>
                    <div class="stat-label">My Sessions</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $total_attendance; ?></div>
                    <div class="stat-label">Total Attendance</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $total_courses; ?></div>
                    <div class="stat-label">Courses</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Students</div>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header">
                    <h3>My Recent Sessions</h3>
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
                            <th>Attendance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($sessions, 0, 10) as $session): ?>
                        <tr>
                            <td><strong><?php echo $session['session_code']; ?></strong></td>
                            <td><?php echo $session['course_code']; ?></td>
                            <td><?php echo $session['topic']; ?></td>
                            <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                            <td><?php echo date('h:i A', strtotime($session['start_time'])) . ' - ' . date('h:i A', strtotime($session['end_time'])); ?></td>
                            <td><?php echo getAttendanceCount($session['id']); ?> students</td>
                            <td>
                                <div class="actions">
                                    <a href="view_session.php?id=<?php echo $session['id']; ?>" class="btn btn-info">View</a>
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