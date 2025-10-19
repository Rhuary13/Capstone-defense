<?php
session_start();

// =========================
// Security: Participant-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../../auth/login.php");
    exit;
}

// ✅ Fix: Set participant name from session
$participantName = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? '');
if (empty($participantName)) {
    die("Participant name not found in session. Please log in again.");
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
// Handle Renewal Request
// =========================
if (isset($_POST['request_renewal'])) {
    $cert_id = intval($_POST['cert_id']);
    $note    = $conn->real_escape_string($_POST['note']);

    $conn->query("INSERT INTO renewal_requests (certificate_id, participant_name, note, requested_at, status)
                  VALUES ($cert_id, '$participantName', '$note', NOW(), 'Pending')");
    $msg = "🔄 Renewal request submitted. Please wait for admin approval.";
}

// =========================
// Fetch Participant Certificates
// =========================
$stmt = $conn->prepare("SELECT * FROM certificates WHERE recipient = ?");
$stmt->bind_param("s", $participantName);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Issuance & Renewal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-8 bg-gray-50">
    <h1 class="text-3xl font-extrabold text-gray-800 mb-6">📜 My Certificates</h1>

    <?php if (!empty($msg)): ?>
    <div class="mb-6 p-4 rounded-lg bg-blue-100 text-blue-700 border border-blue-300">
        <?= $msg ?>
    </div>
    <?php endif; ?>

    <div class="bg-white shadow-lg rounded-xl p-6">
        <?php if ($result->num_rows > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full border border-gray-200 rounded-lg">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
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
                            <td class="p-3 font-medium text-gray-800"><?= htmlspecialchars($row['cert_title']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['issue_date']) ?></td>
                            <td class="p-3"><?= htmlspecialchars($row['expiry_date']) ?></td>
                            <td class="p-3">
                                <?php
                                    $today = date('Y-m-d');
                                    if ($row['expiry_date'] < $today) {
                                        echo "<span class='bg-red-100 text-red-700 px-3 py-1 rounded-full text-sm'>Expired</span>";
                                    } else {
                                        echo "<span class='bg-green-100 text-green-700 px-3 py-1 rounded-full text-sm'>Valid</span>";
                                    }
                                ?>
                            </td>
                            <td class="p-3 space-y-2">
                                <!-- Download -->
                                <?php if (!empty($row['file_path'])): ?>
                                    <a href="<?= htmlspecialchars($row['file_path']) ?>" download
                                       class="block bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-center">
                                        ⬇️ Download
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-500">No file</span>
                                <?php endif; ?>

                                <!-- Renewal Request -->
                                <?php if ($row['expiry_date'] < $today): ?>
                                    <form method="POST" class="space-y-2">
                                        <input type="hidden" name="cert_id" value="<?= $row['id'] ?>">
                                        <textarea name="note" placeholder="Reason for renewal..."
                                                  class="w-full border rounded p-2 text-sm"></textarea>
                                        <button type="submit" name="request_renewal"
                                                class="w-full bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded">
                                            🔄 Request Renewal
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-gray-600">No certificates have been issued to you yet.</p>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
