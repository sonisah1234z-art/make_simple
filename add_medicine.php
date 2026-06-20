<?php
include "db.php";

$message = "";

if(isset($_POST['add'])){

    $name = $_POST['medicine_name'];
    $category = $_POST['category'];
    $stock = $_POST['stock'];
    $expiry = $_POST['expiry'];

    $sql = "INSERT INTO medicines
    (medicine_name, category, stock, expiry_date)

    VALUES
    ('$name','$category','$stock','$expiry')";

    if(mysqli_query($conn,$sql)){
        $message = "Medicine Added Successfully";
    } else {
        $message = "Failed to Add Medicine";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Medicine</title>

<style>

body{
    font-family:Arial;
    background:#f4f6f9;
    padding:40px;
}

.box{
    width:400px;
    background:white;
    padding:30px;
    margin:auto;
    border-radius:10px;
}

input{
    width:100%;
    padding:12px;
    margin-top:10px;
}

button{
    width:100%;
    padding:12px;
    margin-top:15px;
    background:#27ae60;
    color:white;
    border:none;
}

.msg{
    color:green;
    margin-bottom:15px;
}

</style>

</head>

<body>

<div class="box">

<h2>Add Medicine</h2>

<?php
if($message != ""){
    echo "<p class='msg'>$message</p>";
}
?>

<form method="POST">

<input type="text"
name="medicine_name"
placeholder="Medicine Name"
required>

<input type="text"
name="category"
placeholder="Category"
required>

<input type="number"
name="stock"
placeholder="Stock"
required>

<input type="date"
name="expiry"
required>

<button type="submit" name="add">
Add Medicine
</button>

</form>

</div>

</body>
</html>