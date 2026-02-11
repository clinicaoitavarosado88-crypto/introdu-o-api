<?php
// agendamento_sucesso.php
include 'includes/connection.php';

$agendamento_id = $_GET['id'] ?? 0;

if (!$agendamento_id) {
    header('Location: index.php');
    exit;
}

// Busca informações completas do agendamento
$query = "SELECT ag.*, a.SALA, a.TELEFONE, a.TEMPO_ESTIMADO_MINUTOS,
                 p.NOME as PACIENTE_NOME, p.CPF, p.TELEFONE as PACIENTE_TELEFONE,
                 u.NOME as UNIDADE_NOME, u.ENDERECO as UNIDADE_ENDERECO,
                 pr.NOME as MEDICO_NOME, e.NOME as ESPECIALIDADE_NOME,
                 proc.NOME as PROCEDIMENTO_NOME, c.NOME as CONVENIO_NOME
          FROM AGENDAMENTOS ag
          JOIN AGENDAS a ON ag.AGENDA_ID = a.ID
          JOIN PACIENTES p ON ag.PACIENTE_ID = p.ID
          JOIN UNIDADES u ON a.UNIDADE_ID = u.ID
          JOIN CONVENIOS c ON ag.CONVENIO_ID = c.ID
          LEFT JOIN LAB_MEDICOS_PRES pr ON a.MEDICO_ID = pr.ID
          LEFT JOIN LAB_MEDICOS_ESPECIALIDADES me ON pr.ID = me.MEDICO_ID
          LEFT JOIN ESPECIALIDADES e ON me.ESPECIALIDADE_ID = e.ID
          LEFT JOIN PROCEDIMENTOS proc ON a.PROCEDIMENTO_ID = proc.ID
          WHERE ag.ID = ?";

$stmt = ibase_prepare($conn, $query);
$result = ibase_execute($stmt, $agendamento_id);
$agendamento = ibase_fetch_assoc($result);

if (!$agendamento) {
    header('Location: index.php');
    exit;
}

// Determina o tipo de agenda
$tipo_agenda = !empty($agendamento['ESPECIALIDADE_NOME']) ? 'consulta' : 'procedimento';
$nome_agenda = $tipo_agenda === 'consulta' 
    ? "Dr(a). " . utf8_encode($agendamento['MEDICO_NOME']) . " - " . utf8_encode($agendamento['ESPECIALIDADE_NOME'])
    : utf8_encode($agendamento['PROCEDIMENTO_NOME']);

// Formata a data
$data_obj = new DateTime($agendamento['DATA_AGENDAMENTO']);
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

// Formata horário
$horario = substr($agendamento['HORA_AGENDAMENTO'], 0, 5);

// Formata CPF
$cpf = substr($agendamento['CPF'], 0, 3) . '.' . 
       substr($agendamento['CPF'], 3, 3) . '.' . 
       substr($agendamento['CPF'], 6, 3) . '-' . 
       substr($agendamento['CPF'], 9, 2);

