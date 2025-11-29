<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";  // change to your DB name
$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

// ============================
// AUTH CHECK (Admin only)
// ============================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// ============================
// ADD CRITERIA
// ============================
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);

    if (!empty($title) && !empty($description)) {
        $stmt = $pdo->prepare("INSERT INTO criteria (title, description) VALUES (:title, :description)");
        if ($stmt->execute(['title' => $title, 'description' => $description])) {
            $message = "✅ New criteria added successfully!";
        } else {
            $message = "❌ Error adding criteria.";
        }
    } else {
        $message = "⚠️ Please fill in all fields.";
    }
}

// ============================
// FETCH EXISTING CRITERIA
// ============================
$stmt = $pdo->query("SELECT * FROM criteria ORDER BY id DESC");
$criteria = $stmt->fetchAll(PDO::FETCH_ASSOC);
include '../sidebar.php'; 

// --- Placeholder Data Simulation for Performance Evaluation ---

$participantEvaluations = [
    ['id' => 1, 'participant_name' => 'Alice Johnson', 'scenario' => 'Tsunami Evacuation Drill', 'role' => 'Incident Commander', 'score' => 91, 'status' => 'Approved', 'evaluator' => 'Bob Lee', 'date' => '2025-10-01'],
    ['id' => 2, 'participant_name' => 'Michael Chen', 'scenario' => 'Chemical Spill Containment', 'role' => 'Hazmat Technician', 'score' => 75, 'status' => 'Pending Review', 'evaluator' => 'Jane Doe', 'date' => '2025-09-15'],
    ['id' => 3, 'participant_name' => 'Sarah Lopez', 'scenario' => 'Mass Casualty Triage', 'role' => 'Lead Medic', 'score' => 88, 'status' => 'Approved', 'evaluator' => 'Bob Lee', 'date' => '2025-08-22'],
    ['id' => 4, 'participant_name' => 'David Kim', 'scenario' => 'Tsunami Evacuation Drill', 'role' => 'Communications Officer', 'score' => 62, 'status' => 'Needs Improvement', 'evaluator' => 'John Smith', 'date' => '2025-10-01'],
    ['id' => 5, 'participant_name' => 'Emily White', 'scenario' => 'Power Grid Failure', 'role' => 'Logistics Coordinator', 'score' => 82, 'status' => 'Approved', 'evaluator' => 'Jane Doe', 'date' => '2025-07-10'],
];

$evaluationCriteria = [
    ['criteria' => 'Communication Clarity', 'weight' => 25, 'description' => 'Ability to convey critical information clearly and concisely.'],
    ['criteria' => 'Decision Making Speed', 'weight' => 35, 'description' => 'Timeliness of critical decisions under pressure.'],
    ['criteria' => 'Adherence to Protocol', 'weight' => 40, 'description' => 'Following established emergency response procedures.'],
];

