<?php

/**
 * A fake SMTP server for tests: accepts one connection on 127.0.0.1:{port},
 * speaks just enough SMTP (plain, AUTH LOGIN), records the whole conversation
 * to {transcript file}, then exits.
 *
 *   php tests/fixtures/fake-smtp.php 2525 /tmp/transcript.txt [password]
 */

[, $port, $transcriptFile] = $argv;
$expectedPassword = $argv[3] ?? 'secret';

$server = stream_socket_server("tcp://127.0.0.1:$port", $errno, $error);
if (!$server) {
    fwrite(STDERR, "listen failed: $error\n");
    exit(1);
}
echo "ready\n";

$client = stream_socket_accept($server, 15);
if (!$client) {
    exit(1);
}

$log = '';
$send = function (string $line) use ($client, &$log) {
    fwrite($client, $line . "\r\n");
    $log .= "S: $line\n";
};
$read = function () use ($client, &$log): string|false {
    $line = fgets($client);
    if ($line !== false) {
        $log .= 'C: ' . rtrim($line, "\r\n") . "\n";
    }
    return $line;
};

$send('220 fake.smtp ESMTP ready');
$authStep = 0;

while (($line = $read()) !== false) {
    $command = strtoupper(rtrim($line, "\r\n"));

    if ($authStep === 1) {
        $authStep = 2;
        $send('334 UGFzc3dvcmQ6');
        continue;
    }
    if ($authStep === 2) {
        $authStep = 0;
        $send(base64_decode(trim($line)) === $expectedPassword ? '235 Authentication succeeded' : '535 Authentication failed');
        continue;
    }

    if (str_starts_with($command, 'EHLO')) {
        $send('250-fake.smtp');
        $send('250-SIZE 10485760');
        $send('250 AUTH LOGIN');
    } elseif ($command === 'AUTH LOGIN') {
        $authStep = 1;
        $send('334 VXNlcm5hbWU6');
    } elseif (str_starts_with($command, 'MAIL FROM') || str_starts_with($command, 'RCPT TO')) {
        $send('250 OK');
    } elseif ($command === 'DATA') {
        $send('354 End data with <CR><LF>.<CR><LF>');
        $data = '';
        while (($dataLine = fgets($client)) !== false && $dataLine !== ".\r\n") {
            $data .= $dataLine;
        }
        $log .= "DATA-BEGIN\n$data" . "DATA-END\n";
        $send('250 OK queued');
    } elseif ($command === 'QUIT') {
        $send('221 Bye');
        break;
    } else {
        $send('502 Command not implemented');
    }
}

file_put_contents($transcriptFile, $log);
fclose($client);
