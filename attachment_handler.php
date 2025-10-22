<?php
session_start();
require 'config.php';
require_once 'attachment_manager.php';

// Authorization check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

$attachment_manager = new WorkflowAttachmentManager($pdo);
$user_id = $_SESSION['user_id'];

// Handle different actions
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'download':
        $attachment_id = $_GET['id'] ?? 0;
        $result = $attachment_manager->downloadAttachment($attachment_id, $user_id);
        
        if (!$result['success']) {
            http_response_code(404);
            die($result['error']);
        }
        
        // Serve file with proper headers
        $file_path = $result['file_path'];
        $filename = $result['filename'];
        $file_type = $result['file_type'];
        $file_size = $result['file_size'];
        
        // Set headers for file download
        header('Content-Type: ' . $file_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file content
        readfile($file_path);
        exit;
        
    case 'view':
        $attachment_id = $_GET['id'] ?? 0;
        $result = $attachment_manager->downloadAttachment($attachment_id, $user_id);
        
        if (!$result['success']) {
            http_response_code(404);
            die($result['error']);
        }
        
        // Serve file for viewing (inline)
        $file_path = $result['file_path'];
        $filename = $result['filename'];
        $file_type = $result['file_type'];
        $file_size = $result['file_size'];
        
        // Only allow safe file types for viewing
        $viewable_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'text/plain',
            'image/webp'
        ];
        
        if (!in_array($file_type, $viewable_types)) {
            http_response_code(400);
            die('File type not viewable. Download instead.');
        }
        
        header('Content-Type: ' . $file_type);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . $file_size);
        header('Cache-Control: public, max-age=3600');
        
        readfile($file_path);
        exit;
        
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }
        
        $workflow_id = $_POST['workflow_id'] ?? 0;
        $description = $_POST['description'] ?? '';
        
        if (!isset($_FILES['attachment'])) {
            http_response_code(400);
            die('No file uploaded');
        }
        
        $result = $attachment_manager->uploadAttachment(
            $workflow_id,
            $_FILES['attachment'],
            $user_id,
            $description
        );
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        
        if ($result['success']) {
            $_SESSION['success'] = 'File uploaded successfully!';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        
        header("Location: workflow_details.php?id={$workflow_id}");
        exit;
        
    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            die('Method not allowed');
        }
        
        $attachment_id = $_POST['attachment_id'] ?? 0;
        $result = $attachment_manager->deleteAttachment($attachment_id, $user_id);
        
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode($result);
            exit;
        }
        
        if ($result['success']) {
            $_SESSION['success'] = 'File deleted successfully!';
        } else {
            $_SESSION['error'] = $result['error'];
        }
        
        $workflow_id = $_POST['workflow_id'] ?? 0;
        header("Location: workflow_details.php?id={$workflow_id}");
        exit;
        
    case 'list':
        $workflow_id = $_GET['workflow_id'] ?? 0;
        $attachments = $attachment_manager->getWorkflowAttachments($workflow_id);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'attachments' => $attachments
        ]);
        exit;
        
    default:
        http_response_code(400);
        die('Invalid action');
}
?>