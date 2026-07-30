<?php

include "../../config/database.php";

if($_POST){

$name= $_POST["fullname"];

$spec= $_POST["specialization"];

$phone= $_POST["phone"];

$email= $_POST["email"];

$availability= $_POST["availability"];

$sql= "INSERT INTO users(fullname, specialization, phone, email, availability)

VALUES('$name', '$spec', '$phone', '$email', '$availability')";

$conn->query($sql);

header("Location:index.php");}

?>

<html>
<head>

<link rel="stylesheet" href="../../assets/css/style.css">

</head>

<body>

<div class="main">

<h1> Add Doctor </h1>

<form
method="POST"
    class="form-box">
        <input name="fullname" placeholder="Doctor Name" required>

<input
name=
"specialization"

placeholder=
"Specialization">

<input
name=
"phone"

placeholder=
"Phone">

<input
name=
"email"

placeholder=
"Email">

<select
name=
"availability">

<option>
Available
</option>

<option>
Busy
</option>

<option>
On Leave
</option>

</select>

<button>

Save

</button>

</form>

</div>

</body>

</html>