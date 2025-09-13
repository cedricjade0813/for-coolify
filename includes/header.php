<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/db_connect.php';

$user_display_name = '';
$user_data = null;

// Fetch user data based on session
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    try {
        $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_display_name = htmlspecialchars($user_data['username']);
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
} elseif (isset($_SESSION['faculty_id'])) {
    try {
        $stmt = $db->prepare('SELECT * FROM faculty WHERE faculty_id = ?');
        $stmt->execute([$_SESSION['faculty_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            $user_display_name = htmlspecialchars($user_data['full_name']);
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Fallback for display name
if (!$user_display_name) {
    if (isset($_SESSION['username'])) {
        $user_display_name = htmlspecialchars($_SESSION['username']);
    } elseif (isset($_SESSION['student_name'])) {
        $user_display_name = htmlspecialchars($_SESSION['student_name']);
    }
}

$initials = 'U';
if ($user_display_name) {
    $parts = explode(' ', $user_display_name);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>tailwind.config = { theme: { extend: { colors: { primary: '#2B7BE4', secondary: '#4CAF50' }, borderRadius: { 'none': '0px', 'sm': '4px', DEFAULT: '8px', 'md': '12px', 'lg': '16px', 'xl': '20px', '2xl': '24px', '3xl': '32px', 'full': '9999px', 'button': '8px' } } } }</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
    <?php include_once 'modal_system.php'; ?>
    <style>
        :where([class^="ri-"])::before {
            content: "\f3c2";
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f9fafb;
        }

        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .custom-checkbox {
            position: relative;
            display: inline-block;
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 2px solid #d1d5db;
            background-color: white;
            cursor: pointer;
        }

        .custom-checkbox.checked {
            background-color: #2B7BE4;
            border-color: #2B7BE4;
        }

        .custom-checkbox.checked::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 5px;
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e5e7eb;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.toggle-slider {
            background-color: #2B7BE4;
        }

        input:checked+.toggle-slider:before {
            transform: translateX(20px);
        }

        .custom-select {
            position: relative;
            display: inline-block;
        }

        .custom-select-trigger {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background-color: white;
            cursor: pointer;
            min-width: 150px;
        }

        .custom-select-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background-color: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            margin-top: 0.25rem;
            z-index: 10;
            display: none;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .custom-select-option {
            padding: 0.5rem 1rem;
            cursor: pointer;
        }

        .custom-select-option:hover {
            background-color: #f3f4f6;
        }

        .custom-select.open .custom-select-options {
            display: block;
        }

        .drop-zone {
            border: 2px dashed #d1d5db;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s ease;
            cursor: pointer;
        }

        .drop-zone:hover {
            border-color: #2B7BE4;
        }

        .drop-zone.active {
            border-color: #2B7BE4;
            background-color: rgba(43, 123, 228, 0.05);
        }

        /* Dark Theme Styles */
        .dark-theme {
            background-color: #1a1a1a !important;
            color: #e5e5e5 !important;
        }

        .dark-theme .bg-white {
            background-color: #2d2d2d !important;
        }

        .dark-theme .text-gray-800 {
            color: #e5e5e5 !important;
        }

        .dark-theme .text-gray-600 {
            color: #a0a0a0 !important;
        }

        .dark-theme .text-gray-500 {
            color: #888888 !important;
        }

        .dark-theme .border-gray-200 {
            border-color: #404040 !important;
        }

        .dark-theme .border-gray-300 {
            border-color: #555555 !important;
        }

        .dark-theme .bg-gray-50 {
            background-color: #333333 !important;
        }

        .dark-theme .bg-gray-100 {
            background-color: #404040 !important;
        }

        .dark-theme input, .dark-theme select, .dark-theme textarea {
            background-color: #2d2d2d !important;
            border-color: #555555 !important;
            color: #e5e5e5 !important;
        }

        .dark-theme input:focus, .dark-theme select:focus, .dark-theme textarea:focus {
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) !important;
        }
    </style>
</head>

<body>
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-white border-b border-gray-200 fixed top-0 left-0 w-full z-30">
            <div class="flex items-center justify-between px-6 py-3">
                <div class="flex items-center">
                    <img src="../logo.jpg" alt="St. Cecilia's College Logo"
                        class="h-12 w-12 object-contain rounded-full border border-gray-200 bg-white shadow mr-4" />
                    <h1 class="text-xl font-semibold text-gray-800 hidden md:block">Clinic Management System</h1>
                </div>
                <div class="flex items-center space-x-1">
                    <?php
                    // Show a notification badge and short message for the latest staff notification
                    $notifMsg = '';
                    $notifCount = 0;
                    // Build notifList with both display and raw message
                    $notifList = [];
                    $connNotif = new mysqli('localhost', 'root', '', 'clinic_management_system');
                    if (!$connNotif->connect_errno) {
                        $sql = "SELECT message, created_at, is_read FROM notifications WHERE student_id IS NULL ORDER BY created_at DESC LIMIT 10";
                        $result = $connNotif->query($sql);
                        while ($row = $result->fetch_assoc()) {
                            // Extract just the name from the message (between <b> and </b>)
                            if (preg_match('/<b>(.*?)<\\/b>/', $row['message'], $matches)) {
                                $msg = $matches[1] . ' booked an appointment';
                            } else {
                                $msg = 'New appointment booked';
                            }
                            $notifList[] = [
                                'msg' => $msg,
                                'created_at' => $row['created_at'],
                                'is_read' => $row['is_read'],
                                'raw_message' => $row['message']
                            ];
                        }
                        $result2 = $connNotif->query("SELECT COUNT(*) as cnt FROM notifications WHERE student_id IS NULL AND is_read = 0");
                        if ($row2 = $result2->fetch_assoc()) {
                            $notifCount = (int) $row2['cnt'];
                        }
                        $connNotif->close();
                    }
                    ?>
                    <a href="#" class="relative" id="notifIconBtn">
                        <button type="button"
                            class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-primary focus:outline-none">
                            <i class="ri-notification-3-line ri-xl"></i>
                            <?php if ($notifCount > 0): ?>
                                <span
                                    class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white text-xs flex items-center justify-center rounded-full"><?php echo $notifCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <!-- Notification Dropdown (outside the button, but inside the icon container) -->
                        <div id="notifDropdown"
                            class="hidden absolute z-50 top-16 right-0 w-80 max-w-xs bg-white border border-gray-200 rounded-xl shadow-2xl p-0 text-sm text-gray-700">
                            <div class="relative">
                                <span
                                    class="absolute -top-3 right-6 w-0 h-0 border-l-8 border-r-8 border-b-8 border-l-transparent border-r-transparent border-b-white"></span>
                            </div>
                            <div class="px-4 pt-3 pb-2 border-b border-gray-100 font-semibold text-gray-800">
                                Notifications</div>
                            <ul class="max-h-60 overflow-y-auto">
                                <?php if (count($notifList) === 0): ?>
                                    <li class="px-4 py-3 text-gray-400">No notifications</li>
                                <?php else: ?>
                                    <?php foreach ($notifList as $n): ?>
                                        <?php
                                        // Extract name and reason from the raw message
                                        $studentName = '';
                                        $reason = '';
                                        $date = '';
                                        $time = '';
                                        if (preg_match('/<b>(.*?)<\\/b> for <b>(.*?)<\\/b> at <b>(.*?)<\\/b> \\((.*?)\\)\\./', $n['raw_message'], $matches)) {
                                            $studentName = $matches[1];
                                            $date = $matches[2];
                                            $time = $matches[3];
                                            $reason = $matches[4];
                                        }
                                        ?>
                                        <li class="px-4 py-3 flex items-start hover:bg-gray-50 transition <?php echo !$n['is_read'] ? 'bg-blue-50' : ''; ?> notification-item"
                                            data-student="<?php echo htmlspecialchars($studentName); ?>"
                                            data-date="<?php echo htmlspecialchars($date); ?>"
                                            data-time="<?php echo htmlspecialchars($time); ?>"
                                            data-reason="<?php echo htmlspecialchars($reason); ?>" style="cursor:pointer;">
                                            <div class="flex-1">
                                                <div class="font-medium text-gray-900">
                                                    <?php echo htmlspecialchars($n['msg']); ?>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-1">
                                                    <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                            <a href="notifications.php"
                                class="block text-center text-primary py-2 border-t border-gray-100 hover:bg-gray-50 rounded-b-xl font-medium">View
                                all</a>
                        </div>
                    </a>
                    <script>
                            // Notification icon dropdown logic
                            (function () {
                                const notifBtn = document.getElementById('notifIconBtn');
                                const notifDropdown = document.getElementById('notifDropdown');
                                let notifOpen = false;
                                if (notifBtn && notifDropdown) {
                                    notifBtn.addEventListener('click', function (e) {
                                        e.preventDefault();
                                        e.stopPropagation();
                                        notifDropdown.classList.toggle('hidden');
                                        notifOpen = !notifOpen;
                                    });
                                    document.addEventListener('click', function (e) {
                                        if (notifOpen && !notifBtn.contains(e.target) && !notifDropdown.contains(e.target)) {
                                            notifDropdown.classList.add('hidden');
                                            notifOpen = false;
                                        }
                                    });
                                }
                            })();
                    </script>

                    <div class="relative">
                        <div id="userAvatarBtn"
                            class="w-10 h-10 bg-primary rounded-full flex items-center justify-center text-white mr-2 cursor-pointer select-none">
                            <span class="font-medium"><?php echo $initials; ?></span>
                        </div>
                        <!-- Dropdown Pop-up -->
                        <div id="userDropdown"
                            class="hidden absolute -right-20 mt-2 w-56 bg-white rounded-lg shadow-lg border border-gray-100 z-50">
                            <div class="py-2">
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="ri-user-line mr-2 text-lg text-primary"></i> My Profile
                                </a>
                                <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                    <i class="ri-settings-3-line mr-2 text-lg text-primary"></i> Settings & privacy
                                </a>
                                <div class="border-t my-2"></div>
                                <a href="../index.php"
                                    class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-gray-100">
                                    <i class="ri-logout-box-line mr-2 text-lg"></i> Log out
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="hidden md:block">
                        <p class="text-sm font-medium text-gray-800">
                            Hello, <?php echo $user_display_name ? $user_display_name : 'User'; ?>
                        </p>
                        <p class="text-xs text-gray-500">
                            <?php echo isset($_SESSION['role']) ? htmlspecialchars($_SESSION['role']) : ''; ?>
                        </p>
                    </div>
                </div>
            </div>
        </header>
        <div class="flex flex-1">
            <!-- Sidebar -->
            <aside
                class="w-16 md:w-64 bg-white border-r border-gray-200 flex flex-col fixed top-[73px] left-0 h-[calc(100vh-56px)] z-40">
                <nav class="flex-1 pt-5 pb-4 overflow-y-auto">
                    <ul class="space-y-1 px-2" id="sidebarMenu">
                        <li>
                            <a href="dashboard.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="dashboard.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-dashboard-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Dashboard</span>
                            </a>
                        </li>
                        <li>
                            <a href="records.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="records.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-file-text-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Patient</span>
                            </a>
                        </li>
                        <li>
                            <a href="appointments.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="appointments.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-calendar-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Appointments</span>
                            </a>
                        </li>
                        <li>
                            <a href="messages.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="messages.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                <i class="ri-chat-3-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Messages</span>
                            </a>
                        </li>
                        <li>
                            <a href="refer.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="refer.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                <i class="ri-share-forward-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Referral</span>
                            </a>
                        </li>
                        <li>
                            <a href="inventory.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="inventory.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-archive-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Inventory</span>
                            </a>
                        </li>

                        <li>
                            <a href="list.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="list.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-list-unordered ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">MedList</span>
                            </a>
                        </li>

                        <li>
                            <a href="reports.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="reports.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-bar-chart-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Reports</span>
                            </a>
                        </li>
                        <li>
                            <a href="parent_alerts.php"
                                class="flex items-center px-2 py-2 text-sm font-medium rounded-md text-gray-600 hover:bg-primary hover:bg-opacity-10 hover:text-primary"
                                data-page="parent_alerts.php">
                                <div class="w-8 h-8 flex items-center justify-center mr-3 md:mr-4">
                                    <i class="ri-alert-line ri-lg"></i>
                                </div>
                                <span class="hidden md:inline">Alert</span>
                            </a>
                        </li>
                        


                    </ul>
                </nav>
                

            </aside>

            <script>
                // Sidebar active state logic
                (function () {
                    const sidebarLinks = document.querySelectorAll('#sidebarMenu a');
                    const currentPage = window.location.pathname.split('/').pop();
                    sidebarLinks.forEach(link => {
                        if (link.getAttribute('data-page') === currentPage) {
                            link.classList.add('bg-primary', 'bg-opacity-10', 'text-primary');
                            link.classList.remove('text-gray-600');
                        } else {
                            link.classList.remove('bg-primary', 'bg-opacity-10', 'text-primary');
                            link.classList.add('text-gray-600');
                        }
                        link.addEventListener('click', function () {
                            sidebarLinks.forEach(l => l.classList.remove('bg-primary', 'bg-opacity-10', 'text-primary'));
                            this.classList.add('bg-primary', 'bg-opacity-10', 'text-primary');
                        });
                    });
                })();
            </script>
            <script>
                // User avatar dropdown logic
                document.addEventListener('DOMContentLoaded', function () {
                    const avatarBtn = document.getElementById('userAvatarBtn');
                    const dropdown = document.getElementById('userDropdown');
                    let dropdownOpen = false;
                    avatarBtn.addEventListener('click', function (e) {
                        e.stopPropagation();
                        dropdown.classList.toggle('hidden');
                        dropdownOpen = !dropdownOpen;
                    });
                    document.addEventListener('click', function (e) {
                        if (dropdownOpen && !avatarBtn.contains(e.target) && !dropdown.contains(e.target)) {
                            dropdown.classList.add('hidden');
                            dropdownOpen = false;
                        }
                    });
                });
            </script>
            <!-- Patient Details Modal -->
            <div id="patientDetailsModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
                <div class="bg-white rounded shadow-lg p-8 max-w-md w-full relative">
                    <button id="closePatientModalBtn"
                        class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
                    <h3 class="text-lg font-semibold mb-4">Patient Appointment Details</h3>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Student Name</label>
                        <div id="modalStudentName" class="text-gray-900 font-semibold"></div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                        <div id="modalDate" class="text-gray-900 font-semibold"></div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                        <div id="modalTime" class="text-gray-900 font-semibold"></div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reason for Appointment</label>
                        <div id="modalReason" class="text-gray-900 font-semibold"></div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    document.querySelectorAll('.notification-item').forEach(function (item) {
                        item.addEventListener('click', function () {
                            const student = this.getAttribute('data-student');
                            const date = this.getAttribute('data-date');
                            const time = this.getAttribute('data-time');
                            const reason = this.getAttribute('data-reason');
                            if (student) document.getElementById('modalStudentName').textContent = student;
                            if (date) document.getElementById('modalDate').textContent = date;
                            if (time) document.getElementById('modalTime').textContent = time;
                            if (reason) document.getElementById('modalReason').textContent = reason;
                            document.getElementById('patientDetailsModal').classList.remove('hidden');
                            // Remove highlight after click and mark as read in DB
                            this.classList.remove('bg-blue-50');
                            // Mark as read in DB via AJAX
                            const notifIndex = Array.from(document.querySelectorAll('.notification-item')).indexOf(this);
                            fetch('mark_notification_read.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ index: notifIndex })
                            }).then(() => {
                                // Update badge count
                                const badge = document.querySelector('#notifIconBtn span');
                                if (badge) {
                                    let count = parseInt(badge.textContent, 10);
                                    if (count > 0) {
                                        badge.textContent = count - 1;
                                        if (count - 1 === 0) badge.style.display = 'none';
                                    }
                                }
                            });
                        });
                    });
                    if (document.getElementById('closePatientModalBtn')) {
                        document.getElementById('closePatientModalBtn').addEventListener('click', function () {
                            document.getElementById('patientDetailsModal').classList.add('hidden');
                        });
                    }
                    window.addEventListener('click', function (e) {
                        const modal = document.getElementById('patientDetailsModal');
                        if (e.target === modal) {
                            modal.classList.add('hidden');
                        }
                    });
                });
            </script>

            <!-- Profile Modal -->
            <div id="profileModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-600 rounded-full flex items-center justify-center text-white font-semibold text-lg">
                                <?php echo $initials; ?>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Staff Profile</h2>
                                <p class="text-gray-600">Manage your professional profile and contact information</p>
                            </div>
                        </div>
                        <button id="closeProfileModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="ri-close-line text-2xl"></i>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div class="p-6">
                        <!-- Profile Photo Section -->
                        <div class="flex items-center space-x-6 mb-8">
                            <div class="relative">
                                <div id="profileImageContainer" class="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center text-gray-500 text-2xl font-semibold overflow-hidden">
                                    <?php 
                                    // Check if staff has a profile image
                                    $profileImage = null;
                                    if ($user_data && isset($user_data['profile_image']) && !empty($user_data['profile_image'])) {
                                        $profileImage = '../' . $user_data['profile_image'];
                                    }
                                    ?>
                                    <?php if ($profileImage && file_exists($profileImage)): ?>
                                        <img id="profileImage" src="<?php echo htmlspecialchars($profileImage, ENT_QUOTES, 'UTF-8'); ?>" 
                                             alt="Profile Photo" class="w-full h-full object-cover rounded-full">
                                    <?php else: ?>
                                        <span id="profileInitials"><?php echo htmlspecialchars($initials, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div id="imageLoading" class="absolute inset-0 bg-black bg-opacity-50 rounded-full flex items-center justify-center hidden">
                                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white"></div>
                                </div>
                            </div>
                            <div>
                                <input type="file" id="profilePhotoInput" accept="image/jpeg,image/jpg,image/png,image/gif" 
                                       class="hidden" onchange="uploadProfilePhoto()">
                                <button onclick="document.getElementById('profilePhotoInput').click()" 
                                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
                                    <i class="ri-camera-line"></i>
                                    <span>Change Photo</span>
                                </button>
                                <p class="text-sm text-gray-500 mt-1">JPG, PNG, GIF up to 5MB</p>
                                <button id="removePhotoBtn" onclick="removeProfilePhoto()" 
                                        class="text-sm text-red-600 hover:text-red-800 mt-1 <?php echo ($profileImage && file_exists($profileImage)) ? '' : 'hidden'; ?>">
                                    <i class="ri-delete-bin-line mr-1"></i>Remove Photo
                                </button>
                            </div>
                        </div>

                        <!-- Form Sections -->
                        <form id="profileForm" class="max-w-2xl mx-auto">
                            <div class="space-y-6">
                                <!-- Staff ID -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Staff ID</label>
                                    <input type="text" value="<?php echo $user_data ? htmlspecialchars($user_data['faculty_id'] ?? $user_data['id'] ?? 'N/A', ENT_QUOTES, 'UTF-8') : 'N/A'; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-600" readonly>
                                </div>

                                <!-- Full Name -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                    <input type="text" id="profileName" name="name" value="<?php echo $user_data ? htmlspecialchars($user_data['full_name'] ?? $user_data['name'] ?? $user_data['username'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your full name" required>
                                </div>

                                <!-- Username -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                    <input type="text" id="profileUsername" name="username" value="<?php echo $user_data ? htmlspecialchars($user_data['username'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your username" required>
                                </div>

                                <!-- Email -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" id="profileEmail" name="email" value="<?php echo $user_data ? htmlspecialchars($user_data['email'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your email address" required>
                                </div>

                                <!-- Phone -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                    <input type="tel" id="profilePhone" name="phone" value="<?php echo $user_data ? htmlspecialchars($user_data['phone'] ?? $user_data['contact'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your phone number">
                                </div>

                                <!-- Address -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                                    <textarea id="profileAddress" name="address" rows="3" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your address"><?php echo $user_data ? htmlspecialchars($user_data['address'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                </div>

                                <!-- Department -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                    <input type="text" id="profileDepartment" name="department" value="<?php echo $user_data ? htmlspecialchars($user_data['department'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900" 
                                        placeholder="Enter your department">
                                </div>

                                <!-- Role -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                    <input type="text" value="<?php echo isset($_SESSION['role']) ? htmlspecialchars(ucfirst($_SESSION['role']), ENT_QUOTES, 'UTF-8') : 'N/A'; ?>" 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-gray-50 text-gray-600" readonly>
                                </div>

                                <!-- Status -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                    <div class="flex flex-wrap gap-2">
                                        <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm font-medium">Active</span>
                                        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-medium">Verified</span>
                                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">Administrator</span>
                                        <?php elseif (isset($_SESSION['role']) && in_array($_SESSION['role'], ['doctor', 'nurse'])): ?>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">Medical Staff</span>
                                        <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'faculty'): ?>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-medium">Faculty</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-4 mt-8 pt-6 border-t border-gray-200">
                            <button type="button" id="cancelProfileBtn" onclick="cancelProfileEdit()" 
                                    class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                                Cancel
                            </button>
                            <button type="button" id="editProfileBtn" onclick="editProfile()" 
                                    class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                Edit Profile
                            </button>
                            <button type="button" id="saveProfileBtn" onclick="saveProfile()" 
                                    class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors hidden">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Modal -->
            <div id="settingsModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 hidden">
                <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
                    <!-- Modal Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-200">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Settings</h2>
                            <p class="text-gray-600">Manage your application preferences</p>
                        </div>
                        <button id="closeSettingsModal" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <i class="ri-close-line text-2xl"></i>
                        </button>
                    </div>

                    <!-- Modal Content -->
                    <div class="p-6">
                        <div class="space-y-6">
                            <!-- Theme -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-3 flex items-center">
                                    <i class="ri-palette-line mr-2 text-blue-600"></i>
                                    Theme
                                </label>
                                <select id="themeSelect" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white text-gray-900">
                                    <option value="light">Light</option>
                                    <option value="dark">Dark</option>
                                    <option value="auto">Auto (System)</option>
                                </select>
                                <p class="text-sm text-gray-500 mt-2">Choose your preferred color scheme</p>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4 p-6 border-t border-gray-200">
                        <button id="cancelSettingsBtn" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button id="saveSettingsBtn" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            Save Settings
                        </button>
                    </div>
                </div>
            </div>


            <script>
                // Modal functionality for staff
                document.addEventListener('DOMContentLoaded', function() {
                    // Profile Modal
                    const profileModal = document.getElementById('profileModal');
                    const closeProfileModal = document.getElementById('closeProfileModal');
                    const profileLink = document.querySelector('a[href="#"]:has(.ri-user-line)');

                    if (profileLink) {
                        profileLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            profileModal.classList.remove('hidden');
                        });
                    }

                    closeProfileModal.addEventListener('click', function() {
                        profileModal.classList.add('hidden');
                    });

                    // Settings Modal
                    const settingsModal = document.getElementById('settingsModal');
                    const closeSettingsModal = document.getElementById('closeSettingsModal');
                    const settingsLink = document.querySelector('a[href="#"]:has(.ri-settings-3-line)');

                    if (settingsLink) {
                        settingsLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            settingsModal.classList.remove('hidden');
                        });
                    }

                    closeSettingsModal.addEventListener('click', function() {
                        settingsModal.classList.add('hidden');
                    });


                    // Tab functionality
                    const tabBtns = document.querySelectorAll('.tab-btn');
                    tabBtns.forEach(btn => {
                        btn.addEventListener('click', function() {
                            // Remove active class from all tabs
                            tabBtns.forEach(b => {
                                b.classList.remove('bg-blue-600', 'text-white');
                                b.classList.add('text-gray-600');
                            });
                            
                            // Add active class to clicked tab
                            this.classList.add('bg-blue-600', 'text-white');
                            this.classList.remove('text-gray-600');
                        });
                    });

                    // Close modals when clicking outside
                    [profileModal, settingsModal].forEach(modal => {
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                modal.classList.add('hidden');
                            }
                        });
                    });
                });
            </script>

            <script>
                // Staff Profile and Settings Modal Functionality
                document.addEventListener('DOMContentLoaded', function() {
                    // Get user info for theme storage
                    const userId = <?php echo isset($_SESSION['user_id']) ? json_encode($_SESSION['user_id']) : (isset($_SESSION['faculty_id']) ? json_encode($_SESSION['faculty_id']) : 'null'); ?>;
                    const userRole = 'staff';
                    const userKey = userRole + '_' + userId;
                    
                    // Modal Elements
                    const profileModal = document.getElementById('profileModal');
                    const closeProfileModal = document.getElementById('closeProfileModal');
                    const profileLink = document.getElementById('profileLink');
                    const settingsModal = document.getElementById('settingsModal');
                    const closeSettingsModal = document.getElementById('closeSettingsModal');
                    const settingsLink = document.getElementById('settingsLink');
                    const themeSelect = document.getElementById('themeSelect');
                    const saveSettingsBtn = document.getElementById('saveSettingsBtn');
                    const cancelSettingsBtn = document.getElementById('cancelSettingsBtn');
                    
                    // Profile Form Elements
                    const profileForm = document.getElementById('profileForm');
                    const editProfileBtn = document.getElementById('editProfileBtn');
                    const saveProfileBtn = document.getElementById('saveProfileBtn');
                    const cancelProfileBtn = document.getElementById('cancelProfileBtn');
                    
                    // Profile Photo Elements
                    const profilePhotoInput = document.getElementById('profilePhotoInput');
                    const profileImageContainer = document.getElementById('profileImageContainer');
                    const imageLoading = document.getElementById('imageLoading');
                    const removePhotoBtn = document.getElementById('removePhotoBtn');
                    
                    // Initialize profile form as disabled
                    const profileInputs = profileForm.querySelectorAll('input, select, textarea');
                    profileInputs.forEach(input => {
                        if (!input.readOnly) {
                            input.disabled = true;
                        }
                    });
                    
                    // Profile Modal Functions
                    window.openProfileModal = function() {
                        profileModal.classList.remove('hidden');
                        document.getElementById('userDropdown').classList.add('hidden');
                    }
                    
                    window.closeProfileModal = function() {
                        profileModal.classList.add('hidden');
                    }
                    
                    window.editProfile = function() {
                        profileInputs.forEach(input => {
                            if (!input.readOnly) {
                                input.disabled = false;
                            }
                        });
                        editProfileBtn.classList.add('hidden');
                        saveProfileBtn.classList.remove('hidden');
                        cancelProfileBtn.classList.remove('hidden');
                    }
                    
                    window.cancelProfileEdit = function() {
                        location.reload();
                    }
                    
                    window.saveProfile = function() {
                        const formData = new FormData(profileForm);
                        formData.append('update_staff_profile', '1');
                        
                        fetch('update_staff_profile.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification(data.message, 'success');
                                profileInputs.forEach(input => {
                                    if (!input.readOnly) {
                                        input.disabled = true;
                                    }
                                });
                                editProfileBtn.classList.remove('hidden');
                                saveProfileBtn.classList.add('hidden');
                                cancelProfileBtn.classList.add('hidden');
                            } else {
                                showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('An error occurred while updating profile', 'error');
                        });
                    }
                    
                    // Profile Photo Functions
                    window.uploadProfilePhoto = function() {
                        const file = profilePhotoInput.files[0];
                        if (!file) return;
                        
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        if (!allowedTypes.includes(file.type)) {
                            showNotification('Please select a valid image file (JPG, PNG, GIF)', 'error');
                            return;
                        }
                        
                        if (file.size > 5 * 1024 * 1024) {
                            showNotification('File size must be less than 5MB', 'error');
                            return;
                        }
                        
                        const formData = new FormData();
                        formData.append('profile_photo', file);
                        formData.append('upload_staff_photo', '1');
                        
                        imageLoading.classList.remove('hidden');
                        
                        fetch('upload_staff_photo.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            imageLoading.classList.add('hidden');
                            if (data.success) {
                                if (data.image_path) {
                                    const img = document.createElement('img');
                                    img.src = data.image_path;
                                    img.alt = 'Profile Photo';
                                    img.className = 'w-full h-full object-cover rounded-full';
                                    img.id = 'profileImage';
                                    
                                    profileImageContainer.innerHTML = '';
                                    profileImageContainer.appendChild(img);
                                    removePhotoBtn.classList.remove('hidden');
                                }
                                showNotification(data.message, 'success');
                            } else {
                                showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            imageLoading.classList.add('hidden');
                            console.error('Error:', error);
                            showNotification('An error occurred while uploading photo', 'error');
                        });
                    }
                    
                    window.removeProfilePhoto = function() {
                        fetch('remove_staff_photo.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({ remove_staff_photo: '1' })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const initials = <?php echo json_encode($initials); ?>;
                                profileImageContainer.innerHTML = `<span id="profileInitials">${initials}</span>`;
                                removePhotoBtn.classList.add('hidden');
                                showNotification(data.message, 'success');
                            } else {
                                showNotification(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showNotification('An error occurred while removing photo', 'error');
                        });
                    }
                    
                    // Settings Modal Functions
                    window.openSettingsModal = function() {
                        settingsModal.classList.remove('hidden');
                        document.getElementById('userDropdown').classList.add('hidden');
                        loadCurrentSettings();
                    }
                    
                    window.closeSettingsModal = function() {
                        settingsModal.classList.add('hidden');
                    }
                    
                    window.loadCurrentSettings = function() {
                        const savedTheme = localStorage.getItem('theme_' + userKey);
                        if (savedTheme) {
                            themeSelect.value = savedTheme;
                        } else {
                            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                                themeSelect.value = 'auto';
                            } else {
                                themeSelect.value = 'light';
                            }
                        }
                        applyTheme(themeSelect.value);
                    }
                    
                    window.saveSettings = function() {
                        const theme = themeSelect.value;
                        localStorage.setItem('theme_' + userKey, theme);
                        applyTheme(theme);
                        showNotification('Settings saved successfully', 'success');
                        closeSettingsModal();
                    }
                    
                    window.applyTheme = function(theme) {
                        const body = document.body;
                        const html = document.documentElement;
                        
                        body.classList.remove('dark-theme');
                        html.classList.remove('dark-theme');
                        
                        if (theme === 'dark' || (theme === 'auto' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                            body.classList.add('dark-theme');
                            html.classList.add('dark-theme');
                        }
                    }
                    
                    window.initializeTheme = function() {
                        const savedTheme = localStorage.getItem('theme_' + userKey);
                        if (savedTheme) {
                            applyTheme(savedTheme);
                        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                            applyTheme('auto');
                        }
                    }
                    
                    window.showNotification = function(message, type) {
                        const notification = document.createElement('div');
                        notification.className = `fixed top-4 right-4 z-50 px-6 py-3 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full ${
                            type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
                        }`;
                        notification.textContent = message;
                        
                        document.body.appendChild(notification);
                        
                        setTimeout(() => {
                            notification.classList.remove('translate-x-full');
                        }, 100);
                        
                        setTimeout(() => {
                            notification.classList.add('translate-x-full');
                            setTimeout(() => {
                                document.body.removeChild(notification);
                            }, 300);
                        }, 3000);
                    }
                    
                    // Event Listeners
                    if (profileLink) {
                        profileLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            openProfileModal();
                        });
                    }
                    
                    if (settingsLink) {
                        settingsLink.addEventListener('click', function(e) {
                            e.preventDefault();
                            openSettingsModal();
                        });
                    }
                    
                    closeProfileModal.addEventListener('click', window.closeProfileModal);
                    closeSettingsModal.addEventListener('click', window.closeSettingsModal);
                    
                    editProfileBtn.addEventListener('click', window.editProfile);
                    saveProfileBtn.addEventListener('click', window.saveProfile);
                    cancelProfileBtn.addEventListener('click', window.cancelProfileEdit);
                    
                    saveSettingsBtn.addEventListener('click', window.saveSettings);
                    cancelSettingsBtn.addEventListener('click', window.closeSettingsModal);
                    
                    // Close modals when clicking outside
                    [profileModal, settingsModal].forEach(modal => {
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) {
                                if (modal === profileModal) {
                                    window.closeProfileModal();
                                } else if (modal === settingsModal) {
                                    window.closeSettingsModal();
                                }
                            }
                        });
                    });
                    
                    // Close modals with Escape key
                    document.addEventListener('keydown', function(e) {
                        if (e.key === 'Escape') {
                            if (!profileModal.classList.contains('hidden')) {
                                window.closeProfileModal();
                            }
                            if (!settingsModal.classList.contains('hidden')) {
                                window.closeSettingsModal();
                            }
                        }
                    });
                    
                    // Initialize theme on page load
                    window.initializeTheme();
                    
                    // Listen for system theme changes
                    if (window.matchMedia) {
                        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
                            const savedTheme = localStorage.getItem('theme_' + userKey);
                            if (savedTheme === 'auto') {
                                applyTheme('auto');
                            }
                        });
                    }
                });
            </script>
            <?php includeModalSystem(); ?>