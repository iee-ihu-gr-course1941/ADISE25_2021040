var token = "";
var p_turn = "";

function login() {
    var user = $('#username').val();
    if(user == "") {
        alert("Βάλε όνομα!");
        return;
    }

    $.ajax({
        url: "xeri.php/players",
        method: "POST",
        contentType: "application/json",
        data: JSON.stringify({ username: user }),
        success: function(data) {
            token = data.token;
            p_turn = data.p_turn;

            $('#login-screen').hide();
            $('#game-area').show();

            setInterval(update_game_status, 2000);
        },
        error: function(err) {
            alert("Κάτι πήγε στραβά με το login: " + (err.responseJSON ? err.responseJSON.error : "Unknown"));
        }
    });
}

function update_game_status() {
    if(token == "") return;

    $.ajax({
        url: "xeri.php/status",
        success: function(data) {
            var game = data[0];
            var deck = JSON.parse(game.deck);
            $('#deck-count').text(deck.length);

            if(game.status == 'ended') {
                $('#game-over-screen').show();
                var s1 = $('#my-details').text();
                var s2 = $('#opp-details').text();
                $('#final-score').html(s1 + "<br>" + s2);
                return;
            }

            if(game.status == 'started') {
                update_scores();
                render_cards(JSON.parse(game.board), '#board');

                if(game.p_turn == p_turn) {
                    $('#status-text').text("👉 ΣΕΙΡΑ ΣΟΥ!").css('color', 'green');
                } else {
                    $('#status-text').text("⏳ Παίζει ο αντίπαλος...").css('color', 'white');
                }
                fetch_hand();
            } else {
                $('#status-text').text("Περιμένουμε αντίπαλο...");
            }
        }
    });
}

function fetch_hand() {
    $.ajax({
        url: "xeri.php/players",
        method: "GET",
        headers: { "X-Token": token },
        success: function(data) {
            render_cards(JSON.parse(data.hand), '#hand');
        }
    });
}

function render_cards(cards, div_id) {
    var html = '';

    for(var i=0; i<cards.length; i++) {
        var c = cards[i];
        var parts = c.split('-');
        var s = parts[0];
        var v = parts[1];

        var symbol = '';
        var color = '';

        if(s == 'H') { symbol = '♥'; color = 'red'; }
        else if(s == 'D') { symbol = '♦'; color = 'red'; }
        else if(s == 'C') { symbol = '♣'; }
        else if(s == 'S') { symbol = '♠'; }

        var txt = v;
        if(v == 1) txt = 'A';
        if(v == 11) txt = 'J';
        if(v == 12) txt = 'Q';
        if(v == 13) txt = 'K';

        var click = '';
        if(div_id == '#hand') {
            click = 'onclick="play_card(' + i + ')"';
        }

        html += '<div class="card ' + color + '" ' + click + '>' + txt + symbol + '</div>';
    }

    $(div_id).html(html);
}

function play_card(i) {
    var txt = $('#status-text').text();
    if(txt != "👉 ΣΕΙΡΑ ΣΟΥ!") {
        alert("Δεν είναι η σειρά σου");
        return;
    }

    $.ajax({
        url: "xeri.php/play",
        method: "POST",
        headers: { "X-Token": token },
        contentType: "application/json",
        data: JSON.stringify({ cardIndex: i }),
        success: function(data) {
            if(data.message) {
                alert(data.message);
            }
            update_game_status();
        },
        error: function(e) {
            alert("Error playing card");
        }
    });
}

function update_scores() {
    $.ajax({
        url: "xeri.php/players",
        method: "GET",
        success: function(data) {
            for(var i=0; i<data.length; i++) {
                var p = data[i];
                if(p.p_turn == p_turn) {
                    $('#my-details').text("Εγώ: " + p.username + " (" + p.score + ")");
                } else {
                    $('#opp-details').text("Αντίπαλος: " + p.username + " (" + p.score + ")");
                }
            }
        }
    });
}

function reset_game() {
    $.ajax({
        url: "xeri.php/board",
        method: "POST",
        success: function() {
            alert("Reset done");
            location.reload();
        },
        error: function() {
            alert("Error reset");
        }
    });
}