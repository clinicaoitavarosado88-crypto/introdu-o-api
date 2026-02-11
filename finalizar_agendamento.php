<?php
// finalizar_agendamento.php
include 'includes/connection.php';

$agenda_id = $_GET['agenda_id'] ?? 0;
$data = $_GET['data'] ?? '';
$horario = $_GET['horario'] ?? '';

if (!$agenda_id || !$data || !$horario) {
    echo '<div class="text-red-600 text-center p-8">Dados incompletos para o agendamento.</div>';
    exit;
}

// Busca informações da agenda
$query = "SELECT a.*, u.NOME_UNIDADE as UNIDADE_NOME, med.NOME as MEDICO_NOME, e.NOME as ESPECIALIDADE_NOME, 
                 pr.NOME as PROCEDIMENTO_NOME
          FROM AGENDAS a 
          LEFT JOIN LAB_CIDADES u ON a.UNIDADE_ID = u.ID
          LEFT JOIN LAB_MEDICOS_PRES med ON a.MEDICO_ID = med.ID
          LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON med.ID = me.MEDICO_ID
          LEFT JOIN ESPECIALIDADES e ON me.ESPECIALIDADE_ID = e.ID
          LEFT JOIN GRUPO_EXAMES pr ON a.PROCEDIMENTO_ID = pr.ID
          WHERE a.ID = ?";

$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agenda_id);
$agenda = ibase_fetch_assoc($result);

if (!$agenda) {
    echo '<div class="text-red-600 text-center p-8">Agenda não encontrada.</div>';
    exit;
}

// Busca convênios disponíveis
$query_convenios = "SELECT c.ID, c.NOME 
                    FROM AGENDA_CONVENIOS ac
                    JOIN CONVENIOS c ON c.ID = ac.CONVENIO_ID
                    WHERE ac.AGENDA_ID = ?
                    ORDER BY c.NOME";

$stmt_convenios = ibase_prepare($conn, $query_convenios);
$result_convenios = ibase_execute($stmt_convenios, $agenda_id);

$convenios = [];
while ($convenio = ibase_fetch_assoc($result_convenios)) {
    $convenios[] = [
        'id' => $convenio['ID'],
        'nome' => utf8_encode($convenio['NOME'])
    ];
}

// Determina o tipo de agenda
$tipo_agenda = !empty($agenda['ESPECIALIDADE_ID']) ? 'consulta' : 'procedimento';
$nome_agenda = $tipo_agenda === 'consulta' 
    ? "Dr(a). " . utf8_encode($agenda['MEDICO_NOME']) . " - " . utf8_encode($agenda['ESPECIALIDADE_NOME'])
    : utf8_encode($agenda['PROCEDIMENTO_NOME']);

