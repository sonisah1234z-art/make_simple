<?php

$conn=mysqli_connect("localhost","root","","hospital_db");

$id=$_GET['id'];

mysqli_query($conn,
"DELETE FROM nurse_records
WHERE id='$id'");

header("Location:view_nurse.php");

?>