<?php
// Setup instructions for biometric sync
echo "BIOMETRIC INTEGRATION SETUP\n";
echo "==========================\n\n";

echo "1. Run database setup:\n";
echo "   php init_database.php\n";
echo "   mysql -u root -p vss < biometric_setup.sql\n\n";

echo "2. Configure devices in biometric_devices table:\n";
echo "   UPDATE biometric_devices SET device_ip='YOUR_DEVICE_IP', username='admin', password='YOUR_PASSWORD';\n\n";

echo "3. Add students' finger IDs:\n";
echo "   UPDATE students SET finger_id=UNIQUE_ID WHERE grn='STUDENT_GRN';\n\n";

echo "4. Setup automatic sync (Windows Task Scheduler):\n";
echo "   Task: php " . __DIR__ . "\\sync_biometric.php\n";
echo "   Schedule: Every 15 minutes\n\n";

echo "5. Manual sync command:\n";
echo "   php sync_biometric.php\n\n";

echo "6. USB import (fallback):\n";
echo "   Access: http://localhost/vss/usb_log_parser.php\n\n";

echo "DEVICE CONFIGURATION:\n";
echo "- Mess Device: 192.168.1.100 (classify 8-10AM as morning, 7-9PM as night)\n";
echo "- Hostel Device: 192.168.1.101 (8-9PM = Present, 9PM+ = Late)\n";
?>