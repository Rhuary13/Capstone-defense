<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ----------------------
// AUTH CHECK
// ----------------------
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../auth/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ----------------------
// FETCH PARTICIPANT NAME
// ----------------------
$sql = "SELECT name FROM participants WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$participant = $result->fetch_assoc();
$participant_name = $participant ? $participant['name'] : "Unknown User";
$stmt->close();

// ----------------------
// FETCH CERTIFICATES FOR PARTICIPANT
// ----------------------
$sql = "SELECT * FROM certificates WHERE recipient = ? ORDER BY issue_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $participant_name);
$stmt->execute();
$result = $stmt->get_result();
$certificates = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Certificates</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="h-screen flex overflow-hidden bg-gray-100">

  <!-- Sidebar -->
  <aside class="w-64 bg-gradient-to-b from-blue-700 to-blue-600 text-white flex-shrink-0 h-full overflow-y-auto">
    <?php include '../sidebar.php'; ?>
  </aside>

  <!-- Navbar -->
  <nav class="bg-white shadow px-6 py-4 flex justify-between items-center fixed top-0 left-64 right-0 z-10">
    <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
      <i data-lucide="award" class="w-8 h-8 text-blue-600"></i>
      My Certificates
    </h1>
  </nav>

  <!-- Main Content -->
  <main class="flex-1 h-full overflow-y-auto p-8 pt-20">
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-xl font-semibold text-gray-800 mb-4">Hello, <?= htmlspecialchars($participant_name) ?></h2>
      <p class="text-gray-700">Here you can view and download your earned certificates.</p>
    </div>

    <?php if (empty($certificates)): ?>
      <div class="bg-yellow-50 p-6 border-l-4 border-yellow-500 rounded text-yellow-800">
        <p class="font-semibold">No certificates yet</p>
        <p>You will see your certificates here once the admin issues them.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($certificates as $cert): ?>
          <div class="bg-white rounded-xl shadow p-6 flex flex-col justify-between">
            <div>
              <h3 class="text-lg font-bold text-blue-700 mb-2"><?= htmlspecialchars($cert['cert_title']) ?></h3>
              <p class="text-gray-600 mb-2"><?= htmlspecialchars($cert['description']) ?></p>
              <p class="text-sm text-gray-500 mb-1"><strong>Issued:</strong> <?= htmlspecialchars($cert['issue_date']) ?></p>
              <p class="text-sm text-gray-500 mb-4"><strong>Valid Until:</strong> <?= htmlspecialchars($cert['expiry_date']) ?></p>
            </div>
            <div class="flex justify-between items-center">
              <button onclick="viewCertificate(<?= $cert['id'] ?>)" 
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <i data-lucide="eye" class="w-5 h-5"></i> View
              </button>
              <a href="download_certificate.php?id=<?= $cert['id'] ?>" 
                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center gap-2">
                <i data-lucide="download" class="w-5 h-5"></i> Download
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <script>
    lucide.createIcons();

    function viewCertificate(certId) {
      window.open("view_certificate.php?id=" + certId, "_blank", "width=900,height=700");
    }
  </script>
</body>
</html>