// Calculate summary statistics
$totalParticipants = count($participantEvaluations);
$avgScore = array_sum(array_column($participantEvaluations, 'score')) / $totalParticipants;
$approvedForms = count(array_filter($participantEvaluations, fn($e) => $e['status'] === 'Approved'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Performance Evaluation</title>
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
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Participant Performance Evaluation</h1>
            <p class="text-lg text-gray-600 mt-2">
                Centralized dashboard for assessing and documenting participant performance during scenario exercises.
                (Database: <span class="font-mono text-sm bg-gray-200 p-1 rounded">simulation_event_planning</span>)
            </p>
        </header>

        <!-- ============================= -->
        <!-- SUMMARY SCORES & ADMIN ACTIONS -->
        <!-- ============================= -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Card 1: Total Evaluations -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border border-indigo-100">
                <p class="text-sm font-semibold text-indigo-600 uppercase">Total Participants Evaluated</p>
                <p class="text-4xl font-bold text-gray-900 mt-2"><?= $totalParticipants ?></p>
            </div>

            <!-- Card 2: Average Score -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border border-teal-100">
                <p class="text-sm font-semibold text-teal-600 uppercase">Average Performance Score</p>
                <p class="text-4xl font-bold text-gray-900 mt-2"><?= number_format($avgScore, 1) ?><span class="text-lg font-medium text-teal-500"> / 100</span></p>
            </div>

            <!-- Card 3: Forms Pending Approval -->
            <div class="bg-white p-6 rounded-2xl shadow-lg border border-red-100 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-red-600 uppercase">Forms Pending Approval</p>
                    <p class="text-4xl font-bold text-gray-900 mt-2"><?= $totalParticipants - $approvedForms ?></p>
                </div>
                <button class="bg-red-500 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-600 transition shadow-md shadow-red-300">
                    Review Forms
                </button>
            </div>
        </div>

        <!-- ============================= -->
        <!-- SET EVALUATION CRITERIA (ADMIN FUNCTION) -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl shadow-blue-100 border border-blue-200">
            <h2 class="text-2xl font-bold text-blue-700 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                Current Evaluation Criteria Configuration
            </h2>
            
            <div class="space-y-4">
                <?php foreach ($evaluationCriteria as $c): ?>
                <div class="flex justify-between items-center p-4 bg-blue-50 rounded-lg border border-blue-100 hover:shadow-md transition">
                    <div class="flex-1">
                        <p class="font-bold text-gray-800"><?= $c['criteria'] ?></p>
                        <p class="text-sm text-gray-600"><?= $c['description'] ?></p>
                    </div>
                    <div class="text-lg font-extrabold text-blue-700 w-24 text-right">
                        <?= $c['weight'] ?>%
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-6 pt-4 border-t border-gray-100 flex justify-between items-center">
                <p class="text-sm text-gray-600 font-medium">Total Weight: <span class="text-blue-700 font-bold"><?= array_sum(array_column($evaluationCriteria, 'weight')) ?>%</span> (Must equal 100%)</p>
                <button class="bg-blue-600 text-white px-5 py-2 rounded-xl font-semibold hover:bg-blue-700 transition shadow-lg shadow-blue-300">
                    Edit Criteria
                </button>
            </div>
        </div>

        <!-- ============================= -->
        <!-- PARTICIPANT SCORES TABLE -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.292M24 10a4 4 0 01-4 4h-1"></path></svg>
                Detailed Participant Score Summary
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-inner">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tl-xl">Participant</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Role</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Scenario / Date</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Evaluator</th>
                            <th class="p-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider">Score</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php 
                        foreach ($participantEvaluations as $e): 
                        ?>
                        <tr class="hover:bg-purple-50/50 transition duration-150">
                            <td class="p-4 text-sm font-medium text-gray-900"><?= $e['participant_name'] ?></td>
                            <td class="p-4 text-sm text-gray-600"><?= $e['role'] ?></td>
                            <td class="p-4 text-sm text-gray-600">
                                <span class="font-medium text-purple-700"><?= $e['scenario'] ?></span>
                                <br><span class="text-xs text-gray-500"><?= date("M d, Y", strtotime($e['date'])) ?></span>
                            </td>
                            <td class="p-4 text-sm text-gray-600"><?= $e['evaluator'] ?></td>
                            
                            <td class="p-4 text-center text-lg font-extrabold 
                                <?php if ($e['score'] >= 85) echo 'text-green-600'; 
                                else if ($e['score'] >= 70) echo 'text-orange-600';
                                else echo 'text-red-600'; ?>
                            ">
                                <?= $e['score'] ?>%
                            </td>

                            <td class="p-4 text-sm">
                                <?php
                                $status = $e['status'];
                                $badgeClass = match($status) {
                                    'Approved' => 'bg-green-100 text-green-800 border-green-300',
                                    'Pending Review' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'Needs Improvement' => 'bg-red-100 text-red-800 border-red-300',
                                    default => 'bg-gray-100 text-gray-700 border-gray-300'
                                };
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td class="p-4 text-center text-sm font-medium space-x-3">
                                <button class="text-blue-600 hover:text-blue-800 transition font-medium">View Form</button>
                                <span class="text-gray-300">|</span>
                                <button class="text-red-600 hover:text-red-800 transition">Revoke</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

<script>
    // No specific client-side JS needed beyond basic rendering for this admin page mockup.
    // The previous switchTab function is removed as the new design uses static cards and lists.
    // However, if the Admin were to implement a modal for 'Edit Criteria' or 'Review Forms',
    // a small JS function would be needed here. For now, it is kept minimal.
</script>
</body>
</html>