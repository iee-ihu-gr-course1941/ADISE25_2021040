<?php
$host='localhost';
$db = 'xeri_db'; // Το όνομα της βάσης
require_once "db_upass.php";

$user=$DB_USER;
$pass=$DB_PASS;

if(gethostname()=='users.iee.ihu.gr') {
    // εδω βαζουμε το socket
    $mysqli = new mysqli($host, $user, $pass, $db, null, '/home/student/iee/2019/iee2019081/mysql/run/mysql.sock');
} else {
    // Για το σπίτι (XAMPP)
    $mysqli = new mysqli($host, 'root', '', $db);
}

if ($mysqli->connect_errno) {
    echo "Failed to connect to MySQL: (" .
    $mysqli->connect_errno . ") " . $mysqli->connect_error;
}
?>