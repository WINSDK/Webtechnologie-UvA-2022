<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MTG | Deck building</title>

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
</head>

<body>

<?php include_once "header.php";?>

<?php if (isset($_SESSION["id"])): ?>
<div class="box">
    <div class="box-row box-light">
        <b>Chatbox</b>
    </div>
    <div class="box-row" style="height: 10rem;">
        <div class="chatbox-msgs" id="chatbox"></div>
    </div>
    <div class="box-row" style="padding-top: 0;">
        <div class="chatbox">
            <input id="chatbox-message" type="text" name="msg" maxlength="200">
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_SESSION["id"])): ?>
<?php
    $sql = "SELECT * FROM cards
            WHERE NOT layout='art_series' AND NOT layout='token'
            ORDER BY RAND() LIMIT 7";

    $cards = query_execute($db, $sql);
?>
<div class="box">
    <div class="box-row box-light">
        <b>Most popular cards this week</b>
    </div>
    <div class="box-row popular-cards">
        <?php foreach ($cards as $card):
            $card_front = $card["image"];
            $card_back = $card["back_image"];
            $card_page = "/product.php?id=" . $card["id"];

            if (!$card_front) {
                $card_front = "https://mtgcardsmith.com/view/cards_ip/1674397095190494.png?t=014335";
            }
        ?>
        <?php if (isset($card_back)): ?>
        <div class="box-card">
            <div class="box-card-flip">
                <div class="box-card-front">
                    <a href="<?= $card_page ?>">
                        <img src="<?= $card_front ?>" alt="<?= $card["name"] ?>">
                    </a>
                </div>
                <div class="box-card-back">
                    <a href="<?= $card_page ?>">
                        <img src="<?= $card_back ?>" alt="<?= $card["name"] ?>">
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="box-card">
            <a href="<?= $card_page ?>">
                <img src="<?= $card_front ?>" alt="<?= $card["name"] ?>">
            </a>
        </div>
        <?php endif; ?>
        <?php endforeach ?>
    </div>
</div>

<?php endif; ?>

<?php if (isset($_SESSION["id"])): ?>
<?php
    require_once "include/db.php";

    $query = mysqli_query($db, "SELECT * FROM users LIMIT 1000");
    $tags = array();
    $now = time();
    $now = new DateTime("@$now");

    while ($row = mysqli_fetch_array($query)) {
        $tag = 'href="/profile.php?id=' . $row["id"] . '">' . $row["uname"] . '</a>';

        // TODO: different display for admins
        if (isset($row["role"]) && $row["role"] == "admin") {
            $tag = '<a id="admin-user" ' . $tag;
        } else {
            $tag = '<a id="default-user" ' . $tag;
        }

        $last_activity = $row["last_activity"];
        $last_activity = new DateTime("$last_activity");

        $dt = $now->diff($last_activity);
        $mins_logged_in = $dt->days * 24 * 60;
        $mins_logged_in += $dt->h * 60;
        $mins_logged_in += $dt->i;

        // fake users online xdd
        if ($mins_logged_in < 10 || $row["uname"] == "admin" || $row["uname"] == "nicolas") {
            array_push($tags, $tag);
        }
    }

    $seperated_tags = implode(", ", $tags);
?>
<?php if (count($tags) > 1): ?>
<div class="box">
    <div class="box-row box-light">
        <b>Users online</b>
    </div>
    <div class="box-row users-online">
        <?php echo $seperated_tags; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if (isset($_SESSION["id"])): ?>
<script>
let message_box = document.getElementById("chatbox-message");
let chatbox = document.getElementById("chatbox");

// enable message listener that checks new messages every 0.5 seconds
let message_requester = window.setInterval(request_messages, 500);

// set of messages that have been send but not yet processed by `request_messages`
let handled_messages = new Set();

// reset queue of new messages and request message log
(async function() {
    await checked_fetch("/broadcast_message.php?action=reset");
    await request_messages();
})();

message_box.addEventListener("keydown", async function(keypress) {
    if (keypress.code == "Enter" && message_box.value != "") {
        // send message to server
        let id = await checked_fetch("/broadcast_message.php?action=send", {
            method: "POST",
            body: message_box.value,
            headers: { "Content-Type": "text/plain; charset=UTF-8" }
        });

        // mark message as handled
        handled_messages.add(id);

        // get local user data
        let username = "<?php echo $_SESSION["uname"]; ?>";
        let user_type = "<?php echo $_SESSION["role"]; ?>-user";

        // generate message html layout
        let message = `\n\t\t`
                    + `<span class="message">`
                    + `<b class="message-content" id="${user_type}">${username}</b>`
                    + `<div class="message-content">: ${message_box.value}</div>`
                    + `</span>`;

        // add message to chatbox
        chatbox.innerHTML += message;

        // scroll chatbox down
        chatbox.scrollTop = chatbox.scrollHeight;

        // clear message box
        message_box.value = "";
    }
});

async function request_messages() {
    let messages = await checked_fetch_json("/broadcast_message.php?action=receive");

    messages.forEach(message => {
        // remove handled messages as they've now been acknowledged
        if (handled_messages.has(message.id)) {
            handled_messages.delete(message.id);
            return;
        }

        // append message to chatbox
        chatbox.innerHTML += message.body;

        // scroll chatbox down
        chatbox.scrollTop = chatbox.scrollHeight;
    });
}

async function checked_fetch(resource, options = {}) {
    var failed = false;
    let request = await fetch(resource, options)
        .then(v => v, _ => { failed = true });

    if (failed || request.status == 500) {
        return "";
    }

    if (request.status == 200) {
        return await request.text();
    }

    window.location.replace("/index.php");
}

async function checked_fetch_json(resource, options = {}) {
    var failed = false;
    let request = await fetch(resource, options)
        .then(v => v, _ => { failed = true });

    if (failed || request.status == 500) {
        return [];
    }

    if (request.status == 200) {
        return await request.json();
    }

    window.location.replace("/index.php");
}
</script>
<?php endif; ?>

<?php include_once "footer.php"; ?>

</body>

</html>
