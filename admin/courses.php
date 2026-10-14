<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Handle delete course
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $_SESSION['success'] = "Course deleted successfully!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
    }
    redirect('courses.php');
}

// Handle add/edit course
$edit_course = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_course = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $course_code = sanitize($_POST['course_code']);
    $course_name = sanitize($_POST['course_name']);
    $description = sanitize($_POST['description']);
    $edit_id = isset($_POST['edit_id']) ? $_POST['edit_id'] : null;
    
    if (empty($course_code) || empty($course_name)) {
        $error = "Course code and name are required!";
    } else {
        try {
            if ($edit_id) {
                // Update existing course
                $stmt = $pdo->prepare("UPDATE courses SET course_code = ?, course_name = ?, description = ? WHERE id = ?");
                $stmt->execute([$course_code, $course_name, $description, $edit_id]);
                $_SESSION['success'] = "Course updated successfully!";
                $edit_course = null;
            } else {
                // Add new course
                $stmt = $pdo->prepare("INSERT INTO courses (course_code, course_name, description) VALUES (?, ?, ?)");
                $stmt->execute([$course_code, $course_name, $description]);
                $_SESSION['success'] = "Course added successfully!";
            }
            redirect('courses.php');
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get all courses
$courses = getAllCourses();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Attendance System</title>
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
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f7fafc;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #2d3748;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(45, 55, 72, 0.1);
        }
        .form-group textarea {
            resize: vertical;
            min-height: 60px;
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
        .btn-success:hover { background: #2f855a; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3); }
        .btn-danger { background: #e53e3e; color: white; }
        .btn-danger:hover { background: #c53030; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(229, 62, 62, 0.3); }
        .btn-warning { background: #d69e2e; color: white; }
        .btn-warning:hover { background: #b7791f; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(214, 158, 46, 0.3); }
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
                    <h1>Manage Courses</h1>
                    <p class="subtitle">Add, edit, or remove courses from the system</p>
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

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="form-container">
                <h3><?php echo $edit_course ? 'Edit Course' : 'Add New Course'; ?></h3>
                <form method="POST" action="">
                    <?php if ($edit_course): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $edit_course['id']; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label>Course Code <span class="required">*</span></label>
                        <input type="text" name="course_code" value="<?php echo $edit_course ? $edit_course['course_code'] : ''; ?>" placeholder="e.g., CS101" required>
                    </div>
                    <div class="form-group">
                        <label>Course Name <span class="required">*</span></label>
                        <input type="text" name="course_name" value="<?php echo $edit_course ? $edit_course['course_name'] : ''; ?>" placeholder="e.g., Introduction to Programming" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Brief description of the course"><?php echo $edit_course ? $edit_course['description'] : ''; ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success"><?php echo $edit_course ? 'Update Course' : 'Add Course'; ?></button>
                        <?php if ($edit_course): ?>
                            <a href="courses.php" class="btn btn-outline">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Courses Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Courses</h3>
                    <span><?php echo count($courses); ?> courses</span>
                </div>
                <?php if (count($courses) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><strong><?php echo $course['course_code']; ?></strong></td>
                            <td><?php echo $course['course_name']; ?></td>
                            <td><?php echo $course['description'] ?: '-'; ?></td>
                            <td>
                                <div class="actions">
                                    <a href="courses.php?edit=<?php echo $course['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="courses.php?delete=<?php echo $course['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this course?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-muted py-3">No courses added yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>