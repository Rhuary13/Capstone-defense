<?php
session_start();

$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . htmlspecialchars($conn->connect_error));
}

// Helper: safe output
function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// Example: fetch user's progress counts (if tables exist)
$userId = isset($_SESSION['id']) ? intval($_SESSION['id']) : null;
$progressSummary = ['completed' => 0, 'in_progress' => 0, 'not_started' => 0];
if ($userId) {
    $sql = "SELECT status, COUNT(*) as cnt FROM training_progress WHERE id = ? GROUP BY status";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $progressSummary[$row['status']] = (int)$row['cnt'];
            }
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Training Module 1 - Disaster Preparedness</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small helper to make the main scroll isolated */
    .main-scroll {
      max-height: calc(100vh - 48px); /* account for header spacing */
    }
  </style>
</head>
<body class="min-h-screen bg-gray-100 flex text-sm leading-relaxed">

  <!-- Sidebar (keeps your existing sidebar file) -->
  <?php include 'sidebar.php'; ?>

  <!-- Main Content (flex-1) -->
  <main class="flex-1 p-6 lg:p-8 overflow-hidden">
    <!-- Top header row -->
    <header class="flex items-start justify-between gap-4 mb-6">
      <div>
        <nav class="text-xs text-gray-500 mb-2" aria-label="Breadcrumb">
          <ol class="list-reset flex items-center space-x-2">
            <li><a href="#" class="hover:underline">Dashboard</a></li>
            <li>&#47;</li>
            <li class="text-gray-700 font-medium">Module 1 — Disaster Preparedness</li>
          </ol>
        </nav>
        <h1 class="text-2xl lg:text-3xl font-semibold text-gray-800">Hello, <?php echo e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Participant'); ?></h1>
        <p class="text-gray-600 mt-1">Necessary education to prepare you and your household for disasters</p>
      </div>

      <div class="flex items-center gap-3">
        <!-- Quick stats -->
        <div class="hidden sm:flex items-center gap-3 bg-white p-3 rounded-xl shadow">
          <div class="text-xs text-gray-500">Progress</div>
          <div class="text-lg font-semibold text-gray-800">
            <?php
              $completed = $progressSummary['completed'] ?? 0;
              $in_progress = $progressSummary['in_progress'] ?? 0;
              $total = $completed + $in_progress + ($progressSummary['not_started'] ?? 0);
              $pct = ($total>0) ? round(($completed / $total) * 100) : 0;
              echo e($pct) . '%';
            ?>
          </div>
        </div>

        <!-- User card -->
        <div class="flex items-center gap-3 bg-white p-2 rounded-xl shadow">
          <img src="<?php echo e($_SESSION['avatar'] ?? 'https://ui-avatars.com/api/?name='.urlencode($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'P').'&size=64'); ?>"
               alt="avatar" class="w-10 h-10 rounded-full object-cover" />
          <div>
            <div class="text-sm font-medium text-gray-800"><?php echo e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Participant'); ?></div>
            <div class="text-xs text-gray-500"><?php echo e($_SESSION['role'] ?? 'participant'); ?></div>
          </div>
        </div>
      </div>
    </header>

    <!-- Main scrollable area: prevents overlap with sidebar -->
    <section class="main-scroll overflow-auto pr-2 space-y-6">
      <!-- Hero / Intro -->
      <div class="bg-white p-6 rounded-2xl shadow">
        <div class="md:flex md:items-center md:gap-6">
          <div class="flex-1">
            <h2 class="text-2xl font-semibold text-gray-800 mb-1">Why Disaster Preparedness Matters</h2>
            <p class="text-gray-600 leading-relaxed">
              Disaster preparedness equips individuals and communities with the knowledge and skills needed to effectively respond to emergencies such as typhoons, earthquakes, floods, and landslides.
              This module introduces you to the fundamental principles of disaster risk reduction, early warning, household preparedness and community resilience.
            </p>

            <div class="mt-4 flex flex-wrap gap-3">
              <a href="/Capstone-defense/user/module1/module1.1.php" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:shadow-lg">
                <!-- icon -->
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a1 1 0 01.993.883L11 3v6h6a1 1 0 01.117 1.993L17 11h-6v6a1 1 0 01-1.993.117L9 17v-6H3a1 1 0 01-.117-1.993L3 9h6V3a1 1 0 011-1z"/></svg>
                Start Training
              </a>

              <button id="toggle-syllabus" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50">
                View Syllabus
              </button>

              <a href="/resources/evacuation-plan.pdf" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 hover:bg-gray-50" target="_blank" rel="noopener">
                Download Evacuation Checklist
              </a>
            </div>
          </div>

          <!-- Progress Card -->
          <aside class="mt-6 md:mt-0 md:w-72 bg-gray-50 p-4 rounded-xl border border-gray-100">
            <div class="flex items-center justify-between mb-3">
              <div>
                <div class="text-xs text-gray-500">Your module progress</div>
                <div class="text-lg font-semibold text-gray-800"><?php echo e($completed); ?> completed</div>
              </div>
              <div class="text-right text-xs text-gray-500"><?php echo e($total ?: 0); ?> modules</div>
            </div>

            <div class="w-full bg-white h-3 rounded-full overflow-hidden border border-gray-200">
              <div style="width: <?php echo e($pct); ?>%;" class="h-3 rounded-full bg-gradient-to-r from-green-400 to-blue-500"></div>
            </div>

            <ul class="mt-4 space-y-2 text-xs text-gray-600">
              <li>Completed: <span class="font-medium text-gray-800"><?php echo e($completed); ?></span></li>
              <li>In progress: <span class="font-medium text-gray-800"><?php echo e($in_progress); ?></span></li>
              <li>Not started: <span class="font-medium text-gray-800"><?php echo e($progressSummary['not_started'] ?? 0); ?></span></li>
            </ul>
          </aside>
        </div>
      </div>

      <!-- Learning Objectives (cards grid) -->
      <div class="grid lg:grid-cols-3 gap-6">
        <div class="bg-blue-50 p-6 rounded-xl shadow hover:shadow-lg transition">
          <h3 class="text-lg font-bold text-blue-700">Understanding Risks</h3>
          <p class="text-gray-600 mt-2 text-sm">
            Learn about the most common natural disasters in your region and how they impact communities. This includes how to read early warnings and identify safe zones.
          </p>
          <ul class="mt-3 text-xs text-gray-600 space-y-1">
            <li>• Hazard identification</li>
            <li>• Vulnerability assessment</li>
            <li>• Early warning systems</li>
          </ul>
        </div>

        <div class="bg-green-50 p-6 rounded-xl shadow hover:shadow-lg transition">
          <h3 class="text-lg font-bold text-green-700">Preparedness Planning</h3>
          <p class="text-gray-600 mt-2 text-sm">
            Create household emergency plans, pack GO bags, and practice evacuation drills with your family and community.
          </p>
          <ul class="mt-3 text-xs text-gray-600 space-y-1">
            <li>• Family emergency plan</li>
            <li>• Evacuation routes</li>
            <li>• Communication plan</li>
          </ul>
        </div>

        <div class="bg-yellow-50 p-6 rounded-xl shadow hover:shadow-lg transition">
          <h3 class="text-lg font-bold text-yellow-700">Response & Recovery</h3>
          <p class="text-gray-600 mt-2 text-sm">
            Steps for immediate response, first aid basics, securing property, and steps to take for long-term recovery and resilience.
          </p>
          <ul class="mt-3 text-xs text-gray-600 space-y-1">
            <li>• First aid & basic response</li>
            <li>• Damage assessment</li>
            <li>• Psychosocial support</li>
          </ul>
        </div>
      </div>

      <!-- Resources and Activities -->
      <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h4 class="font-semibold text-gray-800">Recommended Resources</h4>
            <a href="/resources" class="text-xs text-blue-600 hover:underline">See all</a>
          </div>

          <ul class="space-y-3">
            <li class="flex items-start gap-3">
              <div class="flex-none mt-1">
                <svg class="w-6 h-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v8m0-8l3 3m-3-3l-3 3"/></svg>
              </div>
              <div>
                <div class="text-sm font-medium text-gray-800">Household Evacuation Checklist</div>
                <div class="text-xs text-gray-500">PDF • 1.2MB</div>
              </div>
              <div class="ml-auto">
                <a href="/resources/evacuation-checklist.pdf" class="text-xs text-blue-600 hover:underline">Download</a>
              </div>
            </li>

            <li class="flex items-start gap-3">
              <div class="flex-none mt-1">
                <svg class="w-6 h-6 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v10m0 0l3-3m-3 3l-3-3"/></svg>
              </div>
              <div>
                <div class="text-sm font-medium text-gray-800">Basic First Aid Guide</div>
                <div class="text-xs text-gray-500">Article • 6 min read</div>
              </div>
              <div class="ml-auto">
                <a href="/resources/first-aid" class="text-xs text-blue-600 hover:underline">Open</a>
              </div>
            </li>
          </ul>
        </div>

        <div class="bg-white p-6 rounded-xl shadow">
          <div class="flex items-center justify-between mb-4">
            <h4 class="font-semibold text-gray-800">Recent Activity</h4>
            <button id="clear-activity" class="text-xs text-red-500 hover:underline">Clear</button>
          </div>

          <ul id="activity-list" class="space-y-3 text-gray-600 text-xs">
            <li class="flex items-start gap-3">
              <div class="w-2.5 h-2.5 rounded-full bg-green-400 mt-2"></div>
              <div>Completed <strong>Module 1.1</strong> — Household Planning <span class="text-gray-400">· 2 days ago</span></div>
            </li>
            <li class="flex items-start gap-3">
              <div class="w-2.5 h-2.5 rounded-full bg-yellow-400 mt-2"></div>
              <div>Started <strong>Module 1.2</strong> — Evacuation Drills <span class="text-gray-400">· 6 days ago</span></div>
            </li>
            <li class="flex items-start gap-3">
              <div class="w-2.5 h-2.5 rounded-full bg-gray-300 mt-2"></div>
              <div>New resource added: <strong>Community Map Template</strong> <span class="text-gray-400">· 1 week ago</span></div>
            </li>
          </ul>
        </div>
      </div>

      <!-- CTA (expanded, with accessibility) -->
      <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-xl shadow flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <h3 class="text-xl font-semibold">Ready to Begin?</h3>
          <p class="text-sm opacity-90">Start your training and gain essential knowledge for disaster preparedness.</p>
        </div>
        <div class="flex gap-3">
          <a href="/Capstone-defense/user/module1/module1.1.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100">Start Training</a>
          <a href="/certificate" class="border border-white/30 px-4 py-2 rounded-lg hover:bg-white/10">View Certificates</a>
        </div>
      </div>

    </section>
  </main>

  <script>
    // Simple interactions
    document.getElementById('toggle-syllabus')?.addEventListener('click', function(){
      alert('Syllabus: Module 1 — Intro to Disaster Preparedness\n\n1) Understanding Risks\n2) Preparedness Planning\n3) Response & Recovery\n\n(You can expand this to a modal or a dedicated syllabus page.)');
    });

    document.getElementById('clear-activity')?.addEventListener('click', function(){
      if (confirm('Clear recent activity? This only clears the client view.')) {
        document.getElementById('activity-list').innerHTML = '<li class="text-gray-400">No recent activity.</li>';
      }
    });
  </script>
</body>
</html>
