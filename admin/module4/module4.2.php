<?php
// module5.2.php - Data Collection & Mapping (Scoring Management)
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // Database name used for connection
$dbName = $db; // FIX: Assign $db to $dbName for UI display consistency
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// =========================
// Only Admin Access
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// =========================
// Handle Form Submission (for data_criteria - preserved existing file logic)
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scenario = $_POST['scenario'];
    $criterion = $_POST['criterion'];
    $objective = $_POST['objective'];

    $stmt = $pdo->prepare("INSERT INTO data_criteria (scenario, criterion, objective) VALUES (?, ?, ?)");
    $stmt->execute([$scenario, $criterion, $objective]);

    $message = "New Data Criterion Added Successfully!";
}

// =========================
// Fetch Existing Records
// =========================
$criteria = $pdo->query("SELECT * FROM data_criteria ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// MOCK DATA FOR SCORING UI
$rubrics = [
    ['id' => 1, 'title' => 'Tsunami Response Protocol 1.1', 'status' => 'Approved', 'criteria_count' => 5, 'created_by' => 'Jane Doe', 'updated_at' => '2025-11-20'],
    ['id' => 2, 'title' => 'Fire Emergency Evacuation V2', 'status' => 'Pending Review', 'criteria_count' => 3, 'created_by' => 'John Smith', 'updated_at' => '2025-11-28'],
    ['id' => 3, 'title' => 'Mass Casualty Triage Guide', 'status' => 'Draft', 'criteria_count' => 7, 'created_by' => 'Jane Doe', 'updated_at' => '2025-11-25'],
    ['id' => 4, 'title' => 'Cyber Attack Initial Assessment', 'status' => 'Approved', 'criteria_count' => 4, 'created_by' => 'Admin', 'updated_at' => '2025-10-15'],
];

// Mock Event Type Data (Core Function: Assign rubrics to event types)
$eventTypes = [
    ['id' => 10, 'name' => 'Tsunami Drill Simulation', 'assigned_rubric_id' => 1, 'assigned_rubric_title' => 'Tsunami Response Protocol 1.1'],
    ['id' => 11, 'name' => 'Structure Fire Scenario', 'assigned_rubric_id' => 2, 'assigned_rubric_title' => 'Fire Emergency Evacuation V2'],
    ['id' => 12, 'name' => 'Vehicle Accident Triage', 'assigned_rubric_id' => 3, 'assigned_rubric_title' => 'Mass Casualty Triage Guide'],
    ['id' => 13, 'name' => 'Infrastructure Failure Response', 'assigned_rubric_id' => null, 'assigned_rubric_title' => 'NOT ASSIGNED'],
];

// Calculate summary stats
$totalRubrics = count($rubrics);
$approvedRubrics = count(array_filter($rubrics, fn($r) => $r['status'] === 'Approved'));
$pendingRubrics = count(array_filter($rubrics, fn($r) => $r['status'] === 'Pending Review'));
$unassignedEvents = count(array_filter($eventTypes, fn($e) => $e['assigned_rubric_id'] === null));

// Sidebar inclusion (must be included before the HTML starts)
include '../sidebar.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scoring Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Tailwind slate-50 */
        }
        /* Define the main content area for independent scrolling */
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
            background-color: #e2e8f0; /* Tailwind slate-200 */
            border-radius: 10px;
        }
    </style>
</head>

