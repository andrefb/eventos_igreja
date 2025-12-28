<?php
require_once 'config.php';

// Inicializar banco se necessário
if (!file_exists(DB_PATH)) {
    initDB();
}

// Verificar se inscrições ainda estão abertas
if (!inscricoesAbertas()) {
    // Se tentar acessar via AJAX, retornar erro
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['sucesso' => false, 'erro' => 'Inscrições encerradas']);
        exit;
    }
    // Se acessar normalmente, redirecionar
    header('Location: /?inscricoes=fechadas');
    exit;
}

// Buscar pratos do banco
$pratos = buscarPratos();

// Processar verificação de email (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'verificar_email') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $inscricao = buscarInscricaoPorEmail($email);
        
        echo json_encode([
            'existe' => $inscricao !== null,
            'dados' => $inscricao
        ]);
        exit;
    }
    
    if ($_POST['action'] === 'salvar') {
        try {
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $inscricaoExistente = buscarInscricaoPorEmail($email);
            
            $dados = [
                'email' => $email,
                'nome' => htmlspecialchars($_POST['nome'] ?? ''),
                'whatsapp' => htmlspecialchars($_POST['whatsapp'] ?? ''),
                'participa_jantar' => ($_POST['jantar'] ?? '') === 'sim',
                'acompanhantes' => $_POST['acompanhantes'] ?? [],
                'pratos' => isset($_POST['prato']) ? [$_POST['prato']] : []
            ];
            
            if ($inscricaoExistente) {
                atualizarInscricao($inscricaoExistente['id'], $dados);
                $id = $inscricaoExistente['id'];
            } else {
                $id = salvarInscricao($dados);
            }
            
            // Retornar sucesso SEM enviar email ainda
            echo json_encode(['sucesso' => true, 'id' => $id]);
        } catch (Exception $e) {
            echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]);
        }
        exit;
    }
    
    // Enviar email em chamada separada
    if ($_POST['action'] === 'enviar_email') {
        $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $inscricao = buscarInscricaoPorEmail($email);
        
        if ($inscricao) {
            $acompanhantes = $inscricao['acompanhantes'] ?? [];
            $pratosNomes = [];
            
            if ($inscricao['participa_jantar'] && !empty($inscricao['pratos'])) {
                $pdo = getDB();
                // Buscar nomes de todos os pratos selecionados
                $pratoIds = $inscricao['pratos'];
                $placeholders = implode(',', array_fill(0, count($pratoIds), '?'));
                $stmt = $pdo->prepare("SELECT nome FROM pratos WHERE id IN ($placeholders)");
                $stmt->execute($pratoIds);
                $pratosNomes = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            $inscricaoEmail = [
                'nome' => $inscricao['nome'],
                'email' => $inscricao['email'],
                'participa_jantar' => $inscricao['participa_jantar']
            ];
            
            $emailEnviado = enviarEmailConfirmacao($inscricaoEmail, $acompanhantes, $pratosNomes);
            echo json_encode(['email_enviado' => $emailEnviado]);
        } else {
            echo json_encode(['email_enviado' => false]);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Inscrição - <?= EVENTO_NOME ?></title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap"
        rel="stylesheet" />
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
    <style>
        body { min-height: 100dvh; }
        .step { display: none; }
        .step.active { display: block; }
        .loading { opacity: 0.5; pointer-events: none; }
        /* Radio button checked state */
        input[type="radio"]:checked + div { border-color: #ffd900 !important; background-color: rgba(255, 217, 0, 0.1) !important; }
        input[type="radio"]:checked + div .radio-circle { border-color: #ffd900 !important; }
        input[type="radio"]:checked + div .radio-dot { transform: scale(1) !important; }
    </style>
</head>

<body class="bg-background-dark font-display text-white selection:bg-primary selection:text-black antialiased">

    <!-- Header -->
    <div class="fixed top-0 z-50 w-full bg-background-dark/80 backdrop-blur-md border-b border-white/5">
        <div class="flex items-center justify-between p-4 h-16 max-w-md mx-auto">
            <a href="index" class="flex items-center gap-2 text-gray-400 hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                <span class="text-sm">Voltar</span>
            </a>
            <img src="logo_branco.png" alt="Vivos com Cristo" class="h-6 w-auto">
        </div>
    </div>

    <!-- Container Principal -->
    <main class="relative flex flex-col w-full min-h-screen pt-20 pb-8 px-4 max-w-md mx-auto">

        <!-- STEP 1: Email -->
        <div id="step-email" class="step active">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black mb-2">
                    <span class="text-white">Confirme sua</span>
                    <span class="text-primary"> Presença</span>
                </h1>
                <p class="text-text-secondary text-sm">Digite seu email para começar</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-text-secondary mb-2">Seu Email</label>
                    <div class="relative">
                        <input type="email" id="input-email"
                            class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-4 text-white placeholder-gray-500 focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                            placeholder="seu@email.com">
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">mail</span>
                    </div>
                </div>

                <button onclick="verificarEmail()"
                    class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold text-lg rounded-full h-14 flex items-center justify-center gap-2 transition-colors mt-6">
                    <span id="btn-continuar-text">Continuar</span>
                    <span class="material-symbols-outlined">arrow_forward</span>
                </button>
            </div>

            <p class="text-center text-[11px] text-gray-600 mt-6">
                Se já se inscreveu, seus dados aparecerão para edição
            </p>
        </div>

        <!-- STEP 2: Formulário de Inscrição -->
        <div id="step-form" class="step">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-black mb-2">
                    <span class="text-white">Confirme sua</span>
                    <span class="text-primary"> Presença</span>
                </h1>
                <p class="text-text-secondary text-sm">Queremos nos preparar para receber você!</p>
            </div>

            <!-- Aviso de edição -->
            <div id="aviso-edicao" class="hidden mb-4 bg-primary/10 rounded-xl p-4 border border-primary/20 flex gap-3 items-start">
                <span class="material-symbols-outlined text-primary text-[20px] shrink-0 mt-0.5">edit</span>
                <p class="text-xs text-primary/90 leading-relaxed">
                    <span class="font-bold">Edição:</span> Você já está inscrito. Atualize seus dados abaixo.
                </p>
            </div>

            <form id="form-inscricao" class="space-y-5">
                <input type="hidden" id="input-email-hidden" name="email">
                
                <!-- Nome -->
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-text-secondary mb-2">Seu Nome</label>
                    <div class="relative">
                        <input type="text" id="input-nome" name="nome" required
                            class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-4 text-white placeholder-gray-500 focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                            placeholder="Seu nome">
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">person</span>
                    </div>
                </div>

                <!-- WhatsApp -->
                <div>
                    <label class="block text-[10px] uppercase tracking-wider text-text-secondary mb-2">WhatsApp</label>
                    <div class="relative">
                        <input type="tel" id="input-whatsapp" name="whatsapp" required
                            class="w-full bg-surface-dark border border-white/10 rounded-xl px-4 py-4 text-white placeholder-gray-500 focus:border-primary focus:ring-1 focus:ring-primary transition-colors"
                            placeholder="(00) 00000-0000">
                        <span class="material-symbols-outlined absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">phone</span>
                    </div>
                </div>

                <!-- Acompanhantes -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-white mb-2">👥 Quem vai com você?</label>
                    <div id="lista-acompanhantes" class="space-y-2">
                        <!-- Acompanhantes serão adicionados aqui -->
                    </div>
                    <button type="button" onclick="adicionarAcompanhante()"
                        class="w-full mt-2 border border-dashed border-white/20 hover:border-primary/50 text-text-secondary hover:text-primary rounded-xl py-3 flex items-center justify-center gap-2 transition-colors">
                        <span class="material-symbols-outlined text-[20px]">add</span>
                        <span class="text-sm">Adicionar pessoa</span>
                    </button>
                </div>

                <!-- Opção Jantar -->
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-white mb-3">🍽️ Vai participar do jantar?</label>
                    <div class="space-y-2">
                        <label class="block cursor-pointer group">
                            <input type="radio" name="jantar" value="sim" class="sr-only peer" onchange="togglePratos(true)">
                            <div class="bg-surface-dark border border-white/10 peer-checked:border-primary peer-checked:bg-primary/10 rounded-xl p-4 flex items-start gap-3 transition-colors">
                                <div class="w-5 h-5 rounded-full border-2 border-white/30 peer-checked:border-primary flex items-center justify-center mt-0.5 radio-circle">
                                    <div class="w-2.5 h-2.5 rounded-full bg-primary scale-0 transition-transform radio-dot"></div>
                                </div>
                                <div>
                                    <span class="text-white font-semibold">Sim, vou levar um prato 🍽️</span>
                                    <p class="text-text-secondary text-xs mt-0.5">E também uma bebida!</p>
                                </div>
                            </div>
                        </label>
                        <label class="block cursor-pointer group">
                            <input type="radio" name="jantar" value="nao" class="sr-only peer" onchange="togglePratos(false)">
                            <div class="bg-surface-dark border border-white/10 peer-checked:border-primary peer-checked:bg-primary/10 rounded-xl p-4 flex items-start gap-3 transition-colors">
                                <div class="w-5 h-5 rounded-full border-2 border-white/30 peer-checked:border-primary flex items-center justify-center mt-0.5 radio-circle">
                                    <div class="w-2.5 h-2.5 rounded-full bg-primary scale-0 transition-transform radio-dot"></div>
                                </div>
                                <div>
                                    <span class="text-white font-semibold">Não, só vou ao culto 🙏</span>
                                    <p class="text-text-secondary text-xs mt-0.5">Participação apenas na celebração</p>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Lista de Pratos (aparece quando seleciona "Sim") -->
                <div id="secao-pratos" class="hidden">
                    <label class="block text-xs font-bold uppercase tracking-wider text-white mb-3">🍲 Qual prato vai levar?</label>
                    <div class="space-y-2" id="lista-pratos">
                        <?php foreach ($pratos as $prato): ?>
                        <?php if (strtolower($prato['nome']) === 'sobremesa') continue; ?>
                        <label class="flex items-center gap-3 bg-surface-dark border border-white/10 rounded-xl p-4 cursor-pointer hover:border-white/20 transition-colors <?= $prato['restantes'] <= 0 ? 'opacity-50' : '' ?>">
                            <input type="radio" name="prato" value="<?= $prato['id'] ?>" 
                                <?= $prato['restantes'] <= 0 ? 'disabled' : '' ?>
                                class="w-5 h-5 bg-surface-dark border-white/30 text-primary focus:ring-primary focus:ring-offset-0">
                            <div class="flex-1">
                                <span class="text-white"><?= htmlspecialchars($prato['nome']) ?></span>
                                <?php if ($prato['restantes'] <= 0): ?>
                                <span class="text-xs text-red-400 ml-2">(esgotado)</span>
                                <?php endif; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Aviso IMPORTANTE sobre bebida -->
                    <div class="mt-4 bg-sky-500/20 rounded-xl p-5 border-2 border-sky-400/60">
                        <div class="flex gap-3 items-center">
                            <span class="material-symbols-outlined text-sky-400 text-[28px] shrink-0">local_bar</span>
                            <p class="text-base text-white font-bold">
                                Leve também a bebida!
                            </p>
                        </div>
                    </div>
                    
                    <!-- Aviso sobre talheres -->
                    <div class="mt-3 bg-primary/10 rounded-xl p-4 border border-primary/20">
                        <div class="flex gap-3 items-start">
                            <span class="material-symbols-outlined text-primary text-[20px] shrink-0 mt-0.5">restaurant</span>
                            <p class="text-xs text-primary/90 leading-relaxed">
                                <span class="font-bold">Talheres:</span> Leve talheres para servir o prato!
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Botão Submit -->
                <button type="submit" id="btn-submit"
                    class="w-full bg-primary hover:bg-[#ffed4a] text-background-dark font-bold text-lg rounded-full h-14 flex items-center justify-center gap-2 transition-colors mt-8">
                    <span class="material-symbols-outlined">check</span>
                    <span id="btn-submit-text">Confirmar Minha Presença</span>
                </button>

                <p class="text-center text-[10px] text-gray-600 mt-4">
                    Ao confirmar, você concorda em receber comunicações sobre o evento.
                </p>
            </form>
        </div>

        <!-- STEP 3: Sucesso -->
        <div id="step-sucesso" class="step">
            <div class="flex flex-col items-center justify-center min-h-[60vh] text-center">
                <div class="w-20 h-20 bg-primary/20 rounded-full flex items-center justify-center mb-6">
                    <span class="material-symbols-outlined text-primary text-4xl">check_circle</span>
                </div>
                <h1 class="text-2xl font-black text-white mb-2">Presença Confirmada!</h1>
                <p class="text-text-secondary mb-8">Estamos alegres por celebrar com você!</p>

                <div class="bg-surface-dark rounded-2xl p-6 border border-white/5 w-full mb-6">
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-primary">calendar_month</span>
                        <div class="text-left">
                            <p class="text-white font-semibold">31 de Dezembro</p>
                            <p class="text-text-secondary text-sm">19h30 - <?= EVENTO_LOCAL ?></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-primary">group</span>
                        <div class="text-left">
                            <p class="text-white font-semibold" id="resumo-pessoas">1 pessoa</p>
                            <p class="text-text-secondary text-sm" id="resumo-jantar">Participando do jantar</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mb-4">
                        <span class="material-symbols-outlined text-primary">email</span>
                        <div class="text-left">
                            <p class="text-text-secondary text-xs">Email de confirmação enviado para:</p>
                            <p class="text-white font-medium text-sm" id="resumo-email"></p>
                        </div>
                    </div>
                    <div id="resumo-participantes" class="border-t border-white/5 pt-4 mt-4">
                        <p class="text-xs text-text-secondary mb-2">Participantes:</p>
                        <div id="lista-resumo-participantes" class="text-sm text-white"></div>
                    </div>
                    <div id="resumo-pratos" class="hidden border-t border-white/5 pt-4 mt-4">
                        <p class="text-sm font-bold text-white mb-2">🍲 Prato que vai levar:</p>
                        <div id="lista-resumo-pratos" class="text-sm text-white"></div>
                    </div>
                </div>
                
                <!-- Lembretes (aparece se participa do jantar) -->
                <div id="resumo-lembretes" class="hidden w-full mb-6 space-y-3">
                    <!-- Bebida em destaque -->
                    <div class="bg-sky-500/20 rounded-xl p-4 border-2 border-sky-400/60">
                        <div class="flex gap-3 items-center">
                            <span class="material-symbols-outlined text-sky-400 text-[24px]">local_bar</span>
                            <p class="text-base text-white font-bold">Leve também a bebida!</p>
                        </div>
                    </div>
                    <!-- Talheres -->
                    <div class="bg-primary/10 rounded-xl p-3 border border-primary/20">
                        <div class="flex gap-2 items-center">
                            <span class="material-symbols-outlined text-primary text-[18px]">restaurant</span>
                            <p class="text-xs text-primary/90">Leve <strong>talheres para servir</strong> o prato</p>
                        </div>
                    </div>
                </div>

                <a href="/" class="text-primary hover:text-[#ffed4a] font-medium transition-colors">
                    ← Voltar para a página inicial
                </a>
            </div>
        </div>
    </main>

    <script>
        let acompanhantesCount = 0;
        let emailAtual = '';
        let modoEdicao = false;

        async function verificarEmail() {
            const email = document.getElementById('input-email').value;
            if (!email || !email.includes('@')) {
                alert('Digite um email válido');
                return;
            }

            document.getElementById('btn-continuar-text').textContent = 'Verificando...';

            try {
                const formData = new FormData();
                formData.append('action', 'verificar_email');
                formData.append('email', email);

                const response = await fetch('inscricao', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                emailAtual = email;
                document.getElementById('input-email-hidden').value = email;

                if (data.existe && data.dados) {
                    // Preencher dados existentes
                    modoEdicao = true;
                    document.getElementById('aviso-edicao').classList.remove('hidden');
                    document.getElementById('input-nome').value = data.dados.nome || '';
                    document.getElementById('input-whatsapp').value = data.dados.whatsapp || '';
                    
                    // Acompanhantes
                    if (data.dados.acompanhantes && data.dados.acompanhantes.length > 0) {
                        data.dados.acompanhantes.forEach(nome => {
                            adicionarAcompanhante(nome);
                        });
                    }

                    // Jantar
                    if (data.dados.participa_jantar == 1) {
                        document.querySelector('input[name="jantar"][value="sim"]').checked = true;
                        togglePratos(true);
                        
                        // Marcar prato
                        if (data.dados.pratos && data.dados.pratos.length > 0) {
                            const radio = document.querySelector(`input[name="prato"][value="${data.dados.pratos[0]}"]`);
                            if (radio) radio.checked = true;
                        }
                    } else {
                        document.querySelector('input[name="jantar"][value="nao"]').checked = true;
                    }
                }

                document.getElementById('step-email').classList.remove('active');
                document.getElementById('step-form').classList.add('active');

            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao verificar email. Tente novamente.');
            } finally {
                document.getElementById('btn-continuar-text').textContent = 'Continuar';
            }
        }

        function adicionarAcompanhante(nome = '') {
            acompanhantesCount++;
            const container = document.getElementById('lista-acompanhantes');
            const div = document.createElement('div');
            div.className = 'flex gap-2';
            div.id = `acompanhante-${acompanhantesCount}`;
            div.innerHTML = `
                <input type="text" name="acompanhantes[]" value="${nome}"
                    class="flex-1 bg-surface-dark border border-white/10 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-primary focus:ring-1 focus:ring-primary transition-colors text-sm"
                    placeholder="Nome do participante">
                <button type="button" onclick="removerAcompanhante(${acompanhantesCount})"
                    class="bg-red-500/10 hover:bg-red-500/20 text-red-400 p-3 rounded-xl transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            `;
            container.appendChild(div);
        }

        function removerAcompanhante(id) {
            document.getElementById(`acompanhante-${id}`).remove();
        }

        function togglePratos(mostrar) {
            const secao = document.getElementById('secao-pratos');
            if (mostrar) {
                secao.classList.remove('hidden');
            } else {
                secao.classList.add('hidden');
            }
        }

        // Form submit
        document.getElementById('form-inscricao').addEventListener('submit', async function(e) {
            e.preventDefault();

            // Validar se selecionou jantar ou culto
            const jantarSim = document.querySelector('input[name="jantar"][value="sim"]');
            const jantarNao = document.querySelector('input[name="jantar"][value="nao"]');
            
            if (!jantarSim.checked && !jantarNao.checked) {
                alert('Por favor, selecione se vai participar do jantar ou não.');
                return;
            }
            
            // Se vai ao jantar, validar se selecionou um prato
            if (jantarSim.checked) {
                const pratoSelecionado = document.querySelector('input[name="prato"]:checked');
                if (!pratoSelecionado) {
                    alert('Por favor, selecione o prato que vai levar.');
                    return;
                }
            }

            const btn = document.getElementById('btn-submit');
            const btnText = document.getElementById('btn-submit-text');
            btn.classList.add('loading');
            btnText.textContent = 'Salvando...';

            try {
                const formData = new FormData(this);
                formData.append('action', 'salvar');

                const response = await fetch('inscricao', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.sucesso) {
                    // Mostrar email
                    document.getElementById('resumo-email').textContent = emailAtual;
                    
                    // Atualizar resumo
                    const acompanhantes = document.querySelectorAll('input[name="acompanhantes[]"]');
                    const totalPessoas = 1 + acompanhantes.length;
                    document.getElementById('resumo-pessoas').textContent =
                        totalPessoas === 1 ? '1 pessoa' : `${totalPessoas} pessoas`;

                    const participaJantar = document.querySelector('input[name="jantar"]:checked')?.value === 'sim';
                    document.getElementById('resumo-jantar').textContent =
                        participaJantar ? 'Participando do jantar' : 'Apenas no culto';

                    // Mostrar participantes no resumo (nome principal + acompanhantes)
                    const nomePrincipal = document.getElementById('input-nome').value;
                    const nomesAcomp = Array.from(acompanhantes).map(i => i.value).filter(v => v);
                    const todosParticipantes = [nomePrincipal, ...nomesAcomp];
                    
                    document.getElementById('lista-resumo-participantes').innerHTML = 
                        todosParticipantes.map(n => `<span class="inline-block bg-white/5 px-2 py-1 rounded mr-1 mb-1">${n}</span>`).join('');

                    // Mostrar pratos no resumo
                    if (participaJantar) {
                        const pratoSelecionado = document.querySelector('input[name="prato"]:checked');
                        if (pratoSelecionado) {
                            document.getElementById('resumo-pratos').classList.remove('hidden');
                            const nomePrato = pratoSelecionado.closest('label').querySelector('.text-white').textContent;
                            document.getElementById('lista-resumo-pratos').innerHTML = 
                                `<span class="inline-block bg-primary/20 text-primary text-lg font-bold px-4 py-2 rounded-lg">${nomePrato}</span>`;
                        }
                        
                        // Mostrar lembretes
                        document.getElementById('resumo-lembretes').classList.remove('hidden');
                    }

                    // Mostrar sucesso PRIMEIRO
                    document.getElementById('step-form').classList.remove('active');
                    document.getElementById('step-sucesso').classList.add('active');
                    
                    // Enviar email em background DEPOIS
                    const emailFormData = new FormData();
                    emailFormData.append('action', 'enviar_email');
                    emailFormData.append('email', emailAtual);
                    fetch('inscricao', { method: 'POST', body: emailFormData });
                } else {
                    alert('Erro ao salvar: ' + (data.erro || 'Tente novamente'));
                }

            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao salvar. Tente novamente.');
            } finally {
                btn.classList.remove('loading');
                btnText.textContent = 'Confirmar Minha Presença';
            }
        });

        // Máscara telefone
        document.getElementById('input-whatsapp').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 11) value = value.slice(0, 11);
            if (value.length > 6) {
                value = `(${value.slice(0, 2)}) ${value.slice(2, 7)}-${value.slice(7)}`;
            } else if (value.length > 2) {
                value = `(${value.slice(0, 2)}) ${value.slice(2)}`;
            } else if (value.length > 0) {
                value = `(${value}`;
            }
            e.target.value = value;
        });
    </script>
</body>

</html>
