<?php
session_start();

/* CONFIG */
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "training_management";

/* DB CONNECT */
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) die("DB error: " . htmlspecialchars($conn->connect_error));
$conn->set_charset("utf8mb4");

$staff_res = $conn->query("SELECT id, name, role FROM staff ORDER BY name ASC");
$staff_list = [];

if ($staff_res && $staff_res->num_rows > 0) {
    while ($row = $staff_res->fetch_assoc()) {
        // Map 'role' → 'expertise'
        $row['expertise'] = $row['role'];
        $staff_list[] = $row;
    }
}
/* AUTH CHECK */
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

/* CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));

/* Helpers */
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }
function short($t,$n=180){ $t=strip_tags($t??''); return mb_strlen($t) <= $n ? $t : mb_substr($t,0,$n).'…'; }
/* -----------------------------------------
   POST HANDLERS
----------------------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if ($csrf !== $_SESSION['csrf']) { $error = "Invalid CSRF token."; }
    else {
        if ($action==='create_module') {
            $title = trim($_POST['title'] ?? '');
            $disaster_type = trim($_POST['disaster_type'] ?? '');
            $topic = trim($_POST['topic'] ?? '');
            $objective_ids = $_POST['objectives'] ?? [];
            if ($title==='' || $disaster_type==='') $error="Title & Disaster Type required.";
            else {
                $stmt=$conn->prepare("INSERT INTO training_modules (title, disaster_type, topic, created_at) VALUES (?,?,?,NOW())");
                $stmt->bind_param("sss",$title,$disaster_type,$topic);
                $ok=$stmt->execute();
                $module_id=$stmt->insert_id;
                $stmt->close();
                if ($ok && !empty($objective_ids)) {
                    $upd=$conn->prepare("UPDATE learning_objectives SET module_id=? WHERE id=?");
                    foreach ($objective_ids as $oid) { $oid_i=(int)$oid; $upd->bind_param("ii",$module_id,$oid_i); $upd->execute(); }
                    $upd->close();
                }
                $success = $ok ? "Module created successfully." : "Failed to create module.";
            }
        }
        if ($action==='create_objective') {
            $otitle=trim($_POST['obj_title'] ?? '');
            $odesc=trim($_POST['obj_desc'] ?? '');
            if ($otitle==='') $error="Objective title required.";
            else {
                $ins=$conn->prepare("INSERT INTO learning_objectives (title, description, created_at) VALUES (?,?,NOW())");
                $ins->bind_param("ss",$otitle,$odesc); $ins->execute(); $ins->close();
                $success="Learning Objective added.";
            }
        }
        if ($action==='delete_module') {
            $mid=(int)($_POST['module_id'] ?? 0);
            if ($mid>0) {
                $conn->query("UPDATE learning_objectives SET module_id=NULL WHERE module_id=$mid");
                $d=$conn->prepare("DELETE FROM training_modules WHERE id=?");
                $d->bind_param("i",$mid); $d->execute(); $d->close();
                $success="Module deleted.";
            }
        }
        if ($action==='delete_objective') {
            $oid=(int)($_POST['objective_id'] ?? 0);
            if ($oid>0) {
                $d=$conn->prepare("DELETE FROM learning_objectives WHERE id=?");
                $d->bind_param("i",$oid); $d->execute(); $d->close();
                $success="Learning Objective deleted.";
            }
        }
    }
}

/* Disaster types */
$disaster_types = ["Earthquake","Tsunami","Volcanic Eruption","Flood","Typhoon","Cyclone","Hurricane","Tornado","Landslide","Wildfire","Other"];

