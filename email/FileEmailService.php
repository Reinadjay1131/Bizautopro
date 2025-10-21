<?php

/**
 * File-Based Email Service for Local Testing
 * This saves emails to files instead of sending them via SMTP
 */
class FileEmailService {
    private $email_dir;
    private $from_email;
    private $from_name;
    
    public function __construct() {
        $this->email_dir = __DIR__ . '/email_files';
        $this->from_email = 'noreply@bizautopro.com';
        $this->from_name = 'BizAutoPro System';
        
        // Create email directory if it doesn't exist
        if (!is_dir($this->email_dir)) {
            mkdir($this->email_dir, 0777, true);
        }
    }
    
    /**
     * Send email by saving to file
     */
    public function sendTemplate($to, $template, $data = []) {
        try {
            $templatePath = __DIR__ . "/templates/{$template}.php";
            
            if (!file_exists($templatePath)) {
                throw new Exception("Email template not found: {$template}");
            }
            
            // Extract data for template
            extract($data);
            
            // Capture template output
            ob_start();
            include $templatePath;
            $body = ob_get_clean();
            
            // Get subject from template data
            $subject = $data['subject'] ?? 'BizAutoPro Notification';
            
            return $this->saveEmailToFile($to, $subject, $body, $template);
            
        } catch (Exception $e) {
            error_log("File Email Service Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Save email to file for review
     */
    private function saveEmailToFile($to, $subject, $body, $template = 'unknown') {
        $timestamp = date('Y-m-d_H-i-s');
        $safe_to = preg_replace('/[^a-zA-Z0-9@.-]/', '_', $to);
        $filename = "{$timestamp}_{$safe_to}_{$template}.html";
        $filepath = $this->email_dir . '/' . $filename;
        
        // Create email content with headers
        $emailContent = "<!DOCTYPE html>\n";
        $emailContent .= "<html><head><meta charset='UTF-8'><title>Email Preview</title></head><body>\n";
        $emailContent .= "<div style='background: #f0f0f0; padding: 20px; margin-bottom: 20px; border-radius: 5px;'>\n";
        $emailContent .= "<h3>📧 Email Details</h3>\n";
        $emailContent .= "<p><strong>To:</strong> {$to}</p>\n";
        $emailContent .= "<p><strong>From:</strong> {$this->from_name} &lt;{$this->from_email}&gt;</p>\n";
        $emailContent .= "<p><strong>Subject:</strong> {$subject}</p>\n";
        $emailContent .= "<p><strong>Template:</strong> {$template}</p>\n";
        $emailContent .= "<p><strong>Sent:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
        $emailContent .= "</div>\n";
        $emailContent .= "<div style='border: 2px solid #007bff; padding: 10px;'>\n";
        $emailContent .= "<h4>Email Content:</h4>\n";
        $emailContent .= $body;
        $emailContent .= "</div>\n";
        $emailContent .= "</body></html>\n";
        
        $result = file_put_contents($filepath, $emailContent);
        
        if ($result !== false) {
            // Log to database as sent
            $this->logEmail($to, $subject, 'sent (file)');
            echo "📧 Email saved to file: {$filename}\n";
            return true;
        } else {
            $this->logEmail($to, $subject, 'failed (file write error)');
            return false;
        }
    }
    
    /**
     * Log email activity to database
     */
    private function logEmail($to, $subject, $status) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO email_logs (recipient_email, subject, status, sent_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$to, $subject, $status]);
        } catch (Exception $e) {
            error_log("Email logging error: " . $e->getMessage());
        }
    }
    
    /**
     * Notification methods (same as EmailService)
     */
    public function notifyAdminNewRegistration($username, $email, $role) {
        global $pdo;
        
        // Get admin emails
        $stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' AND status = 'approved'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $success = true;
        foreach ($admins as $adminEmail) {
            $result = $this->sendTemplate($adminEmail, 'new_user_registration', [
                'subject' => 'New User Registration Pending Approval',
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'approval_link' => 'http://localhost/bizautopro-system/view_pending_users.php'
            ]);
            if (!$result) $success = false;
        }
        return $success;
    }
    
    public function sendApprovalNotification($userEmail, $username) {
        return $this->sendTemplate($userEmail, 'user_approved', [
            'subject' => 'Account Approved - Welcome to BizAutoPro',
            'username' => $username,
            'login_link' => 'http://localhost/bizautopro-system/login.php'
        ]);
    }
    
    /**
     * List saved email files
     */
    public function listSavedEmails() {
        $files = glob($this->email_dir . '/*.html');
        rsort($files); // Most recent first
        
        echo "📂 Saved Email Files:\n";
        echo "==================\n";
        
        if (empty($files)) {
            echo "No email files found.\n";
            return;
        }
        
        foreach (array_slice($files, 0, 10) as $file) { // Show last 10
            $filename = basename($file);
            $filesize = filesize($file);
            $filetime = date('Y-m-d H:i:s', filemtime($file));
            
            echo "📧 {$filename} ({$filesize} bytes) - {$filetime}\n";
        }
        
        echo "\nTo view emails, open files in: {$this->email_dir}\n";
    }
}