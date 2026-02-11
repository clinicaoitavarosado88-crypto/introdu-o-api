/**
 * ============================================================================
 * INTEGRAÇÃO: Sistema de Ressonância com Contraste e Sedação
 * ============================================================================
 *
 * Este arquivo contém o código para integrar o sistema de agendamento
 * de ressonância com validações de CONTRASTE e SEDAÇÃO.
 *
 * Funcionalidades:
 * - Detecta automaticamente agendas de ressonância (IDs 30 e 76)
 * - Adiciona checkbox "Precisa de sedação"
 * - Filtra horários baseado em:
 *   - Exame precisa de contraste? (médico disponível a partir de 07:00)
 *   - Paciente precisa de sedação? (quinta-feira, limite 2/dia)
 * - Mostra mensagens de erro amigáveis
 *
 * Data: 2026-01-19
 * ============================================================================
 */

// IDs das agendas de ressonância
const AGENDAS_RESSONANCIA = [30, 76];

/**
 * Verifica se a agenda atual é de ressonância
 */
function isAgendaRessonancia(agendaId) {
    return AGENDAS_RESSONANCIA.includes(parseInt(agendaId));
}

/**
 * Adiciona checkbox de sedação na tela de agendamento
 *
 * ONDE ADICIONAR: Inserir após o campo de seleção de exame
 */
function adicionarCheckboxSedacao() {
    // Verificar se já existe
    if (document.getElementById('checkbox-sedacao-container')) {
        return;
    }

    // Criar HTML do checkbox
    const html = `
        <div id="checkbox-sedacao-container" class="form-group mb-4 p-3 border border-warning bg-warning bg-opacity-10 rounded">
            <div class="form-check">
                <input class="form-check-input"
                       type="checkbox"
                       id="precisa_sedacao"
                       onchange="onSedacaoChange()">
                <label class="form-check-label" for="precisa_sedacao">
                    <strong>💉 Este paciente precisa de sedação/anestesia</strong>
                </label>
            </div>
            <small class="text-muted d-block mt-2">
                <i class="bi bi-info-circle"></i>
                Marque esta opção para pacientes que necessitam de sedação (crianças, claustrofóbicos, etc).
                <br>
                <strong>Importante:</strong> Agendamentos com sedação só são disponíveis às <strong>Quintas-feiras</strong> (limite: 2 por dia).
            </small>
        </div>
    `;

    // Encontrar onde inserir (após seleção de exame)
    // Tentar múltiplos seletores para encontrar o campo de exames
    const exameContainer = document.querySelector('#exames_search_agendamento')?.parentElement ||
                          document.querySelector('#campo-exame') ||
                          document.querySelector('.select-exame') ||
                          document.querySelector('[data-campo="exame"]') ||
                          document.querySelector('input[placeholder*="exame"]')?.parentElement ||
                          document.querySelector('label:has(+ input#exames_search_agendamento)');

    if (exameContainer) {
        console.log('✅ Container de exames encontrado:', exameContainer);
        exameContainer.insertAdjacentHTML('afterend', html);
    } else {
        console.warn('⚠️ Não foi possível adicionar checkbox de sedação. Tentando inserir no topo do formulário...');

        // Fallback: inserir no início do formulário
        const form = document.querySelector('#form-agendamento-modal');
        if (form) {
            const primeiroFieldset = form.querySelector('.bg-gray-50');
            if (primeiroFieldset) {
                primeiroFieldset.insertAdjacentHTML('beforeend', html);
                console.log('✅ Checkbox inserido no formulário como fallback');
            }
        } else {
            console.error('❌ Não foi possível adicionar checkbox de sedação em lugar algum');
        }
    }
}

/**
 * Evento quando checkbox de sedação é alterado
 */
function onSedacaoChange() {
    const checkbox = document.getElementById('precisa_sedacao');
    const precisaSedacao = checkbox ? checkbox.checked : false;

    if (precisaSedacao) {
        // Informar ao usuário
        mostrarInfoSedacao();
        console.log('✅ Sedação marcada - agendamento será criado com flag de sedação');
    } else {
        ocultarInfoSedacao();
        console.log('ℹ️ Sedação desmarcada');
    }

    // ✅ Não precisa recarregar horários - checkbox só aparece em quintas-feiras
    // A sedação é apenas uma flag adicional no agendamento
}

/**
 * Mostra informação sobre sedação
 */
