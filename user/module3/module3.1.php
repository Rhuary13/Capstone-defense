<?php
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
// Ensure tables exist
// =========================
$conn->query("CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE,
    location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    participant_id INT NOT NULL,
    event_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_registration (participant_id, event_id)
)");

$conn->query("CREATE TABLE IF NOT EXISTS participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    password VARCHAR(255) NOT NULL,
    status ENUM('Pending','Verified') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// =========================
// Handle manual participant registration by staff
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_participant'])) {
    $name  = $conn->real_escape_string($_POST['full_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $pass  = password_hash($_POST['password'], PASSWORD_BCRYPT);

    $conn->query("INSERT INTO participants (full_name, email, phone, password) 
                  VALUES ('$name', '$email', '$phone', '$pass')");
}

// =========================
// Handle verification update
// =========================
if (isset($_GET['verify_id'])) {
    $id = intval($_GET['verify_id']);
    $conn->query("UPDATE participants SET status='Verified' WHERE id=$id");
    header("Location: module3.1.php");
    exit();
}

// =========================
// Handle participant registering for an event
// =========================
$registration_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['event_id'])) {
    $event_id = intval($_POST['event_id']);
    $participant_id = 1; // Replace with session later

    $stmt = $conn->prepare("INSERT IGNORE INTO registrations (participant_id, event_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $participant_id, $event_id);
    if ($stmt->execute()) {
        $registration_message = "✅ Successfully registered for the event!";
    } else {
        $registration_message = "❌ Error: " . $stmt->error;
    }
    $stmt->close();
}

// =========================
// Fetch participants & events
// =========================
$participants = $conn->query("SELECT * FROM participants ORDER BY created_at DESC");
$events = $conn->query("SELECT * FROM events ORDER BY event_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Registration Portal</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-screen flex bg-slate-50 font-sans text-slate-800 overflow-hidden">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 overflow-y-auto p-6 lg:p-10">
    
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl lg:text-3xl font-semibold">Registration Portal</h1>
      <p class="text-sm text-slate-500 mt-1">Assist participants with registration, troubleshooting, and data verification.</p>
    </div>

    <!-- Manual Registration Form -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Manual Registration</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Full Name</label>
          <input type="text" name="full_name" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Email</label>
          <input type="email" name="email" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Phone</label>
          <input type="text" name="phone" 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Password</label>
          <input type="password" name="password" required 
                 class="w-full border rounded-md p-2 text-sm focus:ring focus:ring-indigo-200">
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" name="add_participant" 
                  class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
            Register Participant
          </button>
        </div>
      </form>
    </div>

    <!-- Participant List -->
    <div class="bg-white shadow rounded-lg p-6 mb-8">
      <h2 class="text-lg font-medium mb-4">Participants</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="text-left bg-slate-100">
              <th class="p-3 border-b">Name</th>
              <th class="p-3 border-b">Email</th>
              <th class="p-3 border-b">Phone</th>
              <th class="p-3 border-b">Status</th>
              <th class="p-3 border-b">Registered</th>
              <th class="p-3 border-b">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($participants && $participants->num_rows > 0): ?>
              <?php while ($p = $participants->fetch_assoc()): ?>
                <tr class="hover:bg-slate-50">
                  <td class="p-3 border-b"><?php echo htmlspecialchars($p['full_name']); ?></td>
                  <td class="p-3 border-b"><?php echo htmlspecialchars($p['email']); ?></td>
                  <td class="p-3 border-b"><?php echo htmlspecialchars($p['phone']); ?></td>
                  <td class="p-3 border-b">
                    <span class="px-2 py-1 text-xs rounded 
                                 <?php echo ($p['status'] === 'Verified') ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                      <?php echo htmlspecialchars($p['status']); ?>
                    </span>
                  </td>
                  <td class="p-3 border-b">
                    <?php 
                      if (!empty($p['created_at']) && $p['created_at'] !== '0000-00-00 00:00:00') {
                          echo date("Y-m-d", strtotime($p['created_at']));
                      } else {
                          echo "—";
                      }
                    ?>
                  </td>
                  <td class="p-3 border-b">
                    <?php if ($p['status'] !== 'Verified'): ?>
                      <a href="?verify_id=<?php echo $p['id']; ?>" 
                         class="text-indigo-600 hover:underline">Verify</a>
                    <?php else: ?>
                      <span class="text-slate-400">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr>
                <td colspan="6" class="p-4 text-center text-slate-500">No participants registered yet.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Event Registration Section -->
    <div class="bg-white shadow rounded-lg p-6">
      <h2 class="text-lg font-medium mb-4">Event Registration</h2>

      <?php if (!empty($registration_message)): ?>
        <div class="mb-4 p-3 rounded bg-green-100 text-green-700">
          <?php echo $registration_message; ?>
        </div>
      <?php endif; ?>

      <?php if ($events && $events->num_rows > 0): ?>
        <div class="space-y-4">
          <?php while ($event = $events->fetch_assoc()): ?>
            <div class="p-4 border rounded-lg bg-slate-50">
              <h3 class="text-lg font-semibold"><?php echo htmlspecialchars($event['title']); ?></h3>
              
              <p class="text-sm mt-2">
                📍 <?php echo htmlspecialchars($event['location']); ?> | 
                📅 <?php echo date("F j, Y", strtotime($event['event_date'])); ?>
              </p>
              <form method="POST" class="mt-3">
                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                <button type="submit" 
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                  Register Now
                </button>
              </form>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p class="text-slate-500">No events available at the moment.</p>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
