<?php
include 'includes/db_connect.php';
session_start();
// LOGIN LOGIC: Only users in users table can log in, with role-based redirect
$login_error = '';
$username_val = '';
$password_val = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['username'], $_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $login_type = isset($_POST['login_type']) ? $_POST['login_type'] : 'main';
    $username_val = htmlspecialchars($username);
    $password_val = htmlspecialchars($password);
    try {
      
      $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
      $stmt->execute([$username]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($user) {
        // Check if this is a restricted login type (student/faculty modal)
        if ($login_type === 'student') {
          $login_error = 'This login is for students only. Please use the main login for admin/doctor/nurse accounts.';
        } elseif ($login_type === 'faculty') {
          $login_error = 'This login is for faculty only. Please use the main login for admin/doctor/nurse accounts.';
        } else {
          // Main login - proceed normally
          $role = strtolower($user['role']);
          // Check if user is active
        if ($user['status'] !== 'Active') {
          $login_error = 'Account is inactive. Please contact administrator.';
        } elseif (password_verify($password, $user['password'])) {
          session_start();
          $_SESSION['user_id'] = $user['id'];
          $_SESSION['username'] = $user['username'];
          $_SESSION['role'] = $user['role'];
          // Log the login event
          try {
            $logDb = new PDO('mysql:host=localhost;dbname=clinic_management_system;charset=utf8', 'root', '');
            $logDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $logStmt = $logDb->prepare('CREATE TABLE IF NOT EXISTS logs (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT,
                            user_email VARCHAR(255),
                            action VARCHAR(255),
                            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
                        )');
            $logStmt->execute();
            $logInsert = $logDb->prepare('INSERT INTO logs (user_id, user_email, action) VALUES (?, ?, ?)');
            $logInsert->execute([$user['id'], $user['email'], 'Logged in']);
          } catch (PDOException $e) {
            // Optionally handle log DB error silently
          }
          if ($role === 'admin') {
            header('Location: admin/dashboard.php');
            exit;
          } elseif ($role === 'doctor' || $role === 'nurse' || $role === 'doctor/nurse') {
            header('Location: staff/dashboard.php');
            exit;
          } else {
            $login_error = 'Access denied: Only admin, doctor, or nurse can log in here.';
          }
        } else {
          $login_error = 'The password you\'ve entered is incorrect.';
        }
        } // Close the main login else block
      } else {
        // Try faculty table (faculty login) first
        $facultyStmt = $db->prepare('SELECT * FROM faculty WHERE email = ?');
        $facultyStmt->execute([$username]);
        $faculty = $facultyStmt->fetch(PDO::FETCH_ASSOC);
        if ($faculty) {
          // Check if this is a restricted login type
          if ($login_type === 'student') {
            $login_error = 'This login is for students only. Please use the faculty login for faculty accounts.';
          } else {
            // Faculty login or main login - proceed
            if (password_verify($password, $faculty['password'])) {
            // Faculty login success - direct access to patient dashboard
            session_start();
            // Clear any student session state to avoid showing student name/info
            unset(
              $_SESSION['pending_patient_id'],
              $_SESSION['pending_student_id'],
              $_SESSION['pending_patient_name'],
              $_SESSION['student_row_id'],
              $_SESSION['patient_id'],
              $_SESSION['student_id'],
              $_SESSION['patient_name'],
              $_SESSION['patient_data']
            );
            $_SESSION['faculty_id'] = $faculty['faculty_id'];
            $_SESSION['faculty_email'] = $faculty['email'];
            $_SESSION['faculty_name'] = $faculty['full_name'];
            $_SESSION['role'] = 'faculty';
            $_SESSION['department'] = $faculty['department'];
            header('Location: faculty/profile.php');
            exit;
          } else {
            $login_error = 'The password you\'ve entered is incorrect.';
          }
          } // Close the faculty validation else block
        } else {
          // Try imported_patients table (student login) only if faculty not found
          $importDb = new PDO('mysql:host=localhost;dbname=clinic_management_system;charset=utf8', 'root', '');
          $importDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          $stmt2 = $importDb->prepare('SELECT * FROM imported_patients WHERE student_id = ?');
          $stmt2->execute([$username]);
          $student = $stmt2->fetch(PDO::FETCH_ASSOC);
          if ($student) {
            // Check if this is a restricted login type
            if ($login_type === 'faculty') {
              $login_error = 'This login is for faculty only. Please use the student login for student accounts.';
            } else {
              // Student login or main login - proceed
              if (password_verify($password, $student['password'])) {
              // Step 1: Store pending login and show DOB form
              session_start();
              $_SESSION['pending_patient_id'] = $student['id'];
              $_SESSION['pending_student_id'] = $student['student_id'];
              $_SESSION['pending_patient_name'] = $student['name'];
              $_SESSION['role'] = 'student';
              $_SESSION['student_name'] = $student['name'];
               header('Location: index.php?dobstep=1');
              exit;
            } else {
              $login_error = 'The password you\'ve entered is incorrect.';
            }
            } // Close the student validation else block
          } else {
            $login_error = 'The account you\'ve entered does not exist.';
          }
        }
      }
    } catch (PDOException $e) {
      $login_error = 'Database error.';
    }
  } elseif (isset($_POST['ajax_dob_check']) && isset($_SESSION['pending_patient_id'])) {
    // Handle AJAX DOB check
    session_start();
    $dob = trim($_POST['dob']);
    $pending_id = $_SESSION['pending_patient_id'];
    
    try {
      
      $stmt = $db->prepare('SELECT dob FROM imported_patients WHERE id = ?');
      $stmt->execute([$pending_id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($row && trim($row['dob']) === $dob) {
        // Login success - set session variables
        $_SESSION['user_id'] = $pending_id;
        $_SESSION['username'] = $_SESSION['pending_student_id'];
        $_SESSION['role'] = 'student';
        $_SESSION['student_row_id'] = $pending_id;
        unset($_SESSION['pending_patient_id'], $_SESSION['pending_student_id'], $_SESSION['pending_patient_name']);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
      } else {
        // Return error response
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Incorrect date of birth.']);
        exit;
      }
    } catch (PDOException $e) {
      header('Content-Type: application/json');
      echo json_encode(['success' => false, 'error' => 'Database error.']);
      exit;
    }
  } elseif (isset($_POST['dob']) && isset($_SESSION['pending_patient_id'])) {
    // Handle DOB step (Step 2)
    $dob = trim($_POST['dob']);
    $pending_id = $_SESSION['pending_patient_id'];
    try {
      
      $stmt = $db->prepare('SELECT dob FROM imported_patients WHERE id = ?');
      $stmt->execute([$pending_id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($row && trim($row['dob']) === $dob) {
        // Login success
        session_start();
        $_SESSION['user_id'] = $pending_id;
        $_SESSION['username'] = $_SESSION['pending_student_id'];
        $_SESSION['role'] = 'student';
        $_SESSION['student_row_id'] = $pending_id;
        unset($_SESSION['pending_patient_id'], $_SESSION['pending_student_id'], $_SESSION['pending_patient_name']);
        header('Location: patient/profile.php');
        exit;
      } else {
        $login_error = 'Incorrect date of birth.';
      } 
    } catch (PDOException $e) {
      $login_error = 'Database error.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MediCare - Advanced Clinic Management System</title>
  <script src="https://cdn.tailwindcss.com/3.4.16"></script>
  <script>tailwind.config = { theme: { extend: { colors: { primary: '#4F46E5', secondary: '#60A5FA' }, borderRadius: { 'none': '0px', 'sm': '4px', DEFAULT: '8px', 'md': '12px', 'lg': '16px', 'xl': '20px', '2xl': '24px', '3xl': '32px', 'full': '9999px', 'button': '8px' } } } }</script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">
  <style>
    :where([class^="ri-"])::before {
      content: "\f3c2";
    }

    /* Hide scrollbars while maintaining scroll functionality */
    html, body {
      scrollbar-width: none; /* Firefox */
      -ms-overflow-style: none; /* Internet Explorer 10+ */
      font-family: 'Inter', sans-serif;
    }

    html::-webkit-scrollbar,
    body::-webkit-scrollbar {
      display: none; /* Safari and Chrome */
    }

    /* Modern gradient text */
    .gradient-text {
      background: linear-gradient(135deg, #FCD34D 0%, #F59E0B 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: drop-shadow(0 0 8px rgba(252, 211, 77, 0.5));
    }

    /* Modern gradient buttons */
    .gradient-button {
      background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
      transition: all 0.3s ease;
    }

    .gradient-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(79, 70, 229, 0.3);
    }

    /* Modern card shadows */
    .modern-card {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
    }

    .modern-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    /* Icon containers */
    .icon-container {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 16px;
    }

    .icon-container.blue { background: linear-gradient(135deg, #DBEAFE 0%, #BFDBFE 100%); }
    .icon-container.green { background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%); }
    .icon-container.purple { background: linear-gradient(135deg, #E9D5FF 0%, #DDD6FE 100%); }
    .icon-container.orange { background: linear-gradient(135deg, #FED7AA 0%, #FDBA74 100%); }

    /* Dark gradient section */
    .dark-gradient {
      background: linear-gradient(135deg, #1E293B 0%, #334155 100%);
    }

    /* Form styling */
    .modern-input {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      color: white;
      padding: 12px 16px;
      transition: all 0.3s ease;
    }

    .modern-input:focus {
      outline: none;
      border-color: #4F46E5;
      box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    .modern-input::placeholder {
      color: rgba(255, 255, 255, 0.7);
    }

    /* Benefits grid */
    .benefits-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 24px;
    }

    .benefit-card {
      background: white;
      border-radius: 12px;
      padding: 24px;
      text-align: center;
      box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
      transition: all 0.3s ease;
    }

    .benefit-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }

    .benefit-icon {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
    }

    /* Metrics cards */
    .metric-card {
      background: white;
      border-radius: 16px;
      padding: 32px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }

    .metric-number {
      font-size: 2.5rem;
      font-weight: 700;
      background: linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .stat-counter {
      display: inline-block;
    }

    .nav-link {
      position: relative;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      width: 0;
      height: 2px;
      bottom: -4px;
      left: 0;
      background-color: #4F46E5;
      transition: width 0.3s;
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .custom-checkbox {
      appearance: none;
      -webkit-appearance: none;
      width: 20px;
      height: 20px;
      border: 2px solid #d1d5db;
      border-radius: 4px;
      outline: none;
      transition: all 0.2s;
      position: relative;
      cursor: pointer;
    }

    .custom-checkbox:checked {
      background-color: #4F46E5;
      border-color: #4F46E5;
    }

    .custom-checkbox:checked::after {
      content: '';
      position: absolute;
      top: 2px;
      left: 6px;
      width: 5px;
      height: 10px;
      border: solid white;
      border-width: 0 2px 2px 0;
      transform: rotate(45deg);
    }

    @keyframes fade-in {
      from { opacity: 0; transform: translateY(-4px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in {
      animation: fade-in 0.3s ease;
    }
  </style>
</head>

<body class="bg-white">
  <!-- Header Section -->
  <header class="w-full bg-white shadow-sm fixed top-0 left-0 right-0 z-50">
    <div class="container mx-auto px-6 py-4 flex items-center justify-between">
      <div class="flex items-center">
        <a href="index.php" class="mr-12 block" style="width:64px;height:64px;">
          <img src="logo.jpg" alt="St. Cecilia's College Logo"
            class="h-16 w-16 object-contain rounded-full border border-gray-200 bg-white shadow"
            onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'64\' height=\'64\'><rect width=\'100%\' height=\'100%\' fill=\'%23f3f4f6\'/><text x=\'50%\' y=\'50%\' font-size=\'12\' fill=\'%23999\' text-anchor=\'middle\' alignment-baseline=\'middle\'>Logo?</text></svg>';this.style.background='#f3f4f6';this.style.border='2px dashed #f87171';" />
          <!-- If logo.jpg does not display, check for case sensitivity, file permissions, or file corruption. -->
        </a>
        <nav class="hidden md:flex space-x-8">
          <a href="index.php" class="nav-link text-gray-800 font-medium hover:text-primary transition-colors">Home</a>
          <a href="#studentLoginModal" class="nav-link text-gray-800 font-medium hover:text-primary transition-colors" onclick="document.getElementById('studentLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">Student Login</a>
          <a href="#facultyLoginModal" class="nav-link text-gray-800 font-medium hover:text-primary transition-colors" onclick="document.getElementById('facultyLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">Faculty Login</a>
          <a href="#features" class="nav-link text-gray-800 font-medium hover:text-primary transition-colors">Features</a>
          <a href="#contact" class="nav-link text-gray-800 font-medium hover:text-primary transition-colors">Contact</a>
        </nav>
      </div>
      <div class="flex items-center space-x-4">
        <a href="#loginModal" id="loginBtn" class="text-gray-700 hover:text-primary font-medium whitespace-nowrap">Login</a>
        <button class="md:hidden w-10 h-10 flex items-center justify-center text-gray-700">
          <i class="ri-menu-line ri-lg"></i>
        </button>
      </div>
    </div>
  </header>
  <!-- Hero Section -->
  <section class="w-full pt-28 pb-16 md:py-32 relative overflow-hidden">
    <div id="heroBg" class="absolute inset-0">
      <div id="heroBg1" class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out"></div>
      <div id="heroBg2" class="absolute inset-0 bg-cover bg-center transition-opacity duration-1000 ease-in-out opacity-0"></div>
      <div class="absolute inset-0 bg-gradient-to-r from-black/60 via-black/40 to-black/20"></div>
    </div>
    <div class="container mx-auto relative z-10">
      <div class="max-w-4xl mx-auto text-center">
        <h1 class="text-5xl md:text-6xl font-bold text-white mb-6 leading-tight drop-shadow-lg">
          Clinic Management <span class="gradient-text">System</span>
        </h1>
        <p class="text-xl text-white mb-10 max-w-3xl mx-auto leading-relaxed drop-shadow-md">
          A modern platform for managing appointments, patient records, inventory, and more for clinics and schools. 
          Empowering <span class="text-yellow-300 font-semibold">Admins</span>, <span class="text-yellow-300 font-semibold">Doctors/Nurses</span>, and <span class="text-yellow-300 font-semibold">Students</span>.
        </p>
        <div class="flex flex-col sm:flex-row gap-6 justify-center mb-10">
          <a href="#features" class="gradient-button text-white px-8 py-4 rounded-xl font-semibold text-lg hover:shadow-lg transition-all duration-300 text-center whitespace-nowrap flex items-center justify-center">
            Explore Features
            <i class="ri-arrow-right-line ml-2"></i>
          </a>
          <a href="#roles" class="bg-white text-primary border-2 border-primary px-8 py-4 rounded-xl font-semibold text-lg hover:bg-gray-50 transition-all duration-300 text-center whitespace-nowrap flex items-center justify-center">
            See User Roles
          </a>
        </div>
        <p class="text-white/90 text-lg drop-shadow-md">St. Cecilia's College Clinic Management System</p>
      </div>
    </div>
  </section>
  <!-- Roles Section -->
  <section id="roles" class="py-20 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Who Can Use This System?</h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">Designed for all clinic stakeholders. Each role has a dedicated dashboard and features.</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="modern-card p-8 text-center flex flex-col h-full">
          <div class="icon-container blue mx-auto">
            <i class="ri-shield-user-line text-2xl text-blue-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Admin</h3>
          <p class="text-gray-600 mb-6 leading-relaxed flex-grow">Manage users, view reports, oversee all clinic operations.</p>
          <ul class="text-left text-gray-600 mb-8 space-y-2">
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>User Management</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>System Reports</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Data Analytics</li>
          </ul>
          <a href="#loginModal" class="gradient-button text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 inline-flex items-center justify-center mt-auto" onclick="document.getElementById('loginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">
            Go to Admin Dashboard
            <i class="ri-arrow-right-line ml-1"></i>
          </a>
        </div>
        <div class="modern-card p-8 text-center flex flex-col h-full">
          <div class="icon-container green mx-auto">
            <i class="ri-stethoscope-line text-2xl text-green-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Doctor/Nurse</h3>
          <p class="text-gray-600 mb-6 leading-relaxed flex-grow">View appointments, manage patient records, issue prescriptions, and monitor inventory.</p>
          <ul class="text-left text-gray-600 mb-8 space-y-2">
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Patient Records</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Prescriptions</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Appointments</li>
          </ul>
          <a href="#loginModal" class="gradient-button text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 inline-flex items-center justify-center mt-auto" onclick="document.getElementById('loginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">
            Go to Staff Dashboard
            <i class="ri-arrow-right-line ml-1"></i>
          </a>
        </div>
        <div class="modern-card p-8 text-center flex flex-col h-full">
          <div class="icon-container purple mx-auto">
            <i class="ri-user-3-line text-2xl text-purple-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Student</h3>
          <p class="text-gray-600 mb-6 leading-relaxed flex-grow">Book appointments, view your medical history, and receive notifications.</p>
          <ul class="text-left text-gray-600 mb-8 space-y-2">
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Book Appointments</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Medical History</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Notifications</li>
          </ul>
          <a href="#studentLoginModal" class="gradient-button text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 inline-flex items-center justify-center mt-auto" onclick="document.getElementById('studentLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">
            Go to Student Portal
            <i class="ri-arrow-right-line ml-1"></i>
          </a>
        </div>
        <div class="modern-card p-8 text-center flex flex-col h-full">
          <div class="icon-container orange mx-auto">
            <i class="ri-graduation-cap-line text-2xl text-orange-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Faculty</h3>
          <p class="text-gray-600 mb-6 leading-relaxed flex-grow">Access patient profiles, view medical records, and manage student health information.</p>
          <ul class="text-left text-gray-600 mb-8 space-y-2">
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Patient Profiles</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Medical Records</li>
            <li class="flex items-center"><i class="ri-check-line text-green-500 mr-2"></i>Health Monitoring</li>
          </ul>
          <a href="#facultyLoginModal" class="gradient-button text-white px-4 py-2 rounded-lg text-sm font-medium hover:shadow-lg transition-all duration-300 inline-flex items-center justify-center mt-auto" onclick="document.getElementById('facultyLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">
            Go to Faculty Portal
            <i class="ri-arrow-right-line ml-1"></i>
          </a>
        </div>
      </div>
    </div>
  </section>
  <!-- Features Section -->
  <section id="features" class="py-20 bg-gray-50">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Comprehensive Clinic Management Features</h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">Our platform offers everything you need to run your clinic efficiently and provide exceptional care.</p>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="modern-card p-8">
          <div class="icon-container blue mb-6">
            <i class="ri-calendar-check-line text-2xl text-blue-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Online Appointments</h3>
          <p class="text-gray-600 leading-relaxed">Book and manage appointments 24/7 with real-time availability and automated reminders.</p>
        </div>
        <div class="modern-card p-8">
          <div class="icon-container green mb-6">
            <i class="ri-folder-user-line text-2xl text-green-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Patient Records</h3>
          <p class="text-gray-600 leading-relaxed">Secure electronic health records with complete patient history and visit notes.</p>
        </div>
        <div class="modern-card p-8">
          <div class="icon-container purple mb-6">
            <i class="ri-medicine-bottle-line text-2xl text-purple-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Prescription Management</h3>
          <p class="text-gray-600 leading-relaxed">Digital prescription system with medication tracking and refill management.</p>
        </div>
        <div class="modern-card p-8">
          <div class="icon-container orange mb-6">
            <i class="ri-bar-chart-2-line text-2xl text-orange-600"></i>
          </div>
          <h3 class="text-2xl font-bold text-gray-900 mb-4">Reports & Analytics</h3>
          <p class="text-gray-600 leading-relaxed">Generate reports and gain insights to improve clinic performance.</p>
        </div>
      </div>
    </div>
  </section>
  
  <!-- Additional Platform Benefits Section -->
  <section class="py-20 bg-white">
    <div class="container mx-auto px-6">
      <div class="text-center mb-16">
        <h2 class="text-4xl font-bold text-gray-900 mb-6">Additional Platform Benefits</h2>
        <p class="text-xl text-gray-600 max-w-3xl mx-auto">Experience the advantages of our modern clinic management system.</p>
      </div>
      
      <!-- Benefits Grid -->
      <div class="benefits-grid mb-16">
        <div class="benefit-card">
          <div class="benefit-icon blue">
            <i class="ri-time-line text-xl text-blue-600"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">24/7 Availability</h3>
          <p class="text-gray-600 text-sm">Access your clinic data anytime, anywhere with our cloud-based system.</p>
        </div>
        <div class="benefit-card">
          <div class="benefit-icon green">
            <i class="ri-shield-check-line text-xl text-green-600"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">HIPAA Compliant</h3>
          <p class="text-gray-600 text-sm">Full compliance with healthcare data protection standards.</p>
        </div>
        <div class="benefit-card">
          <div class="benefit-icon purple">
            <i class="ri-smartphone-line text-xl text-purple-600"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">Mobile Responsive</h3>
          <p class="text-gray-600 text-sm">Optimized for all devices with seamless mobile experience.</p>
        </div>
        <div class="benefit-card">
          <div class="benefit-icon orange">
            <i class="ri-customer-service-2-line text-xl text-orange-600"></i>
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">24/7 Support</h3>
          <p class="text-gray-600 text-sm">Round-the-clock technical support and assistance.</p>
        </div>
      </div>
      
      <!-- Metrics Section -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="metric-card">
          <div class="metric-number">99.9%</div>
          <p class="text-gray-600 font-medium">Uptime Guarantee</p>
        </div>
        <div class="metric-card">
          <div class="metric-number">1000+</div>
          <p class="text-gray-600 font-medium">Happy Users</p>
        </div>
        <div class="metric-card">
          <div class="metric-number">24/7</div>
          <p class="text-gray-600 font-medium">Support Available</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Contact Section -->
  <section id="contact" class="py-20 dark-gradient">
    <div class="container mx-auto px-6">
      <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
          <h2 class="text-4xl font-bold text-white mb-6">Contact Us</h2>
          <p class="text-xl text-gray-300">Have questions or need support? Reach out to our team.</p>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
          <!-- Contact Information -->
          <div class="space-y-8">
            <div class="flex items-center space-x-4">
              <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center">
                <i class="ri-map-pin-line text-white text-xl"></i>
              </div>
              <div>
                <h3 class="text-white font-semibold mb-1">Location</h3>
                <p class="text-gray-300">St. Cecilia's College Cebu, Minglanilla</p>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center">
                <i class="ri-phone-line text-white text-xl"></i>
              </div>
              <div>
                <h3 class="text-white font-semibold mb-1">Phone</h3>
                <a href="tel:09166764802" class="text-gray-300 hover:text-white transition-colors">09166764802</a>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center">
                <i class="ri-mail-line text-white text-xl"></i>
              </div>
              <div>
                <h3 class="text-white font-semibold mb-1">Email</h3>
                <a href="mailto:cms@medicare.com" class="text-gray-300 hover:text-white transition-colors">cms@medicare.com</a>
              </div>
            </div>
            <div class="flex items-center space-x-4">
              <div class="w-12 h-12 bg-white/10 rounded-lg flex items-center justify-center">
                <i class="ri-time-line text-white text-xl"></i>
              </div>
              <div>
                <h3 class="text-white font-semibold mb-1">Hours</h3>
                <p class="text-gray-300">Monday - Friday: 8:00 AM - 5:00 PM</p>
              </div>
            </div>
          </div>
          
          <!-- Contact Form -->
          <div>
            <form class="space-y-6">
              <div>
                <label class="block text-white font-medium mb-2">Name</label>
                <input type="text" class="modern-input w-full" placeholder="Your name">
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Email</label>
                <input type="email" class="modern-input w-full" placeholder="your@email.com">
              </div>
              <div>
                <label class="block text-white font-medium mb-2">Message</label>
                <textarea rows="4" class="modern-input w-full resize-none" placeholder="Your message"></textarea>
              </div>
              <button type="submit" class="gradient-button text-white px-8 py-4 rounded-xl font-semibold text-lg hover:shadow-lg transition-all duration-300 w-full flex items-center justify-center">
                Send Message
                <i class="ri-arrow-right-line ml-2"></i>
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- Footer -->
  <footer class="bg-gray-900 text-white pt-10 pb-6">
    <div class="container mx-auto px-6">
      <div class="flex flex-col md:flex-row justify-between items-center">
        <div class="mb-4 md:mb-0">
          <span class="font-['Pacifico'] text-2xl text-white">SSCMS</span>
          <span class="text-gray-400 ml-4">© 2025 Clinic Management System. All rights reserved.</span>
        </div>
        <div class="flex space-x-6">
          <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Privacy Policy</a>
          <a href="#" class="text-gray-400 hover:text-white text-sm transition-colors">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Mobile menu toggle
      const menuButton = document.querySelector('button.md\\:hidden');
      const mobileMenu = document.createElement('div');
      mobileMenu.className = 'fixed inset-0 bg-white z-50 transform translate-x-full transition-transform duration-300 md:hidden';
      mobileMenu.innerHTML = `
<div class="flex justify-between items-center p-6 border-b">
<a href="#" class="text-2xl font-['Pacifico'] text-primary">logo</a>
<button class="w-10 h-10 flex items-center justify-center text-gray-700">
<i class="ri-close-line ri-lg"></i>
</button>
</div>
<nav class="p-6 space-y-6">
<a href="#" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2">Home</a>
<a href="#studentLoginModal" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2" onclick="document.getElementById('studentLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">Student Login</a>
<a href="#facultyLoginModal" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2" onclick="document.getElementById('facultyLoginModal').classList.remove('hidden');document.body.style.overflow='hidden';return false;">Faculty Login</a>
<a href="#" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2">Services</a>
<a href="#" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2">Appointments</a>
<a href="#" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2">Patient Portal</a>
<a href="#" class="block text-gray-800 font-medium hover:text-primary transition-colors py-2">Contact</a>
<div class="pt-4 border-t">
<a href="#loginModal" class="block text-gray-700 hover:text-primary font-medium py-2">Staff Login</a>
<a href="#" class="block bg-primary text-white px-5 py-2.5 !rounded-button font-medium hover:bg-opacity-90 transition-colors text-center mt-4">Book Appointment</a>
</div>
</nav>
`;
      document.body.appendChild(mobileMenu);
      menuButton.addEventListener('click', function () {
        mobileMenu.classList.remove('translate-x-full');
      });
      mobileMenu.querySelector('button').addEventListener('click', function () {
        mobileMenu.classList.add('translate-x-full');
      });
    });
  </script>
  <!-- Login Modal -->
  <div id="loginModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b">
        <h2 class="text-2xl font-semibold text-gray-900">Login to Your Account</h2>
        <button id="closeLoginModal"
          class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-gray-700">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>
      <form class="p-6 space-y-6" method="POST" action="" id="loginForm">
        <div>
          <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Username</label>
          <div class="relative">
            <input type="text" id="username" name="username" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your username" value="<?php echo $username_val; ?>">
            <?php if ($login_error === 'The account you\'ve entered does not exist.'): ?>
              <div class="absolute left-0 right-0 mt-1 text-xs text-red-600 animate-fade-in">
                The account you've entered does not exist.
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div>
          <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <input type="password" id="password" name="password" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your password" value="<?php echo $password_val; ?>">
            <?php if ($login_error === 'The password you\'ve entered is incorrect.'): ?>
              <div class="absolute left-0 right-0 mt-1 text-xs text-red-600 animate-fade-in">
                The password you've entered is incorrect.
              </div>
            <?php endif; ?>
            <?php if ($login_error === 'Account is inactive. Please contact administrator.'): ?>
              <div class="absolute left-0 right-0 mt-1 text-xs text-red-600 animate-fade-in">
                Account is inactive. Please contact administrator.
              </div>
            <?php endif; ?>
          </div>
          
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <label class="flex items-center text-sm text-gray-600">
              <input type="checkbox" id="showPassword" class="mr-2 rounded border-gray-300 text-primary focus:ring-primary focus:ring-offset-0">
              Show password
            </label>
          </div>
          <a href="#" id="forgotPasswordLink" class="text-sm text-primary hover:text-opacity-80">Forgot password?</a>
        </div>
        <button type="submit"
          class="w-full bg-primary text-white py-2 !rounded-button font-medium hover:bg-opacity-90 transition-colors">Login</button>
      </form>
      
    </div>
  </div>
  <!-- Forgot Password Modal -->
  <div id="forgotPasswordModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b">
        <h2 class="text-2xl font-semibold text-gray-900">Forgot Password</h2>
        <button id="closeForgotPasswordModal" class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-gray-700">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>
      <form class="p-6 space-y-6" id="forgotPasswordForm">
        <div>
          <label for="forgot_email" class="block text-sm font-medium text-gray-700 mb-2">Enter your email address</label>
          <input type="email" id="forgot_email" name="forgot_email" required class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary" placeholder="you@email.com">
          <div id="forgotEmailError" class="text-xs text-red-600 mt-1 hidden"></div>
          <div id="forgotSuccessMsg" class="text-xs text-green-600 mt-2 hidden"></div>
        </div>
        <div class="pt-4">
          <div class="flex gap-3">
            <button type="button" id="cancelForgotPassword" class="flex-1 bg-gray-200 text-gray-800 py-2 !rounded-button font-medium hover:bg-gray-300 transition-colors text-center">Cancel</button>
            <button type="submit" class="flex-1 bg-primary text-white py-2 !rounded-button font-medium hover:bg-opacity-90 transition-colors">Send Reset Link</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  
  <!-- Student Login Modal -->
  <div id="studentLoginModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b">
        <h2 class="text-2xl font-semibold text-gray-900">Student Login</h2>
        <button id="closeStudentLoginModal"
          class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-gray-700">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>
       <form class="p-6 space-y-6" method="POST" action="index.php" id="studentLoginForm">
         <input type="hidden" name="login_type" value="student">
        <div>
          <label for="student_username" class="block text-sm font-medium text-gray-700 mb-2">Student ID</label>
          <div class="relative">
            <input type="text" id="student_username" name="username" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your student ID">
          </div>
          <?php if (!empty($login_error) && isset($_POST['login_type']) && $_POST['login_type'] === 'student' && $login_error === 'The account you\'ve entered does not exist.'): ?>
            <div class="text-red-600 text-sm mt-1">
              <?php echo htmlspecialchars($login_error); ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <label for="student_password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <input type="password" id="student_password" name="password" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your password">
          </div>
          <?php if (!empty($login_error) && isset($_POST['login_type']) && $_POST['login_type'] === 'student' && $login_error !== 'The account you\'ve entered does not exist.'): ?>
            <div class="text-red-600 text-sm mt-1">
              <?php echo htmlspecialchars($login_error); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <label class="flex items-center text-sm text-gray-600">
              <input type="checkbox" id="showStudentPassword" class="mr-2 rounded border-gray-300 text-primary focus:ring-primary focus:ring-offset-0">
              Show password
            </label>
          </div>
          <a href="#" class="text-sm text-primary hover:text-opacity-80">Forgot password?</a>
        </div>
        <button type="submit"
          class="w-full bg-primary text-white py-2 !rounded-button font-medium hover:bg-opacity-90 transition-colors">Login as Student</button>
      </form>
    </div>
  </div>

  <!-- Faculty Login Modal -->
  <div id="facultyLoginModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
      <div class="flex justify-between items-center p-6 border-b">
        <h2 class="text-2xl font-semibold text-gray-900">Faculty Login</h2>
        <button id="closeFacultyLoginModal"
          class="w-10 h-10 flex items-center justify-center text-gray-500 hover:text-gray-700">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>
       <form class="p-6 space-y-6" method="POST" action="index.php" id="facultyLoginForm">
         <input type="hidden" name="login_type" value="faculty">
        <div>
          <label for="faculty_username" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
          <div class="relative">
            <input type="email" id="faculty_username" name="username" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your email address">
          </div>
          <?php if (!empty($login_error) && isset($_POST['login_type']) && $_POST['login_type'] === 'faculty' && $login_error === 'The account you\'ve entered does not exist.'): ?>
            <div class="text-red-600 text-sm mt-1">
              <?php echo htmlspecialchars($login_error); ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <label for="faculty_password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
          <div class="relative">
            <input type="password" id="faculty_password" name="password" required
              class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary"
              placeholder="Enter your password">
          </div>
          <?php if (!empty($login_error) && isset($_POST['login_type']) && $_POST['login_type'] === 'faculty' && $login_error !== 'The account you\'ve entered does not exist.'): ?>
            <div class="text-red-600 text-sm mt-1">
              <?php echo htmlspecialchars($login_error); ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <label class="flex items-center text-sm text-gray-600">
              <input type="checkbox" id="showFacultyPassword" class="mr-2 rounded border-gray-300 text-primary focus:ring-primary focus:ring-offset-0">
              Show password
            </label>
          </div>
          <a href="#" class="text-sm text-primary hover:text-opacity-80">Forgot password?</a>
        </div>
        <button type="submit"
          class="w-full bg-primary text-white py-2 !rounded-button font-medium hover:bg-opacity-90 transition-colors">Login as Faculty</button>
      </form>
    </div>
  </div>
  
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const loginBtn = document.getElementById('loginBtn');
      const loginModal = document.getElementById('loginModal');
      const closeLoginModal = document.getElementById('closeLoginModal');
      const forgotPasswordLink = document.getElementById('forgotPasswordLink');
      const forgotPasswordModal = document.getElementById('forgotPasswordModal');
      const closeForgotPasswordModal = document.getElementById('closeForgotPasswordModal');
      const forgotPasswordForm = document.getElementById('forgotPasswordForm');
      const forgotEmailError = document.getElementById('forgotEmailError');
      const forgotSuccessMsg = document.getElementById('forgotSuccessMsg');

      // Show modal only if loginBtn is clicked
      if (loginBtn && loginModal) {
        loginBtn.addEventListener('click', function () {
          loginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        });
      }
      if (closeLoginModal && loginModal) {
        closeLoginModal.addEventListener('click', function () {
          loginModal.classList.add('hidden');
          document.body.style.overflow = '';
        });
        loginModal.addEventListener('click', function (e) {
          if (e.target === loginModal) {
            loginModal.classList.add('hidden');
            document.body.style.overflow = '';
          }
        });
      }
      if (forgotPasswordLink && forgotPasswordModal) {
        forgotPasswordLink.addEventListener('click', function (e) {
          e.preventDefault();
          forgotPasswordModal.classList.remove('hidden');
          loginModal.classList.add('hidden');
          document.body.style.overflow = 'hidden';
        });
      }
      if (closeForgotPasswordModal && forgotPasswordModal) {
        closeForgotPasswordModal.addEventListener('click', function () {
          forgotPasswordModal.classList.add('hidden');
          loginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        });
        forgotPasswordModal.addEventListener('click', function (e) {
          if (e.target === forgotPasswordModal) {
            forgotPasswordModal.classList.add('hidden');
            document.body.style.overflow = '';
          }
        });
      }
      if (forgotPasswordForm) {
      const cancelForgotPassword = document.getElementById('cancelForgotPassword');
      if (cancelForgotPassword) {
        cancelForgotPassword.addEventListener('click', function () {
          forgotPasswordModal.classList.add('hidden');
          loginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        });
      }
        forgotPasswordForm.addEventListener('submit', async function (e) {
          e.preventDefault();
          forgotEmailError.classList.add('hidden');
          forgotSuccessMsg.classList.add('hidden');
          const email = document.getElementById('forgot_email').value.trim();
          if (!email) {
            forgotEmailError.textContent = 'Email is required.';
            forgotEmailError.classList.remove('hidden');
            return;
          }
          // AJAX to backend for password reset (to be implemented)
          const res = await fetch('send_reset_link.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'email=' + encodeURIComponent(email)
          });
          let data;
          try {
            data = await res.json();
          } catch (err) {
            forgotEmailError.textContent = 'Server error. Please try again later.';
            forgotEmailError.classList.remove('hidden');
            return;
          }
          if (data.success) {
            forgotSuccessMsg.textContent = 'A password reset link has been sent to your email.';
            forgotSuccessMsg.classList.remove('hidden');
          } else {
            forgotEmailError.textContent = data.message || 'No account found with that email.';
            forgotEmailError.classList.remove('hidden');
          }
        });
      }

      // Check for hash to auto-open login modal
      if (window.location.hash === '#login') {
        loginModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Remove the hash from URL after opening modal
        history.replaceState(null, null, ' ');
      }

      // Student Login Modal handlers
      const studentLoginModal = document.getElementById('studentLoginModal');
      const closeStudentLoginModal = document.getElementById('closeStudentLoginModal');
      const showStudentPassword = document.getElementById('showStudentPassword');
      const studentPasswordInput = document.getElementById('student_password');

      if (closeStudentLoginModal && studentLoginModal) {
        closeStudentLoginModal.addEventListener('click', function () {
          studentLoginModal.classList.add('hidden');
          document.body.style.overflow = '';
        });
        studentLoginModal.addEventListener('click', function (e) {
          if (e.target === studentLoginModal) {
            studentLoginModal.classList.add('hidden');
            document.body.style.overflow = '';
          }
        });
      }

      if (showStudentPassword && studentPasswordInput) {
        showStudentPassword.addEventListener('change', function() {
          const type = this.checked ? 'text' : 'password';
          studentPasswordInput.setAttribute('type', type);
        });
      }

      // Faculty Login Modal handlers
      const facultyLoginModal = document.getElementById('facultyLoginModal');
      const closeFacultyLoginModal = document.getElementById('closeFacultyLoginModal');
      const showFacultyPassword = document.getElementById('showFacultyPassword');
      const facultyPasswordInput = document.getElementById('faculty_password');

      if (closeFacultyLoginModal && facultyLoginModal) {
        closeFacultyLoginModal.addEventListener('click', function () {
          facultyLoginModal.classList.add('hidden');
          document.body.style.overflow = '';
        });
        facultyLoginModal.addEventListener('click', function (e) {
          if (e.target === facultyLoginModal) {
            facultyLoginModal.classList.add('hidden');
            document.body.style.overflow = '';
          }
        });
      }

      if (showFacultyPassword && facultyPasswordInput) {
        showFacultyPassword.addEventListener('change', function() {
          const type = this.checked ? 'text' : 'password';
          facultyPasswordInput.setAttribute('type', type);
        });
      }

      // If there is a login error (but not DOB error), show the appropriate modal automatically
      <?php if (!empty($login_error) && !isset($_GET['dobstep'])): ?>
        <?php if (isset($_POST['login_type']) && $_POST['login_type'] === 'student'): ?>
          studentLoginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        <?php elseif (isset($_POST['login_type']) && $_POST['login_type'] === 'faculty'): ?>
          facultyLoginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        <?php else: ?>
          loginModal.classList.remove('hidden');
          document.body.style.overflow = 'hidden';
        <?php endif; ?>
      <?php endif; ?>
    });
  </script>
  <!-- Render DOB form if needed -->
  <?php if (isset($_GET['dobstep']) && isset($_SESSION['pending_patient_id'])): ?>
    <div class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
      <div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
        <div class="flex justify-between items-center p-6 border-b">
          <h2 class="text-2xl font-semibold text-gray-900">Security Question</h2>
        </div>
        <form class="p-6 space-y-6" method="POST" action="">
          <div>
            <label for="dob" class="block text-sm font-medium text-gray-700 mb-2">What is your date of birth?</label>
            <div class="relative">
              <input type="text" id="dob" name="dob" required placeholder="MM/DD/YYYY" class="block w-full pl-3 pr-3 py-2 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary">
              <?php if (!empty($login_error)): ?>
                <div class="absolute left-0 right-0 mt-1 text-xs text-red-600 animate-fade-in">
                  <?php echo htmlspecialchars($login_error); ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
          <div class="pt-4">
            <div class="flex gap-3">
              <button type="button" onclick="window.location.href='index.php#login'" class="flex-1 bg-gray-200 text-gray-800 py-2 !rounded-button font-medium hover:bg-gray-300 transition-colors text-center">Cancel</button>
              <button type="submit" class="flex-1 bg-primary text-white py-2 !rounded-button font-medium hover:bg-opacity-90 transition-colors">Continue</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>
  <script>
    // Background images for hero section with smooth transitions
const heroImages = [
  'scc3.png',
  'scc1.png',
  'scc2.png',
  'scc4.png'
];
let heroBgIdx = 0;
let isTransitioning = false;

function setHeroBg() {
  const heroBg1 = document.getElementById('heroBg1');
  const heroBg2 = document.getElementById('heroBg2');
  
  if (heroBg1 && heroBg2 && !isTransitioning) {
    isTransitioning = true;
    
    // Set the new image on the hidden background
    const currentImage = heroImages[heroBgIdx];
    const hiddenBg = heroBg1.style.opacity === '0' ? heroBg1 : heroBg2;
    const visibleBg = hiddenBg === heroBg1 ? heroBg2 : heroBg1;
    
    hiddenBg.style.backgroundImage = `url('${currentImage}')`;
    
    // Crossfade effect
    setTimeout(() => {
      hiddenBg.style.opacity = '1';
      visibleBg.style.opacity = '0';
      
      // Reset transition flag after animation completes
      setTimeout(() => {
        isTransitioning = false;
      }, 1000);
    }, 50);
  }
}

// Initialize first image
function initHeroBg() {
  const heroBg1 = document.getElementById('heroBg1');
  if (heroBg1) {
    heroBg1.style.backgroundImage = `url('${heroImages[0]}')`;
    heroBg1.style.opacity = '1';
  }
}

initHeroBg();
setInterval(() => {
  heroBgIdx = (heroBgIdx + 1) % heroImages.length;
  setHeroBg();
}, 3000); // Increased interval to allow for transition

// Password toggle functionality
document.addEventListener('DOMContentLoaded', function() {
  const showPasswordCheckbox = document.getElementById('showPassword');
  const passwordInput = document.getElementById('password');

  if (showPasswordCheckbox && passwordInput) {
    showPasswordCheckbox.addEventListener('change', function() {
      // Toggle the type attribute based on checkbox state
      const type = this.checked ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
    });
  }
});

// Password toggle functionality
document.addEventListener('DOMContentLoaded', function() {
  const showPasswordCheckbox = document.getElementById('showPassword');
  const passwordInput = document.getElementById('password');

  if (showPasswordCheckbox && passwordInput) {
    showPasswordCheckbox.addEventListener('change', function() {
      // Toggle the type attribute based on checkbox state
      const type = this.checked ? 'text' : 'password';
      passwordInput.setAttribute('type', type);
    });
  }
});
  </script>
</body>

</html>