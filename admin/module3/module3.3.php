<?php
session_start();

// -----------------------------
// Simple role check (Admin-only)
// -----------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo "Forbidden: Admins only.";
    exit;
}

// -----------------------------
// DB Connection (PDO)
// -----------------------------
$DB_HOST = '127.0.0.1';
$DB_NAME = 'simulation_event_planning';
$DB_USER = 'root';
$DB_PASS = '';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    echo "DB Connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

// -----------------------------
// Create table if not exists (simple schema)
// -----------------------------
$pdo->exec("
CREATE TABLE IF NOT EXISTS injects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    exercise_id INT DEFAULT NULL,
    decision_points JSON DEFAULT NULL,
    schedule_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// -----------------------------
// CSRF token helper
// -----------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf_token'];

// -----------------------------
// Helper: sanitize (server-side)
// -----------------------------
function s($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// -----------------------------
// Handle POST actions: create, update, delete
// -----------------------------
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'] ?? '';

            if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $exercise_id = $_POST['exercise_id'] ?: null;

            // decision_points posted as JSON string from client
            $decision_points_json = $_POST['decision_points'] ?? '[]';

            // ---- validate schedule_json ----
            $schedule_json = trim($_POST['schedule_json'] ?? '');
            if ($schedule_json === '') {
                $schedule_json = null;
            } else {
                json_decode($schedule_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "Schedule JSON is invalid.";
                    $schedule_json = null;
                }
            }

            if ($title === '' || $description === '') $errors[] = "Title and description required.";

            if (!$errors) {
                $stmt = $pdo->prepare("INSERT INTO injects (title, description, exercise_id, decision_points, schedule_json) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $exercise_id, $decision_points_json, $schedule_json]);
                $success = "Inject created.";
            }

        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $exercise_id = $_POST['exercise_id'] ?: null;
            $decision_points_json = $_POST['decision_points'] ?? '[]';

            // ---- validate schedule_json ----
            $schedule_json = trim($_POST['schedule_json'] ?? '');
            if ($schedule_json === '') {
                $schedule_json = null;
            } else {
                json_decode($schedule_json);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = "Schedule JSON is invalid.";
                    $schedule_json = null;
                }
            }

            if ($id <= 0) $errors[] = "Invalid ID.";
            if ($title === '' || $description === '') $errors[] = "Title and description required.";

            if (!$errors) {
                $stmt = $pdo->prepare("UPDATE injects 
                  SET title = ?, description = ?, exercise_id = ?, decision_points = ?, schedule_json = ? 
                  WHERE id = ?");
                $stmt->execute([$title, $description, $exercise_id, $decision_points_json, $schedule_json, $id]);
                $success = "Inject updated.";
            }

            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) $errors[] = "Invalid ID to delete.";

                if (!$errors) {
                    $stmt = $pdo->prepare("DELETE FROM injects WHERE id = ?");
                    $stmt->execute([$id]);
                    $success = "Inject deleted.";
                }

            } else {
                $errors[] = "Unknown action.";
            }
        }
}


// -----------------------------
// Fetch current injects
// -----------------------------
$stmt = $pdo->query("SELECT * FROM injects ORDER BY created_at DESC");
$injects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// -----------------------------
// Minimal list of exercises (for linking) - in real app replace with exercises table
// -----------------------------
$exercises = [
    ['id' => 1, 'name' => 'Flood Response Drill'],
    ['id' => 2, 'name' => 'Earthquake Tabletop'],
    ['id' => 3, 'name' => 'Mass Casualty Simulation']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Post-Simulation Debriefing | Admin</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and layout */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb; /* Light background for the overall page */
        }
        /* The main content area that needs independent scrolling */
        /* Note: This max-height assumes a fixed header (h-16 or 64px) for the surrounding container */
        #main-content-scrollable {
            max-height: calc(100vh - 64px); 
            overflow-y: auto;
            -webkit-overflow-scrolling: touch; /* For smoother mobile scrolling */
        }
        /* Style for the active tab indicator */
        .tab-active {
            border-bottom: 3px solid blue; /* Emerald 500 */
            color: Blue;
            font-weight: 600;
        }
    </style>
