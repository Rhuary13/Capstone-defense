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
// Security: Admin-only
// =========================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../auth/login.php");
    exit;
}

// Handle file upload
$uploadedFilePath = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['customCert'])) {
    $targetDir = __DIR__ . "/../../uploads/certificates/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $fileName = time() . "_" . basename($_FILES["customCert"]["name"]);
    $targetFile = $targetDir . $fileName;
    if (move_uploaded_file($_FILES["customCert"]["tmp_name"], $targetFile)) {
        $uploadMsg = "File uploaded successfully: " . htmlspecialchars($fileName);
        $uploadedFilePath = "../../uploads/certificates/" . $fileName;
    } else {
        $uploadMsg = "Error uploading file.";
    }
}

// Handle Save Certificate
if (isset($_POST['save_certificate'])) {
    $title       = $conn->real_escape_string($_POST['cert_title']);
    $recipient   = $conn->real_escape_string($_POST['recipient']);
    $description = $conn->real_escape_string($_POST['description']);
    $sign1       = $conn->real_escape_string($_POST['signatory1']);
    $sign2       = $conn->real_escape_string($_POST['signatory2']);
    $issueDate   = $conn->real_escape_string($_POST['issue_date']);
    $expiryDate  = $conn->real_escape_string($_POST['expiry_date']);

    $sql = "INSERT INTO certificates 
        (cert_title, recipient, description, signatory1, signatory2, issue_date, expiry_date)
        VALUES 
        ('$title', '$recipient', '$description', '$sign1', '$sign2', '$issueDate', '$expiryDate')";

    if ($conn->query($sql)) {
        $msg = "✅ Certificate saved successfully!";
    } else {
        $msg = "❌ Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Certificate Designer</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:wght@700&family=Roboto&display=swap');
    .certificate-frame {
      width: 1123px; height: 794px;
      background: #fff;
      border: 8px solid #d4af37;
      border-radius: 12px;
      padding: 60px;
      position: relative;
      box-shadow: 0 0 15px rgba(0,0,0,0.15);
    }
    .signature-line { border-top: 1px solid #333; width: 220px; margin: 0 auto; }
  </style>
</head>
<body class="flex h-screen bg-gray-100">

<!-- Sidebar -->
<?php include '../sidebar.php'; ?>

<!-- Main Content -->
<main class="flex-1 overflow-y-auto p-6 bg-gray-50">
  <h1 class="text-2xl font-bold text-gray-700 mb-6">Certificate Designer</h1>

  <?php if (!empty($msg)): ?>
    <p class="mb-4 font-semibold text-green-600"><?= $msg ?></p>
  <?php endif; ?>

  <div class="bg-white shadow-md rounded-lg p-6">
    <h2 class="text-xl font-semibold mb-4">Customize Certificate</h2>

    <!-- File Upload -->
    <form method="POST" enctype="multipart/form-data" class="mb-6">
      <label class="block text-gray-700 font-semibold mb-2">Upload Ready-Made Certificate (PDF, Word, Image)</label>
      <input type="file" name="customCert" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg"
             class="w-full p-2 border rounded mb-2">
      <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
        Upload File
      </button>
      <?php if (!empty($uploadMsg)): ?>
        <p class="mt-2 text-sm text-blue-600"><?= $uploadMsg ?></p>
      <?php endif; ?>
    </form>

    <!-- Show uploaded or default certificate -->
    <?php if ($uploadedFilePath): ?>
      <div class="mt-6 bg-white shadow rounded p-4">
        <h2 class="text-lg font-semibold mb-4">Uploaded Certificate</h2>
        <?php if (preg_match('/\.(jpg|jpeg|png)$/i', $uploadedFilePath)): ?>
          <img src="<?= $uploadedFilePath ?>" alt="Uploaded Certificate" class="mx-auto max-w-full shadow-lg">
        <?php elseif (preg_match('/\.pdf$/i', $uploadedFilePath)): ?>
          <iframe src="<?= $uploadedFilePath ?>" class="w-full h-[800px] border"></iframe>
        <?php elseif (preg_match('/\.(doc|docx)$/i', $uploadedFilePath)): ?>
          <p class="text-blue-600">Word document uploaded:
            <a href="<?= $uploadedFilePath ?>" target="_blank" class="underline">Open File</a>
          </p>
        <?php else: ?>
          <p class="text-red-600">Unsupported file type.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div id="certificate" class="certificate-frame mx-auto">
        <h1 class="text-5xl font-extrabold text-center tracking-wide">CERTIFICATE</h1>
        <h2 class="text-2xl text-center text-yellow-700 mt-2 mb-6">OF RECOGNITION</h2>
        <p class="text-center text-gray-600 mb-6 tracking-wide uppercase">
          THE FOLLOWING AWARD IS GIVEN TO
        </p>

        <!-- Editable Recipient -->
        <h3 id="recipient" class="editable text-4xl text-center font-bold text-yellow-800 italic mb-4" contenteditable="true">
          Dani Martinez
        </h3>
        <!-- Editable Description -->
        <p id="description" class="editable text-center text-gray-700 max-w-3xl mx-auto" contenteditable="true">
          This certificate is given to Dani Martinez for his achievement in the field of education and proves that he is competent in his field.
        </p>
        <!-- Editable Signatories -->
        <div class="flex justify-between mt-16 px-16">
          <div id="signatory1" class="editable text-center" contenteditable="true">
            <p class="font-bold uppercase">Howard Ong</p>
            <p class="text-gray-600">Head Master</p>
          </div>
          <div id="signatory2" class="editable text-center" contenteditable="true">
            <p class="font-bold uppercase">Neil Tran</p>
            <p class="text-gray-600">Mentor</p>
          </div>
        </div>
      </div>

      <!-- Save form -->
      <form method="POST" action="module6.2.php" class="mt-4">
        <input type="hidden" name="cert_title" id="certTitleInput">
        <input type="hidden" name="recipient" id="recipientInput">
        <input type="hidden" name="description" id="descriptionInput">
        <input type="hidden" name="signatory1" id="sign1Input">
        <input type="hidden" name="signatory2" id="sign2Input">
        <input type="hidden" name="issue_date" id="issueDateInput">
        <input type="hidden" name="expiry_date" id="expiryDateInput">

        <button type="submit" name="save_certificate"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
          Save Certificate
        </button>
      </form>
    <?php endif; ?>
  </div>
</main>

<script>
document.querySelector("form:last-of-type")?.addEventListener("submit", function () {
  document.getElementById("certTitleInput").value = "Certificate of Recognition";
  document.getElementById("recipientInput").value = document.getElementById("recipient").innerText;
  document.getElementById("descriptionInput").value = document.getElementById("description").innerText;
  document.getElementById("sign1Input").value = document.getElementById("signatory1").innerText;
  document.getElementById("sign2Input").value = document.getElementById("signatory2").innerText;

  // auto issue & expiry
  document.getElementById("issueDateInput").value = new Date().toISOString().split("T")[0];
  let expiry = new Date();
  expiry.setFullYear(expiry.getFullYear() + 1);
  document.getElementById("expiryDateInput").value = expiry.toISOString().split("T")[0];
});
</script>
</body>
</html>
