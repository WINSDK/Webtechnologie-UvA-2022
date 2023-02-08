<?php

include_once "include/common.php";
include_once "include/db.php";

session_start();

$sql = "SELECT * FROM cards WHERE id=?";
$card = query_execute($db, $sql, "i", $_GET["id"])[0];

// Make sure the right part of the type line is used.
if (str_contains($card["type_line"], "Planeswalker")) {
    $card_type = "planeswalker";
}
else if (!strrchr($card["type_line"], "—")) {
    $card_type = $card["type_line"];
}
else {
    $card_type = strrchr($card["type_line"], "—");
    $card_type = ltrim($card_type, '— ');
}

$card_type = mysqli_real_escape_string($db, $card_type);

if (!strrchr($card_type, " ")) {
    $card_half_type = $card_type;
}
else {
    $card_half_type = strrchr($card_type, " ");
    $card_half_type = ltrim($card_half_type, ' ');
}

$card_half_type = mysqli_real_escape_string($db, $card_half_type);
$card_name = mysqli_real_escape_string($db, $card["name"]);

// Suggested cards are determi  ned from multiple keywords.
// If 7 cards arent found, a broader search is used.
$base_sql = "SELECT * FROM cards
        WHERE real_card='1' AND NOT layout='emblem'
        AND NOT layout='art_series' AND NOT layout='token'
        AND NOT layout='planar' AND NOT type_line LIKE '%card%'
        AND NOT name='$card_name'";

$type_line = mysqli_real_escape_string($db, $card["type_line"]);
$color_identity = mysqli_real_escape_string($db, $card["color_identity"]);

// Search for the exact type line, color identity and cmc.
$suggest_sql = $base_sql;
$suggest_sql .= "AND type_line LIKE '%$type_line%'
                 AND color_identity='$color_identity'
                 AND cmc='{$card["cmc"]}'
                 ORDER BY id LIMIT 7";

$suggested_cards = query_execute_unsafe($db, $suggest_sql);

// Search for partial type line and exact color identity if not enough cards are
// found.
if (count($suggested_cards) < 7) {
    $suggest_sql = $base_sql;
    $suggest_sql .= "AND type_line LIKE '%$card_type%'
                     AND color_identity='$color_identity'
                     ORDER BY id LIMIT 7";
    $suggested_cards = query_execute_unsafe($db, $suggest_sql);
}

// Search for single type and partial color identity if not enough cards are found.
if (count($suggested_cards) < 7) {
    $suggest_sql = $base_sql;
    $suggest_sql .= "AND type_line LIKE '%$card_half_type%'
                     AND color_identity LIKE '%$color_identity%'
                     ORDER BY id LIMIT 7";
    $suggested_cards = query_execute_unsafe($db, $suggest_sql);
}

// Search for single type and color identity if not enough cards are found.
if (count($suggested_cards) < 3) {
    $suggest_sql = $base_sql;
    $suggest_sql .= "AND color_identity='$color_identity'
                     ORDER BY id LIMIT 7";
    $suggested_cards = query_execute_unsafe($db, $suggest_sql);
}

// Redirect to shop if page is reached without id
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if(!isset($_GET["id"])) {
        header("Location: /shop");
        exit;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION["id"])) {
    if (!isset($_SESSION["cart"])) {
        $_SESSION["cart"] = array();
    }

    $amount = $_POST["amount"];
    $foil = $_POST["foil"];
    $card_id = $_POST["id"];
    $card_id .= $foil ? "f" : "";

    // Check if foil/non-foil version of this card exists
    if ($card["normal_price"] == 0 && !$foil) {
        reload_err("Non-foil version of this card is not for sale");
    } elseif ($card["foil_price"] == 0 && $foil) {
        reload_err("Foil version of this card is not for sale");
    }

    if ($_SESSION["cart"][$card_id] + $amount > 50) {
        reload_err("Purchasing more than 50 of one card is not possible");
    }

    if (!isset($_SESSION["cart"][$card_id])) {
        $_SESSION["cart"][$card_id] = 0;
    }

    if (isset($amount) && isset($card_id) && $amount > 0) {
        $_SESSION["cart"][$card_id] += $amount;
    }

    header("Location: " . $_SERVER["REQUEST_URI"], true, 303);
    exit;
}

$formats = array("standard", "pioneer", "modern", "legacy", "vintage", "pauper", "commander");

$card_front = $card["image"] ? $card["image"] : "/img/no_image_available.png";
$card_back  = $card["back_image"] ? $card["back_image"] : "/img/default_mtg_card.webp";
$card_price = $card["normal_price"];
$foil_price = $card["foil_price"];
$card_name = str_replace(" ", "_", $card["name"]);
$card_versions = array("name" => $card_name);
$card_versions = "/card_versions?" . http_build_query($card_versions);
$formats = array("standard", "pioneer", "modern", "legacy", "vintage", "pauper", "commander");

if ($card["normal_price"] == 0) {
    $card_price = "--";
}

if ($card["foil_price"] == 0) {
    $foil_price = "--";
}

$legal_cards = "";
$counter = 0;
foreach ($formats as $format) {
    if ($card["{$format}_legal"] == "legal") {
        if ($counter != 0) {
            $legal_cards .= " - ";
        }
        $legal_cards .= $format;
        $counter++;
    }
}

if ($card["normal_price"] == 0) {
    $card_price = "--";
}

