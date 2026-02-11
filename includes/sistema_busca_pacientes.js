// ===== SISTEMA DE BUSCA DE PACIENTES =====

let timeoutBusca = null;
let pacienteSelecionado = null;

// Função específica para configurar busca básica no contexto de retorno
function configurarBuscaBasicaRetorno(nomeInput) {
    if (!nomeInput) {
        console.error('Input de nome não fornecido para busca de retorno');
        return;
    }
    
    // Para o contexto de retorno, apenas configurar validação básica
    nomeInput.addEventListener('input', function() {
        const termo = this.value.trim();
        
        // Limpar seleção anterior
        pacienteSelecionado = null;
        const pacienteIdInput = document.getElementById('paciente_existente_id_retorno');
        if (pacienteIdInput) {
            pacienteIdInput.value = '';
        }
        
        // Se o termo tem menos de 2 caracteres, não fazer busca
        if (termo.length < 2) {
            return;
        }
        
        console.log('🔍 Busca básica de retorno:', termo);
        
        // Implementar busca real
        realizarBuscaPacienteRetorno(termo);
    });
    
    console.log('✅ Busca básica configurada para retorno');
}

// Função para realizar busca de paciente no contexto de retorno
function realizarBuscaPacienteRetorno(termo) {
    // Limpar timeout anterior
    if (timeoutBusca) {
        clearTimeout(timeoutBusca);
    }
    
    // Definir nova busca com delay
    timeoutBusca = setTimeout(() => {
        console.log('🔍 Executando busca de retorno para:', termo);
        
        fetch('buscar_paciente.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `termo=${encodeURIComponent(termo)}`
        })
        .then(response => response.json())
        .then(data => {
            console.log('📋 Resultados da busca de retorno:', data);
            
            if (data.status === 'sucesso' && data.pacientes && data.pacientes.length > 0) {
                mostrarResultadosBuscaRetorno(data.pacientes);
            } else {
                console.log('❌ Nenhum paciente encontrado para retorno');
                esconderResultadosBuscaRetorno();
            }
        })
        .catch(error => {
            console.error('❌ Erro na busca de pacientes para retorno:', error);
            esconderResultadosBuscaRetorno();
        });
    }, 300); // Delay de 300ms
}

// Função para mostrar resultados da busca no contexto de retorno
function mostrarResultadosBuscaRetorno(pacientes) {
    let dropdown = document.getElementById('dropdown-resultados-retorno');
    
    // Se não existe, criar dinamicamente
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'dropdown-resultados-retorno';
        dropdown.className = 'absolute z-50 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-y-auto';
        
        const nomeInput = document.getElementById('nome_paciente_retorno');
        if (nomeInput && nomeInput.parentNode) {
            nomeInput.parentNode.appendChild(dropdown);
        }
    }
    
    dropdown.innerHTML = '';
    
    pacientes.forEach(paciente => {
        const item = document.createElement('div');
        item.className = 'p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-200';
        item.innerHTML = `
            <div class="font-semibold">${paciente.nome}</div>
            <div class="text-sm text-gray-600">CPF: ${paciente.cpf} | Tel: ${paciente.telefone}</div>
        `;
        
        item.addEventListener('click', () => {
            selecionarPacienteRetorno(paciente);
        });
        
        dropdown.appendChild(item);
    });
    
    dropdown.classList.remove('hidden');
}

// Função para selecionar paciente no contexto de retorno
function selecionarPacienteRetorno(paciente) {
    console.log('👤 Paciente selecionado para retorno:', paciente);
    
    // Preencher campos
    const nomeInput = document.getElementById('nome_paciente_retorno');
    const cpfInput = document.getElementById('cpf_paciente_retorno');
    const telefoneInput = document.getElementById('telefone_paciente_retorno');
    const pacienteIdInput = document.getElementById('paciente_existente_id_retorno');
    
    if (nomeInput) nomeInput.value = paciente.nome;
    if (cpfInput) cpfInput.value = paciente.cpf;
    if (telefoneInput) telefoneInput.value = paciente.telefone;
    if (pacienteIdInput) pacienteIdInput.value = paciente.id;
    
    // Armazenar seleção global
    pacienteSelecionado = paciente;
    
    // Esconder dropdown
    esconderResultadosBuscaRetorno();
}

// Função para esconder resultados da busca no contexto de retorno
function esconderResultadosBuscaRetorno() {
    const dropdown = document.getElementById('dropdown-resultados-retorno');
    if (dropdown) {
        dropdown.classList.add('hidden');
    }
}

