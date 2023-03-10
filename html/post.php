<?php

session_start();

// Ensure you can't reach the post page if you're not logged in.
if (!isset($_SESSION["id"])) {
    header("Location: /");
    exit;
}

// Check if the specific page id exists.
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    $redirect_title="Incorrect post id";
    $redirect_msg="You entered an invalid post id.";
    include_once "include/redirect.php";

    // Wait two seconds before refreshing.
    header("Refresh: 2; url=/forum");
    exit;
}

include_once "include/common.php";
include_once "include/db.php";

$visiting_user = query_execute($db, "SELECT * FROM users WHERE id=?", "s", $_SESSION["id"])[0];
$is_admin = $visiting_user["role"] == "admin";

$thread_id = intval($_GET["id"]);
$thread = query_execute_unsafe($db, "SELECT * from forum_threads WHERE id=$thread_id");

if (count($thread) === 0) {
    $redirect_title="Post not found";
    $redirect_msg="You tried to visit a thread that doesn't exist.";
    include_once "include/redirect.php";

    // Wait two seconds before refreshing.
    header("Refresh: 2; url=/forum.php");
    exit;
}

$thread = $thread[0];
$user_id = $_SESSION["id"];

// Submit a new comment to the sql database.
if (isset($_POST["submit"])) {
    $text = htmlspecialchars(trim($_POST["text"]));

    // Verify if the message is the correct size.
    validate_predicates(
        ["Messages should be of at least 2 characters", strlen($text) >= 1],
        ["Messages should not exceed 4096 characters", strlen($text) < 4096]
    );

    // Add the comment into the sql database.
    $sql = "INSERT INTO forum_posts (thread_id, user_id, text) VALUES (?, ?, ?)";
    query_execute($db, $sql, "iis", $thread_id, $user_id, $text);

    // Add 1 to the comment count of the thread.
    $sql = "UPDATE forum_threads SET comments=comments + 1 WHERE id=?";
    query_execute($db, $sql, "i", $thread_id);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
} elseif (isset($_POST["remove-post"]) && $is_admin) {
    // Decrement comment count of the thread.
    $sql = "UPDATE forum_threads SET comments = comments - 1 WHERE id IN
            (SELECT thread_id FROM forum_posts WHERE id=?)";
    query_execute($db, $sql, "i", $_POST["id"]);

    // Remove comment from database.
    $sql = "DELETE FROM forum_posts WHERE id=?";
    query_execute($db, $sql, "i", $_POST["id"]);

    header("Location: " . $_SERVER["REQUEST_URI"]);
    exit;
} elseif (isset($_POST["remove-thread"]) && $is_admin) {
    // Delete thread
    $sql = "DELETE FROM forum_threads WHERE id=?";
    query_execute($db, $sql, "i", $_GET["id"]);

    // Delete posts from this thread
    $sql = "DELETE FROM forum_posts WHERE thread_id=?";
    query_execute($db, $sql, "i", $_GET["id"]);

    header("Location: /forum.php");
    exit;
}

$user = query_execute_unsafe($db, "SELECT * FROM users WHERE id='".$thread["user_id"]."'")[0];

// Set a maximum of 50 comments in a single thread.
$sql = "SELECT * FROM forum_posts WHERE thread_id=$thread_id ORDER BY date LIMIT 50";
$posts = query_execute_unsafe($db, $sql);
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MTG | Forum</title>

    <link rel="icon" type="image/x-icon" href="/img/favicon.ico">
	<link rel="stylesheet" type="text/css" href="/css/style.css">
    <link rel="stylesheet" type="text/css" href="/css/forum.css">
    <link rel="stylesheet" type="text/css" href="/css/form.css">
    <script>
// update's textarea size on text input
function grow_box(self) {
    if (self.scrollHeight > 84) {
        self.style.height = "99px";
        self.style.height = (self.scrollHeight + 4) + "px";
    }
}
    </script>
</head>

<body>
<?php include_once "header.php";?>

<div class="box box-row">
    <?php if($is_admin): ?>
    <form method="post" class="form" style="display: inline; float: right">
        <input type="submit" name="remove-thread" value="Remove thread">
    </form>
    <?php endif; ?>
    <div class="post-title"><?= $thread["title"] ?></div>
    <div class="bottom-text">
        <p><?= $user["uname"] ?> - <?= format_datetime($thread["date"]) ?></p>
    </div>
    <p class="post-content"><?= $thread["thread_content"] ?></p>
</div>

<?php foreach ($posts as $post):
    $post_user_id = $post["user_id"];
    $post_user = query_execute_unsafe($db, "SELECT * FROM users where id=$post_user_id")[0];

    // Get the profile picture of the user.
    $profile_pic = @file_get_contents("./img/user" . $post_user_id . ".raw");
    $profile_pic_type = @file_get_contents("./img/user" . $post_user_id . ".info");

    if (!$profile_pic) {
        $profile_pic = file_get_contents("./img/standard_pfp.raw");
        $profile_pic_type = "image/png";
    }
?>

<div class="box">
    <div class="comment-header box-row">
        <a href="/profile?id=<?= $post_user_id ?>" class="username"><?= $post_user["uname"] ?></a>
        <div class="comment-timestamp">
            <?= format_datetime($post["date"]) ?>
<?php if($is_admin): ?>
            <form method="post" class="form" style="display: inline;">
                <input type="hidden" name="id" value="<?= $post["id"] ?>">
                <input type="submit" name="remove-post" value="Remove comment">
            </form>
<?php endif; ?>
        </div>
    </div>
    <div class="box-row post">
        <div class="profile-pic">
            <a href="/profile?id=<?= $post_user_id ?>">
                <img src="<?= "data:$profile_pic_type;base64,$profile_pic" ?>" alt="Profile picture">
            </a>
        </div>
        <div class="box-row box-light comment-content"><?= $post["text"] ?></div>
    </div>
</div>
<?php endforeach ?>
<?php include_once "include/errors.php";?>

<div class="box box-row">
    <form class="form" action="/post?id=<?= $thread_id ?>" method="post">
        <textarea
            name="text"
            class="textarea-content"
            maxlength="10000"
            oninput="grow_box(this)"
            placeholder="Write a message!"
        ></textarea>
        <input type="submit" name="submit" value="Add comment">
    </form>
</div>

<?php include_once "footer.php"; ?>

</body>
<?php if (isset($_GET["error"])): ?>
<!-- Scroll down to bottom of page if we have an error. -->
<script>
    let body_height = document.scrollingElement.scrollHeight;
    document.scrollingElement.scrollTo({ top: body_height })
</script>
<?php endif; ?>

</html>
