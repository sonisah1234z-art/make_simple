<?php
$conn = new mysqli('localhost', 'root', '', 'hospital_system');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$sql = "INSERT IGNORE INTO doctors (name, specialty, email, phone) VALUES 
    ('Dr. Sara Khan', 'General Physician', 'sara.khan@hospital.com', '+1234567890'),
    ('Dr. Amir Shah', 'Cardiologist', 'amir.shah@hospital.com', '+1234567891'),
    ('Dr. Aisha Mahmood', 'Pediatrician', 'aisha.mahmood@hospital.com', '+1234567892'),
    ('Dr. Bilal Qureshi', 'Orthopedic Surgeon', 'bilal.qureshi@hospital.com', '+1234567893'),
    ('Dr. Nadia Farooq', 'Neurologist', 'nadia.farooq@hospital.com', '+1234567894'),
    ('Dr. Kamran Siddiqui', 'ENT Specialist', 'kamran.siddiqui@hospital.com', '+1234567895'),
    ('Dr. Sara Iqbal', 'Dermatologist', 'sara.iqbal@hospital.com', '+1234567896'),
    ('Dr. Hina Javed', 'Gynecologist', 'hina.javed@hospital.com', '+1234567897'),
    ('Dr. Faisal Khan', 'Oncologist', 'faisal.khan@hospital.com', '+1234567898'),
    ('Dr. Zoya Ahmed', 'Psychiatrist', 'zoya.ahmed@hospital.com', '+1234567899'),
    ('Dr. Omar Tariq', 'Urologist', 'omar.tariq@hospital.com', '+1234567800'),
    ('Dr. Mehwish Ali', 'Nephrologist', 'mehwish.ali@hospital.com', '+1234567801'),
    ('Dr. Salman Raza', 'Pulmonologist', 'salman.raza@hospital.com', '+1234567802'),
    ('Dr. Amina Haq', 'Gastroenterologist', 'amina.haq@hospital.com', '+1234567803'),
    ('Dr. Yasir Malik', 'Ophthalmologist', 'yasir.malik@hospital.com', '+1234567804'),
    ('Dr. Farah Khan', 'Endocrinologist', 'farah.khan@hospital.com', '+1234567805'),
    ('Dr. Aliya Shah', 'Rheumatologist', 'aliya.shah@hospital.com', '+1234567806'),
    ('Dr. Hamza Ijaz', 'Cardiologist', 'hamza.ijaz@hospital.com', '+1234567807'),
    ('Dr. Nida Saeed', 'Pediatric Surgeon', 'nida.saeed@hospital.com', '+1234567808'),
    ('Dr. Umar Ali', 'Radiologist', 'umar.ali@hospital.com', '+1234567809'),
    ('Dr. Sana Mir', 'Pathologist', 'sana.mir@hospital.com', '+1234567810'),
    ('Dr. Abbas Khan', 'General Surgeon', 'abbas.khan@hospital.com', '+1234567811'),
    ('Dr. Laila Ahmed', 'Anesthesiologist', 'laila.ahmed@hospital.com', '+1234567812'),
    ('Dr. Rashid Noman', 'Emergency Medicine', 'rashid.noman@hospital.com', '+1234567813'),
    ('Dr. Maria Yusuf', 'Dental Surgeon', 'maria.yusuf@hospital.com', '+1234567814'),
    ('Dr. Tariq Mehmood', 'Nutritionist', 'tariq.mehmood@hospital.com', '+1234567815'),
    ('Dr. Zainab Rafiq', 'Physiotherapist', 'zainab.rafiq@hospital.com', '+1234567816'),
    ('Dr. Saad Abbas', 'ENT Surgeon', 'saad.abbas@hospital.com', '+1234567817'),
    ('Dr. Rabia Khan', 'Cardiac Surgeon', 'rabia.khan@hospital.com', '+1234567818'),
    ('Dr. Noman Faraz', 'Oncologist', 'noman.faraz@hospital.com', '+1234567819'),
    ('Dr. Shazia Yousuf', 'Dermatology Specialist', 'shazia.yousuf@hospital.com', '+1234567820')";
if ($conn->query($sql) !== TRUE) {
    echo 'Insert error: ' . $conn->error . "\n";
}

$result = $conn->query('SELECT COUNT(*) as c FROM doctors');
$row = $result->fetch_assoc();
echo 'count=' . $row['c'] . "\n";
$result = $conn->query('SELECT name,specialty,email,phone FROM doctors ORDER BY id LIMIT 10');
while ($r = $result->fetch_assoc()) {
    echo $r['name'] . ' | ' . $r['specialty'] . ' | ' . $r['email'] . ' | ' . $r['phone'] . "\n";
}
?>