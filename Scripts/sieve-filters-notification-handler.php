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
include 'config.php';

$DIR = __DIR__ . '/';
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

$getMessageSubject = function ($sMessage, $sMessageSubjectPattern) {
    $sSubject = '';
    preg_match("/$sMessageSubjectPattern/im", $sMessage, $sSubjectMatch);
    if (isset($sSubjectMatch[1])) {
        $sSubject = trim($sSubjectMatch[1]);
        // TODO: decrypt UTF8-encoded values
    }
    return $sSubject;
};

/* === Define the patterns and variables === */
$sMessageIdPattern = "^(?:\s*message-id):\s*(.+)$";
$sMessageSubjectPattern = "^(?:\s*subject):\s*(.+)$";

// Get the message subject
$sMessageSubject = $getMessageSubject($sMessage, $sMessageSubjectPattern);
// Get the message id line
preg_match("/$sMessageIdPattern/im", $sMessage, $sMessageIdLine);

/* === Output debug info ==== */
$logger("=== Process incomming message ===");
$logger("Recipient:", $RECIPIENT);
$logger("Message id:", $sMessageIdLine[1] ?? 'not-found');

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

    $aRequestData = array(
        'Module' => 'PushNotificator',
        'Method' => 'SendPush',
        'Parameters' => json_encode(array(
            "Secret" => $CONFIG_SECRET,
            "Data" => array(
                array(
                    "Email" => $RECIPIENT,
                    "Data" => array(
                        array(
                            "From" => $SENDER,
                            "To" => $RECIPIENT,
                            "Subject" => $sMessageSubject
                        )
                    )
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

$logger("=== END Process incomming message ===");
$logger();
