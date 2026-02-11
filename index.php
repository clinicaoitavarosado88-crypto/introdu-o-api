<?php
// ✅ HEADERS ANTI-CACHE - Força navegador a sempre buscar versão mais recente
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); // Data no passado

include 'includes/connection.php';
include 'includes/verificar_permissao.php';

// Verificar se o usuário está logado
$usuario_atual = getUsuarioAtual();
if (!$usuario_atual) {
    // Redirecionar para login se acessado diretamente
    header('Location: ../frmindex.php?erro=login_necessario');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br" class="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Agenda - Clínica Oitava Rosado</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="includes/estilos.css">
  <script src="includes/funcoes.js?v=<?= time() ?>" defer></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script>
    tailwind.config = {
      darkMode: 'class',
    }
  </script>

</head>
<body class="bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
  <div class="flex min-h-screen">
    <?php include 'includes/sidebar.php'; ?>

    <div class="flex-1 flex flex-col ml-16">
      <?php include 'includes/header.php'; ?>

      <main id="main-content" class="flex-1 p-4 sm:p-8 overflow-y-auto">
        <div id="conteudo-dinamico" class="text-center text-gray-600 dark:text-gray-300 text-sm md:text-base">
          <div class="text-center">
            <img src="../logooitava.png" alt="Logo" class="mx-auto w-24 md:w-32 mb-6">
            <h2 class="text-xl md:text-2xl font-semibold mb-2">
              Seja bem-vindo(a) ao Agendamento da Clínica Oitava Rosado
            </h2>
            <p class="text-gray-600 dark:text-gray-300 text-sm md:text-base">
              Navegue pelos itens no menu para ter acesso às funcionalidades
            </p>
          </div>
        </div>
        
        <!-- Indicador de carregamento -->
        <div id="loader" class="hidden mt-6 text-teal-600 text-sm flex items-center space-x-2">
          <svg class="animate-spin w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.818-1A9.003 9.003 0 003.582 9" />
          </svg>
          <span>Carregando dados...</span>
        </div>
      </main>
    </div>
  </div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Select2 -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script src="includes/agenda-new.js?v=<?= time() ?>"></script>
<script src="integracao_ressonancia.js?v=<?= time() ?>"></script>
<script src="includes/sistema_busca_pacientes.js" defer></script>
<script src="includes/select2_agendamento.js" defer></script>
<script src="includes/scripts.js" defer></script>

<script>
// Verificação adicional de autenticação
document.addEventListener('DOMContentLoaded', function() {
    // Verificar periodicamente se o usuário ainda está logado
    function verificarAutenticacao() {
        fetch('includes/verificar_permissao.php?acao=obter_usuario_atual')
            .then(res => res.json())
            .then(data => {
                if (!data.usuario) {
                    showToast('Sua sessão expirou. Você será redirecionado para o login.', false);
                    setTimeout(() => {
                        window.location.href = '../frmindex.php?erro=sessao_expirada';
                    }, 3000);
                }
            })
            .catch(() => {
                // Erro na verificação - assumir que não está logado
                console.warn('Erro ao verificar autenticação');
            });
    }
    
    // Verificar a cada 5 minutos
    setInterval(verificarAutenticacao, 5 * 60 * 1000);
    
    // Verificação inicial após 1 segundo
    setTimeout(verificarAutenticacao, 1000);
});
</script>

<?php include 'includes/footer.php'; ?>
  
