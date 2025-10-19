<?php
// staff_assessment.php
session_start();

// --- Database Connection ---
$host = "localhost";
$user = "root";
$pass = "";
$db   = "training_management"; 

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// --- Ensure tables exist ---
$conn->query("CREATE TABLE IF NOT EXISTS assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  validity_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS assessment_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  assessment_id INT NOT NULL,
  question TEXT NOT NULL,
  type ENUM('checklist','multiple_choice','true_false') DEFAULT 'checklist',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assessment_id) REFERENCES assessments(id) ON DELETE CASCADE
)");

// --- Handle Assessment Creation ---
if (isset($_POST['create_assessment'])) {
    $title       = $_POST['title'];
    $description = $_POST['description'];
    $validity    = $_POST['validity_date'];

    $stmt = $conn->prepare("INSERT INTO assessments (title, description, validity_date) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $title, $description, $validity);
    $stmt->execute();
    $stmt->close();
}

// --- Handle Question Creation ---
if (isset($_POST['add_question'])) {
    $assessment_id = $_POST['assessment_id'];
    $question      = $_POST['question'];
    $type          = $_POST['type'];

    $stmt = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question, type) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $assessment_id, $question, $type);
    $stmt->execute();
    $stmt->close();
}

// --- Fetch all assessments ---
$assessments = $conn->query("SELECT * FROM assessments ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Staff Assessment & Evaluation</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">

  <!-- Sidebar -->
  <?php include '../sidebar.php'; ?> 

  <!-- Main Content -->
  <div class="flex-1 p-6 overflow-y-auto">
    <header class="bg-indigo-700 text-white p-4 rounded-lg shadow mb-6 text-xl font-bold">
      Assessment & Evaluation - Staff
    </header>

    <!-- Create Assessment -->
    <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
      <h2 class="text-lg font-semibold mb-4">Create New Assessment</h2>
      <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1">Assessment Title</label>
          <input type="text" name="title" required class="w-full p-2 border rounded-lg focus:ring focus:ring-indigo-300">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Validity Date</label>
          <input type="date" name="validity_date" class="w-full p-2 border rounded-lg focus:ring focus:ring-indigo-300">
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium mb-1">Description</label>
          <textarea name="description" rows="3" class="w-full p-2 border rounded-lg focus:ring focus:ring-indigo-300"></textarea>
        </div>
        <div class="md:col-span-2 flex justify-end">
          <button type="submit" name="create_assessment" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg shadow">
            Save Assessment
          </button>
        </div>
      </form>
    </div>

    <!-- Assessments List -->
    <div class="bg-white rounded-2xl shadow-md p-6">
      <h2 class="text-lg font-semibold mb-4">Manage Assessments</h2>
      <?php while ($a = $assessments->fetch_assoc()): ?>
        <div class="border rounded-lg p-4 mb-4">
          <h3 class="text-md font-bold text-indigo-700"><?php echo htmlspecialchars($a['title']); ?></h3>
          <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($a['description']); ?></p>
          <p class="text-sm"><strong>Validity:</strong> <?php echo htmlspecialchars($a['validity_date']); ?></p>

          <!-- Add Question Form -->
          <form method="POST" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
            <input type="hidden" name="assessment_id" value="<?php echo $a['id']; ?>">
            <input type="text" name="question" placeholder="Enter question/checklist item" required class="p-2 border rounded-lg col-span-2 focus:ring focus:ring-indigo-300">
            <select name="type" class="p-2 border rounded-lg">
              <option value="checklist">Checklist</option>
              <option value="multiple_choice">Multiple Choice</option>
              <option value="true_false">True/False</option>
            </select>
            <button type="submit" name="add_question" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg shadow">
              Add
            </button>
          </form>

          <!-- Show Questions -->
          <?php
            $questions = $conn->query("SELECT * FROM assessment_questions WHERE assessment_id = ".$a['id']." ORDER BY created_at ASC");
            if ($questions->num_rows > 0):
          ?>
          <ul class="mt-3 list-disc list-inside text-sm">
            <?php while ($q = $questions->fetch_assoc()): ?>
              <li><?php echo htmlspecialchars($q['question']); ?> <span class="text-gray-500">(<?php echo $q['type']; ?>)</span></li>
            <?php endwhile; ?>
          </ul>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    </div>
  </div>
</body>
</html>
<?php $conn->close(); ?>
