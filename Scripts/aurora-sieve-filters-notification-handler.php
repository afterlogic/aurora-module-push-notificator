#!/bin/php
<?php
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * @var string $CONFIG_AURORA_URL
 * @var string $CONFIG_SECRET
 * @var string $CONFIG_LOG_FILE_PATH
 */

$DIR = __DIR__ . '/';
include $DIR . 'aurora-notification-handler-config.php';
$DATE = date("Y-m-d");
$LOG_FILE = $CONFIG_LOG_FILE_PATH . 'seive-script-log-' . $DATE . '.log';

$oColors = (object)['no' => "\033[0m",'red' => "\033[1;31m",'green' => "\033[1;32m",'yellow' => "\033[1;33m",'bg_red' => "\033[1;41m",'bg_green' => "\033[1;42m",'bg_yellow' => "\033[1;43m"];
$bDebug = false;

if ($bDebug) {
    $LOG_FILE = $DIR . 'seive-script-log-' . $DATE . '.log';
    $SENDER = 'sender@domain.com';
    $RECIPIENT = '';
    $sMessage = file_get_contents($DIR . 'message.eml');
} else {
    // available environment variables: $HOME, $USER, $SENDER, $RECIPIENT, $ORIG_RECIPIENT
    $SENDER = getenv('SENDER');
    $RECIPIENT = getenv('RECIPIENT');
    $sMessage = file_get_contents('php://stdin');
}

// Define a log function
$bLogFileExists = false;
$logger = function ($label = '', ...$args) use ($oColors, $bDebug, $bLogFileExists, $LOG_FILE) {
    if (!$bLogFileExists) {
        $dir = dirname($LOG_FILE);

        if (!is_dir($dir)) {
            mkdir($dir, 0766, true);
        }

        $bLogFileExists = true;
    }

    if (isset($args[0]) && is_array($args[0])) {
        $args = array_map(function ($item) {
            return json_encode($item);
        }, $args);
    }
    if ($bDebug) {
        printf("%s %s\n", $label, $oColors->red . implode(" ", $args) . $oColors->no);
    }
    $TIME = date("h:i:s") . "." . substr((string)microtime(), 2, 3);
    $text = '[' . $TIME . '] ' . $label . ' ' . implode(" ", $args) . "\n";
    file_put_contents($LOG_FILE, $text, FILE_APPEND);
};

function getMessageSubject($sMessage)
{
    $sSubject = '';
    preg_match("/^(?:\s*subject):\s*(.+)$/im", $sMessage, $aMatch);
    if (isset($aMatch[1])) {
        $sSubject = trim($aMatch[1]);
        // TODO: decrypt UTF8-encoded values
    }
    return $sSubject;
};

function getMessageId($sMessage)
{
    $sSubject = '';
    preg_match("/^(?:\s*message-id):\s*(.+)$/im", $sMessage, $aMatch);
    if (isset($aMatch[1])) {
        $sSubject = trim($aMatch[1]);
        // TODO: decrypt UTF8-encoded values
    }
    return $sSubject;
};

// Get the message subject
$sMessageSubject = getMessageSubject($sMessage);
// Get the message id line
$sMessageId = getMessageId($sMessage);

/* === Output debug info ==== */
$logger("=== Process incomming message ===");
$logger("Recipient:", $RECIPIENT);
$logger("Message id:", $sMessageId);

if (!$CONFIG_AURORA_URL) {
    $logger("CONFIG_AURORA_URL is not set!");
} else {

    /* === getting account setting ==== */
    // if (!($USER && $PASS && $DATABASE)) {
    //     $logger("", "The script is not configured properly!");
    //     exit(0);
    // }
    // $mysqli = new mysqli("127.0.0.1", $USER, $PASS, $DATABASE);

    // if ($mysqli->connect_errno) {
    //     $logger("Failed to connect to MySQL:", $mysqli->connect_error);
    //     exit(0);
    // }

    // Getting message resipient's settings
    // $sAccountParamSQL = "" .
    // "SELECT * FROM mail_accounts AS a
    // LEFT JOIN core_users AS u ON u.Id = a.IdUser
    // WHERE a.Email === '$RECIPIENT' AND u.PublicId = '$RECIPIENT'";

    // if ($bDebug) {
    //     $logger("Account SQL: \n", $sAccountParamSQL);
    // }

    // // Execute the query and get the contacts count
    // $oResult = $mysqli->query($sAccountParamSQL);
    // $sAccountProperties = (int) $oResult->fetch_assoc()['Properties'];

    // if (!$aAccountParams || !isset($aAccountParams['PushEnabled'])) {
    //     $logger("No params found for mail account:", $RECIPIENT);
    //     exit(0);
    // }

    if (empty($RECIPIENT)) {
        $logger('Recipient address is not found.');
    } elseif (empty($SENDER) && empty($sMessageSubject)) {
        $logger('"From" and "Subject" headers are not found in the mail message.');
    } else {
        $aPushMessageData = [
            'From' => $SENDER,
            'To' => $RECIPIENT,
            'Subject' => $sMessageSubject,
            'Folder' => 'INBOX'
        ];

        if ($sMessageId) {
            $aPushMessageData['MessageId'] = $sMessageId;
        } else {
            $logger('"Message-ID" header is not found.');
        }

        $aRequestData = array(
            'Module' => 'PushNotificator',
            'Method' => 'SendPush',
            'Parameters' => json_encode(array(
                'Secret' => $CONFIG_SECRET,
                'Data' => array(
                    array(
                        'Email' => $RECIPIENT,
                        'Data' => [$aPushMessageData]
                    )
                )
            ))
        );

        $logger("API request url:", $CONFIG_AURORA_URL);
        $logger("API request data:", $aRequestData['Parameters']);

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $CONFIG_AURORA_URL . '/?/Api/',
            CURLOPT_POSTFIELDS => $aRequestData,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true
        ));

        $response = curl_exec($ch);
        curl_close($ch);
        $logger("API response:", $response);
    }
}

$logger("=== END Process incomming message ===");
$logger();