function mostrarInfoSedacao() {
    const info = document.getElementById('info-sedacao');
    if (info) {
        info.classList.remove('hidden', 'd-none');
        return;
    }

    // Criar info se não existir
    const html = `
        <div id="info-sedacao" class="alert alert-warning mt-2">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Atenção:</strong> Agendamentos com sedação só estão disponíveis às <strong>Quintas-feiras</strong>.
            O calendário será filtrado automaticamente.
        </div>
    `;

    const checkbox = document.getElementById('checkbox-sedacao-container');
    if (checkbox) {
        checkbox.insertAdjacentHTML('afterend', html);
    }
}

/**
 * Oculta informação sobre sedação
 */
function ocultarInfoSedacao() {
    const info = document.getElementById('info-sedacao');
    if (info) {
        info.classList.add('hidden', 'd-none');
    }
}

/**
 * Buscar horários para ressonância com validações
 *
 * @param {number} agendaId - ID da agenda
 * @param {string} data - Data no formato YYYY-MM-DD
 * @param {number|string|Array|null} examesIds - ID(s) do(s) exame(s) - pode ser número único, string "544,545" ou array [544,545]
 * @param {boolean} precisaSedacao - Se o paciente precisa de sedação
 */
async function buscarHorariosRessonancia(agendaId, data, examesIds = null, precisaSedacao = false) {
    try {
        // Montar URL (caminho relativo)
        let url = `buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}`;

        // ✅ Aceitar múltiplos formatos de examesIds
        if (examesIds) {
            let examesIdsStr = '';

            if (Array.isArray(examesIds)) {
                // Se for array, juntar com vírgula
                examesIdsStr = examesIds.join(',');
            } else {
                // Se for número ou string, usar direto
                examesIdsStr = String(examesIds);
            }

            if (examesIdsStr) {
                url += `&exame_id=${encodeURIComponent(examesIdsStr)}`;
                console.log(`🔍 Buscando horários com ${examesIdsStr.split(',').length} exame(s): ${examesIdsStr}`);
            }
        }

        // Se precisa sedação mas não é quinta-feira, avisar antes
        if (precisaSedacao) {
            const dataObj = new Date(data + 'T00:00:00');
            const diaSemana = dataObj.getDay(); // 0=Domingo, 4=Quinta

            if (diaSemana !== 4) {
                // Não é quinta-feira
                mostrarErroSedacao('dia_errado', data);
                return { erro: true, horarios: [] };
            }
        }

        // Fazer requisição (usar fetchWithAuth se disponível, senão fetch normal)
        const fetchFunction = typeof fetchWithAuth !== 'undefined' ? fetchWithAuth : fetch;
        const response = await fetchFunction(url);
        const data_response = await response.json();

        // Verificar se há erro
        if (data_response.erro) {
            tratarErroRessonancia(data_response);
            return data_response;
        }

        // Se precisa sedação, verificar limite
        if (precisaSedacao && data_response.info_horario) {
            const anestesiasDisponiveis = data_response.info_horario.anestesias_disponiveis || 0;

            if (anestesiasDisponiveis <= 0) {
                mostrarErroSedacao('limite_atingido', data);
                return { erro: true, horarios: [] };
            }

            // Mostrar info de vagas de sedação disponíveis
            mostrarInfoVagasSedacao(anestesiasDisponiveis);
        }

        return data_response;

    } catch (error) {
        console.error('Erro ao buscar horários de ressonância:', error);
        mostrarErroGenerico();
        return { erro: true, horarios: [] };
    }
}

/**
 * Trata erros específicos da API de ressonância
 */
function tratarErroRessonancia(data) {
    const tipo = data.tipo;
    const mensagem = data.mensagem;
    const sugestao = data.sugestao;

    switch (tipo) {
        case 'contraste_indisponivel':
            mostrarAlerta('warning', '🩺 Médico Indisponível', mensagem, sugestao);
            break;

        case 'anestesia_indisponivel':
            mostrarAlerta('warning', '💉 Sedação Indisponível', mensagem, sugestao);
            break;

        case 'limite_anestesia_atingido':
            mostrarAlerta('danger', '⚠️ Limite Atingido', mensagem, sugestao);
            break;

        case 'horario_nao_configurado':
            mostrarAlerta('info', '📅 Dia sem Atendimento', mensagem, sugestao);
            break;

        default:
            mostrarAlerta('danger', '❌ Erro', mensagem, sugestao);
    }
}

/**
 * Mostra alerta formatado
 */
