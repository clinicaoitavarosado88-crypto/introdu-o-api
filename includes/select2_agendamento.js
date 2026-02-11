/**
 * Funções Select2 para Modal de Agendamento
 * Melhora a performance e usabilidade da busca de pacientes e seleção de exames
 */

/**
 * Configurar Select2 para busca de pacientes
 */
function configurarSelect2Pacientes() {
    const selectElement = document.getElementById('busca-paciente-select');
    if (!selectElement || typeof $ === 'undefined') {
        console.warn('Select2 ou jQuery não disponível para pacientes');
        return;
    }

    $(selectElement).select2({
        placeholder: 'Digite o nome, CPF ou telefone do paciente...',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: 'buscar_paciente.php',
            dataType: 'json',
            delay: 300,
            data: function (params) {
                return {
                    termo: params.term
                };
            },
            processResults: function (data) {
                if (data.status === 'sucesso') {
                    return {
                        results: data.pacientes.map(paciente => ({
                            id: paciente.id,
                            text: `${paciente.nome} - ${paciente.cpf}`,
                            paciente: paciente
                        }))
                    };
                }
                return { results: [] };
            },
            cache: true
        }
    }).on('select2:select', function (e) {
        const paciente = e.params.data.paciente;
        preencherDadosPaciente(paciente);
    }).on('select2:clear', function () {
        limparDadosPaciente();
    });
}

/**
 * Configurar Select2 para convênios
 */
function configurarSelect2Convenios() {
    const selectElement = document.getElementById('convenio_agendamento');
    if (!selectElement || typeof $ === 'undefined') {
        console.warn('Select2 ou jQuery não disponível para convênios');
        return;
    }

    $(selectElement).select2({
        placeholder: 'Selecione o convênio',
        allowClear: true
    });
}

/**
 * Configurar Select2 para exames
 */
function configurarSelect2Exames(exames) {
    const selectElement = document.getElementById('exames-select2');
    if (!selectElement || typeof $ === 'undefined') {
        console.warn('Select2 ou jQuery não disponível para exames');
        return;
    }

    // Limpar opções existentes
    $(selectElement).empty();
    
    // Adicionar exames como opções
    exames.forEach(exame => {
        const option = new Option(exame.nome, exame.id, false, false);
        selectElement.appendChild(option);
    });

    $(selectElement).select2({
        placeholder: 'Digite para buscar e selecionar exames...',
        allowClear: true,
        multiple: true,
        closeOnSelect: false
    }).on('select2:select select2:unselect', function () {
        atualizarExamesSelecionados();
    });
}

/**
 * Carregar exames para agendamento
 */
function carregarExamesAgendamento(agendaId, agendaInfo) {
    if (agendaInfo.tipo && agendaInfo.tipo.toLowerCase() === 'procedimento') {
        const secaoExames = document.getElementById('secao-exames-agendamento');
        if (secaoExames) {
            secaoExames.classList.remove('hidden');
            
            // Buscar exames do procedimento
            fetch('buscar_exames_agenda.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `agenda_id=${agendaId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'sucesso' && data.exames.length > 0) {
                    configurarSelect2Exames(data.exames);
                }
            })
            .catch(error => {
                console.error('Erro ao carregar exames:', error);
            });
        }
    }
}

/**
 * Preencher dados do paciente selecionado
 */
function preencherDadosPaciente(paciente) {
    document.getElementById('nome-paciente').value = paciente.nome || '';
    document.getElementById('cpf-paciente').value = paciente.cpf || '';
    document.getElementById('telefone-paciente').value = paciente.telefone || '';
    document.getElementById('email-paciente').value = paciente.email || '';
    document.getElementById('data-nascimento').value = paciente.data_nascimento || '';
    document.getElementById('paciente-existente-id').value = paciente.id;
    
    // Desabilitar checkbox de cadastro (paciente já existe)
    const checkboxCadastro = document.getElementById('cadastrar-paciente');
    if (checkboxCadastro) {
        checkboxCadastro.checked = false;
        checkboxCadastro.disabled = true;
        checkboxCadastro.parentElement.style.opacity = '0.5';
    }
}

/**
 * Limpar dados do paciente
 */
function limparDadosPaciente() {
    document.getElementById('nome-paciente').value = '';
    document.getElementById('cpf-paciente').value = '';
    document.getElementById('telefone-paciente').value = '';
    document.getElementById('email-paciente').value = '';
    document.getElementById('data-nascimento').value = '';
    document.getElementById('paciente-existente-id').value = '';
    
    // Reabilitar checkbox de cadastro
    const checkboxCadastro = document.getElementById('cadastrar-paciente');
    if (checkboxCadastro) {
        checkboxCadastro.disabled = false;
        checkboxCadastro.parentElement.style.opacity = '1';
    }
}

/**
 * Atualizar lista de exames selecionados
 */
function atualizarExamesSelecionados() {
    const selectElement = document.getElementById('exames-select2');
    const containerSelecionados = document.getElementById('exames_selecionados_agendamento');
    const hiddenInput = document.getElementById('exames_ids_selected_agendamento');
    const btnLimpar = document.getElementById('btn_limpar_exames_agendamento');
    
    if (!selectElement || !containerSelecionados) return;
    
    const selectedValues = $(selectElement).val() || [];
    const selectedTexts = [];
    
    selectedValues.forEach(value => {
        const option = selectElement.querySelector(`option[value="${value}"]`);
        if (option) {
            selectedTexts.push(option.text);
        }
    });
    
    if (selectedValues.length > 0) {
        containerSelecionados.innerHTML = selectedTexts.map(text => 
            `<span class="inline-block bg-teal-100 text-teal-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                <i class="bi bi-check-circle mr-1"></i>${text}
            </span>`
        ).join('');
        
        btnLimpar.classList.remove('hidden');
        hiddenInput.value = selectedValues.join(',');
    } else {
        containerSelecionados.innerHTML = '<div class="text-sm text-gray-500">Nenhum exame selecionado</div>';
        btnLimpar.classList.add('hidden');
        hiddenInput.value = '';
    }
}

/**
 * Limpar todos os exames selecionados
 */
function limparTodosExamesAgendamento() {
    const selectElement = document.getElementById('exames-select2');
    if (selectElement) {
        $(selectElement).val(null).trigger('change');
    }
}

/**
 * Inicializar todos os Select2 do modal
 */
function inicializarSelect2Modal() {
    // Aguardar um pouco para garantir que o DOM foi completamente carregado
    setTimeout(() => {
        configurarSelect2Pacientes();
        configurarSelect2Convenios();
    }, 100);
}