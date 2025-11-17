<?php
class MessFeedback {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    public function create($student_id, $type, $subject, $rating, $message = '') {
        $query = "INSERT INTO mess_feedback (student_id, feedback_type, subject, rating, message) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$student_id, $type, $subject, $rating, $message]);
    }
    
    public function getAll() {
        $query = "SELECT mf.*, s.name as student_name, mf.created_at as date 
                  FROM mess_feedback mf 
                  JOIN students s ON mf.student_id = s.id 
                  ORDER BY mf.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function updateStatus($id, $status, $reviewed_by = null) {
        $query = "UPDATE mess_feedback SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$status, $reviewed_by, $id]);
    }
    
    public function getActions($status) {
        $actions = [
            'pending' => ['review', 'resolve'],
            'reviewed' => ['resolve', 'reopen'],
            'resolved' => ['reopen']
        ];
        return $actions[$status] ?? [];
    }
}
?>