function mostrarAlerta(tipo, titulo, mensagem, sugestao = null) {
    const cores = {
        'warning': 'alert-warning',
        'danger': 'alert-danger',
        'info': 'alert-info',
        'success': 'alert-success'
    };

    const classe = cores[tipo] || 'alert-info';

    const html = `
        <div class="alert ${classe} alert-dismissible fade show" role="alert">
            <h5 class="alert-heading">${titulo}</h5>
            <p class="mb-1">${mensagem}</p>
            ${sugestao ? `<hr><small class="mb-0"><i class="bi bi-lightbulb"></i> ${sugestao}</small>` : ''}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    // Inserir no container de mensagens
    const container = document.getElementById('container-mensagens') ||
                     document.querySelector('.mensagens-agendamento') ||
                     document.querySelector('.alert-container');

    if (container) {
        container.innerHTML = html;
    } else {
        // Fallback: alert browser
        alert(`${titulo}\n\n${mensagem}\n\n${sugestao || ''}`);
    }
}

/**
 * Mostra erro específico de sedação
 */
function mostrarErroSedacao(tipo, data) {
    const dataFormatada = formatarDataBR(data);

    if (tipo === 'dia_errado') {
        mostrarAlerta('warning',
            '💉 Sedação Indisponível',
            `A data selecionada (${dataFormatada}) não está configurada para receber agendamentos com sedação.`,
            'Agendamentos com sedação só estão disponíveis às <strong>Quintas-feiras</strong>.'
        );
    } else if (tipo === 'limite_atingido') {
        mostrarAlerta('danger',
            '⚠️ Limite de Sedações Atingido',
            `O limite de agendamentos com sedação para ${dataFormatada} foi atingido (2/2).`,
            'Selecione outra quinta-feira disponível.'
        );
    }
}

/**
 * Mostra informação de vagas de sedação disponíveis
 */
function mostrarInfoVagasSedacao(vagasDisponiveis) {
    const container = document.getElementById('info-vagas-sedacao');

    const html = `
        <div class="alert alert-success mt-2">
            <i class="bi bi-check-circle"></i>
            <strong>Vagas de sedação disponíveis:</strong> ${vagasDisponiveis}
        </div>
    `;

    if (container) {
        container.innerHTML = html;
    }
}

/**
 * Mostra erro genérico
 */
function mostrarErroGenerico() {
    mostrarAlerta('danger',
        '❌ Erro ao Carregar',
        'Ocorreu um erro ao buscar os horários disponíveis.',
        'Tente novamente ou entre em contato com o suporte.'
    );
}

/**
 * Formata data para padrão brasileiro
 */
function formatarDataBR(data) {
    const partes = data.split('-');
    return `${partes[2]}/${partes[1]}/${partes[0]}`;
}

// ============================================================================
// FUNÇÕES DE INTEGRAÇÃO COM O SISTEMA EXISTENTE
// ============================================================================

/**
 * ESTA FUNÇÃO DEVE SER CHAMADA quando a tela de agendamento for carregada
 *
 * Exemplo de integração:
 *
 * // No seu código de carregamento da agenda:
 * function carregarAgenda(agendaId) {
 *     if (isAgendaRessonancia(agendaId)) {
 *         adicionarCheckboxSedacao();
 *     }
 *     // ... resto do código
 * }
 */

/**
 * ESTA FUNÇÃO DEVE SUBSTITUIR a busca normal de horários para ressonância
 *
 * Exemplo de integração:
 *
 * async function buscarHorarios(agendaId, data) {
 *     if (isAgendaRessonancia(agendaId)) {
 *         const exameId = obterExameSelecionado(); // Implementar conforme seu sistema
 *         const precisaSedacao = document.getElementById('precisa_sedacao')?.checked || false;
 *
 *         return await buscarHorariosRessonancia(agendaId, data, exameId, precisaSedacao);
 *     }
 *
 *     // Busca normal para outras agendas
 *     return await buscarHorariosNormal(agendaId, data);
 * }
 */

// ============================================================================
// EXPORTS (se estiver usando módulos ES6)
// ============================================================================

// export {
//     isAgendaRessonancia,
//     adicionarCheckboxSedacao,
//     buscarHorariosRessonancia,
//     AGENDAS_RESSONANCIA
// };

// ============================================================================
// EXPOR FUNÇÕES GLOBALMENTE (para uso em outros scripts)
// ============================================================================

window.isAgendaRessonancia = isAgendaRessonancia;
window.adicionarCheckboxSedacao = adicionarCheckboxSedacao;
window.buscarHorariosRessonancia = buscarHorariosRessonancia;
window.AGENDAS_RESSONANCIA = AGENDAS_RESSONANCIA;

console.log('✅ Módulo de integração de ressonância carregado');
console.log('✅ Funções expostas globalmente: isAgendaRessonancia, adicionarCheckboxSedacao, buscarHorariosRessonancia');
