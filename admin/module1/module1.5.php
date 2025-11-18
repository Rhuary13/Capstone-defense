<?php
session_start();

// -------------------------
// Database connection
// -------------------------
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// -------------------------
// Security check
// -------------------------
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

// -------------------------
// Handle Edit / Resource Updates
// -------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['edit_module_id'])) {
        $id = (int)$_POST['edit_module_id'];
        $title = $conn->real_escape_string($_POST['title']);
        $desc = $conn->real_escape_string($_POST['description']);
        $conn->query("UPDATE training_modules SET title='$title', description='$desc' WHERE id=$id");
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// -------------------------
// Helpers
// -------------------------
function generateInsight(float $completionRate, float $avgScore): string {
    if ($completionRate === 0 && $avgScore === 0) {
        return "No learner data yet. Consider assigning this module to learners or attaching quizzes/resources.";
    }
    if ($completionRate < 50) return "Low completion rate detected. Consider improving content or sending reminders.";
    if ($avgScore < 60) return "Low average quiz score. Review materials or update quizzes.";
    if ($avgScore < 75) return "Average performance. Consider adding interactive activities.";
    return "Module performing well. Maintain current approach.";
}
function progressColorClass(float $rate): string {
    if ($rate < 50) return 'text-red-600';
    if ($rate < 75) return 'text-yellow-600';
    return 'text-green-600';
}

// -------------------------
// Fetch modules
// -------------------------
$completion_stats = [];
$has_completion_table = $conn->query("SHOW TABLES LIKE 'training_completion'")->num_rows > 0;
$sql = $has_completion_table ?
"SELECT tm.id AS module_id, tm.title, tm.description,
        COUNT(tc.user_id) AS total_learners,
        SUM(CASE WHEN tc.status = 'Completed' THEN 1 ELSE 0 END) AS completed,
        SUM(CASE WHEN tc.status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress
 FROM training_modules tm
 LEFT JOIN training_completion tc ON tm.id = tc.module_id
 GROUP BY tm.id ORDER BY tm.id ASC"
:
"SELECT tm.id AS module_id, tm.title, tm.description,
        COUNT(DISTINCT qr.participant_id) AS total_learners,
        SUM(CASE WHEN qr.status = 'Passed' THEN 1 ELSE 0 END) AS completed,
        0 AS in_progress
 FROM training_modules tm
 LEFT JOIN quiz_results qr ON tm.id = qr.lesson_id
 GROUP BY tm.id ORDER BY tm.id ASC";

$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) {
    $r['total_learners'] = (int)($r['total_learners'] ?? 0);
    $r['completed'] = (int)($r['completed'] ?? 0);
    $r['in_progress'] = (int)($r['in_progress'] ?? 0);
    $completion_stats[] = $r;
}