/* ==========================
  RENDER MODULE CREATION
========================== */
function renderStaffAssignmentPanel($conn, $disaster_types, $staff_list) {
    $objectives_res = $conn->query("SELECT * FROM learning_objectives ORDER BY id DESC");
    ?>
<div class="big-card p-4 bg-white rounded-2xl shadow border border-slate-200 w-full flex flex-col gap-4">

    <!-- MODULE CREATION -->
    <h3 class="text-lg font-bold text-slate-900 mb-1">Create Training Module</h3>
    <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-3 w-full">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="create_module">

        <div class="flex flex-col gap-1">
            <label class="font-medium text-slate-700 text-sm">Module Title</label>
            <input name="title" required class="w-full border border-slate-300 px-3 py-1.5 rounded-xl focus:ring-1 focus:ring-blue-400 focus:outline-none text-sm">
        </div>

        <div class="flex flex-col gap-1">
            <label class="font-medium text-slate-700 text-sm">Disaster Type</label>
            <select id="module_disaster_type" name="disaster_type" required class="w-full border border-slate-300 px-3 py-1.5 rounded-xl focus:ring-1 focus:ring-blue-400 focus:outline-none text-sm">
                <option value="">Select type</option>
                <?php foreach($disaster_types as $d): ?>
                    <option value="<?= e($d) ?>"><?= e($d) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label class="font-medium text-slate-700 text-sm">Topic</label>
            <input name="topic" placeholder="Ex: Evacuation, First Aid" class="w-full border border-slate-300 px-3 py-1.5 rounded-xl focus:ring-1 focus:ring-blue-400 focus:outline-none text-sm">
        </div>

        <!-- FILE ATTACHMENT -->
        <div class="flex flex-col gap-1">
            <label class="font-medium text-slate-700 text-sm">Attach File (optional)</label>
            <input type="file" name="module_file" class="border border-slate-300 px-3 py-1.5 rounded-xl focus:ring-1 focus:ring-blue-400 focus:outline-none text-sm">
        </div>

        <!-- OBJECTIVES -->
        <div class="flex flex-col gap-1">
            <label class="font-medium text-slate-700 text-sm">Select Objectives</label>
            <div class="max-h-40 overflow-auto border border-slate-200 rounded-xl p-2 bg-slate-50 flex flex-col gap-2">
                <?php if($objectives_res && $objectives_res->num_rows):
                    while($o = $objectives_res->fetch_assoc()): ?>
                        <label class="flex flex-col gap-1 bg-white border border-slate-200 rounded-xl p-2 hover:shadow transition text-sm">
                            <div class="flex items-center gap-1">
                                <input type="checkbox" name="objectives[]" value="<?= (int)$o['id'] ?>" <?= $o['module_id']?'disabled':'' ?> class="mt-0.5">
                                <span class="font-medium"><?= e($o['title']) ?></span>
                            </div>
                            <span class="text-xs text-slate-500"><?= e(short($o['description'])) ?></span>
                            <?php if(!empty($o['file_path'])): ?>
                                <a href="<?= e($o['file_path']) ?>" target="_blank" class="text-blue-600 text-xs hover:underline">Attached File</a>
                            <?php endif; ?>
                        </label>
                    <?php endwhile;
                else: ?>
                    <div class="text-slate-400 text-sm">No objectives available.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- STAFF ASSIGNMENT -->
        <h3 class="text-lg font-semibold mt-3">Assign Staff</h3>
        <p class="text-slate-500 text-xs mb-1">Select staff qualified for the chosen disaster type.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-80 overflow-auto">
            <?php foreach($staff_list as $s):
                $dq_res = $conn->query("SELECT disaster_type FROM staff_disaster_certifications WHERE staff_id=".(int)$s['id']);
                $qualified = [];
                if($dq_res && $dq_res->num_rows) while($dq=$dq_res->fetch_assoc()) $qualified[] = $dq['disaster_type'];
            ?>
            <div class="rounded-xl border border-slate-200 p-2 shadow-sm flex flex-col gap-1 hover:shadow transition text-sm">
                <label class="flex items-center gap-1">
                    <input type="checkbox" name="staff_ids[]" value="<?= (int)$s['id'] ?>">
                    <strong class="text-slate-900"><?= e($s['name']) ?></strong> (<?= e($s['role']) ?>)
                </label>
                <p class="text-xs text-slate-500">Qualified: <?= !empty($qualified) ? e(implode(', ',$qualified)) : 'None' ?></p>
                <button type="button" onclick="openStaffModal(<?= $s['id'] ?>)" class="mt-1 px-2 py-0.5 text-xs bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition">View Full Profile</button>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="mt-4 px-5 py-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition text-sm">Create Module & Assign Staff</button>
    </form>
</div>

<!-- STAFF MODAL -->
<div id="staffModal" class="hidden fixed inset-0 bg-black/40 backdrop-blur-sm flex items-center justify-center p-2">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl p-4 relative overflow-auto max-h-[90vh]">
        <button onclick="closeStaffModal()" class="absolute top-2 right-2 text-lg hover:text-red-500">✖</button>
        <div id="staffModalContent" class="space-y-2 text-sm"></div>
    </div>
</div>

<script>
function openStaffModal(id){
    fetch("load_staff_profile.php?id="+id)
        .then(res=>res.text())
        .then(html=>{
            document.getElementById("staffModalContent").innerHTML = html;
            document.getElementById("staffModal").classList.remove("hidden");
        });
}
function closeStaffModal(){
    document.getElementById("staffModal").classList.add("hidden");
}
// Filter staff by selected disaster type
document.getElementById('module_disaster_type').addEventListener('change', function(){
    let type = this.value;
    document.querySelectorAll('input[name="staff_ids[]"]').forEach(cb=>{
        let qualified = cb.parentNode.querySelector('p')?.innerText || '';
        cb.parentNode.style.display = (type && !qualified.includes(type)) ? 'none' : 'block';
    });
});
</script>
<?php
}