// Configurar busca em tempo real
function configurarBuscaPaciente(customNomeInput = null, customTelefoneInput = null, contexto = 'default') {
    let inputBusca, dropdown;
    
    // Se parâmetros customizados foram fornecidos (para contexto de retorno)
    if (customNomeInput && contexto === 'retorno') {
        inputBusca = customNomeInput;
        dropdown = document.getElementById('dropdown-resultados-retorno');
        
        // Se o dropdown não existe, criar temporariamente ou usar fallback
        if (!dropdown) {
            console.log('📝 Dropdown para retorno não encontrado, configurando busca básica');
            configurarBuscaBasicaRetorno(customNomeInput);
            return;
        }
    } else {
        // Comportamento padrão
        inputBusca = document.getElementById('busca-paciente-select');
        dropdown = document.getElementById('dropdown-resultados');
    }
    
    if (!inputBusca || !dropdown) {
        console.error('Elementos de busca não encontrados');
        return;
    }
    
    // Busca em tempo real
    inputBusca.addEventListener('input', function(e) {
        const termo = e.target.value.trim();
        
        // Limpar timeout anterior
        if (timeoutBusca) {
            clearTimeout(timeoutBusca);
        }
        
        // Limpar seleção anterior
        pacienteSelecionado = null;
        document.getElementById('paciente-existente-id').value = '';
        
        if (termo.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        // Debounce de 300ms
        timeoutBusca = setTimeout(() => {
            buscarPacientes(termo);
        }, 300);
    });
    
    // Esconder dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!inputBusca.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Mostrar dropdown ao focar
    inputBusca.addEventListener('focus', function() {
        if (dropdown.children.length > 0) {
            dropdown.classList.remove('hidden');
        }
    });
}

// Buscar pacientes via AJAX
function buscarPacientes(termo) {
    const dropdown = document.getElementById('dropdown-resultados');
    
    // Mostrar loading
    dropdown.innerHTML = `
        <div class="p-3 text-center text-gray-500">
            <i class="bi bi-arrow-clockwise spin mr-2"></i>Buscando...
        </div>
    `;
    dropdown.classList.remove('hidden');
    
    // Fazer requisição
    const formData = new FormData();
    formData.append('termo', termo);
    
    fetch('buscar_paciente.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Resposta da busca:', data);
        
        if (data.status === 'sucesso' && data.pacientes.length > 0) {
            exibirResultadosBusca(data.pacientes);
        } else {
            dropdown.innerHTML = `
                <div class="p-3 text-center text-gray-500">
                    <i class="bi bi-person-x mr-2"></i>Nenhum paciente encontrado
                    <div class="text-xs mt-1">Cadastre um novo paciente</div>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Erro na busca:', error);
        dropdown.innerHTML = `
            <div class="p-3 text-center text-red-500">
                <i class="bi bi-exclamation-triangle mr-2"></i>Erro ao buscar
            </div>
        `;
    });
}

// Exibir resultados da busca
function exibirResultadosBusca(pacientes) {
    const dropdown = document.getElementById('dropdown-resultados');
    
    let html = '';
    pacientes.forEach((paciente, index) => {
        const idade = calcularIdade(paciente.data_nascimento);
        const idadeTexto = idade ? ` (${idade} anos)` : '';
        
        html += `
            <div class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0" 
                 onclick="selecionarPaciente(${index})">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="font-medium text-gray-900">
                            ${paciente.nome}${idadeTexto}
                        </div>
                        <div class="text-sm text-gray-600">
                            CPF: ${paciente.cpf} • Tel: ${paciente.telefone}
                        </div>
                        ${paciente.email ? `<div class="text-xs text-gray-500">${paciente.email}</div>` : ''}
                    </div>
                    <div class="text-xs text-gray-400 ml-2">
                        <i class="bi bi-arrow-right"></i>
                    </div>
                </div>
            </div>
        `;
    });
    
    dropdown.innerHTML = html;
    
    // Armazenar pacientes para seleção
    window.pacientesEncontrados = pacientes;
}

// Selecionar paciente da busca
function selecionarPaciente(index) {
    const pacientes = window.pacientesEncontrados || [];
    if (!pacientes[index]) return;
    
    const paciente = pacientes[index];
    pacienteSelecionado = paciente;
    
    // Preencher formulário
    document.getElementById('nome-paciente').value = paciente.nome;
    document.getElementById('cpf-paciente').value = paciente.cpf;
    document.getElementById('telefone-paciente').value = paciente.telefone;
    document.getElementById('email-paciente').value = paciente.email || '';
    document.getElementById('data-nascimento').value = paciente.data_nascimento || '';
    document.getElementById('paciente-existente-id').value = paciente.id;
    
    // Atualizar campo de busca
    document.getElementById('busca-paciente-select').value = `${paciente.nome} (${paciente.cpf})`;
    
    // Esconder dropdown
    document.getElementById('dropdown-resultados').classList.add('hidden');
    
    // Desabilitar checkbox de cadastro (paciente já existe)
    const checkboxCadastro = document.getElementById('cadastrar-paciente');
    if (checkboxCadastro) {
        checkboxCadastro.checked = false;
        checkboxCadastro.disabled = true;
        checkboxCadastro.parentElement.style.opacity = '0.5';
    }
    
    // Focar no próximo campo
    setTimeout(() => {
        const convenioSelect = document.querySelector('select[name="convenio_id"]');
        if (convenioSelect) convenioSelect.focus();
    }, 100);
    
    mostrarFeedback('Paciente selecionado! Dados preenchidos automaticamente.', 'sucesso');
}

// Limpar seleção de paciente
function limparSelecaoPaciente() {
    pacienteSelecionado = null;
    document.getElementById('paciente-existente-id').value = '';
    
    // Reabilitar checkbox de cadastro
    const checkboxCadastro = document.getElementById('cadastrar-paciente');
    if (checkboxCadastro) {
        checkboxCadastro.disabled = false;
        checkboxCadastro.parentElement.style.opacity = '1';
    }
}

// Calcular idade
function calcularIdade(dataNascimento) {
    if (!dataNascimento) return null;
    
    const hoje = new Date();
    const nascimento = new Date(dataNascimento);
    let idade = hoje.getFullYear() - nascimento.getFullYear();
    
    const mesAtual = hoje.getMonth();
    const mesNascimento = nascimento.getMonth();
    
    if (mesAtual < mesNascimento || (mesAtual === mesNascimento && hoje.getDate() < nascimento.getDate())) {
        idade--;
    }
    
    return idade;
}

// Configurar validação de paciente
function configurarValidacaoPaciente() {
    const nomeInput = document.getElementById('nome-paciente');
    const cpfInput = document.getElementById('cpf-paciente');
    const telefoneInput = document.getElementById('telefone-paciente');
    
    // Limpar seleção ao alterar campos manualmente
    [nomeInput, cpfInput, telefoneInput].forEach(input => {
        if (input) {
            input.addEventListener('input', function() {
                // Se o usuário alterar manualmente, limpar a seleção
                if (pacienteSelecionado) {
                    limparSelecaoPaciente();
                }
            });
        }
    });
}

// Validar se paciente foi selecionado ou deve ser cadastrado
function validarPaciente() {
    const pacienteId = document.getElementById('paciente-existente-id').value;
    const cadastrarPaciente = document.getElementById('cadastrar-paciente')?.checked;
    const nomeInput = document.getElementById('nome-paciente');
    const cpfInput = document.getElementById('cpf-paciente');
    
    // Se não tem ID do paciente e não marcou para cadastrar
    if (!pacienteId && !cadastrarPaciente) {
        mostrarFeedback('Selecione um paciente existente ou marque a opção para cadastrar um novo paciente.', 'erro');
        nomeInput.focus();
        return false;
    }
    
    // Se vai cadastrar, validar campos obrigatórios
    if (cadastrarPaciente) {
        if (!nomeInput.value.trim()) {
            mostrarFeedback('Nome do paciente é obrigatório.', 'erro');
            nomeInput.focus();
            return false;
        }
        
        // Verificar se CPF é obrigatório (checkbox "não tem CPF")
        const naoTemCpf = document.getElementById('nao_tem_cpf')?.checked || 
                         document.getElementById('nao_tem_cpf_agendamento')?.checked || false;
        
        if (!naoTemCpf && !cpfInput.value.trim()) {
            mostrarFeedback('CPF do paciente é obrigatório. Marque "Não tem CPF" se o paciente não possuir.', 'erro');
            cpfInput.focus();
            return false;
        }
        
        // Validar CPF apenas se preenchido
        const cpf = cpfInput.value.replace(/[^0-9]/g, '');
        if (cpf.length > 0 && cpf.length !== 11) {
            mostrarFeedback('CPF deve ter 11 dígitos válidos.', 'erro');
            cpfInput.focus();
            return false;
        }
    }
    
    return true;
}

// Atualizar função de salvar encaixe
function salvarEncaixeComValidacao() {
    console.log('💾 Iniciando salvamento do encaixe...');
    
    // Validar paciente primeiro
    if (!validarPaciente()) {
        return;
    }
    
    const form = document.getElementById('form-encaixe-flexivel');
    if (!form) {
        mostrarFeedback('Formulário não encontrado.', 'erro');
        return;
    }
    
    const formData = new FormData(form);
    
    // Adicionar ID do paciente se selecionado
    const pacienteId = document.getElementById('paciente-existente-id').value;
    if (pacienteId) {
        formData.append('paciente_id', pacienteId);
        console.log('📋 Paciente selecionado ID:', pacienteId);
    } else {
        // Verificar se deve cadastrar
        const cadastrarPaciente = document.getElementById('cadastrar-paciente')?.checked;
        if (cadastrarPaciente) {
            formData.append('cadastrar_paciente', 'true');
            console.log('📋 Novo paciente será cadastrado');
        }
    }
    
    // Validação de campos obrigatórios
    const camposObrigatorios = form.querySelectorAll('[required]');
    let valido = true;
    
    camposObrigatorios.forEach(campo => {
        if (!campo.value.trim()) {
            campo.classList.add('border-red-500');
            valido = false;
        } else {
            campo.classList.remove('border-red-500');
        }
    });
    
    if (!valido) {
        mostrarFeedback('Por favor, preencha todos os campos obrigatórios.', 'erro');
        return;
    }
    
    // Mostrar loading
    const btnSalvar = document.querySelector('[onclick="salvarEncaixeComValidacao()"]');
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="bi bi-arrow-clockwise spin mr-2"></i>Salvando...';
    }
    
    // Fazer requisição
    fetch('processar_encaixe.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('📄 Resposta:', data);
        
        if (data.status === 'sucesso') {
            mostrarFeedback('Encaixe realizado com sucesso!', 'sucesso');
            
            // Fechar modal após 1 segundo
            setTimeout(() => {
                fecharModalEncaixe();
                atualizarVisualizacaoAgenda(); // Atualizar a agenda
            }, 1000);
        } else {
            mostrarFeedback(data.mensagem || 'Erro ao salvar encaixe.', 'erro');
        }
    })
    .catch(error => {
        console.error('❌ Erro:', error);
        mostrarFeedback('Erro de conexão. Tente novamente.', 'erro');
    })
    .finally(() => {
        // Restaurar botão
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = '<i class="bi bi-check mr-2"></i>Salvar Encaixe';
        }
    });
}

// Configurar máscaras
function configurarMascarasPaciente() {
    // Máscara CPF
    const cpfInput = document.getElementById('cpf-paciente');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara Telefone
    const telefoneInput = document.getElementById('telefone-paciente');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 10) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{4})(\d{4})$/, '$1-$2');
            } else {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d{4})$/, '$1-$2');
            }
            e.target.value = value;
        });
    }
}

// Inicializar sistema
function inicializarSistemaBuscaPaciente() {
    configurarBuscaPaciente();
    configurarValidacaoPaciente();
    configurarMascarasPaciente();
    
    console.log('✅ Sistema de busca de pacientes inicializado');
}

// Função auxiliar para mostrar feedback
function mostrarFeedback(mensagem, tipo = 'info') {
    // Remover feedback anterior
    const feedbackAnterior = document.querySelector('.feedback-message');
    if (feedbackAnterior) {
        feedbackAnterior.remove();
    }
    
    // Cores por tipo
    const cores = {
        'sucesso': 'bg-green-100 border-green-500 text-green-700',
        'erro': 'bg-red-100 border-red-500 text-red-700',
        'info': 'bg-blue-100 border-blue-500 text-blue-700',
        'aviso': 'bg-yellow-100 border-yellow-500 text-yellow-700'
    };
    
    // Criar elemento de feedback
    const feedback = document.createElement('div');
    feedback.className = `feedback-message fixed top-4 right-4 p-4 border-l-4 rounded shadow-lg z-50 ${cores[tipo] || cores.info}`;
    feedback.innerHTML = `
        <div class="flex items-center">
            <span>${mensagem}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg leading-none">&times;</button>
        </div>
    `;
    
    document.body.appendChild(feedback);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (feedback && feedback.parentElement) {
            feedback.remove();
        }
    }, 5000);
}

// Exportar funções globais
window.inicializarSistemaBuscaPaciente = inicializarSistemaBuscaPaciente;
window.selecionarPaciente = selecionarPaciente;
window.salvarEncaixeComValidacao = salvarEncaixeComValidacao;
window.mostrarFeedback = mostrarFeedback;