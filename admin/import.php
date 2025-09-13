<?php
include '../includea/header.php';
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

.upload-section {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid rgba(226, 232, 240, 0.8);
    backdrop-filter: blur(10px);
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

.status-badge {
    position: relative;
    overflow: hidden;
}

.status-badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.5s;
}

.status-badge:hover::before {
    left: 100%;
}


.upload-btn {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.upload-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
}

.upload-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.upload-btn:hover::before {
    left: 100%;
}

/* Animation for loading states */
@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.loading {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Custom scrollbar */
.table-container::-webkit-scrollbar {
    height: 8px;
}

.table-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .summary-card {
        margin-bottom: 1rem;
    }
    
    .upload-section .flex {
        flex-direction: column;
        gap: 1rem;
    }
    
}

/* Focus states for accessibility */
.focus-visible:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

/* Smooth transitions for all interactive elements */
button, a, input, select {
    transition: all 0.2s ease;
}

/* Enhanced shadow system */
.shadow-soft {
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
}

.shadow-medium {
    box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
}

.shadow-strong {
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
}
</style>

<?php

// Database connection (using MySQL for clinic_management_system)
$db = new PDO('mysql:host=localhost;dbname=clinic_management_system;charset=utf8mb4', 'root', '');

// Pagination settings
$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $records_per_page;

