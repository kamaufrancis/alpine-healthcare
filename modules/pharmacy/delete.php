<?php

include "../../config/database.php";

$id=
$_GET["id"];

$conn
->query(
"DELETE FROM medicines WHERE id=$id"
);

header(
"Location:index.php"
);

?>