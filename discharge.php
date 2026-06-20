<?php
require_once 'db.php';

/* Create table if not exists */
$conn->query("
CREATE TABLE IF NOT EXISTS discharge (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    summary TEXT,
    discharged_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
");

/* Save discharge */
if(isset($_POST['discharge']))
{
    $patient_id = $_POST['patient_id'];
    $summary = $_POST['summary'];

    $sql = "INSERT INTO discharge(patient_id, summary)
            VALUES('$patient_id','$summary')";

    if($conn->query($sql))
    {
        header("Location: view_discharge.php");
        exit();
    }
    else
    {
        echo "Error: " . $conn->error;
    }
}

$patients = $conn->query("SELECT id, name FROM patients");
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Discharge</title>

<style>
body{
    font-family:Arial;
    background:linear-gradient(to right,#4facfe,#00f2fe);
}

.container{
    width:700px;
    margin:40px auto;
    background:white;
    padding:30px;
    border-radius:15px;
    box-shadow:0 0 15px gray;
}

h2{
    text-align:center;
    color:#e74c3c;
}

select,textarea{
    width:100%;
    padding:12px;
    margin-top:10px;
    margin-bottom:15px;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#e74c3c;
    color:white;
    border:none;
    border-radius:8px;
    font-size:16px;
    cursor:pointer;
}

button:hover{
    background:#c0392b;
}
</style>

</head>
<body>

<div class="container">

<h2><img src="assets/icons/hospital.svg" class="icon">Patient Discharge System</h2>

<form method="post">

<label>Select Patient</label>

<select name="patient_id" required>
<option value="">Select Patient</option>

<?php
while($row = $patients->fetch_assoc())
{
    echo "<option value='{$row['id']}'>{$row['name']}</option>";
}
?>

</select>

<label>Discharge Summary</label>

<textarea name="summary" rows="5" required>
Patient was admitted for treatment and monitored regularly. Condition improved during hospitalization. Vital signs are stable and the patient is fit for discharge.
</textarea>

<button type="submit" name="discharge">
    <img src="assets/icons/check.svg" class="icon">Discharge Patient
</button>

</form>

</div>

</body>
</html>