/* ==========================
   RENDER MODULE LIST WITH VIEW/DELETE
========================== */
function renderTopicPanelWithFilter($conn) {
    $modules_res = $conn->query("SELECT * FROM training_modules ORDER BY topic ASC");
    ?>
    <div class="big-card p-6 bg-white rounded-2xl shadow-lg flex flex-col gap-4">
        <h3 class="text-xl font-bold">Modules</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4" id="modulesGrid">
            <?php if($modules_res && $modules_res->num_rows):
                while($m=$modules_res->fetch_assoc()):
                    $assignees=[]; 
                    if($conn->query("SHOW TABLES LIKE 'module_assignments'")->num_rows){
                        $resA=$conn->query("SELECT u.name FROM module_assignments ma JOIN users u ON ma.user_id=u.id WHERE ma.module_id=".(int)$m['id']);
                        if($resA && $resA->num_rows) while($a=$resA->fetch_assoc()) $assignees[]=$a['name'];
                    }
            ?>
            <div class="module-card p-4 bg-slate-50 border rounded-xl flex flex-col justify-between hover:bg-slate-100">
                <div>
                    <h4 class="font-semibold"><?= e($m['title']) ?></h4>
                    <span class="text-xs bg-green-100 px-2 py-1 rounded-full"><?= e($m['disaster_type']) ?></span>
                    <div class="text-xs text-slate-500">Topic: <?= e($m['topic'] ?? '—') ?></div>
                    <div class="text-xs text-slate-500">Assigned To: <?= !empty($assignees)? e(implode(', ',$assignees)) : '—' ?></div>
                </div>
                <div class="flex gap-2 mt-2 justify-end items-center text-xs">
                    <button class="text-blue-600 hover:underline view-btn" data-id="<?= (int)$m['id'] ?>">View</button>
                    <form method="POST" class="inline" onsubmit="return confirm('Delete this module?');">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="action" value="delete_module">
                        <input type="hidden" name="module_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="text-rose-600 hover:underline">Delete</button>
                    </form>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="text-slate-400 col-span-full">No modules.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
<div id="viewModal" class="fixed inset-0 bg-black/50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-xl w-11/12 max-w-lg shadow-xl relative overflow-auto max-h-[90vh]">
        <button id="closeModal" class="absolute top-3 right-3 text-rose-600 font-bold text-xl">&times;</button>
        <div id="modalContent" class="space-y-3 text-sm"></div>
    </div>
</div>

<script>
const modal = document.getElementById('viewModal');
const modalContent = document.getElementById('modalContent');

document.querySelectorAll('.view-btn').forEach(btn=>{
    btn.addEventListener('click', ()=> {
        fetch('?view_id='+btn.dataset.id)
        .then(r=>r.text())
        .then(html=>{
            modalContent.innerHTML = html;
            modal.classList.remove('hidden');
        });
    });
});

document.getElementById('closeModal').addEventListener('click', ()=> {
    modal.classList.add('hidden');
});
</script>
    <?php
}

