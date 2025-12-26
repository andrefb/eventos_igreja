<?php
/**
 * Configuração do Sistema de Inscrição
 * Igreja Vivos com Cristo - Réveillon 2025
 */

// Configurações de erro (desativar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Timezone São Paulo
date_default_timezone_set('America/Sao_Paulo');

/**
 * Carregar variáveis do .env
 */
function loadEnv(): void {
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) return;
    
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        
        if (!getenv($name)) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

loadEnv();

// Configurações do evento
define('EVENTO_NOME', getenv('EVENTO_NOME') ?: 'Culto de Réveillon 2025');
define('EVENTO_DATA', getenv('EVENTO_DATA') ?: '2025-12-31 19:30:00');
define('EVENTO_LOCAL', getenv('EVENTO_LOCAL') ?: 'Vila Velha');

// Configurações de email
define('MAIL_HOST', getenv('MAIL_HOST') ?: '');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Igreja Vivos com Cristo');

// Configurações do admin
define('ADMIN_USER', getenv('ADMIN_USER') ?: 'admin');
define('ADMIN_PASS_ENV', getenv('ADMIN_PASS') ?: 'admin123');

// Caminho do banco de dados SQLite
define('DB_PATH', __DIR__ . '/db.sqlite');

/**
 * Conexão com o banco de dados SQLite
 */
