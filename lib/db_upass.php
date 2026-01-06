<?php
// lib/db_upass.php

// 1. ΡΥΘΜΙΣΕΙΣ (CREDENTIALS)
$DB_USER = 'iee2019081';
$DB_PASS = ''; // <--- ΠΡΟΣΟΧΗ: Βάλε τον κωδικό σου εδώ!
$DB_NAME = 'xeri_db';         // Το όνομα της βάσης που έφτιαξες

// 2. ΣΥΝΔΕΣΗ (CONNECTION LOGIC)
if (gethostname() == 'users.iee.ihu.gr') {
    // --- ΡΥΘΜΙΣΕΙΣ ΓΙΑ ΤΟΝ SERVER ΤΗΣ ΣΧΟΛΗΣ ---

    // Το Path που βρήκαμε μέσω SSH

    $socket = '/home/student/iee/2019/iee2019081/mysql/run/mysql.sock';

    // Σύνδεση μέσω Socket
    $mysqli = new mysqli(null, $DB_USER, $DB_PASS, $DB_NAME, null, $socket);

} else {
    // --- ΡΥΘΜΙΣΕΙΣ ΓΙΑ ΤΟ ΣΠΙΤΙ (XAMPP) ---
    // Συνήθως στο XAMPP ο root δεν έχει κωδικό
    $mysqli = new mysqli('localhost', 'root', '', $DB_NAME);
}

// 3. ΕΛΕΓΧΟΣ ΛΑΘΩΝ
if ($mysqli->connect_errno) {
    // Αν αποτύχει, μας λέει γιατί
    die("❌ Αποτυχία Σύνδεσης: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error);
}



// Ρύθμιση charset σε utf8 για τα ελληνικά
$mysqli->set_charset("utf8");
?>