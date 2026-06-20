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

if(isset($_POST['alert']))
{
    $message = "Emergency Help Needed";

    $sql = "INSERT INTO emergency_alerts
            (patient_id, patient_name, message, status)
            VALUES
            ('$patient_id','$patient_name','$message','Pending')";

    if(mysqli_query($conn, $sql))
    {
        echo "<script>alert('Emergency Alert Sent Successfully!');</script>";
    }
    else
    {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Emergency Alert</title>

    <style>
    body{
        font-family:Arial, sans-serif;
        background:#ffe5e5;
        display:flex;
        justify-content:center;
        align-items:center;
        height:100vh;
    }

    .container{
        width:500px;
        background:white;
        padding:30px;
        border-radius:15px;
        text-align:center;
        box-shadow:0 0 15px rgba(0,0,0,0.2);
    }

    h1{
        color:red;
        margin-bottom:15px;
    }

    p{
        color:#555;
        margin-bottom:20px;
    }

    button{
        background:red;
        color:white;
        border:none;
        padding:18px 35px;
        font-size:20px;
        border-radius:10px;
        cursor:pointer;
    }

    button:hover{
        background:darkred;
    }

    .back{
        display:inline-block;
        margin-top:20px;
        text-decoration:none;
        background:#007bff;
        color:white;
        padding:10px 20px;
        border-radius:5px;
    }

    .back:hover{
        background:#0056b3;
    }
    </style>

</head>
<body>

<div class="container">

    <h1><img src="assets/icons/emergency.svg" class="icon">Emergency Alert</h1>

    <p>
        Patient: <strong><?php echo $patient_name; ?></strong>
    </p>

    <form method="POST">
        <button type="submit" name="alert">
            SEND EMERGENCY ALERT
        </button>
    </form>

    <a href="patient_dashboard.php" class="back">
        &larr; Back to Dashboard
    </a>

</div>

</body>
</html>