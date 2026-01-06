<?php
// xeri.php

// 1. Ενεργοποίηση Error Reporting για να βλέπουμε τι γίνεται
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Σύνδεση στη Βάση (ΜΙΑ ΦΟΡΑ)
require_once "lib/dbconnect.php";

// 3. Φόρτωση Λογικής Παιχνιδιού
require_once "lib/users.php";
require_once "lib/game.php";

// 4. Ανάγνωση Αιτήματος (Router)
$method = $_SERVER['REQUEST_METHOD'];
$request = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$input = json_decode(file_get_contents('php://input'), true);
if ($input == null) {
    $input = [];
}

// Token από Headers
if (isset($_SERVER['HTTP_X_TOKEN'])) {
    $input['token'] = $_SERVER['HTTP_X_TOKEN'];
}

// Δρομολόγηση
$r = array_shift($request);

switch ($r) {
    case 'players':
        handle_player($method, $request, $input);
        break;
    case 'status':
        show_status();
        break;
    case 'board':
        handle_board($method);
        break;
    case 'play':
        handle_play($method, $input);
        break;
    default:
        header("HTTP/1.1 404 Not Found");
        echo json_encode(['error' => 'Path not found']);
        exit;
}
?>