?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agendamento Confirmado - Clínica Oitava Rosado</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-3xl mx-auto px-4">
            <!-- Cabeçalho de sucesso -->
            <div class="bg-white rounded-lg shadow-md p-8 mb-6 text-center">
                <div class="mb-6">
                    <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                        <i class="bi bi-check-circle-fill text-3xl text-green-600"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Agendamento Confirmado!</h1>
                    <p class="text-gray-600">Seu agendamento foi realizado com sucesso.</p>
                </div>
                
                <!-- Número do agendamento -->
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 inline-block">
                    <p class="text-sm text-green-700 mb-1">Número do Agendamento</p>
                    <p class="text-xl font-bold text-green-800"><?= htmlspecialchars($agendamento['NUMERO_AGENDAMENTO']) ?></p>
                </div>
            </div>
            
            <!-- Detalhes do agendamento -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Detalhes do Agendamento</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Informações do serviço -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="bi bi-calendar-event text-teal-600 mr-2"></i>
                            Informações do Atendimento
                        </h3>
                        <div class="space-y-2 text-sm">
                            <p><strong>Serviço:</strong> <?= htmlspecialchars($nome_agenda) ?></p>
                            <p><strong>Data:</strong> <?= $dia_semana ?>, <?= $data_formatada ?></p>
                            <p><strong>Horário:</strong> <?= $horario ?></p>
                            <p><strong>Convênio:</strong> <?= htmlspecialchars(utf8_encode($agendamento['CONVENIO_NOME'])) ?></p>
                            <?php if ($agendamento['TIPO_CONSULTA'] === 'retorno'): ?>
                                <p><strong>Tipo:</strong> <span class="text-blue-600">Retorno</span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Informações do local -->
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                            <i class="bi bi-geo-alt text-teal-600 mr-2"></i>
                            Local do Atendimento
                        </h3>
                        <div class="space-y-2 text-sm">
                            <p><strong>Unidade:</strong> <?= htmlspecialchars(utf8_encode($agendamento['UNIDADE_NOME'])) ?></p>
                            <p><strong>Sala:</strong> <?= htmlspecialchars($agendamento['SALA']) ?></p>
                            <p><strong>Telefone:</strong> <?= htmlspecialchars($agendamento['TELEFONE']) ?></p>
                            <?php if ($agendamento['UNIDADE_ENDERECO']): ?>
                                <p><strong>Endereço:</strong> <?= htmlspecialchars(utf8_encode($agendamento['UNIDADE_ENDERECO'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informações do paciente -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-semibold text-gray-800 mb-4">Dados do Paciente</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <p><strong>Nome:</strong> <?= htmlspecialchars(utf8_encode($agendamento['PACIENTE_NOME'])) ?></p>
                        <p><strong>CPF:</strong> <?= $cpf ?></p>
                    </div>
                    <div>
                        <p><strong>Telefone:</strong> <?= htmlspecialchars($agendamento['PACIENTE_TELEFONE']) ?></p>
                        <p><strong>Status:</strong> <span class="text-green-600 font-medium"><?= ucfirst(strtolower($agendamento['STATUS'])) ?></span></p>
                    </div>
                </div>
            </div>
            
            <!-- Instruções importantes -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-yellow-800 mb-3 flex items-center">
                    <i class="bi bi-exclamation-triangle text-yellow-600 mr-2"></i>
                    Instruções Importantes
                </h3>
                <ul class="text-sm text-yellow-700 space-y-2">
                    <li class="flex items-start">
                        <i class="bi bi-clock text-yellow-600 mr-2 mt-0.5"></i>
                        Chegue com <strong>15 minutos de antecedência</strong> do horário marcado.
                    </li>
                    <li class="flex items-start">
                        <i class="bi bi-card-text text-yellow-600 mr-2 mt-0.5"></i>
                        Traga um <strong>documento com foto</strong> e a <strong>carteirinha do convênio</strong>.
                    </li>
                    <li class="flex items-start">
                        <i class="bi bi-phone text-yellow-600 mr-2 mt-0.5"></i>
                        Em caso de cancelamento, entre em contato com <strong>48 horas de antecedência</strong>.
                    </li>
                    <li class="flex items-start">
                        <i class="bi bi-file-medical text-yellow-600 mr-2 mt-0.5"></i>
                        Traga todos os <strong>exames anteriores</strong> relacionados ao atendimento.
                    </li>
                </ul>
            </div>
            
            <!-- Ações -->
            <div class="flex flex-col sm:flex-row gap-4">
                <button onclick="imprimirComprovante()" 
                        class="flex-1 bg-teal-600 text-white py-3 px-6 rounded-md hover:bg-teal-700 transition flex items-center justify-center">
                    <i class="bi bi-printer mr-2"></i>
                    Imprimir Comprovante
                </button>
                
                <button onclick="voltarInicio()" 
                        class="flex-1 bg-gray-600 text-white py-3 px-6 rounded-md hover:bg-gray-700 transition flex items-center justify-center">
                    <i class="bi bi-house mr-2"></i>
                    Voltar ao Início
                </button>
            </div>
        </div>
    </div>
    
    <script>
    function imprimirComprovante() {
        window.print();
    }
    
    function voltarInicio() {
        window.location.href = 'index.php';
    }
    
    // Confetti animation on page load (opcional)
    setTimeout(() => {
        const colors = ['#10B981', '#0D9488', '#06B6D4'];
        // Aqui você pode adicionar uma animação de confetti se desejar
    }, 500);
    </script>
    
    <!-- Estilos para impressão -->
    <style media="print">
        body { 
            background: white !important; 
        }
        .no-print { 
            display: none !important; 
        }
        .shadow-md { 
            box-shadow: none !important; 
        }
    </style>
</body>
</html>