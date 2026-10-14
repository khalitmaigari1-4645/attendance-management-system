<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Handle delete lecturer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'lecturer'");
        $stmt->execute([$_GET['delete']]);
        $success = "Lecturer deleted successfully!";
    } catch(PDOException $e) {
        $error = "Error deleting lecturer: " . $e->getMessage();
    }
}

// Handle edit lecturer
$edit_lecturer = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'lecturer'");
    $stmt->execute([$_GET['edit']]);
    $edit_lecturer = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $edit_id = isset($_POST['edit_id']) ? $_POST['edit_id'] : null;
    
    $errors = array();
    
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($username)) $errors[] = "Username is required";
    if (!$edit_id && empty($password)) $errors[] = "Password is required for new lecturer";
    
    if (empty($errors)) {
        try {
            if ($edit_id) {
                // Update existing lecturer
                if ($password) {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, password = MD5(?) WHERE id = ? AND role = 'lecturer'");
                    $stmt->execute([$full_name, $username, $password, $edit_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ? WHERE id = ? AND role = 'lecturer'");
                    $stmt->execute([$full_name, $username, $edit_id]);
                }
                $success = "Lecturer updated successfully!";
                $edit_lecturer = null;
            } else {
                // Add new lecturer
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, MD5(?), 'lecturer', ?)");
                $stmt->execute([$username, $password, $full_name]);
                $success = "Lecturer added successfully!<br><strong>Username:</strong> " . $username . "<br><strong>Password:</strong> " . $password;
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get all lecturers
$stmt = $pdo->query("SELECT * FROM users WHERE role = 'lecturer' ORDER BY id DESC");
$lecturers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Lecturers - Attendance System</title>
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
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            max-width: 600px;
        }
        .form-container h3 { color: #2d3748; font-size: 18px; font-weight: 600; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        .form-group input:focus {
            outline: none;
            border-color: #2d3748;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
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
        .btn-danger { background: #e53e3e; color: white; }
        .btn-danger:hover { background: #c53030; transform: translateY(-1px); }
        .btn-warning { background: #d69e2e; color: white; }
        .btn-warning:hover { background: #b7791f; transform: translateY(-1px); }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        .btn-outline { background: transparent; border: 2px solid #718096; color: #718096; }
        .btn-outline:hover { background: #718096; color: white; }
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
        .actions { display: flex; gap: 8px; }
        .required { color: #e53e3e; }
        .info-note {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            color: #2a69ac;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; min-height: auto; }
            .main-content { margin-left: 0; padding: 20px; }
            .dashboard-container { flex-direction: column; }
            .header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .form-container { max-width: 100%; }
        }
        @media (max-width: 480px) {
            .main-content { padding: 15px; }
            .table-container { padding: 15px; overflow-x: auto; }
            .form-container { padding: 15px; }
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
                    <h1>Manage Lecturers</h1>
                    <p class="subtitle">Add, edit, or remove lecturers from the system</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <div class="info-note">
                <strong>Note:</strong> Lecturers can create sessions and manage attendance for their courses.
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="form-container">
                <h3><?php echo $edit_lecturer ? 'Edit Lecturer' : 'Add New Lecturer'; ?></h3>
                <form method="POST" action="">
                    <?php if ($edit_lecturer): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $edit_lecturer['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="full_name" value="<?php echo $edit_lecturer ? $edit_lecturer['full_name'] : ''; ?>" placeholder="Enter full name" required>
                    </div>
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" value="<?php echo $edit_lecturer ? $edit_lecturer['username'] : ''; ?>" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label>Password <?php echo $edit_lecturer ? '<span style="font-weight:400;color:#718096;">(Leave blank to keep current)</span>' : '<span class="required">*</span>'; ?></label>
                        <input type="text" name="password" <?php echo $edit_lecturer ? '' : 'required'; ?> placeholder="<?php echo $edit_lecturer ? 'Leave blank to keep current' : 'Enter password'; ?>">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><?php echo $edit_lecturer ? 'Update Lecturer' : 'Add Lecturer'; ?></button>
                        <?php if ($edit_lecturer): ?>
                            <a href="lecturers.php" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Lecturers Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Lecturers</h3>
                    <span><?php echo count($lecturers); ?> lecturers</span>
                </div>
                <?php if (count($lecturers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lecturers as $lecturer): ?>
                        <tr>
                            <td><?php echo $lecturer['id']; ?></td>
                            <td><?php echo $lecturer['full_name']; ?></td>
                            <td><?php echo $lecturer['username']; ?></td>
                            <td>
                                <div class="actions">
                                    <a href="lecturers.php?edit=<?php echo $lecturer['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="lecturers.php?delete=<?php echo $lecturer['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this lecturer?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-muted py-3">No lecturers registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>