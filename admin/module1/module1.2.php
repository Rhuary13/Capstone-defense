<?php
session_start();
// Database connection
$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "training_management"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ✅ Security check
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}
// ✅ Security check
if (!isset($_SESSION['id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// ----------------------
// ADD Training Program
// ----------------------
if (isset($_POST['add_program'])) {
    $title       = $conn->real_escape_string($_POST['title']);
    $definition  = $conn->real_escape_string($_POST['definition']);
    $scope       = $conn->real_escape_string($_POST['scope']);
    $goal        = $conn->real_escape_string($_POST['goal']);
    $format      = $conn->real_escape_string($_POST['format']);
    $example     = $conn->real_escape_string($_POST['example']);

    $conn->query("INSERT INTO training_programs (title, definition, scope, goal, format, example) 
                  VALUES ('$title', '$definition', '$scope', '$goal', '$format', '$example')");
    header("Location: program.php?success=1");
    exit;
}

// ----------------------
// DELETE Program
// ----------------------
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $conn->query("DELETE FROM training_programs WHERE id=$id");
    header("Location: program.php?deleted=1");
    exit;
}

// ----------------------
// EDIT Program (Fetch Data)
// ----------------------
$editData = null;
if (isset($_GET['edit'])) {
    $id = (int) $_GET['edit'];
    $result = $conn->query("SELECT * FROM training_programs WHERE id=$id");
    $editData = $result->fetch_assoc();
}

// ----------------------
// UPDATE Program
// ----------------------
if (isset($_POST['update_program'])) {
    $id         = (int) $_POST['id'];
    $title      = $conn->real_escape_string($_POST['title']);
    $definition = $conn->real_escape_string($_POST['definition']);
    $scope      = $conn->real_escape_string($_POST['scope']);
    $goal       = $conn->real_escape_string($_POST['goal']);
    $format     = $conn->real_escape_string($_POST['format']);
    $example    = $conn->real_escape_string($_POST['example']);

    $conn->query("UPDATE training_programs 
                  SET title='$title', definition='$definition', scope='$scope',
                      goal='$goal', format='$format', example='$example'
                  WHERE id=$id");
    header("Location: program.php?updated=1");
    exit;
}

// ----------------------
// Fetch All Programs
// ----------------------
$programs = $conn->query("SELECT * FROM training_programs ORDER BY created_at DESC");
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training Programs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs" defer></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    </head>
    <body class="bg-gray-100 min-h-screen flex">

        <!-- Sidebar -->
        <?php include '../sidebar.php'; ?>

    <!-- Main Area -->
    <div class="flex-1 flex flex-col h-screen">

    <!-- Top Navigation -->
    <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
        <h1 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="layers" class="w-6 h-6 text-blue-600"></i>
            Training Programs
        </h1>
    </nav>

    <main class="flex-1 px-6 py-8 mt-16 h-[calc(100vh-4rem)] overflow-y-auto flex justify-center">
    <div class="w-full max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Add/Update Program Form -->
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center gap-2 mb-4">
                    <i data-lucide="<?= $editData ? 'pencil' : 'plus-circle' ?>" class="w-5 h-5 text-blue-600"></i>
                    <?= $editData ? 'Edit Training Program' : 'Add Training Program' ?>
                </h3>

                <!-- Alerts -->
                <?php if (isset($_GET['success'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">✅ Program added successfully!</div>
                <?php elseif (isset($_GET['updated'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-700 border border-blue-200">✏️ Program updated successfully!</div>
                <?php elseif (isset($_GET['deleted'])): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-700 border border-red-200">🗑️ Program deleted successfully!</div>
                <?php endif; ?>

                <!-- Form -->
    <form method="POST" class="space-y-3">
    <input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

    <!-- Two column grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <div>
            <label class="block text-sm font-medium">Title</label>
            <input type="text" name="title" required
                   value="<?= htmlspecialchars($editData['title'] ?? '') ?>"
                   class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
        </div>

        <div>
            <label class="block text-sm font-medium">Definition</label>
            <textarea name="definition" rows="2"
                      class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($editData['definition'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">Scope</label>
            <textarea name="scope" rows="2"
                      class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($editData['scope'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">Goal</label>
            <textarea name="goal" rows="2"
                      class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($editData['goal'] ?? '') ?></textarea>
        </div>

        <div>
            <label class="block text-sm font-medium">Format</label>
            <textarea name="format" rows="2"
                      class="mt-1 w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($editData['format'] ?? '') ?></textarea>
        </div>

        

    </div>

    <!-- Buttons full-width -->
    <div class="flex gap-2 mt-4">
        <?php if ($editData): ?>
            <button type="submit" name="update_program"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Update</button>
            <a href="program.php"
               class="px-4 py-2 bg-gray-300 rounded-lg hover:bg-gray-400">Cancel</a>
        <?php else: ?>
            <button type="submit" name="add_program"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
        <?php endif; ?>
    </div>
</form>
</div>

            <!-- Existing Programs -->
            <div class="bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <h3 class="text-lg font-semibold mb-4">Existing Training Programs</h3>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full text-sm text-left">
                        <thead class="bg-gray-50 text-gray-700 uppercase text-xs">
                            <tr>
                                <th class="px-3 py-2">Title</th>
                                <th class="px-3 py-2">Definition</th>
                                <th class="px-3 py-2">Scope</th>
                                <th class="px-3 py-2">Goal</th>
                                <th class="px-3 py-2">Format</th>
                                <th class="px-3 py-2">Date</th>
                                <th class="px-3 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <?php while ($row = $programs->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['title']); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['definition']); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['scope']); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['goal']); ?></td>
                                    <td class="px-3 py-2"><?= htmlspecialchars($row['format']); ?></td>
                                    <td class="px-3 py-2"><?= $row['created_at']; ?></td>
                                    <td class="px-3 py-2 flex gap-3">
                                        <a href="?edit=<?= $row['id']; ?>" class="text-blue-600 hover:underline">Edit</a>
                                        <a href="?delete=<?= $row['id']; ?>" onclick="return confirm('Delete this program?')" class="text-red-600 hover:underline">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</main>

</div>

<script>lucide.createIcons();</script>
</body>
</html>