if ($card["foil_price"] == 0) {
    $foil_price = "--";
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MTG | Shop</title>

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
	<link rel="stylesheet" type="text/css" href="/css/form.css">
	<link rel="stylesheet" type="text/css" href="/css/shop.css">
</head>

<body>
<?php include_once "header.php"; ?>

<?php include_once "include/errors.php"; ?>

<div class="box">
    <div class="box-row box-light">
        <h1><?= $card["name"] ?></h1>
    </div>
    <div class="box-row" style="margin-top: 1%">
        <div class="product-column">
            <div class="left-product-column">
                <div class="card-window">
                    <div class="floating-card">
                        <div class="card-face">
                            <img draggable="false" src="<?= $card_front ?>" alt="Card front"></img>
                        </div>
                        <div class="card-face">
                            <img draggable="false" src="<?= $card_back ?>" alt="Card back"></img>
                        </div>
                        <div class="card-face"></div>
                        <div class="card-face"></div>
                        <div class="card-face"></div>
                        <div class="card-face"></div>
                    </div>
                </div>
                <div class="version-button">
                    <a href="<?= $card_versions ?>">all variations</a>
                </div>
            </div>
            <div class="flex-break"></div>
            <div class="right-product-column">
                <div id="product-info">
                    <table class="info-table">
<?php if (isset($card["mana_cost"]) and $card["mana_cost"] != ""): ?>
                        <tr>
                            <th>Mana cost</th>
                            <th><?= $card["mana_cost"] ?></th>
                        </tr>
<?php endif ?>
                        <tr>
                            <th>Type line</th>
                            <th><?= $card["type_line"] ?></th>
                        </tr>
<?php if (isset($card["oracle_text"])): ?>
                        <tr>
                            <th>Oracle text</th>
                            <th><?= nl2br($card["oracle_text"]) ?></th>
                        </tr>
<?php endif ?>
<?php if (isset($card["flavor_text"])): ?>
                        <tr>
                            <th>Flavor text</th>
                            <th><?= nl2br($card["flavor_text"]) ?></th>
                        </tr>
<?php endif ?>
<?php if (isset($card["power"])): ?>
                        <tr>
                            <th>Power/Toughness</th>
                            <th><?= $card["power"] ?>/<?= $card["toughness"] ?></th>
                        </tr>
<?php endif ?>
<?php if (isset($card["loyalty"])): ?>
                        <tr>
                            <th>Loyalty</th>
                            <th><?= $card["loyalty"] ?></th>
                        </tr>
<?php endif ?>
                        <tr>
                            <th>Legal in</th>
                            <th><?= $legal_cards ?></th>
                        </tr>
                            <th>Set</th>
                            <th><?= $card["set_name"] ?></th>
                        </tr>
                        <tr>
                            <th>Artist</th>
                            <th><?= $card["artist"] ?></th>
                        </tr>
                    </table>
                </div>
                <form method="POST" action="/product?id=<?= $_GET["id"] ?>" class="form">
                    <fieldset>
                        <legend>
                            Add item(s) to cart
                        </legend>
                        <span>Normal price: <?= format_eur($card_price) ?></span>
                        <span>Foil price: <?= format_eur($foil_price) ?></span>
                        <br>
<?php if (isset($_SESSION["id"])): ?>
                        <label>
                            <b>Amount</b>
                            <input type="number" name="amount" value="1" min="1" max="50">
                        </label>
                        <label>
                            <b>Foil</b>
                            <input id="foil" type="checkbox" name="foil">
                        </label>
                        <input type="hidden" id="id" name="id" value="<?= $_GET["id"] ?>">
                        <input id="cart-submit" type="submit" value="Add to cart">
<?php else: ?>
                        Please <a href="/login">login</a> or <a href="/register">register</a> to add this item to your cart.
<?php endif; ?>
                    </fieldset>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="box">
    <div class="box-row box-light">
        <b>Similar cards</b>
    </div>
    <div class="box-row popular-cards">
<?php
foreach ($suggested_cards as $suggest_card):
    $card_front = $suggest_card["image"] ? $suggest_card["image"] : "/img/no_image_available.png";
    $card_back = $suggest_card["back_image"];
    $card_page = "/product?id=" . $suggest_card["id"];
?>
<?php if (isset($card_back)): ?>
        <div class="box-card-small">
            <div class="box-card-flip">
                <div class="box-card-front">
                    <a href="<?= $card_page ?>">
                        <img src="<?= $card_front ?>" alt="<?= $suggest_card["name"] ?>">
                    </a>
                </div>
                <div class="box-card-back">
                    <a href="<?= $card_page ?>">
                        <img src="<?= $card_back ?>" alt="<?= $suggest_card["name"] ?>">
                    </a>
                </div>
            </div>
        </div>
<?php else: ?>
        <div class="box-card-small">
            <a href="<?= $card_page ?>">
                <img src="<?= $card_front ?>" alt="<?= $suggest_card["name"] ?>">
            </a>
        </div>
<?php endif; ?>
<?php endforeach ?>
<?php if (!$suggested_cards): ?>
        <div class="box-card-small">
            <img src="/img/no_cards_found.png" alt="no cards found">
        </div>
<?php endif; ?>
    </div>
</div>

<?php include_once "footer.php"; ?>

<script src="/js/card_display.js"></script>

</body>

</html>
