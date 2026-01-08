<?php
require_once __DIR__ . '/../core/auth_check.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../models/Sport.php';

$db = (new Database())->getConnection();
$sportModel = new Sport($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sportModel->add($_POST['name'], $_POST['category'], $_POST['description']);
    header('Location: sports.php');
    exit;
}

$sports = $sportModel->getAll();
include __DIR__ . '/../views/modules/sports.php';
?>