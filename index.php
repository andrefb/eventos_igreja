<?php 
require_once 'config.php'; 
initDB();
$inscricoesAbertas = inscricoesAbertas();
?>
<!DOCTYPE html>
<html class="dark" lang="pt-BR">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Culto de Réveillon 2026 - Igreja Vivos com Cristo</title>
    
    <!-- Open Graph / WhatsApp -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://eventos.vivos.site/">
    <meta property="og:title" content="Culto de Réveillon 2026">
    <meta property="og:description" content="Venha celebrar a virada do ano conosco! 31 de Dezembro, 19h30 - Igreja Vivos com Cristo">
   <!-- <meta property="og:image" content="https://eventos.vivos.site/og-image.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630"> -->
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:title" content="Culto de Réveillon 2026">
    <meta property="twitter:description" content="Venha celebrar a virada do ano conosco! 🎉">
    <!-- <meta property="twitter:image" content="https://eventos.vivos.site/og-image.jpg">-->
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
                    borderRadius: {
                        "DEFAULT": "1rem",
                        "lg": "1.5rem",
                        "xl": "2rem",
                        "2xl": "2.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        body {
            min-height: 100dvh;
        }

        .countdown-box {
            animation: pulse-subtle 2s ease-in-out infinite;
        }

        @keyframes pulse-subtle {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }
    </style>
</head>

