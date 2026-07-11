<?php
require_once 'auth.php';
require_nurse();
require_once 'db.php';

$id=(int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM nurse_records WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

$row=mysqli_fetch_assoc($result);
$stmt->close();

if(isset($_POST['update']))
{
    $vitals=$_POST['vitals'];
    $note=$_POST['note'];
    $attendance=$_POST['attendance'];

    $stmt = $conn->prepare("UPDATE nurse_records SET vitals = ?, note = ?, attendance = ? WHERE id = ?");
    $stmt->bind_param('sssi', $vitals, $note, $attendance, $id);
    $stmt->execute();
    $stmt->close();

    header("Location:view_nurses.php");
    exit();
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
