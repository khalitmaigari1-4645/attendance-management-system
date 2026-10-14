
<?php

/**
 * Helper functions for the attendance system
 */

// ============================================================
// REDIRECT
// ============================================================

function redirect($page) {
    header("Location: " . $page);
    exit();
}


// ============================================================
// AUTHENTICATION / ROLES
// ============================================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLecturer() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'lecturer';
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}


// ============================================================
// USER FUNCTIONS
// ============================================================

function getUserDetails($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);

    return $stmt->fetch();
}


// ============================================================
// STUDENT FUNCTIONS
// ============================================================

function getStudentByUserId($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM students WHERE user_id = ?");
    $stmt->execute([$user_id]);

    return $stmt->fetch();
}

function getStudentById($student_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$student_id]);

    return $stmt->fetch();
}

function getStudentName($student_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT u.full_name
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ?
    ");

    $stmt->execute([$student_id]);

    $result = $stmt->fetch();

    return $result ? $result['full_name'] : 'Unknown';
}


// ============================================================
// COURSE FUNCTIONS
// ============================================================

function getAllCourses() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT *
        FROM courses
        ORDER BY course_name
    ");

    return $stmt->fetchAll();
}

function getCourseById($course_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT *
        FROM courses
        WHERE id = ?
    ");

    $stmt->execute([$course_id]);

    return $stmt->fetch();
}


// ============================================================
// SESSION FUNCTIONS
// ============================================================

function generateSessionCode() {
    return 'SES' .
        date('Ymd') .
        strtoupper(substr(uniqid(), -6));
}

function getSessionByCode($session_code) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            c.course_name,
            c.course_code,
            u.full_name AS created_by_name
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON s.created_by = u.id
        WHERE s.session_code = ?
    ");

    $stmt->execute([$session_code]);

    return $stmt->fetch();
}

function getAllSessions() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT
            s.*,
            c.course_name,
            c.course_code,
            u.full_name AS created_by_name
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        JOIN users u ON s.created_by = u.id
        ORDER BY s.session_date DESC, s.start_time DESC
    ");

    return $stmt->fetchAll();
}

function getSessionsByLecturer($user_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            s.*,
            c.course_name,
            c.course_code
        FROM sessions s
        JOIN courses c ON s.course_id = c.id
        WHERE s.created_by = ?
        ORDER BY s.session_date DESC, s.start_time DESC
    ");

    $stmt->execute([$user_id]);

    return $stmt->fetchAll();
}


// ============================================================
// ATTENDANCE FUNCTIONS
// ============================================================

/*
|--------------------------------------------------------------------------
| Check if student already marked attendance
|--------------------------------------------------------------------------
*/

function hasMarkedAttendance($session_id, $student_id) {
    global $pdo;

    try {

        $stmt = $pdo->prepare("
            SELECT id
            FROM attendance
            WHERE session_id = ?
              AND student_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            (int)$session_id,
            (int)$student_id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;

    } catch (PDOException $e) {

        error_log(
            "ATTENDANCE CHECK ERROR: " .
            $e->getMessage()
        );

        return false;
    }
}


/*
|--------------------------------------------------------------------------
| Mark attendance
|--------------------------------------------------------------------------
|
| Returns:
|
| true       = successfully inserted
| duplicate  = attendance already exists
| false      = database/other error
|
*/

function markAttendance(
    $session_id,
    $student_id,
    $status = 'present'
) {
    global $pdo;

    /*
    |--------------------------------------------------------------------------
    | Validate status
    |--------------------------------------------------------------------------
    */

    if (!in_array($status, ['present', 'late'], true)) {
        $status = 'present';
    }

    try {

        $stmt = $pdo->prepare("
            INSERT INTO attendance
                (session_id, student_id, status)
            VALUES
                (?, ?, ?)
        ");

        $stmt->execute([
            (int)$session_id,
            (int)$student_id,
            $status
        ]);

        return true;

    } catch (PDOException $e) {

        /*
        |--------------------------------------------------------------------------
        | MySQL duplicate-key error
        |--------------------------------------------------------------------------
        |
        | Your database has:
        |
        | UNIQUE KEY unique_attendance
        | (session_id, student_id)
        |
        | MySQL error 1062 means the student already has
        | attendance for this session.
        |
        */

        if (
            isset($e->errorInfo[1]) &&
            (int)$e->errorInfo[1] === 1062
        ) {

            error_log(
                "DUPLICATE ATTENDANCE: " .
                "session_id={$session_id}, " .
                "student_id={$student_id}"
            );

            return 'duplicate';
        }

        /*
        |--------------------------------------------------------------------------
        | Other database error
        |--------------------------------------------------------------------------
        */

        error_log(
            "ATTENDANCE INSERT ERROR: " .
            $e->getMessage()
        );

        error_log(
            "Session ID: {$session_id}, " .
            "Student ID: {$student_id}, " .
            "Status: {$status}"
        );

        return false;
    }
}

function getSessionAttendance($session_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            s.student_id,
            s.course,
            u.full_name
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        WHERE a.session_id = ?
        ORDER BY a.scanned_at DESC
    ");

    $stmt->execute([$session_id]);

    return $stmt->fetchAll();
}

function getAttendanceCount($session_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS count
        FROM attendance
        WHERE session_id = ?
    ");

    $stmt->execute([$session_id]);

    return $stmt->fetch()['count'];
}

function getStudentAttendanceHistory($student_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            a.*,
            s.session_code,
            s.session_date,
            s.start_time,
            s.end_time,
            s.topic,
            c.course_name,
            c.course_code
        FROM attendance a
        JOIN sessions s ON a.session_id = s.id
        JOIN courses c ON s.course_id = c.id
        WHERE a.student_id = ?
        ORDER BY
            s.session_date DESC,
            s.start_time DESC
    ");

    $stmt->execute([$student_id]);

    return $stmt->fetchAll();
}


// ============================================================
// DASHBOARD COUNTS
// ============================================================

function getTotalStudents() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM students
    ");

    return $stmt->fetch()['count'];
}

function getTotalCourses() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM courses
    ");

    return $stmt->fetch()['count'];
}

