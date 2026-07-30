<?php

include "../../config/database.php";

$id=
$_GET["id"];

$row=
$conn
->query(
"SELECT * FROM users WHERE id=$id"
)
->fetch_assoc();

if($_POST){

$conn->query(
"
UPDATE users

SET

fullname='".$_POST['fullname']."',

specialization='".$_POST['specialization']."',

phone='".$_POST['phone']."',

email='".$_POST['email']."',

availability='".$_POST['availability']."'

WHERE id=$id
"
);

header(
"Location:index.php"
);

}

?>

<form method="POST">

<input
name="fullname"
value="<?= $row['fullname']?>">

<input
name="specialization"
value="<?= $row['specialization']?>">

<input
name="phone"
value="<?= $row['phone']?>">

<input
name="email"
value="<?= $row['email']?>">

<button>

Update

</button>

</form>