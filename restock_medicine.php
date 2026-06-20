<?php
include "db.php";

if(isset($_POST['restock']))
{
    $id = $_POST['id'];
    $qty = $_POST['qty'];

    $conn->query("
        UPDATE medicines
        SET stock = stock + $qty
        WHERE id = $id
    ");

    echo "<script>alert('Medicine Restocked Successfully');</script>";
}

$medicines = $conn->query("SELECT id, medicine_name FROM medicines");
?>

<!DOCTYPE html>
<html>
<head>
<title>Restock Medicine</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

.container{
    width:400px;
    margin:50px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px gray;
}

input,select{
    width:100%;
    padding:10px;
    margin:10px 0;
}

button{
    width:100%;
    padding:10px;
    background:#27ae60;
    color:white;
    border:none;
}
</style>

</head>

<body>

<div class="container">

<h2><img src="assets/icons/capsule.svg" class="icon">Restock Medicine</h2>

<form method="POST">

<select name="id" required>
<option value="">Select Medicine</option>

<?php while($row = $medicines->fetch_assoc()) { ?>
<option value="<?php echo $row['id']; ?>">
    <?php echo $row['medicine_name']; ?>
</option>
<?php } ?>

</select>

<input type="number" name="qty" placeholder="Enter Quantity" required>

<button type="submit" name="restock">
Restock Now
</button>

</form>

</div>

</body>
</html>