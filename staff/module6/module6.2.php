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

// =========================
// Security: Staff-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'staff') {
    header("Location: ../../auth/login.php");
    exit;
}

// =========================
// Fetch Certificates added by Admin
// =========================
$result = $conn->query("SELECT * FROM certificates ORDER BY issue_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Certificates - Staff View</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .certificate-frame {
      width: 800px; height: 600px;
      background: #fff;
      border: 6px solid #d4af37;
      border-radius: 12px;
      padding: 40px;
      margin: auto;
      position: relative;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    .signature-line { border-top: 1px solid #333; width: 200px; margin: 0 auto; }
  </style>
</head>
<body class="h-screen flex bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
  <h1 class="text-2xl font-bold text-gray-700 mb-6">📜 Certificates (from Admin)</h1>

  <?php if ($result->num_rows > 0): ?>
    <div class="grid gap-8">
      <?php while ($row = $result->fetch_assoc()): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
          <div class="certificate-frame">
            <h1 class="text-4xl font-extrabold text-center tracking-wide">CERTIFICATE</h1>
            <h2 class="text-xl text-center text-yellow-700 mt-2 mb-6">
              <?= htmlspecialchars($row['cert_title']) ?>
            </h2>

            <p class="text-center text-gray-600 mb-6 tracking-wide uppercase">
              AWARDED TO
            </p>

            <!-- Recipient -->
            <h3 class="text-3xl text-center font-bold text-yellow-800 italic mb-4">
              <?= htmlspecialchars($row['recipient']) ?>
            </h3>

            <!-- Description -->
            <p class="text-center text-gray-700 max-w-3xl mx-auto mb-8">
              <?= htmlspecialchars($row['description']) ?>
            </p>

            <!-- Signatories -->
            <div class="flex justify-between mt-12 px-12">
              <div class="text-center">
                <p class="font-bold uppercase"><?= htmlspecialchars($row['signatory1']) ?></p>
                <p class="text-gray-600">Signatory</p>
              </div>
              <div class="text-center">
                <p class="font-bold uppercase"><?= htmlspecialchars($row['signatory2']) ?></p>
                <p class="text-gray-600">Signatory</p>
              </div>
            </div>

            <!-- Dates -->
            <div class="absolute bottom-4 left-0 right-0 text-center text-sm text-gray-500">
              Issued: <?= htmlspecialchars($row['issue_date']) ?> |
              Expires: <?= htmlspecialchars($row['expiry_date']) ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <p class="text-gray-600">No certificates have been added by Admin yet.</p>
  <?php endif; ?>
</main>
</body>
</html>
<?php $conn->close(); ?>
