<?php
include "db.php";

$result = mysqli_query($conn,"SELECT * FROM nurses");
$total = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse List</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#f4f6f9;
}

/* Header */

.header{
    background:linear-gradient(135deg,#3498db,#2ecc71);
    color:white;
    text-align:center;
    padding:25px;
    font-size:30px;
    font-weight:bold;
    box-shadow:0 3px 10px rgba(0,0,0,0.2);
}

/* Container */

.container{
    width:95%;
    margin:30px auto;
}

/* Cards */

.cards{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:25px;
}

.card{
    background:white;
    padding:25px;
    border-radius:12px;
    text-align:center;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

.card h3{
    color:#2c3e50;
    margin-bottom:10px;
}

.card p{
    font-size:28px;
    color:#27ae60;
    font-weight:bold;
}

/* Table */

.table-box{
    background:white;
    padding:20px;
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}

table{
    width:100%;
    border-collapse:collapse;
}

th{
    background:#2c3e50;
    color:white;
    padding:15px;
}

td{
    padding:12px;
    text-align:center;
    border-bottom:1px solid #ddd;
}

tr:hover{
    background:#f1f1f1;
}

/* Buttons */

.btn{
    padding:8px 12px;
    border-radius:6px;
    text-decoration:none;
    color:white;
    font-size:14px;
}

.edit{
    background:#3498db;
}

.delete{
    background:#e74c3c;
}

.back{
    display:inline-block;
    margin-top:20px;
    background:#2c3e50;
    color:white;
    padding:12px 20px;
    text-decoration:none;
    border-radius:8px;
}

.back:hover{
    background:#34495e;
}

</style>
</head>

<body>

<div class="header">
👩‍⚕ Nurse Management System
</div>

<div class="container">

<div class="cards">

<div class="card">
<h3>Total Nurses</h3>
<p><?php echo $total; ?></p>
</div>

<div class="card">
<h3>Department Staff</h3>
<p>🏥</p>
</div>

<div class="card">
<h3>Hospital Nurses</h3>
<p>👩‍⚕</p>
</div>

</div>

<div class="table-box">

<h2 style="margin-bottom:15px;">📋 Nurse List</h2>

<table>

<tr>
<th>ID</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Department</th>
<th>Action</th>
</tr>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?php echo $row['id']; ?></td>

<td><?php echo $row['nurse_name']; ?></td>

<td><?php echo $row['email']; ?></td>

<td><?php echo $row['phone']; ?></td>

<td><?php echo $row['department']; ?></td>

<td>
<a class="btn edit"
href="edit_nurse.php?id=<?php echo $row['id']; ?>">
Edit
</a>

<a class="btn delete"
href="delete_nurse.php?id=<?php echo $row['id']; ?>"
onclick="return confirm('Delete this nurse?')">
Delete
</a>
</td>

</tr>

<?php } ?>

</table>

<a href="admin_dashboard.php" class="back">
⬅ Back to Dashboard
</a>

</div>

</div>

</body>
</html>