<?php
session_start();

// =========================
// Security: Admin-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// =========================
// Database Connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// =========================
// Handle New Issuance
// =========================
if (isset($_POST['issue_cert'])) {
    $recipient   = $conn->real_escape_string($_POST['recipient']);
    $cert_title  = $conn->real_escape_string($_POST['cert_title']);
    $issue_date  = $_POST['issue_date'];
    $expiry_date = $_POST['expiry_date'];

    if (strtotime($expiry_date) > strtotime($issue_date)) {
        $conn->query("INSERT INTO certificates (recipient, cert_title, issue_date, expiry_date) 
                      VALUES ('$recipient','$cert_title','$issue_date','$expiry_date')");
        $msg = "✅ Certificate issued successfully!";
    } else {
        $msg = "❌ Expiry date must be later than issue date.";
    }
}

// =========================
// Handle Renewal
// =========================
if (isset($_POST['renew_cert'])) {
    $cert_id     = intval($_POST['cert_id']);
    $new_expiry  = $_POST['new_expiry'];

    $conn->query("UPDATE certificates SET expiry_date = '$new_expiry' WHERE id = $cert_id");
    $msg = "🔄 Certificate renewed successfully!";
}

// =========================
// Fetch Certificates
// =========================
$result = $conn->query("SELECT * FROM certificates");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Issuance & Renewal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<?php include '../sidebar.php'; ?>

<main class=" overflow-y-auto p-8 bg-gray-50">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">📜 Certificate Issuance & Renewal</h1>

    <?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-lg 
                <?= str_contains($msg,'❌') ? 'bg-red-100 text-red-700 border border-red-300' : 'bg-green-100 text-green-700 border border-green-300' ?>">
        <?= $msg ?>
    </div>
    <?php endif; ?>

    <!-- Issue New Certificate -->
    <div class="bg-white shadow-lg rounded-xl p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">➕ Issue New Certificate</h2>
        <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" name="recipient" placeholder="Recipient Name"
                   class="p-3 border rounded-lg focus:ring focus:ring-blue-300" required>
            <input type="text" name="cert_title" placeholder="Certificate Title"
                   class="p-3 border rounded-lg focus:ring focus:ring-blue-300" required>
            <input type="date" name="issue_date" class="p-3 border rounded-lg focus:ring focus:ring-blue-300" required>
            <input type="date" name="expiry_date" class="p-3 border rounded-lg focus:ring focus:ring-blue-300" required>
            <div class="md:col-span-2">
                <button type="submit" name="issue_cert"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg shadow">
                    Issue Certificate
                </button>
            </div>
        </form>
    </div>

    <!-- Certificate List -->
    <div class="bg-white shadow-lg rounded-xl p-6">
        <h2 class="text-xl font-semibold text-gray-700 mb-4">📂 Certificates</h2>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="p-3 text-left">Recipient</th>
                        <th class="p-3 text-left">Title</th>
                        <th class="p-3">Issue Date</th>
                        <th class="p-3">Expiry Date</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($row['recipient']) ?></td>
                        <td class="p-3 text-gray-600"><?= htmlspecialchars($row['cert_title']) ?></td>
                        <td class="p-3"><?= $row['issue_date'] ?></td>
                        <td class="p-3"><?= $row['expiry_date'] ?></td>
                        <td class="p-3">
                            <?php
                                $today = date('Y-m-d');
                                if ($row['expiry_date'] < $today) {
                                    echo "<span class='bg-red-100 text-red-700 px-2 py-1 rounded-full text-sm'>Expired</span>";
                                } else {
                                    echo "<span class='bg-green-100 text-green-700 px-2 py-1 rounded-full text-sm'>Valid</span>";
                                }
                            ?>
                        </td>
                        <td class="p-3 space-x-2">
                            <!-- Renew Form -->
                            <form method="POST" class="inline">
                                <input type="hidden" name="cert_id" value="<?= $row['id'] ?>">
                                <input type="date" name="new_expiry"
                                       class="border rounded p-1 text-sm" required>
                                <button type="submit" name="renew_cert"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">
                                    Renew
                                </button>
                            </form>
                            <!-- Verify Link -->
                            <a href="module6.4.php?serial=<?= urlencode($row['serial_number'] ?? '') ?>"
                               class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">
                                Verify
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
// Client-side date validation for issuance form
document.querySelector("form").addEventListener("submit", function(e) {
    const issue = document.querySelector("input[name='issue_date']").value;
    const expiry = document.querySelector("input[name='expiry_date']").value;
    if (issue && expiry && new Date(expiry) <= new Date(issue)) {
        e.preventDefault();
        alert("❌ Expiry date must be later than Issue date!");
    }
});
</script>
</body>
</html>
