<?php

function handle_player($method, $request, $input) {
    if($method=='GET' && isset($input['token'])) {
        show_user($input['token']);
    }
    else if($method=='GET') {
        show_users();
    }
    else if($method=='POST') {
        handle_login($input);
    }
    else {
        header("HTTP/1.1 405 Method Not Allowed");
    }
}

function show_user($token) {
    global $mysqli;
    $sql = 'select username, p_turn, hand, score from players where token=?';
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $token);
    $st->execute();
    $res = $st->get_result();
    if($row = $res->fetch_assoc()) {
        header('Content-type: application/json');
        print json_encode($row);
    } else {
        header("HTTP/1.1 404 Not Found");
        print json_encode(['error' => 'Player not found']);
    }
}

function show_users() {
    global $mysqli;
    $sql = 'select username, p_turn, score from players';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    header('Content-type: application/json');
    print json_encode($res->fetch_all(MYSQLI_ASSOC));
}

function handle_login($input) {
    global $mysqli;
    if(!isset($input['username'])) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['error' => 'Username is required']);
        return;
    }
    $username = $input['username'];

    $sql = "SELECT count(*) as c FROM players";
    $res = $mysqli->query($sql);
    $count = $res->fetch_assoc()['c'];

    if($count >= 2) {
        header("HTTP/1.1 400 Bad Request");
        print json_encode(['error' => 'Game is full']);
        return;
    }

    $p_turn = ($count == 0) ? 'P1' : 'P2';
    $token = bin2hex(random_bytes(16));

    $sql = "INSERT INTO players(username, token, p_turn, hand) VALUES(?, ?, ?, '[]')";
    $st = $mysqli->prepare($sql);
    $st->bind_param('sss', $username, $token, $p_turn);
    $st->execute();

    if($count == 1) { // Αν μπήκε και ο δεύτερος
        reset_board();
    }

    header('Content-type: application/json');
    print json_encode(['token' => $token, 'p_turn' => $p_turn]);
}
?>