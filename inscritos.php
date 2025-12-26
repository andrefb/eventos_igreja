<?php
require_once 'config.php';

session_start();

// Senha para pastores (definida no .env como PASTOR_PASS)
define('PASTOR_PASS', getenv('PASTOR_PASS') ?: 'pastor123');

// Verificar login
$logado = isset($_SESSION['pastor_logado']) && $_SESSION['pastor_logado'] === true;

// Processar login/logout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['senha'])) {
        if ($_POST['senha'] === PASTOR_PASS) {
            $_SESSION['pastor_logado'] = true;
            $logado = true;
        } else {
            $erroLogin = 'Senha incorreta';
        }
    }
    
    if (isset($_POST['logout'])) {
        session_destroy();
        header('Location: inscritos');
        exit;
    }
}

// Buscar dados
if ($logado) {
    $pdo = getDB();
    
    // Total de pessoas (inscritos + acompanhantes)
    $totalPessoas = $pdo->query("SELECT COUNT(*) FROM inscricoes")->fetchColumn() + 
                    $pdo->query("SELECT COUNT(*) FROM acompanhantes")->fetchColumn();
    
    // Pessoas no jantar
    $pessoasJantar = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM inscricoes WHERE participa_jantar = 1) +
            (SELECT COUNT(*) FROM acompanhantes a 
             JOIN inscricoes i ON a.inscricao_id = i.id 
             WHERE i.participa_jantar = 1)
    ")->fetchColumn();
    
    // Total de pratos escolhidos
    $totalPratos = $pdo->query("SELECT COUNT(*) FROM inscricao_pratos")->fetchColumn();
    
    // Inscrições com acompanhantes e pratos
    $inscricoes = $pdo->query("
        SELECT i.*, 
               (SELECT GROUP_CONCAT(nome, ', ') FROM acompanhantes WHERE inscricao_id = i.id) as nomes_acompanhantes,
               (SELECT GROUP_CONCAT(p.nome, ', ') FROM inscricao_pratos ip 
                JOIN pratos p ON ip.prato_id = p.id WHERE ip.inscricao_id = i.id) as pratos_escolhidos
        FROM inscricoes i 
        ORDER BY i.created_at ASC
    ")->fetchAll();
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Inscritos - <?= EVENTO_NOME ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#ffd900",
                        "background-dark": "#0f1014",
                        "surface-dark": "#1a1c23",
                        "text-secondary": "#9ca3af",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
</head>
<body class="bg-background-dark font-display text-white min-h-screen">

<?php if (!$logado): ?>
    <!-- Tela de Login -->
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <div class="text-center mb-8">
                <img src="logo_branco.png" alt="Logo" class="h-12 mx-auto mb-4">
                <h1 class="text-2xl font-bold">Lista de Inscritos</h1>
                <p class="text-text-secondary text-sm"><?= EVENTO_NOME ?></p>
            </div>
            
            <?php if (isset($erroLogin)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-4 text-sm text-center">
                <?= $erroLogin ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Senha de Acesso</label>
                    <input type="password" name="senha" required autofocus
                        class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary text-center text-lg tracking-widest"
                        placeholder="••••••••">
                </div>
                <button type="submit"
                    class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold rounded-xl py-3 transition-colors">
                    Acessar
                </button>
            </form>
            
            <p class="text-center text-xs text-gray-600 mt-6">
                <a href="index" class="text-primary hover:underline">← Voltar ao site</a>
            </p>
        </div>
    </div>

<?php else: ?>
    <!-- Header -->
    <header class="bg-surface-dark border-b border-white/5 sticky top-0 z-50">
        <div class="max-w-2xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="logo_branco.png" alt="Logo" class="h-8">
                <div>
                    <h1 class="font-bold text-lg">Inscritos</h1>
                    <p class="text-xs text-text-secondary"><?= EVENTO_NOME ?></p>
                </div>
            </div>
            <form method="POST" class="inline">
                <input type="hidden" name="logout" value="1">
                <button type="submit" class="text-red-400 hover:text-red-300 text-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">logout</span>
                    Sair
                </button>
            </form>
        </div>
    </header>

    <!-- Conteúdo -->
    <main class="max-w-2xl mx-auto px-4 py-6">
        <!-- Cards de estatísticas -->
        <div class="grid grid-cols-3 gap-3 mb-6">
            <div class="bg-surface-dark rounded-xl p-4 border border-white/5 text-center">
                <p class="text-2xl font-bold text-primary"><?= $totalPessoas ?></p>
                <p class="text-xs text-text-secondary">Total Pessoas</p>
            </div>
            <div class="bg-surface-dark rounded-xl p-4 border border-white/5 text-center">
                <p class="text-2xl font-bold text-primary"><?= $pessoasJantar ?></p>
                <p class="text-xs text-text-secondary">No Jantar</p>
            </div>
            <div class="bg-surface-dark rounded-xl p-4 border border-white/5 text-center">
                <p class="text-2xl font-bold text-primary"><?= $totalPratos ?></p>
                <p class="text-xs text-text-secondary">Pratos</p>
            </div>
        </div>

        <!-- Filtros -->
        <div class="flex gap-2 mb-4">
            <button onclick="filtrar('todos')" id="btn-todos" class="px-4 py-2 rounded-xl text-sm font-medium bg-primary text-background-dark">
                Todos
            </button>
            <button onclick="filtrar('jantar')" id="btn-jantar" class="px-4 py-2 rounded-xl text-sm font-medium bg-surface-dark text-white hover:bg-white/10">
                Jantar
            </button>
            <button onclick="filtrar('culto')" id="btn-culto" class="px-4 py-2 rounded-xl text-sm font-medium bg-surface-dark text-white hover:bg-white/10">
                Só Culto
            </button>
        </div>

        <!-- Lista de Inscritos -->
        <div class="space-y-3" id="lista-inscritos">
            <?php if (empty($inscricoes)): ?>
            <div class="bg-surface-dark rounded-xl p-8 text-center text-text-secondary border border-white/5">
                <span class="material-symbols-outlined text-4xl mb-2">inbox</span>
                <p>Nenhuma inscrição ainda</p>
            </div>
            <?php else: ?>
            <?php foreach ($inscricoes as $insc): ?>
            <div class="rounded-xl p-4 border <?= $insc['participa_jantar'] ? 'bg-surface-dark border-white/5' : 'bg-blue-950/30 border-blue-500/20' ?>" data-tipo="<?= $insc['participa_jantar'] ? 'jantar' : 'culto' ?>">
                <div class="flex justify-between items-start gap-3">
                    <div class="flex-1">
                        <p class="font-bold text-white"><?= htmlspecialchars($insc['nome']) ?></p>
                        <?php if ($insc['nomes_acompanhantes']): ?>
                        <p class="text-sm text-text-secondary mt-1"><?= htmlspecialchars($insc['nomes_acompanhantes']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($insc['participa_jantar'] && $insc['pratos_escolhidos']): ?>
                    <div class="text-right shrink-0">
                        <span class="text-xs text-primary bg-primary/10 px-2 py-1 rounded">
                            <?= htmlspecialchars($insc['pratos_escolhidos']) ?>
                        </span>
                    </div>
                    <?php elseif (!$insc['participa_jantar']): ?>
                    <div class="text-right shrink-0">
                        <span class="text-xs text-blue-400 bg-blue-500/20 px-2 py-1 rounded">Só culto</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        function filtrar(tipo) {
            const items = document.querySelectorAll('[data-tipo]');
            const btns = document.querySelectorAll('[id^="btn-"]');
            
            // Reset botões
            btns.forEach(btn => {
                btn.classList.remove('bg-primary', 'text-background-dark');
                btn.classList.add('bg-surface-dark', 'text-white');
            });
            
            // Ativar botão clicado
            document.getElementById('btn-' + tipo).classList.remove('bg-surface-dark', 'text-white');
            document.getElementById('btn-' + tipo).classList.add('bg-primary', 'text-background-dark');
            
            // Filtrar lista
            items.forEach(item => {
                if (tipo === 'todos' || item.dataset.tipo === tipo) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    </script>
<?php endif; ?>

</body>
</html>
