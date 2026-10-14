<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is lecturer
if (!isLoggedIn() || !isLecturer()) {
    redirect('../auth/login.php');
}

// Get lecturer's sessions
$sessions = getSessionsByLecturer($_SESSION['user_id']);

// Get filter parameters
$filter_session = isset($_GET['session_id']) ? $_GET['session_id'] : '';
$filter_student = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query for attendance records from lecturer's sessions
$query = "SELECT a.*, s.student_id, u.full_name as student_name, 
          sess.session_code, sess.topic, sess.session_date, sess.start_time, sess.end_time,
          c.course_name, c.course_code
          FROM attendance a 
          JOIN students s ON a.student_id = s.id 
          JOIN users u ON s.user_id = u.id 
          JOIN sessions sess ON a.session_id = sess.id 
          JOIN courses c ON sess.course_id = c.id 
          WHERE sess.created_by = ?";

$params = array($_SESSION['user_id']);

if (!empty($filter_session)) {
    $query .= " AND sess.id = ?";
    $params[] = $filter_session;
}

if (!empty($filter_student)) {
    $query .= " AND s.id = ?";
    $params[] = $filter_student;
}

if (!empty($filter_date)) {
    $query .= " AND DATE(a.scanned_at) = ?";
    $params[] = $filter_date;
}

$query .= " ORDER BY a.scanned_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$attendance_records = $stmt->fetchAll();

// Get all students for filter dropdown
$stmt = $pdo->query("SELECT s.*, u.full_name FROM students s JOIN users u ON s.user_id = u.id ORDER BY u.full_name");
$students = $stmt->fetchAll();

// Get statistics
$total_present = 0;
$total_late = 0;
foreach ($attendance_records as $record) {
    if ($record['status'] == 'present') $total_present++;
    if ($record['status'] == 'late') $total_late++;
}
$total_records = count($attendance_records);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Lecturer</title>
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
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 24px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            color: #2d3748;
            font-weight: 600;
            font-size: 13px;
        }
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            background: #f7fafc;
            transition: all 0.3s ease;
        }
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #2d3748;
            background: #ffffff;
        }
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
        .btn-secondary {
            background: #718096;
            color: white;
        }
        .btn-secondary:hover {
            background: #4a5568;
        }
        .btn-sm { padding: 4px 10px; font-size: 12px; }
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
        .badge {
            display: inline-block;
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-present { background: #c6f6d5; color: #22543d; }
        .badge-late { background: #fefcbf; color: #744210; }
        .text-center { text-align: center; }
        .text-muted { color: #718096; }
        .py-3 { padding: 30px 0; }
        .no-records {
            padding: 40px 0;
            text-align: center;
            color: #718096;
        }
        @media (max-width: 1024px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; padding: 20px; }
            .dashboard-container { flex-direction: column; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .filter-form { flex-direction: column; }
            .filter-group { width: 100%; }
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
        <!-- Sidebar -->
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

        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div>
                    <h1>Attendance Reports</h1>
                    <p class="subtitle">View attendance records for your sessions</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-number"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $total_present; ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $total_late; ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-number"><?php echo $total_records > 0 ? round(($total_present / $total_records) * 100) : 0; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label>Session</label>
                        <select name="session_id">
                            <option value="">All Sessions</option>
                            <?php foreach ($sessions as $session): ?>
                                <option value="<?php echo $session['id']; ?>" <?php echo ($filter_session == $session['id']) ? 'selected' : ''; ?>>
                                    <?php echo $session['session_code'] . ' - ' . $session['topic']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Student</label>
                        <select name="student_id">
                            <option value="">All Students</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['id']; ?>" <?php echo ($filter_student == $student['id']) ? 'selected' : ''; ?>>
                                    <?php echo $student['student_id'] . ' - ' . $student['full_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Date</label>
                        <input type="date" name="date" value="<?php echo $filter_date; ?>">
                    </div>
                    <div class="filter-group" style="min-width: 100px;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="attendance_report.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Attendance Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>Attendance Records</h3>
                    <span><?php echo $total_records; ?> records found</span>
                </div>
                <?php if ($total_records > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Session Code</th>
                            <th>Course</th>
                            <th>Topic</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><strong><?php echo $record['student_id']; ?></strong></td>
                            <td><?php echo $record['student_name']; ?></td>
                            <td><?php echo $record['session_code']; ?></td>
                            <td><?php echo $record['course_code']; ?></td>
                            <td><?php echo $record['topic']; ?></td>
                            <td><?php echo date('d M Y', strtotime($record['session_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $record['status']; ?>">
                                    <?php echo ucfirst($record['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y h:i A', strtotime($record['scanned_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-records">
                    <p>No attendance records found for your sessions.</p>
                    <p style="font-size: 13px; margin-top: 5px;">Create a session and have students scan QR codes to mark attendance.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>