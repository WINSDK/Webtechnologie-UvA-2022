<?php
include_once "include/common.php";
include_once "include/db.php";

session_start();

if ($_POST["action"] == "remove" && isset($_POST["id"])) {
    unset($_SESSION["cart"][$_POST["id"]]);
    header("Location: " . $_SERVER["REQUEST_URI"], true, 303);
}

$keys_string = implode(',', array_keys($_SESSION["cart"]));

$sql = "SELECT * FROM products WHERE id IN ($keys_string)";
$products = query_execute($db, $sql);
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

<div class="box box-row">
<h1>Cart</h1>
<table class="box">
    <tr>
        <th>Product</th>
        <th>Price</th>
        <th>Amount</th>
        <th width="30px"></th>
    </tr>
    <?php foreach($products as $product): ?>
    <tr>
        <td><?= $product["name"] ?></td>
        <td>€<?= $product["price"] ?></td>
        <td><?= $_SESSION["cart"][$product["id"]] ?></td>
        <td>
            <form method="post" action="" class="form remove-form">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="id" value="<?= $product["id"] ?>">
                <input type="submit" value="&#x2716;">
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<form class="form">
    <input type="submit" value="Purchase">
</form>
</div>

<?php include_once "footer.php"; ?>

</body>

</html>