<body
    class="bg-background-dark font-display text-white selection:bg-primary selection:text-black antialiased overflow-x-hidden">

    <!-- Header Fixo -->
    <div class="fixed top-0 z-50 w-full bg-background-dark/80 backdrop-blur-md border-b border-white/5">
        <div class="flex items-center justify-between p-4 h-16 max-w-md mx-auto">
            <div class="flex items-center gap-2">
                <img src="logo_branco.png" alt="Vivos com Cristo" class="h-7 w-auto">
                <div class="flex flex-col leading-none">
                    <span class="text-[10px] uppercase tracking-wider text-gray-400">Igreja</span>
                    <span class="text-sm font-bold tracking-tight text-white">Vivos com Cristo</span>
                </div>
            </div>
            <button id="btnShare"
                class="bg-primary/10 hover:bg-primary/20 text-primary p-2 rounded-full transition-colors"
                onclick="compartilhar()">
                <span class="material-symbols-outlined text-[20px]">share</span>
            </button>
        </div>
    </div>

    <!-- Conteúdo Principal -->
    <main class="relative flex flex-col w-full min-h-screen pt-16 pb-24 max-w-md mx-auto">

        <!-- Hero com Background -->
        <div
            class="relative w-full px-4 pt-6 pb-6 flex flex-col items-center justify-center text-center overflow-hidden">
            <!-- Background Estrelado -->
            <div class="absolute inset-0 z-0 opacity-40">
                <div class="w-full h-full bg-gradient-to-b from-indigo-950 via-purple-950 to-background-dark"></div>
                <div class="absolute inset-0 bg-gradient-to-b from-background-dark via-transparent to-background-dark">
                </div>
            </div>

            <!-- Conteúdo Hero -->
            <div class="relative z-10 flex flex-col gap-4 items-center w-full">
                <!-- Badge Animado -->
                <div
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/10 backdrop-blur-sm">
                    <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                    <span class="text-xs font-medium tracking-wide uppercase text-primary">Celebre 2026 Conosco</span>
                </div>

                <!-- Título -->
                <h1 class="text-4xl font-black leading-[1.1] tracking-tight">
                    <span class="block text-transparent bg-clip-text bg-gradient-to-r from-white to-gray-400">Culto
                        de</span>
                    <span class="block text-primary drop-shadow-sm">Réveillon</span>
                </h1>

                <!-- Info Data/Local -->
                <div class="flex flex-col gap-1 items-center justify-center text-gray-300 text-sm font-medium mt-2">
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px] text-primary">calendar_month</span>
                        <span>31/12 • 19h30</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[18px] text-primary">location_on</span>
                        <span>Vila Velha</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Countdown Timer -->
        <div class="px-4 mb-8 mt-4 relative z-10">
            <div class="grid grid-cols-4 gap-2 w-full p-4 rounded-2xl bg-surface-dark border border-white/5 shadow-xl">
                <div class="flex flex-col items-center gap-1">
                    <div class="countdown-box text-xl font-bold text-white bg-white/5 w-full aspect-square rounded-xl flex items-center justify-center border border-white/5"
                        id="days">05</div>
                    <span class="text-[10px] uppercase tracking-wider text-text-secondary">Dias</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="countdown-box text-xl font-bold text-white bg-white/5 w-full aspect-square rounded-xl flex items-center justify-center border border-white/5"
                        id="hours">12</div>
                    <span class="text-[10px] uppercase tracking-wider text-text-secondary">Horas</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="countdown-box text-xl font-bold text-white bg-white/5 w-full aspect-square rounded-xl flex items-center justify-center border border-white/5"
                        id="minutes">30</div>
                    <span class="text-[10px] uppercase tracking-wider text-text-secondary">Min</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="text-xl font-bold text-primary bg-primary/10 w-full aspect-square rounded-xl flex items-center justify-center border border-primary/20"
                        id="seconds">45</div>
                    <span class="text-[10px] uppercase tracking-wider text-primary">Seg</span>
                </div>
            </div>
        </div>

        <!-- Botão CTA Principal -->
        <div class="px-4 mb-10 w-full">
            <?php if ($inscricoesAbertas): ?>
            <a href="inscricao" class="w-full relative group overflow-hidden rounded-full p-[1px] block">
                <span
                    class="absolute inset-0 bg-gradient-to-r from-primary via-yellow-200 to-primary opacity-70 group-hover:opacity-100 transition-opacity"></span>
                <div
                    class="relative bg-primary hover:bg-[#ffed4a] transition-colors rounded-full h-14 flex items-center justify-center gap-2">
                    <span class="text-background-dark font-bold text-lg">Confirmar Presença</span>
                    <span class="material-symbols-outlined text-background-dark">arrow_forward</span>
                </div>
            </a>
            <?php else: ?>
            <div class="w-full rounded-full bg-gray-700 h-14 flex items-center justify-center gap-2 cursor-not-allowed">
                <span class="material-symbols-outlined text-gray-400">lock</span>
                <span class="text-gray-400 font-bold text-lg">Inscrições Encerradas</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Programação Timeline -->
        <div class="flex flex-col gap-6 px-4">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-bold text-white">Programação</h2>
                <span class="text-xs font-medium text-primary bg-primary/10 px-2 py-1 rounded-md">31 Dez</span>
            </div>

            <div class="relative pl-2">
                <!-- Linha Vertical -->
                <div class="absolute left-[19px] top-4 bottom-4 w-[2px] bg-white/10 rounded-full"></div>

                <div class="flex flex-col gap-6">
                    <!-- Item 1: Culto -->
                    <div class="relative flex items-start gap-4">
                        <div
                            class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-dark border border-white/10 ring-4 ring-background-dark">
                            <span class="material-symbols-outlined text-primary text-[20px]">music_note</span>
                        </div>
                        <div
                            class="flex flex-col pt-1 pb-2 bg-surface-dark/50 p-4 rounded-2xl border border-white/5 w-full">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-primary font-bold text-sm">19h30</span>
                                <span class="bg-white/5 text-[10px] px-2 py-0.5 rounded text-gray-400">Igreja</span>
                            </div>
                            <h3 class="text-white text-base font-bold leading-tight mb-1">Culto de Celebração</h3>
                            <p class="text-text-secondary text-sm leading-relaxed">Momento de louvor, adoração e
                                ministração da palavra</p>
                        </div>
                    </div>

                    <!-- Item 2: Jantar -->
                    <div class="relative flex items-start gap-4">
                        <div
                            class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-dark border border-white/10 ring-4 ring-background-dark">
                            <span class="material-symbols-outlined text-primary text-[20px]">restaurant</span>
                        </div>
                        <div
                            class="flex flex-col pt-1 pb-2 bg-surface-dark/50 p-4 rounded-2xl border border-white/5 w-full">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-primary font-bold text-sm">21h30</span>
                                <span class="bg-white/5 text-[10px] px-2 py-0.5 rounded text-gray-400">Igreja</span>
                            </div>
                            <h3 class="text-white text-base font-bold leading-tight mb-1">Jantar <span class="text-xs font-normal italic text-gray-400">(opcional)</span></h3>
                            <p class="text-text-secondary text-sm leading-relaxed">Jantar e comunhão</p>
                        </div>
                    </div>

                    <!-- Item 3: Encerramento -->
                    <div class="relative flex items-start gap-4">
                        <div
                            class="relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-dark border border-white/10 ring-4 ring-background-dark">
                            <span class="material-symbols-outlined text-primary text-[20px]">night_shelter</span>
                        </div>
                        <div
                            class="flex flex-col pt-1 pb-2 bg-surface-dark/50 p-4 rounded-2xl border border-white/5 w-full">
                            <div class="flex justify-between items-start mb-1">
                                <span class="text-primary font-bold text-sm">22h30</span>
                                <span class="bg-white/5 text-[10px] px-2 py-0.5 rounded text-gray-400">Livre</span>
                            </div>
                            <h3 class="text-white text-base font-bold leading-tight mb-1">Encerramento</h3>
                            <p class="text-text-secondary text-sm leading-relaxed">Fique à vontade para celebrar a
                                virada onde desejar!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Jantar Compartilhado -->
        <div class="mt-10 px-4">
            <div class="bg-surface-dark rounded-3xl p-6 border border-white/5 relative overflow-hidden">
                <div
                    class="absolute -top-10 -right-10 w-32 h-32 bg-primary/5 rounded-full blur-2xl pointer-events-none">
                </div>
                <div class="flex items-center gap-3 mb-4">
                    <span class="material-symbols-outlined text-primary">soup_kitchen</span>
                    <h2 class="text-lg font-bold text-white">Jantar Compartilhado</h2>
                </div>
                <p class="text-sm text-text-secondary mb-6 leading-relaxed">
                    A participação no jantar é opcional. Quem for participar deve escolher <span class="font-bold text-white">um prato</span> para compartilhar e trazer sua própria <span class="font-bold text-white">bebida</span>.
                </p>
                <div class="bg-primary/10 rounded-xl p-4 border border-primary/20 flex gap-3 items-start">
                    <span class="material-symbols-outlined text-primary text-[20px] shrink-0 mt-0.5">lightbulb</span>
                    <p class="text-xs text-primary/90 leading-relaxed">
                        <span class="font-bold">Dica Importante:</span> Traga os talheres necessários para servir o prato.
                    </p>
                </div>
            </div>
        </div>



        <!-- Footer -->
        <div class="mt-12 mb-8 px-6 text-center">
            <div class="flex justify-center gap-4 mt-6">
                <a class="text-gray-400 hover:text-white transition-colors" href="https://vivos.site" target="_blank">
                    <span class="material-symbols-outlined">public</span>
                </a>
                <a class="text-gray-400 hover:text-white transition-colors"
                    href="https://instagram.com/igrejavivoscomcristo" target="_blank">
                    <span class="material-symbols-outlined">photo_camera</span>
                </a>
                <a class="text-gray-400 hover:text-white transition-colors"
                    href="https://youtube.com/igrejavivoscomcristo" target="_blank">
                    <span class="material-symbols-outlined">smart_display</span>
                </a>
            </div>
            <p class="text-[10px] text-gray-600 mt-4 uppercase tracking-widest">© 2025 Igreja Vivos com Cristo</p>
        </div>
    </main>

    <!-- Bottom Bar Fixa (Mobile) -->
    <div class="fixed bottom-4 left-4 right-4 z-40 md:hidden">
        <div
            class="bg-surface-dark/90 backdrop-blur-lg border border-white/10 rounded-full p-2 flex items-center justify-between shadow-2xl">
            <div class="flex items-center gap-3 px-3">
                <span class="material-symbols-outlined text-primary">event_available</span>
                <div class="flex flex-col">
                    <span class="text-[10px] text-gray-400 uppercase">Faltam</span>
                    <span class="text-xs font-bold text-white" id="countdown-mini">5 dias</span>
                </div>
            </div>
            <?php if ($inscricoesAbertas): ?>
            <a href="inscricao"
                class="bg-primary text-background-dark font-bold text-sm px-5 py-2.5 rounded-full shadow-lg shadow-primary/20 hover:bg-[#ffed4a] transition-colors">
                Confirmar
            </a>
            <?php else: ?>
            <span class="bg-gray-700 text-gray-400 font-bold text-sm px-5 py-2.5 rounded-full">
                Encerrado
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Script Countdown com Timezone São Paulo -->
    <script>
        // Data do evento: 31/12/2025 às 19:30 (horário de Brasília/São Paulo)
        // Usando timezone explícito para garantir hora correta
        const eventDateStr = '2025-12-31T19:30:00';

        // Função para obter hora atual no timezone de São Paulo
        function getNowInSaoPaulo() {
            return new Date(new Date().toLocaleString('en-US', { timeZone: 'America/Sao_Paulo' }));
        }

        // Data do evento no timezone de São Paulo
        const eventDate = new Date(eventDateStr);

        function updateCountdown() {
            const now = getNowInSaoPaulo();
            const diff = eventDate - now;

            if (diff <= 0) {
                document.getElementById('days').textContent = '00';
                document.getElementById('hours').textContent = '00';
                document.getElementById('minutes').textContent = '00';
                document.getElementById('seconds').textContent = '00';
                document.getElementById('countdown-mini').textContent = 'Agora!';
                return;
            }

            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);

            document.getElementById('days').textContent = String(days).padStart(2, '0');
            document.getElementById('hours').textContent = String(hours).padStart(2, '0');
            document.getElementById('minutes').textContent = String(minutes).padStart(2, '0');
            document.getElementById('seconds').textContent = String(seconds).padStart(2, '0');

            // Mini countdown
            if (days > 0) {
                document.getElementById('countdown-mini').textContent = `${days} dia${days > 1 ? 's' : ''}`;
            } else if (hours > 0) {
                document.getElementById('countdown-mini').textContent = `${hours}h ${minutes}min`;
            } else {
                document.getElementById('countdown-mini').textContent = `${minutes}min ${seconds}s`;
            }
        }

        // Atualiza a cada segundo
        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Função de compartilhar
        function compartilhar() {
            const url = 'https://eventos.vivos.site';
            const texto = 'Culto de Réveillon 2026 - Igreja Vivos com Cristo';

            if (navigator.share) {
                navigator.share({
                    title: texto,
                    text: 'Venha celebrar a virada do ano conosco! 🎉',
                    url: url
                }).catch(() => { });
            } else {
                // Fallback: copia para clipboard
                navigator.clipboard.writeText(url).then(() => {
                    alert('Link copiado! ' + url);
                }).catch(() => {
                    prompt('Copie o link:', url);
                });
            }
        }
    </script>
</body>

</html>