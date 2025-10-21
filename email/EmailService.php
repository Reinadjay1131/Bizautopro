<?php

/**
 * Email Service for BizAutoPro
 * Simple email service using PHP's built-in mail() function
 * Can be upgraded to PHPMailer/SwiftMailer later
 */
class EmailService {
    private $from_email;
    private $from_name;
    private $smtp_enabled;
    
    public function __construct() {
        $this->from_email = 'noreply@bizautopro.com';
        $this->from_name = 'BizAutoPro System';
        $this->smtp_enabled = false; // Set to true when SMTP is configured
    }
    
    /**
     * Send email using template
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
            
            // Get subject from template (should be set in template)
            $subject = $data['subject'] ?? 'BizAutoPro Notification';
            
            return $this->sendEmail($to, $subject, $body);
            
        } catch (Exception $e) {
            error_log("Email Service Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send plain email
     */
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        try {
            $headers = [
                'From' => "{$this->from_name} <{$this->from_email}>",
                'Reply-To' => $this->from_email,
                'X-Mailer' => 'BizAutoPro Email Service'
            ];
            
            if ($isHTML) {
                $headers['MIME-Version'] = '1.0';
                $headers['Content-type'] = 'text/html; charset=UTF-8';
            }
            
            $headerString = '';
            foreach ($headers as $key => $value) {
                $headerString .= "{$key}: {$value}\r\n";
            }
            
            // Log email attempt
            $this->logEmail($to, $subject, 'sending');
            
            // Send email
            $result = mail($to, $subject, $body, $headerString);
            
            // Log result
            $this->logEmail($to, $subject, $result ? 'sent' : 'failed');
            
            return $result;
            
        } catch (Exception $e) {
            $this->logEmail($to, $subject, 'error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send notification for new user registration
     */
    public function notifyAdminNewRegistration($username, $email, $role) {
        global $pdo;
        
        // Get admin emails
        $stmt = $pdo->prepare("SELECT email FROM users WHERE role = 'admin' AND status = 'approved'");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($admins as $adminEmail) {
            $this->sendTemplate($adminEmail, 'new_user_registration', [
                'subject' => 'New User Registration Pending Approval',
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'approval_link' => 'http://localhost/bizautopro-system/view_pending_users.php'
            ]);
        }
    }
    
    /**
     * Send welcome email to approved user
     */
    public function sendApprovalNotification($userEmail, $username) {
        $this->sendTemplate($userEmail, 'user_approved', [
            'subject' => 'Account Approved - Welcome to BizAutoPro',
            'username' => $username,
            'login_link' => 'http://localhost/bizautopro-system/login.php'
        ]);
    }
    
    /**
     * Send inventory alert
     */
    public function sendInventoryAlert($productName, $currentStock, $reorderLevel) {
        global $pdo;
        
        // Get inventory managers and admins
        $stmt = $pdo->prepare("SELECT email FROM users WHERE (role = 'inventory_manager' OR role = 'admin') AND status = 'approved'");
        $stmt->execute();
        $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($recipients as $email) {
            $this->sendTemplate($email, 'inventory_alert', [
                'subject' => 'Low Stock Alert - ' . $productName,
                'product_name' => $productName,
                'current_stock' => $currentStock,
                'reorder_level' => $reorderLevel,
                'inventory_link' => 'http://localhost/bizautopro-system/inventory.php'
            ]);
        }
    }
    
    /**
     * Send lead notification
     */
    public function notifyLeadAssignment($leadId, $assignedTo, $leadData) {
        global $pdo;
        
        // Get assigned user's email
        $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ? AND status = 'approved'");
        $stmt->execute([$assignedTo]);
        $userEmail = $stmt->fetchColumn();
        
        if ($userEmail) {
            $this->sendTemplate($userEmail, 'lead_assignment', [
                'subject' => 'New Lead Assigned - ' . $leadData['company_name'],
                'lead_id' => $leadId,
                'company_name' => $leadData['company_name'],
                'contact_person' => $leadData['contact_person'],
                'lead_link' => 'http://localhost/bizautopro-system/leads.php'
            ]);
        }
    }
    
    /**
     * Send workflow notification
     */
    public function notifyWorkflowUpdate($workflowId, $status, $assignedUsers) {
        global $pdo;
        
        // Get workflow details
        $stmt = $pdo->prepare("SELECT title, description FROM workflows WHERE id = ?");
        $stmt->execute([$workflowId]);
        $workflow = $stmt->fetch();
        
        if ($workflow) {
            foreach ($assignedUsers as $username) {
                $stmt = $pdo->prepare("SELECT email FROM users WHERE username = ? AND status = 'approved'");
                $stmt->execute([$username]);
                $userEmail = $stmt->fetchColumn();
                
                if ($userEmail) {
                    $this->sendTemplate($userEmail, 'workflow_update', [
                        'subject' => 'Workflow Update - ' . $workflow['title'],
                        'workflow_title' => $workflow['title'],
                        'workflow_status' => $status,
                        'workflow_link' => 'http://localhost/bizautopro-system/workflows.php'
                    ]);
                }
            }
        }
    }
    
    /**
     * Log email activity
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
     * Get email statistics
     */
    public function getEmailStats($days = 30) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                DATE(sent_at) as date
            FROM email_logs 
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status, DATE(sent_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$days]);
        
        return $stmt->fetchAll();
    }
}