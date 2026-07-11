<?php
$conn = new mysqli('localhost','root','','hospital_system');
if ($conn->connect_error) { echo 'ERR:' . $conn->connect_error; exit(1); }
$selectedDepartment = '';
$selectedDepartmentName = '';
$selectedSpecialty = '';
$departmentKeywords = [
    'Critical Care Medicine' => ['critical', 'icu', 'critical care'],
    'Dental' => ['dental', 'dentist', 'oral', 'tooth'],
    'Dermatology' => ['dermatology', 'derma', 'skin'],
    'Emergency Medicine' => ['emergency', 'er', 'urgent'],
    'ENT (Ear, Nose & Throat)' => ['ent', 'ear', 'nose', 'throat'],
    'Orthopedics and Trauma Surgery' => ['orthopedic', 'orthopaedic', 'trauma', 'bone', 'joint'],
    'General Practice' => ['general practice', 'general medicine', 'primary care', 'family medicine', 'physician'],
    'Internal Medicine' => ['internal medicine', 'internal'],
    'Neurology' => ['neurology', 'neuro', 'neurologist', 'brain'],
    'Neurosurgery' => ['neurosurgery', 'neurosurgeon', 'brain surgery'],
    'Nephrology' => ['nephrology', 'kidney'],
    'Obstetrics & Gynecology' => ['obstetrics', 'gynecology', 'obgyn', 'women’s health', 'women'],
    'Paediatrics' => ['paediatrics', 'pediatrics', 'pediatric', 'child'],
    'Psychiatry & Mental Health' => ['psychiatry', 'mental health', 'psychology', 'psych'],
    'Plastic Surgery and Burns' => ['plastic surgery', 'burns', 'reconstructive'],
    'Pharmacy' => ['pharmacy', 'pharmacist', 'medicines'],
    'Radiology' => ['radiology', 'imaging', 'x-ray', 'ct', 'mri', 'ultrasound'],
    'Urology and Kidney Transplant Surgery' => ['urology', 'kidney transplant', 'kidney'],
    'Cardiology' => ['cardiology', 'cardiologist', 'heart'],
    'Pediatrics' => ['pediatrics', 'pediatric', 'child', 'children'],
    'General Physician' => ['general physician', 'general medicine', 'family doctor', 'general practitioner'],
];
$doctorQuery = "SELECT id, name, specialty, availability_status, status, available_slots, department, working_days FROM doctors WHERE (status = 'Available' OR availability_status = 'Available')";
$departmentFilterTerm = '';
if ($selectedDepartmentName !== '') {
    $departmentFilterTerm = $selectedDepartmentName;
} elseif ($selectedDepartment !== '') {
    $departmentFilterTerm = $selectedDepartment;
}
if ($departmentFilterTerm !== '') {
    $normalizedFilterTerm = strtolower(trim($departmentFilterTerm));
    $keywords = $departmentKeywords[$departmentFilterTerm] ?? [$normalizedFilterTerm];
    $extraTerms = array_filter(array_unique(array_merge([$normalizedFilterTerm], preg_split('/[^a-z0-9]+/', $normalizedFilterTerm))), fn($term) => strlen(trim($term)) > 1);
    $filters = [];
    foreach (array_unique(array_merge($keywords, $extraTerms)) as $keyword) {
        $keyword = trim((string)$keyword);
        if ($keyword === '') {
            continue;
        }
        $escapedKeyword = $conn->real_escape_string(strtolower($keyword));
        $filters[] = "LOWER(specialty) LIKE '%$escapedKeyword%'";
        $filters[] = "LOWER(department) LIKE '%$escapedKeyword%'";
    }
    if (!empty($filters)) {
        $doctorQuery .= ' AND (' . implode(' OR ', $filters) . ')';
    }
}
if ($selectedSpecialty !== '') {
    $escapedSpecialty = $conn->real_escape_string(strtolower($selectedSpecialty));
    $doctorQuery .= " AND (LOWER(specialty) LIKE '%$escapedSpecialty%' OR LOWER(department) LIKE '%$escapedSpecialty%')";
}
$doctorQuery .= ' ORDER BY name ASC';
$res = $conn->query($doctorQuery);
if (!$res) { echo 'QUERY_ERR:' . $conn->error; exit(1); }
echo "QUERY: $doctorQuery\n";
echo "COUNT: " . $res->num_rows . "\n";
while ($row = $res->fetch_assoc()) {
    echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['specialty'] . ' | ' . $row['status'] . ' | ' . $row['availability_status'] . ' | ' . $row['department'] . ' | ' . $row['available_slots'] . '\n';
}
?>