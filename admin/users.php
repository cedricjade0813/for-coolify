<?php
session_start();
include '../includea/header.php';

// Database connection (MySQL)
try {
    $db = new PDO('mysql:host=localhost;dbname=clinic_management_system;charset=utf8mb4', 'root', ''); // Change username/password if needed
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Create table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        username VARCHAR(255) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        role VARCHAR(50) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'Active',
        password VARCHAR(255) NOT NULL
    )");
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get total count for pagination
$total_count_stmt = $db->query('SELECT COUNT(*) FROM users');
$total_records = $total_count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);
// Add user if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = 'Active';
    try {
        $stmt = $db->prepare('INSERT INTO users (name, username, email, role, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $username, $email, $role, $status]);
        $_SESSION['user_message'] = ['type' => 'success', 'text' => 'User added successfully!'];
    } catch (PDOException $e) {
        $_SESSION['user_message'] = ['type' => 'error', 'text' => 'Failed to add user: ' . $e->getMessage()];
    }
    // header('Location: users.php');
    // exit;
}
// Handle update user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $id = $_POST['edit_id'];
    $name = $_POST['edit_name'];
    $username = $_POST['edit_username'];
    $email = $_POST['edit_email'];
    $role = $_POST['edit_role'];
    $status = $_POST['edit_status'];
    try {
        $stmt = $db->prepare('UPDATE users SET name=?, username=?, email=?, role=?, status=? WHERE id=?');
        $stmt->execute([$name, $username, $email, $role, $status, $id]);
        $_SESSION['user_message'] = ['type' => 'success', 'text' => 'User updated successfully!'];
    } catch (PDOException $e) {
        $_SESSION['user_message'] = ['type' => 'error', 'text' => 'Failed to update user: ' . $e->getMessage()];
    }
    // header('Location: users.php');
    // exit;
}
// Fetch users with pagination
$stmt = $db->prepare('SELECT * FROM users ORDER BY id DESC LIMIT ' . (int)$records_per_page . ' OFFSET ' . (int)$offset);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Custom styles for the modern dashboard design */
    .main-content {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        min-height: calc(100vh - 73px);
    }

    .summary-card {
        transition: all 0.3s ease;
        border: 1px solid rgba(226, 232, 240, 0.8);
        backdrop-filter: blur(10px);
    }

    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        border-color: rgba(59, 130, 246, 0.3);
    }

    .table-container {
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid rgba(226, 232, 240, 0.8);
        backdrop-filter: blur(10px);
    }

    .table-row {
        transition: all 0.2s ease;
    }

    .table-row:hover {
        background-color: rgba(59, 130, 246, 0.02);
        transform: scale(1.001);
    }

    .shadow-soft {
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
    }

    /* Enhanced shadow system */
    .shadow-medium {
        box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
    }

    .shadow-strong {
        box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
    }

    /* Smooth transitions for all interactive elements */
    button,
    a,
    input,
    select {
        transition: all 0.2s ease;
    }

    /* Focus states for accessibility */
    .focus-visible:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .summary-card {
            margin-bottom: 1rem;
        }

        .pagination-nav {
            flex-wrap: wrap;
            justify-content: center;
        }
    }
</style>

