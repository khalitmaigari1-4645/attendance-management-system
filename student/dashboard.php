<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is student
if (!isLoggedIn() || !isStudent()) {
    redirect('../auth/login.php');
}

// Get student details
$student = getStudentByUserId($_SESSION['user_id']);

if (!$student) {
    die("Student profile not found. Please contact admin.");
}

// Get attendance history
$attendance_history = getStudentAttendanceHistory($student['id']);
$total_attendance = count($attendance_history);
$present_count = 0;
$late_count = 0;

foreach ($attendance_history as $record) {
    if ($record['status'] == 'present') $present_count++;
    if ($record['status'] == 'late') $late_count++;
}

$attendance_rate = $total_attendance > 0 ? round(($present_count / $total_attendance) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Attendance System</title>
    <style>
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

        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

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

        .profile-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .profile-card .profile-row {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .profile-card .profile-item {
            flex: 1;
            min-width: 150px;
        }

        .profile-card .profile-item label {
            display: block;
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .profile-card .profile-item .value {
            font-size: 18px;
            color: #2d3748;
            font-weight: 600;
            margin-top: 3px;
        }

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

        .stat-card .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-card .stat-label {
            color: #718096;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }

        .stat-card.primary .stat-number {
            color: #2b6cb0;
        }
        .stat-card.success .stat-number {
            color: #38a169;
        }
        .stat-card.warning .stat-number {
            color: #d69e2e;
        }
        .stat-card.danger .stat-number {
            color: #e53e3e;
        }

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

        table tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-present {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge-late {
            background: #fefcbf;
            color: #744210;
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

        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .profile-card .profile-row {
                flex-direction: column;
                gap: 15px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .table-container {
                padding: 15px;
                overflow-x: auto;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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
                <p>Student Panel</p>
            </div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="attendance.php">My Attendance</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Student Dashboard</h1>
                    <p class="subtitle">Welcome back, <?php echo $_SESSION['full_name']; ?></p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <!-- Profile Card -->
            <div class="profile-card">
                <div class="profile-row">
                    <div class="profile-item">
                        <label>Student ID</label>
                        <div class="value"><?php echo $student['student_id']; ?></div>
                    </div>
                    <div class="profile-item">
                        <label>Full Name</label>
                        <div class="value"><?php echo $_SESSION['full_name']; ?></div>
                    </div>
                    <div class="profile-item">
                        <label>Course</label>
                        <div class="value"><?php echo $student['course'] ?: 'Not specified'; ?></div>
                    </div>
                    <div class="profile-item">
                        <label>Phone</label>
                        <div class="value"><?php echo $student['phone'] ?: 'Not specified'; ?></div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-number"><?php echo $total_attendance; ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $present_count; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $late_count; ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-number"><?php echo $attendance_rate; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>

            <!-- Attendance History -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Recent Attendance History</h3>
                </div>
                <?php if (count($attendance_history) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Session Code</th>
                                <th>Course</th>
                                <th>Topic</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($attendance_history, 0, 10) as $record): ?>
                                <tr>
                                    <td><strong><?php echo $record['session_code']; ?></strong></td>
                                    <td><?php echo $record['course_code']; ?></td>
                                    <td><?php echo $record['topic']; ?></td>
                                    <td><?php echo date('d M Y', strtotime($record['session_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $record['status']; ?>">
                                            <?php echo ucfirst($record['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (count($attendance_history) > 10): ?>
                        <p style="text-align: center; margin-top: 15px;">
                            <a href="attendance.php" class="btn">View All Attendance</a>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-center text-muted py-3">No attendance records yet. Scan a QR code to mark your attendance.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>