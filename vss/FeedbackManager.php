<?php
class FeedbackManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Submit feedback with enhanced fields
    public function submitFeedback($studentId, $type, $subject, $category, $message, $rating, $priority = 'medium') {
        $stmt = $this->pdo->prepare("
            INSERT INTO mess_feedback 
            (student_id, feedback_type, subject, category, message, rating, priority) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$studentId, $type, $subject, $category, $message, $rating, $priority]);
    }
    
    // Get feedback with filters
    public function getFeedback($hostelId = null, $status = null, $type = null, $limit = 50) {
        $sql = "
            SELECT mf.*, s.name as student_name, s.grn, 
                   u.username as reviewed_by_name,
                   COUNT(fr.id) as response_count
            FROM mess_feedback mf 
            JOIN students s ON mf.student_id = s.id 
            LEFT JOIN users u ON mf.reviewed_by = u.id
            LEFT JOIN feedback_responses fr ON mf.id = fr.feedback_id
        ";
        
        $params = [];
        $conditions = [];
        
        if ($hostelId) {
            $conditions[] = "s.hostel_id = ?";
            $params[] = $hostelId;
        }
        
        if ($status) {
            $conditions[] = "mf.status = ?";
            $params[] = $status;
        }
        
        if ($type) {
            $conditions[] = "mf.feedback_type = ?";
            $params[] = $type;
        }
        
        if ($conditions) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " GROUP BY mf.id ORDER BY mf.created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Update feedback status with response
    public function updateFeedbackStatus($feedbackId, $status, $reviewerId, $responseMessage = null) {
        $this->pdo->beginTransaction();
        
        try {
            // Update feedback status
            $stmt = $this->pdo->prepare("
                UPDATE mess_feedback 
                SET status = ?, reviewed_by = ?, reviewed_at = NOW(), response_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$status, $reviewerId, $responseMessage, $feedbackId]);
            
            // Add response if provided
            if ($responseMessage) {
                $responseType = ($status == 'resolved') ? 'resolution' : 'action_taken';
                $stmt = $this->pdo->prepare("
                    INSERT INTO feedback_responses 
                    (feedback_id, responder_id, response_message, response_type) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$feedbackId, $reviewerId, $responseMessage, $responseType]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollback();
            return false;
        }
    }
    
    // Get feedback statistics
    public function getFeedbackStats($hostelId = null, $days = 30) {
        $sql = "
            SELECT 
                feedback_type,
                status,
                priority,
                COUNT(*) as count,
                AVG(rating) as avg_rating
            FROM mess_feedback mf
            JOIN students s ON mf.student_id = s.id
            WHERE mf.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $params = [$days];
        
        if ($hostelId) {
            $sql .= " AND s.hostel_id = ?";
            $params[] = $hostelId;
        }
        
        $sql .= " GROUP BY feedback_type, status, priority";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    // Get categories
    public function getCategories() {
        $stmt = $this->pdo->prepare("SELECT * FROM feedback_categories WHERE is_active = 1 ORDER BY category_name");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>