/* ==========================
   MODAL CONTENT FETCH
========================== */
if(isset($_GET['view_id'])){
    $id=(int)$_GET['view_id'];

    // Module info
    $stmt=$conn->prepare("SELECT * FROM training_modules WHERE id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();
    $module=$stmt->get_result()->fetch_assoc();
    $stmt->close();

    if(!$module){
        echo "<p class='text-red-600'>Module not found.</p>";
        exit;
    }

    // Objectives
    $objs_res = $conn->query("SELECT * FROM learning_objectives WHERE module_id=$id ORDER BY id ASC");

    // Lessons (existing table)
    $lessons_res = $conn->query("SELECT * FROM lessons WHERE module_id=$id ORDER BY created_at ASC");

    // Assigned users
    $assignees = [];
if ($conn->query("SHOW TABLES LIKE 'module_staff_assignments'")->num_rows) {
    $resA = $conn->query("
        SELECT s.name 
        FROM module_staff_assignments msa
        JOIN staff s ON msa.staff_id = s.id
        WHERE msa.module_id = $id
    ");

    if ($resA && $resA->num_rows) {
        while ($a = $resA->fetch_assoc()) {
            $assignees[] = $a['name'];
        }
    }
}
    echo "<h2 class='text-xl font-semibold mb-2'>".e($module['title'])."</h2>";
    echo "<p class='text-xs text-slate-500'>Disaster: ".e($module['disaster_type'])."</p>";
    echo "<p class='text-xs text-slate-500'>Topic: ".e($module['topic'])."</p>";
    echo "<p class='text-xs text-slate-500'>Created: ".e($module['created_at'])."</p>";
    echo "<p class='text-xs text-slate-500'>Assigned To: ".(!empty($assignees)? e(implode(', ', $assignees)) : '—')."</p>";

    // Objectives
    echo "<h3 class='font-semibold mt-3'>Objectives:</h3><ul class='list-disc pl-5'>";
    if($objs_res && $objs_res->num_rows) while($o=$objs_res->fetch_assoc()) echo "<li>".e($o['title'])."</li>";
    else echo "<li class='text-slate-400'>None</li>";
    echo "</ul>";

    // Lessons
    echo "<h3 class='font-semibold mt-3'>Lessons:</h3>";
    if($lessons_res && $lessons_res->num_rows){
        echo "<ul class='list-disc pl-5'>";
        while($l=$lessons_res->fetch_assoc()){
            echo "<li><strong>".e($l['title'])."</strong> — <span class='text-xs text-slate-500'>".e($l['description'])."</span></li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='text-slate-400'>No lessons added yet.</p>";
    }

    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Modules</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>.big-card{background:white;padding:16px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.06);}</style>
</head>
<body class="bg-slate-100 h-screen overflow-hidden">
<div class="flex h-full">
<?php include "../sidebar.php"; ?>
<div class="flex-1 overflow-auto p-6">

<header class="bg-white/90 backdrop-blur-sm p-5 rounded-xl shadow-md mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-3">
    <div class="flex flex-col">
        <h1 class="text-2xl font-bold text-slate-900">Content Management</h1>
        <p class="text-sm text-slate-500 mt-1">Modules • Topics • Learning Objectives</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-block bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-xs font-medium uppercase tracking-wide">
            Admin
        </span>
        <!-- Optional icon -->
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.364 4.56M15 11v1m0 4h.01" />
        </svg>
    </div>
</header>

<?php if(!empty($success)) echo "<div class='mb-4 p-3 bg-green-50 border-l-4 border-green-400 text-green-700'>".e($success)."</div>"; ?>
<?php if(!empty($error)) echo "<div class='mb-4 p-3 bg-rose-50 border-l-4 border-rose-400 text-rose-700'>".e($error)."</div>"; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <aside><?php renderStaffAssignmentPanel($conn, $disaster_types, $staff_list); ?></aside>
    <main class="col-span-2"><?php renderTopicPanelWithFilter($conn); ?></main>
</div>
</div>
</body>
</html>
