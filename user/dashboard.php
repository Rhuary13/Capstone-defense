<?php
session_start();
$host = "localhost";
$user = "root";
$pass = ""; // or your MySQL password if set
$db   = "auth"; // <-- use your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Training Module 1 - Disaster Preparedness</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 flex">
  
  <?php include 'sidebar.php'; ?>

  <!-- Main Content -->
  <main class="flex-1 p-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-800">Hello, Participant</h1>
      <p class="text-gray-600">Necessary Education for Disaster Preparedness</p>
    </div>

    <!-- Hero Section -->
    <div class="bg-white p-6 rounded-xl shadow mb-8">
      <h2 class="text-2xl font-semibold text-gray-700 mb-2">Why Disaster Preparedness Matters</h2>
      <p class="text-gray-600 leading-relaxed">
        Disaster preparedness education equips individuals and communities with the knowledge and skills needed to effectively respond to emergencies such as typhoons, earthquakes, floods, and landslides. 
        This module introduces participants to the fundamental principles of disaster risk reduction and community resilience.
      </p>
    </div>

    <!-- Learning Objectives -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
      <div class="bg-blue-50 p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-lg font-bold text-blue-700">Understanding Risks</h3>
        <p class="text-gray-600 mt-2 text-sm">
          Learn about the most common natural disasters in your region and how they impact communities.
        </p>
      </div>
      <div class="bg-green-50 p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-lg font-bold text-green-700">Preparedness Planning</h3>
        <p class="text-gray-600 mt-2 text-sm">
          Discover how to create household emergency plans and evacuation strategies.
        </p>
      </div>
      <div class="bg-yellow-50 p-6 rounded-xl shadow hover:shadow-lg transition">
        <h3 class="text-lg font-bold text-yellow-700">Response & Recovery</h3>
        <p class="text-gray-600 mt-2 text-sm">
          Understand the steps for effective response during disasters and long-term recovery practices.
        </p>
      </div>
    </div>

    <!-- Call to Action -->
    <div class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-xl shadow flex items-center justify-between">
      <div>
        <h3 class="text-xl font-semibold">Ready to Begin?</h3>
        <p class="text-sm opacity-90">Start your training and gain essential knowledge for disaster preparedness.</p>
      </div>
      <a href="/Capstone-defense/user/module1/module1.1.php" class="bg-white text-blue-600 px-4 py-2 rounded-lg font-medium hover:bg-gray-100 transition">
        Start Training
      </a>
    </div>
  </main>
</body>
</html>
