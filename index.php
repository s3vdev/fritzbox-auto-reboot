<?php
/**
 * Created by PhpStorm.
 * User: svenmielke
 * Date: 2020-08-16
 * Time: 13:15
 *
 * Install LAMP to Raspberry PI
 * https://projects.raspberrypi.org/de-DE/projects/lamp-web-server-with-wordpress/4
 * https://randomnerdtutorials.com/raspberry-pi-apache-mysql-php-lamp-server/
 * https://stackoverflow.com/questions/2509143/how-do-i-install-soap-extension
 *
 * Source: https://www.schlaue-huette.de/apis-co/fritz-tr064/dsl-informationen-auslesen/
 * Reboot: https://www.ip-phone-forum.de/threads/remote-reboot.285557/
 * Crontab generator: https://crontab-generator.org/
 *
 * AddOn
 * https://blog.butenostfreesen.de/2018/10/11/Fritz-Box-Monitoring-mit-Grafana-und-Raspberry/
 *
 * PHPMailer
 * https://github.com/PHPMailer/PHPMailer
 *
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



// Load PHPMailer
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'config.php';

// Import PHPMailer classes into the global namespace. These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


### DO NOT EDIT THIS ###
try {

    $timestamp = time();
    $router_status = 'ok';
    $sms_status = '';
    $reboot_status = '';
    $status_color = 'green';

    function secondsToTime($seconds)
    {
        $dtF = new \DateTime('@0');
        $dtT = new \DateTime("@$seconds");
        return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
    }

    function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        // $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    function reboot($host, $user, $password)
    {

        $reboot = new SoapClient(null,
            [
                'location' => "http://$host:49000/upnp/control/deviceconfig",
                'uri' => "urn:dslforum-org:service:DeviceConfig:1",
                'noroot' => True,
                'login' => $user,
                'password' => $password,
                'trace' => True,
                'exceptions' => 0
            ]
        );

        $action = 'Reboot';
        //$argument = 'NewSessionID';
        //$SID = '0000000000000000';
        //$direction_in_value = $SID;
        //$result = $reboot->{$action}(new SoapParam($direction_in_value, $argument));
        $result = $reboot->{$action}();

        /*
        if(is_soap_fault($result)) {
            echo " Fehlercode: $result->faultcode | Fehlerstring: $result->faultstring";
        } else {
            echo "$result";
        }
        */

        if ($result != null) {
            $reboot = false;
        } else {
            $reboot = true;
        }

        return $reboot;
    }


    function sms($reason, $absender)
    {

        global $sms_numbers, $sms_user, $sms_pass, $absender, $sms_reply;

        // Handynummern mit Semikolon getrennt
        $handy = "$sms_numbers";
        $request = "";
        // Benutzername
        $param["id"] = "$sms_user";
        // Passwort
        $param["pw"] = "$sms_pass";
        // SMS Type
        $param["type"] = "2";
        //Empfänger mit Semikolon getrennt
        $param["empfaenger"] = $handy;
        // Absender
        $param["absender"] = "$absender";
        // Inhalt der SMS
        $param["text"] = "$reason";
        // SMS Code für Antwortfunktion Typ 2
        $param["reply"] = "1";
        // E-Mailadresse für Antwort
        $param["reply_email"] = "$sms_reply";
        // Datum und Uhrzeit für Terminsms
        $param["termin"] = "TT.MM.JJJJ-SS:MM";
        // Sendestatus
        $param["id_status"] = "1";
        // Massenversand einleiten mit mehr als 500 Empfängern gleichzeitig
        $param["massen"] = "2";
        foreach ($param as $key => $val) {
            $request .= $key . "=" . urlencode($val);
            //append the ampersand (&) sign after each paramter/value pair
            $request .= "&";
        }
        $url = "http://www.smskaufen.com/sms/gateway/sms.php";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    function email($reason, $absender) {
        global $smtp_host, $smtp_SMTPAuth, $smtp_username, $smtp_password, $smtp_port, $smtp_send_from, $smtp_addReplyTo_name, $smtp_addReplyTo, $receivers;

        // Instantiation and passing `true` enables exceptions
        $mail = new PHPMailer(true);

        try {
            //Server settings
            //$mail->SMTPDebug = SMTP::DEBUG_SERVER;                    // Enable verbose debug output
            $mail->isSMTP();                                            // Send using SMTP
            $mail->Host       = $smtp_host;                             // Set the SMTP server to send through
            $mail->SMTPAuth   = $smtp_SMTPAuth;                         // Enable SMTP authentication
            $mail->Username   = $smtp_username;                         // SMTP username
            $mail->Password   = $smtp_password;                         // SMTP password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
            $mail->Port       = $smtp_port;                             // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

            //Recipients
            $mail->setFrom($smtp_send_from, $absender);
            $mail->addReplyTo($smtp_addReplyTo, $smtp_addReplyTo_name);


            foreach ($receivers as $receiver) {
                $mail->addAddress($receiver); // Name is optional
            }

            //$mail->addCC('nerratenderra@gmail.com');
            //$mail->addBCC('bcc@example.com');

            // Attachments
            //$mail->addAttachment('/var/tmp/file.tar.gz');             // Add attachments
            //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');        // Optional name

            // Content
            $mail->isHTML(true);                                // Set email format to HTML
            $mail->Subject = $absender;
            $mail->Body    = $reason;
            $mail->AltBody = $reason;

            $mail->send();
            $mail_status = 'Email has been sent';
        } catch (Exception $e) {
            $mail_status =  "Email could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        return $mail_status;
    }

    $client0 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/igdupnp/control/WANCommonIFC1",
            'uri' => "urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1",
            'soapaction' => "urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1#GetCommonLinkProperties",
            'noroot' => True
        ]
    );


    $client1 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/igdupnp/control/WANCommonIFC1",
            'uri' => "urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1",
            'soapaction' => "urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1#GetAddonInfos",
            'noroot' => True
        ]
    );

    $client2 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/igdupnp/control/WANIPConn1",
            'uri' => "urn:schemas-upnp-org:service:WANIPConnection:1",
            'soapaction' => "urn:schemas-upnp-org:service:WANIPConnection:1#GetStatusInfo",
            'noroot' => True
        ]
    );

    $client3 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/igdupnp/control/WANIPConn1",
            'uri' => "urn:schemas-upnp-org:service:WANIPConnection:1",
            'soapaction' => "urn:schemas-upnp-org:service:WANIPConnection:1#GetExternalIPAddress",
            'noroot' => True
        ]
    );


    $client4 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/upnp/control/wandslifconfig1",
            'uri' => "urn:dslforum-org:service:WANDSLInterfaceConfig:1",
            'soapaction' => "urn:dslforum-org:service:WANDSLInterfaceConfig:1#GetStatisticsTotal",
            'noroot' => True,
            'login' => $fritz_login_user,
            'password' => $fritz_login_passwort
        ]
    );


    $client5 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/upnp/control/wandslifconfig1",
            'uri' => "urn:dslforum-org:service:WANDSLInterfaceConfig:1",
            'soapaction' => "urn:dslforum-org:service:WANDSLInterfaceConfig:1#GetInfo",
            'noroot' => True,
            'login' => $fritz_login_user,
            'password' => $fritz_login_passwort
        ]
    );

    $client6 = new SoapClient(null,
        [
            'location' => "http://$fritzbox:49000/upnp/control/wlanconfig3",
            'uri' => "urn:dslforum-org:service:WLANConfiguration:3",
            'soapaction' => "urn:dslforum-org:service:WLANConfiguration:3#GetInfo",
            'noroot' => True,
            'login' => $fritz_login_user,
            'password' => $fritz_login_passwort
        ]
    );


    $status = $client0->GetCommonLinkProperties();
    $status1 = $client1->GetAddonInfos();
    $status2 = $client2->GetStatusInfo();
    $status3 = $client3->GetExternalIPAddress();
    $status4 = $client4->GetStatisticsTotal();
    $status5 = $client5->GetInfo();
    $status6 = $client6->GetInfo();

    #echo "<pre>";
    #print_r($client4->GetStatisticsTotal());
    #echo "</pre>";


    // Set some vars
    $wanip = $status3;
    $upload = $status['NewLayer1UpstreamMaxBitRate'] / 1000; // Maximale Upstream Rate, welche im DSL Profil zugelassen wird
    $download = $status['NewLayer1DownstreamMaxBitRate'] / 1000; // Maximale Downstream Rate, welche im DSL Profil zugelassen wird
    $physicallink = $status['NewPhysicalLinkStatus']; // DSL Sync Status
    $wanaccess = $status['NewWANAccessType']; // Verbindungstyp
    $connection_status = $status2['NewConnectionStatus']; //PPPoE Verbindungsstatus
    $wanaccess_type = $status1['NewX_AVM_DE_WANAccessType']; //WANAccessType
    $uptime = $status2['NewUptime']; // Dauer der Verbindung
    $crcdown = $status4['NewCRCErrors']; // CRC Fehler seit letztem Resync im Downstream
    $crcup = $status4['NewATUCCRCErrors']; // CRC Fehler seit letztem Resync im Upstream
    $max_upload = $status5['NewUpstreamMaxRate']; // Maximale Leitungskapazität im Upstream
    $max_download = $status5['NewDownstreamMaxRate']; // Maximale Leitungskapazität im Downstream
    $snr_upstream = $status5['NewUpstreamNoiseMargin'] / 10; // Signal Rauschabstand im Upstream
    $snr_downstream = $status5['NewDownstreamNoiseMargin'] / 10; // Signal Rauschabstand im Downstream
    $daempfung_downstream = $status5['NewDownstreamAttenuation'] / 10; // Dämpfung Downstream
    $daempfung_upstream = $status5['NewUpstreamAttenuation'] / 10; // Dämpfung Upstream
    $gaestewlan_status = $status6['NewStatus']; // Gäste WLAN Status
    $gaestewlan_ssid = $status6['NewSSID']; // Gäste WLAN SSID

    // Begin checks...
    if (empty($status)) {
        $status_color = "red";
        $reboot_status = "Die Daten der Fritz!Box konnten nicht ausgelesen werden! Überprüfe Benutzername und Passwort!";
        exit();
    } elseif ($connection_status != "Connected") {
        $status_color = "red";
        $reboot_status = "Die Fritz!Box ist nicht mit dem Internet verbunden!";
    } elseif ($connection_status == "Connected" AND $download < $downstream ) {

        $status_color = "red";
        $router_status = 'rebooted';
        $text = "Die Fritz!Box versucht soeben neuzustarten, da der Downstream unter {$downstream} war. Downstream: " . round(number_format($download, 0)) . " kbit/s - Upstream: " . round(number_format($upload, 0)) . " kbit/s";

        // Send E-Mail/Push/Telegram...
        if ($send_sms) {
            $send_status = sms($text, $absender);
        } elseif ($send_mail) {
            $send_status = email($text, $absender);
        } else {
            $send_status = 'ok, reboot ohne send function.';
        }

        if (!empty($send_status) AND $do_reboot) {

            // Reboot Fritz!Box
            $reboot = reboot($fritzbox, $fritz_login_user, $fritz_login_passwort);
            if ($reboot) {
                $reboot_status = "$text!";
            } else {
                $reboot_status = "Die Fritz!Box konnte nicht automatisch neugestartet werden. Versuche einen manuellen neustart!";
            }
        }

    } else {
        $reboot_status = "Die Fritz!Box funktioniert ordnungsgemäß!";
    }

    // Insert Record to Database
    $mysqli = new mysqli("$db_host", "$db_user", "$db_pass", "$db");
    if ($mysqli->connect_errno) {
        printf("MySql Connect failed: %s\n", $mysqli->connect_error);
        exit();
    }

    // If Monitoring runs error log to MySQl
    if ($router_status != "ok") {

        $sql = "INSERT INTO fritzbox (downstream_current, upstream_current, dsl_state, connection_type, pppoe_connected, pppoe_time, ip, crc_downstream, crc_upstream, max_downstream, max_upstream, snr_downstream, snr_upstream, daempfung_downstream, daempfung_upstream, timestamp, gaestewlan_status, gaestewlan_ssid, router_status, send_status) VALUES ('$download','$upload','$physicallink','$wanaccess ($wanaccess_type)','$connection_status','$uptime','$status3','$crcdown','$crcup','$max_download','$max_upload','$snr_downstream','$snr_upstream','$daempfung_downstream','$daempfung_upstream','$timestamp','$gaestewlan_status','$gaestewlan_ssid', '$router_status', '$send_status');";
        if ($mysqli->query($sql) === TRUE) {
            //echo "New record created successfully";
        } else {
            echo "Error: " . $sql . "<br>" . $mysqli->error;
        }
    }

    //$wanaccess = $wanaccess == 'Other' ? "LTE" : "DSL";
    $connection_status = $connection_status == 'Connected' ? "<span style='color: green'>Online</span>" : "<span style='color: red;'>Offline</span>";
    ?>

    <!DOCTYPE html>
    <html lang="de">
    <head>
        <!--<meta http-equiv="refresh" content="10">-->
        <meta http-equiv=content-type content="text/html; charset=utf-8"/>
        <meta http-equiv="Cache-Control" content="private, no-transform"/>
        <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
        <meta name="format-detection" content="telephone=no"/>
        <meta http-equiv="x-rim-auto-match" content="none"/>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, minimal-ui"/>
        <meta name="mobile-web-app-capable" content="yes"/>
        <meta name="apple-mobile-web-app-capable" content="yes"/>
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
        <meta http-equiv="cleartype" content="on">
        <link rel="shortcut icon" type="image/x-icon" href="favicon.ico"/>
        <link rel="apple-touch-icon" href="logo_fritzDiamond.png"/>
        <link rel="apple-touch-startup-image" href="logo_fritzDiamond.png">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.1.3/css/bootstrap.css">
        <link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.css">
        <style>
            html, input, textarea, keygen, select, button {
                font-family: 'Source Sans Pro', Arial, sans-serif;
                font-size: 100%;
            }
        </style>
        <title>
            FRITZ!Box Monitoring & Reboot
        </title>
    </head>
    <body>

    <div class="container">
        <h4 style="color: <?= $status_color ?>"><?= $reboot_status ?></h4>

        <table>
            <tr>
                <td bgcolor="#eeeeee" align="right">WAN IP:</td>
                <td><b><?= $wanip ?></b></td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">Max. Profil Upstream:</td>
                <td><b><?= round(number_format($upload, 0)) ?> Mbit/s</b></td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">Max. Profil Downstream:</td>
                <td><b><?= round(number_format($download, 0)) ?> Mbit/s</b></td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">DSL Sync:</td>
                <td><b><?= $physicallink ?></b></td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">Zugangsart:</td>
                <td><b><?= $wanaccess ?></b> (<?= $wanaccess_type ?>)</td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">PPPoE Status:</td>
                <td><b><?= $connection_status ?></b></td>
            </tr>
            <tr>
                <td bgcolor="#eeeeee" align="right">Verbindungsdauer in Sek.:</td>
                <td><b><?= $uptime ?></b> (<?= secondsToTime($uptime) ?>)</td>
            </tr>

            <tr>
            <td bgcolor="#eeeeee" align="right">CRC Fehler Downstream:</td>
            <td><b><?= $crcdown ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">CRC Fehler Upstream:</td>
            <td><b><?= $crcup ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">Max. Leitungskapazität Downstream in kbit/s</td>
            <td><b><?= $max_download ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">Max. Leitungskapazität Upstream in kbit/s</td>
            <td><b><?= $max_upload ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">SNR Downstream in dB</td>
            <td><b><?= $snr_downstream ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">SNR Upstream in dB</td>
            <td><b><?= $snr_upstream ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">Dämpfung Downstream in dB</td>
            <td><b><?= $daempfung_downstream ?></b></td>
            </tr>
            <tr>
            <td bgcolor="#eeeeee" align="right">Dämpfung Upstream in dB</td>
            <td><b><?= $daempfung_upstream ?></b></td>
            </tr>
        </table>
    </div>

    <br><br>

    <div class="container">
        <h4>History</h4>

        <div class="accordion" id="accordionExample">
            <div class="card">
                <div class="card-header" id="headingOne">
                    <h5 class="mb-0">
                        <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="false" aria-controls="collapseOne">
                            Reboot History
                        </button>
                    </h5>
                </div>
                <div id="collapseOne" class="collapse show" aria-labelledby="headingOne" data-parent="#accordionExample">
                    <div class="card-body">
                        <table id="historie" class="table table-striped table-bordered" style="width:100%">
                            <thead>
                            <tr>
                                <th>Down</th>
                                <th>UP</th>
                                <th>Uptime</th>
                                <th>IP</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>

                            <?php
                            $sql = "SELECT * FROM fritzbox ORDER BY timestamp DESC";
                            $results = $mysqli->query($sql);
                            if ($results && $results->num_rows > 0) {
                                $results->fetch_all(MYSQLI_ASSOC);
                            }

                            foreach($results as $result) {

                                ?>

                                <tr>
                                    <td><?= round(number_format($result['downstream_current'], 0)) ?> Mbits</td>
                                    <td><?= round(number_format($result['upstream_current'], 0)) ?> Mbits</td>
                                    <td><?= secondsToTime($result['pppoe_time']) ?></td>
                                    <td><?= $result['ip'] ?></td>
                                    <td><?= date('d.m.Y H:i:s', $result['timestamp']) ?></td>
                                    <td class="text-center"><i class="fas fa-smoking" style="color: red" data-toggle="tooltip" data-placement="top" title="Firtz!Box has <?= $result['router_status'] ?>"></i></td>
                                </tr>

                            <?php } ?>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="//code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js" integrity="sha384-ZMP7rVo3mIykV+2+9J3UJ46jBk0WLaUAdn689aCwoqbBJiSnjAK/l8WvCWPIPm49" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js" integrity="sha384-ChfqqxuZUCnJSK3+MXmPNIyE6ZbWh2IMqE241rYiqJxyMiZ6OW/JmZQ5stwEULTy" crossorigin="anonymous"></script>
    <script src="//cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    <script src="//cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
    <script>
        $(document).ready(function () {

            $(function () {
                $('[data-toggle="tooltip"]').tooltip()
            })

            $('#historie').DataTable();
        });
    </script>
    </body>
    </html>
    <?php

    $mysqli->close();

} catch (exception $e) {

    /*
    echo '<pre>';
    print_r($e);
    echo '</pre>';
    */

    echo $e->getMessage();
}
?>