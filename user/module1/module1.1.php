<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db = "training_management";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// =========================
// Security check
// =========================
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// =========================
// Fetch all modules uploaded by admin
// =========================
$modules = $conn->query("SELECT * FROM training_modules ORDER BY id DESC");

// =========================
// Fetch Notifications for Participant
// =========================
$notifications = [];
// Assuming a 'user_type' is set in the session, e.g., 'participant' or 'staff'
$user_type = $_SESSION['user_type'] ?? 'participant'; 

$stmt = $conn->prepare("SELECT id, title, message, created_at, is_read FROM notifications WHERE recipient_type = 'all' OR recipient_type = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("s", $user_type);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Participant Lessons & Notifications</title>

    <!-- Tailwind CDN (same as your original) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        /* small visual tweaks on top of Tailwind */
        /* Create independent scroll region inside main */
        .app-scroll {
            max-height: calc(100vh - 48px); /* leave room for header padding */
            overflow: auto;
            scroll-behavior: smooth;
            padding-right: 8px; /* avoid overlap with custom scrollbar */
        }
        /* subtle scrollbar styling for modern feel */
        .app-scroll::-webkit-scrollbar { width: 10px; }
        .app-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(15,23,42,0.12);
            border-radius: 8px;
            border: 2px solid transparent;
            background-clip: content-box;
        }
    </style>
