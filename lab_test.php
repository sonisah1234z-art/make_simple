<?php
require_once 'db.php';

/* GET PATIENTS */
$patients = $conn->query("SELECT id, name FROM patients");

/* CREATE UPLOAD FOLDER IF NOT EXISTS */
$folder = "uploads/";
if(!is_dir($folder)){
    mkdir($folder, 0777, true);
}

if(isset($_POST['save']))
{
    $patient_id = $_POST['patient_id'];
    $test_type  = $_POST['test_type'];
    $result     = $_POST['result'];

    /* IMAGE UPLOAD */
    $image = $_FILES['image']['name'];
    $tmp   = $_FILES['image']['tmp_name'];

    if(!empty($image)){
        move_uploaded_file($tmp, $folder.$image);
    }

    $sql = "INSERT INTO lab_tests(patient_id,test_type,result,image)
            VALUES('$patient_id','$test_type','$result','$image')";

    if($conn->query($sql)){
        echo "<script>alert('Lab Test Saved Successfully');</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Lab Test</title>

<style>
body{
    font-family:Arial;
    background:#e3f2fd;
}

.container{
    width:500px;
    margin:40px auto;
    background:white;
    padding:20px;
    border-radius:10px;
    box-shadow:0 0 10px gray;
}

input,select,textarea{
    width:100%;
    padding:10px;
    margin:10px 0;
}

button{
    width:100%;
    padding:10px;
    background:green;
    color:white;
    border:none;
}
</style>
</head>

<body>

<div class="container">

<h2>🧪 Lab Test Entry</h2>

<form method="post" enctype="multipart/form-data">

<select name="patient_id" required>
<option value="">Select Patient</option>

<?php
while($p = $patients->fetch_assoc())
{
    echo "<option value='{$p['id']}'>{$p['name']}</option>";
}
?>

</select>

<select name="test_type">
<option>Blood Test</option>
<option>X-Ray</option>
<option>MRI</option>
<option>CT Scan</option>
</select>

<textarea name="result" placeholder="Enter result" required></textarea>

<input type="file" name="image" accept="image/*">

<button type="submit" name="save">Save</button>

</form>

</div>

</body>
</html>