<main class="flex-1 overflow-y-auto main-content p-6 ml-16 md:ml-64 mt-[56px]">
    <!-- Success/Error Message -->
    <?php if (isset($_SESSION['user_message'])): ?>
        <?php
        if ($_SESSION['user_message']['type'] === 'success') {
            showSuccessModal(htmlspecialchars($_SESSION['user_message']['text']), 'Success');
        } else {
            showErrorModal(htmlspecialchars($_SESSION['user_message']['text']), 'Error');
        }
        unset($_SESSION['user_message']);
        ?>
    <?php endif; ?>

    <!-- Application Header -->
    <div class="mb-3">
       
                <h1 class="text-2xl font-bold text-gray-800 mb-6">User Management</h1>
           

    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Total Users</p>
                    <p class="text-3xl font-bold text-gray-800"><?php echo $total_records; ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-50 rounded-lg flex items-center justify-center">
                    <i class="ri-user-line text-2xl text-blue-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Active Users</p>
                    <p class="text-3xl font-bold text-green-600"><?php
                                                                    $active_count = $db->query("SELECT COUNT(*) FROM users WHERE status = 'Active'")->fetchColumn();
                                                                    echo $active_count;
                                                                    ?></p>
                </div>
                <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                    <i class="ri-user-check-line text-2xl text-green-600"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 mb-1">Search Results</p>
                    <p class="text-3xl font-bold text-purple-600" id="searchResultsCount"><?php echo $total_records; ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center">
                    <i class="ri-search-line text-2xl text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>
    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-soft table-container">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Users</h3>
            <div class="flex items-center space-x-3">
                <!-- Search Bar -->
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ri-search-line text-gray-400"></i>
                    </div>
                    <input type="text" id="userSearch" placeholder="Search users by name, email, or role..."
                        class="block w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                </div>
                <!-- Add User Button -->
                <button id="addUserBtn"
                    class="px-4 py-2 bg-gray-900 text-white font-medium text-sm rounded-lg hover:bg-gray-800 transition-colors flex items-center space-x-2 h-9">
                    <i class="ri-add-line text-lg"></i>
                    <span>Add User</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 table-fixed" id="userTable">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/4">
                            Name
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">
                            Email
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                            Role
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                            Status
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="table-row hover:bg-gray-50"
                                data-id="<?= $user['id'] ?>"
                                data-name="<?= htmlspecialchars($user['name']) ?>"
                                data-username="<?= htmlspecialchars($user['username']) ?>"
                                data-email="<?= htmlspecialchars($user['email']) ?>"
                                data-role="<?= htmlspecialchars($user['role']) ?>"
                                data-status="<?= htmlspecialchars($user['status']) ?>">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900">
                                    <div class="truncate" title="<?= htmlspecialchars($user['name']) ?>">
                                        <?= htmlspecialchars($user['name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div class="truncate" title="<?= htmlspecialchars($user['email']) ?>">
                                        <?= htmlspecialchars($user['email']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $roleColors = [
                                        'admin' => 'bg-purple-100 text-purple-800',
                                        'doctor/nurse' => 'bg-blue-100 text-blue-800',
                                        'user' => 'bg-gray-100 text-gray-800',
                                        'moderator' => 'bg-blue-100 text-blue-800',
                                        'viewer' => 'bg-yellow-100 text-yellow-800'
                                    ];
                                    $roleClass = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $roleClass ?>">
                                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($user['status'] === 'Active'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Disabled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button class="editBtn text-blue-600 hover:text-blue-900 mr-4"
                                        data-id="<?= $user['id'] ?>"
                                        data-name="<?= htmlspecialchars($user['name']) ?>"
                                        data-username="<?= htmlspecialchars($user['username']) ?>"
                                        data-email="<?= htmlspecialchars($user['email']) ?>"
                                        data-role="<?= htmlspecialchars($user['role']) ?>"
                                        data-status="<?= htmlspecialchars($user['status']) ?>">
                                        Edit
                                    </button>
                                    <?php if ($user['status'] === 'Active'): ?>
                                        <button class="disableBtn text-red-600 hover:text-red-900">
                                            Disable
                                        </button>
                                    <?php else: ?>
                                        <button class="enableBtn text-green-600 hover:text-green-900">
                                            Enable
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="ri-user-line text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 text-lg font-medium">No users found</p>
                                    <p class="text-gray-400 text-sm">Add a new user to get started</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination and Records Info -->
        <?php if ($total_records > 0): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="flex justify-between items-center">
                    <!-- Records Information -->
                    <div class="text-sm text-gray-500">
                        <?php
                        $start = $offset + 1;
                        $end = min($offset + $records_per_page, $total_records);
                        ?>
                        Showing <?php echo $start; ?> to <?php echo $end; ?> of <?php echo $total_records; ?> entries
                    </div>

                    <!-- Pagination Navigation -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="flex justify-end items-center -space-x-px" aria-label="Pagination">
                            <!-- Previous Button -->
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>" class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm first:rounded-s-lg last:rounded-e-lg border border-gray-200 text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" aria-label="Previous">
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m15 18-6-6 6-6"></path>
                                    </svg>
                                    <span class="sr-only">Previous</span>
                                </a>
                            <?php else: ?>
                                <button type="button" disabled class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm first:rounded-s-lg last:rounded-e-lg border border-gray-200 text-gray-800 disabled:opacity-50 disabled:pointer-events-none" aria-label="Previous">
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m15 18-6-6 6-6"></path>
                                    </svg>
                                    <span class="sr-only">Previous</span>
                                </button>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);

                            // Show first page if not in range
                            if ($start_page > 1): ?>
                                <a href="?page=1" class="min-h-9.5 min-w-9.5 flex justify-center items-center border border-gray-200 text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm first:rounded-s-lg last:rounded-e-lg focus:outline-hidden focus:bg-gray-100">1</a>
                                <?php if ($start_page > 2): ?>
                                    <span class="min-h-9.5 min-w-9.5 flex justify-center items-center border border-gray-200 text-gray-800 py-2 px-3 text-sm">...</span>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <?php if ($i == $page): ?>
                                    <button type="button" class="min-h-9.5 min-w-9.5 flex justify-center items-center bg-gray-200 text-gray-800 border border-gray-200 py-2 px-3 text-sm first:rounded-s-lg last:rounded-e-lg focus:outline-hidden focus:bg-gray-300" aria-current="page"><?php echo $i; ?></button>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="min-h-9.5 min-w-9.5 flex justify-center items-center border border-gray-200 text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm first:rounded-s-lg last:rounded-e-lg focus:outline-hidden focus:bg-gray-100"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Show last page if not in range -->
                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <span class="min-h-9.5 min-w-9.5 flex justify-center items-center border border-gray-200 text-gray-800 py-2 px-3 text-sm">...</span>
                                <?php endif; ?>
                                <a href="?page=<?php echo $total_pages; ?>" class="min-h-9.5 min-w-9.5 flex justify-center items-center border border-gray-200 text-gray-800 hover:bg-gray-100 py-2 px-3 text-sm first:rounded-s-lg last:rounded-e-lg focus:outline-hidden focus:bg-gray-100"><?php echo $total_pages; ?></a>
                            <?php endif; ?>

                            <!-- Next Button -->
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>" class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm first:rounded-s-lg last:rounded-e-lg border border-gray-200 text-gray-800 hover:bg-gray-100 focus:outline-hidden focus:bg-gray-100" aria-label="Next">
                                    <span class="sr-only">Next</span>
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"></path>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <button type="button" disabled class="min-h-9.5 min-w-9.5 py-2 px-2.5 inline-flex justify-center items-center gap-x-1.5 text-sm first:rounded-s-lg last:rounded-e-lg border border-gray-200 text-gray-800 disabled:opacity-50 disabled:pointer-events-none" aria-label="Next">
                                    <span class="sr-only">Next</span>
                                    <svg class="shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m9 18 6-6-6-6"></path>
                                    </svg>
                                </button>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!-- Add New User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-strong w-full max-w-lg p-6 relative transform transition-all duration-300 scale-95 opacity-0 max-h-[90vh] overflow-y-auto" id="addUserModalContent">
            <button id="closeModalBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
            <div class="mb-4">
                <h3 class="text-xl font-bold text-gray-800 mb-1">Add New User</h3>
                <p class="text-sm text-gray-600">Create a new user account with appropriate permissions</p>
            </div>
            <form id="addUserForm" class="space-y-3" method="post" autocomplete="off">
                <input type="hidden" name="add_user" value="1">

                <!-- Basic Information Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="username"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                </div>

                <!-- Email and Role Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" required
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="user@email.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Role</label>
                        <select name="role"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="doctor/nurse">Doctor/Nurse</option>
                        </select>
                    </div>
                </div>

                <!-- Password Fields Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" id="add_password"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            required />
                        <span class="absolute right-3 top-8 cursor-pointer" onclick="togglePassword('add_password', this)">
                            <i class="ri-eye-off-line text-sm" id="add_password_icon"></i>
                        </span>
                    </div>
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="confirm_password" id="add_confirm_password"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            required />
                        <span class="absolute right-3 top-8 cursor-pointer" onclick="togglePassword('add_confirm_password', this)">
                            <i class="ri-eye-off-line text-sm" id="add_confirm_password_icon"></i>
                        </span>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition-colors">
                        Add User
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden backdrop-blur-sm">
        <div class="bg-white rounded-xl shadow-strong w-full max-w-lg p-6 relative transform transition-all duration-300 scale-95 opacity-0 max-h-[90vh] overflow-y-auto" id="editUserModalContent">
            <button id="closeEditModalBtn" class="absolute top-4 right-4 text-gray-400 hover:text-gray-700 transition-colors">
                <i class="ri-close-line text-xl"></i>
            </button>
            <div class="mb-4">
                <h3 class="text-xl font-bold text-gray-800 mb-1">Edit User</h3>
                <p class="text-sm text-gray-600">Update user information and permissions</p>
            </div>
            <form id="editUserForm" class="space-y-3" method="post" autocomplete="off">
                <input type="hidden" name="edit_user" value="1">
                <input type="hidden" name="edit_id" id="edit_id">

                <!-- Basic Information Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="edit_name" id="edit_name"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="edit_username" id="edit_username"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required />
                    </div>
                </div>

                <!-- Email and Role Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="edit_email" name="edit_email" required
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="user@email.com">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Role</label>
                        <select name="edit_role" id="edit_role"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            required>
                            <option value="">Select Role</option>
                            <option value="admin">Administrator</option>
                            <option value="doctor/nurse">Doctor/Nurse</option>
                        </select>
                    </div>
                </div>

                <!-- Status Row -->
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Status</label>
                    <select name="edit_status" id="edit_status"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        required>
                        <option value="Active">Active</option>
                        <option value="Disabled">Disabled</option>
                    </select>
                </div>

                <!-- Password Fields Row (Optional) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password (optional)</label>
                        <input type="password" name="edit_password" id="edit_password"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10"
                            placeholder="Leave blank to keep unchanged" />
                        <span class="absolute right-3 top-8 cursor-pointer" onclick="togglePassword('edit_password', this)">
                            <i class="ri-eye-off-line text-sm" id="edit_password_icon"></i>
                        </span>
                    </div>
                    <div class="relative">
                        <label class="block text-xs font-medium text-gray-700 mb-1">Confirm Password</label>
                        <input type="password" name="edit_confirm_password" id="edit_confirm_password"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 pr-10" />
                        <span class="absolute right-3 top-8 cursor-pointer" onclick="togglePassword('edit_confirm_password', this)">
                            <i class="ri-eye-off-line text-sm" id="edit_confirm_password_icon"></i>
                        </span>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="flex justify-end pt-2">
                    <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition-colors">
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    // Modal logic with animations
    const addUserBtn = document.getElementById('addUserBtn');
    const addUserModal = document.getElementById('addUserModal');
    const addUserModalContent = document.getElementById('addUserModalContent');
    const closeModalBtn = document.getElementById('closeModalBtn');

    function openAddModal() {
        addUserModal.classList.remove('hidden');
        setTimeout(() => {
            addUserModalContent.classList.remove('scale-95', 'opacity-0');
            addUserModalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeAddModal() {
        addUserModalContent.classList.remove('scale-100', 'opacity-100');
        addUserModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            addUserModal.classList.add('hidden');
        }, 300);
    }

    addUserBtn.addEventListener('click', openAddModal);
    closeModalBtn.addEventListener('click', closeAddModal);
    window.addEventListener('click', (e) => {
        if (e.target === addUserModal) closeAddModal();
    });
    // Edit User Modal logic with animations
    const editUserModal = document.getElementById('editUserModal');
    const editUserModalContent = document.getElementById('editUserModalContent');
    const closeEditModalBtn = document.getElementById('closeEditModalBtn');
    const editUserForm = document.getElementById('editUserForm');

    function openEditModal() {
        editUserModal.classList.remove('hidden');
        setTimeout(() => {
            editUserModalContent.classList.remove('scale-95', 'opacity-0');
            editUserModalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function closeEditModal() {
        editUserModalContent.classList.remove('scale-100', 'opacity-100');
        editUserModalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            editUserModal.classList.add('hidden');
        }, 300);
    }

    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('edit_id').value = this.getAttribute('data-id');
            document.getElementById('edit_name').value = this.getAttribute('data-name');
            document.getElementById('edit_username').value = this.getAttribute('data-username');
            document.getElementById('edit_email').value = this.getAttribute('data-email');
            document.getElementById('edit_role').value = this.getAttribute('data-role');
            document.getElementById('edit_status').value = this.getAttribute('data-status');
            openEditModal();
        });
    });
    closeEditModalBtn.addEventListener('click', closeEditModal);
    window.addEventListener('click', (e) => {
        if (e.target === editUserModal) closeEditModal();
    });
    // Search bar logic
    document.getElementById('userSearch').addEventListener('input', filterUsers);

    function filterUsers() {
        const search = document.getElementById('userSearch').value.trim().toLowerCase();
        const rows = document.querySelectorAll('#userTable tbody tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name').toLowerCase();
            const username = row.getAttribute('data-username').toLowerCase();
            const email = row.getAttribute('data-email').toLowerCase();
            const role = row.getAttribute('data-role').toLowerCase();

            const matchesSearch = (!search ||
                name.includes(search) ||
                username.includes(search) ||
                email.includes(search) ||
                role.includes(search));

            if (matchesSearch) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update search results counter
        const searchResultsElement = document.getElementById('searchResultsCount');
        if (searchResultsElement) {
            searchResultsElement.textContent = visibleCount;
        }

        // Update pagination text if it exists
        const paginationText = document.querySelector('.text-sm.text-gray-500');
        if (paginationText && search) {
            paginationText.textContent = `Showing ${visibleCount > 0 ? '1' : '0'} to ${visibleCount} of ${visibleCount} entries`;
        }
    }
    // Action buttons (demo only)
    document.querySelector('#userTable tbody').addEventListener('click', async function(e) {
        if (e.target.classList.contains('editBtn')) {
            document.getElementById('edit_id').value = e.target.getAttribute('data-id');
            document.getElementById('edit_name').value = e.target.getAttribute('data-name');
            document.getElementById('edit_username').value = e.target.getAttribute('data-username');
            document.getElementById('edit_email').value = e.target.getAttribute('data-email');
            document.getElementById('edit_role').value = e.target.getAttribute('data-role');
            document.getElementById('edit_status').value = e.target.getAttribute('data-status');
            openEditModal();
        }
        if (e.target.classList.contains('disableBtn')) {
            const tr = e.target.closest('tr');
            const userId = tr.getAttribute('data-id');
            // Send AJAX to disable user
            const formData = new FormData();
            formData.append('disable_user', '1');
            formData.append('user_id', userId);
            const res = await fetch('user_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showSuccessModal('User disabled successfully!', 'Success');
                // Auto refresh page after 1.5 seconds to ensure correct styling
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showErrorModal(data.message || 'Failed to disable user.', 'Error');
            }
        }
        if (e.target.classList.contains('enableBtn')) {
            const tr = e.target.closest('tr');
            const userId = tr.getAttribute('data-id');
            // Send AJAX to enable user
            const formData = new FormData();
            formData.append('enable_user', '1');
            formData.append('user_id', userId);
            const res = await fetch('user_actions.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.success) {
                showSuccessModal('User enabled successfully!', 'Success');
                // Auto refresh page after 1.5 seconds to ensure correct styling
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showErrorModal(data.message || 'Failed to enable user.', 'Error');
            }
        }
    });

    // Password show/hide toggle function
    function togglePassword(inputId, iconSpan) {
        const input = document.getElementById(inputId);
        const icon = iconSpan.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('ri-eye-off-line');
            icon.classList.add('ri-eye-line');
        } else {
            input.type = 'password';
            icon.classList.remove('ri-eye-line');
            icon.classList.add('ri-eye-off-line');
        }
    }

    // Add User AJAX
    document.getElementById('addUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        let valid = true;
        // Remove previous error messages
        form.querySelectorAll('.form-error').forEach(el => el.remove());
        // Name validation
        if (!form.name.value.trim()) {
            showFieldError(form.name, 'Name is required');
            valid = false;
        }
        // Username validation
        if (!form.username.value.trim()) {
            showFieldError(form.username, 'Username is required');
            valid = false;
        }
        // Email validation
        if (!form.email.value.trim()) {
            showFieldError(form.email, 'Email is required');
            valid = false;
        } else if (!validateEmail(form.email.value.trim())) {
            showFieldError(form.email, 'Invalid email format');
            valid = false;
        }
        // Role validation
        if (!form.role.value) {
            showFieldError(form.role, 'Role is required');
            valid = false;
        }
        // Password validation
        if (!form.password.value) {
            showFieldError(form.password, 'Password is required');
            valid = false;
        }
        if (!form.confirm_password.value) {
            showFieldError(form.confirm_password, 'Confirm password is required');
            valid = false;
        }
        if (form.password.value && form.confirm_password.value && form.password.value !== form.confirm_password.value) {
            showFieldError(form.confirm_password, 'Passwords do not match!');
            valid = false;
        }
        if (!valid) return;
        const formData = new FormData(form);
        const res = await fetch('user_actions.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeAddModal();
            form.reset();
            setTimeout(() => {
                showSuccessModal(data.message, 'Success');
                // Auto refresh page after 1.5 seconds to ensure correct styling
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }, 200);
        } else {
            showErrorModal(data.message, 'Error');
        }
    });

    // Edit User AJAX
    document.getElementById('editUserForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        let valid = true;
        // Remove previous error messages
        form.querySelectorAll('.form-error').forEach(el => el.remove());
        // Name validation
        if (!form.edit_name.value.trim()) {
            showFieldError(form.edit_name, 'Name is required');
            valid = false;
        }
        // Username validation
        if (!form.edit_username.value.trim()) {
            showFieldError(form.edit_username, 'Username is required');
            valid = false;
        }
        // Email validation
        if (!form.edit_email.value.trim()) {
            showFieldError(form.edit_email, 'Email is required');
            valid = false;
        } else if (!validateEmail(form.edit_email.value.trim())) {
            showFieldError(form.edit_email, 'Invalid email format');
            valid = false;
        }
        // Role validation
        if (!form.edit_role.value) {
            showFieldError(form.edit_role, 'Role is required');
            valid = false;
        }
        // Password validation (optional)
        if (form.edit_password.value || form.edit_confirm_password.value) {
            if (!form.edit_password.value) {
                showFieldError(form.edit_password, 'Password is required');
                valid = false;
            }
            if (!form.edit_confirm_password.value) {
                showFieldError(form.edit_confirm_password, 'Confirm password is required');
                valid = false;
            }
            if (form.edit_password.value !== form.edit_confirm_password.value) {
                showFieldError(form.edit_confirm_password, 'Passwords do not match!');
                valid = false;
            }
        }
        if (!valid) return;
        const formData = new FormData(form);
        const res = await fetch('user_actions.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.success) {
            closeEditModal();
            setTimeout(() => {
                showSuccessModal(data.message, 'Success');
                // Auto refresh page after 1.5 seconds to ensure correct styling
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }, 200);
        } else {
            showErrorModal(data.message, 'Error');
        }
    });

    // Helper to show error message below a field
    function showFieldError(input, message) {
        const error = document.createElement('div');
        error.className = 'form-error text-xs text-red-600 mt-1';
        error.textContent = message;
        input.parentNode.appendChild(error);
    }



    // Email validation function
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }
</script>

<?php
include '../includea/footer.php';
?>