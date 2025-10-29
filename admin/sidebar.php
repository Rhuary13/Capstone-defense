<!-- Sidebar -->
<aside class="w-64 h-screen bg-gradient-to-b from-blue-700 to-blue-600 text-white flex flex-col shadow-lg top-0 left-0 overflow-y-auto">
  <!-- Logo / Title -->
  <div class="p-5 text-2xl font-bold border-b border-blue-500 flex-shrink-0">
    Admin Panel
  </div>

  <!-- Navigation -->
  <nav class="flex-1 p-4">
    <ul class="space-y-2">
      <!-- Dashboard -->
      <li>
        <a href="/Capstone-defense/admin/dashboard.php" 
           class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-blue-500 transition">
          <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Training Module Management -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="folder" class="w-5 h-5"></i>
            <span>Training Module</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module1/module1.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Module Creation</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Content Structuring</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Scheduling</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Records</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.5.php" class="block px-2 py-2 rounded hover:bg-blue-500">Assessment & Evaluation</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.6.php" class="block px-2 py-2 rounded hover:bg-blue-500">Completion Tracking</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.7.php" class="block px-2 py-2 rounded hover:bg-blue-500">Analytics</a></li>
        </ul>
      </li>

      <!-- Simulation Event Planning -->
      <li x-data="{ open: false }">
        <button @click="open = !open" 
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="calendar" class="w-5 h-5"></i>
            <span>Simulation & Event</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module2/module2.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Event Scheduling</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Role Assignment</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Safety Protocols</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Notifications</a></li>
        </ul>
      </li>

      <!-- Participant Registration and Attendance -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span>Registration & Attendance</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module3/module3.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Registration Portal</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Attendance Tracking</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Capacity Management</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Reporting</a></li>
        </ul>
      </li>

      <!-- Scenario-Based Exercise Design -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="activity" class="w-5 h-5"></i>
            <span>Scenario-Based Design</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module4/module4.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Scenario Templates</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Variable Configuration</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Decision Points</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Debriefing</a></li>
        </ul>
      </li>

      <!-- Evaluation and Scoring System -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <span>Evaluation & Scoring </span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module5/module5.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Criteria</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Data</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Scoring</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Reports</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.5.php" class="block px-2 py-2 rounded hover:bg-blue-500">Feedback</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.6.php" class="block px-2 py-2 rounded hover:bg-blue-500">History</a></li>  
        </ul>
      </li>

      <!-- Certification Issuance -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="award" class="w-5 h-5"></i>
            <span>Certification Issuance</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module6/module6.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Certification Criteria</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Certificate Designer</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Issuance & Renewal</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Credential Verification</a></li>
        </ul>
      </li>

      <!-- Resource and Equipment Inventory for Simulation -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-2 rounded-lg hover:bg-blue-500 transition focus:outline-none">
          <div class="flex items-center gap-3">
            <i data-lucide="package" class="w-5 h-5"></i>
            <span>Equipment Inventory</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-4 h-4 transform transition-transform"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-8 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm">
          <li><a href="/Capstone-defense/admin/module7/module7.1.php" class="block px-2 py-2 rounded hover:bg-blue-500">Equipment List</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.2.php" class="block px-2 py-2 rounded hover:bg-blue-500">Gear Checkout</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.3.php" class="block px-2 py-2 rounded hover:bg-blue-500">Maintenance Tracker</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.4.php" class="block px-2 py-2 rounded hover:bg-blue-500">Damage Reports</a></li>
        </ul>
      </li>

      <li>
        <a href="/Capstone-defense/auth/logout.php"
           class="flex items-center gap-3 px-4 py-2 rounded-lg hover:bg-red-500 transition">
          <i data-lucide="log-out" class="w-5 h-5"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>

<!-- Load Lucide Icons & Alpine.js -->
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://unpkg.com/alpinejs" defer></script>
<script>
  lucide.createIcons();
</script>
