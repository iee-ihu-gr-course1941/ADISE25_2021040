<?php
// lib/game.php

function show_status() {
    global $mysqli;
    check_abort(); // Έλεγχος αν πρέπει να λήξει λόγω χρόνου
    $sql = 'select * from game_status';
    $st = $mysqli->prepare($sql);
    $st->execute();
    $res = $st->get_result();
    header('Content-type: application/json');
    print json_encode($res->fetch_all(MYSQLI_ASSOC));
}

function handle_board($method) {
    if($method=='GET') {
        show_status();
    } else if ($method=='POST') {
        do_full_reset();
    } else {
        header("HTTP/1.1 405 Method Not Allowed");
    }
}

function reset_board() {
    global $mysqli;
    $suits = ['H', 'D', 'C', 'S'];
    $deck = [];
    for($i=1; $i<=13; $i++) {
        foreach($suits as $s) {
            $deck[] = "$s-$i";
        }
    }
    shuffle($deck);

    $board = array_splice($deck, 0, 4);
    $hand1 = array_splice($deck, 0, 6);
    $hand2 = array_splice($deck, 0, 6);

    // ΔΙΟΡΘΩΣΗ: Προσθέσαμε WHERE id=1 για να μην σκάει η MySQL
    $sql = "UPDATE game_status SET status='started', p_turn='P1', board=?, deck=? WHERE id=1";
    $st = $mysqli->prepare($sql);
    $b_json = json_encode($board);
    $d_json = json_encode($deck);
    $st->bind_param('ss', $b_json, $d_json);
    $st->execute();

    $sql = "UPDATE players SET hand=? WHERE p_turn='P1'";
    $st = $mysqli->prepare($sql);
    $h1 = json_encode($hand1);
    $st->bind_param('s', $h1);
    $st->execute();

    $sql = "UPDATE players SET hand=? WHERE p_turn='P2'";
    $st = $mysqli->prepare($sql);
    $h2 = json_encode($hand2);
    $st->bind_param('s', $h2);
    $st->execute();
}

function get_card_points($card) {
    $parts = explode('-', $card);
    $suit = $parts[0];
    $val = intval($parts[1]);

    if ($val == 1) return 1;
    if ($val >= 11 && $val <= 13) return 1;
    if ($val == 10 && $suit == 'D') return 2;
    if ($val == 2 && $suit == 'C') return 1;
    return 0;
}

function handle_play($method, $input) {
    if($method!='POST') { header("HTTP/1.1 405 Method Not Allowed"); return; }
    global $mysqli;

    if(!isset($input['token']) || !isset($input['cardIndex'])) {
        print json_encode(['error' => 'Missing data']); return;
    }

    $token = $input['token'];
    $cardIndex = $input['cardIndex'];

    $sql = "SELECT * FROM players WHERE token=?";
    $st = $mysqli->prepare($sql);
    $st->bind_param('s', $token);
    $st->execute();
    $res = $st->get_result();
    $player = $res->fetch_assoc();

    if(!$player) { print json_encode(['error' => 'Invalid token']); return; }

    $sql = "SELECT * FROM game_status WHERE id=1";
    $game = $mysqli->query($sql)->fetch_assoc();

    if($game['status'] != 'started') {
        print json_encode(['error' => 'Game not started']); return;
    }

    if($game['p_turn'] != $player['p_turn']) {
        print json_encode(['error' => 'Wait for your turn!']); return;
    }

    $hand = json_decode($player['hand']);
    $board = json_decode($game['board']);

    if(!isset($hand[$cardIndex])) { print json_encode(['error' => 'Card not found']); return; }

    $playedCard = $hand[$cardIndex];
    array_splice($hand, $cardIndex, 1);

    $playedVal = explode('-', $playedCard)[1];
    $points = 0;
    $message = "";

    // Λογική παιχνιδιού
    if(empty($board)) {
        $board[] = $playedCard;
    } else {
        $topCard = end($board);
        $topVal = explode('-', $topCard)[1];

        if($playedVal == $topVal || $playedVal == 11) {
            $points += get_card_points($playedCard);
            foreach($board as $c) { $points += get_card_points($c); }

            if(count($board) == 1 && $playedVal == $topVal) {
                $points += 10;
                $message = "XERI!";
                if($playedVal == 11) { $points += 10; $message = "XERI JACK!"; }
            }
            $board = [];
        } else {
            $board[] = $playedCard;
        }
    }

    // Update Player
    $newScore = $player['score'] + $points;
    $stmt = $mysqli->prepare("UPDATE players SET hand=?, score=? WHERE token=?");
    $h_json = json_encode($hand);
    $stmt->bind_param('sis', $h_json, $newScore, $token);
    $stmt->execute();

    // Update Game
    $nextTurn = ($game['p_turn'] == 'P1') ? 'P2' : 'P1';
    $stmt = $mysqli->prepare("UPDATE game_status SET board=?, p_turn=?, last_change=NOW() WHERE id=1");
    $b_json = json_encode($board);
    $stmt->bind_param('ss', $b_json, $nextTurn);
    $stmt->execute();

    // Check Re-deal
    check_redeal($game['deck']);

    header('Content-type: application/json');
    print json_encode(['status' => 'ok', 'message' => $message]);
}

function check_redeal($currentDeckJson) {
    global $mysqli;
    $res1 = $mysqli->query("SELECT hand FROM players WHERE p_turn='P1'");
    $hand1 = json_decode($res1->fetch_assoc()['hand']);
    $res2 = $mysqli->query("SELECT hand FROM players WHERE p_turn='P2'");
    $hand2 = json_decode($res2->fetch_assoc()['hand']);
    $deck = json_decode($currentDeckJson);

    if (count($hand1) == 0 && count($hand2) == 0) {
        if(count($deck) > 0) {
            $newHand1 = array_splice($deck, 0, 6);
            $newHand2 = array_splice($deck, 0, 6);

            $stmt = $mysqli->prepare("UPDATE players SET hand=? WHERE p_turn='P1'");
            $h1 = json_encode($newHand1);
            $stmt->bind_param('s', $h1);
            $stmt->execute();

            $stmt = $mysqli->prepare("UPDATE players SET hand=? WHERE p_turn='P2'");
            $h2 = json_encode($newHand2);
            $stmt->bind_param('s', $h2);
            $stmt->execute();

            $stmt = $mysqli->prepare("UPDATE game_status SET deck=? WHERE id=1");
            $d_json = json_encode($deck);
            $stmt->bind_param('s', $d_json);
            $stmt->execute();
        } else {
            $mysqli->query("UPDATE game_status SET status='ended' WHERE id=1");
        }
    }
}

function check_abort() {
    // Αν θες να λήγει αυτόματα μετά από 5 λεπτά αδράνειας
    global $mysqli;
    $sql = "UPDATE game_status SET status='aborted' WHERE status='started' AND last_change < (NOW() - INTERVAL 5 MINUTE) AND id=1";
    $mysqli->query($sql);
}

function do_full_reset() {
    global $mysqli;
    $mysqli->query("TRUNCATE TABLE players");
    $mysqli->query("TRUNCATE TABLE game_status");
    $mysqli->query("INSERT INTO game_status(id, status, board, deck) VALUES(1, 'waiting', '[]', '[]')");
    header('Content-type: application/json');
    print json_encode(['status' => 'ok', 'message' => 'Game Reset Successfully']);
}
?>