</head>
<body class="bg-slate-50 h-screen flex overflow-hidden font-sans text-slate-800">

    <?php include '../sidebar.php'; ?>

    <!-- Main content -->
    <main class="flex-1 h-screen flex flex-col">
        <!-- Topbar -->
        <header class="flex items-center justify-between bg-white/60 backdrop-blur-md border-b border-slate-200 px-6 py-4">
            <div class="flex items-center gap-4">
                <h1 class="text-2xl font-extrabold text-sky-700 flex items-center gap-3">
                    <i data-lucide="book-open" class="w-7 h-7"></i>
                    <span>Training Modules</span>
                </h1>
                <p class="text-sm text-slate-500 hidden sm:block">Learning materials & lesson previews</p>
            </div>

            <div class="flex items-center gap-4">
                <!-- Search (UI only, non-functional in current logic) -->
                <div class="relative hidden md:block">
                    <input type="search" placeholder="Search modules, disaster type..." class="w-72 pl-10 pr-4 py-2 rounded-lg border border-slate-200 bg-white shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-200" />
                    <i data-lucide="search" class="absolute left-3 top-2.5 w-5 h-5 text-slate-400"></i>
                </div>

                <!-- Notifications summary -->
                <button class="relative flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-slate-100 transition" aria-label="Notifications">
                    <i data-lucide="bell" class="w-5 h-5"></i>
                    <span class="hidden md:block text-sm text-slate-600">Notifications</span>
                    <?php 
                        $unread = 0;
                        foreach ($notifications as $n) { if (!$n['is_read']) $unread++; }
                    ?>
                    <?php if ($unread > 0): ?>
                        <span class="absolute -top-1 -right-1 inline-flex items-center justify-center px-2 py-0.5 text-xs font-medium leading-none text-white bg-rose-600 rounded-full">
                            <?= (int)$unread ?>
                        </span>
                    <?php endif; ?>
                </button>

                <!-- Profile placeholder -->
                <div class="flex items-center gap-3">
                    <div class="text-right hidden sm:block">
                        <div class="text-sm font-medium"><?= htmlspecialchars($_SESSION['name'] ?? 'Participant'); ?></div>
                        <div class="text-xs text-slate-500">Participant</div>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-sky-400 to-indigo-500 flex items-center justify-center text-white font-bold">
                        <?= strtoupper(substr(htmlspecialchars($_SESSION['name'] ?? 'P'),0,1)); ?>
                    </div>
                </div>
            </div>
        </header>

        <!-- Scrollable app content -->
        <div class="app-scroll px-6 py-6">
            <div class="max-w-6xl mx-auto space-y-8">

                <!-- Notifications panel -->
                <section class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-1 bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-amber-50">
                                    <i data-lucide="bell-ring" class="w-6 h-6 text-amber-600"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold">My Notifications</div>
                                    <div class="text-xs text-slate-500">Latest updates from admins</div>
                                </div>
                            </div>
                            <a href="#" class="text-xs text-sky-600 hover:underline">See all</a>
                        </div>

                        <?php if (!empty($notifications)): ?>
                            <ul class="space-y-3">
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="p-3 rounded-lg border <?php echo $notification['is_read'] ? 'bg-white' : 'bg-amber-50 border-amber-100'; ?>">
                                        <div class="flex justify-between items-start gap-3">
                                            <div class="flex-1">
                                                <div class="text-sm font-medium text-slate-800"><?= htmlspecialchars($notification['title']); ?></div>
                                                <div class="text-xs text-slate-500 mt-1"><?= nl2br(htmlspecialchars($notification['message'])); ?></div>
                                            </div>
                                            <div class="text-xs text-slate-400 text-right ml-3">
                                                <?= date("M d, Y", strtotime($notification['created_at'])); ?><br />
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="inline-block mt-2 px-2 py-0.5 text-xs rounded bg-rose-100 text-rose-700">New</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="p-4 text-center text-slate-500 bg-slate-50 rounded-lg">
                                You have no new notifications.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Spotlight / Quick links -->
                    <div class="md:col-span-2 bg-white rounded-2xl border border-slate-200 shadow-sm p-5 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-800">Learning Spotlight</h2>
                                <p class="text-xs text-slate-500">Featured lesson & quick actions</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <a href="#" class="inline-flex items-center gap-2 px-3 py-2 bg-sky-50 border border-sky-100 text-sky-700 rounded-lg text-sm">
                                    <i data-lucide="play-circle" class="w-4 h-4"></i> Continue last lesson
                                </a>
                                <a href="#" class="inline-flex items-center gap-2 px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm hover:shadow-sm">
                                    <i data-lucide="download" class="w-4 h-4"></i> Download all
                                </a>
                            </div>
                        </div>

                        <!-- If you want a featured module preview, show the first module -->
                        <?php 
                            // reset pointer and fetch first lesson as spotlight (if exists)
                            if ($modules) {
                                $modules->data_seek(0);
                                $spotlight = $modules->fetch_assoc();
                            } else {
                                $spotlight = null;
                            }
                        ?>
                        <?php if ($spotlight): ?>
                            <div class="grid sm:grid-cols-3 gap-4">
                                <div class="sm:col-span-1">
                                    <?php
                                        $filePath = "../../admin/module1/uploads/" . htmlspecialchars($spotlight['file_name']);
                                        $ext = strtolower(pathinfo($spotlight['file_name'] ?? '', PATHINFO_EXTENSION));
                                    ?>
                                    <div class="rounded-lg overflow-hidden border border-slate-100 bg-slate-50 aspect-video flex items-center justify-center">
                                        <?php if (!empty($spotlight['file_name']) && $ext === 'pdf'): ?>
                                            <iframe src="<?= $filePath ?>" class="w-full h-full"></iframe>
                                        <?php elseif (!empty($spotlight['file_name']) && in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                            <img src="<?= $filePath ?>" alt="Spotlight" class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="text-sm text-slate-500 p-4 text-center">
                                                No preview available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="sm:col-span-2 flex flex-col gap-2">
                                    <div class="flex items-start justify-between">
                                        <div>
                                            <h3 class="text-lg font-semibold"><?= htmlspecialchars($spotlight['title']); ?></h3>
                                            <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($spotlight['disaster_type'] ?? 'General'); ?></div>
                                        </div>
                                        <div class="text-right text-xs text-slate-400">
                                            Uploaded: <?= htmlspecialchars($spotlight['created_at'] ?? 'N/A'); ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-slate-600 line-clamp-4">
                                        <?= nl2br(htmlspecialchars($spotlight['objectives'] ?? 'No objectives provided.')); ?>
                                    </p>

                                    <div class="mt-auto flex gap-3">
                                        <a href="<?= $filePath ?>" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-sky-600 text-white text-sm" download>
                                            <i data-lucide="download" class="w-4 h-4"></i> Download
                                        </a>
                                        <a href="#" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-200 text-sm hover:shadow">
                                            <i data-lucide="book-open" class="w-4 h-4"></i> Open lesson
                                        </a>
                                        <a href="#" class="ml-auto text-sm text-slate-500 hover:underline">More</a>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-sm text-slate-500">No featured module available.</div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Modules list -->
                <section class="space-y-6">
                    <?php
                        // Reset the modules pointer and loop normally.
                        if ($modules) $modules->data_seek(0);
                    ?>
                    <?php if ($modules && $modules->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <?php while ($lesson = $modules->fetch_assoc()): ?>
                                <article class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm flex flex-col">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="text-lg font-semibold text-slate-800"><?= htmlspecialchars($lesson['title']); ?></h3>
                                            <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($lesson['disaster_type'] ?? 'General'); ?></div>
                                        </div>
                                        <div class="text-xs text-slate-400 text-right">
                                            <?= htmlspecialchars($lesson['created_at'] ?? 'N/A'); ?>
                                        </div>
                                    </div>

                                    <?php if (!empty($lesson['objectives'])): ?>
                                        <p class="text-sm text-slate-600 mt-3 line-clamp-4">
                                            <?= nl2br(htmlspecialchars($lesson['objectives'])); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($lesson['file_name'])):
                                        $filePath = "../../admin/module1/uploads/" . htmlspecialchars($lesson['file_name']);
                                        $ext = strtolower(pathinfo($lesson['file_name'], PATHINFO_EXTENSION));
                                    ?>
                                        <div class="mt-4 rounded-lg overflow-hidden border border-slate-100">
                                            <?php if ($ext === 'pdf'): ?>
                                                <div class="w-full h-60 md:h-44">
                                                    <iframe src="<?= $filePath ?>" class="w-full h-full border-0"></iframe>
                                                </div>
                                            <?php elseif (in_array($ext, ['jpg','jpeg','png','gif'])): ?>
                                                <img src="<?= $filePath ?>" alt="Lesson Image" class="w-full h-44 object-cover">
                                            <?php elseif (in_array($ext, ['doc','docx'])): ?>
                                                <div class="w-full h-44">
                                                    <iframe src="https://docs.google.com/viewer?url=<?= urlencode('http://localhost/capstone/admin/module1/uploads/' . $lesson['file_name']) ?>&embedded=true" class="w-full h-full border-0"></iframe>
                                                </div>
                                                <div class="p-3 border-t border-slate-100">
                                                    <a href="<?= $filePath ?>" class="text-sm text-emerald-600 hover:underline" download>⬇️ Download DOC</a>
                                                </div>
                                            <?php else: ?>
                                                <div class="p-4">
                                                    <a href="<?= $filePath ?>" class="px-3 py-2 bg-slate-700 text-white rounded hover:opacity-95" download>⬇️ Download Lesson</a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-sm text-slate-500 mt-3">No lesson file uploaded.</p>
                                    <?php endif; ?>

                                    <div class="mt-4 flex items-center gap-3">
                                        <a href="#" class="text-sm px-3 py-2 bg-white border border-slate-200 rounded-lg hover:shadow">Open</a>
                                        <a href="#" class="text-sm px-3 py-2 bg-white border border-slate-200 rounded-lg hover:shadow">Take Quiz</a>
                                        <a href="#" class="ml-auto text-sm text-slate-500">Details</a>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-lg p-6 bg-rose-50 border border-rose-100 text-rose-700">
                            ❌ No lessons available yet. Please check with the administrator.
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </main>

<script>
    lucide.createIcons();
</script>
</body>
</html>
