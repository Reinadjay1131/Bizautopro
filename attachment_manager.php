<?php
/**
 * File Attachment Manager for Workflows
 * Handles secure file upload, storage, retrieval, and management
 */

class WorkflowAttachmentManager {
    private $pdo;
    private $upload_dir;
    private $allowed_extensions;
    private $max_file_size;
    
    public function __construct($pdo, $upload_dir = 'uploads/workflows/', $max_size = 10485760) { // 10MB default
        $this->pdo = $pdo;
        $this->upload_dir = $upload_dir;
        $this->max_file_size = $max_size;
        $this->allowed_extensions = [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'rtf', 'csv',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp',
            'zip', 'rar', '7z',
            'mp4', 'avi', 'mov', 'wmv' // Basic video support
        ];
        
        // Create upload directory if it doesn't exist
        if (!file_exists($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
        
        // Create .htaccess for security
        $this->createSecurityFiles();
    }
    
    /**
     * Upload and attach file to workflow
     */
    public function uploadAttachment($workflow_id, $file, $uploaded_by, $description = '') {
        try {
            // Validate file
            $validation = $this->validateFile($file);
            if (!$validation['valid']) {
                return ['success' => false, 'error' => $validation['error']];
            }
            
            // Generate secure filename
            $filename = $this->generateSecureFilename($file['name']);
            $file_path = $this->upload_dir . $filename;
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                return ['success' => false, 'error' => 'Failed to save file'];
            }
            
            // Calculate file hash for integrity checking
            $file_hash = hash_file('sha256', $file_path);
            
            // Store attachment info in database
            $stmt = $this->pdo->prepare("
                INSERT INTO workflow_attachments 
                (workflow_id, original_filename, stored_filename, file_path, file_size, file_type, file_hash, uploaded_by, description, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $workflow_id,
                $file['name'],
                $filename,
                $file_path,
                $file['size'],
                $file['type'],
                $file_hash,
                $uploaded_by,
                $description
            ]);
            
            $attachment_id = $this->pdo->lastInsertId();
            
            // Update workflow with attachment path if it's the first attachment
            $existing_attachment = $this->pdo->prepare("
                SELECT COUNT(*) FROM workflow_attachments WHERE workflow_id = ?
            ");
            $existing_attachment->execute([$workflow_id]);
            
            if ($existing_attachment->fetchColumn() == 1) {
                $this->pdo->prepare("UPDATE workflows SET attachment_path = ? WHERE id = ?")
                    ->execute([$file_path, $workflow_id]);
            }
            
            // Log activity
            $this->pdo->prepare("
                INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
                VALUES (?, ?, 'file_upload', ?, NOW())
            ")->execute([$workflow_id, $uploaded_by, "Uploaded file: {$file['name']}"]);
            
            return [
                'success' => true,
                'attachment_id' => $attachment_id,
                'filename' => $filename,
                'file_path' => $file_path,
                'file_size' => $file['size']
            ];
            
        } catch (Exception $e) {
            // Clean up file if database insert failed
            if (isset($file_path) && file_exists($file_path)) {
                unlink($file_path);
            }
            
            return ['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all attachments for a workflow
     */
    public function getWorkflowAttachments($workflow_id) {
        $stmt = $this->pdo->prepare("
            SELECT wa.*, u.username as uploaded_by_name
            FROM workflow_attachments wa
            JOIN users u ON wa.uploaded_by = u.id
            WHERE wa.workflow_id = ?
            ORDER BY wa.uploaded_at DESC
        ");
        $stmt->execute([$workflow_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get attachment details by ID
     */
    public function getAttachment($attachment_id, $user_id = null) {
        $stmt = $this->pdo->prepare("
            SELECT wa.*, w.title as workflow_title
            FROM workflow_attachments wa
            JOIN workflows w ON wa.workflow_id = w.id
            WHERE wa.id = ?
        ");
        $stmt->execute([$attachment_id]);
        return $stmt->fetch();
    }
    
    /**
     * Download attachment securely
     */
    public function downloadAttachment($attachment_id, $user_id) {
        $attachment = $this->getAttachment($attachment_id, $user_id);
        
        if (!$attachment) {
            return ['success' => false, 'error' => 'Attachment not found'];
        }
        
        // Check if user has access to the workflow
        if (!$this->checkUserAccess($attachment['workflow_id'], $user_id)) {
            return ['success' => false, 'error' => 'Access denied'];
        }
        
        $file_path = $attachment['file_path'];
        
        if (!file_exists($file_path)) {
            return ['success' => false, 'error' => 'File not found on server'];
        }
        
        // Verify file integrity
        $current_hash = hash_file('sha256', $file_path);
        if ($current_hash !== $attachment['file_hash']) {
            return ['success' => false, 'error' => 'File integrity check failed'];
        }
        
        // Log download
        $this->pdo->prepare("
            INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
            VALUES (?, ?, 'file_download', ?, NOW())
        ")->execute([$attachment['workflow_id'], $user_id, "Downloaded file: {$attachment['original_filename']}"]);
        
        return [
            'success' => true,
            'file_path' => $file_path,
            'filename' => $attachment['original_filename'],
            'file_type' => $attachment['file_type'],
            'file_size' => $attachment['file_size']
        ];
    }
    
    /**
     * Delete attachment
     */
    public function deleteAttachment($attachment_id, $user_id) {
        $attachment = $this->getAttachment($attachment_id, $user_id);
        
        if (!$attachment) {
            return ['success' => false, 'error' => 'Attachment not found'];
        }
        
        // Check if user has permission (admin or uploader)
        $user_role = $this->getUserRole($user_id);
        if ($user_role !== 'admin' && $attachment['uploaded_by'] != $user_id) {
            return ['success' => false, 'error' => 'Permission denied'];
        }
        
        try {
            // Delete file from filesystem
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }
            
            // Delete from database
            $this->pdo->prepare("DELETE FROM workflow_attachments WHERE id = ?")
                ->execute([$attachment_id]);
            
            // Log deletion
            $this->pdo->prepare("
                INSERT INTO workflow_history (workflow_id, user_id, action, notes, timestamp)
                VALUES (?, ?, 'file_delete', ?, NOW())
            ")->execute([$attachment['workflow_id'], $user_id, "Deleted file: {$attachment['original_filename']}"]);
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Delete failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Validate uploaded file
     */
    private function validateFile($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File upload was interrupted',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Server configuration error',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
                UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
            ];
            
            return [
                'valid' => false,
                'error' => $error_messages[$file['error']] ?? 'Unknown upload error'
            ];
        }
        
        // Check file size
        if ($file['size'] > $this->max_file_size) {
            return [
                'valid' => false,
                'error' => 'File too large. Maximum size: ' . $this->formatFileSize($this->max_file_size)
            ];
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowed_extensions)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed: ' . implode(', ', $this->allowed_extensions)
            ];
        }
        
        // Check MIME type for extra security
        $allowed_mimes = [
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'zip' => 'application/zip'
        ];
        
        if (isset($allowed_mimes[$extension])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mime_type !== $allowed_mimes[$extension] && !in_array($mime_type, ['application/octet-stream', 'text/plain'])) {
                return [
                    'valid' => false,
                    'error' => 'File type mismatch. Expected: ' . $allowed_mimes[$extension] . ', Got: ' . $mime_type
                ];
            }
        }
        
        return ['valid' => true];
    }
    
    /**
     * Generate secure filename
     */
    private function generateSecureFilename($original_name) {
        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $timestamp = date('Y-m-d_H-i-s');
        $random = bin2hex(random_bytes(8));
        return "workflow_{$timestamp}_{$random}.{$extension}";
    }
    
    /**
     * Check if user has access to workflow
     */
    private function checkUserAccess($workflow_id, $user_id) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM workflows w
            JOIN users u ON u.id = ?
            WHERE w.id = ? AND (
                u.role = 'admin' OR 
                w.created_by = ? OR 
                w.assigned_to = ?
            )
        ");
        $stmt->execute([$user_id, $workflow_id, $user_id, $user_id]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Get user role
     */
    private function getUserRole($user_id) {
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Format file size for display
     */
    public function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Create security files
     */
    private function createSecurityFiles() {
        // Create .htaccess to prevent direct access
        $htaccess_content = "
# Deny direct access to uploaded files
<Files *>
    Order Deny,Allow
    Deny from all
</Files>

# Allow access only through PHP scripts
<FilesMatch \"\.(php)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>
        ";
        
        file_put_contents($this->upload_dir . '.htaccess', $htaccess_content);
        
        // Create index.php to prevent directory listing
        $index_content = "<?php\n// Directory access forbidden\nheader('HTTP/1.0 403 Forbidden');\nexit;\n?>";
        file_put_contents($this->upload_dir . 'index.php', $index_content);
    }
    
    /**
     * Clean up old attachments
     */
    public function cleanupOldAttachments($days = 365) {
        // Get attachments older than specified days from deleted workflows
        $stmt = $this->pdo->prepare("
            SELECT wa.* FROM workflow_attachments wa
            LEFT JOIN workflows w ON wa.workflow_id = w.id
            WHERE w.id IS NULL OR wa.uploaded_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        $old_attachments = $stmt->fetchAll();
        
        $deleted_count = 0;
        foreach ($old_attachments as $attachment) {
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
                $deleted_count++;
            }
            
            $this->pdo->prepare("DELETE FROM workflow_attachments WHERE id = ?")
                ->execute([$attachment['id']]);
        }
        
        return $deleted_count;
    }
}
?>