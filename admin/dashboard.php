<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Get statistics
$total_students = getTotalStudents();
$total_courses = getTotalCourses();
$total_sessions = getTotalSessions();
$total_attendance = getTotalAttendance();

// Get recent sessions
$stmt = $pdo->query("SELECT s.*, c.course_name, c.course_code, u.full_name as created_by_name 
                     FROM sessions s 
                     JOIN courses c ON s.course_id = c.id 
                     JOIN users u ON s.created_by = u.id 
                     ORDER BY s.created_at DESC LIMIT 5");
$recent_sessions = $stmt->fetchAll();

// Get recent attendance
$stmt = $pdo->query("SELECT a.*, s.student_id, u.full_name as student_name, sess.session_code, sess.topic
                     FROM attendance a 
                     JOIN students s ON a.student_id = s.id 
                     JOIN users u ON s.user_id = u.id 
                     JOIN sessions sess ON a.session_id = sess.id 
                     ORDER BY a.scanned_at DESC LIMIT 10");
$recent_attendance = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Attendance System</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }

        /* Dashboard Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
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

        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .sidebar-header p {
            font-size: 12px;
            opacity: 0.7;
            margin-top: 5px;
        }

        .sidebar ul {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar ul li {
            margin-bottom: 2px;
        }

        .sidebar ul li a {
            display: block;
            padding: 12px 25px;
            color: #cbd5e0;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .sidebar ul li a:hover {
            background: rgba(255,255,255,0.1);
            color: #ffffff;
        }

        .sidebar ul li a.active {
            background: rgba(255,255,255,0.1);
            color: #ffffff;
            border-right: 4px solid #ffffff;
        }

        /* Main Content */
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

        .header h1 {
            color: #2d3748;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .header .subtitle {
            color: #718096;
            margin-top: 4px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: #4a5568;
            font-size: 14px;
        }

        .user-info .user-name {
            font-weight: 600;
            color: #2d3748;
        }

        .user-info a {
            color: #e53e3e;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 14px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .user-info a:hover {
            background: #e53e3e;
            color: white;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 24px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .stat-card .stat-label {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #2d3748;
            margin-top: 8px;
        }

        .stat-card.primary .stat-number { color: #2b6cb0; }
        .stat-card.success .stat-number { color: #38a169; }
        .stat-card.warning .stat-number { color: #d69e2e; }
        .stat-card.danger .stat-number { color: #e53e3e; }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        /* Tables */
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

        .table-header h3 {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
        }

        .table-container table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table-container table th {
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

        .table-container table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f0f4f8;
            color: #2d3748;
        }

        .table-container table tr:hover {
            background: #f7fafc;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-present {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-late {
            background: #fefcbf;
            color: #744210;
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: #2d3748;
            color: white;
        }

        .btn-primary:hover {
            background: #1a202c;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(45, 55, 72, 0.3);
        }

        .btn-success {
            background: #38a169;
            color: white;
        }

        .btn-success:hover {
            background: #2f855a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3);
        }

        .btn-warning {
            background: #d69e2e;
            color: white;
        }

        .btn-warning:hover {
            background: #b7791f;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(214, 158, 46, 0.3);
        }

        .btn-sm {
            padding: 5px 14px;
            font-size: 12px;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #718096;
        }

        .py-3 {
            padding: 30px 0;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                position: relative;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .dashboard-container {
                flex-direction: column;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .table-container {
                padding: 15px;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h2>Attendance System</h2>
                <p>Administrator Panel</p>
            </div>
           <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="students.php">Manage Students</a></li>
                <li><a href="lecturers.php">Manage Lecturers</a></li>
                <li><a href="courses.php" class="active">Manage Courses</a></li>
                <li><a href="sessions.php">Manage Sessions</a></li>
                <li><a href="attendance_report.php">Attendance Reports</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Dashboard</h1>
                    <p class="subtitle">Welcome back, <?php echo $_SESSION['full_name']; ?></p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-label">Total Students</div>
                    <div class="stat-number"><?php echo $total_students; ?></div>
                </div>
                <div class="stat-card success">
                    <div class="stat-label">Total Courses</div>
                    <div class="stat-number"><?php echo $total_courses; ?></div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-label">Total Sessions</div>
                    <div class="stat-number"><?php echo $total_sessions; ?></div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-label">Attendance Records</div>
                    <div class="stat-number"><?php echo $total_attendance; ?></div>
                </div>
            </div>

            <div class="content-grid">
                <!-- Recent Sessions -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Recent Sessions</h3>
                        <a href="sessions.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <?php if (count($recent_sessions) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Session Code</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Date</th>
                                <th>Created By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sessions as $session): ?>
                            <tr>
                                <td><strong><?php echo $session['session_code']; ?></strong></td>
                                <td><?php echo $session['course_code'] . ' - ' . $session['course_name']; ?></td>
                                <td><?php echo $session['topic']; ?></td>
                                <td><?php echo date('d M Y', strtotime($session['session_date'])); ?></td>
                                <td><?php echo $session['created_by_name']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-center text-muted py-3">No sessions created yet.</p>
                    <?php endif; ?>
                </div>

                <!-- Recent Attendance -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>Recent Attendance</h3>
                        <a href="attendance_report.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <?php if (count($recent_attendance) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Session</th>
                                <th>Status</th>
                                <th>Scanned At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attendance as $record): ?>
                            <tr>
                                <td><?php echo $record['student_name']; ?></td>
                                <td><?php echo $record['session_code'] . ' - ' . $record['topic']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y H:i', strtotime($record['scanned_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p class="text-center text-muted py-3">No attendance records yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>