<?php
$cookieFile = sys_get_temp_dir() . '/admin-dashboard-cookie.txt';
@unlink($cookieFile);

$ch = curl_init('http://localhost/make_simple/admin-login.php');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_exec($ch);
curl_close($ch);

$ch = curl_init('http://localhost/make_simple/admin-login.php');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'admin', 'password' => 'admin123']));
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loc = curl_getinfo($ch, CURLINFO_REDIRECT_URL);

$dashboardCh = curl_init('http://localhost/make_simple/admin-dashboard.php');
curl_setopt($dashboardCh, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($dashboardCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($dashboardCh, CURLOPT_FOLLOWLOCATION, true);
$dashboardBody = curl_exec($dashboardCh);
$dashboardStatus = curl_getinfo($dashboardCh, CURLINFO_HTTP_CODE);

echo 'login_status=' . $status . PHP_EOL;
echo 'login_loc=' . $loc . PHP_EOL;
echo 'dashboard_status=' . $dashboardStatus . PHP_EOL;
if (strpos($dashboardBody, 'Admin Console') !== false) {
    echo 'contains_admin_console=yes' . PHP_EOL;
} else {
    echo 'contains_admin_console=no' . PHP_EOL;
}
if (strpos($dashboardBody, 'Welcome,') !== false) {
    echo 'contains_welcome=yes' . PHP_EOL;
} else {
    echo 'contains_welcome=no' . PHP_EOL;
}
$pos = strpos($dashboardBody, 'Admin Console');
if ($pos !== false) {
    echo substr($dashboardBody, max(0, $pos - 200), 800) . PHP_EOL;
}