// Formata a data para exibição
$data_obj = new DateTime($data);
$data_formatada = $data_obj->format('d/m/Y');
$dia_semana = [
    'Sunday' => 'Domingo',
    'Monday' => 'Segunda-feira',
    'Tuesday' => 'Terça-feira', 
    'Wednesday' => 'Quarta-feira',
    'Thursday' => 'Quinta-feira',
    'Friday' => 'Sexta-feira',
    'Saturday' => 'Sábado'
][$data_obj->format('l')];

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalizar Agendamento - Clínica Oitava Rosado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-2xl mx-auto px-4">
            <!-- Cabeçalho -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="text-center mb-4">
                    <i class="bi bi-calendar-check text-4xl text-teal-600 mb-2"></i>
                    <h1 class="text-2xl font-bold text-gray-800">Finalizar Agendamento</h1>
                </div>
                
                <!-- Resumo do agendamento -->
                <div class="bg-teal-50 border border-teal-200 rounded-lg p-4">
                    <h2 class="text-lg font-semibold text-teal-800 mb-3">
                        <?= htmlspecialchars($nome_agenda) ?>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div>
                            <p><strong>Data:</strong> <?= $dia_semana ?>, <?= $data_formatada ?></p>
                            <p><strong>Horário:</strong> <?= $horario ?></p>
                        </div>
                        <div>
                            <p><strong>Unidade:</strong> <?= htmlspecialchars(utf8_encode($agenda['UNIDADE_NOME'])) ?></p>
                            <p><strong>Sala:</strong> <?= htmlspecialchars($agenda['SALA']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulário de agendamento -->
            <form id="form-agendamento" class="bg-white rounded-lg shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Dados do Paciente</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nome completo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="nome_paciente" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            CPF <span class="text-red-500">*</span>
                        </label>
                        <input type="text" name="cpf_paciente" id="cpf" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                               placeholder="000.000.000-00">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Data de nascimento <span class="text-red-500">*</span>
                        </label>
                        <input type="date" name="data_nascimento" id="data_nascimento" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                               onchange="calcularIdade()">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Idade <span class="text-red-500">*</span>
                        </label>
                        <input type="number" name="idade" id="idade" required readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 focus:outline-none focus:ring-2 focus:ring-teal-500"
                               placeholder="Calculado automaticamente">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Telefone <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="telefone_paciente" id="telefone" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                               placeholder="(84) 99999-9999">
                    </div>
                    
                    <?php if ($tipo_agenda === 'consulta'): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de consulta <span class="text-red-500">*</span>
                        </label>
                        <select name="tipo_consulta" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">Selecione o tipo</option>
                            <option value="primeira_vez">Primeira vez</option>
                            <option value="retorno">Retorno</option>
                            <option value="urgencia">Urgência</option>
                            <option value="rotina">Rotina</option>
                            <option value="revisao">Revisão</option>
                            <option value="seguimento">Seguimento</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            E-mail
                        </label>
                        <input type="email" name="email_paciente"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                               placeholder="exemplo@email.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Convênio <span class="text-red-500">*</span>
                        </label>
                        <select name="convenio_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                            <option value="">Selecione o convênio</option>
                            <?php foreach ($convenios as $convenio): ?>
                                <option value="<?= $convenio['id'] ?>"><?= htmlspecialchars($convenio['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <?php if ($tipo_agenda === 'consulta' && ($agenda['POSSUI_RETORNO'] ?? 0)): ?>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo de consulta
                    </label>
                    <div class="flex space-x-4">
                        <label class="flex items-center">
                            <input type="radio" name="tipo_consulta" value="primeira_vez" checked
                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Primeira vez</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="tipo_consulta" value="retorno"
                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300">
                            <span class="ml-2 text-sm text-gray-700">Retorno</span>
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Observações
                    </label>
                    <textarea name="observacoes" rows="3"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                              placeholder="Alguma observação especial sobre o agendamento..."></textarea>
                </div>
                
                <!-- Campos ocultos -->
                <input type="hidden" name="agenda_id" value="<?= $agenda_id ?>">
                <input type="hidden" name="data_agendamento" value="<?= $data ?>">
                <input type="hidden" name="horario_agendamento" value="<?= $horario ?>">
                
                <!-- Botões -->
                <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <button type="button" onclick="voltarAgendamento()" 
                            class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                        <i class="bi bi-arrow-left mr-2"></i>Voltar
                    </button>
                    
                    <button type="submit" 
                            class="px-6 py-3 bg-teal-600 text-white rounded-md hover:bg-teal-700 transition">
                        <i class="bi bi-check-circle mr-2"></i>Confirmar Agendamento
                    </button>
                </div>
            </form>
            
            <!-- Loading overlay -->
            <div id="loading" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg text-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-teal-600 mx-auto mb-4"></div>
                    <p>Processando agendamento...</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Máscara para CPF
    document.getElementById('cpf').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    });
    
    // Máscara para telefone
    document.getElementById('telefone').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
        e.target.value = value;
    });
    
    // Função para calcular idade automaticamente
    function calcularIdade() {
        const dataNascimento = document.getElementById('data_nascimento').value;
        const campoIdade = document.getElementById('idade');
        
        if (dataNascimento) {
            const hoje = new Date();
            const nascimento = new Date(dataNascimento);
            
            // Verificar se a data é válida
            if (nascimento > hoje) {
                alert('Data de nascimento não pode ser futura');
                document.getElementById('data_nascimento').value = '';
                campoIdade.value = '';
                return;
            }
            
            let idade = hoje.getFullYear() - nascimento.getFullYear();
            const mesAtual = hoje.getMonth();
            const mesNascimento = nascimento.getMonth();
            
            // Ajustar idade se ainda não fez aniversário neste ano
            if (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < nascimento.getDate())) {
                idade--;
            }
            
            campoIdade.value = idade;
            
            // Validação de idade mínima (se necessário)
            if (idade < 0) {
                alert('Idade inválida');
                campoIdade.value = '';
            }
        } else {
            campoIdade.value = '';
        }
    }
    
    // Submissão do formulário - garante que só execute uma vez
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('form-agendamento');
        
        // Verifica se já tem listener para evitar duplicação
        if (form && !form.getAttribute('data-listener-added')) {
            form.setAttribute('data-listener-added', 'true');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validação mais robusta
                const campos = this.querySelectorAll('[required]');
                let valido = true;
                let camposInvalidos = [];
                
                campos.forEach(campo => {
                    if (!campo.value || !campo.value.trim()) {
                        campo.classList.add('border-red-500');
                        camposInvalidos.push(campo.name || campo.id || 'Campo obrigatório');
                        valido = false;
                    } else {
                        campo.classList.remove('border-red-500');
                    }
                });
                
                if (!valido) {
                    console.log('Campos inválidos:', camposInvalidos);
                    alert('Por favor, preencha todos os campos obrigatórios: ' + camposInvalidos.join(', '));
                    return false;
                }
                
                // Previne múltiplos submits
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn.disabled) return false;
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-clock mr-2"></i>Processando...';
                
                // Mostra loading
                const loading = document.getElementById('loading');
                loading.classList.remove('hidden');
                loading.classList.add('flex');
                
                // Envia dados
                const formData = new FormData(this);
                
                fetch('processar_agendamento.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'sucesso') {
                        // Redireciona para página de sucesso
                        window.location.href = `agendamento_sucesso.php?id=${data.agendamento_id}`;
                    } else {
                        alert('Erro: ' + data.mensagem);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erro ao processar agendamento. Tente novamente.');
                })
                .finally(() => {
                    loading.classList.add('hidden');
                    loading.classList.remove('flex');
                    // Reabilita o botão em caso de erro
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle mr-2"></i>Confirmar Agendamento';
                });
            });
        }
    });
    
    // Função para voltar ao agendamento
    function voltarAgendamento() {
        if (confirm('Deseja realmente voltar? Os dados preenchidos serão perdidos.')) {
            history.back();
        }
    }
    </script>
</body>
</html>