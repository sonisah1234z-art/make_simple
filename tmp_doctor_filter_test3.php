<?php
$conn = new mysqli('localhost','root','','hospital_system');
if ($conn->connect_error) { echo 'ERR:' . $conn->connect_error; exit(1); }
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
$tests = ['General Practice','Cardiology','Pediatrics','ENT (Ear, Nose & Throat)','Orthopedics and Trauma Surgery','General Physician'];
foreach ($tests as $term) {
    $departmentFilterTerm = $term;
    $normalizedFilterTerm = strtolower(trim($departmentFilterTerm));
    $keywords = $departmentKeywords[$departmentFilterTerm] ?? [$normalizedFilterTerm];
    $extraTerms = array_filter(array_unique(array_merge([$normalizedFilterTerm], preg_split('/[^a-z0-9]+/', $normalizedFilterTerm))), fn($term) => strlen(trim($term)) > 1);
    $filters = [];
    foreach (array_unique(array_merge($keywords, $extraTerms)) as $keyword) {
        $keyword = trim((string)$keyword);
        if ($keyword === '') continue;
        $escapedKeyword = $conn->real_escape_string(strtolower($keyword));
        $filters[] = "LOWER(specialty) LIKE '%$escapedKeyword%'";
        $filters[] = "LOWER(department) LIKE '%$escapedKeyword%'";
    }
    $doctorQuery = "SELECT id, name, specialty, availability_status, status, available_slots, department FROM doctors WHERE (status = 'Available' OR availability_status = 'Available')";
    if (!empty($filters)) {
        $doctorQuery .= ' AND (' . implode(' OR ', $filters) . ')';
    }
    $doctorQuery .= ' ORDER BY name ASC';
    $res = $conn->query($doctorQuery);
    echo "TERM: $term | COUNT: " . ($res ? $res->num_rows : 'ERR') . "\n";
    echo "QUERY: $doctorQuery\n";
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo $row['id'] . ' | ' . $row['name'] . ' | ' . $row['specialty'] . ' | ' . $row['department'] . '\n';
        }
    }
    echo "------\n";
}
?>