<!-- Sidebar -->
<aside class="w-64 h-screen bg-gradient-to-b from-blue-700 to-blue-600 text-white flex flex-col shadow-xl top-0 left-0 overflow-y-auto">
  <!-- Logo / Title -->
  <div class="p-5 text-2xl font-extrabold border-b border-blue-500 flex-shrink-0">
    Admin Panel
  </div>

  <!-- Navigation -->
  <nav class="flex-1 p-4">
    <ul class="space-y-3">

      <!-- Dashboard -->
      <li>
        <a href="/Capstone-defense/admin/dashboard.php"
           class="flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 text-lg font-medium">
          <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
          <span>Dashboard</span>
        </a>
      </li>

      <!-- Training Module Management -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="folder" class="w-5 h-5"></i>
            <span>Training Module</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module1/module1.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Module Creation</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Quiz & Assessment</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.5.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Progress & Completion Tracking</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.6.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Completion Tracking</a></li>
          <li><a href="/Capstone-defense/admin/module1/module1.7.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Analytics</a></li>
        </ul>
      </li>

      <!-- Simulation Event Planning -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="calendar" class="w-5 h-5"></i>
            <span>Simulation & Event</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module2/module2.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Event Scheduling</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Role Assignment</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Safety Protocols</a></li>
          <li><a href="/Capstone-defense/admin/module2/module2.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Notifications</a></li>
        </ul>
      </li>

      <!-- Participant Registration and Attendance -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span>Scenario-Based Design</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module3/module3.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Debriefing</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Scenario Templates</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Variable Configuration</a></li>
          <li><a href="/Capstone-defense/admin/module3/module3.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Decision Points</a></li>
        </ul>
      </li>

      <!-- Scenario-Based Exercise Design -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="activity" class="w-5 h-5"></i>
            <span>Evaluation & Scoring</span> 
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module4/module4.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Criteria</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Data</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Scoring</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Reports</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Feedback</a></li>
          <li><a href="/Capstone-defense/admin/module4/module4.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">History</a></li>
        </ul>
      </li>

      <!-- Evaluation & Scoring -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="check-circle" class="w-5 h-5"></i>
            <span>Equipment Inventory</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module5/module5.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Equipment List</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Gear Checkout</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Maintenance Tracker</a></li>
          <li><a href="/Capstone-defense/admin/module5/module5.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Damage Reports</a></li>
        </ul>
      </li>

      <!-- Certification Issuance -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="award" class="w-5 h-5"></i>
            <span>Registration & Attendance</span> 
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module6/module6.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Registration Portal</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Attendance Tracking</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Capacity Management</a></li>
          <li><a href="/Capstone-defense/admin/module6/module6.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Reporting</a></li>
        </ul>
      </li>

      <!-- Equipment Inventory -->
      <li x-data="{ open: false }">
        <button @click="open = !open"
                class="w-full flex items-center justify-between px-4 py-3 rounded-lg hover:bg-blue-500 transition-all duration-200 focus:outline-none text-lg font-medium">
          <div class="flex items-center gap-4">
            <i data-lucide="package" class="w-5 h-5"></i>
            <span>Certification Issuance</span>
          </div>
          <svg :class="{'rotate-180': open}" class="w-5 h-5 transform transition-transform duration-300"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 9l-7 7-7-7" />
          </svg>
        </button>
        <ul x-show="open" x-transition
            class="ml-6 mt-2 space-y-2 border-l border-blue-400 pl-4 text-sm font-normal">
          <li><a href="/Capstone-defense/admin/module7/module7.1.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Certification Criteria</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.2.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Certificate Designer</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.3.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Issuance & Renewal</a></li>
          <li><a href="/Capstone-defense/admin/module7/module7.4.php" class="block px-3 py-2 rounded hover:bg-blue-500 transition">Credential Verification</a></li>
        </ul>
      </li>

      <!-- Logout -->
      <li>
        <a href="/Capstone-defense/auth/logout.php"
           class="flex items-center gap-4 px-4 py-3 rounded-lg hover:bg-red-500 transition-all duration-200 text-lg font-medium">
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
