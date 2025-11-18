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
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$message = "";

// =========================
// Login Processing
// =========================
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $dbPass = $row['password'];
        if ($password === $dbPass) {
            session_regenerate_id(true);
            $_SESSION['id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];

            if ($row['role'] === "admin") header("Location: ../admin/dashboard.php");
            elseif ($row['role'] === "staff") header("Location: ../staff/dashboard.php");
            else header("Location: ../user/dashboard.php");
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
<style>
/* Floating labels */
.input-group {
  position: relative;
}
.input-label {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #9ca3af;
  transition: 0.2s ease all;
  pointer-events: none;
}
input:focus + .input-label,
input:not(:placeholder-shown) + .input-label {
  top: -0.5rem;
  left: 0.5rem;
  font-size: 0.75rem;
  color: #3b82f6;
  background: white;
  padding: 0 0.25rem;
}
</style>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600 p-4">

<div class="relative w-full max-w-md bg-white rounded-3xl shadow-2xl p-8 overflow-hidden">
    <!-- Decorative background circles -->
    <div class="absolute -top-32 -right-32 w-64 h-64 bg-purple-300 rounded-full opacity-30"></div>
    <div class="absolute -bottom-32 -left-32 w-64 h-64 bg-blue-300 rounded-full opacity-30"></div>

    <h2 class="text-3xl font-bold text-center text-gray-800 mb-6">Welcome Back</h2>

    <?php if(!empty($message)): ?>
        <div class="mb-4 px-4 py-2 text-center text-red-700 bg-red-100 rounded-lg">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <!-- Username -->
        <div class="input-group">
            <input type="text" name="username" placeholder=" " required
                class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <label class="input-label">Username</label>
        </div>

        <!-- Password -->
        <div class="input-group relative">
            <input type="password" id="password" name="password" placeholder=" " required
                class="w-full border rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <label class="input-label">Password</label>
            <button type="button" onclick="togglePassword()"
                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-700">
                👁️
            </button>
        </div>

        <!-- Login Button -->
        <button type="submit"
            class="w-full bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white py-2 rounded-lg font-semibold transition duration-300">
            Login
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-gray-600">
        Don’t have an account? <a href="register.php" class="text-blue-500 font-medium underline hover:text-blue-700">Register here</a>
    </p>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
