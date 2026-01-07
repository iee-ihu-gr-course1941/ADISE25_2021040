<?php
// game.php

function show_status() {
    global $mysqli;
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
        // Εδώ πατήθηκε το κουμπί RESET GAME.
        // Εδώ πρεπει να τους σβήσουμε όλους για να αρχίσουμε από το μηδέν.
        global $mysqli;
        $mysqli->query("TRUNCATE TABLE players");
        $mysqli->query("UPDATE game_status SET status='waiting', board='[]', deck='[]', p_turn=NULL WHERE id=1");
        header('Content-type: application/json');
        print json_encode(['status' => 'ok', 'message' => 'Game Reset']);
    }
}

// Αυτή η συνάρτηση καλείται από το users.php όταν μπει ο 2ος παίκτης
function reset_board() {
    global $mysqli;

    // Φτιάχνουμε τράπουλα
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

    // Ενημερώνουμε το status σε STARTED
    $sql = "UPDATE game_status SET status='started', p_turn='P1', board=?, deck=? WHERE id=1";
    $st = $mysqli->prepare($sql);
    $b_json = json_encode($board);
    $d_json = json_encode($deck);
    $st->bind_param('ss', $b_json, $d_json);
    $st->execute();

    // Μοιράζουμε στους υπάρχοντες παίκτες
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

function handle_play($method, $input) {
    if($method!='POST') return;
    global $mysqli;

    $token = $input['token'];
    $cardIndex = $input['cardIndex'];

    $st = $mysqli->prepare("SELECT * FROM players WHERE token=?");
    $st->bind_param('s', $token);
    $st->execute();
    $player = $st->get_result()->fetch_assoc();

    if(!$player) return;

    $game = $mysqli->query("SELECT * FROM game_status WHERE id=1")->fetch_assoc();

    if($game['status'] != 'started') return;
    if($game['p_turn'] != $player['p_turn']) return;

    $hand = json_decode($player['hand']);
    $board = json_decode($game['board']);

    if(!isset($hand[$cardIndex])) return; // Ασφάλεια

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

    $newScore = $player['score'] + $points;
    $stmt = $mysqli->prepare("UPDATE players SET hand=?, score=? WHERE token=?");
    $h_json = json_encode($hand);
    $stmt->bind_param('sis', $h_json, $newScore, $token);
    $stmt->execute();

    $nextTurn = ($game['p_turn'] == 'P1') ? 'P2' : 'P1';
    $stmt = $mysqli->prepare("UPDATE game_status SET board=?, p_turn=? WHERE id=1");
    $b_json = json_encode($board);
    $stmt->bind_param('ss', $b_json, $nextTurn);
    $stmt->execute();

    check_redeal($game['deck']);

    header('Content-type: application/json');
    print json_encode(['status' => 'ok', 'message' => $message]);
}

function get_card_points($card) {
    $parts = explode('-', $card);
    $val = intval($parts[1]);
    if ($val == 1 || $val >= 11) return 1;
    if ($val == 10 && $parts[0] == 'D') return 2;
    if ($val == 2 && $parts[0] == 'C') return 1;
    return 0;
}

function check_redeal($currentDeckJson) {
    global $mysqli;

    $hand1 = json_decode($mysqli->query("SELECT hand FROM players WHERE p_turn='P1'")->fetch_assoc()['hand']);
    $hand2 = json_decode($mysqli->query("SELECT hand FROM players WHERE p_turn='P2'")->fetch_assoc()['hand']);
    $deck = json_decode($currentDeckJson);

    if (count($hand1) == 0 && count($hand2) == 0) {
        if(count($deck) > 0) {
            $newHand1 = array_splice($deck, 0, 6);
            $newHand2 = array_splice($deck, 0, 6);

            $st = $mysqli->prepare("UPDATE players SET hand=? WHERE p_turn='P1'");
            $h1 = json_encode($newHand1);
            $st->bind_param('s', $h1);
            $st->execute();

            $st = $mysqli->prepare("UPDATE players SET hand=? WHERE p_turn='P2'");
            $h2 = json_encode($newHand2);
            $st->bind_param('s', $h2);
            $st->execute();

            $st = $mysqli->prepare("UPDATE game_status SET deck=? WHERE id=1");
            $d_json = json_encode($deck);
            $st->bind_param('s', $d_json);
            $st->execute();
        } else {
            $mysqli->query("UPDATE game_status SET status='ended' WHERE id=1");
        }
    }
}
?>