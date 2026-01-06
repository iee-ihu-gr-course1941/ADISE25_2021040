var me = { token: null, p_turn: null };

function login() {
    var user = $('#username').val();
    if(!user) { alert("Βάλε όνομα!"); return; }

    $.ajax({
        url: "xeri.php/players",
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ username: user }),
        success: function(data) {
            me.token = data.token;
            me.p_turn = data.p_turn;

            $('#login-screen').hide();
            $('#game-area').show();

            setInterval(update_game_status, 2000);
        },
        error: function(err) { alert("Error: " + err.responseJSON.error); }
    });
}


function update_game_status() {
    if(!me.token) return;

    $.ajax({
        url: "xeri.php/status",
        success: function(data) {
            var game = data[0];

            // 1. Ενημέρωση Τράπουλας (ΝΕΟ)
            var deck = JSON.parse(game.deck);
            $('#deck-count').text(deck.length);

            // 2. Έλεγχος αν τελείωσε (ΝΕΟ)
            if(game.status === 'ended') {
                $('#game-over-screen').show();
                // Παίρνουμε τα τελικά σκορ από το scoreboard
                var myScore = $('#my-details').text();
                var oppScore = $('#opp-details').text();
                $('#final-score').html(myScore + "<br>" + oppScore);
                return; // Σταματάμε εδώ
            }

            if(game.status === 'started') {
                update_scores(); // Συνεχής ενημέρωση σκορ
                render_cards(JSON.parse(game.board), '#board');

                if(game.p_turn === me.p_turn) {
                    $('#status-text').text("👉 ΣΕΙΡΑ ΣΟΥ!").css('color', '#2ecc71');
                    fetch_hand();
                } else {
                    $('#status-text').text("⏳ Παίζει ο αντίπαλος...").css('color', 'white');
                    fetch_hand();
                }
            } else {
                $('#status-text').text("Περιμένουμε 2ο παίκτη...");
            }
        }
    });
}

function fetch_hand() {
    $.ajax({
        url: "xeri.php/players",
        method: "GET",
        headers: { "X-Token": me.token }, // Στέλνουμε το Token για να μας αναγνωρίσει
        success: function(data) {
            // Ζωγραφίζουμε το χέρι μας
            render_cards(JSON.parse(data.hand), '#hand');
        }
    });
}

// Η συνάρτηση που φτιάχνει τα ΩΡΑΙΑ φύλλα
function render_cards(cardsData, containerId) {
    var html = '';
    if(cardsData) {
        cardsData.forEach(function(cardStr, index) { // Πρόσθεσα το index
            var parts = cardStr.split('-');
            var suit = parts[0];
            var val = parts[1];

            var symbol = '';
            var colorClass = '';

            if(suit === 'H') { symbol = '♥'; colorClass = 'red'; }
            else if(suit === 'D') { symbol = '♦'; colorClass = 'red'; }
            else if(suit === 'C') { symbol = '♣'; colorClass = ''; }
            else if(suit === 'S') { symbol = '♠'; colorClass = ''; }

            if(val == 1) val = 'A';
            else if(val == 11) val = 'J';
            else if(val == 12) val = 'Q';
            else if(val == 13) val = 'K';

            // Αν είναι το χέρι μας (#hand), βάζουμε onclick
            var clickAction = '';
            if(containerId === '#hand') {
                clickAction = 'onclick="play_card(' + index + ')"';
            }

            html += '<div class="card ' + colorClass + '" ' + clickAction + '>' + val + symbol + '</div>';
        });
    }
    $(containerId).html(html);
}

// Η νέα συνάρτηση που στέλνει την κίνηση
function play_card(index) {
    // Αν δεν είναι η σειρά μας, μην κάνεις τίποτα
    if($('#status-text').text() !== "👉 ΣΕΙΡΑ ΣΟΥ!") {
        alert("Περίμενε τη σειρά σου!");
        return;
    }

    $.ajax({
        url: "xeri.php/play",
        method: "POST",
        headers: { "X-Token": me.token },
        contentType: "application/json",
        data: JSON.stringify({ cardIndex: index }),
        success: function(data) {
            if(data.message) alert(data.message); // Αν έκανες Ξερή!
            update_game_status(); // Ανανέωσε το τραπέζι αμέσως
        },
        error: function(err) {
            alert("Error: " + err.responseJSON.error);
        }
    });
}


function play_card(index) {
    // Αν δεν είναι η σειρά μας, μην κάνεις τίποτα
    if($('#status-text').text() !== "👉 ΣΕΙΡΑ ΣΟΥ!") {
        alert("Περίμενε τη σειρά σου!");
        return;
    }

    $.ajax({
        url: "xeri.php/play",
        method: "POST",
        headers: { "X-Token": me.token },
        contentType: "application/json",
        data: JSON.stringify({ cardIndex: index }),
        success: function(data) {
            if(data.message) alert(data.message); // Αν έκανες Ξερή!
            update_game_status(); // Ανανέωσε το τραπέζι αμέσως
        },
        error: function(err) {
            alert("Error: " + err.responseJSON.error);
        }
    });
}



function reset_game() {
    $.ajax({
        url: "xeri.php/board", // Σύμφωνα με το PDF, το POST στο board κάνει reset
        method: "POST",
        success: function(data) {
            alert("Το παιχνίδι έγινε Reset!");
            location.reload(); // Ξαναφορτώνουμε τη σελίδα για να μπούμε από την αρχή
        },
        error: function(err) {
            alert("Κάτι πήγε στραβά με το Reset.");
        }
    });
}





function update_scores() {
    $.ajax({
        url: "xeri.php/players",
        method: "GET",
        success: function(players) {
            players.forEach(function(p) {
                // ΔΙΟΡΘΩΣΗ: Συγκρίνουμε με το p_turn (P1 ή P2) που είναι μοναδικό
                if(p.p_turn === me.p_turn) {
                    $('#my-details').text("Εγώ: " + p.username + " (" + p.score + ")");
                } else {
                    $('#opp-details').text("Αντίπαλος: " + p.username + " (" + p.score + ")");
                }
            });
        }
    });
}