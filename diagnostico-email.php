<?php
/**
 * Diagnóstico de Email - DELETE APÓS USAR!
 */
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO DE EMAIL ===\n\n";

// 1. Verificar .env
echo "1. ARQUIVO .ENV:\n";
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    echo "   ✅ .env existe\n";
} else {
    echo "   ❌ .env NÃO EXISTE!\n";
}

// Carregar .env
$env = [];
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        $env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

echo "\n2. VARIÁVEIS DE EMAIL:\n";
$vars = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
foreach ($vars as $var) {
    $val = $env[$var] ?? '(não definido)';
    if ($var === 'MAIL_PASSWORD') {
        $val = $val !== '(não definido)' ? str_repeat('*', strlen($val)) . ' (' . strlen($val) . ' chars)' : $val;
    }
    echo "   {$var}: {$val}\n";
}

$host = $env['MAIL_HOST'] ?? 'mail.igrejavivoscomcristo.com';
$port = (int)($env['MAIL_PORT'] ?? 587);

echo "\n3. EXTENSÕES PHP:\n";
echo "   openssl: " . (extension_loaded('openssl') ? "✅ OK" : "❌ FALTANDO") . "\n";

echo "\n4. TESTE DE CONEXÃO ({$host}:{$port}):\n";
$usaSSL = ($port === 465);
$erro = '';
$errNo = 0;

$start = microtime(true);

if ($usaSSL) {
    echo "   Modo: SSL direto (porta 465)\n";
    $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $socket = @stream_socket_client("ssl://{$host}:{$port}", $errNo, $erro, 10, STREAM_CLIENT_CONNECT, $context);
} else {
    echo "   Modo: STARTTLS (porta {$port})\n";
    $socket = @fsockopen($host, $port, $errNo, $erro, 10);
}

$elapsed = round((microtime(true) - $start) * 1000);

if (!$socket) {
    echo "   ❌ FALHOU em {$elapsed}ms\n";
    echo "   Erro: [{$errNo}] {$erro}\n";
} else {
    echo "   ✅ CONECTOU em {$elapsed}ms!\n";
    $banner = fgets($socket, 515);
    echo "   Banner: " . trim($banner) . "\n";
    
    echo "\n5. TESTE DE AUTENTICAÇÃO:\n";
    
    fputs($socket, "EHLO eventos.vivos.site\r\n");
    $resp = '';
    while ($line = fgets($socket, 515)) {
        $resp .= $line;
        if (substr($line, 3, 1) !== '-') break;
    }
    echo "   EHLO: " . (str_starts_with($resp, '250') ? "✅ OK" : "❌ " . trim($resp)) . "\n";
    
    // STARTTLS para porta 587
    if (!$usaSSL) {
        fputs($socket, "STARTTLS\r\n");
        $resp = fgets($socket, 515);
        echo "   STARTTLS: " . (str_starts_with($resp, '220') ? "✅ OK" : "❌ " . trim($resp)) . "\n";
        
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        fputs($socket, "EHLO eventos.vivos.site\r\n");
        $resp = '';
        while ($line = fgets($socket, 515)) {
            $resp .= $line;
            if (substr($line, 3, 1) !== '-') break;
        }
    }
    
    fputs($socket, "AUTH LOGIN\r\n");
    $resp = fgets($socket, 515);
    echo "   AUTH LOGIN: " . (str_starts_with($resp, '334') ? "✅ OK" : "❌ " . trim($resp)) . "\n";
    
    $user = $env['MAIL_USERNAME'] ?? '';
    $pass = $env['MAIL_PASSWORD'] ?? '';
    
    if ($user && $pass) {
        fputs($socket, base64_encode($user) . "\r\n");
        $resp = fgets($socket, 515);
        
        fputs($socket, base64_encode($pass) . "\r\n");
        $resp = fgets($socket, 515);
        
        if (str_starts_with($resp, '235')) {
            echo "   LOGIN: ✅ AUTENTICADO COM SUCESSO!\n";
        } else {
            echo "   LOGIN: ❌ FALHOU - " . trim($resp) . "\n";
        }
    } else {
        echo "   LOGIN: ⚠️ Credenciais não configuradas\n";
    }
    
    fputs($socket, "QUIT\r\n");
    fclose($socket);
}

echo "\n=== FIM ===\n";
echo "⚠️ DELETE ESTE ARQUIVO APÓS USAR!\n";
