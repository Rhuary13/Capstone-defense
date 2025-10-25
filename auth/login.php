<?php
session_start();

// =========================
// Database connection
// =========================
$host = "localhost";
$user = "root";
$pass = "";
$db   = "auth";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = ""; // Initialize feedback message

// =========================
// Login Processing
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Fetch user
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $dbPass = $row['password'];

        // Since you're using plaintext, direct comparison only
        if ($password === $dbPass) {

            // Regenerate session for security
            session_regenerate_id(true);

            // Store user info in session
            $_SESSION['id']       = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role']     = $row['role'];

            // Redirect based on role
            if ($row['role'] === "admin") {
                header("Location: ../admin/dashboard.php");
            } elseif ($row['role'] === "staff") {
                header("Location: ../staff/dashboard.php");
            } else {
                header("Location: ../user/dashboard.php");
            }
            exit;
        } else {
            $message = "Invalid password.";
        }
    } else {
        $message = "User not found.";
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gradient-to-r from-blue-500 to-purple-600">

<div class="bg-white p-8 rounded-2xl shadow-lg w-96">
    <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Login</h2>

    <?php if(!empty($message)): ?>
        <div class="mb-4 text-red-600 text-sm text-center">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-4">
            <label class="block text-gray-700">Username</label>
            <input type="text" name="username" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>
        <div class="mb-4">
            <label class="block text-gray-700">Password</label>
            <input type="password" name="password" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>
        <button type="submit" 
                class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg transition duration-300">
            Login
        </button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
        Don’t have an account? <a href="register.php" class="text-blue-500 underline">Register here</a>
    </p>
</div>

</body>
</html>
