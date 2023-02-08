<?php

include_once "include/common.php";
include_once "include/db.php";

session_start();

// Ensure user is logged in
if (!isset($_SESSION["id"])) {
    header("Location: /");
    exit;
}

$cart_empty = (!isset($_SESSION["cart"]) || count($_SESSION["cart"]) === 0);

$cart_empty = (!isset($_SESSION["cart"]) || count($_SESSION["cart"]) === 0);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION["id"])) {
        // Ignore post if not logged in
        exit();
    }

    if ($_POST["action"] == "remove" && isset($_POST["id"])) {
        unset($_SESSION["cart"][$_POST["id"]]);
        header("Location: " . $_SERVER["REQUEST_URI"], true, 303);
        exit;
    } elseif ($_POST["action"] == "change-amount" && isset($_POST["id"])) {
        if (!is_numeric($_POST["amount"])) {
            reload_err("Amount is not numeric");
        }

        if ($_POST["amount"] <= 0) {
            unset($_SESSION["cart"][$_POST["id"]]);
        } elseif ($_POST["amount"] > 50) {
            reload_err("Purchasing more than 50 of one card is not possible");
        } else {
            $_SESSION["cart"][$_POST["id"]] = $_POST["amount"];
        }
        header("Location: " . $_SERVER["REQUEST_URI"], true, 303);
        exit;
    } elseif ($_POST["action"] == "checkout") {
        if (!$cart_empty) {
            header("Location: /checkout");
        } else {
            header("Location: " . $_SERVER["REQUEST_URI"], true, 303);
        }

        exit;
    }

    http_response_code(500);
    exit;
}

$total = 0;
$products = [];
if (!$cart_empty) {
    $raw_keys = array_keys($_SESSION["cart"]);
    $keys = str_replace("f", "", $raw_keys);
    $keys_string = implode(',', $keys);

    $sql = "SELECT * FROM cards WHERE id IN ($keys_string)";
    $cards = query_execute_unsafe($db, $sql);

    foreach ($cards as $card) {
        if (in_array($card["id"], $raw_keys)) {
            $amount = $_SESSION["cart"][$card["id"]];
            $price = $card["normal_price"];
            $name = $card["name"];
            array_push($products, [
                "amount" => $amount,
                "price" => $price,
                "name" => $name,
                "id" => $card["id"]
            ]);
            $total += $price * $amount;
        }
        if (in_array($card["id"] . "f", $raw_keys)) {
            $amount = $_SESSION["cart"][$card["id"] . "f"];
            $price = $card["foil_price"];
            $name = $card["name"] . " (foil)";
            array_push($products, [
                "amount" => $amount,
                "price" => $price,
                "name" => $name,
                "id" => $card["id"]
            ]);
            $total += $price * $amount;
        }
    }
}

$_SESSION["cart_total"] = $total;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MTG | Cart</title>

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
	<link rel="stylesheet" type="text/css" href="/css/form.css">
	<link rel="stylesheet" type="text/css" href="/css/shop.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.1/jquery.min.js"></script> 
</head>

<body>
<?php include_once "header.php"; ?>
<?php include_once "include/errors.php"; ?>

<div class="box">
    <div class="box-row box-light">
        <h1>Cart</h1>
    </div>
    <div class="box-row box-container">
        <div id="cart-list">
            <table class="box">
                <tr>
                    <th>Product</th>
                    <th>Price</th>
                    <th>Amount</th>
                    <th>Total Price</th>
                    <th style="width: 30px;"></th>
                </tr>
<?php foreach($products as $product): ?>
                <tr>
                    <td class="col-text">
                        <a href="/product?id=<?= $product["id"] ?>">
                            <?= $product["name"] ?>
                        </a>
                    </td>
                    <td class="col-num"><?= format_eur($product["price"]) ?></td>
                    <td class="col-center">
                        <form method="post" class="form remove-form">
                            <input type="hidden" name="action" value="change-amount">
                            <input type="number" name="amount" max="50" value="<?= $product["amount"] ?>" aria-label="amount">
                            <?php if (str_contains($product["name"], "(foil)")): ?>
                            <input type="hidden" name="id" value="<?= $product["id"] . "f" ?>">
                            <?php else: ?>
                            <input type="hidden" name="id" value="<?= $product["id"] ?>">
                            <?php endif; ?>
                            <input type="submit" value="Update">
                        </form>
                    </td>
                    <td class="col-num">
                        <?= format_eur($product["amount"] * $product["price"]) ?>
                    </td>
                    <td>
                        <form method="post" class="form remove-form">
                            <input type="hidden" name="action" value="remove">
<?php if (str_contains($product["name"], "(foil)")): ?>
                            <input type="hidden" name="id" value="<?= $product["id"] . "f" ?>">
<?php else: ?>
                            <input type="hidden" name="id" value="<?= $product["id"] ?>">
<?php endif; ?>
                            <input type="submit" value="&#x2716;">
                        </form>
                    </td>
                </tr>
<?php endforeach; ?>
<?php if ($cart_empty): ?>
                <tr>
                    <td colspan="5" style="text-align: center; font-size: 1rem; padding: 1rem;">
                        Your cart is empty, consider going to the <a href="/shop">shop</a> to add items.
                    </td>
                </tr>
<?php endif; ?>
            </table>
        </div>

        <div id="cart-details" class="box box-row ">
            <h2>
            Total: <?= format_eur($total) ?>
            </h2>

<?php if (!$cart_empty): ?>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="checkout">
                <input type="submit" value="Checkout">
            </form>
<?php endif; ?>
        </div>
    </div>
</div>

<?php include_once "footer.php"; ?>

</body>

</html>
