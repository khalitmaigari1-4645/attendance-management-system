<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

// Function to generate student ID like real schools
function generateStudentID() {
    global $pdo;
    
    // Get current year (last 2 digits)
    $year = date('y');
    
    // Get last student ID to increment
    $stmt = $pdo->query("SELECT student_id FROM students ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetch();
    
    if ($last) {
        // Extract the number from last ID (e.g., STU-24-001 -> 001)
        $parts = explode('-', $last['student_id']);
        $last_num = intval(end($parts));
        $new_num = str_pad($last_num + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $new_num = '001';
    }
    
    // Format: STU-YY-NNN (e.g., STU-24-001)
    return 'STU-' . $year . '-' . $new_num;
}

// Handle delete student
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $student_id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if ($student) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$student['user_id']]);
            $success = "Student deleted successfully!";
        }
    } catch(PDOException $e) {
        $error = "Error deleting student: " . $e->getMessage();
    }
}

// Handle add/edit student
$edit_student = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT s.*, u.full_name 
                           FROM students s 
                           JOIN users u ON s.user_id = u.id 
                           WHERE s.id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_student = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $course = sanitize($_POST['course']);
    $phone = sanitize($_POST['phone']);
    $edit_id = isset($_POST['edit_id']) ? $_POST['edit_id'] : null;
    
    // Validate all fields
    $errors = array();
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($course)) {
        $errors[] = "Course is required";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // Validate phone number (basic validation)
    if (!empty($phone) && !preg_match("/^[0-9]{10,15}$/", $phone)) {
        $errors[] = "Please enter a valid phone number (10-15 digits)";
    }
    
    if (empty($errors)) {
        try {
            if ($edit_id) {
                // Update existing student
                $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
                $stmt->execute([$edit_id]);
                $student = $stmt->fetch();
                
                $stmt = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
                $stmt->execute([$full_name, $student['user_id']]);
                
                $stmt = $pdo->prepare("UPDATE students SET course = ?, phone = ? WHERE id = ?");
                $stmt->execute([$course, $phone, $edit_id]);
                $success = "Student updated successfully!";
                $edit_student = null;
            } else {
                // Generate student ID
                $student_id = generateStudentID();
                $default_password = 'password123';
                
                // Add new student
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role, full_name) VALUES (?, MD5(?), 'student', ?)");
                $stmt->execute([$student_id, $default_password, $full_name]);
                $user_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, course, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $student_id, $course, $phone]);
                $success = "Student added successfully!<br><strong>Student ID:</strong> " . $student_id . "<br><strong>Default Password:</strong> password123";
            }
        } catch(PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Get all students
$stmt = $pdo->query("SELECT s.*, u.full_name 
                     FROM students s 
                     JOIN users u ON s.user_id = u.id 
                     ORDER BY s.id DESC");
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Attendance System</title>
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

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-error {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .form-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .form-container h3 {
            color: #2d3748;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label .required {
            color: #e53e3e;
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

        .form-group input[readonly] {
            background: #edf2f7;
            cursor: not-allowed;
        }

        .form-actions {
            grid-column: 1 / -1;
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
            padding: 5px 12px;
            font-size: 12px;
        }

        .btn-outline {
            background: transparent;
            border: 2px solid #718096;
            color: #718096;
        }

        .btn-outline:hover {
            background: #718096;
            color: white;
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

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: #718096;
        }

        .py-3 {
            padding: 30px 0;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .info-note {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            color: #2a69ac;
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        @media (max-width: 1024px) {
            .form-grid {
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
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .form-grid {
                grid-template-columns: 1fr;
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

            .form-container {
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
                    <h1>Manage Students</h1>
                    <p class="subtitle">Add, edit, or remove students from the system</p>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                    <span style="color: #718096;">|</span>
                    <a href="../auth/logout.php">Logout</a>
                </div>
            </div>

            <div class="info-note">
                <strong>Student ID Format:</strong> STU-YY-NNN (e.g., STU-24-001)
                <br><strong>Default Password:</strong> password123
                <br><strong>Note:</strong> All fields are required to add a student.
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add/Edit Form -->
            <div class="form-container">
                <h3><?php echo $edit_student ? 'Edit Student' : 'Add New Student'; ?></h3>
                <form method="POST" action="">
                    <?php if ($edit_student): ?>
                        <input type="hidden" name="edit_id" value="<?php echo $edit_student['id']; ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Student ID</label>
                            <?php if ($edit_student): ?>
                                <input type="text" value="<?php echo $edit_student['student_id']; ?>" readonly>
                            <?php else: ?>
                                <input type="text" value="<?php echo generateStudentID(); ?>" readonly>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="full_name" value="<?php echo $edit_student ? $edit_student['full_name'] : ''; ?>" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group">
                            <label>Course <span class="required">*</span></label>
                            <input type="text" name="course" value="<?php echo $edit_student ? $edit_student['course'] : ''; ?>" placeholder="e.g., Computer Science" required>
                        </div>
                        <div class="form-group">
                            <label>Phone <span class="required">*</span></label>
                            <input type="text" name="phone" value="<?php echo $edit_student ? $edit_student['phone'] : ''; ?>" placeholder="Enter phone number (10-15 digits)" required>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-success"><?php echo $edit_student ? 'Update Student' : 'Add Student'; ?></button>
                            <?php if ($edit_student): ?>
                                <a href="students.php" class="btn btn-outline">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>All Students</h3>
                    <span><?php echo count($students); ?> students</span>
                </div>
                <?php if (count($students) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Full Name</th>
                            <th>Course</th>
                            <th>Phone</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><strong><?php echo $student['student_id']; ?></strong></td>
                            <td><?php echo $student['full_name']; ?></td>
                            <td><?php echo $student['course']; ?></td>
                            <td><?php echo $student['phone']; ?></td>
                            <td>
                                <div class="actions">
                                    <a href="students.php?edit=<?php echo $student['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    <a href="students.php?delete=<?php echo $student['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this student?')">Delete</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="text-center text-muted py-3">No students registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>