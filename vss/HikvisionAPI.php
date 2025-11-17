<?php
class HikvisionAPI {
    private $ip;
    private $username;
    private $password;
    private $pdo;
    
    public function __construct($ip, $username, $password, $pdo) {
        $this->ip = $ip;
        $this->username = $username;
        $this->password = $password;
        $this->pdo = $pdo;
    }
    
    // Fetch attendance logs from device
    public function fetchAttendanceLogs($startTime, $endTime) {
        $url = "http://{$this->ip}/ISAPI/AccessControl/AcsEvent?format=json";
        
        $data = json_encode([
            'AcsEventCond' => [
                'searchID' => '1',
                'searchResultPosition' => 0,
                'maxResults' => 1000,
                'major' => 5,
                'minor' => 75,
                'startTime' => $startTime,
                'endTime' => $endTime
            ]
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        }
        return false;
    }
    
    // Process and store logs in database
    public function processLogs($logs, $deviceId) {
        if (!isset($logs['AcsEvent']['InfoList'])) return 0;
        
        $processed = 0;
        foreach ($logs['AcsEvent']['InfoList'] as $log) {
            $fingerId = $log['cardNo'] ?? null;
            $eventTime = $log['time'] ?? null;
            
            if (!$fingerId || !$eventTime) continue;
            
            // Get student by finger_id
            $stmt = $this->pdo->prepare("SELECT id FROM students WHERE finger_id = ?");
            $stmt->execute([$fingerId]);
            $student = $stmt->fetch();
            
            if (!$student) continue;
            
            // Determine event type based on time
            $hour = date('H', strtotime($eventTime));
            $eventType = $this->getEventType($hour, $deviceId);
            
            // Insert log
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO attendance_logs 
                (student_id, finger_id, event_time, event_type, device_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$student['id'], $fingerId, $eventTime, $eventType, $deviceId]);
            $processed++;
        }
        
        return $processed;
    }
    
    private function getEventType($hour, $deviceId) {
        // Get device type
        $stmt = $this->pdo->prepare("SELECT device_type FROM biometric_devices WHERE id = ?");
        $stmt->execute([$deviceId]);
        $device = $stmt->fetch();
        
        if ($device['device_type'] == 'mess') {
            return ($hour >= 8 && $hour <= 10) ? 'mess_morning' : 'mess_night';
        }
        return 'hostel_checkin';
    }
}
?>