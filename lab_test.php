<?php
require_once 'db.php';
ensure_lab_tests_table($conn);

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

    /* Attachment upload => store in `file` column */
    $uploadName = null;
    if (!empty($_FILES['file']['name'])) {
        $f = $_FILES['file'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','pdf'];
            if (in_array($ext, $allowed, true)) {
                $uploadName = bin2hex(random_bytes(8)) . '.' . $ext;
                move_uploaded_file($f['tmp_name'], $folder . $uploadName);
            }
        }
    }

    $sql = "INSERT INTO lab_tests(patient_id,test_type,result,file) VALUES('$patient_id','$test_type','$result','" . ($uploadName ? $conn->real_escape_string($uploadName) : '') . "')";

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
<link rel="stylesheet" href="assets/css/compact-ui.css">
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

<input type="file" name="file" accept="image/*,.pdf">

<button type="submit" name="save">Save</button>

</form>

</div>

</body>
</html>
