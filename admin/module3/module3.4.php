<?php
session_start();

// --- SECURITY CHECK ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// --- DB CONNECTION ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // change to your DB name
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// --- CSRF TOKEN ---
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$CSRF = $_SESSION['csrf'];

// --- Handle Form Submission ---
$errors = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $type = trim($_POST['type'] ?? '');

        if ($title === '' || $type === '') {
            $errors[] = "Title and type are required.";
        }

        if (!$errors) {
            $stmt = $pdo->prepare("INSERT INTO debriefing_materials (title, type, status) VALUES (?, ?, 'Pending')");
            $stmt->execute([$title, $type]);
            $success = "New material added successfully.";
        }
    }
}

// --- Fetch Existing Materials ---
$stmt = $pdo->query("SELECT * FROM debriefing_materials ORDER BY id DESC");
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

function s($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
include '../sidebar.php'; 

// --- Placeholder Data Simulation for Reports and Analytics ---

$scenarioReports = [
    ['id' => 101, 'scenario' => 'Tsunami Evacuation Drill', 'event_date' => '2025-10-01', 'success_rate' => 92, 'response_time' => '12:35 min', 'lead_evaluator' => 'Jane Doe', 'status' => 'Approved'],
    ['id' => 102, 'scenario' => 'Chemical Spill Containment', 'event_date' => '2025-09-15', 'success_rate' => 78, 'response_time' => '08:10 min', 'lead_evaluator' => 'John Smith', 'status' => 'Pending Review'],
    ['id' => 103, 'scenario' => 'Mass Casualty Triage', 'event_date' => '2025-08-22', 'success_rate' => 85, 'response_time' => '15:40 min', 'lead_evaluator' => 'Jane Doe', 'status' => 'Completed'],
];

// Mock data for performance charts
$performanceMetrics = [
    'Tsunami Evacuation Drill' => ['avg_time' => 13.0, 'target_time' => 10.0, 'success' => 92],
    'Chemical Spill Containment' => ['avg_time' => 9.5, 'target_time' => 7.0, 'success' => 78],
    'Mass Casualty Triage' => ['avg_time' => 16.0, 'target_time' => 15.0, 'success' => 85],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scenario Evaluation Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background */
        }
        /* Define the main content area to take full viewport height minus sidebar, with independent scrolling */
        .h-screen-main {
            min-height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
        .h-screen-main::-webkit-scrollbar {
            width: 8px;
        }
        .h-screen-main::-webkit-scrollbar-thumb {
            background-color: #a0aec0; /* Gray-500 */
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-gray-50 flex">

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-screen-main p-8 space-y-10">

        <!-- PAGE HEADER -->
        <header class="pb-4 border-b border-gray-200">
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Scenario Evaluation Reports</h1>
            <p class="text-lg text-gray-600 mt-2">
                Analyze and document performance against objectives for all past simulation scenarios.
                (Database: <span class="font-mono text-sm bg-gray-200 p-1 rounded">simulation_event_planning</span>)
            </p>
        </header>

        <!-- ============================= -->
        <!-- SCENARIO ANALYTICS DASHBOARD (ADMIN FUNCTION) -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl shadow-indigo-100 border border-indigo-100">
            <h2 class="text-2xl font-bold text-indigo-700 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path></svg>
                Performance Analytics & Comparison
            </h2>

            <!-- Tab Navigation for Admin Functions -->
            <div class="flex border-b border-gray-200 mb-6">
                <button onclick="switchTab('analytics')" id="tab-analytics" class="tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg transition duration-200 text-white bg-indigo-600">
                    Scenario Performance Overview
                </button>
                <button onclick="switchTab('comparison')" id="tab-comparison" class="tab-btn px-4 py-2 text-sm font-semibold rounded-t-lg text-gray-600 hover:text-indigo-600 hover:bg-gray-50 ml-2">
                    Historical Comparison
                </button>
            </div>

            <!-- TAB CONTENT: Performance Analytics -->
            <div id="content-analytics" class="tab-content space-y-6">
                <p class="text-gray-600">View key performance indicators (KPIs) and efficiency metrics for recent high-priority scenarios.</p>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($performanceMetrics as $scenario => $metrics): ?>
                    <div class="p-5 bg-white border border-gray-200 rounded-xl shadow-lg">
                        <p class="text-lg font-bold text-indigo-800 mb-2"><?= $scenario ?></p>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm text-gray-700">
                                <span>Avg. Response Time:</span>
                                <span class="font-bold text-lg text-red-600"><?= $metrics['avg_time'] ?> min</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-700">
                                <span>Target Time:</span>
                                <span class="font-medium text-green-600"><?= $metrics['target_time'] ?> min</span>
                            </div>
                            <div class="flex justify-between text-sm text-gray-700 pt-2 border-t border-gray-100">
                                <span>Success Rate:</span>
                                <span class="font-bold text-xl text-indigo-600"><?= $metrics['success'] ?>%</span>
                            </div>
                        </div>
                        <div class="mt-4 h-1 bg-gray-200 rounded-full">
                            <div class="h-1 bg-indigo-500 rounded-full" style="width: <?= $metrics['success'] ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- TAB CONTENT: Historical Comparison -->
            <div id="content-comparison" class="tab-content hidden space-y-6">
                <p class="text-gray-600">Select two scenarios or two dates for the same scenario to analyze performance trends and efficiency drift over time.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-gray-50 rounded-lg border">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Scenario 1 / Date</label>
                        <select class="w-full p-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <option>Tsunami Drill (Oct 2025)</option>
                            <option>Tsunami Drill (Mar 2025)</option>
                        </select>
                    </div>
                    <div class="flex items-center justify-center text-xl font-bold text-gray-500">
                        VS
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Scenario 2 / Date</label>
                        <select class="w-full p-2 border rounded-lg focus:ring-indigo-500 focus:border-indigo-500">
                            <option>Chemical Spill (Sep 2025)</option>
                            <option>Mass Casualty (Aug 2025)</option>
                        </select>
                    </div>
                </div>

                <div class="p-6 bg-yellow-50 text-yellow-800 rounded-xl border border-yellow-200 text-sm">
                    <p class="font-semibold">Comparison Chart Placeholder</p>
                    <p>A time-series chart would be rendered here using a library like Chart.js or D3.js to visualize the difference in Response Time, Resource Consumption, and Success Rate between the selected scenarios.</p>
                </div>
                
            </div>
        </div>

        <!-- ============================= -->
        <!-- SUBMIT NEW SCENARIO REPORT -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Document New Evaluation Report
            </h2>

            <form action="report_submit.php" method="POST" enctype="multipart/form-data" class="space-y-6">

                <!-- Basic Details -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Scenario Name</label>
                        <input type="text" name="scenario_name" required placeholder="e.g., Earthquake Response 2025"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Evaluation Date</label>
                        <input type="date" name="evaluation_date" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Lead Evaluator</label>
                        <input type="text" name="lead_evaluator" required placeholder="Name of Admin/Observer"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-4 bg-teal-50 rounded-xl border border-teal-200">
                    <div>
                        <label class="block text-sm font-semibold text-teal-700 mb-2">Success Rate (%)</label>
                        <input type="number" name="success_rate" required min="0" max="100" placeholder="e.g., 95"
                            class="w-full px-4 py-3 rounded-xl border border-teal-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-teal-700 mb-2">Critical Response Time (HH:MM:SS)</label>
                        <input type="text" name="response_time" required placeholder="e.g., 00:10:45"
                            class="w-full px-4 py-3 rounded-xl border border-teal-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-teal-700 mb-2">Resource Utilization (%)</label>
                        <input type="number" name="resource_util" required min="0" max="100" placeholder="e.g., 80"
                            class="w-full px-4 py-3 rounded-xl border border-teal-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm">
                    </div>
                </div>

                <!-- Detailed Observations -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Detailed Observations & Recommendations</label>
                    <textarea name="observations" rows="6"
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-teal-200 focus:border-teal-500 shadow-sm"
                        placeholder="Document areas of excellence, identified failures, and corrective actions recommended for the next drill."></textarea>
                </div>

                <!-- PICTURE UPLOAD -->
                <div class="p-6 border border-red-200 bg-red-50 rounded-xl">
                    <label class="block text-sm font-semibold text-red-700 mb-3 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        Insert Scenario Pictures (Evidence)
                    </label>
                    <input type="file" name="scenario_pictures[]" multiple 
                        class="block w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-red-100 file:text-red-700
                        hover:file:bg-red-200"
                    >
                    <p class="text-xs text-red-600 mt-2">
                        Upload images captured during the event for visual documentation. Max 5 files (JPG/PNG).
                    </p>
                </div>

                <!-- SUBMIT -->
                <div class="flex justify-end pt-4">
                    <button type="submit"
                        class="flex items-center px-8 py-3 bg-teal-600 text-white font-bold text-lg rounded-xl shadow-lg shadow-teal-300 hover:bg-teal-700 transition transform hover:scale-[1.02]">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Finalize & Submit Report
                    </button>
                </div>

            </form>
        </div>

        <!-- ============================= -->
        <!-- REPORT LIST TABLE -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Current Evaluation Reports
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-inner">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tl-xl">Scenario</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Date</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Facilitator</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Success Rate</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Response Time</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php 
                        foreach ($scenarioReports as $r): 
                        ?>
                        <tr class="hover:bg-blue-50/50 transition duration-150">
                            <td class="p-4 text-sm font-medium text-gray-900"><?= $r['scenario'] ?></td>
                            <td class="p-4 text-sm text-gray-600"><?= date("M d, Y", strtotime($r['event_date'])) ?></td>
                            <td class="p-4 text-sm text-gray-600"><?= $r['lead_evaluator'] ?></td>
                            <td class="p-4 text-sm font-semibold text-indigo-600"><?= $r['success_rate'] ?>%</td>
                            <td class="p-4 text-sm font-medium text-red-600"><?= $r['response_time'] ?></td>

                            <td class="p-4 text-sm">
                                <?php
                                $status = $r['status'];
                                $badgeClass = match($status) {
                                    'Approved' => 'bg-green-100 text-green-800 border-green-300',
                                    'Pending Review' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'Completed' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    default => 'bg-gray-100 text-gray-700 border-gray-300'
                                };
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td class="p-4 text-center text-sm font-medium space-x-3">
                                <button class="text-blue-600 hover:text-blue-800 transition">View Full</button>
                                <span class="text-gray-300">|</span>
                                <button class="text-gray-600 hover:text-red-800 transition">Delete</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<script>
    // Tab switching logic for the Admin Analytics section
    function switchTab(tabName) {
        // Hide all content tabs
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });
        // Deactivate all tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'hover:bg-gray-50');
            btn.classList.add('text-gray-600', 'hover:text-indigo-600', 'hover:bg-gray-50');
        });

        // Show the selected content tab
        document.getElementById('content-' + tabName).classList.remove('hidden');
        
        // Activate the selected button
        const activeBtn = document.getElementById('tab-' + tabName);
        activeBtn.classList.remove('text-gray-600', 'hover:text-indigo-600', 'hover:bg-gray-50');
        activeBtn.classList.add('bg-indigo-600', 'text-white');
    }

    // Initialize the first tab as active when the page loads
    document.addEventListener('DOMContentLoaded', () => {
        // Since we explicitly set 'analytics' as the default active tab in PHP
        // we can skip calling switchTab('analytics') here, but this is a reminder
        // for more complex clientside rendering. The current static PHP setup is fine.
    });
</script>
</body>
</html>