<?php

include_once "include/common.php";
include_once "include/db.php";

session_start();

$cards_per_page = 60;
$page_offset = 0;
$page = 1;

// if there is a page specified
if (isset($_GET["page"])) {
    // reload the page without a page specified if the page isn't a number
    if (!is_numeric($_GET["page"])) {
        header("Location: /shop");
    }

    $page = intval($_GET["page"]);

    // reload the page without a page specified if the page number is invalid
    if ($page < 1) {
        header("Location: /shop");
    }

    $page_offset = ($page - 1) * $cards_per_page;
}

$card_name = mysqli_real_escape_string($db, $_GET["name"]);
$card_name = str_replace("_", " ", $card_name);

$sql = "SELECT * FROM cards
        WHERE real_card='1'
        AND NOT layout='art_series'
        AND NOT layout='token'
        AND NOT layout='emblem'
        AND NOT layout='planar'
        AND NOT type_line LIKE '%card%'";

$sql_search = "AND name=\"{$card_name}\"";

if (isset($sql_search)) {
    $_SESSION["version_search"] = $sql_search;
}
else if (isset($_SESSION["search"])) {
    $sql_search = $_SESSION["version_search"];
}

$sql .= $sql_search;
$sql .= " LIMIT {$cards_per_page} OFFSET {$page_offset}";

$cards = query_execute_unsafe($db, $sql);

$sql_amount = "SELECT COUNT(1) FROM cards ";
$sql_amount .= "WHERE real_card='1'
                AND NOT layout='art_series'
                AND NOT layout='token'
                AND NOT layout='emblem'";
$sql_amount .= $sql_search;

$card_amount = mysqli_query($db, $sql_amount);
$card_amount = mysqli_fetch_array($card_amount)[0];
$last_page = intdiv(intval($card_amount), $cards_per_page) + 1;
if (str_replace("\\'", "'",$card_name)) {
    $card_name = str_replace("\\'", "'",$card_name);
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.2.1/css/all.min.css">
</head>

<body>

<?php include_once "header.php"; ?>

<div class="box box-row box-container">
<?php
foreach ($cards as $card):
    $card_front = $card["image"] ? $card["image"] : "/img/no_image_available.png";
    $card_back = $card["back_image"];
    $card_price = $card["normal_price"];
    $card_page = "/product?id=" . $card["id"];

    if ($card["normal_price"] == 0) {
        if ($card["foil_price"] == 0) {
            $card_price = "--";
        }
        else {
            $card_price = $card["foil_price"];
        }
    }
?>
    <div class="box box-item">
        <div class="box-row item-header">
            <div class="box-left item-name">
                <a href="/product?id=<?= $card["id"] ?>"><?= $card["name"] ?></a>
            </div>
            <div class="box-right item-price"><?= format_eur($card_price) ?></div>
        </div>

        <div class="box-row item-set"><?= $card["set_name"] ?></div>

        <div class="box-row">
<?php if (isset($card_back)): ?>
            <div class="box-card-small">
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
            <div class="box-card-small">
                <a href="<?= $card_page ?>">
                    <img src="<?= $card_front ?>" alt="<?= $card["name"] ?>">
                </a>
            </div>
<?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php if ($card_amount == 0): ?>
    <div class="center-img box-row box-card-small">
            <img src="/img/no_cards_found.png" alt="no cards found">
    </div>
<?php endif; ?>
</div>
<?php if ($card_amount == 0): ?>
            <div class="center-img box-row box-card-small">
        <img src="/img/no_cards_found.png" alt="no cards found">
        </div>
<?php endif; ?>
</div>

<?php if ($card_amount > $cards_per_page): ?>
<div class="pageinator">
<?php if ($page > 2): ?>
    <a class="first-page" href="/shop?page=1";>
        <i class="fa-solid fa-chevron-left"></i>
        <i class="fa-solid fa-chevron-left"></i>
    </a>
<?php endif; ?>
<?php if ($page > 1): ?>
    <a href="/shop?page=<?= $page - 1 ?>">
        <i class="fa-solid fa-chevron-left"></i>
    </a>
<?php endif; ?>
<?php
function window($page, $last_page) {
    if ($page < 4) {
        return range(1, 7);
    }

    if ($last_page - $page < 4) {
        return range($last_page - 6, $last_page);
    }

    return range($page - 3, $page + 3);
}

foreach (window($page, $last_page) as $page_ref) {
    $tag = '<a href="/shop?page=' . strval($page_ref). '"';

    $tag .= $page_ref == $page ? ' class="this-page-button">' : ">";
    $tag .= strval($page_ref);
    $tag .= "</a>";

    if (strval($page_ref) <= $last_page And strval($page_ref) > 0) {
        echo "\t$tag\n";
    }
}
?>
<?php if ($last_page != $page): ?>
    <a href="/shop?page=<?= $page + 1 ?>">
        <i class="fa-solid fa-chevron-right"></i>
    </a>
<?php endif; ?>
<?php if ($last_page - $page > 1): ?>
    <a class="last-page" href="/shop?page=<?= $last_page ?>">
        <i class="fa-solid fa-chevron-right"></i>
        <i class="fa-solid fa-chevron-right"></i>
    </a>
<?php endif; ?>
</div>
<?php endif; ?>

<?php include_once "footer.php"; ?>

</body>

</html>