</head>
<body class="flex min-h-screen">

      <?php include '../sidebar.php'; ?>
    <!--
        NOTE: The content below simulates the main area beside the sidebar.php content.
        The overall page layout (sidebar + main content) is assumed to be a flex container.
    -->

    <!-- Main Content Wrapper (Simulates the area next to the sidebar) -->
    <div class="flex-1">

        <!-- Fixed Header (Provides context and space for the main content to scroll) -->
        <header class="bg-white shadow-md h-16 flex items-center justify-between px-8 z-10 sticky top-0">
            <h1 class="text-2xl font-bold text-gray-800">Post-Simulation Debriefing</h1>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-500">Role: Admin</span>
                <img class="h-10 w-10 rounded-full object-cover" src="https://placehold.co/100x100/34D399/ffffff?text=AD" alt="Admin Profile">
            </div>
        </header>

        <!-- Scrollable Main Content Area -->
        <main id="main-content-scrollable" class="p-8">

            <section class="mb-8">
                <div class="bg-white p-6 rounded-xl shadow-lg border-t-4 border-blue-400">
                    <h2 class="text-xl font-semibold text-gray-700 mb-2">Purpose Summary: Driving Continuous Improvement</h2>
                    <p class="text-gray-600">
                        This administrative hub is used to **conduct structured review sessions** after each simulation. The primary goal is to discuss lessons learned, consolidate essential feedback from participating barangays, and manage the official approval process for all resulting improvement reports.
                    </p>
                </div>
            </section>

            <!-- Tab Navigation for Admin Functions -->
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <div class="flex border-b border-gray-200 mb-6 space-x-4">
                    <button class="px-4 py-2 text-lg transition duration-150 ease-in-out tab-active" data-tab="schedule">
                        1. Schedule Sessions
                    </button>
                    <button class="px-4 py-2 text-lg transition duration-150 ease-in-out" data-tab="feedback">
                        2. Record Barangay Feedback
                    </button>
                    <button class="px-4 py-2 text-lg transition duration-150 ease-in-out" data-tab="approval">
                        3. Approve Improvement Reports
                    </button>
                </div>

                <!-- Tab Content Containers -->

                <!-- 1. Schedule Debrief Sessions -->
                <div id="tab-schedule" class="tab-content">
                    <h3 class="text-2xl font-bold text-blue-500 mb-6">Schedule New Debrief Session</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div class="col-span-1 md:col-span-2 lg:col-span-1 p-6 bg-gray-50 rounded-lg shadow-inner">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">Session Details</h4>
                            <label for="simulation" class="block text-sm font-medium text-gray-700 mb-1">Target Simulation Event</label>
                            <select id="simulation" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 mb-4">
                                <option>Select Simulation (e.g., QC Flood Drill 2025)</option>
                                <option>QC Fire Incident Simulation 2025</option>
                                <option>Typhoon Ulysses Response Drill</option>
                            </select>

                            <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" id="date" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 mb-4">

                            <label for="time" class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                            <input type="time" id="time" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500 mb-4">

                            <label for="location" class="block text-sm font-medium text-gray-700 mb-1">Location / Platform</label>
                            <input type="text" id="location" placeholder="e.g., QC Hall Multipurpose Room / Google Meet Link" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-emerald-500 focus:border-emerald-500">
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <h4 class="text-lg font-semibold text-gray-700 mb-3">Required Attendees (Barangays & Personnel)</h4>
                            <div class="h-64 overflow-y-auto border border-gray-200 p-4 rounded-lg bg-white shadow-sm">
                                <p class="text-sm font-medium text-gray-600 mb-2">Select Participating Barangay Representatives:</p>
                                <div class="space-y-2">
                                    <!-- Dynamic list of Barangays - placeholder content -->
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-emerald-500">
                                        <span class="text-gray-700">Brgy. Holy Spirit (District 2)</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-emerald-500">
                                        <span class="text-gray-700">Brgy. Loyola Heights (District 3)</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-emerald-500">
                                        <span class="text-gray-700">Brgy. Central (District 4)</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-emerald-500">
                                        <span class="text-gray-700">Brgy. Fairview (District 5)</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-emerald-500">
                                        <span class="text-gray-700">Brgy. Tandang Sora (District 6)</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-4">...and 137 other Barangays</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button onclick="scheduleDebrief()" class="mt-6 w-full md:w-auto px-6 py-3 bg-emerald-600 text-white font-semibold rounded-lg shadow-lg hover:bg-emerald-700 transition duration-300">
                        <svg class="inline w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Finalize & Send Schedule
                    </button>
                    <div id="schedule-message" class="mt-3 text-sm font-medium text-gray-500"></div>
                </div>

                <!-- 2. Record Barangay Feedback -->
                <div id="tab-feedback" class="tab-content hidden">
                    <h3 class="text-2xl font-bold text-blue-600 mb-6">Record Detailed Barangay Feedback</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="col-span-1">
                            <label for="session-select" class="block text-sm font-medium text-gray-700 mb-1">Select Debrief Session</label>
                            <select id="session-select" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 mb-4">
                                <option>QC Flood Drill 2025 Debrief (Oct 15, 2025)</option>
                                <option>QC Fire Incident Simulation Debrief (Nov 5, 2025)</option>
                            </select>

                            <label for="barangay-select" class="block text-sm font-medium text-gray-700 mb-1">Select Reporting Barangay</label>
                            <select id="barangay-select" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 mb-4">
                                <option>Brgy. Commonwealth</option>
                                <option>Brgy. Payatas</option>
                                <option>Brgy. Matandang Balara</option>
                            </select>

                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-lg">
                                <p class="font-semibold text-blue-800">Observation Guide:</p>
                                <ul class="list-disc list-inside text-sm text-blue-700 mt-1">
                                    <li>Action Plan Efficacy (1-5 Rating)</li>
                                    <li>Communication Flow (Clear/Confused)</li>
                                    <li>Resource Utilization (Efficient/Lacking)</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-span-1 md:col-span-2">
                            <label for="key-lessons" class="block text-sm font-medium text-gray-700 mb-1">Key Lessons Learned / Strengths Observed</label>
                            <textarea id="key-lessons" rows="4" placeholder="Document the barangay's identified strengths and key takeaways." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 mb-4"></textarea>

                            <label for="improvement-areas" class="block text-sm font-medium text-gray-700 mb-1">Critical Improvement Areas / Weaknesses</label>
                            <textarea id="improvement-areas" rows="4" placeholder="List specific weaknesses in their decision points or execution plan." class="w-full p-3 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 mb-4"></textarea>

                            <button onclick="recordFeedback()" class="w-full px-6 py-3 bg-blue-600 text-white font-semibold rounded-lg shadow-lg hover:bg-blue-700 transition duration-300">
                                <svg class="inline w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Save Barangay Feedback
                            </button>
                            <div id="feedback-message" class="mt-3 text-sm font-medium text-gray-500"></div>
                        </div>
                    </div>
                </div>

                <!-- 3. Approve Improvement Reports -->
                <div id="tab-approval" class="tab-content hidden">
                    <h3 class="text-2xl font-bold text-red-600 mb-6">Improvement Reports Awaiting Approval</h3>

                    <div class="mb-4 bg-red-50 p-3 rounded-lg border border-red-200">
                        <p class="text-sm text-red-700 font-medium">
                            Reports require admin review to officially validate and integrate recommended changes into future simulation protocols.
                        </p>
                    </div>

                    <!-- Report List Table -->
                    <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-md">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Barangay</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Simulation</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted On</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <!-- Report 1: Needs Review -->
                                <tr class="hover:bg-red-50/50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Brgy. Loyola Heights</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">QC Fire Incident Simulation</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-11-08</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Awaiting Review
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                        <button onclick="viewReport('Loyola')" class="text-indigo-600 hover:text-indigo-900 transition">View Details</button>
                                        <button onclick="approveReport('Loyola')" class="text-green-600 hover:text-green-900 transition">Approve</button>
                                        <button onclick="rejectReport('Loyola')" class="text-red-600 hover:text-red-900 transition">Reject</button>
                                    </td>
                                </tr>
                                <!-- Report 2: Needs Review -->
                                <tr class="hover:bg-red-50/50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Brgy. Fairview</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Typhoon Ulysses Response Drill</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-11-09</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                            Awaiting Review
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                        <button onclick="viewReport('Fairview')" class="text-indigo-600 hover:text-indigo-900 transition">View Details</button>
                                        <button onclick="approveReport('Fairview')" class="text-green-600 hover:text-green-900 transition">Approve</button>
                                        <button onclick="rejectReport('Fairview')" class="text-red-600 hover:text-red-900 transition">Reject</button>
                                    </td>
                                </tr>
                                <!-- Report 3: Approved (Example for completeness) -->
                                <tr class="bg-green-50/50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Brgy. Central</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">QC Flood Drill 2025</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-10-25</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Approved
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                        <button onclick="viewReport('Central')" class="text-indigo-600 hover:text-indigo-900 transition">View Details</button>
                                        <span class="text-gray-400">Completed</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="approval-message" class="mt-3 text-sm font-medium text-gray-500"></div>
                </div>

            </div>

        </main>
    </div>

    <!-- JavaScript for Tab Switching and Admin Functions -->
    <script>
        // Tab switching logic
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('[data-tab]');
            const contents = document.querySelectorAll('.tab-content');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    // Remove active class from all tabs and hide all content
                    tabs.forEach(t => t.classList.remove('tab-active'));
                    contents.forEach(c => c.classList.add('hidden'));

                    // Add active class to clicked tab
                    tab.classList.add('tab-active');

                    // Show corresponding content
                    const targetId = `tab-${tab.getAttribute('data-tab')}`;
                    document.getElementById(targetId).classList.remove('hidden');
                });
            });
        });

        // --- Admin Functions (Placeholder for API Integration) ---
        // Context: 'simulation_event_planning' database connection/API calls

        // Function 1: Schedule debrief sessions
        function scheduleDebrief() {
            const simulation = document.getElementById('simulation').value;
            const date = document.getElementById('date').value;
            const time = document.getElementById('time').value;
            const location = document.getElementById('location').value;
            const messageElement = document.getElementById('schedule-message');

            if (!simulation || !date || !time || !location || simulation.includes('Select Simulation')) {
                messageElement.textContent = 'Please fill in all session details.';
                messageElement.classList.add('text-red-500');
                return;
            }

            // --- API Simulation: Data would be sent to Firestore 'debrief_sessions' collection. ---
            messageElement.classList.remove('text-red-500');
            messageElement.classList.add('text-green-500');
            messageElement.innerHTML = `
                <div class="p-3 bg-green-100 border border-green-400 rounded-lg mt-3">
                    <p class="font-bold">Scheduled Successfully!</p>
                    <p class="text-sm">Session for "${simulation}" on ${date} at ${time} at ${location} has been scheduled and invitations sent.</p>
                </div>
            `;
            // Reset form fields
            document.getElementById('simulation').value = 'Select Simulation (e.g., QC Flood Drill 2025)';
            document.getElementById('date').value = '';
            document.getElementById('time').value = '';
            document.getElementById('location').value = '';
        }

        // Function 2: Record barangay feedback
        function recordFeedback() {
            const session = document.getElementById('session-select').value;
            const barangay = document.getElementById('barangay-select').value;
            const keyLessons = document.getElementById('key-lessons').value;
            const improvementAreas = document.getElementById('improvement-areas').value;
            const messageElement = document.getElementById('feedback-message');

            if (!keyLessons || !improvementAreas) {
                messageElement.textContent = 'Please document both key lessons and improvement areas.';
                messageElement.classList.add('text-red-500');
                return;
            }

            // --- API Simulation: Data would be sent to Firestore 'barangay_feedback' collection. ---
            messageElement.classList.remove('text-red-500');
            messageElement.classList.add('text-green-500');
            messageElement.innerHTML = `
                <div class="p-3 bg-green-100 border border-green-400 rounded-lg mt-3">
                    <p class="font-bold">Feedback Recorded!</p>
                    <p class="text-sm">Feedback for ${barangay} from the ${session} debrief has been saved.</p>
                </div>
            `;
            // Reset form fields
            document.getElementById('key-lessons').value = '';
            document.getElementById('improvement-areas').value = '';
        }

        // Function 3: Approve improvement reports (Uses custom modal logic instead of alert())
        function viewReport(barangay) {
            // NOTE: In a production app, this would trigger a modal to show the report content.
            // Using a log message here since real modals are complex to implement in a single file without libraries.
            console.log(`VIEWING Report Details for ${barangay}.`);
            
            // Temporary visual feedback
            const tempMessage = document.getElementById('approval-message');
            tempMessage.classList.remove('text-red-500', 'text-green-500');
            tempMessage.classList.add('text-indigo-500');
            tempMessage.innerHTML = `<div class="p-3 bg-indigo-100 border border-indigo-400 rounded-lg mt-3">Viewing details for ${barangay}'s Improvement Report (Check console for simulated data retrieval).</div>`;
            setTimeout(() => tempMessage.innerHTML = '', 3000);
        }

        function approveReport(barangay) {
            const messageElement = document.getElementById('approval-message');
            // --- API Simulation: Would involve an updateDoc to 'improvement_reports' setting status to 'Approved' ---
            messageElement.classList.remove('text-red-500', 'text-yellow-500');
            messageElement.classList.add('text-green-500');
            messageElement.innerHTML = `
                <div class="p-3 bg-green-100 border border-green-400 rounded-lg mt-3">
                    <p class="font-bold">Report Approved!</p>
                    <p class="text-sm">The Improvement Report for ${barangay} has been officially approved. Status updated for dissemination.</p>
                </div>
            `;
            // Simulate UI update
            const row = event.target.closest('tr');
            if(row) {
                row.classList.remove('hover:bg-red-50/50');
                row.classList.add('bg-green-50/50');
                const statusCell = row.querySelector('.bg-yellow-100');
                if(statusCell) {
                    statusCell.textContent = 'Approved';
                    statusCell.classList.remove('bg-yellow-100', 'text-yellow-800');
                    statusCell.classList.add('bg-green-100', 'text-green-800');
                    // Disable action buttons after approval
                    const actionsCell = row.querySelector('.space-x-2');
                    if (actionsCell) actionsCell.innerHTML = `<button onclick="viewReport('${barangay}')" class="text-indigo-600 hover:text-indigo-900 transition">View Details</button><span class="text-gray-400 ml-2">Completed</span>`;
                }
            }
        }

        function rejectReport(barangay) {
            const messageElement = document.getElementById('approval-message');
            // --- API Simulation: Would involve an updateDoc to 'improvement_reports' setting status to 'Rejected' ---
            messageElement.classList.remove('text-green-500', 'text-yellow-500');
            messageElement.classList.add('text-red-500');
            messageElement.innerHTML = `
                <div class="p-3 bg-red-100 border border-red-400 rounded-lg mt-3">
                    <p class="font-bold">Report Rejected!</p>
                    <p class="text-sm">The Improvement Report for ${barangay} has been rejected. The barangay must be notified to resubmit a revised report.</p>
                </div>
            `;
            // In a real application, you would log the reason for rejection here.
        }
    </script>
</body>
</html>