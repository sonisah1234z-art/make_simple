<?php
$conn=mysqli_connect("localhost","root","","hospital_db");

$id=$_GET['id'];

$result=mysqli_query($conn,
"SELECT * FROM nurse_records WHERE id='$id'");

$row=mysqli_fetch_assoc($result);

if(isset($_POST['update']))
{
    $vitals=$_POST['vitals'];
    $note=$_POST['note'];
    $attendance=$_POST['attendance'];

    mysqli_query($conn,"
    UPDATE nurse_records
    SET
    vitals='$vitals',
    note='$note',
    attendance='$attendance'
    WHERE id='$id'
    ");

    header("Location:view_nurse.php");
}
?>

<form method="post">

Vitals<br>
<input type="text"
name="vitals"
value="<?php echo $row['vitals']; ?>"><br><br>

Note<br>
<textarea name="note"><?php echo $row['note']; ?></textarea><br><br>

Attendance<br>
<select name="attendance">
<option>Present</option>
<option>Absent</option>
</select><br><br>

<button name="update">
Update
</button>

</form>