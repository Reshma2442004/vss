<?php
require_once 'config/database.php';

echo "<h2>VSS System Fix - User Roles & Authentication</h2>";

// 1. Fix users with empty or 'staff' roles
echo "<h3>1. Fixing User Roles</h3>";
$query = "
    SELECT u.id as user_id, u.username, u.role as current_role, s.role as staff_role 
    FROM users u 
    LEFT JOIN staff s ON u.id = s.user_id 
    WHERE u.role IS NULL OR u.role = '' OR u.role = 'staff'
";

$stmt = $pdo->query($query);
$users = $stmt->fetchAll();

foreach ($users as $user) {
    if ($user['staff_role']) {
        $update = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $update->execute([$user['staff_role'], $user['user_id']]);
        echo "✅ Updated {$user['username']}: '{$user['current_role']}' → '{$user['staff_role']}'<br>";
    } else {
        echo "⚠️ User {$user['username']} has no staff record - skipping<br>";
    }
}

// 2. Check for missing dashboard files
echo "<h3>2. Checking Dashboard Files</h3>";
$required_dashboards = [
    'super_admin.php', 'rector.php', 'student_head.php', 'mess_head.php',
    'library_head.php', 'health_staff.php', 'vvk_staff.php', 'placement_staff.php',
    'ed_cell_staff.php', 'scholarship_staff.php', 'student.php'
];

foreach ($required_dashboards as $dashboard) {
    $path = "dashboards/{$dashboard}";
    if (file_exists($path)) {
        echo "✅ {$dashboard} exists<br>";
    } else {
        echo "❌ {$dashboard} missing<br>";
    }
}

// 3. Show current user status
echo "<h3>3. Current User Status</h3>";
$all_users = $pdo->query("SELECT u.username, u.role, s.role as staff_role FROM users u LEFT JOIN staff s ON u.id = s.user_id ORDER BY u.id")->fetchAll();

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Username</th><th>User Role</th><th>Staff Role</th><th>Status</th></tr>";

foreach ($all_users as $user) {
    $status = empty($user['role']) ? '❌ No Role' : '✅ Ready';
    echo "<tr>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['role']}</td>";
    echo "<td>{$user['staff_role']}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><h3>✅ Fix Complete!</h3>";
echo "<a href='auth/login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login</a>";
?>