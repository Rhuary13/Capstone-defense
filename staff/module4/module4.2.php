<?php
session_start();

/**
 * variable_config.php
 * Staff role: Modify variables to match training goals
 * Tech Stack: PHP + MySQL + TailwindCSS + JavaScript
 * DB: simulation_event_planning
 */

// --- Configuration ---
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

// --- Connect to DB ---
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// --- Create table if not exists ---
$conn->query("
    CREATE TABLE IF NOT EXISTS training_variables (
        id INT AUTO_INCREMENT PRIMARY KEY,
        variable_name VARCHAR(100) NOT NULL,
        variable_value VARCHAR(255) NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

// --- Handle update request ---
$message = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update'])) {
    $id = intval($_POST['id']);
    $value = trim($_POST['variable_value']);

    $stmt = $conn->prepare("UPDATE training_variables SET variable_value=? WHERE id=?");
    $stmt->bind_param("si", $value, $id);
    if ($stmt->execute()) {
        $message = "✅ Variable updated successfully!";
    } else {
        $message = "❌ Error updating variable.";
    }
    $stmt->close();
}

// --- Fetch all variables ---
$result = $conn->query("SELECT * FROM training_variables ORDER BY id ASC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Variable Configuration - Staff</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    
    <!-- Sidebar -->
    <?php include '../sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 p-6">
        <div class="bg-white shadow-lg rounded-2xl p-6">
            <h1 class="text-2xl font-bold mb-4 text-gray-800">⚙️ Variable Configuration</h1>
            <p class="text-gray-600 mb-6">Staff can modify variables to align with training goals.</p>

            <?php if ($message): ?>
                <div class="mb-4 p-3 rounded-lg 
                    <?= strpos($message, '✅') !== false ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="space-y-4">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <form method="POST" class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-gray-50 p-3 rounded-lg shadow-sm gap-3">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 w-full">
                            <label class="font-medium text-gray-700 w-40"><?= htmlspecialchars($row['variable_name']) ?></label>
                            <input type="text" 
                                name="variable_value" 
                                value="<?= htmlspecialchars($row['variable_value']) ?>" 
                                class="flex-1 px-3 py-1 border rounded-lg focus:outline-none focus:ring focus:ring-blue-200"
                                required>
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                            <button type="submit" name="update" 
                                    class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-1 rounded-lg shadow">
                                Update
                            </button>
                        </div>
                    </form>
                <?php endwhile; ?>
            </div>
        </div>
    </main>

    <script>
        // Simple confirmation before update
        document.querySelectorAll("form").forEach(form => {
            form.addEventListener("submit", (e) => {
                if (!confirm("Are you sure you want to update this variable?")) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