// Module effectiveness
$module_effectiveness = [];
$res2 = $conn->query("
SELECT tm.id, tm.title, ROUND(AVG(qr.score),2) AS avg_score,
       COUNT(qr.participant_id) AS attempts
FROM training_modules tm
LEFT JOIN quiz_results qr ON tm.id = qr.lesson_id
GROUP BY tm.id ORDER BY tm.id ASC
");
while ($r = $res2->fetch_assoc()) {
    $r['avg_score'] = $r['avg_score'] !== null ? (float)$r['avg_score'] : 0.0;
    $r['attempts'] = (int)($r['attempts'] ?? 0);
    $module_effectiveness[$r['id']] = $r;
}

// Overall quiz summary
$quiz_summary = ['total'=>0,'passed'=>0,'failed'=>0,'avg_score'=>0.0];
$res3 = $conn->query("
SELECT COUNT(*) AS total,
       SUM(CASE WHEN status='Passed' THEN 1 ELSE 0 END) AS passed,
       SUM(CASE WHEN status='Failed' THEN 1 ELSE 0 END) AS failed,
       ROUND(AVG(score),2) AS avg_score
FROM quiz_results
");
if($row=$res3->fetch_assoc()){
    $quiz_summary['total'] = (int)$row['total'];
    $quiz_summary['passed'] = (int)$row['passed'];
    $quiz_summary['failed'] = (int)$row['failed'];
    $quiz_summary['avg_score'] = $row['avg_score']!==null?(float)$row['avg_score']:0.0;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Admin — Module Analytics</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<style>
body{background:#f8fafc;margin:0;font-family:ui-sans-serif,system-ui,sans-serif}
aside{width:240px;position:fixed;top:0;left:0;height:100vh;overflow-y:auto}
main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh;overflow-y:auto;padding:1.25rem}
.modules-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.25rem}
.module-card{border-radius:.9rem;transition:transform .12s,box-shadow .12s}
.module-card:hover{transform:translateY(-6px);box-shadow:0 10px 28px rgba(2,6,23,0.06)}
.radial{font-weight:700;font-size:.95rem;display:flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:999px;background:rgba(0,0,0,0.03)}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;z-index:50;}
.modal-content{background:#fff;padding:1.5rem;border-radius:.75rem;max-width:500px;width:100%;}
</style>
</head>
<body>
<!-- Sidebar -->
<aside>
<?php include '../sidebar.php'; ?>
</aside>

<!-- Main Content -->
<main>
<header class="bg-white border-b px-6 py-4 flex items-center justify-between mb-6">
<div>
<h1 class="text-2xl font-bold text-sky-700">📋 Module Completion & Effectiveness</h1>
<p class="text-sm text-gray-600 mt-1">Use these insights to improve content and learning flow.</p>
</div>
<div class="flex items-center gap-3">
<div class="text-sm text-gray-600">Admin: <span class="font-medium"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></span></div>
<div class="text-sm text-gray-400"><?= date('F j, Y, g:i A') ?></div>
</div>
</header>

<!-- Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
<div class="bg-white p-4 rounded-lg module-card"><p class="text-xs text-gray-500">Total Quiz Attempts</p><p class="text-2xl font-bold text-sky-600"><?= number_format($quiz_summary['total']) ?></p></div>
<div class="bg-white p-4 rounded-lg module-card"><p class="text-xs text-gray-500">Passed</p><p class="text-2xl font-bold text-green-600"><?= number_format($quiz_summary['passed']) ?></p></div>
<div class="bg-white p-4 rounded-lg module-card"><p class="text-xs text-gray-500">Failed</p><p class="text-2xl font-bold text-red-600"><?= number_format($quiz_summary['failed']) ?></p></div>
<div class="bg-white p-4 rounded-lg module-card"><p class="text-xs text-gray-500">Avg Quiz Score</p><p class="text-2xl font-bold text-purple-600"><?= round($quiz_summary['avg_score'],2) ?>%</p></div>
</div>

<!-- Controls -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
<div class="flex items-center gap-3 w-full md:w-2/3">
<input id="search" oninput="filterModules()" placeholder="Search modules or type id..." class="w-full md:w-1/2 border rounded px-3 py-2 focus:outline-none" />
<select id="sort" onchange="sortModules()" class="border rounded px-3 py-2">
<option value="id_asc">ID ↑</option>
<option value="id_desc">ID ↓</option>
<option value="title_asc">Title A→Z</option>
<option value="title_desc">Title Z→A</option>
<option value="completion_desc">Completion % ↓</option>
</select>
<button onclick="exportCSV()" class="ml-2 px-3 py-2 rounded bg-sky-600 text-white">Export CSV</button>
</div>
<div class="text-sm text-gray-500">Modules: <strong><?= number_format(count($completion_stats)) ?></strong></div>
</div>

<!-- Modules Grid -->
<div id="modulesGrid" class="modules-grid">
<?php foreach($completion_stats as $m):
$total=(int)$m['total_learners'];
$completed=(int)$m['completed'];
$in_progress=(int)$m['in_progress'];
$completionRate=$total>0?round(($completed/$total)*100,2):0.0;
$eff=$module_effectiveness[$m['module_id']]??['avg_score'=>0.0,'attempts'=>0];
$avgScore=(float)$eff['avg_score'];
$attempts=(int)$eff['attempts'];
$insight=generateInsight($completionRate,$avgScore);
$colorClass=progressColorClass($completionRate);
?>
<article class="bg-white p-5 module-card" data-title="<?= htmlspecialchars(strtolower($m['title'])) ?>" data-id="<?= intval($m['module_id']) ?>" data-completion="<?= $completionRate ?>">
<div class="flex gap-4">
<div class="radial <?= $colorClass ?>"><?= $completionRate ?>%</div>
<div class="flex-1">
<h3 class="text-lg font-semibold"><?= htmlspecialchars($m['title']) ?></h3>
<p class="text-xs text-gray-500 mt-1"><?= "$completed completed · $in_progress in progress · $total learners" ?></p>
<div class="mt-3 bg-gray-50 p-3 rounded border-l-4 border-sky-400"><p class="text-sm text-gray-700"><?= htmlspecialchars($insight) ?></p></div>
<div class="mt-3 flex gap-2">
<button onclick="openEditModal(<?= $m['module_id'] ?>,'<?= htmlspecialchars(addslashes($m['title'])) ?>','<?= htmlspecialchars(addslashes($m['description'])) ?>')" class="px-3 py-1 rounded bg-sky-600 text-white text-sm">Edit</button>
<button onclick="openResourceModal(<?= $m['module_id'] ?>,'<?= htmlspecialchars(addslashes($m['title'])) ?>')" class="px-3 py-1 rounded border bg-white text-sm">Resources</button>
</div>
</div>
</div>
</article>
<?php endforeach; ?>
</div>
</main>

<!-- Edit Modal -->
<div id="editModal" class="modal-bg flex">
<div class="modal-content">
<h2 class="text-xl font-bold text-sky-700 mb-4">Edit Module</h2>
<form method="POST">
<input type="hidden" name="edit_module_id" id="edit_module_id" />
<label class="block mb-1 font-medium">Title</label>
<input type="text" name="title" id="edit_title" class="w-full border rounded px-3 py-2 mb-3" required />
<label class="block mb-1 font-medium">Description</label>
<textarea name="description" id="edit_description" class="w-full border rounded px-3 py-2 mb-3" required></textarea>
<div class="flex justify-end gap-2">
<button type="button" onclick="closeEditModal()" class="px-3 py-1 border rounded">Cancel</button>
<button type="submit" class="px-3 py-1 bg-sky-600 text-white rounded">Save</button>
</div>
</form>
</div>
</div>

<!-- Resource Modal -->
<div id="resourceModal" class="modal-bg flex">
<div class="modal-content">
<h2 class="text-xl font-bold text-sky-700 mb-4" id="resource_title">Module Resources</h2>
<p class="text-gray-600 mb-4">Here you can attach quizzes, documents, or videos for this module.</p>
<ul class="list-disc pl-5 text-gray-700 mb-4">
<li>Quiz 1: [Edit / Delete]</li>
<li>Document: "Lesson PDF" [Upload / Delete]</li>
<li>Video: "Introduction" [Upload / Delete]</li>
</ul>
<div class="flex justify-end">
<button type="button" onclick="closeResourceModal()" class="px-3 py-1 border rounded">Close</button>
</div>
</div>
</div>

<script>
document.getElementById('editModal').style.display='none';
document.getElementById('resourceModal').style.display='none';

function openEditModal(id,title,desc){
    document.getElementById('edit_module_id').value=id;
    document.getElementById('edit_title').value=title;
    document.getElementById('edit_description').value=desc;
    document.getElementById('editModal').style.display='flex';
}
function closeEditModal(){ document.getElementById('editModal').style.display='none'; }
function openResourceModal(id,title){
    document.getElementById('resource_title').innerText='Resources for '+title;
    document.getElementById('resourceModal').style.display='flex';
}
function closeResourceModal(){ document.getElementById('resourceModal').style.display='none'; }

function filterModules(){
const q=document.getElementById('search').value.trim().toLowerCase();
document.querySelectorAll('#modulesGrid article').forEach(a=>{
const title=a.dataset.title||'', id=a.dataset.id||'';
a.style.display = (!q||title.includes(q)||id.includes(q))?'':'none';
});
}
function sortModules(){
const mode=document.getElementById('sort').value;
const container=document.getElementById('modulesGrid');
const nodes=Array.from(container.children);
let sorted=nodes.slice();
sorted.sort((A,B)=>{
const aId=parseInt(A.dataset.id||0), bId=parseInt(B.dataset.id||0);
const aTitle=A.dataset.title||'', bTitle=B.dataset.title||'';
const aComp=parseFloat(A.dataset.completion||0), bComp=parseFloat(B.dataset.completion||0);
if(mode==='id_asc') return aId-bId;
if(mode==='id_desc') return bId-aId;
if(mode==='title_asc') return aTitle.localeCompare(bTitle);
if(mode==='title_desc') return bTitle.localeCompare(aTitle);
if(mode==='completion_desc') return bComp-aComp;
return 0;
});
sorted.forEach(n=>container.appendChild(n));
}
function exportCSV(){
const rows=[['Module ID','Title','Completion %','Completed','In Progress','Total Learners','Avg Score','Attempts']];
document.querySelectorAll('#modulesGrid article').forEach(a=>{
const id=a.dataset.id||'', title=a.querySelector('h3')?.innerText||'', comp=a.dataset.completion||'', avg=a.querySelector('.text-lg.font-semibold')?.innerText||'', attempts=a.querySelector('.text-xs.text-gray-500')?.innerText||'';
rows.push([id,title,comp,'','','',avg,attempts]);
});
const csv=rows.map(r=>r.map(c=>'"'+String(c).replace(/"/g,'""')+'"').join(',')).join("\n");
const blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
const url=URL.createObjectURL(blob);
const a=document.createElement('a'); a.href=url; a.download='module_analytics.csv'; document.body.appendChild(a); a.click();
URL.revokeObjectURL(url); a.remove();
}
</script>
</body>
</html>
