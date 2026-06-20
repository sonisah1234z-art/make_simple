<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = mysqli_connect("localhost","root","","hospital_db");

if(!$conn){
    die("Database connection failed: " . mysqli_connect_error());
}

$message = "";

if($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $patient_name = isset($_POST['patient_name']) ? trim($_POST['patient_name']) : '';
    $vitals = isset($_POST['vitals']) ? trim($_POST['vitals']) : '';
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';
    $attendance = isset($_POST['attendance']) ? trim($_POST['attendance']) : '';

    if($patient_name != "" && $vitals != "")
    {
        $sql = "INSERT INTO nurse_records
                (patient_name,vitals,note,attendance)
                VALUES
                ('$patient_name','$vitals','$note','$attendance')";

        if(mysqli_query($conn,$sql))
        {
            $message = "Record Saved Successfully";
        }
        else
        {
            $message = "Error: " . mysqli_error($conn);
        }
    }
    else
    {
        $message = "Please fill all required fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Add Nurse Record</title>

<style>

body{
    font-family:Arial,sans-serif;
    background:#f4f6f9;
    margin:0;
}

.header{
    background:#2c3e50;
    color:white;
    text-align:center;
    padding:20px;
    font-size:24px;
}

.container{
    width:500px;
    margin:30px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px rgba(0,0,0,0.1);
}

label{
    font-weight:bold;
}

input,textarea,select{
    width:100%;
    padding:10px;
    margin-top:5px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:5px;
}

button{
    width:100%;
    padding:12px;
    background:#27ae60;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;
}

button:hover{
    background:#219150;
}

.message{
    text-align:center;
    color:green;
    font-weight:bold;
    margin-bottom:15px;
}

.back{
    display:block;
    text-align:center;
    margin-top:15px;
}

.back a{
    text-decoration:none;
    color:#2c3e50;
    font-weight:bold;
}

</style>

</head>
<body>

<div class="header">
🏥 Add Nurse Record
</div>

<div class="container">

<?php
if($message != ""){
    echo "<div class='message'>$message</div>";
}
?>

<form method="POST">

<label>Patient Name</label>
<input type="text" name="patient_name" required>

<label>Vitals</label>
<input type="text" name="vitals" placeholder="BP, Temperature, Pulse" required>

<label>Note</label>
<textarea name="note" rows="4"></textarea>

<label>Attendance</label>
<select name="attendance">
    <option value="Present">Present</option>
    <option value="Absent">Absent</option>
</select>

<button type="submit">
Save Record
</button>

</form>

<div class="back">
    <a href="view_nurses.php">📋 View Nurse Records</a>
</div>

</div>

</body>
</html>