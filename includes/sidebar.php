<aside class="group w-16 hover:w-64 bg-white dark:bg-gray-800 shadow-md border-r border-gray-200 dark:border-gray-700 transition-all duration-300 overflow-visible flex flex-col fixed top-0 left-0 h-screen z-40">

  <!-- TOPO: logo e navegação -->
    <div class="p-4 flex-1">
    <img src="../logooitava.png" alt="Logo" class="w-10 group-hover:w-32 transition-all duration-300 mb-6">
    <nav class="space-y-4">
    <!-- CONSULTAS -->
    <div class="relative">
        <button onclick="toggleSubmenu('submenu-consultas')" class="flex items-center justify-between text-gray-700 dark:text-gray-300 hover:text-teal-600 w-full px-2 py-2">
            <span class="flex items-center space-x-2">
            <svg stroke="currentColor" fill="none" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" class="shrink-0" height="24" width="24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"></path></svg>            
            <span class="hidden group-hover:inline">Consultas</span>
            </span>
            <span class="text-xl hidden group-hover:inline">▾</span>
        </button>

    <div id="submenu-consultas" class="absolute left-full group-hover:left-60 top-0 min-w-[16rem] max-w-[vw] w-72 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 shadow-xl z-50 hidden flex flex-col">
        <div class="sticky top-0 z-10 bg-teal-600 text-white px-4 py-2 text-base font-semibold">
            Especialidades
        </div>
        <ul class="p-2 space-y-1 text-sm text-gray-800 dark:text-gray-200 max-h-[650px] overflow-y-auto flex-grow">
            <?php
            $sql = "SELECT NOME FROM especialidades ORDER BY NOME";
            $result = ibase_query($conn, $sql);
            while ($row = ibase_fetch_assoc($result)) {
                $nome = trim(mb_convert_encoding($row['NOME'], 'UTF-8', 'Windows-1252'));
                if ($nome !== '') {
                    echo "<li><a href='#' onclick=\"carregarConteudo('consulta', '" . htmlspecialchars($nome) . "')\" class='block px-3 py-2 rounded hover:bg-teal-100 transition'>" . htmlspecialchars($nome) . "</a></li>";
                }
            }
            ?>
        </ul>
        <div class="border-t border-gray-300 dark:border-gray-600">
            <a href="cadastrar_especialidade.php" class="block text-teal-600 font-semibold px-3 py-2 rounded hover:bg-teal-100">+ Nova especialidade</a>
        </div>
    </div>


    <!-- PROCEDIMENTOS -->
    <div class="relative">
        <button onclick="toggleSubmenu('submenu-procedimentos')" class="flex items-center justify-between text-gray-700 dark:text-gray-300 hover:text-teal-600 w-full px-2 py-2">
            <span class="flex items-center space-x-2">
            <svg stroke="currentColor" fill="none" stroke-width="1.5" viewBox="0 0 24 24" aria-hidden="true" class="shrink-0" height="24" width="24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"></path></svg>            
            <span class="hidden group-hover:inline">Procedimentos</span>
            </span>
            <span class="text-xl hidden group-hover:inline">▾</span>
        </button>

    <div id="submenu-procedimentos" class="absolute left-full group-hover:left-60 top-0 min-w-[16rem] max-w-[vw] w-72 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 shadow-xl z-50 hidden flex flex-col">
        <div class="sticky top-0 z-10 bg-teal-600 text-white px-4 py-2 text-base font-semibold">
            Procedimentos
        </div>
        <ul class="p-2 space-y-1 text-sm text-gray-800 dark:text-gray-200 max-h-[650px] overflow-y-auto flex-grow">
            <?php
            $sql = "SELECT NOME FROM grupo_exames ORDER BY NOME";
            $result = ibase_query($conn, $sql);
            while ($row = ibase_fetch_assoc($result)) {
                $nome = trim(mb_convert_encoding($row['NOME'], 'UTF-8', 'Windows-1252'));
                if ($nome !== '') {
                    echo "<li><a href='#' onclick=\"carregarConteudo('procedimento', '" . htmlspecialchars($nome) . "')\" class='block px-3 py-2 rounded hover:bg-teal-100 transition'>" . htmlspecialchars($nome) . "</a></li>";
                }
            }
            ?>
        </ul>
        <div class="border-t border-gray-300 dark:border-gray-600">
            <a href="cadastrar_grupo_exames.php" class="block text-teal-600 font-semibold px-3 py-2 rounded hover:bg-teal-100">+ Novo grupo de exame</a>
        </div>
    </div>

    </nav>
  </div>

  <!-- RODAPÉ FIXO -->
<div class="space-y-2 px-4 pb-4 mt-auto">

  <!-- CONFIGURAÇÕES -->
  <a href="#" onclick="carregarConfiguracoes()" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-teal-600 transition">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5"
         viewBox="0 0 24 24" class="shrink-0 w-5 h-5" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
    </svg>
    <span class="hidden group-hover:inline">Configurações</span>
  </a>

  <!-- VOLTAR -->
  <a href="../frmindex.php" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-teal-600 transition">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5"
         viewBox="0 0 24 24" class="shrink-0 w-5 h-5" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
    </svg>
    <span class="hidden group-hover:inline">Voltar ao Menu Principal</span>
  </a>

  <!-- SAIR -->
  <a href="../frmlogout.php" class="flex items-center space-x-2 text-gray-700 dark:text-gray-300 hover:text-teal-600 transition">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" stroke="currentColor" stroke-width="1.5"
         viewBox="0 0 24 24" class="shrink-0 w-5 h-5" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round"
        d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6A2.25 2.25 0 0 0 5.25 5.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M18 15l3-3m0 0l-3-3m3 3H9" />
    </svg>
    <span class="hidden group-hover:inline">Sair</span>
  </a>

</div>

</aside>