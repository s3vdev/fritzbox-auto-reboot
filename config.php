<?php
/**
 * Created by PhpStorm.
 * User: svenmielke
 * Date: 12/7/20
 * Time: 1:39 PM
 */


// Fritz!Box Einstellungen
$fritzbox = "";
$fritz_login_user = "";
$fritz_login_passwort = "";
$downstream = 130000;
$do_reboot = true;

// Absender
$absender = "Fritz!Box Autoreboot";


//Email Versand
$send_mail = true; // false = email wird nicht gesendet, true = email wird vor FritzBox reboot gesendet
$smtp_host = '';
$smtp_username = '';
$smtp_password = '';
$smtp_send_from = '';
$smtp_addReplyTo = '';
$smtp_addReplyTo_name = '';
$smtp_port = 587;
$smtp_SMTPAuth = true;
$receivers = [
    'mail1@example.com',
    'mail2@example.com',
];


// Datenbank Verbindung
$db_host = "localhost";
$db_user = "";
$db_pass = "";
$db = "fritzbox";


// SMS Gateway
$send_sms = false; // false = SMS wird nicht gesendet, true = sms wird vor FritzBox reboot gesendet
$sms_user = "";
$sms_pass = "";
$sms_numbers = ""; // mehrere durch simikolon getrennt
$sms_absender = $absender;
$sms_reply = "";