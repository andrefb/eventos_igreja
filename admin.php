<?php
require_once 'config.php';

// Garantir que as tabelas existam (importante para a tabela de configurações)
initDB();

session_start();

// Verificar login
$logado = isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true;

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao'])) {
    if ($_POST['acao'] === 'login') {
        $senhaCorreta = ($_POST['senha'] ?? '') === ADMIN_PASS_ENV;
        
        if ($_POST['usuario'] === ADMIN_USER && $senhaCorreta) {
            $_SESSION['admin_logado'] = true;
            $logado = true;
        } else {
            $erroLogin = 'Usuário ou senha incorretos';
        }
    }
    
    if ($_POST['acao'] === 'logout') {
        session_destroy();
        header('Location: admin.php');
        exit;
    }
    
    if ($_POST['acao'] === 'excluir_inscricao' && $logado) {
        $pdo = getDB();
        $id = (int)$_POST['id'];
        
        // Restaurar quantidade dos pratos
        $pdo->exec("UPDATE pratos SET quantidade_disponivel = quantidade_disponivel + 1 
                    WHERE id IN (SELECT prato_id FROM inscricao_pratos WHERE inscricao_id = $id)");
        
        // Excluir inscrição
        $pdo->exec("DELETE FROM inscricao_pratos WHERE inscricao_id = $id");
        $pdo->exec("DELETE FROM acompanhantes WHERE inscricao_id = $id");
        $pdo->exec("DELETE FROM inscricoes WHERE id = $id");
        
        header('Location: admin.php?msg=excluido');
        exit;
    }
    
    if ($_POST['acao'] === 'salvar_prato' && $logado) {
        $pdo = getDB();
        $nome = htmlspecialchars($_POST['nome'] ?? '');
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $id = (int)($_POST['id'] ?? 0);
        
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE pratos SET nome = ?, quantidade_total = ?, quantidade_disponivel = ? WHERE id = ?");
            $stmt->execute([$nome, $quantidade, $quantidade, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO pratos (nome, quantidade_total, quantidade_disponivel) VALUES (?, ?, ?)");
            $stmt->execute([$nome, $quantidade, $quantidade]);
        }
        
        header('Location: admin.php?tab=pratos&msg=salvo');
        exit;
    }
    
    if ($_POST['acao'] === 'excluir_prato' && $logado) {
        $pdo = getDB();
        $id = (int)$_POST['id'];
        $pdo->exec("DELETE FROM pratos WHERE id = $id");
        
        header('Location: admin?tab=pratos&msg=excluido');
        exit;
    }
    
    if ($_POST['acao'] === 'ajustar_prato' && $logado) {
        $pdo = getDB();
        $id = (int)$_POST['id'];
        $ajuste = (int)$_POST['ajuste']; // +1 ou -1
        
        if ($ajuste > 0) {
            // Aumentar total (mais vagas disponíveis)
            $pdo->exec("UPDATE pratos SET quantidade_total = quantidade_total + 1 WHERE id = $id");
        } else {
            // Diminuir total (menos vagas, mínimo = quantidade já escolhida)
            $pdo->exec("UPDATE pratos SET quantidade_total = CASE 
                WHEN quantidade_total > (SELECT COUNT(*) FROM inscricao_pratos WHERE prato_id = $id) 
                THEN quantidade_total - 1 
                ELSE quantidade_total END 
                WHERE id = $id");
        }
        
        header('Location: admin?tab=pratos');
        exit;
    }
    
    if ($_POST['acao'] === 'salvar_data_limite' && $logado) {
        $data = $_POST['data'] ?? '';
        $hora = $_POST['hora'] ?? '09:00';
        $dataHora = $data . ' ' . $hora . ':00';
        
        setDataLimite($dataHora);
        
        header('Location: admin?tab=config&msg=salvo');
        exit;
    }
}

// Buscar dados
if ($logado) {
    $pdo = getDB();
    
    // Total de inscritos
    $totalInscritos = $pdo->query("SELECT COUNT(*) FROM inscricoes")->fetchColumn();
    
    // Total de pessoas (incluindo acompanhantes)
    $totalPessoas = $pdo->query("SELECT COUNT(*) FROM inscricoes")->fetchColumn() + 
                    $pdo->query("SELECT COUNT(*) FROM acompanhantes")->fetchColumn();
    
    // Participando do jantar
    $participandoJantar = $pdo->query("SELECT COUNT(*) FROM inscricoes WHERE participa_jantar = 1")->fetchColumn();
    
    // Inscrições
    $inscricoes = $pdo->query("
        SELECT i.*, 
               (SELECT COUNT(*) FROM acompanhantes WHERE inscricao_id = i.id) as total_acompanhantes,
               (SELECT GROUP_CONCAT(nome, ', ') FROM acompanhantes WHERE inscricao_id = i.id) as nomes_acompanhantes,
               (SELECT GROUP_CONCAT(p.nome, ', ') FROM inscricao_pratos ip 
                JOIN pratos p ON ip.prato_id = p.id WHERE ip.inscricao_id = i.id) as pratos_escolhidos
        FROM inscricoes i 
        ORDER BY i.created_at DESC
    ")->fetchAll();
    
    // Pratos (com contagem de escolhidos calculada dinamicamente)
    $pratos = $pdo->query("
        SELECT p.*, 
               (SELECT COUNT(*) FROM inscricao_pratos WHERE prato_id = p.id) as escolhidos
        FROM pratos p 
        ORDER BY nome
    ")->fetchAll();
}

$tab = $_GET['tab'] ?? 'inscricoes';
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Admin - <?= EVENTO_NOME ?></title>
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
                <h1 class="text-2xl font-bold">Painel Admin</h1>
                <p class="text-text-secondary text-sm"><?= EVENTO_NOME ?></p>
            </div>
            
            <?php if (isset($erroLogin)): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-xl mb-4 text-sm">
                <?= $erroLogin ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="acao" value="login">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Usuário</label>
                    <input type="text" name="usuario" required
                        class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="admin">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Senha</label>
                    <input type="password" name="senha" required
                        class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary"
                        placeholder="••••••••">
                </div>
                <button type="submit"
                    class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold rounded-xl py-3 transition-colors">
                    Entrar
                </button>
            </form>
            
            <p class="text-center text-xs text-gray-600 mt-6">
                <a href="index" class="text-primary hover:underline">← Voltar ao site</a>
            </p>
        </div>
    </div>

<?php else: ?>
    <!-- Header Admin -->
    <header class="bg-surface-dark border-b border-white/5 sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="logo_branco.png" alt="Logo" class="h-8">
                <div>
                    <h1 class="font-bold text-lg">Painel Admin</h1>
                    <p class="text-xs text-text-secondary"><?= EVENTO_NOME ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="index" class="text-text-secondary hover:text-white text-sm flex items-center gap-1">
                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                    Ver site
                </a>
                <form method="POST" class="inline">
                    <input type="hidden" name="acao" value="logout">
                    <button type="submit" class="text-red-400 hover:text-red-300 text-sm flex items-center gap-1">
                        <span class="material-symbols-outlined text-[18px]">logout</span>
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Conteúdo -->
    <main class="max-w-6xl mx-auto px-4 py-8">
        <!-- Cards de estatísticas -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <div class="bg-surface-dark rounded-2xl p-6 border border-white/5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-primary">groups</span>
                    <span class="text-text-secondary text-sm">Inscrições</span>
                </div>
                <p class="text-3xl font-bold"><?= $totalInscritos ?></p>
            </div>
            <div class="bg-surface-dark rounded-2xl p-6 border border-white/5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-primary">person</span>
                    <span class="text-text-secondary text-sm">Total de Pessoas</span>
                </div>
                <p class="text-3xl font-bold"><?= $totalPessoas ?></p>
            </div>
            <div class="bg-surface-dark rounded-2xl p-6 border border-white/5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="material-symbols-outlined text-primary">restaurant</span>
                    <span class="text-text-secondary text-sm">No Jantar</span>
                </div>
                <p class="text-3xl font-bold"><?= $participandoJantar ?></p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6">
            <a href="?tab=inscricoes" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-colors <?= $tab === 'inscricoes' ? 'bg-primary text-background-dark' : 'bg-surface-dark text-white hover:bg-white/10' ?>">
                Inscrições
            </a>
            <a href="?tab=pratos" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-colors <?= $tab === 'pratos' ? 'bg-primary text-background-dark' : 'bg-surface-dark text-white hover:bg-white/10' ?>">
                Pratos
            </a>
            <a href="?tab=config" 
                class="px-4 py-2 rounded-xl text-sm font-medium transition-colors <?= $tab === 'config' ? 'bg-primary text-background-dark' : 'bg-surface-dark text-white hover:bg-white/10' ?>">
                ⚙️ Configurações
            </a>
        </div>

        <?php if ($tab === 'inscricoes'): ?>
        <!-- Lista de Inscrições -->
        <div class="bg-surface-dark rounded-2xl border border-white/5 overflow-hidden">
            <?php if (empty($inscricoes)): ?>
            <div class="p-8 text-center text-text-secondary">
                <span class="material-symbols-outlined text-4xl mb-2">inbox</span>
                <p>Nenhuma inscrição ainda</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white/5">
                        <tr>
                            <th class="text-left px-4 py-3 text-text-secondary font-medium">Nome</th>
                            <th class="text-left px-4 py-3 text-text-secondary font-medium">WhatsApp</th>
                            <th class="text-left px-4 py-3 text-text-secondary font-medium">Pessoas</th>
                            <th class="text-left px-4 py-3 text-text-secondary font-medium">Jantar</th>
                            <th class="text-left px-4 py-3 text-text-secondary font-medium">Pratos</th>
                            <th class="text-right px-4 py-3 text-text-secondary font-medium">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <?php foreach ($inscricoes as $insc): ?>
                        <tr class="hover:bg-white/5">
                            <td class="px-4 py-3">
                                <div class="font-medium"><?= htmlspecialchars($insc['nome']) ?></div>
                                <div class="text-xs text-text-secondary"><?= htmlspecialchars($insc['email']) ?></div>
                                <?php if ($insc['nomes_acompanhantes']): ?>
                                <div class="text-xs text-primary mt-1">+ <?= htmlspecialchars($insc['nomes_acompanhantes']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-text-secondary"><?= htmlspecialchars($insc['whatsapp']) ?></td>
                            <td class="px-4 py-3">
                                <span class="bg-primary/20 text-primary px-2 py-1 rounded text-xs font-medium">
                                    <?= 1 + $insc['total_acompanhantes'] ?>
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($insc['participa_jantar']): ?>
                                <span class="text-green-400">✓ Sim</span>
                                <?php else: ?>
                                <span class="text-text-secondary">Não</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-text-secondary text-xs max-w-[150px]">
                                <div class="truncate"><?= htmlspecialchars($insc['pratos_escolhidos'] ?? '-') ?></div>
                                <div class="text-sky-400/70 text-[10px] mt-0.5"><?= 1 + $insc['total_acompanhantes'] ?> pessoa<?= (1 + $insc['total_acompanhantes']) > 1 ? 's' : '' ?></div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" class="inline" onsubmit="return confirm('Excluir inscrição de <?= htmlspecialchars($insc['nome']) ?>?')">
                                    <input type="hidden" name="acao" value="excluir_inscricao">
                                    <input type="hidden" name="id" value="<?= $insc['id'] ?>">
                                    <button type="submit" class="text-red-400 hover:text-red-300">
                                        <span class="material-symbols-outlined text-[20px]">delete</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <?php elseif ($tab === 'pratos'): ?>
        <!-- Gerenciar Pratos -->
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Formulário -->
            <div class="bg-surface-dark rounded-2xl p-6 border border-white/5">
                <h2 class="font-bold mb-4">Adicionar Prato</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="acao" value="salvar_prato">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Nome do Prato</label>
                        <input type="text" name="nome" required
                            class="w-full bg-background-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary"
                            placeholder="Ex: Lasanha">
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Quantidade Disponível</label>
                        <input type="number" name="quantidade" min="0" value="5" required
                            class="w-full bg-background-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary">
                    </div>
                    <button type="submit"
                        class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold rounded-xl py-3 transition-colors">
                        Adicionar Prato
                    </button>
                </form>
            </div>
            
            <!-- Lista de Pratos -->
            <div class="bg-surface-dark rounded-2xl border border-white/5 overflow-hidden">
                <div class="p-4 border-b border-white/5">
                    <h2 class="font-bold">Pratos Cadastrados</h2>
                </div>
                <?php if (empty($pratos)): ?>
                <div class="p-8 text-center text-text-secondary">
                    <p>Nenhum prato cadastrado</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-white/5">
                    <?php foreach ($pratos as $prato): ?>
                    <div class="flex items-center justify-between p-4 hover:bg-white/5">
                        <div>
                            <div class="font-medium"><?= htmlspecialchars($prato['nome']) ?></div>
                            <div class="text-xs text-text-secondary">
                                <?= $prato['escolhidos'] ?> escolhidos • <?= $prato['quantidade_total'] - $prato['escolhidos'] ?> restantes (de <?= $prato['quantidade_total'] ?>)
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- Botões +/- -->
                            <div class="flex items-center gap-1 bg-white/5 rounded-lg p-1">
                                <form method="POST" class="inline">
                                    <input type="hidden" name="acao" value="ajustar_prato">
                                    <input type="hidden" name="id" value="<?= $prato['id'] ?>">
                                    <input type="hidden" name="ajuste" value="-1">
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center rounded bg-red-500/20 text-red-400 hover:bg-red-500/30 text-lg font-bold">
                                        −
                                    </button>
                                </form>
                                <span class="w-8 text-center font-bold text-white"><?= $prato['quantidade_total'] ?></span>
                                <form method="POST" class="inline">
                                    <input type="hidden" name="acao" value="ajustar_prato">
                                    <input type="hidden" name="id" value="<?= $prato['id'] ?>">
                                    <input type="hidden" name="ajuste" value="1">
                                    <button type="submit" class="w-7 h-7 flex items-center justify-center rounded bg-green-500/20 text-green-400 hover:bg-green-500/30 text-lg font-bold">
                                        +
                                    </button>
                                </form>
                            </div>
                            <?php $restantes = $prato['quantidade_total'] - $prato['escolhidos']; ?>
                            <span class="<?= $restantes > 0 ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' ?> px-2 py-1 rounded text-xs">
                                <?= $restantes > 0 ? 'Disponível' : 'Esgotado' ?>
                            </span>
                            <?php if ($prato['escolhidos'] == 0): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Excluir prato?')">
                                <input type="hidden" name="acao" value="excluir_prato">
                                <input type="hidden" name="id" value="<?= $prato['id'] ?>">
                                <button type="submit" class="text-red-400 hover:text-red-300 p-1">
                                    <span class="material-symbols-outlined text-[18px]">delete</span>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($tab === 'config'): ?>
        <!-- Configurações -->
        <?php 
        $dataLimiteAtual = getDataLimite();
        $dataLimiteParts = $dataLimiteAtual ? explode(' ', $dataLimiteAtual) : ['2025-12-30', '09:00:00'];
        $dataAtual = $dataLimiteParts[0];
        $horaAtual = substr($dataLimiteParts[1] ?? '09:00:00', 0, 5);
        $inscricoesAbertasAgora = inscricoesAbertas();
        ?>
        <div class="max-w-md">
            <div class="bg-surface-dark rounded-2xl p-6 border border-white/5 mb-6">
                <div class="flex items-center gap-3 mb-4">
                    <span class="material-symbols-outlined text-primary">schedule</span>
                    <h2 class="font-bold text-lg">Data Limite de Inscrições</h2>
                </div>
                
                <!-- Status atual -->
                <div class="mb-6 p-4 rounded-xl <?= $inscricoesAbertasAgora ? 'bg-green-500/10 border border-green-500/20' : 'bg-red-500/10 border border-red-500/20' ?>">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined <?= $inscricoesAbertasAgora ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $inscricoesAbertasAgora ? 'lock_open' : 'lock' ?>
                        </span>
                        <span class="font-bold <?= $inscricoesAbertasAgora ? 'text-green-400' : 'text-red-400' ?>">
                            <?= $inscricoesAbertasAgora ? 'Inscrições ABERTAS' : 'Inscrições FECHADAS' ?>
                        </span>
                    </div>
                    <p class="text-xs text-text-secondary mt-2">
                        Limite atual: <strong class="text-white"><?= date('d/m/Y H:i', strtotime($dataLimiteAtual)) ?></strong>
                    </p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="acao" value="salvar_data_limite">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Data</label>
                            <input type="date" name="data" value="<?= $dataAtual ?>" required
                                class="w-full bg-background-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider text-text-secondary mb-2">Hora</label>
                            <input type="time" name="hora" value="<?= $horaAtual ?>" required
                                class="w-full bg-background-dark border border-white/10 rounded-xl px-4 py-3 text-white focus:border-primary focus:ring-1 focus:ring-primary">
                        </div>
                    </div>
                    
                    <p class="text-xs text-text-secondary">
                        Após essa data/hora, as inscrições serão bloqueadas automaticamente.
                    </p>
                    
                    <button type="submit"
                        class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold rounded-xl py-3 transition-colors">
                        Salvar Data Limite
                    </button>
                </form>
            </div>
            
            <!-- Dicas rápidas -->
            <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4">
                <p class="text-xs text-blue-400 font-medium mb-2">💡 Dica</p>
                <p class="text-xs text-text-secondary">
                    Se os pastores pedirem mais tempo, basta alterar a data/hora aqui e as inscrições voltam a funcionar automaticamente!
                </p>
            </div>
        </div>
        <?php endif; ?>
    </main>
<?php endif; ?>

</body>
</html>
