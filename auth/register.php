<?php
session_start();

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db   = "auth";
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);

    // Default role = user
    $role = 'user';

    // Check if username already exists
    $check = "SELECT * FROM users WHERE username='$username' LIMIT 1";
    $result = mysqli_query($conn, $check);

    if (mysqli_num_rows($result) > 0) {
        $message = "Username already taken.";
    } else {
        // Insert new user
        $sql = "INSERT INTO users (username, password, role) 
                VALUES ('$username', '$password', '$role')";

        if (mysqli_query($conn, $sql)) {
            $message = "Registration successful. You can now <a href='login.php' class='text-blue-500 underline'>Login</a>.";
        } else {
            $message = "Error: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex items-center justify-center min-h-screen bg-gradient-to-r from-green-500 to-blue-600">
  <div class="bg-white p-8 rounded-2xl shadow-lg w-96">
    <h2 class="text-2xl font-bold mb-6 text-center text-gray-800">Register</h2>

    <?php if (!empty($message)) : ?>
      <div class="mb-4 text-center text-sm text-red-600"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="mb-4">
        <label class="block text-gray-700">Username</label>
        <input type="text" name="username" required
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
      </div>
      <div class="mb-4">
        <label class="block text-gray-700">Password</label>
        <input type="password" name="password" required
          class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400">
      </div>
      <button type="submit"
        class="w-full bg-green-500 text-white py-2 rounded-lg hover:bg-green-600 transition duration-300">Register</button>
    </form>

    <p class="mt-4 text-center text-sm text-gray-600">
      Already have an account?
      <a href="login.php" class="text-blue-500 underline">Login</a>
    </p>
  </div>
</body>
</html>
