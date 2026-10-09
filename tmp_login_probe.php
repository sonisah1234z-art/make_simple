<?php
$cookieFile = sys_get_temp_dir() . '/admin-login-cookie.txt';
@unlink($cookieFile);

$ch = curl_init('http://localhost/make_simple/admin-login.php');
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
$body = curl_exec($ch);
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
echo 'status=' . $status . PHP_EOL;
echo 'loc=' . $loc . PHP_EOL;
echo substr($body, 0, 500) . PHP_EOL;
