#!/bin/php
<?php
if (PHP_SAPI !== 'cli') {
    exit;
}
/**
 * @var string $CONFIG_AURORA_URL
 * @var string $CONFIG_SECRET
 * @var string $CONFIG_LOG_FILE_PATH
 * @var bool $DEBUG_MODE
 * @var string $DEBUG_RECIPIENT
 * @var string $DEBUG_SENDER
 */

$DIR = __DIR__ . '/';
include $DIR . 'aurora-notification-handler-config.php';
$DATE = date("Y-m-d");
$LOG_FILE = (string) $CONFIG_LOG_FILE_PATH . 'seive-script-log-' . $DATE . '.log';

$oColors = (object)['no' => "\033[0m",'red' => "\033[1;31m",'green' => "\033[1;32m",'yellow' => "\033[1;33m",'bg_red' => "\033[1;41m",'bg_green' => "\033[1;42m",'bg_yellow' => "\033[1;43m"];
$bDebug = !!$DEBUG_MODE;

if ($bDebug) {
    $LOG_FILE = $DIR . 'seive-script-log-' . $DATE . '.log';
    $SENDER = (string) $DEBUG_RECIPIENT;
    $RECIPIENT = (string) $DEBUG_RECIPIENT;
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

/**
 * Decodes a MIME header (RFC 2047) into UTF-8.
 * Supports non-Latin characters, Q/Base64 encoding, multiple encoded-words.
 *
 * @param string $header The original header (e.g., Subject, From, etc.)
 * @return string Decoded UTF-8 string
 */
function decode_mime_header_utf8(string $header): string
{
    // 1) Remove "folding": CRLF + WSP -> space
    $h = preg_replace("/\r?\n[ \t]+/", " ", $header);

    // 2) Decode all blocks =?charset?Q|B?...?=
    $decoded = preg_replace_callback(
        '/=\?([^?]+)\?([bBqQ])\?([^?]*)\?=/u',
        function ($m) {
            $charset = trim($m[1], " \t\"'");       // e.g. UTF-8, KOI8-R, windows-1251
            $encoding = strtoupper($m[2]);          // Q or B
            $text = $m[3];

            if ($encoding === 'B') {
                $bin = base64_decode($text, true);
            } else { // Q-encoding in headers: "_" = space
                $text = str_replace('_', ' ', $text);
                $bin = quoted_printable_decode($text);
            }

            if ($bin === false) {
                return $m[0]; // fallback to original fragment
            }

            // 3) Convert to UTF-8 safely, handling exotic charsets
            // Prefer mbstring, fallback to iconv
            if (function_exists('mb_convert_encoding')) {
                $out = @mb_convert_encoding($bin, 'UTF-8', $charset);
                if ($out !== false) {
                    return $out;
                }
            }
            if (function_exists('iconv')) {
                $out = @iconv($charset, 'UTF-8//TRANSLIT', $bin);
                if ($out !== false) {
                    return $out;
                }
            }

            // If conversion failed — return raw data
            return $bin;
        },
        $h
    );

    // 4) Normalize spaces between adjacent encoded-words
    $decoded = preg_replace('/\s{2,}/u', ' ', $decoded);

    return trim($decoded);
}

function getMessageSubject($sMessage)
{
    $sSubject = '';
    preg_match("/^(?:\s*subject):\s*(.+)$/im", $sMessage, $aMatch);
    if (isset($aMatch[1])) {
        $sSubject = decode_mime_header_utf8(trim($aMatch[1]));
    }
    return $sSubject;
};

function getMessageId($sMessage)
{
    $sMessageId = '';
    preg_match("/^(?:\s*message-id):\s*(.+)$/im", $sMessage, $aMatch);
    if (isset($aMatch[1])) {
        $sMessageId = trim($aMatch[1]);
    }
    return $sMessageId;
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