function getDB(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('Erro ao conectar com o banco de dados: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Inicializa o banco de dados com as tabelas necessárias
 */
function initDB(): void {
    $pdo = getDB();
    
    // Tabela de pratos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pratos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome TEXT NOT NULL,
            quantidade_total INTEGER DEFAULT 0,
            quantidade_disponivel INTEGER DEFAULT 0,
            ativo INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabela de inscrições
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inscricoes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            nome TEXT NOT NULL,
            whatsapp TEXT,
            participa_jantar INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Tabela de acompanhantes
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS acompanhantes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            inscricao_id INTEGER NOT NULL,
            nome TEXT NOT NULL,
            FOREIGN KEY (inscricao_id) REFERENCES inscricoes(id) ON DELETE CASCADE
        )
    ");
    
    // Tabela pivot inscrição-pratos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS inscricao_pratos (
            inscricao_id INTEGER NOT NULL,
            prato_id INTEGER NOT NULL,
            PRIMARY KEY (inscricao_id, prato_id),
            FOREIGN KEY (inscricao_id) REFERENCES inscricoes(id) ON DELETE CASCADE,
            FOREIGN KEY (prato_id) REFERENCES pratos(id) ON DELETE CASCADE
        )
    ");
    
    // Inserir pratos padrão se não existirem
    $stmt = $pdo->query("SELECT COUNT(*) FROM pratos");
    if ($stmt->fetchColumn() == 0) {
        $pratos = [
            ['Arroz', 5],
            ['Macarrão', 5],
            ['Salada', 5],
            ['Sobremesa', 5],
            ['Carne', 5],
            ['Frango', 5],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO pratos (nome, quantidade_total, quantidade_disponivel) VALUES (?, ?, ?)");
        foreach ($pratos as $prato) {
            $stmt->execute([$prato[0], $prato[1], $prato[1]]);
        }
    }
}

/**
 * Buscar inscrição por email
 */
function buscarInscricaoPorEmail(string $email): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM inscricoes WHERE email = ?");
    $stmt->execute([$email]);
    $inscricao = $stmt->fetch();
    
    if ($inscricao) {
        // Buscar acompanhantes
        $stmt = $pdo->prepare("SELECT nome FROM acompanhantes WHERE inscricao_id = ?");
        $stmt->execute([$inscricao['id']]);
        $inscricao['acompanhantes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Buscar pratos escolhidos
        $stmt = $pdo->prepare("SELECT prato_id FROM inscricao_pratos WHERE inscricao_id = ?");
        $stmt->execute([$inscricao['id']]);
        $inscricao['pratos'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    return $inscricao ?: null;
}

/**
 * Buscar todos os pratos disponíveis
 */
function buscarPratos(): array {
    $pdo = getDB();
    $stmt = $pdo->query("
        SELECT p.*, 
               (SELECT COUNT(*) FROM inscricao_pratos WHERE prato_id = p.id) as escolhidos,
               (p.quantidade_total - (SELECT COUNT(*) FROM inscricao_pratos WHERE prato_id = p.id)) as restantes
        FROM pratos p 
        WHERE p.ativo = 1 
        ORDER BY nome
    ");
    return $stmt->fetchAll();
}

/**
 * Salvar nova inscrição
 */
function salvarInscricao(array $dados): int {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Inserir inscrição
        $stmt = $pdo->prepare("
            INSERT INTO inscricoes (email, nome, whatsapp, participa_jantar) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $dados['email'],
            $dados['nome'],
            $dados['whatsapp'] ?? '',
            $dados['participa_jantar'] ? 1 : 0
        ]);
        
        $inscricaoId = $pdo->lastInsertId();
        
        // Inserir acompanhantes
        if (!empty($dados['acompanhantes'])) {
            $stmt = $pdo->prepare("INSERT INTO acompanhantes (inscricao_id, nome) VALUES (?, ?)");
            foreach ($dados['acompanhantes'] as $nome) {
                if (trim($nome)) {
                    $stmt->execute([$inscricaoId, trim($nome)]);
                }
            }
        }
        
        // Inserir pratos e decrementar quantidade
        if (!empty($dados['pratos']) && $dados['participa_jantar']) {
            $stmtInsert = $pdo->prepare("INSERT INTO inscricao_pratos (inscricao_id, prato_id) VALUES (?, ?)");
            $stmtUpdate = $pdo->prepare("UPDATE pratos SET quantidade_disponivel = quantidade_disponivel - 1 WHERE id = ? AND quantidade_disponivel > 0");
            
            foreach ($dados['pratos'] as $pratoId) {
                $stmtInsert->execute([$inscricaoId, $pratoId]);
                $stmtUpdate->execute([$pratoId]);
            }
        }
        
        $pdo->commit();
        return $inscricaoId;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Atualizar inscrição existente
 */
function atualizarInscricao(int $inscricaoId, array $dados): bool {
    $pdo = getDB();
    
    try {
        $pdo->beginTransaction();
        
        // Restaurar quantidade dos pratos antigos
        $stmt = $pdo->prepare("
            UPDATE pratos SET quantidade_disponivel = quantidade_disponivel + 1 
            WHERE id IN (SELECT prato_id FROM inscricao_pratos WHERE inscricao_id = ?)
        ");
        $stmt->execute([$inscricaoId]);
        
        // Atualizar inscrição
        $stmt = $pdo->prepare("
            UPDATE inscricoes 
            SET nome = ?, whatsapp = ?, participa_jantar = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([
            $dados['nome'],
            $dados['whatsapp'] ?? '',
            $dados['participa_jantar'] ? 1 : 0,
            $inscricaoId
        ]);
        
        // Remover acompanhantes antigos
        $pdo->prepare("DELETE FROM acompanhantes WHERE inscricao_id = ?")->execute([$inscricaoId]);
        
        // Inserir novos acompanhantes
        if (!empty($dados['acompanhantes'])) {
            $stmt = $pdo->prepare("INSERT INTO acompanhantes (inscricao_id, nome) VALUES (?, ?)");
            foreach ($dados['acompanhantes'] as $nome) {
                if (trim($nome)) {
                    $stmt->execute([$inscricaoId, trim($nome)]);
                }
            }
        }
        
        // Remover pratos antigos
        $pdo->prepare("DELETE FROM inscricao_pratos WHERE inscricao_id = ?")->execute([$inscricaoId]);
        
        // Inserir novos pratos e decrementar quantidade
        if (!empty($dados['pratos']) && $dados['participa_jantar']) {
            $stmtInsert = $pdo->prepare("INSERT INTO inscricao_pratos (inscricao_id, prato_id) VALUES (?, ?)");
            $stmtUpdate = $pdo->prepare("UPDATE pratos SET quantidade_disponivel = quantidade_disponivel - 1 WHERE id = ? AND quantidade_disponivel > 0");
            
            foreach ($dados['pratos'] as $pratoId) {
                $stmtInsert->execute([$inscricaoId, $pratoId]);
                $stmtUpdate->execute([$pratoId]);
            }
        }
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// Inicializar banco na primeira execução
if (!file_exists(DB_PATH)) {
    initDB();
}

/**
 * Enviar email de confirmação
 */
function enviarEmailConfirmacao(array $inscricao, array $acompanhantes = [], array $pratosEscolhidos = []): bool {
    if (empty(MAIL_HOST) || empty(MAIL_PASSWORD)) {
        return false; // Email não configurado
    }
    
    $nome = $inscricao['nome'];
    $email = $inscricao['email'];
    $participaJantar = $inscricao['participa_jantar'];
    
    // Montar lista de pessoas
    $totalPessoas = 1 + count($acompanhantes);
    $listaPessoas = "• {$nome} (você)\n";
    foreach ($acompanhantes as $acomp) {
        $listaPessoas .= "• {$acomp}\n";
    }
    
    // Montar lista de pratos
    $listaPratos = '';
    if ($participaJantar && !empty($pratosEscolhidos)) {
        $listaPratos = "\n🍽️ PRATOS QUE VOCÊ VAI LEVAR:\n";
        foreach ($pratosEscolhidos as $prato) {
            $listaPratos .= "• {$prato}\n";
        }
    }
    
    // Montar mensagem
    $assunto = "✅ Presença Confirmada - " . EVENTO_NOME;
    
    $mensagemTexto = "
Olá, {$nome}!

Sua presença está confirmada para o {EVENTO_NOME}!

📅 DATA: 31 de Dezembro de 2025
⏰ HORÁRIO: 19h30
📍 LOCAL: " . EVENTO_LOCAL . "

👥 PESSOAS CONFIRMADAS ({$totalPessoas}):
{$listaPessoas}
{$listaPratos}
" . ($participaJantar ? "
⚠️ LEMBRETES IMPORTANTES:
• Traga a bebida de sua preferência
• Traga talheres para servir o prato que vai levar
• Chegue com antecedência para organização
" : "") . "

Estamos ansiosos para celebrar a virada do ano com você!

Com carinho,
Igreja Vivos com Cristo

---
Este é um email automático. Não responda.
Para alterar sua inscrição, acesse: https://eventos.vivos.site/inscricao
";

    // Versão HTML
    $mensagemHTML = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f5; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 500px; margin: 0 auto; background: #ffffff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 24px; }
        .logo { font-size: 24px; font-weight: bold; color: #333; }
        .logo span { color: #d4a800; }
        h1 { color: #d4a800; margin: 0 0 8px 0; font-size: 24px; }
        .subtitle { color: #666; font-size: 14px; }
        .card { background: #f8f9fa; border-radius: 12px; padding: 16px; margin: 16px 0; border: 1px solid #eee; }
        .card-title { color: #888; font-size: 10px; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
        .info-row { display: flex; align-items: center; margin: 8px 0; color: #333; }
        .icon { margin-right: 8px; }
        .badge { background: #fff3cd; color: #856404; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; border-radius: 12px; padding: 16px; margin: 16px 0; }
        .warning-title { color: #856404; font-weight: bold; font-size: 14px; margin-bottom: 8px; }
        .warning ul { margin: 0; padding-left: 20px; color: #856404; font-size: 13px; }
        .footer { text-align: center; color: #999; font-size: 11px; margin-top: 24px; }
        .footer a { color: #d4a800; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <div class='logo'>Igreja <span>Vivos com Cristo</span></div>
        </div>
        
        <div style='text-align: center; margin-bottom: 24px;'>
            <div style='width: 60px; height: 60px; background: #fff3cd; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 28px;'>✅</div>
            <h1>Presença Confirmada!</h1>
            <p class='subtitle'>Estamos ansiosos para celebrar com você!</p>
        </div>
        
        <div class='card'>
            <div class='card-title'>Evento</div>
            <div class='info-row'>📅 <strong style='margin-left: 8px;'>31 de Dezembro de 2025 • 19h30</strong></div>
            <div class='info-row'>📍 <span style='margin-left: 8px;'>" . EVENTO_LOCAL . "</span></div>
        </div>
        
        <div class='card'>
            <div class='card-title'>Pessoas Confirmadas</div>
            <div style='margin-bottom: 8px;'><span class='badge'>{$totalPessoas} pessoa" . ($totalPessoas > 1 ? 's' : '') . "</span></div>
            " . implode('', array_map(fn($p) => "<div class='info-row'>👤 <span style='margin-left: 8px;'>{$p}</span></div>", 
                array_merge([$nome . ' (você)'], $acompanhantes))) . "
        </div>
        
        " . ($participaJantar && !empty($pratosEscolhidos) ? "
        <div class='card'>
            <div class='card-title'>Pratos que você vai levar</div>
            " . implode('', array_map(fn($p) => "<div class='info-row'>🍽️ <span style='margin-left: 8px;'>{$p}</span></div>", $pratosEscolhidos)) . "
        </div>
        " : "") . "
        
        " . ($participaJantar ? "
        <div class='warning'>
            <div class='warning-title'>⚠️ Lembretes Importantes</div>
            <ul>
                <li>Traga a <strong>bebida de sua preferência</strong></li>
                <li>Traga <strong>talheres para servir</strong> o prato que vai levar</li>
                <li>Chegue com antecedência para organização</li>
            </ul>
        </div>
        " : "") . "
        
        <div class='footer'>
            <p>Este é um email automático.</p>
            <p><a href='https://eventos.vivos.site/inscricao'>Alterar inscrição</a></p>
            <p>© 2025 Igreja Vivos com Cristo</p>
        </div>
    </div>
</body>
</html>
";

    // Enviar usando SMTP nativo do PHP ou mail()
    return enviarEmailSMTP($email, $nome, $assunto, $mensagemTexto, $mensagemHTML);
}

/**
 * Enviar email via SMTP
 */
function enviarEmailSMTP(string $para, string $nome, string $assunto, string $textoPlano, string $html): bool {
    try {
        $porta = (int)MAIL_PORT;
        $usaSSL = ($porta === 465);
        
        // Conectar com ou sem SSL
        if ($usaSSL) {
            $socket = @stream_socket_client(
                "ssl://" . MAIL_HOST . ":" . MAIL_PORT,
                $errno, $errstr, 30,
                STREAM_CLIENT_CONNECT,
                stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]])
            );
        } else {
            $socket = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 30);
        }
        
        if (!$socket) {
            error_log("Erro SMTP: Não foi possível conectar - $errstr ($errno)");
            return false;
        }

        // Boundary para multipart
        $boundary = md5(time());
        
        // Headers
        $headers = [
            "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM_ADDRESS . ">",
            "Reply-To: " . MAIL_FROM_ADDRESS,
            "To: {$nome} <{$para}>",
            "Subject: =?UTF-8?B?" . base64_encode($assunto) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            "X-Mailer: PHP/" . phpversion()
        ];

        // Corpo
        $corpo = "--{$boundary}\r\n";
        $corpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $corpo .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $corpo .= chunk_split(base64_encode($textoPlano)) . "\r\n";
        $corpo .= "--{$boundary}\r\n";
        $corpo .= "Content-Type: text/html; charset=UTF-8\r\n";
        $corpo .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $corpo .= chunk_split(base64_encode($html)) . "\r\n";
        $corpo .= "--{$boundary}--";

        // Comandos SMTP
        $resposta = fgets($socket, 515);
        
        fputs($socket, "EHLO " . MAIL_HOST . "\r\n");
        $resposta = fgets($socket, 515);
        while (substr($resposta, 3, 1) == '-') $resposta = fgets($socket, 515);
        
        // STARTTLS apenas se não for SSL direto (porta 587)
        if (!$usaSSL) {
            fputs($socket, "STARTTLS\r\n");
            $resposta = fgets($socket, 515);
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            
            fputs($socket, "EHLO " . MAIL_HOST . "\r\n");
            $resposta = fgets($socket, 515);
            while (substr($resposta, 3, 1) == '-') $resposta = fgets($socket, 515);
        }
        
        fputs($socket, "AUTH LOGIN\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, base64_encode(MAIL_USERNAME) . "\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, base64_encode(MAIL_PASSWORD) . "\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, "MAIL FROM:<" . MAIL_FROM_ADDRESS . ">\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, "RCPT TO:<{$para}>\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, "DATA\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, implode("\r\n", $headers) . "\r\n\r\n" . $corpo . "\r\n.\r\n");
        $resposta = fgets($socket, 515);
        
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return str_starts_with($resposta, '250');
        
    } catch (Exception $e) {
        error_log("Erro ao enviar email: " . $e->getMessage());
        return false;
    }
}
