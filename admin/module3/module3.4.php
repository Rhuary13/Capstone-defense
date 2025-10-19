<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// CREATE DATABASE TABLES IF THEY DON'T EXIST
// =========================

// Create 'events' table
$sql_create_events_table = "
CREATE TABLE IF NOT EXISTS `events` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `duration` INT(11) NOT NULL,
    `location` VARCHAR(255) NOT NULL,
    `location_lat` DECIMAL(10, 8) NULL,
    `location_lng` DECIMAL(11, 8) NULL,
    `facilitator` VARCHAR(255) DEFAULT NULL,
    `notes` TEXT DEFAULT NULL,
    `approval_status` ENUM('Pending','Approved') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `approved_at` TIMESTAMP NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_events_table)) {
    die("Error creating events table: " . $conn->error);
}

// Create 'staff' table
$sql_create_staff_table = "
CREATE TABLE IF NOT EXISTS `staff` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_staff_table)) {
    die("Error creating staff table: " . $conn->error);
}

// Create 'participants' table
$sql_create_participants_table = "
CREATE TABLE IF NOT EXISTS `participants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('participant') NOT NULL DEFAULT 'participant'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_participants_table)) {
    die("Error creating participants table: " . $conn->error);
}

// Create 'attendance_staff' table
$sql_create_staff_attendance_table = "
CREATE TABLE IF NOT EXISTS `attendance_staff` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `staff_id` INT(11) NOT NULL,
    `event_id` INT(11) NOT NULL,
    `status` ENUM('Present', 'Absent') NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`staff_id`) REFERENCES `staff`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_staff_attendance_table)) {
    die("Error creating attendance_staff table: " . $conn->error);
}

// Create 'attendance_participants' table
$sql_create_participant_attendance_table = "
CREATE TABLE IF NOT EXISTS `attendance_participants` (
    `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `participant_id` INT(11) NOT NULL,
    `event_id` INT(11) NOT NULL,
    `status` ENUM('Present', 'Absent') NOT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`participant_id`) REFERENCES `participants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`event_id`) REFERENCES `events`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($sql_create_participant_attendance_table)) {
    die("Error creating attendance_participants table: " . $conn->error);
}

// ----------------------
// AUTHENTICATION & ROLE CHECK
// ----------------------
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

$user_role = $_SESSION['role'] ?? 'participant';
$user_id = $_SESSION['id'];

// ----------------------
// FETCH DATA FOR ADMIN
// ----------------------
$staff_reports = [];
$events_list = [];
$event_types = ['Training', 'Simulation', 'Event Program'];

if ($user_role === 'admin') {
    // Fetch list of all events for the filter dropdown
    $events_res = $conn->query("SELECT id, title, type FROM events ORDER BY date DESC");
    if ($events_res) {
        while ($row = $events_res->fetch_assoc()) {
            $events_list[] = $row;
        }
        $events_res->free();
    }

    // Base query to fetch all staff attendance
    $sql_staff_report = "
        SELECT 
            s.name, 
            e.title, 
            e.date, 
            e.type,
            sa.status 
        FROM 
            attendance_staff sa
        JOIN 
            staff s ON sa.staff_id = s.id
        JOIN 
            events e ON sa.event_id = e.id
    ";

    $where_clauses = [];
    $bind_types = "";
    $bind_params = [];

    // Add filter by event if selected
    if (isset($_GET['event_id']) && $_GET['event_id'] !== '') {
        $where_clauses[] = "sa.event_id = ?";
        $bind_types .= "i";
        $bind_params[] = (int)$_GET['event_id'];
    }

    // Add filter by event type if selected
    if (isset($_GET['event_type']) && $_GET['event_type'] !== '') {
        $where_clauses[] = "e.type = ?";
        $bind_types .= "s";
        $bind_params[] = $_GET['event_type'];
    }

    if (!empty($where_clauses)) {
        $sql_staff_report .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $stmt = $conn->prepare($sql_staff_report);
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $staff_reports[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

// ----------------------
// FETCH DATA FOR PARTICIPANT
// ----------------------
$participant_history = [];
if ($user_role === 'participant') {
    $sql_participant_history = "
        SELECT
            e.title,
            e.date,
            e.time,
            e.type,
            ap.status
        FROM
            attendance_participants ap
        JOIN
            events e ON ap.event_id = e.id
        WHERE
            ap.participant_id = ?
        ORDER BY
            e.date DESC;
    ";
    
    $stmt = $conn->prepare($sql_participant_history);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $participant_history[] = $row;
        }
        $result->free();
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden">

<aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
</aside>

<main class="flex-1 h-full overflow-y-auto p-8 bg-gray-100 pt-20">
    <div class="bg-white p-6 rounded-xl shadow mb-8">
        <?php if ($user_role === 'admin'): ?>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-4">
                <i data-lucide="clipboard-list" class="w-8 h-8 text-blue-600"></i>
                Staff Attendance Reports
            </h2>
            
            <form method="GET" class="flex flex-col sm:flex-row gap-4 mb-4 items-center">
                <label for="event-select" class="block text-sm font-medium text-gray-700">Filter by Event:</label>
                <select id="event-select" name="event_id" class="px-3 py-2 border rounded-lg">
                    <option value="">All Events</option>
                    <?php foreach ($events_list as $event): ?>
                        <option value="<?= $event['id'] ?>" <?= (isset($_GET['event_id']) && $_GET['event_id'] == $event['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($event['title']) ?> (<?= htmlspecialchars($event['type']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="type-select" class="block text-sm font-medium text-gray-700 sm:ml-4">Filter by Type:</label>
                <select id="type-select" name="event_type" class="px-3 py-2 border rounded-lg">
                    <option value="">All Types</option>
                    <?php foreach ($event_types as $type): ?>
                        <option value="<?= htmlspecialchars($type) ?>" <?= (isset($_GET['event_type']) && $_GET['event_type'] == $type) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 mt-2 sm:mt-0">Apply Filter</button>
            </form>
            
            <button onclick="exportTableToCSV('staff_report.csv')" class="px-4 py-2 mb-4 bg-green-600 text-white rounded-lg hover:bg-green-700">
                <i data-lucide="file-text" class="inline-block w-4 h-4 mr-2"></i>Export to CSV
            </button>

            <div class="overflow-x-auto">
                <table id="staff-table" class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3">Staff Name</th>
                            <th class="p-3">Event Title</th>
                            <th class="p-3">Event Type</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staff_reports)): ?>
                            <tr>
                                <td colspan="5" class="p-3 text-center text-gray-500">No staff attendance records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($staff_reports as $report): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?= htmlspecialchars($report['name']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($report['title']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($report['type']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($report['date']) ?></td>
                                    <td class="p-3">
                                        <span class="<?= $report['status'] === 'Present' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= htmlspecialchars($report['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($user_role === 'participant'): ?>
            <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-2 mb-4">
                <i data-lucide="history" class="w-8 h-8 text-blue-600"></i>
                Your Attendance History
            </h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-3">Event Title</th>
                            <th class="p-3">Event Type</th>
                            <th class="p-3">Date</th>
                            <th class="p-3">Time</th>
                            <th class="p-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($participant_history)): ?>
                            <tr>
                                <td colspan="5" class="p-3 text-center text-gray-500">No attendance history found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($participant_history as $history): ?>
                                <tr class="border-b">
                                    <td class="p-3"><?= htmlspecialchars($history['title']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($history['type']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($history['date']) ?></td>
                                    <td class="p-3"><?= htmlspecialchars($history['time']) ?></td>
                                    <td class="p-3">
                                        <span class="<?= $history['status'] === 'Present' ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= htmlspecialchars($history['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
             <div class="p-4 text-red-800 bg-red-100 border border-red-300 rounded-lg">
                 Access Denied. You do not have permission to view this page.
             </div>
        <?php endif; ?>
    </div>
</main>
<script>
    // Initialize Lucide icons
    lucide.createIcons();

    function exportTableToCSV(filename) {
        let csv = [];
        const rows = document.querySelectorAll("#staff-table tr");
        
        for (const row of rows) {
            const cols = row.querySelectorAll("th, td");
            const rowData = [];
            for (const col of cols) {
                rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
            }
            csv.push(rowData.join(","));
        }

        // Create CSV file
        const csvFile = new Blob([csv.join("\n")], { type: "text/csv" });
        
        // Create a temporary link to download the file
        const downloadLink = document.createElement("a");
        downloadLink.download = filename;
        downloadLink.href = window.URL.createObjectURL(csvFile);
        downloadLink.style.display = "none";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    }
</script>
</body>
</html>