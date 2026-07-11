<?php
session_start();
include 'db.php';

if(!isset($_SESSION['patient_id']))
{
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

$message = "";

if(isset($_POST['check']))
{
    $age = $_POST['age'];
    $bp = $_POST['blood_pressure'];
    $sugar = $_POST['sugar_level'];
    $bmi = $_POST['bmi'];

    $score = 0;

    if($age > 60)
        $score += 2;
    elseif($age > 40)
        $score += 1;

    if($bp >= 140)
        $score += 2;
    elseif($bp >= 120)
        $score += 1;

    if($sugar >= 200)
        $score += 2;
    elseif($sugar >= 140)
        $score += 1;

    if($bmi >= 30)
        $score += 2;
    elseif($bmi >= 25)
        $score += 1;

    if($score <= 2)
    {
        $risk = "Low";
        $color = "#22c55e";
        $icon = "🟢";
    }
    elseif($score <=5)
    {
        $risk = "Medium";
        $color = "#f59e0b";
        $icon = "🟡";
    }
    else
    {
        $risk = "High";
        $color = "#ef4444";
        $icon = "🔴";
    }

    $stmt = $conn->prepare("INSERT INTO health_risk(patient_id,patient_name,age,blood_pressure,sugar_level,bmi,risk_level)
    VALUES(?,?,?,?,?,?,?)");

    $stmt->bind_param("isiiids",$patient_id,$patient_name,$age,$bp,$sugar,$bmi,$risk);
    $stmt->execute();

    $message = "$icon Your Health Risk Level is <b>$risk</b>";
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Health Risk Prediction</title>

<style>

body{
font-family:Arial;
background:#eef4ff;
}

.container{
width:500px;
margin:40px auto;
background:white;
padding:25px;
border-radius:10px;
box-shadow:0 0 10px gray;
}

h2{
text-align:center;
color:#2563eb;
}

input{
width:100%;
padding:10px;
margin:10px 0;
border:1px solid #ccc;
border-radius:5px;
}

button{
width:100%;
padding:12px;
background:#2563eb;
color:white;
border:none;
border-radius:5px;
font-size:16px;
cursor:pointer;
}

button:hover{
background:#1d4ed8;
}

.result{
margin-top:20px;
padding:15px;
text-align:center;
font-size:20px;
font-weight:bold;
border-radius:8px;
background:#f1f5f9;
}

</style>

</head>

<body>

<div class="container">

<h2>❤️ Health Risk Prediction</h2>

<form method="POST">

<input type="number" name="age" placeholder="Age" required>

<input type="number" name="blood_pressure" placeholder="Blood Pressure (mmHg)" required>

<input type="number" name="sugar_level" placeholder="Sugar Level (mg/dL)" required>

<input type="number" step="0.1" name="bmi" placeholder="BMI" required>

<button type="submit" name="check">
Check Health Risk
</button>

</form>

<?php

if($message!="")
{
echo "<div class='result'>$message</div>";
}

?>

</div>

</body>
</html>