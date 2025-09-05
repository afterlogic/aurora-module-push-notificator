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
 * Extracts Subject and Message-ID headers from a raw EML message.
 * - Subject: returns '' when header exists but is empty; null if header missing.
 * - Message-ID: angle brackets are stripped if present.
 *
 * @param string $eml Full EML message as string
 * @return array{subject: string|null, message_id: string|null}
 */
function getHeadersFromEml(string $eml): array
{
    // Split headers and body (first empty line separates them)
    $parts = preg_split("/\R\R/", $eml, 2);
    $rawHeaders = $parts[0] ?? '';

    // Unfold headers: join continuation lines starting with space or tab
    $rawHeaders = preg_replace("/\r?\n[ \t]+/", ' ', $rawHeaders);

    $result = [
        'subject'    => null,
        'message_id' => null,
    ];

    // Subject: allow empty value -> use (.*) not (.+), anchored at start of line
    if (preg_match('/^Subject:[ \t]*(.*)$/mi', $rawHeaders, $m)) {
        $rawSubject = rtrim($m[1]); // keep empty string if it's truly empty

        $decoded = decode_mime_header_best_effort($rawSubject);
        // If decoder returns null, keep raw; if it's empty string, preserve emptiness
        $result['subject'] = $decoded !== null ? $decoded : $rawSubject;
    }

    // Message-ID: prefer <...>, fallback to non-space token
    if (preg_match('/^Message-ID:[ \t]*(\S+)/mi', $rawHeaders, $m)) {
        $result['message_id'] = trim($m[1]);
    }

    return $result;
}

/**
 * Best-effort MIME header decode to UTF-8.
 * Returns '' for empty input, null only on hard failure.
 */
function decode_mime_header_best_effort(string $value): ?string
{
    // Preserve explicit emptiness
    if (trim($value) === '') {
        return '';
    }

    // Prefer mbstring
    if (function_exists('mb_decode_mimeheader')) {
        $out = @mb_decode_mimeheader($value);
        // mb_decode_mimeheader never returns false, but keep symmetry
        return $out;
    }

    // Fallback to iconv
    if (function_exists('iconv_mime_decode')) {
        $out = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        if ($out !== false) {
            return $out;
        }
    }

    // Last resort: return as-is
    return $value;
}


$headers = getHeadersFromEml($sMessage);
// Get the message subject
$sMessageSubject = $headers['subject'] ?? '';
// Get the message id line
$sMessageId = $headers['message_id'] ?? '';

/* === Output debug info ==== */
$logger("=== Process incomming message ===");
$logger("Recipient:", $RECIPIENT);
$logger("Message id:", $sMessageId);
$logger("Subject:", $sMessageSubject);

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