<body class="bg-slate-50 flex">

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-screen-main p-4 sm:p-8 space-y-10">

        <!-- PAGE HEADER AND PRIMARY ACTION -->
        <header class="pb-4 border-b-2 border-indigo-200 flex justify-between items-start">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">
                    <span class="text-indigo-600">Scoring</span> Management
                </h1>
                <p class="text-lg text-gray-600 mt-2">
                    Maintain standardized parameters for scoring participants or teams by managing rubrics and assignments.
                </p>
                <!-- FIX APPLIED HERE: Using $dbName, which is now explicitly defined and checking for existence -->
                <span class="font-mono text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded mt-2 inline-block">Admin View: <?= isset($dbName) ? $dbName : 'Database N/A' ?></span>
            </div>
            
            <button class="bg-indigo-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-indigo-700 transition shadow-lg shadow-indigo-300/60" onclick="showCreateRubricModal()">
                + Create New Rubric
            </button>
        </header>

        <!-- ============================================== -->
        <!-- SECTION 1: KEY PERFORMANCE INDICATORS (KPIs) -->
        <!-- ============================================== -->
        <h2 class="text-2xl font-bold text-indigo-700">Oversight Dashboard</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <!-- Card 1: Total Rubrics -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-l-4 border-indigo-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-indigo-700 uppercase tracking-widest">Total Rubrics Created</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $totalRubrics ?></p>
            </div>

            <!-- Card 2: Approved Rubrics -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-l-4 border-green-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-green-700 uppercase tracking-widest">Currently Approved Rubrics</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $approvedRubrics ?></p>
            </div>

            <!-- Card 3: Pending Approval -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-l-4 border-yellow-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-yellow-700 uppercase tracking-widest">Pending Review</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $pendingRubrics ?></p>
                <button class="text-xs text-yellow-600 mt-2 font-medium hover:underline">Review Now</button>
            </div>

            <!-- Card 4: Events Needing Rubric -->
            <div class="bg-white p-6 rounded-2xl shadow-xl border-l-4 border-red-400 transition hover:shadow-2xl">
                <p class="text-sm font-bold text-red-700 uppercase tracking-widest">Events Lacking Rubric</p>
                <p class="text-5xl font-extrabold text-gray-900 mt-3"><?= $unassignedEvents ?></p>
                <button class="text-xs text-red-600 mt-2 font-medium hover:underline">Assign Rubrics</button>
            </div>
        </div>


        <!-- ============================================== -->
        <!-- SECTION 2: RUBRIC MANAGEMENT (Create, Edit, Approve) -->
        <!-- ============================================== -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-7 h-7 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-4m0 4h.01M9 17h.01M15 17v-4m0 4h.01M15 17h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Rubric Inventory and Lifecycle
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-purple-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider rounded-tl-xl">Rubric Title</th>
                            <th class="p-4 text-center text-xs font-bold text-purple-700 uppercase tracking-wider">Criteria Count</th>
                            <th class="p-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Created By</th>
                            <th class="p-4 text-left text-xs font-bold text-purple-700 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-center text-xs font-bold text-purple-700 uppercase tracking-wider rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($rubrics as $r): ?>
                        <tr class="hover:bg-purple-50 transition duration-150">
                            <td class="p-4 text-sm font-semibold text-gray-900">
                                <?= $r['title'] ?>
                                <p class="text-xs text-gray-500 mt-0.5">Last updated: <?= $r['updated_at'] ?></p>
                            </td>
                            <td class="p-4 text-center text-sm font-black text-indigo-600"><?= $r['criteria_count'] ?></td>
                            <td class="p-4 text-sm text-gray-700"><?= $r['created_by'] ?></td>
                            
                            <td class="p-4 text-sm">
                                <?php
                                $status = $r['status'];
                                $badgeClass = match($status) {
                                    'Approved' => 'bg-green-100 text-green-800 border-green-300',
                                    'Pending Review' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'Draft' => 'bg-gray-100 text-gray-700 border-gray-300',
                                    default => 'bg-red-100 text-red-800 border-red-300'
                                };
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $badgeClass ?> shadow-sm">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td class="p-4 text-center text-sm font-medium space-x-3">
                                <button class="text-blue-600 hover:text-blue-800 transition font-medium underline-offset-2 hover:underline" onclick="viewRubric(<?= $r['id'] ?>)">View</button>
                                <span class="text-gray-300">|</span>
                                <?php if ($r['status'] === 'Pending Review'): ?>
                                    <button class="text-green-600 hover:text-green-800 transition underline-offset-2 hover:underline font-bold" onclick="approveRubric(<?= $r['id'] ?>)">Approve</button>
                                <?php elseif ($r['status'] === 'Draft'): ?>
                                    <button class="text-indigo-600 hover:text-indigo-800 transition underline-offset-2 hover:underline" onclick="editRubric(<?= $r['id'] ?>)">Edit</button>
                                <?php else: ?>
                                    <button class="text-indigo-600 hover:text-indigo-800 transition underline-offset-2 hover:underline" onclick="editRubric(<?= $r['id'] ?>)">Edit</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- SECTION 3: RUBRIC ASSIGNMENT TO EVENT TYPES -->
        <!-- ============================================== -->
        <div class="bg-white p-6 md:p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-7 h-7 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Assign Rubrics to Event Types
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($eventTypes as $e): ?>
                    <?php
                        $isAssigned = $e['assigned_rubric_id'] !== null;
                        $color = $isAssigned ? 'border-sky-300 bg-sky-50' : 'border-red-300 bg-red-50';
                        $statusText = $isAssigned ? 'Assigned' : 'Unassigned';
                        $statusClass = $isAssigned ? 'text-green-700 bg-green-100' : 'text-red-700 bg-red-100';
                        $actionButton = $isAssigned ? 'Change Rubric' : 'Assign Rubric';
                    ?>
                    <div class="p-5 rounded-xl border-2 <?= $color ?> shadow-md transition hover:shadow-lg">
                        <div class="flex justify-between items-start mb-2">
                            <p class="font-bold text-xl text-gray-800"><?= $e['name'] ?></p>
                            <span class="text-xs font-semibold px-3 py-1 rounded-full <?= $statusClass ?>"><?= $statusText ?></span>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Current Rubric:</span> 
                            <span class="font-mono text-indigo-700"><?= $e['assigned_rubric_title'] ?></span>
                        </div>

                        <div class="mt-4 pt-3 border-t border-gray-100">
                            <button class="bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-600 transition shadow-md shadow-indigo-300" onclick="showAssignmentModal(<?= $e['id'] ?>)">
                                <?= $actionButton ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
    </main>

