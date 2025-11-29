<?php
session_start();

// =============================
// Database Connection
// =============================
$host = "localhost";
$user = "root";
$pass = "";
$db = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =============================
// Fetch Facilitators (with certifications)
// =============================
$facilitators = $conn->query("
    SELECT id, name, role, certification, trainings, availability_status 
    FROM staff 
    WHERE certification IS NOT NULL AND certification <> ''
    ORDER BY name ASC
");

// =============================
// Fetch Scheduled Briefings
// =============================
$briefings = $conn->query("
    SELECT id, title, briefing_date, facilitator_name, status 
    FROM pre_briefings 
    ORDER BY briefing_date DESC
");
$facilitatorData = [
    ['id' => 1, 'name' => 'Dr. Eleanor Vance', 'role' => 'Lead Disaster Analyst', 'certification' => 'FEMA Certified L400', 'trainings' => 'Tsunami, Fire, Earthquake', 'availability_status' => 'Available'],
    ['id' => 2, 'name' => 'John R. Cooper', 'role' => 'Logistics Coordinator', 'certification' => 'Emergency Management Specialist', 'trainings' => 'Fire, Mass Casualty', 'availability_status' => 'Unavailable'],
    ['id' => 3, 'name' => 'Sarah L. Jenkins', 'role' => 'Triage Specialist', 'certification' => 'AHA CPR/AED Certified', 'trainings' => 'Flood, Earthquake', 'availability_status' => 'Available'],
];

// Placeholder array for scheduled briefings
$briefingData = [
    ['id' => 201, 'title' => 'Post-Typhoon Triage Overview', 'briefing_date' => '2025-12-15 10:00:00', 'facilitator_name' => 'Dr. Eleanor Vance', 'status' => 'Approved'],
    ['id' => 202, 'title' => 'New Protocol Safety Check', 'briefing_date' => '2025-12-20 14:30:00', 'facilitator_name' => 'John R. Cooper', 'status' => 'Pending'],
    ['id' => 203, 'title' => 'Winter Storm Preparedness', 'briefing_date' => '2026-01-05 09:00:00', 'facilitator_name' => 'Sarah L. Jenkins', 'status' => 'Scheduled'],
];
// -------------------------------------------------------------------------

// Assuming the sidebar file exists in the parent directory as per the original code
// This line is kept as requested by the user's input structure
include '../sidebar.php'; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pre-Simulation Briefing</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background for a cleaner look */
        }
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
            background-color: #cbd5e1; /* Gray-300 */
            border-radius: 10px;
        }
        /* Custom file input styling */
        .custom-file-input {
            cursor: pointer;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            background-color: #ffffff;
            transition: all 0.2s;
        }
        .custom-file-input:hover {
            border-color: #6366f1; /* Indigo-500 */
            box-shadow: 0 0 0 1px #6366f1;
        }
    </style>
</head>

