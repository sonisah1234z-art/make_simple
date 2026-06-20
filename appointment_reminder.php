<?php
require 'send_email.php';

$patientEmail = "patient@gmail.com";

$subject = "Appointment Reminder";

$message = "
Dear Patient,<br><br>
This is a reminder for your appointment tomorrow.<br>
Thank You.
";

if(sendEmail($patientEmail, $subject, $message))
{
    echo "Appointment Reminder Sent";
}
else
{
    echo "Failed";
}
?>