<!-- MODAL PLACEHOLDER FOR RUBRIC ACTIONS -->
<div id="actionModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50">
    <div class="bg-white w-11/12 md:w-1/2 lg:w-1/3 p-8 rounded-xl shadow-2xl">
    <div class="flex justify-between items-center mb-6">
      <h3 id="modalTitle" class="font-bold text-xl text-indigo-700">Action</h3>
        <button onclick="closeModal()" class="text-gray-500 text-2xl hover:text-gray-700">✕</button>
      </div>
      <div id="modalContent" class="text-sm text-gray-700 max-h-[60vh] overflow-y-auto space-y-4">
        <p>This panel simulates a detailed view, edit form, or assignment interface based on the action button clicked.</p>
        <p class="font-medium text-gray-800">For example, clicking 'Approve' would show a confirmation, and 'Edit' would show a form to manage criteria (which is a detailed process that would require a separate dedicated file).</p>
    </div>
      <div class="mt-6 flex justify-end gap-3">
      <button onclick="closeModal()" class="px-5 py-2 border rounded-lg text-sm bg-slate-100 hover:bg-slate-200">Close</button>
      </div>
      </div>
</div>

<script>
    function closeModal() {
        document.getElementById('actionModal').classList.add('hidden');
        document.getElementById('actionModal').classList.remove('flex');
    }

    function showModal(title, content) {
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalContent').innerHTML = content;
        document.getElementById('actionModal').classList.remove('hidden');
        document.getElementById('actionModal').classList.add('flex');
    }

    // --- Rubric Actions (Simulated) ---

    function showCreateRubricModal() {
        showModal(
            "Create New Rubric", 
            "<p>A full form for defining the rubric title, description, and adding criteria (e.g., Criteria Name, Max Score, Description) would go here. Submission would create a new 'Draft' rubric.</p>" + 
            '<div class="flex justify-end mt-4"><button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Submit Rubric Draft</button></div>'
        );
    }

    // Securely encode PHP arrays for JavaScript consumption
    const RUBRICS_DATA = <?= json_encode($rubrics, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const EVENT_TYPES_DATA = <?= json_encode($eventTypes, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function viewRubric(id) {
        // Use the securely encoded global data
        const rubric = RUBRICS_DATA.find(r => r.id === id);
        if (!rubric) return;
        
        let content = `
            <h4 class="text-lg font-bold">Details for: ${rubric.title}</h4>
            <p><strong>Status:</strong> <span class="font-semibold">${rubric.status}</span></p>
            <p><strong>Criteria Count:</strong> ${rubric.criteria_count}</p>
            <p><strong>Created By:</strong> ${rubric.created_by}</p>
            <p><strong>Updated At:</strong> ${rubric.updated_at}</p>
            <p class="mt-4 italic text-gray-500">Note: The full detailed criteria structure (e.g., 5 criteria items with descriptions and weights) would be displayed here.</p>
        `;
        showModal(`View Rubric #${id}`, content);
    }
    
    function editRubric(id) {
        showModal(`Edit Rubric #${id}`, "This action would load a detailed form allowing the admin to modify the title, description, and add/remove/update scoring criteria for this rubric.");
    }
    
    function approveRubric(id) {
        showModal(`Approve Rubric #${id}`, "Are you sure you want to approve this rubric? Once approved, it can be assigned to event types for standardized scoring." + 
        '<div class="flex justify-end mt-4"><button class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-700">Confirm Approval</button></div>');
    }

    // --- Assignment Actions (Simulated) ---

    function showAssignmentModal(eventId) {
        const event = EVENT_TYPES_DATA.find(e => e.id === eventId);
        const availableRubrics = RUBRICS_DATA.filter(r => r.status === 'Approved');

        let options = availableRubrics.map(r => `<option value="${r.id}">${r.title}</option>`).join('');

        const content = `
            <p class="text-lg font-bold">Assign Rubric to: ${event.name}</p>
            <p class="text-sm text-gray-500">Ensure the selected rubric is appropriate for this event type's objectives.</p>
            
            <label for="rubricSelect" class="block text-sm font-medium text-gray-700 mt-4">Select Approved Rubric:</label>
            <select id="rubricSelect" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                <option value="">-- Select a Rubric --</option>
                ${options}
            </select>
            
            <div class="flex justify-end mt-6">
                <button class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-700" onclick="finalizeAssignment(${eventId})">Finalize Assignment</button>
            </div>
        `;
        showModal(`Assign Rubric to Event #${eventId}`, content);
    }
    
    function finalizeAssignment(eventId) {
        // In a real application, you would capture the value from rubricSelect and submit it.
        // For this mockup, we just close the modal.
        // Replaced alert() with a console log for best practice in modern web development
        console.log('Assignment logic simulated. Event ' + eventId + ' rubric updated.');
        closeModal();
    }

</script>
</body>
</html>