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

// Handle Connection Error
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =============================
// Fetch Simulation Types
// =============================
$simulationTypes = $conn->query("SELECT id, name FROM simulation_types ORDER BY name ASC");

// =============================
// Fetch Scenario List
// =============================
$scenarios = $conn->query("
    SELECT id, title, simulation_type, difficulty, status 
    FROM scenario_templates 
    ORDER BY created_at DESC
");
$simulationTypes = new ArrayIterator([
    ['name' => 'Flood Response Drill'],
    ['name' => 'Earthquake Evacuation'],
    ['name' => 'Fire Incident Management'],
    ['name' => 'Disease Outbreak Protocol'],
]);

$scenarios = new ArrayIterator([
    ['id' => 101, 'title' => 'Tsunami Alert Phase 2', 'simulation_type' => 'Earthquake Evacuation', 'difficulty' => 'Advanced', 'status' => 'Published'],
    ['id' => 102, 'title' => 'Market Fire Scenario', 'simulation_type' => 'Fire Incident Management', 'difficulty' => 'Intermediate', 'status' => 'Draft'],
    ['id' => 103, 'title' => 'Typhoon Preparation Drill', 'simulation_type' => 'Flood Response Drill', 'difficulty' => 'Basic', 'status' => 'Pending Approval'],
]);
// ----------------------------------------------------------------------------------
include '../sidebar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scenario Creation Templates</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom styles for independent scrolling and font */
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc; /* Lighter background for a cleaner look */
        }
        /* Ensure main content handles its own scroll, assuming sidebar is fixed or also scrollable */
        .h-screen-main {
            min-height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Enhanced scrollbar for better aesthetics */
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

<!-- We are treating the main content area as the independently scrollable part -->
<body class="bg-gray-50 flex">

    <!-- The 'sidebar.php' include is here, making this the main content area -->

    <!-- ============================= -->
    <!-- MAIN CONTENT WITH INDEPENDENT SCROLLING -->
    <!-- ============================= -->
    <main class="flex-1 h-screen-main p-8 space-y-10">

        <!-- HEADER -->
        <header class="pb-4 border-b border-gray-200">
            <h1 class="text-2xl font-extrabold text-gray-900 tracking-tight">Scenario Management Hub</h1>
            <p class="text-base text-gray-600 mt-2">
                Design, structure, and deploy all disaster simulation templates for training.
            </p>
        </header>

        <!-- ============================= -->
        <!-- FORM CARD: CREATE NEW SCENARIO -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-2xl shadow-indigo-100 border border-indigo-100">
            <h2 class="text-2xl font-bold text-indigo-700 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create New Scenario Template
            </h2>

            <form action="scenario_save.php" method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- SECTION 1: CORE DEFINITIONS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Scenario Title -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Scenario Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" required placeholder="e.g., High-Intensity Typhoon Response"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 transition duration-150 ease-in-out focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none shadow-sm">
                    </div>

                    <!-- Simulation Type -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Simulation Type <span class="text-red-500">*</span></label>
                        <select name="simulation_type" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 bg-white transition duration-150 ease-in-out focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none shadow-sm">
                            <option value="">Select Hazard Type...</option>
                            <?php $simulationTypes->rewind(); while ($row = $simulationTypes->current()): ?>
                                <option value="<?= $row['name'] ?>"><?= $row['name'] ?></option>
                            <?php $simulationTypes->next(); endwhile; ?>
                        </select>
                    </div>

                    <!-- Difficulty Level -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Difficulty Level <span class="text-red-500">*</span></label>
                        <select name="difficulty" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 bg-white transition duration-150 ease-in-out focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none shadow-sm">
                            <option value="Basic">Basic (Focus on Fundamentals)</option>
                            <option value="Intermediate">Intermediate (Moderate Stress)</option>
                            <option value="Advanced">Advanced (Complex, High-Stress)</option>
                        </select>
                    </div>

                </div>

                <!-- SECTION 2: DESCRIPTION & STATUS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Scenario Description</label>
                        <textarea
                            name="description" rows="4"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 transition duration-150 ease-in-out focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none shadow-sm"
                            placeholder="Describe the scenario, objectives, hazards, flow, and expected actions for the responding teams."
                        ></textarea>
                    </div>

                    <!-- Status -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Scenario Status</label>
                        <select name="status"
                            class="w-full px-4 py-3 rounded-xl border border-gray-300 bg-white transition duration-150 ease-in-out focus:ring-4 focus:ring-indigo-200 focus:border-indigo-500 focus:outline-none shadow-sm">
                            <option value="Draft">Draft (In Progress)</option>
                            <option value="Pending Approval">Pending Approval (Ready for Review)</option>
                            <option value="Published">Published (Ready to Use)</option>
                        </select>
                    </div>
                </div>


                <!-- SECTION 3: MEDIA UPLOAD -->
                <div class="border-t border-gray-100 pt-6">
                    <h3 class="text-xl font-semibold text-gray-700 mb-4">Media Assets (Optional)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- Floor Plan Upload -->
                        <div class="p-4 border-2 border-dashed border-blue-200 rounded-xl bg-blue-50">
                            <label class="block text-sm font-semibold text-blue-700 mb-2">Upload Floor Plan (Image/PDF)</label>
                            <input type="file" name="floor_plan"
                                class="custom-file-input w-full block text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-blue-100 file:text-blue-700
                                hover:file:bg-blue-200"
                            >
                            <p class="text-xs text-blue-600 mt-2">
                                Max file size: 10MB. Used for mapping routes, hazards, and safe zones.
                            </p>
                        </div>

                        <!-- 3D Model Upload -->
                        <div class="p-4 border-2 border-dashed border-purple-200 rounded-xl bg-purple-50">
                            <label class="block text-sm font-semibold text-purple-700 mb-2">Upload 3D Model (GLB/OBJ)</label>
                            <input type="file" name="model_3d"
                                class="custom-file-input w-full block text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-full file:border-0
                                file:text-sm file:font-semibold
                                file:bg-purple-100 file:text-purple-700
                                hover:file:bg-purple-200"
                            >
                            <p class="text-xs text-purple-600 mt-2">
                                Required for VR/AR immersive simulation environments.
                            </p>
                        </div>

                    </div>
                </div>


                <!-- SUBMIT BUTTON -->
                <div class="flex justify-end pt-4">
                    <button type="submit"
                        class="flex items-center px-8 py-3 bg-indigo-600 text-white font-bold text-lg rounded-xl shadow-xl shadow-indigo-300 hover:bg-indigo-700 transform hover:scale-[1.02] transition duration-200">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.21a.5.5 0 01.121.512L12.924 19.58c-.538.529-1.398.532-1.939.006L2.397 12.064c-.37-.369-.368-.967.004-1.336l.995-.985a.5.5 0 01.512-.121l4.417 1.054a.5.5 0 00.524-.047l.453-.339a.5.5 0 01.603-.04l.988.75a.5.5 0 00.627-.168l1.002-1.637a.5.5 0 01.464-.265l.504-.006c.463-.006.843.342.923.805l.394 2.164a.5.5 0 00.512.435h2.163a.5.5 0 01.488.618l.386 2.083c.09.48.487.843.957.843h.525c.491 0 .914.398 1.01.898l.386 2.083a.5.5 0 00.488.618h2.163a.5.5 0 01.464-.265l1.002-1.637a.5.5 0 00.627-.168l.988-.75a.5.5 0 01.603.04l.453.339a.5.5 0 00.524.047l4.417-1.054a.5.5 0 01.512.121l.995.985c.37.37.371.968.004 1.336l-8.586 8.586z"></path></svg>
                        Save Scenario Template
                    </button>
                </div>
            </form>
        </div>

        <!-- ============================= -->
        <!-- SCENARIO LIST -->
        <!-- ============================= -->
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-200">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <svg class="w-6 h-6 mr-3 text-teal-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                Existing Scenario Templates
            </h2>

            <div class="overflow-x-auto rounded-xl border border-gray-100 shadow-inner">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tl-xl">Title</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Simulation Type</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Difficulty</th>
                            <th class="p-4 text-left text-xs font-bold text-gray-600 uppercase tracking-wider">Status</th>
                            <th class="p-4 text-center text-xs font-bold text-gray-600 uppercase tracking-wider rounded-tr-xl">Action</th>
                        </tr>
                    </thead>

                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php $scenarios->rewind(); while ($s = $scenarios->current()): ?>
                        <tr class="hover:bg-indigo-50/50 transition duration-150">
                            <td class="p-4 text-sm font-medium text-gray-900"><?= $s['title'] ?></td>
                            <td class="p-4 text-sm text-gray-600"><?= $s['simulation_type'] ?></td>
                            <td class="p-4 text-sm">
                                <?php
                                $color = '';
                                if ($s['difficulty'] === 'Basic') $color = 'bg-green-100 text-green-800 border-green-300';
                                elseif ($s['difficulty'] === 'Intermediate') $color = 'bg-yellow-100 text-yellow-800 border-yellow-300';
                                elseif ($s['difficulty'] === 'Advanced') $color = 'bg-red-100 text-red-800 border-red-300';
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $color ?>">
                                    <?= $s['difficulty'] ?>
                                </span>
                            </td>
                            <td class="p-4 text-sm">
                                <?php
                                $statusColor = '';
                                if ($s['status'] === 'Draft') $statusColor = 'bg-gray-100 text-gray-600 border-gray-300';
                                elseif ($s['status'] === 'Pending Approval') $statusColor = 'bg-blue-100 text-blue-800 border-blue-300';
                                elseif ($s['status'] === 'Published') $statusColor = 'bg-teal-100 text-teal-800 border-teal-300';
                                ?>
                                <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?= $statusColor ?>">
                                    <?= $s['status'] ?>
                                </span>
                            </td>
                            <td class="p-4 text-center text-sm font-medium space-x-3">
                                <a href="scenario_edit.php?id=<?= $s['id'] ?>"
                                    class="text-indigo-600 hover:text-indigo-800 hover:underline transition">Edit</a>
                                <span class="text-gray-300">|</span>
                                <a href="scenario_delete.php?id=<?= $s['id'] ?>"
                                    onclick="return confirm('WARNING: Are you sure you want to permanently delete the scenario: <?= $s['title'] ?>?')"
                                    class="text-red-600 hover:text-red-800 hover:underline transition">Delete</a>
                            </td>
                        </tr>
                        <?php $scenarios->next(); endwhile; ?>
                    </tbody>

                </table>
            </div>
            <!-- Pagination Placeholder -->
            <div class="flex justify-between items-center mt-6 text-sm text-gray-600">
                <span>Showing 1 to 3 of 3 results</span>
                <div class="space-x-1">
                    <button class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition disabled:opacity-50" disabled>Previous</button>
                    <button class="px-3 py-1 border rounded-lg bg-indigo-500 text-white font-semibold">1</button>
                    <button class="px-3 py-1 border rounded-lg hover:bg-gray-100 transition disabled:opacity-50" disabled>Next</button>
                </div>
            </div>
        </div>
    </main>
</body>
</html>