<?php
class AttendanceProcessor {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Generate daily attendance summary
    public function generateDailySummary($date) {
        // Get all students
        $stmt = $this->pdo->prepare("SELECT id FROM students");
        $stmt->execute();
        $students = $stmt->fetchAll();
        
        foreach ($students as $student) {
            $studentId = $student['id'];
            
            // Check morning meal (8:00-10:00)
            $morningMeal = $this->checkAttendance($studentId, $date, 'mess_morning') ? 'Present' : 'Absent';
            
            // Check night meal (19:00-21:00)
            $nightMeal = $this->checkAttendance($studentId, $date, 'mess_night') ? 'Present' : 'Absent';
            
            // Check hostel attendance (20:00-21:00 = Present, 21:01-23:59 = Late)
            $hostelStatus = $this->checkHostelAttendance($studentId, $date);
            
            // Insert or update summary
            $stmt = $this->pdo->prepare("
                INSERT INTO attendance_summary 
                (student_id, date, morning_meal, night_meal, hostel) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                morning_meal = VALUES(morning_meal),
                night_meal = VALUES(night_meal),
                hostel = VALUES(hostel)
            ");
            $stmt->execute([$studentId, $date, $morningMeal, $nightMeal, $hostelStatus]);
        }
    }
    
    private function checkAttendance($studentId, $date, $eventType) {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM attendance_logs 
            WHERE student_id = ? AND DATE(event_time) = ? AND event_type = ?
        ");
        $stmt->execute([$studentId, $date, $eventType]);
        return $stmt->fetch()['count'] > 0;
    }
    
    private function checkHostelAttendance($studentId, $date) {
        $stmt = $this->pdo->prepare("
            SELECT event_time 
            FROM attendance_logs 
            WHERE student_id = ? AND DATE(event_time) = ? AND event_type = 'hostel_checkin'
            ORDER BY event_time DESC LIMIT 1
        ");
        $stmt->execute([$studentId, $date]);
        $log = $stmt->fetch();
        
        if (!$log) return 'Absent';
        
        $hour = date('H', strtotime($log['event_time']));
        return ($hour >= 20 && $hour <= 21) ? 'Present' : 'Late';
    }
    
    // Get attendance report
    public function getAttendanceReport($hostelId, $startDate, $endDate) {
        $stmt = $this->pdo->prepare("
            SELECT s.name, s.grn, ats.date, ats.morning_meal, ats.night_meal, ats.hostel
            FROM attendance_summary ats
            JOIN students s ON ats.student_id = s.id
            WHERE s.hostel_id = ? AND ats.date BETWEEN ? AND ?
            ORDER BY s.name, ats.date
        ");
        $stmt->execute([$hostelId, $startDate, $endDate]);
        return $stmt->fetchAll();
    }
}
?>