function getTotalSessions() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM sessions
    ");

    return $stmt->fetch()['count'];
}

function getTotalAttendance() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT COUNT(*) AS count
        FROM attendance
    ");

    return $stmt->fetch()['count'];
}


// ============================================================
// INPUT SANITIZATION
// ============================================================

function sanitize($input) {
    return htmlspecialchars(
        strip_tags(trim($input)),
        ENT_QUOTES,
        'UTF-8'
    );
}


// ============================================================
// GET SERVER LOCAL IP
// ============================================================

/**
 * Get the server's LAN IPv4 address.
 *
 * On your current Windows machine:
 *
 * Wi-Fi:
 * 192.168.223.125
 *
 * VMware:
 * 192.168.147.1
 * 192.168.188.1
 *
 * Virtual adapter:
 * 192.168.56.1
 *
 * The function tries to select a usable private LAN address
 * and avoids loopback/virtual adapter addresses where possible.
 */

function getLocalIP() {

    // --------------------------------------------------------
    // WINDOWS
    // --------------------------------------------------------

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {

        $output = shell_exec('ipconfig 2>nul');

        if ($output) {

            preg_match_all(
                '/IPv4 Address[.\s]*:\s*([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)/i',
                $output,
                $matches
            );

            if (!empty($matches[1])) {

                $ips = $matches[1];

                // ------------------------------------------------
                // First preference:
                // Your current Wi-Fi network
                // ------------------------------------------------

                foreach ($ips as $ip) {

                    if (strpos($ip, '192.168.223.') === 0) {
                        return $ip;
                    }
                }

                // ------------------------------------------------
                // Second preference:
                // Any normal private LAN IP
                // ------------------------------------------------

                foreach ($ips as $ip) {

                    // Ignore localhost
                    if ($ip === '127.0.0.1') {
                        continue;
                    }

                    // Ignore VMware/VirtualBox common ranges
                    if (
                        strpos($ip, '192.168.147.') === 0 ||
                        strpos($ip, '192.168.188.') === 0 ||
                        strpos($ip, '192.168.56.') === 0
                    ) {
                        continue;
                    }

                    // 192.168.x.x
                    if (strpos($ip, '192.168.') === 0) {
                        return $ip;
                    }

                    // 10.x.x.x
                    if (strpos($ip, '10.') === 0) {
                        return $ip;
                    }

                    // 172.16.x.x - 172.31.x.x
                    if (
                        preg_match(
                            '/^172\.(1[6-9]|2[0-9]|3[0-1])\./',
                            $ip
                        )
                    ) {
                        return $ip;
                    }
                }
            }
        }
    }

    // --------------------------------------------------------
    // LINUX / MAC
    // --------------------------------------------------------

    $output = shell_exec('hostname -I 2>/dev/null');

    if ($output) {

        $ips = preg_split('/\s+/', trim($output));

        foreach ($ips as $ip) {

            if (
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                ) &&
                $ip !== '127.0.0.1'
            ) {
                return $ip;
            }
        }
    }

    // --------------------------------------------------------
    // FALLBACK
    // --------------------------------------------------------

    $hostname = gethostname();

    if ($hostname) {

        $ip = gethostbyname($hostname);

        if (
            filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            ) &&
            $ip !== '127.0.0.1' &&
            $ip !== $hostname
        ) {
            return $ip;
        }
    }

    // Final fallback
    return 'localhost';
}


// ============================================================
// GET BASE URL
// ============================================================

function getBaseURL() {

    // Detect HTTP / HTTPS
    $protocol = (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== 'off'
    )
        ? 'https'
        : 'http';

    // Get LAN IP
    $ip = getLocalIP();

    // Detect Apache port
    $port = $_SERVER['SERVER_PORT'] ?? '80';

    // If Apache is running on a custom port
    if ($port != '80' && $port != '443') {

        return $protocol .
            '://' .
            $ip .
            ':' .
            $port .
            '/attendance/';
    }

    return $protocol .
        '://' .
        $ip .
        '/attendance/';
}


// ============================================================
// QR CODE URL
// ============================================================

function getQRUrl($session_code) {

    return getBaseURL() .
        'student/scan.php?code=' .
        urlencode($session_code);
}


// ============================================================
// SESSION ACTIVE CHECK
// ============================================================
function isSessionActive($session)
{
    if (!$session) {
        return false;
    }

    if (
        empty($session['session_date']) ||
        empty($session['start_time']) ||
        empty($session['end_time'])
    ) {
        return false;
    }

    $today = date('Y-m-d');
    $now   = date('H:i:s');

    // Session must be today
    if ($session['session_date'] !== $today) {
        return false;
    }

    // Current time must be within the session period
    return (
        $now >= $session['start_time'] &&
        $now <= $session['end_time']
    );
}
?>
