<?php include 'includes/connection.php'; ?>

<form method="post" id="form-agenda" class="space-y-8">
<!-- form_criar_agenda.php -->
    <!-- Título e subtítulo alinhados à esquerda -->
    <div class="mb-6 text-left">
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Criar nova agenda</h2>
    <p class="text-sm text-gray-500 mt-1">Preencha os campos abaixo para criar uma nova agenda no sistema</p>
    </div>

    <!-- Informações gerais -->
    <div class="space-y-4">
    <div class="mb-2 text-left">
        <h3 class="text-base font-semibold text-teal-700">Informações gerais</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
    <div>
        <label class="block text-sm font-medium text-gray-700">Unidade <span class="text-red-500">*</span></label>
        <select name="unidade_id" data-erro="Informe a unidade"
        class="campo-obrigatorio w-full border border-gray-300 rounded px-3 py-2 text-sm">
        <option value="">Selecione uma unidade</option>
        <?php
        $q = ibase_query($conn, "SELECT ID, NOME_UNIDADE FROM LAB_CIDADES where AGENDA_ATI = 1 ORDER BY NOME_UNIDADE");
        while ($r = ibase_fetch_assoc($q)) {
            echo "<option value='{$r['ID']}'>" . htmlspecialchars(mb_convert_encoding($r['NOME_UNIDADE'], 'UTF-8', 'Windows-1252')) . "</option>";
        }
        ?>
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700">Tipo de Agendamento <span class="text-red-500">*</span></label>
        <select name="tipo" id="tipo_agendamento" data-erro="Selecione o tipo de agendamento"
        class="campo-obrigatorio w-full border border-gray-300 rounded px-3 py-2 text-sm">
        <option value="">Selecione</option>
        <option value="consulta">Consulta</option>
        <option value="procedimento">Procedimento</option>
        </select>
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700">Telefone</label>
      <input type="text" name="telefone" id="telefone" class="campo-obrigatorio w-full border rounded px-3 py-2 text-sm" data-erro="Informe o telefone">
    </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <div id="campo-procedimento">
        <label class="block text-sm font-medium text-gray-700">Grupo de Exames <span class="text-red-500">*</span></label>
        <select name="procedimento_id" data-erro="Informe o grupo de exames"
        class="select2-tailwind campo-obrigatorio w-full border border-gray-300 rounded px-3 py-2 text-sm">
        <option value="">Selecione um grupo</option>
        <?php
        $g = ibase_query($conn, "SELECT ID, NOME FROM GRUPO_EXAMES ORDER BY NOME");
        while ($r = ibase_fetch_assoc($g)) {
            echo "<option value='{$r['ID']}'>" . htmlspecialchars(mb_convert_encoding($r['NOME'], 'UTF-8', 'Windows-1252')) . "</option>";
        }
        ?>
        </select>
    </div>

    <div id="campo-prestador">
        <label class="block text-sm font-medium text-gray-700">Prestador <span id="prestador-obrigatorio" class="text-red-500">*</span></label>
        <select name="medico_id" id="select-prestador" data-erro="Informe o prestador"
        class="select2-tailwind w-full border border-gray-300 rounded px-3 py-2 text-sm">
        <option value="">Selecione um prestador</option>
        <?php
        $m = ibase_query($conn, "SELECT ID, NOME FROM LAB_MEDICOS_PRES WHERE MEDICO_EXECUTANTE = 'S' ORDER BY NOME");
        while ($r = ibase_fetch_assoc($m)) {
            $nome = utf8_encode($r['NOME']);
            echo "<option value='{$r['ID']}'>DR (A). $nome</option>";
        }
        ?>
        </select>
    </div>
    </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
    <div class="flex flex-col items-center justify-center">
        <span class="block text-sm font-medium text-gray-700 mb-2 text-center">Tipo de agenda <span class="text-red-500">*</span></span>
        <div class="flex gap-6 text-sm text-gray-700" id="tipo-agenda-wrapper">
            <label class="inline-flex items-center">
            <input type="radio" name="tipo_agenda" value="Horário Marcado" class="form-radio campo-radio" data-erro="Selecione o tipo de agenda">
            <span class="ml-2">Horário Marcado</span>
            </label>
            <label class="inline-flex items-center">
            <input type="radio" name="tipo_agenda" value="Ordem de Chegada" class="form-radio campo-radio" data-erro="Selecione o tipo de agenda">
            <span class="ml-2">Ordem de Chegada</span>
            </label>
            <label class="inline-flex items-center">
            <input type="radio" name="tipo_agenda" value="Agenda Aberta" class="form-radio campo-radio" data-erro="Selecione o tipo de agenda">
            <span class="ml-2">Agenda Aberta</span>
            </label>
        </div>
    </div>
    </div>
    <hr class="mt-6 border-gray-300">
    </div>

  <!-- Dias e Horários -->
  <div class="space-y-4">
    <div class="mb-2 text-left">
    <h3 class="font-semibold text-teal-700">Dias de atendimento <span class="text-red-500">*</span></h3>
    </div>
    <div id="grupo-dias" class="flex flex-wrap gap-4">
    <?php
    $dias = [
        'Segunda' => 'Segunda-feira',
        'Terça'   => 'Terça-feira',
        'Quarta'  => 'Quarta-feira',
        'Quinta'  => 'Quinta-feira',
        'Sexta'   => 'Sexta-feira',
        'Sábado'  => 'Sábado',
        'Domingo'  => 'Domingo',
    ];

    foreach ($dias as $valor => $label) {
        $slug = strtolower(substr($label, 0, 3)); // gera 'seg', 'ter', ..., 'sab'
        echo "<label>
            <input type='checkbox' name='dias[]' value='$valor' data-dia='$slug' class='dia-checkbox mr-1 campo-obrigatorio'>
            $label
        </label>";
    }

    ?>
    </div>
    <small id="erro-dias" class="text-red-600 hidden text-sm mt-1">Selecione pelo menos um dia</small>
    <div class="mb-2 text-left">
    <h3 class="font-semibold text-teal-700">Horários de atendimento por dia</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <?php
    foreach ($dias as $dia) {
        $slug = strtolower(substr($dia, 0, 3));

        echo "
        <div>
            <label class='block text-sm'>$dia - Início Manhã</label>
            <select name='{$slug}_manha_inicio' class='campo-horario select2-tailwind select-horario w-full border rounded px-2 py-1 text-sm' data-dia='$slug'>
            <option value=''>-- : --</option>";
        $hora = strtotime("06:00");
        $fim = strtotime("23:45");
        while ($hora <= $fim) {
        $h = date("H:i", $hora);
        echo "<option value='$h'>$h</option>";
        $hora = strtotime("+5 minutes", $hora);
        }
        echo "</select>
        </div>";

        echo "
        <div>
            <label class='block text-sm'>$dia - Fim Manhã</label>
            <select name='{$slug}_manha_fim' class='campo-horario select2-tailwind select-horario w-full border rounded px-2 py-1 text-sm' data-dia='$slug'>
            <option value=''>-- : --</option>";
        $hora = strtotime("06:00");
        while ($hora <= $fim) {
        $h = date("H:i", $hora);
        echo "<option value='$h'>$h</option>";
        $hora = strtotime("+5 minutes", $hora);
        }
        echo "</select>
        </div>";

        echo "
        <div>
            <label class='block text-sm'>$dia - Início Tarde</label>
            <select name='{$slug}_tarde_inicio' class='campo-horario select2-tailwind select-horario w-full border rounded px-2 py-1 text-sm' data-dia='$slug'>
            <option value=''>-- : --</option>";
        $hora = strtotime("06:00");
        while ($hora <= $fim) {
        $h = date("H:i", $hora);
        echo "<option value='$h'>$h</option>";
        $hora = strtotime("+5 minutes", $hora);
        }
        echo "</select>
        </div>";

        echo "
        <div>
            <label class='block text-sm'>$dia - Fim Tarde</label>
            <select name='{$slug}_tarde_fim' class='campo-horario select2-tailwind select-horario w-full border rounded px-2 py-1 text-sm' data-dia='$slug'>
            <option value=''>-- : --</option>";
        $hora = strtotime("06:00");
        while ($hora <= $fim) {
        $h = date("H:i", $hora);
        echo "<option value='$h'>$h</option>";
        $hora = strtotime("+5 minutes", $hora);
        }
        echo "</select>
        </div>";
    }
    ?>
    </div>
    
    <!-- Limite de vagas por dia -->
    <div class="mb-2 text-left">
      <h3 class="font-semibold text-teal-700">Limite de vagas por dia</h3>
      <p class="text-xs text-gray-500">Configure quantas vagas cada dia terá disponível</p>
    </div>
    <div id="vagas-por-dia" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <?php
      foreach ($dias as $valor => $label) {
          $slug = strtolower(substr($label, 0, 3));
          echo "
          <div id='vagas-$slug' class='hidden'>
              <label class='block text-sm font-medium text-gray-700'>$label</label>
              <input type='number' name='vagas_$slug' min='1' max='100' 
                     class='w-full border border-gray-300 rounded px-3 py-2 text-sm' 
                     placeholder='Ex: 20'>
          </div>";
      }
      ?>
    </div>
  </div>
  <hr class="mt-6 border-gray-300">

  <!-- Agendamentos -->
  <div class="space-y-4">
    <div class="mb-2 text-left">
    <h3 class="font-semibold text-teal-700">Agendamentos</h3>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <div>
        <label class="block text-sm font-medium">Tempo estimado (min) *</label>
        <select class="select2-tailwind" id="tempo_estimado" data-placeholder="Tempo estimado (min)" name="tempo_estimado_minutos">
          <option></option>
          <option>5 minutos</option>
          <option>10 minutos</option>
          <option>15 minutos</option>
          <option>20 minutos</option>
          <option>30 minutos</option>
          <option>45 minutos</option>
          <option>60 minutos</option>
          <option>75 minutos</option>
          <option>90 minutos</option>
          <option>105 minutos</option>
          <option>120 minutos</option>
        </select>
      </div>
      <!-- Campo removido - será substituído por campos individuais por dia -->
        <!-- ✅ NOVO: Campo de encaixes -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            <i class="bi bi-lightning-charge text-orange-500 mr-1"></i>
            Limite de encaixes por dia
        </label>
        <input type="number" name="limite_encaixes_dia" min="0" max="10" value="0"
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
               placeholder="Ex: 3" required>
        <p class="text-xs text-gray-500 mt-1">
            Quantidade de encaixes permitidos (0 = sem encaixes)
        </p>
    </div>
    <!-- ✅ NOVO: Campo de retornos -->
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            <i class="bi bi-arrow-clockwise text-indigo-500 mr-1"></i>
            Limite de retornos por dia
        </label>
        <input type="number" name="limite_retornos_dia" min="0" max="50" value="0"
               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
               placeholder="Ex: 10" required>
        <p class="text-xs text-gray-500 mt-1">
            Quantidade de retornos permitidos por dia (0 = sem limite)
        </p>
    </div>
    </div>
      <div>
        <label class="block text-sm font-medium">Sala/local de atendimento *</label>
        <input type="text" name="sala" class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
      </div>
    </div>
    
    <!-- Campos sempre visíveis -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
      <div>
        <label class="block text-sm font-medium text-gray-700">Idade mínima para atendimento</label>
        <input type="number" name="idade_minima_atendimento" min="0" value="0" 
               class="w-full border rounded px-3 py-2 text-sm"
               placeholder="Ex: 18 (0 = sem restrição)">
        <p class="text-xs text-gray-500 mt-1">Idade mínima em anos (0 = qualquer idade)</p>
      </div>
      
      <div>
        <label class="block text-sm font-medium text-gray-700">Atende comorbidade?</label>
        <select name="atende_comorbidade" class="w-full border rounded px-3 py-2 text-sm">
          <option value="0">Não</option>
          <option value="1">Sim</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">Aceita pacientes com comorbidades</p>
      </div>
         
    <div id="bloco-consulta" style="display: none;">
      <div>
        <label class="block text-sm font-medium text-gray-700">Possui retorno?</label>
        <select name="possui_retorno" class="w-full border rounded px-3 py-2 text-sm">
          <option value="0">Não</option>
          <option value="1">Sim</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">Permite agendamento de retorno</p>
      </div>
    </div>
    </div>
  </div>
  <hr class="mt-6 border-gray-300">

  <!-- Convênios -->
  <div class="space-y-4">
    <div class="mb-2 text-left">
    <h3 class="font-semibold text-teal-700">Convênios atendidos</h3>
    </div>
    
    <!-- Container onde os convênios serão adicionados dinamicamente -->
    <div id="container-convenios"></div>
      <!-- Bloco completo, inicialmente oculto -->
      <div id="bloco-aplicar-valores" class="hidden">
        <!-- Título -->
        <div class="text-left mb-2">
          <h3 class="font-semibold text-teal-700">Aplicar a todos os convênios</h3>
        </div>

        <!-- Campos -->
        <div class="flex flex-wrap items-end gap-4 border-t pt-4 mb-6">
          <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700">Qtd. Atendimentos (Aplicar)</label>
            <input type="number" id="aplicar-limite" class="border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm w-48">
          </div>
          <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-700">Qtd. Retornos (Aplicar)</label>
            <input type="number" id="aplicar-retornos" class="border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm w-48">
          </div>
          <div class="h-full flex items-end">
            <button type="button" onclick="aplicarValoresConvenios()" class="text-sm font-bold text-teal-700 hover:underline">
              Aplicar para todos
            </button>
          </div>
        </div>
      </div>



    <!-- Botão para adicionar novo convênio -->
    <button type="button" onclick="adicionarConvenio()" class="flex items-center gap-1 text-teal-700 hover:underline text-sm mt-2 font-bold">
      <i class="bi bi-plus-circle"></i> Adicionar convênio
    </button>

  </div>
    <hr class="mt-6 border-gray-300">

  <!-- Campos adicionais -->
  <div class="space-y-4">
    <h3 class="font-semibold text-teal-700 text-left">Adicionais</h3>
    <div class="space-y-2">
      <label class="block text-sm font-medium text-left">Observações</label>
      <textarea name="observacoes" class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" placeholder="Escreva aqui suas observações" rows="2"></textarea>
      <label class="block text-sm font-medium text-left">Informações fixas</label>
      <textarea name="informacoes_fixas" class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" rows="2" placeholder="Espaço destinado para informações fixas"></textarea>
      <label class="block text-sm font-medium text-left">Orientações</label>
      <textarea name="orientacoes" class="w-full border rounded px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" rows="2" placeholder="Espaço destinado para orientações ao paciente"></textarea>
      
      <!-- Seção de Preparos -->
      <div class="mt-4">
        <div class="flex justify-between items-center mb-3">
          <label class="block text-sm font-medium text-gray-700">Preparos para exames/consultas</label>
          <button type="button" onclick="window.abrirModalPreparo()" class="flex items-center gap-1 text-teal-700 hover:underline text-sm font-bold">
            <i class="bi bi-plus-circle"></i> Adicionar preparo
          </button>
        </div>
        
        <!-- Lista de preparos -->
        <div id="lista-preparos" class="space-y-2">
          <!-- Preparos serão adicionados aqui dinamicamente -->
        </div>
        
        <!-- Mensagem quando não há preparos -->
        <div id="sem-preparos" class="text-sm text-gray-500 text-center py-4 border-2 border-dashed border-gray-200 rounded">
          Nenhum preparo adicionado. Clique em "Adicionar preparo" para começar.
        </div>
      </div>
    </div>
  </div>
      <hr class="mt-6 border-gray-300">

    <!-- Rodapé com botão de voltar e ações -->
    <div class="flex justify-between items-center mt-6">
        <button onclick="voltarParaListagem()" type="button" class="text-red-600 hover:underline">← Voltar para Pesquisa</button>
        <div class="flex gap-4">
            <button type="button" onclick="mostrarFormulario('pesquisa')" class="text-gray-500 hover:underline">Cancelar</button>
            <button type="submit" class="bg-teal-700 text-white px-4 py-2 rounded hover:bg-teal-800">Salvar informações</button>
        </div>
    </div>
    
    <!-- Modal para adicionar/editar preparo -->
    <div id="modal-preparo" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
        <div class="bg-white rounded-lg w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-800" id="modal-titulo">Adicionar Preparo</h3>
                    <button type="button" onclick="window.fecharModalPreparo()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título do preparo *</label>
                        <input type="text" id="preparo-titulo" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                               placeholder="Ex: Preparo para Ultrassom Abdominal">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Instruções de preparo *</label>
                        <textarea id="preparo-instrucoes" rows="8" class="w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                                  placeholder="Descreva as instruções detalhadas do preparo..."></textarea>
                        <p class="text-xs text-gray-500 mt-1">Você pode escrever instruções detalhadas aqui</p>
                    </div>
                    
                    <!-- Seção de anexos -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Anexos (Termos, PDFs, etc.)</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <input type="file" id="preparo-anexos" multiple accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png" 
                                       class="hidden" onchange="handleFileSelect(this)">
                                <button type="button" onclick="document.getElementById('preparo-anexos').click()" 
                                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm">
                                    Selecionar arquivos
                                </button>
                                <p class="text-xs text-gray-500 mt-1">PDF, DOC, TXT, JPG, PNG (máx. 5MB cada)</p>
                            </div>
                        </div>
                        
                        <!-- Lista de arquivos selecionados -->
                        <div id="lista-anexos-preparo" class="mt-3 space-y-2">
                            <!-- Arquivos serão listados aqui -->
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="window.fecharModalPreparo()" class="px-4 py-2 text-gray-600 hover:underline">
                        Cancelar
                    </button>
                    <button type="button" onclick="window.salvarPreparo()" class="px-4 py-2 bg-teal-700 text-white rounded hover:bg-teal-800">
                        Salvar Preparo
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Inicialização para formulário de criar agenda
        document.addEventListener('DOMContentLoaded', function() {
            const selectPrestador = document.getElementById('select-prestador');
            const prestadorObrigatorio = document.getElementById('prestador-obrigatorio');
            
            // Limpar estado inicial - prestador começa como opcional
            if (selectPrestador) {
                selectPrestador.classList.remove('campo-obrigatorio');
                selectPrestador.removeAttribute('required');
            }
            if (prestadorObrigatorio) {
                prestadorObrigatorio.style.display = 'none';
            }
            
            console.log('🔧 Formulário criar agenda - estado inicial limpo');
            
            // Inicializar preparos
            if (typeof inicializarPreparos === 'function') {
                inicializarPreparos();
            }
        });
    </script>
</form>

