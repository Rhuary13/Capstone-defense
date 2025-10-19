<?php
session_start();

// ========================
// Database Connection
// ========================
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Handle verification request
$verificationResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_value'])) {
    $search = trim($_POST['search_value']);

    $stmt = $conn->prepare("
        SELECT * FROM certificate_issuance 
        WHERE id = ? OR participant_name LIKE ?
        LIMIT 1
    ");
    $likeSearch = "%$search%";
    $stmt->bind_param("is", $search, $likeSearch);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check validity
        $status = $row['status'];
        $expiryDate = $row['renewed_date'] ?: $row['issued_date'];
        $isValid = false;

        if ($expiryDate) {
            $expiry = strtotime($expiryDate . " +1 year");
            $isValid = $expiry > time();
        }

        $verificationResult = [
            "found" => true,
            "participant" => $row['participant_name'],
            "certificate" => $row['certificate_title'],
            "issued" => $row['issued_date'] ?: "-",
            "renewed" => $row['renewed_date'] ?: "-",
            "status" => $status,
            "valid" => $isValid
        ];
    } else {
        $verificationResult = ["found" => false];
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Credential Verification</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-gray-100">

  <!-- Sidebar -->
  <?php include "../sidebar.php"; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto h-screen p-6 bg-gray-50">
    <h1 class="text-2xl font-bold text-gray-700 mb-6">🔍 Credential Verification</h1>

    <div class="bg-white shadow-md rounded-lg p-6">
      <form method="POST" class="flex gap-3 mb-6">
        <input type="text" name="search_value" placeholder="Enter Certificate ID or Participant Name"
               class="flex-1 border p-2 rounded focus:ring focus:ring-blue-300"
               required>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
          Verify
        </button>
      </form>

      <!-- Results -->
      <?php if ($verificationResult): ?>
        <?php if ($verificationResult['found']): ?>
          <div class="p-4 border rounded-lg shadow-sm 
            <?= $verificationResult['valid'] ? 'bg-green-50 border-green-400' : 'bg-red-50 border-red-400' ?>">
            
            <h2 class="text-lg font-bold mb-2">Certificate Details</h2>
            <p><strong>Participant:</strong> <?= htmlspecialchars($verificationResult['participant']) ?></p>
            <p><strong>Certificate:</strong> <?= htmlspecialchars($verificationResult['certificate']) ?></p>
            <p><strong>Issued Date:</strong> <?= $verificationResult['issued'] ?></p>
            <p><strong>Renewed Date:</strong> <?= $verificationResult['renewed'] ?></p>
            <p><strong>Status:</strong> <?= $verificationResult['status'] ?></p>

            <p class="mt-3 font-bold 
              <?= $verificationResult['valid'] ? 'text-green-700' : 'text-red-700' ?>">
              <?= $verificationResult['valid'] ? '✅ This certificate is VALID' : '❌ This certificate is INVALID or EXPIRED' ?>
            </p>
          </div>
        <?php else: ?>
          <p class="p-4 bg-yellow-50 border border-yellow-400 text-yellow-700 rounded">
            ⚠️ No matching certificate found.
          </p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>

</body>
</html>