// Get total count for pagination
$total_count_stmt = $db->query('SELECT COUNT(*) FROM imported_patients');
$total_records = $total_count_stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Create imported_patients table if not exists (matching your actual database structure)
$db->exec('CREATE TABLE IF NOT EXISTS imported_patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(255) DEFAULT NULL,
    name VARCHAR(255) DEFAULT NULL,
    dob VARCHAR(255) DEFAULT NULL,
    gender VARCHAR(255) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    parent_email VARCHAR(255) DEFAULT NULL,
    parent_phone VARCHAR(20) DEFAULT NULL,
    contact_number VARCHAR(20) DEFAULT NULL,
    religion VARCHAR(100) DEFAULT NULL,
    citizenship VARCHAR(100) DEFAULT NULL,
    course_program VARCHAR(255) DEFAULT NULL,
    civil_status VARCHAR(255) DEFAULT NULL,
    password VARCHAR(255) DEFAULT NULL,
    year_level VARCHAR(255) DEFAULT NULL,
    guardian_name VARCHAR(255) DEFAULT NULL,
    guardian_contact VARCHAR(255) DEFAULT NULL,
    emergency_contact_name VARCHAR(255) DEFAULT NULL,
    emergency_contact_number VARCHAR(20) DEFAULT NULL,
    upload_year VARCHAR(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci');

// Handle CSV upload and import
$uploadStatus = '';
$previewRows = [];
$duplicateCount = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $file = $_FILES['csvFile']['tmp_name'];
    
    // Check if file was uploaded successfully
    if (!is_uploaded_file($file)) {
        $uploadStatus = "<span class='text-red-700'>File upload failed. Please try again.</span>";
    } elseif (($handle = fopen($file, 'r')) !== false) {
        $header = fgetcsv($handle); // Assume first row is header
        $existingIds = [];
        $stmt = $db->query('SELECT id FROM imported_patients');
        foreach ($stmt as $row) {
            $existingIds[] = $row['id'];
        }
        $inserted = 0;
        $rowCount = 0;
        $hasHeader = false;
        
        while (($data = fgetcsv($handle)) !== false) {
            $rowCount++;
            error_log("CSV Import: Processing row $rowCount with " . count($data) . " columns");
            
            // Skip rows that don't have enough columns
            if (count($data) < 8) {
                error_log("CSV Import: Skipping row $rowCount - insufficient columns (" . count($data) . "/8)");
                continue;
            }
            
            // Auto-detect and skip header rows
            $firstColumn = isset($data[0]) ? strtolower(trim($data[0])) : '';
            if ($firstColumn === 'student_id' || $firstColumn === 'studentid' || $firstColumn === 'id' || 
                stripos($firstColumn, 'student') !== false) {
                error_log("CSV Import: Skipping row $rowCount - detected header row");
                continue;
            }
            
            // Map columns: [student_id, name, dob, gender, address, civil_status, password, year_level]
            $student_id = isset($data[0]) ? trim($data[0]) : '';
            $name = isset($data[1]) ? trim($data[1]) : '';
            $dob = isset($data[2]) ? trim($data[2]) : '';
            $gender = isset($data[3]) ? trim($data[3]) : '';
            $address = isset($data[4]) ? trim($data[4]) : '';
            $civil_status = isset($data[5]) ? trim($data[5]) : '';
            $password = isset($data[6]) ? trim($data[6]) : '';
            $year_level = isset($data[7]) ? trim($data[7]) : '';
            
            // Skip empty rows
            if (empty($student_id) || empty($name)) {
                error_log("CSV Import: Skipping row $rowCount - empty student_id or name. student_id='$student_id', name='$name'");
                continue;
            }
            
            $isDuplicate = false;
            // Check for duplicate student_id with proper charset handling
            $stmtCheck = $db->prepare('SELECT COUNT(*) FROM imported_patients WHERE student_id = ? COLLATE utf8mb4_general_ci');
            $stmtCheck->execute([$student_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                $isDuplicate = true;
                error_log("CSV Import: Duplicate found for student_id: $student_id");
            }
            $previewRows[] = [
                'student_id' => $student_id,
                'name' => $name,
                'dob' => $dob,
                'gender' => $gender,
                'address' => $address,
                'civil_status' => $civil_status,
                'password' => $password,
                'year_level' => $year_level,
                'duplicate' => $isDuplicate
            ];
            if (!$isDuplicate) {
                try {
                    // Hash the password before storing it
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt2 = $db->prepare('INSERT INTO imported_patients (student_id, name, dob, gender, address, civil_status, password, year_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $result = $stmt2->execute([$student_id, $name, $dob, $gender, $address, $civil_status, $hashedPassword, $year_level]);
                    
                    if ($result) {
                        $inserted++;
                        error_log("CSV Import: Successfully inserted student_id: $student_id");
                    } else {
                        error_log("CSV Import: Failed to insert record for student_id: $student_id");
                    }
                } catch (PDOException $e) {
                    error_log("CSV Import Error: " . $e->getMessage() . " for student_id: $student_id");
                }
            } else {
                $duplicateCount++;
            }
        }
        fclose($handle);
        
        // Enhanced status message with more details
        if ($inserted > 0) {
            $uploadStatus = "<span class='text-green-700'>Upload and import successful! $inserted new record(s) added.";
            if ($duplicateCount > 0) {
                $uploadStatus .= " $duplicateCount duplicate(s) skipped.";
            }
            $uploadStatus .= "</span>";
        } else {
            $uploadStatus = "<span class='text-yellow-700'>No new records added.";
            if ($duplicateCount > 0) {
                $uploadStatus .= " $duplicateCount duplicate(s) found.";
            }
            $uploadStatus .= " Check your CSV format and try again.</span>";
        }
    } else {
        $uploadStatus = "<span class='text-red-700'>Failed to open uploaded file.</span>";
    }
}
?>

<main class="flex-1 overflow-y-auto main-content p-6 ml-16 md:ml-64 mt-[56px]">
        <!-- Application Header -->
        <div class="mb-3">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Patient Import System</h1>
            
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Total Patients</p>
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
                        <p class="text-sm font-medium text-gray-600 mb-1">Active Records</p>
                        <p class="text-3xl font-bold text-green-600"><?php echo max(0, $total_records - $duplicateCount); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                        <i class="ri-user-check-line text-2xl text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Duplicates</p>
                        <p class="text-3xl font-bold text-red-600"><?php echo $duplicateCount; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-50 rounded-lg flex items-center justify-center">
                        <i class="ri-user-unfollow-line text-2xl text-red-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-soft p-6 summary-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 mb-1">Import Status</p>
                        <p class="text-lg font-semibold text-gray-800">Ready</p>
                    </div>
                    <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center">
                        <i class="ri-database-2-line text-2xl text-gray-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Section -->
        <div class="bg-white rounded-lg shadow-soft p-6 mb-8 upload-section">
            <h3 class="text-lg font-semibold text-gray-800 mb-2">Import & Filter</h3>
            <p class="text-gray-600 text-sm mb-6">Upload CSV files or use filters to manage patient data</p>
            
            <div class="flex flex-col lg:flex-row gap-4 items-start lg:items-end">
                <!-- File Upload Form -->
                <div class="flex-1">
                    <form id="csvUploadForm" enctype="multipart/form-data" method="post" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select CSV File</label>
                            <div class="flex items-center space-x-4">
                                <input type="file" name="csvFile" id="csvFile" accept=".csv"
                                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-700 border border-gray-300 rounded-lg px-3 py-2"
                                    required />
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Action and Filter Controls -->
                <div class="flex items-center space-x-3">
                    <button type="submit" form="csvUploadForm"
                        class="px-6 py-2 upload-btn text-white font-medium text-sm rounded-lg flex items-center space-x-2">
                        <i class="ri-upload-line"></i>
                        <span>Upload</span>
                    </button>
                    <select id="yearFilter" class="px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">All Year</option>
                        <option value="1st Year">1st Year</option>
                        <option value="2nd Year">2nd Year</option>
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                    </select>
                    <button id="exportBtn" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition-colors duration-200 flex items-center space-x-2">
                        <i class="ri-download-line"></i>
                        <span>Export</span>
                    </button>
                </div>
            </div>
            
            <!-- Upload Status Notification -->
            <?php if ($uploadStatus): ?>
                <div id="uploadStatus" class="mt-4 p-4 rounded-lg border-l-4 <?php echo strpos($uploadStatus, 'successful') !== false ? 'bg-green-50 border-green-400 text-green-800' : (strpos($uploadStatus, 'failed') !== false ? 'bg-red-50 border-red-400 text-red-800' : 'bg-yellow-50 border-yellow-400 text-yellow-800'); ?>">
                    <?php echo $uploadStatus; ?>
                </div>
            <?php else: ?>
                <div id="uploadStatus" class="hidden mt-4 p-4 rounded-lg"></div>
            <?php endif; ?>
        </div>
        <!-- Patient Directory Table -->
        <div class="bg-white rounded-lg shadow-soft table-container">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-end">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Patient Directory</h3>
                        <p class="text-gray-600 text-sm mt-1">Complete list of imported patients with pagination</p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="relative">
                            <input type="text" id="tableSearch" placeholder="Search patients..." 
                                class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 w-64">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="ri-search-line text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full table-fixed divide-y divide-gray-200" id="importedPatientsTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-20 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID
                            </th>
                            <th class="w-48 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="w-64 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Address
                            </th>
                            <th class="w-24 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Gender
                            </th>
                            <th class="w-32 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Year Level
                            </th>
                            <th class="w-32 px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Birth Date
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    <?php
                    $stmt = $db->prepare('SELECT id, student_id, name, dob, gender, address, civil_status, year_level FROM imported_patients ORDER BY id DESC LIMIT ' . (int)$records_per_page . ' OFFSET ' . (int)$offset);
                    $stmt->execute();
                    $patients = $stmt->fetchAll();
                    
                    if (count($patients) > 0):
                        foreach ($patients as $row): ?>
                        <tr class="table-row">
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate" title="<?php echo htmlspecialchars($row['student_id']); ?>">
                                <?php echo htmlspecialchars($row['student_id']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate" title="<?php echo htmlspecialchars($row['name']); ?>">
                                <?php echo htmlspecialchars($row['name']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate" title="<?php echo htmlspecialchars($row['address']); ?>">
                                <?php echo htmlspecialchars($row['address']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate" title="<?php echo htmlspecialchars($row['gender']); ?>">
                                <?php echo htmlspecialchars($row['gender']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate" title="<?php echo htmlspecialchars($row['year_level']); ?>">
                                <?php echo htmlspecialchars($row['year_level']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 truncate" title="<?php echo htmlspecialchars($row['dob']); ?>">
                                <?php echo htmlspecialchars($row['dob']); ?>
                            </td>
                        </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <i class="ri-user-line text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-gray-500 text-lg font-medium">No patients found</p>
                                    <p class="text-gray-400 text-sm">Upload a CSV file to get started</p>
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
</main>

<script>
// Table search and filter functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('tableSearch');
    const yearFilter = document.getElementById('yearFilter');
    const exportBtn = document.getElementById('exportBtn');
    const table = document.getElementById('importedPatientsTable');
    const tbody = table.querySelector('tbody');
    const rows = tbody.querySelectorAll('tr');
    
    // Search functionality
    if (searchInput && rows.length > 0) {
        searchInput.addEventListener('input', function() {
            filterTable();
        });
    }
    
    // Year filter functionality
    if (yearFilter) {
        yearFilter.addEventListener('change', function() {
            const selectedYear = this.value;
            if (selectedYear === '') {
                // If "All Year" is selected, reload the page without filters
                window.location.href = window.location.pathname;
            } else {
                // Filter all records across all pages
                filterAllRecords(selectedYear);
            }
        });
    }
    
    // Export functionality
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            exportToCSV();
        });
    }
    
    function filterTable() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const selectedYear = yearFilter ? yearFilter.value : '';
        
        rows.forEach(function(row) {
            const cells = row.querySelectorAll('td');
            let found = true;
            
            // Search filter
            if (searchTerm !== '') {
                let searchFound = false;
                cells.forEach(function(cell) {
                    const cellText = cell.textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        searchFound = true;
                    }
                });
                if (!searchFound) {
                    found = false;
                }
            }
            
            // Year filter
            if (selectedYear !== '' && found) {
                const yearLevelCell = cells[4]; // Year Level is the 5th column (index 4)
                if (yearLevelCell) {
                    const yearLevelText = yearLevelCell.textContent.trim().toLowerCase();
                    const selectedYearLower = selectedYear.toLowerCase();
                    
                    // Extract year number for flexible matching
                    const yearNumber = selectedYearLower.match(/(\d+)/);
                    if (yearNumber) {
                        const targetYear = yearNumber[1];
                        // Check if the cell contains the year number
                        if (!yearLevelText.includes(targetYear)) {
                            found = false;
                        }
                    } else if (selectedYearLower === 'all year') {
                        // Show all years
                        found = true;
                    } else {
                        // Fallback to exact match
                        if (yearLevelText !== selectedYearLower) {
                            found = false;
                        }
                    }
                }
            }
            
            // Show/hide row based on filters
            if (found) {
                row.style.display = '';
                row.classList.remove('hidden');
            } else {
                row.style.display = 'none';
                row.classList.add('hidden');
            }
        });
        
        // Update "no results" message if needed
        updateNoResultsMessage();
    }
    
    function updateNoResultsMessage() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const noResultsRow = tbody.querySelector('.no-results-row');
        
        // Remove existing no results message
        if (noResultsRow) {
            noResultsRow.remove();
        }
        
        // Add no results message if no rows are visible and filters are active
        const hasActiveFilters = (searchInput && searchInput.value.trim() !== '') || (yearFilter && yearFilter.value !== '');
        if (visibleRows.length === 0 && hasActiveFilters) {
            const noResultsRow = document.createElement('tr');
            noResultsRow.className = 'no-results-row';
            noResultsRow.innerHTML = `
                <td colspan="6" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center">
                        <i class="ri-search-line text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-lg font-medium">No patients found</p>
                        <p class="text-gray-400 text-sm">Try adjusting your search terms or filters</p>
                    </div>
                </td>
            `;
            tbody.appendChild(noResultsRow);
        }
    }
    
    function filterAllRecords(yearLevel) {
        // Show loading state
        const tbody = table.querySelector('tbody');
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mb-4"></div>
                        <p class="text-gray-500 text-lg font-medium">Loading filtered results...</p>
                    </div>
                </td>
            </tr>
        `;
        
        // Make AJAX request to filter all records
        fetch('filter_patients.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                year_level: yearLevel,
                page: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the table with filtered results
                updateTableWithData(data.patients, data.total_records, data.current_page, data.total_pages);
            } else {
                alert('Error filtering records: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error filtering records. Please try again.');
        });
    }
    
    function updateTableWithData(patients, totalRecords, currentPage, totalPages) {
        const tbody = table.querySelector('tbody');
        
        if (patients.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center">
                        <div class="flex flex-col items-center">
                            <i class="ri-user-line text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500 text-lg font-medium">No patients found</p>
                            <p class="text-gray-400 text-sm">No records match the selected year level</p>
                        </div>
                    </td>
                </tr>
            `;
        } else {
            let tableHTML = '';
            patients.forEach(function(patient) {
                tableHTML += `
                    <tr class="table-row">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate" title="${patient.student_id}">
                            ${patient.student_id}
                        </td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900 truncate" title="${patient.name}">
                            ${patient.name}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 truncate" title="${patient.address}">
                            ${patient.address}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 truncate" title="${patient.gender}">
                            ${patient.gender}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 truncate" title="${patient.year_level}">
                            ${patient.year_level}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500 truncate" title="${patient.dob}">
                            ${patient.dob}
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = tableHTML;
        }
        
        // Update pagination
        updatePagination(totalRecords, currentPage, totalPages);
    }
    
    function updatePagination(totalRecords, currentPage, totalPages) {
        const paginationContainer = document.querySelector('.px-6.py-4.border-t.border-gray-200.bg-gray-50');
        if (paginationContainer) {
            const recordsInfo = paginationContainer.querySelector('.text-sm.text-gray-500');
            const paginationNav = paginationContainer.querySelector('nav');
            
            if (recordsInfo) {
                const start = (currentPage - 1) * 10 + 1;
                const end = Math.min(currentPage * 10, totalRecords);
                recordsInfo.innerHTML = `Showing ${start} to ${end} of ${totalRecords} entries`;
            }
            
            if (paginationNav && totalPages > 1) {
                // Update pagination buttons
                updatePaginationButtons(paginationNav, currentPage, totalPages);
            }
        }
    }
    
    function updatePaginationButtons(nav, currentPage, totalPages) {
        // This would update the pagination buttons
        // For now, we'll keep the existing pagination structure
        // In a full implementation, you'd regenerate the pagination HTML
    }
    
    function exportToCSV() {
        const selectedYear = yearFilter ? yearFilter.value : '';
        
        // Export all filtered records, not just current page
        fetch('export_patients.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                year_level: selectedYear
            })
        })
        .then(response => response.blob())
        .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `patients_export_${selectedYear || 'all'}_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error exporting data. Please try again.');
        });
    }
});
</script>

<?php
include '../includea/footer.php';
?>