<body class="bg-gray-50 flex">

    <!-- MAIN CONTENT -->
    <main class="flex-1 h-screen-main p-8 space-y-10">

        <!-- PAGE HEADER -->
        <header class="pb-4 border-b border-gray-200">
            <h1 class="text-4xl font-extrabold text-gray-900 tracking-tight">Pre-Simulation Briefing Manager</h1>
            <p class="text-lg text-gray-600 mt-2">
                Coordinate and schedule essential briefing sessions, assign certified facilitators, and manage guide approval.
            </p>
        </header>

        <!-- ============================= -->
        <!-- CREATE NEW BRIEFING FORM CARD -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl shadow-blue-100 border border-blue-100">
            <h2 class="text-2xl font-bold text-blue-700 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                Schedule New Briefing Session
            </h2>

            <!-- Error Message Box (Initially hidden) -->
            <div id="errorMessageBox" class="hidden p-4 mb-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg" role="alert">
                <p class="font-bold">Selection Required</p>
                <p id="errorMessageText">Please select a certified facilitator before viewing details.</p>
            </div>

            <form action="briefing_save.php" method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- TITLE & DATE -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <!-- Briefing Title -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Briefing Title</label>
                        <input type="text" name="title" required placeholder="e.g., Q4 Fire Safety Orientation"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 transition duration-150 ease-in-out focus:ring-4 focus:ring-blue-200 focus:border-blue-500 shadow-sm">
                    </div>

                    <!-- Date and Time -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Briefing Date & Time</label>
                        <input type="datetime-local" name="briefing_date" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 transition duration-150 ease-in-out focus:ring-4 focus:ring-blue-200 focus:border-blue-500 shadow-sm">
                    </div>

                </div>

                <!-- FACILITATOR SELECTION -->
                <div class="p-6 border border-indigo-200 bg-indigo-50 rounded-xl">
                    <label class="block text-sm font-semibold text-indigo-700 mb-3">Assign Facilitator</label>

                    <div class="flex gap-4">
                        <select id="facilitatorSelect" name="facilitator_id" required
                            class="flex-1 px-4 py-3 rounded-xl border border-indigo-300 bg-white shadow-md focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500">
                            <option value="">-- Select Certified Facilitator --</option>
                            <?php 
                            // Iterating over the standard PHP array $facilitatorData
                            foreach ($facilitatorData as $f): 
                            ?>
                                <option value="<?= $f['id'] ?>"
                                    data-name="<?= htmlspecialchars($f['name']) ?>"
                                    data-role="<?= htmlspecialchars($f['role']) ?>"
                                    data-cert="<?= htmlspecialchars($f['certification']) ?>"
                                    data-train="<?= htmlspecialchars($f['trainings']) ?>"
                                    data-status="<?= htmlspecialchars($f['availability_status']) ?>">
                                    <?= $f['name'] ?> – <?= $f['role'] ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button type="button"
                            onclick="openFacilitatorPreview()"
                            class="px-5 py-3 bg-indigo-600 text-white font-medium rounded-xl shadow-lg hover:bg-indigo-700 transition transform hover:scale-105">
                            <svg class="w-5 h-5 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            View Details
                        </button>
                    </div>

                    <p class="text-xs text-indigo-600 mt-2">
                        Only certified facilitators with required training appear in this list.
                    </p>
                </div>

                <!-- BRIEFING GUIDE UPLOAD -->
                <div class="p-6 border border-gray-200 bg-gray-50 rounded-xl">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Upload Briefing Guide (PDF / DOCX)</label>
                    <input type="file" name="briefing_guide"
                        class="custom-file-input w-full block text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-100 file:text-blue-700
                        hover:file:bg-blue-200"
                    >
                    <p class="text-xs text-gray-500 mt-2">
                        Max file size: 5MB. Must contain objectives, safety reminders, and timeline.
                    </p>
                </div>

                <!-- DESCRIPTION -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Briefing Summary</label>
                    <textarea name="description" rows="4"
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 transition duration-150 ease-in-out focus:ring-4 focus:ring-blue-200 focus:border-blue-500 shadow-sm"
                        placeholder="Provide a concise overview of the briefing's focus and key takeaways."></textarea>
                </div>

                <!-- SUBMIT -->
                <div class="flex justify-end pt-4">
                    <button type="submit"
                        class="flex items-center px-8 py-3 bg-blue-600 text-white font-bold text-lg rounded-xl shadow-lg shadow-blue-300 hover:bg-blue-700 transition transform hover:scale-[1.02]">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        Save Briefing Schedule
                    </button>
                </div>

            </form>
        </div>

        <!-- ============================= -->
        <!-- EXISTING BRIEFINGS TABLE CARD -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                Scheduled Briefings
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-inner">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tl-xl">Title</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Date & Time</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Facilitator</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tr-xl">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php 
                        // Iterating over the standard PHP array $briefingData
                        foreach ($briefingData as $b): 
                        ?>
                        <tr class="hover:bg-blue-50/50 transition duration-150">
                            <td class="p-4 text-sm font-medium text-gray-900"><?= $b['title'] ?></td>
                            <td class="p-4 text-sm text-gray-600">
                                <?= date("M d, Y", strtotime($b['briefing_date'])) ?> 
                                <span class="font-semibold text-gray-800"><?= date("h:i A", strtotime($b['briefing_date'])) ?></span>
                            </td>
                            <td class="p-4 text-sm text-gray-600"><?= $b['facilitator_name'] ?></td>

                            <td class="p-4 text-sm">
                                <?php
                                $status = $b['status'];
                                $badgeClass = match($status) {
                                    'Approved' => 'bg-green-100 text-green-800 border-green-300',
                                    'Pending' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
                                    'Scheduled' => 'bg-blue-100 text-blue-800 border-blue-300',
                                    default => 'bg-gray-100 text-gray-700 border-gray-300'
                                };
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $badgeClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>

                            <td class="p-4 text-center text-sm font-medium space-x-3">
                                <a href="briefing_edit.php?id=<?= $b['id'] ?>" 
                                    class="text-blue-600 hover:text-blue-800 hover:underline transition">Edit</a>
                                <span class="text-gray-300">|</span>
                                <a href="briefing_delete.php?id=<?= $b['id'] ?>" 
                                    onclick="return confirm('Delete this briefing?')"
                                    class="text-red-600 hover:text-red-800 hover:underline transition">Delete</a>
                                <span class="text-gray-300">|</span>
                                <a href="briefing_approve.php?id=<?= $b['id'] ?>" 
                                    class="text-green-600 hover:text-green-800 hover:underline transition">Approve</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FACILITATOR DETAILS MODAL -->
        <div id="facilitatorModal"
            class="fixed inset-0 bg-gray-900 bg-opacity-70 hidden flex items-center justify-center z-50 transition-opacity duration-300">
            <div class="bg-white w-full max-w-lg p-8 rounded-xl shadow-2xl transform scale-100 transition-transform duration-300">

                <h2 class="text-2xl font-bold text-gray-800 mb-6 border-b pb-2 flex items-center">
                    <svg class="w-6 h-6 mr-2 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-7 0V4a2 2 0 012-2h2a2 2 0 012 2v2m0 0h-4"></path></svg>
                    Facilitator Profile Overview
                </h2>

                <div class="space-y-4 text-gray-700">
                    <div class="grid grid-cols-2 gap-y-3">
                        <p class="font-semibold text-indigo-600">Name:</p>
                        <p id="mName" class="font-medium"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-y-3">
                        <p class="font-semibold text-indigo-600">Role:</p>
                        <p id="mRole"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-y-3">
                        <p class="font-semibold text-indigo-600">Certification:</p>
                        <p id="mCert" class="font-mono text-xs p-1 bg-gray-100 rounded inline-block"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-y-3">
                        <p class="font-semibold text-indigo-600">Specialized Training:</p>
                        <p id="mTrainings"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-y-3 pt-3 border-t border-gray-100">
                        <p class="font-semibold text-indigo-600">Current Availability:</p>
                        <p>
                            <span id="mStatus" class="px-3 py-1 text-sm font-bold rounded-full"></span>
                        </p>
                    </div>
                </div>

                <div class="mt-8 text-right">
                    <button onclick="closeFacilitatorModal()"
                        class="px-6 py-2 bg-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-400 transition">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </main>

