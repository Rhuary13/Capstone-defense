<?php
// admin_credential_verification.php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "simulation_event_planning"; 
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Simple admin auth guard (replace with real auth)
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Secret key for signature
$APP_SECRET = "CHANGE_ME_SECRET";

// Generate unique serial number
function generate_serial($prefix = 'CERT', $length = 8) {
    $pool = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = $prefix . '-';
    for ($i = 0; $i < $length; $i++) $s .= $pool[random_int(0, strlen($pool)-1)];
    return $s;
}
function make_signature($serial, $secret) {
    return hash_hmac('sha256', $serial, $secret);
}

// =========================
// Assign serial
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_serial'])) {
    if (!$is_admin) { http_response_code(403); echo "Forbidden"; exit; }

    $cert_id = intval($_POST['cert_id']);
    $provided = trim($_POST['serial'] ?? '');
    $serial = $provided === '' ? generate_serial() : strtoupper(preg_replace('/[^A-Z0-9\-]/', '', $provided));
    $signature = make_signature($serial, $APP_SECRET);

    $stmt = $conn->prepare("UPDATE certificates SET serial_number = ?, qr_code = ? WHERE id = ?");
    $stmt->bind_param('ssi', $serial, $signature, $cert_id);
    $stmt->execute();
    $_SESSION['flash'] = $stmt->affected_rows > 0 ? "Serial assigned: $serial" : "Failed: " . $conn->error;
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// =========================
// Verify endpoint
// =========================
if (isset($_GET['action']) && $_GET['action'] === 'verify') {
    $serial = $_GET['serial'] ?? '';
    $sig = $_GET['sig'] ?? '';
    $serial = substr(preg_replace('/[^A-Z0-9\-]/i', '', $serial), 0, 128);
    $sig = preg_replace('/[^a-f0-9]/i', '', $sig);

    if ($serial === '' || $sig === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Missing serial or signature']);
        exit;
    }
    $expected = make_signature($serial, $APP_SECRET);
    if (!hash_equals($expected, $sig)) {
        echo json_encode(['ok' => false, 'message' => 'Signature mismatch']);
        exit;
    }
    $stmt = $conn->prepare("SELECT id, recipient, cert_title, serial_number, issue_date, expiry_date FROM certificates WHERE serial_number = ? LIMIT 1");
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
        echo json_encode(['ok' => false, 'message' => 'Serial not found']);
        exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Certificate verified', 'data' => $row]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin — Credential Verification</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 flex">
  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">Credential Verification — Admin</h1>
      <div>
        <?php if ($is_admin): ?>
          <span class="text-sm text-green-600">Signed in as Admin</span>
        <?php else: ?>
          <span class="text-sm text-red-600">Not signed in</span>
        <?php endif; ?>
      </div>
    </header>

    <?php if (!empty($_SESSION['flash'])): ?>
      <div class="mb-4 p-3 rounded bg-white shadow text-sm">
        <?= htmlspecialchars($_SESSION['flash']) ?>
      </div>
      <?php unset($_SESSION['flash']); ?>
    <?php endif; ?>

    <section>
      <div class="bg-white shadow rounded p-4">
        <h2 class="font-medium mb-4">Certificates</h2>
        <div class="overflow-x-auto">
          <table class="min-w-full table-auto">
            <thead>
              <tr class="text-left text-sm text-gray-600">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Recipient</th>
                <th class="px-3 py-2">Title</th>
                <th class="px-3 py-2">Serial</th>
                <th class="px-3 py-2">QR / Signature</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $rs = $conn->query("SELECT id, recipient, cert_title, serial_number, qr_code FROM certificates ORDER BY id DESC");
              while ($r = $rs->fetch_assoc()):
              ?>
              <tr class="border-t">
                <td class="px-3 py-2 text-sm"><?= htmlspecialchars($r['id']) ?></td>
                <td class="px-3 py-2 text-sm"><?= htmlspecialchars($r['recipient']) ?></td>
                <td class="px-3 py-2 text-sm"><?= htmlspecialchars($r['cert_title']) ?></td>
                <td class="px-3 py-2 text-sm"><?= htmlspecialchars($r['serial_number'] ?? '') ?></td>
                <td class="px-3 py-2 text-sm break-all"><?= htmlspecialchars($r['qr_code'] ?? '') ?></td>
                <td class="px-3 py-2 text-sm">
                  <?php if ($is_admin): ?>
                    <form method="post" class="inline-block">
                      <input type="hidden" name="cert_id" value="<?= htmlspecialchars($r['id']) ?>" />
                      <input type="text" name="serial" placeholder="Optional serial" class="p-1 border rounded text-sm" />
                      <button name="assign_serial" class="ml-2 px-2 py-1 bg-green-600 text-white rounded text-sm">Assign</button>
                    </form>
                    <?php if (!empty($r['serial_number']) && !empty($r['qr_code'])): ?>
                      <div class="mt-2">
                        <button class="copy-link-btn px-2 py-1 bg-gray-200 rounded text-sm" data-serial="<?= htmlspecialchars($r['serial_number']) ?>" data-sig="<?= htmlspecialchars($r['qr_code']) ?>">Copy link</button>
                        <button class="show-qr-btn ml-2 px-2 py-1 bg-gray-200 rounded text-sm" data-serial="<?= htmlspecialchars($r['serial_number']) ?>" data-sig="<?= htmlspecialchars($r['qr_code']) ?>">Show QR</button>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-xs text-red-500">No permission</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <div id="qrModal" class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-40 hidden">
      <div class="bg-white p-4 rounded shadow-lg w-80">
        <h3 class="font-medium mb-2">Verification QR</h3>
        <div id="qrContainer" class="mb-4 flex justify-center"></div>
        <div id="linkPreview" class="break-words text-sm mb-4"></div>
        <div class="flex justify-end">
          <button id="closeModal" class="px-3 py-1 bg-gray-200 rounded">Close</button>
        </div>
      </div>
    </div>
  </div>

<script>
function buildVerifyLink(serial, sig) {
  const base = window.location.origin + window.location.pathname;
  return base + '?action=verify&serial=' + encodeURIComponent(serial) + '&sig=' + encodeURIComponent(sig);
}
document.addEventListener('click', function(e){
  const target = e.target;
  if (target.classList.contains('copy-link-btn')) {
    const url = buildVerifyLink(target.dataset.serial, target.dataset.sig);
    navigator.clipboard.writeText(url).then(()=>alert('Verification link copied')).catch(()=>alert('Copy failed: ' + url));
  }
  if (target.classList.contains('show-qr-btn')) {
    const url = buildVerifyLink(target.dataset.serial, target.dataset.sig);
    const qrImg = document.createElement('img');
    qrImg.src = 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' + encodeURIComponent(url);
    const container = document.getElementById('qrContainer');
    container.innerHTML = '';
    container.appendChild(qrImg);
    document.getElementById('linkPreview').textContent = url;
    document.getElementById('qrModal').classList.remove('hidden');
  }
  if (target.id === 'closeModal') {
    document.getElementById('qrModal').classList.add('hidden');
  }
});
</script>
</body>
</html>
