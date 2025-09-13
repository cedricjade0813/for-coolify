<?php
session_start();
require_once '../includes/db_connect.php';

header('Content-Type: application/json');

// Check if user is logged in as staff
if (!isset($_SESSION['user_id']) && !isset($_SESSION['faculty_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['upload_staff_photo'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

try {
    // Check if file was uploaded
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['profile_photo'];

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.']);
        exit;
    }

    // Validate file size (5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 5MB']);
        exit;
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = '../uploads/profiles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Determine user type and ID
    $user_id = null;
    $table = null;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        $table = 'users';
    } elseif (isset($_SESSION['faculty_id'])) {
        $user_id = $_SESSION['faculty_id'];
        $table = 'faculty';
    }

    // Generate unique filename
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'staff_' . $user_id . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
        exit;
    }

    // Get current profile image to delete old one
    $stmt = $db->prepare("SELECT profile_image FROM {$table} WHERE " . ($table === 'users' ? 'id' : 'faculty_id') . " = ?");
    $stmt->execute([$user_id]);
    $current_image = $stmt->fetchColumn();

    // Delete old profile image if exists
    if ($current_image && file_exists($current_image)) {
        unlink($current_image);
    }

    // Update database with new image path
    $stmt = $db->prepare("UPDATE {$table} SET profile_image = ? WHERE " . ($table === 'users' ? 'id' : 'faculty_id') . " = ?");
    $stmt->execute([$file_path, $user_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Profile photo updated successfully',
        'image_path' => $file_path
    ]);

} catch (Exception $e) {
    error_log('Staff photo upload error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred while uploading photo']);
}
?>
