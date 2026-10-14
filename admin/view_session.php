<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Check if session ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('sessions.php');
}

$session_id = $_GET['id'];

// Get session details
$stmt = $pdo->prepare("SELECT s.*, c.course_name, c.course_code, u.full_name as created_by_name 
                       FROM sessions s 
                       JOIN courses c ON s.course_id = c.id 
                       JOIN users u ON s.created_by = u.id 
                       WHERE s.id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

if (!$session) {
    redirect('sessions.php');
}

// Get attendance records for this session
$stmt = $pdo->prepare("SELECT a.*, s.student_id, u.full_name as student_name 
                       FROM attendance a 
                       JOIN students s ON a.student_id = s.id 
                       JOIN users u ON s.user_id = u.id 
                       WHERE a.session_id = ? 
                       ORDER BY a.scanned_at DESC");
$stmt->execute([$session_id]);
$attendance_records = $stmt->fetchAll();

$attendance_count = count($attendance_records);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Session - Attendance System</title>
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

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 24px;
            margin-bottom: 24px;
        }

        .card h3 {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .info-item {
            padding: 10px;
            background: #f7fafc;
            border-radius: 6px;
        }

        .info-item label {
            display: block;
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .info-item .value {
            font-size: 16px;
            color: #2d3748;
            font-weight: 500;
            margin-top: 3px;
        }

        .qr-container {
            text-align: center;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .qr-container img {
            max-width: 200px;
            height: auto;
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

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .btn-primary {
            background: #2d3748;
            color: white;
        }

        .btn-primary:hover {
            background: #1a202c;
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

        .flex {
            display: flex;
            gap: 10px;
            align-items: center;
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

            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }

            .card {
                padding: 15px;
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
                    <h1>Session Details</h1>
                    <p class="subtitle">View session information and attendance</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <!-- Session Info -->
            <div class="card">
                <div class="flex" style="justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h3>Session Information</h3>
                    <a href="sessions.php" class="btn btn-secondary">Back to Sessions</a>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Session Code</label>
                        <div class="value"><?php echo $session['session_code']; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Course</label>
                        <div class="value"><?php echo $session['course_code'] . ' - ' . $session['course_name']; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Topic</label>
                        <div class="value"><?php echo $session['topic']; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Date</label>
                        <div class="value"><?php echo date('l, d M Y', strtotime($session['session_date'])); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Time</label>
                        <div class="value"><?php echo date('h:i A', strtotime($session['start_time'])) . ' - ' . date('h:i A', strtotime($session['end_time'])); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Created By</label>
                        <div class="value"><?php echo $session['created_by_name']; ?></div>
                    </div>
                </div>
            </div>

            <!-- QR Code -->
            <div class="card">
                <h3>QR Code</h3>
                <div class="qr-container">
                    <?php if ($session['qr_image'] && file_exists('../' . $session['qr_image'])): ?>
                        <img src="../<?php echo $session['qr_image']; ?>" alt="QR Code">
                        <p style="margin-top: 10px; color: #718096; font-size: 14px;">
                            Scan this QR code with your phone to mark attendance
                        </p>
                    <?php else: ?>
                        <p class="text-muted">QR Code not generated yet.</p>
                        <a href="generate_qr.php?id=<?php echo $session['id']; ?>" class="btn btn-primary" style="margin-top: 10px;">
                            Generate QR Code
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="card">
                <h3>Attendance Records (<?php echo $attendance_count; ?> students)</h3>
                <?php if ($attendance_count > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Status</th>
                            <th>Scanned At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance_records as $record): ?>
                        <tr>
                            <td><?php echo $record['student_id']; ?></td>
                            <td><?php echo $record['student_name']; ?></td>
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
                <p class="text-center text-muted py-3">No attendance records for this session yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>