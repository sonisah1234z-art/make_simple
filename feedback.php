<?php
session_start();
include 'db.php';

if(!isset($_SESSION['patient_id'])){
    header("Location: login.php");
    exit();
}

$patient_id = $_SESSION['patient_id'];
$patient_name = $_SESSION['patient_name'];

if(isset($_POST['submit'])){
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // Some setups don't include a `patient_id` column. Insert patient_name instead.
    $stmt = $conn->prepare("INSERT INTO feedback (patient_name, rating, comments) VALUES (?, ?, ?)");
    if($stmt){
        $stmt->bind_param('sis', $patient_name, $rating, $comments);
        if($stmt->execute()){
            // Redirect after successful submission to avoid resubmits
            header('Location: view_feedback.php');
            exit();
        } else {
            error_log('Feedback insert failed: ' . $stmt->error);
            echo "<script>alert('Failed to submit feedback. Please try again.');</script>";
        }
        $stmt->close();
    } else {
        error_log('Prepare failed: ' . $conn->error);
        echo "<script>alert('Unable to process feedback.');</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Feedback</title>

<style>
body{
    font-family:Arial;
    background:linear-gradient(135deg,#e3f2fd,#fce4ec);
    padding:20px;
}

.container{
    width:450px;
    margin:auto;
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 10px 30px rgba(0,0,0,0.1);
    text-align:center;
}

h2{
    color:#333;
}

input, select, textarea{
    width:100%;
    padding:10px;
    margin:10px 0;
    border:1px solid #ccc;
    border-radius:8px;
}

button{
    width:100%;
    padding:12px;
    background:#2c7be5;
    color:white;
    border:none;
    border-radius:8px;
    font-weight:bold;
    cursor:pointer;
}

button:hover{
    background:#1a68d1;
}
</style>
</head>

<body>

<div class="container">

<h2><img src="assets/icons/star.svg" class="icon">Give Your Feedback</h2>

<p>Welcome, <b><?php echo htmlspecialchars($patient_name); ?></b></p>

<form method="POST">

    <label>Rating</label>
    <select name="rating" required>
        <option value="">Select</option>
        <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
        <option value="4">⭐⭐⭐⭐ Good</option>
        <option value="3">⭐⭐⭐ Average</option>
        <option value="2">⭐⭐ Poor</option>
        <option value="1">⭐ Very Bad</option>
    </select>

    <textarea name="comments" placeholder="Write your feedback..." required></textarea>

    <button type="submit" name="submit">Submit Feedback</button>

</form>

</div>

</body>
</html>