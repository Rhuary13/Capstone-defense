<?php
session_start();

// DB Connection
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_NAME = "simulation_event_planning";

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

// Create table if missing
$conn->query("
    CREATE TABLE IF NOT EXISTS certificate_issuance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        participant_name VARCHAR(255) NOT NULL,
        certificate_title VARCHAR(255) NOT NULL,
        issued_date DATE DEFAULT NULL,
        renewed_date DATE DEFAULT NULL,
        status ENUM('Pending','Issued','Renewed') DEFAULT 'Pending',
        file_path VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Handle issuance
if (isset($_POST['issue'])) {
    $id = intval($_POST['id']);
    $date = date("Y-m-d");
    $stmt = $conn->prepare("UPDATE certificate_issuance SET status='Issued', issued_date=? WHERE id=?");
    $stmt->bind_param("si", $date, $id);
    $stmt->execute();
    $stmt->close();
}

// Handle renewal
if (isset($_POST['renew'])) {
    $id = intval($_POST['id']);
    $date = date("Y-m-d");
    $stmt = $conn->prepare("UPDATE certificate_issuance SET status='Renewed', renewed_date=? WHERE id=?");
    $stmt->bind_param("si", $date, $id);
    $stmt->execute();
    $stmt->close();
}

// Fetch data
$result = $conn->query("SELECT * FROM certificate_issuance ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Issuance & Renewal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-gray-100">

  <!-- Sidebar include -->
  <?php include "../sidebar.php"; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto h-screen p-6 bg-gray-50">
    <h1 class="text-2xl font-bold text-gray-700 mb-4">📑 Issuance & Renewal</h1>

    <div class="bg-white shadow rounded-lg p-6">
      <table class="min-w-full table-auto border-collapse">
        <thead>
          <tr class="bg-gray-200 text-gray-700 text-left">
            <th class="px-4 py-2">Participant</th>
            <th class="px-4 py-2">Certificate</th>
            <th class="px-4 py-2">Issued Date</th>
            <th class="px-4 py-2">Renewed Date</th>
            <th class="px-4 py-2">Status</th>
            <th class="px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php while($row = $result->fetch_assoc()): ?>
            <tr class="border-b hover:bg-gray-50">
              <td class="px-4 py-2"><?= htmlspecialchars($row['participant_name']) ?></td>
              <td class="px-4 py-2"><?= htmlspecialchars($row['certificate_title']) ?></td>
              <td class="px-4 py-2"><?= $row['issued_date'] ?: '-' ?></td>
              <td class="px-4 py-2"><?= $row['renewed_date'] ?: '-' ?></td>
              <td class="px-4 py-2">
                <span class="px-2 py-1 text-xs rounded 
                  <?= $row['status']=='Issued' ? 'bg-green-200 text-green-700' : 
                     ($row['status']=='Renewed' ? 'bg-blue-200 text-blue-700' : 'bg-yellow-200 text-yellow-700') ?>">
                  <?= $row['status'] ?>
                </span>
              </td>
              <td class="px-4 py-2 flex gap-2">
                <?php if ($row['status']=='Pending'): ?>
                  <form method="POST" onsubmit="return confirm('Issue this certificate?')">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" name="issue" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">Issue</button>
                  </form>
                <?php elseif ($row['status']=='Issued'): ?>
                  <form method="POST" onsubmit="return confirm('Renew this certificate?')">
                    <input type="hidden" name="id" value="<?= $row['id'] ?>">
                    <button type="submit" name="renew" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">Renew</button>
                  </form>
                <?php endif; ?>
                <?php if ($row['file_path']): ?>
                  <a href="<?= htmlspecialchars($row['file_path']) ?>" download class="bg-gray-500 text-white px-3 py-1 rounded hover:bg-gray-600">Export</a>
                <?php else: ?>
                  <span class="text-gray-400 italic">No file</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </main>

</body>
</html>