<script>
    // Facilitator Modal Functions
    function openFacilitatorPreview() {
        const select = document.getElementById('facilitatorSelect');
        const selectedOption = select.options[select.selectedIndex];
        const errorBox = document.getElementById('errorMessageBox');
        
        // Use custom UI message box instead of alert()
        if (!selectedOption.value) {
            errorBox.classList.remove('hidden');
            document.getElementById('errorMessageText').textContent = 'Please select a certified facilitator before viewing details.';
            // Hide the error message after 3 seconds for a better UX
            setTimeout(() => {
                errorBox.classList.add('hidden');
            }, 3000);
            return;
        }

        errorBox.classList.add('hidden'); // Ensure error is hidden if selection is valid

        document.getElementById('mName').textContent = selectedOption.getAttribute('data-name');
        document.getElementById('mRole').textContent = selectedOption.getAttribute('data-role');
        document.getElementById('mCert').textContent = selectedOption.getAttribute('data-cert');
        document.getElementById('mTrainings').textContent = selectedOption.getAttribute('data-train');
        
        const status = selectedOption.getAttribute('data-status');
        const statusElem = document.getElementById('mStatus');
        statusElem.textContent = status;
        
        // Dynamically set status badge colors
        if (status === 'Available') {
            statusElem.className = 'px-3 py-1 text-sm font-bold rounded-full bg-green-500 text-white';
        } else {
            statusElem.className = 'px-3 py-1 text-sm font-bold rounded-full bg-red-500 text-white';
        }

        document.getElementById('facilitatorModal').classList.remove('hidden');
    }

    function closeFacilitatorModal() {
        document.getElementById('facilitatorModal').classList.add('hidden');
    }
</script>
</body>
</html>