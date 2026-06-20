<?php
include "db.php";

/* DELETE MEDICINE */
if(isset($_GET['delete_id']))
{
    $id = $_GET['delete_id'];

    $conn->query("
        DELETE FROM medicines
        WHERE id = $id
    ");

    echo "<script>alert('Medicine Deleted Successfully');</script>";
}

/* FETCH ALL MEDICINES */
$result = $conn->query("SELECT * FROM medicines");
?>

<!DOCTYPE html>
<html>
<head>
<title>Delete Medicine</title>

<style>
body{
    font-family:Arial;
    background:#f4f6f9;
}

table{
    width:90%;
    margin:30px auto;
    border-collapse:collapse;
    background:white;
}

th{
    background:#2c3e50;
    color:white;
    padding:10px;
}

td{
    padding:10px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

a.delete{
    background:red;
    color:white;
    padding:5px 10px;
    text-decoration:none;
    border-radius:5px;
}
</style>

</head>

<body>

<h2 style="text-align:center;">🗑️ Delete Medicines</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Stock</th>
<th>Price</th>
<th>Action</th>
</tr>

<?php while($row = $result->fetch_assoc()) { ?>

<tr>
<td><?php echo $row['id']; ?></td>
<td><?php echo $row['medicine_name']; ?></td>
<td><?php echo $row['stock']; ?></td>
<td><?php echo $row['price']; ?></td>

<td>
<a class="delete"
href="delete_medicine.php?delete_id=<?php echo $row['id']; ?>"
onclick="return confirm('Are you sure you want to delete this medicine?');">
Delete
</a>
</td>

</tr>

<?php } ?>

</table>

</body>
</html>