<?php

require_once "dbconnect.php";

// Σβήνουμε τους παλιούς (λάθος) πίνακες
$mysqli->query("DROP TABLE IF EXISTS players");
$mysqli->query("DROP TABLE IF EXISTS game_status");

//  Game Status Table
$sql1 = "CREATE TABLE game_status (
    id INT PRIMARY KEY,
    status ENUM('waiting', 'started', 'ended', 'aborted') DEFAULT 'waiting',
    board JSON DEFAULT NULL,
    deck JSON DEFAULT NULL,
    p_turn ENUM('P1','P2') DEFAULT NULL,
    result ENUM('P1','P2','D') DEFAULT NULL,
    last_change TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

//  Players Table
$sql2 = "CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    token VARCHAR(50) NOT NULL,
    p_turn ENUM('P1','P2') DEFAULT NULL,
    hand JSON DEFAULT NULL,
    score INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

// Αρχικοποίηση
$sql3 = "INSERT INTO game_status (id, status, board, deck) VALUES (1, 'waiting', '[]', '[]');";
// Εκτέλεση queries
if($mysqli->query($sql1) && $mysqli->query($sql2) && $mysqli->query($sql3)) {
    echo "<h1>✅ ΕΠΙΤΥΧΙΑ!</h1> <p>Οι πίνακες φτιάχτηκαν σωστά (με το p_turn).</p>";
    echo "<a href='index.html'>👉 Πάμε στο Παιχνίδι</a>";
} else {
    echo "<h1>❌ Σφάλμα:</h1> " . $mysqli->error;
}
?>