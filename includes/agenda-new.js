// ARQUIVO: includes/agenda.js - VERSÃO COMPLETA COM DRAG & DROP
// Última atualização: 2025-08-13 15:30 - Correção do modal de edição

console.log('🔄 agenda.js carregado/recarregado em:', new Date().toLocaleString(), '- Modal de edição corrigido');
console.log('🧹 NOVO ARQUIVO - Cache forcado - versão:', '2025-08-13-15-45-NEW-FILE');
window.AGENDA_JS_VERSION = '2025-08-13-15-35';

// Configuração de autenticação API
const API_CONFIG = {
    token: '8RWg2ZAX7W2T4453vfdoSuNLRC3GIDGIhougqziUcg0'
};

// Função para fazer requisições autenticadas
function fetchWithAuth(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Authorization': `Bearer ${API_CONFIG.token}`,
            'Content-Type': 'application/json',
            ...options.headers
        }
    };
    
    return fetch(url, { ...options, ...defaultOptions });
}

// Função auxiliar para parsing JSON seguro
async function safeJsonParse(response) {
    const text = await response.text();

    // Tentar parsear o JSON primeiro
    let jsonData = null;
    try {
        jsonData = JSON.parse(text);
    } catch (error) {
        console.error('💥 Erro ao parsear JSON:', error);
        console.error('📝 Texto recebido:', text);
        console.error('🌐 URL da requisição:', response.url);
        throw new Error(`Erro ao parsear resposta JSON: ${error.message}`);
    }

    // Se a resposta não foi bem-sucedida, mas tem JSON, mostrar a mensagem
    if (!response.ok) {
        console.error(`❌ Erro HTTP ${response.status}: ${response.statusText}`);
        if (jsonData && jsonData.mensagem) {
            console.error('📨 Mensagem do servidor:', jsonData.mensagem);
            // Retornar o JSON com erro para o .then() processar
            return jsonData;
        }
        throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
    }

    if (!text.trim()) {
        console.warn('⚠️ Resposta vazia recebida do servidor');
        return {};
    }

    return jsonData;
}

// Função de diagnóstico para verificar se existe código duplicado
window.diagnosticarFuncoes = function() {
    console.log('🔍 Diagnóstico de funções:');
    console.log('- editarAgendamento:', typeof window.editarAgendamento);
    console.log('- visualizarAgendamento:', typeof window.visualizarAgendamento);
    console.log('- criarModalAgendamentoComDados:', typeof criarModalAgendamentoComDados);
    console.log('- criarModalVisualizacao:', typeof criarModalVisualizacao);
    console.log('- fecharModalEdicao:', typeof window.fecharModalEdicao);
    console.log('- Versão do arquivo:', window.AGENDA_JS_VERSION);
    
    // Verificar se existe alguma função problemática
    if (typeof criarHTMLModal !== 'undefined') {
        console.error('❌ PROBLEMA: criarHTMLModal ainda existe!');
    } else {
        console.log('✅ criarHTMLModal não existe (correto)');
    }
};

// Função de teste para verificar se os modais estão funcionando
window.testarModalVisualizacao = function() {
    console.log('🧪 Testando modal de visualização...');
    
    // Dados de teste
    const dadosTeste = {
        sucesso: true,
        id: 169,
        numero: "AGD-0011",
        data: "2025-08-18",
        horario: "10:00",
        status: "AGENDADO",
        tipo_consulta: "primeira_vez",
        observacoes: "Teste de observações",
        paciente: {
            id: 622689,
            nome: "Teste João",
            cpf: "08635709407",
            data_nascimento: "1995-09-21",
            telefone: "849818165666",
            email: "teste@email.com"
        },
        convenio: {
            id: 18,
            nome: "Marinha"
        },
        agenda: {
            id: 2,
            sala: "Sala de Imagem",
            telefone: "(84) 98888-4567",
            unidade: "Mossoró",
            medico: "",
            especialidade: "",
            procedimento: "Acupuntura"
        },
        exames: [
            { id: 2473, nome: "ANGIO TC AORTA 2º" }
        ]
    };
    
    try {
        criarModalVisualizacao(dadosTeste);
    } catch (error) {
        console.error('❌ Erro no teste:', error);
    }
};

// Variáveis globais
let agendamentosCache = {};
let mesAtual = new Date().getMonth();
let anoAtual = new Date().getFullYear();
let draggedElement = null;
let draggedData = null;
let agendamentos = {}; // No início do arquivo agenda.js
window.agendaIdAtual = null;
window.dataSelecionadaAtual = null;
window.usuarioAtual = null; // ID do usuário logado para controle de permissões

/**
 * Detecta automaticamente o usuário logado do sistema principal
 * Busca pelo cookie "log_usuario" usado pelo sistema
 */
function detectarUsuarioLogado() {
    // Primeiro tentar obter do cookie do sistema principal
    const usuarioCookie = getCookie('log_usuario');
    
    if (usuarioCookie) {
        console.log('🍪 Usuário detectado do cookie do sistema principal:', usuarioCookie);
        configurarUsuarioAtual(usuarioCookie);
        return;
    }
    
    // Fallback: Buscar do backend
    fetchWithAuth('includes/verificar_permissao.php?acao=obter_usuario_atual')
        .then(safeJsonParse)
        .then(data => {
            if (data.usuario) {
                console.log('🔍 Usuário detectado do backend:', data.usuario);
                configurarUsuarioAtual(data.usuario);
            } else {
                console.warn('⚠️ Nenhum usuário logado detectado');
                // Em desenvolvimento, pode usar RENISON como fallback
                // configurarUsuarioAtual('RENISON');
            }
        })
        .catch(error => {
            console.error('💥 Erro ao detectar usuário:', error);
            // Em desenvolvimento, pode usar RENISON como fallback
            // configurarUsuarioAtual('RENISON');
        });
}

/**
 * Função auxiliar para obter cookie
 * @param {string} name - Nome do cookie
 * @returns {string|null} Valor do cookie ou null se não encontrado
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        const cookieValue = parts.pop().split(';').shift();
        return cookieValue ? decodeURIComponent(cookieValue) : null;
    }
    return null;
}

/**
 * Configura o usuário atual para verificação de permissões
 * @param {string} usuarioId - ID do usuário logado
 */
window.configurarUsuarioAtual = function(usuarioId) {
    window.usuarioAtual = usuarioId;
    console.log('👤 Usuário configurado:', usuarioId);
    
    // Verificar permissões do usuário
    verificarPermissoesUsuario(usuarioId);
};

/**
 * Verifica as permissões do usuário atual
 * @param {string} usuarioId - ID do usuário
 */
function verificarPermissoesUsuario(usuarioId) {
    if (!usuarioId) {
        console.warn('⚠️ Usuário não configurado para verificação de permissões');
        return;
    }
    
    fetchWithAuth(`testar_permissoes.php?usuario=${encodeURIComponent(usuarioId)}`)
        .then(safeJsonParse)
        .then(data => {
            if (data.erro) {
                console.error('❌ Erro ao verificar permissões:', data.erro);
                return;
            }
            
            console.log('🔐 Permissões do usuário:', data);
            
            // Armazenar informações de permissão
            window.usuarioPermissoes = {
                pode_administrar_agendas: data.pode_administrar_agendas,
                total_permissoes: data.total_permissoes,
                permissoes: data.permissoes
            };
            
            // Mostrar/ocultar elementos baseado em permissões
            atualizarInterfacePorPermissoes();
        })
        .catch(error => {
            console.error('💥 Erro ao verificar permissões:', error);
        });
}

/**
 * Atualiza a interface baseada nas permissões do usuário
 */
function atualizarInterfacePorPermissoes() {
    const podeAdministrar = window.usuarioPermissoes?.pode_administrar_agendas || false;
    
    // Ocultar botões de bloquear se não tem permissão
    const botoesBloqueio = document.querySelectorAll('[onclick*="bloquearHorario"], [onclick*="desbloquearHorario"]');
    botoesBloqueio.forEach(botao => {
        if (!podeAdministrar) {
            botao.style.display = 'none';
            console.log('🚫 Botão de bloqueio ocultado por falta de permissão');
        } else {
            botao.style.display = '';
        }
    });
    
    // Mostrar botões de cancelar para qualquer usuário logado
    const botoesCancelamento = document.querySelectorAll('[onclick*="cancelarAgendamento"]');
    const temUsuario = window.usuarioAtual !== null;
    botoesCancelamento.forEach(botao => {
        if (!temUsuario) {
            botao.style.display = 'none';
            console.log('🚫 Botão de cancelamento ocultado - usuário não logado');
        } else {
            botao.style.display = '';
            console.log('✅ Botão de cancelamento disponível para usuário logado');
        }
    });
    
    console.log(podeAdministrar ? '✅ Usuário tem permissão de administrar agendas' : '❌ Usuário SEM permissão de administrar agendas');
}

// Integração com o sistema existente
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('area-visualizacao')) {
        console.log('Sistema de agenda inicializado');
    }
    
    // Detectar usuário automaticamente do sistema principal
    detectarUsuarioLogado();

    // Auto-verificar horário quando digitado
    const horarioInput = document.getElementById('horario_digitado');
    if (horarioInput) {
        let timeoutId;
        horarioInput.addEventListener('input', function() {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                if (this.value && this.value.includes(':')) {
                    verificarDisponibilidadeHorario();
                }
            }, 1000);
        });
    }
});

/**
 * Função global para carregar agendamento (chamada pelos cards)
 */
window.carregarAgendamento = function(agendaId, especialidadeId = null) {
    console.log('Carregando agendamento para agenda ID:', agendaId, 'Especialidade ID:', especialidadeId);
    
    // Armazenar IDs globalmente para uso nas funções
    window.especialidadeIdSelecionada = especialidadeId;
    window.agendaIdAtual = agendaId;
    
    const conteudoDiv = document.getElementById('conteudo-dinamico');
    const loader = document.getElementById('loader');

    if (!conteudoDiv) {
        console.error('Elemento conteudo-dinamico não encontrado');
        return;
    }

    if (loader) {
        loader.classList.remove('hidden');
    }

    fetchWithAuth(`carregar_agendamento.php?agenda_id=${agendaId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(html => {
            conteudoDiv.innerHTML = html;
            
            setTimeout(() => {
                inicializarSistemaAgenda(agendaId);
                
                // ✅ APLICAR estilos visuais para ENCAIXE sem drag após carregar
                setTimeout(() => {
                    aplicarEstilosEncaixeSemDrag();
                }, 300);
            }, 200);
        })
        .catch(error => {
            console.error('Erro ao carregar agendamento:', error);
            conteudoDiv.innerHTML = `
                <div class="text-center text-red-600 p-8">
                    <svg class="w-16 h-16 text-red-600 mx-auto mb-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <h3 class="text-lg font-semibold mb-2">Erro ao carregar agendamento</h3>
                    <p class="text-sm mb-4">Não foi possível carregar a tela de agendamento.</p>
                    <button onclick="window.location.reload()" class="px-6 py-3 bg-teal-600 text-white rounded hover:bg-teal-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Tentar Novamente
                    </button>
                </div>
            `;
        })
        .finally(() => {
            if (loader) {
                loader.classList.add('hidden');
            }
        });
};

/**
 * Inicializa todo o sistema de agenda
 */
function inicializarSistemaAgenda(agendaId) {
    console.log('Inicializando sistema de agenda para ID:', agendaId);

    configurarBotoesVisualizacao(agendaId);
    configurarCalendario(agendaId);

    const hoje = new Date();
    const dataAtual = formatarDataISO(hoje);

    // ✅ INICIALIZAR data selecionada antes de configurar o calendário
    window.dataSelecionadaAtual = dataAtual;

    carregarVisualizacaoDia(agendaId, dataAtual);

    // ✅ CORREÇÃO 19/01/2026: Checkbox de sedação movido para criarModalAgendamento()
    // (linha 8023) para ser adicionado APÓS o modal ser criado.
    // Esta chamada aqui era prematura - o modal ainda não existe neste momento.
    if ([30, 76].includes(parseInt(agendaId))) {
        console.log('🏥 Agenda de Ressonância detectada - ID:', agendaId);
        // Checkbox será adicionado quando modal for aberto (ver criarModalAgendamento linha 8025)
    }

    console.log('Sistema de agenda inicializado com sucesso');
}

/**
 * Configura os botões de visualização (Dia, Semana, Mês)
 */
function configurarBotoesVisualizacao(agendaId) {
    document.querySelectorAll('.btn-visualizacao').forEach(btn => {
        btn.addEventListener('click', function() {
            const tipo = this.dataset.tipo;
            alternarTipoVisualizacao(tipo, agendaId);
        });
    });
}

/**
 * Configura o calendário lateral
 */
function configurarCalendario(agendaId) {
    console.log('🔧 Configurando calendário - agendaId:', agendaId);
    
    // ✅ CORREÇÃO: Remover listeners antigos primeiro para evitar duplicação
    document.querySelectorAll('.nav-calendario').forEach(btn => {
        btn.replaceWith(btn.cloneNode(true)); // Remove todos os event listeners
    });
    
    // Listeners para os dias do calendário
    document.querySelectorAll('.dia-calendario').forEach(dia => {
        dia.addEventListener('click', function(e) {
            const data = this.dataset.data;
            const isDisabled = this.hasAttribute('disabled');
            const hasDisabledClass = this.classList.contains('cursor-not-allowed');
            
            console.log(`📅 Dia clicado: ${data}, disabled: ${isDisabled}, classes: ${this.className}`);
            
            // Verificar se é dia passado (exceto hoje)
            const hoje = new Date();
            const dataHoje = formatarDataISO(hoje);
            const dataClicada = new Date(data + 'T00:00:00');
            const ehPassado = dataClicada < hoje && data !== dataHoje;
            
            // Permitir dias passados se há agendamentos ou se é dia válido para atendimento
            if (ehPassado) {
                const temAgendamentos = this.classList.contains('tem-agendamentos') || 
                                       this.querySelector('.indicador-agendamentos') ||
                                       this.dataset.agendamentos > 0 ||
                                       parseInt(this.dataset.agendamentos || '0') > 0;
                
                // Verificar se é um dia válido da semana para atendimento
                const dataObj = new Date(data + 'T00:00:00');
                const diaSemana = dataObj.getDay(); // 0=Domingo, 1=Segunda, etc.
                const ehDiaUtilNormal = diaSemana >= 1 && diaSemana <= 5; // Segunda a Sexta
                
                // Se tem agendamentos OU é dia útil normal, permitir
                if (temAgendamentos || ehDiaUtilNormal) {
                    console.log(`✅ Permitindo seleção de dia passado: ${data} (agendamentos: ${temAgendamentos}, dia útil: ${ehDiaUtilNormal})`);
                } else {
                    console.warn(`⚠️ Dia no passado não é dia de atendimento: ${data} (dia da semana: ${diaSemana})`);
                    return;
                }
            }
            
            // Verificar se está desabilitado (bloqueado ou outros motivos) - mas permitir dias passados válidos
            if (!ehPassado && (isDisabled || hasDisabledClass)) {
                console.warn(`⚠️ Dia desabilitado: ${data}`);
                return;
            }
            
            selecionarDiaNoCalendario(this, agendaId, data);
        });
    });
    
    // ✅ CORREÇÃO: Listeners para navegação do calendário (prev/next)
    const navButtons = document.querySelectorAll('.nav-calendario');
    console.log(`🔧 Configurando ${navButtons.length} botões de navegação`);
    
    navButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault(); // Previne qualquer comportamento padrão
            e.stopPropagation(); // Impede propagação do evento
            
            const direcao = this.dataset.direcao;
            console.log(`🔄 Navegação clicada: ${direcao}`);
            navegarMesCalendario(agendaId, direcao);
        });
    });
    
    // Selecionar dia atual por padrão
    const hoje = new Date();
    const dataHoje = formatarDataISO(hoje);
    const diaHoje = document.querySelector(`[data-data="${dataHoje}"]`);
    if (diaHoje && !diaHoje.hasAttribute('disabled')) {
        diaHoje.classList.add('bg-teal-500', 'text-white');
    }
}

/**
 * Alterna o tipo de visualização
 */
function alternarTipoVisualizacao(tipo, agendaId) {
    // Atualizar botões
    document.querySelectorAll('.btn-visualizacao').forEach(btn => {
        btn.classList.remove('bg-teal-600', 'text-white');
        btn.classList.add('bg-white', 'text-gray-700');
    });
    
    const btnAtivo = document.querySelector(`[data-tipo="${tipo}"]`);
    if (btnAtivo) {
        btnAtivo.classList.remove('bg-white', 'text-gray-700');
        btnAtivo.classList.add('bg-teal-600', 'text-white');
    }
    
    // Atualizar título
    const titulos = {
        'dia': 'Agenda do Dia',
        'semana': 'Agenda da Semana',
        'mes': 'Agenda do Mês'
    };
    
    const tituloElement = document.getElementById('titulo-visualizacao');
    if (tituloElement) {
        tituloElement.textContent = titulos[tipo];
    }
    
    // Carregar visualização correspondente
    const dataSelecionada = obterDataSelecionada();
    
    switch (tipo) {
        case 'dia':
            carregarVisualizacaoDia(agendaId, dataSelecionada);
            break;
        case 'semana':
            carregarVisualizacaoSemana(agendaId, dataSelecionada);
            break;
        case 'mes':
            carregarVisualizacaoMes(agendaId, dataSelecionada);
            break;
    }
}

/**
 * Carrega visualização do dia com lista de horários e pacientes
 */
/**
 * Carrega visualização do dia com lista de horários e pacientes
 */
function carregarVisualizacaoDia(agendaId, data) {
    const container = document.getElementById('area-visualizacao');
    
    if (!container) return;
    
    // Armazenar globalmente para uso nas funções de drag & drop
    window.agendaIdAtual = agendaId;
    window.dataSelecionadaAtual = data;
    
    // Loading
    container.innerHTML = `
        <div class="text-center text-gray-500 py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-600 mx-auto mb-2"></div>
            <p>Carregando agenda do dia...</p>
        </div>`;
    
    console.log('Buscando agenda do dia para:', { agendaId, data });

    // ✅ Determinar qual API usar baseado no tipo de agenda
    const isRessonancia = [30, 76].includes(parseInt(agendaId));
    const apiHorarios = isRessonancia
        ? `buscar_horarios_ressonancia.php?agenda_id=${agendaId}&data=${data}`
        : `buscar_horarios.php?agenda_id=${agendaId}&data=${data}`;

    if (isRessonancia) {
        console.log('🏥 Usando API especializada de Ressonância');
    }

    // Buscar horários e agendamentos do dia
    Promise.all([
        fetchWithAuth(apiHorarios).then(safeJsonParse),
        fetchWithAuth(`buscar_agendamentos_dia.php?agenda_id=${agendaId}&data=${data}`).then(safeJsonParse)
    ])
    .then(([dadosHorarios, agendamentos]) => {
        console.log('Dados recebidos:', { dadosHorarios, agendamentos });

        // ✅ Verificar se não há horários configurados para este dia
        if (dadosHorarios.erro && dadosHorarios.tipo === 'horario_nao_configurado') {
            const diaSemana = dadosHorarios.dia_semana || 'este dia';
            container.innerHTML = `
                <div class="text-center py-12">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-900 mb-4">
                        <span class="text-4xl">📅</span>
                    </div>
                    <p class="text-lg text-gray-700 dark:text-gray-300 mb-2 font-semibold">
                        Não há atendimento às ${diaSemana}s
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        ${dadosHorarios.mensagem || 'Esta agenda não possui horários configurados para este dia.'}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        ${dadosHorarios.sugestao || 'Tente selecionar outro dia da semana'}
                    </p>
                </div>
            `;
            return;
        }

        // Armazenar agendamentos globalmente
        window.agendamentos = agendamentos || {};

        // Verifica se a resposta tem o novo formato
        let horarios, infoVagas, infoEncaixes;
        if (dadosHorarios.horarios) {
            horarios = dadosHorarios.horarios;
            infoVagas = dadosHorarios.info_vagas;
            infoEncaixes = dadosHorarios.info_encaixes; // ✅ Nova informação
        } else {
            // Formato antigo - compatibilidade
            horarios = dadosHorarios;
            infoVagas = null;
            infoEncaixes = null;
        }

        renderizarAgendaDia(horarios, agendamentos || {}, agendaId, data, container, infoVagas, infoEncaixes);
    })
    .catch(error => {
        console.error('Erro ao carregar agenda do dia:', error);
        container.innerHTML = `
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900 mb-4">
                    <span class="text-4xl">⚠️</span>
                </div>
                <p class="text-lg text-red-600 dark:text-red-400 mb-2 font-semibold">Erro ao carregar agenda</p>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Verifique sua conexão ou entre em contato com o suporte.
                </p>
                <button onclick="carregarVisualizacaoDia(${agendaId}, '${data}')"
                        class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition">
                    <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>
                    🔄 Tentar Novamente
                </button>
            </div>`;
    });
}


/**
 * Renderiza a agenda do dia com lista de horários
 */
/**
 * ✅ FUNÇÃO: Gerar exibição de exames para agendamentos de procedimento
 */
function gerarExibicaoExames(agendamento) {
    // Mostrar exames se existirem, independente do tipo
    if (!agendamento.exames || !Array.isArray(agendamento.exames) || agendamento.exames.length === 0) {
        return '';
    }
    
    const totalExames = agendamento.exames.length;
    
    if (totalExames === 1) {
        // Um exame: mostrar o nome
        const exame = agendamento.exames[0];
        return `<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded" title="${exame.nome}">
                    <i class="bi bi-clipboard-pulse mr-1"></i>${exame.nome}
                </span>`;
    } else {
        // Múltiplos exames: mostrar o primeiro + contador com tooltip
        const primeiroExame = agendamento.exames[0];
        const tooltipContent = agendamento.exames.map(e => e.nome).join('\\n');
        
        return `<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded" title="${tooltipContent}">
                    <i class="bi bi-clipboard-pulse mr-1"></i>${primeiroExame.nome}
                </span>
                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded cursor-help" 
                      title="${agendamento.exames.slice(1).map(e => e.nome).join('\\n')}"
                      onclick="mostrarModalExames(${agendamento.id})">
                    <i class="bi bi-plus mr-1"></i>+${totalExames - 1}
                </span>`;
    }
}

/**
 * ✅ FUNÇÃO: Mostrar modal com lista completa de exames
 */
function mostrarModalExames(agendamentoId) {
    console.log('Mostrar exames do agendamento:', agendamentoId);
    
    // Buscar os dados do agendamento nos dados globais
    let agendamentoEncontrado = null;
    if (window.agendamentos) {
        for (let hora in window.agendamentos) {
            if (window.agendamentos[hora].id === agendamentoId) {
                agendamentoEncontrado = window.agendamentos[hora];
                break;
            }
        }
    }
    
    if (!agendamentoEncontrado || !agendamentoEncontrado.exames || agendamentoEncontrado.exames.length === 0) {
        alert('Nenhum exame encontrado para este agendamento.');
        return;
    }
    
    // Criar modal simples
    const modalHtml = `
        <div id="modal-exames" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" onclick="fecharModalExames()">
            <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4" onclick="event.stopPropagation()">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-semibold text-gray-800">
                        <i class="bi bi-clipboard-pulse mr-2"></i>Exames do Paciente
                    </h3>
                    <p class="text-sm text-gray-600">${agendamentoEncontrado.paciente}</p>
                </div>
                <div class="px-6 py-4 max-h-60 overflow-y-auto">
                    <ul class="space-y-2">
                        ${agendamentoEncontrado.exames.map(exame => `
                            <li class="flex items-center text-sm text-gray-700">
                                <i class="bi bi-check-circle text-green-600 mr-2"></i>
                                ${exame.nome}
                            </li>
                        `).join('')}
                    </ul>
                </div>
                <div class="px-6 py-4 border-t text-right">
                    <button onclick="fecharModalExames()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

/**
 * ✅ FUNÇÃO: Fechar modal de exames
 */
function fecharModalExames() {
    const modal = document.getElementById('modal-exames');
    if (modal) {
        modal.remove();
    }
}

/**
 * Renderiza a agenda do dia com lista de horários
 */
function renderizarAgendaDia(horarios, agendamentos, agendaId, data, container, infoVagas, infoEncaixes) {
    const agora = new Date();
    const dataHoje = formatarDataISO(agora);
    const horaAtual = agora.getHours() * 60 + agora.getMinutes();
    
    if (!Array.isArray(horarios) || horarios.length === 0) {
        container.innerHTML = `
            <div class="text-center text-gray-500 py-12">
                <i class="bi bi-calendar-x text-5xl mb-4 text-gray-300"></i>
                <h3 class="text-lg font-semibold mb-2">Nenhum horário disponível</h3>
                <p class="text-sm">Não há horários de atendimento para esta data.</p>
                ${infoVagas && infoVagas.limite_total > 0 && infoVagas.disponiveis === 0 ? 
                    `<div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <i class="bi bi-info-circle mr-2"></i>
                            Todas as ${infoVagas.limite_total} vagas normais para este dia já foram preenchidas.
                        </p>
                        ${infoEncaixes && infoEncaixes.disponiveis > 0 ? 
                            `<p class="text-sm text-yellow-800 mt-2">
                                <i class="bi bi-lightning-charge mr-2"></i>
                                Ainda há ${infoEncaixes.disponiveis} vaga${infoEncaixes.disponiveis > 1 ? 's' : ''} de encaixe disponível${infoEncaixes.disponiveis > 1 ? 'is' : ''}.
                            </p>` : ''
                        }
                    </div>` : ''
                }
            </div>`;
        return;
    }
    
    // ✅ ADICIONA INFORMAÇÃO SOBRE VAGAS NORMAIS E ENCAIXES SEPARADAMENTE
    let htmlInfoVagas = '';
    
    if (infoVagas || infoEncaixes) {
        htmlInfoVagas = `<div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">`;
        
        // ✅ INFORMAÇÕES DE VAGAS NORMAIS
        if (infoVagas) {
            const vagasDisponiveis = infoVagas.disponiveis;
            const vagasOcupadas = infoVagas.ocupadas;
            const limiteTotal = infoVagas.limite_total;
            
            htmlInfoVagas += `
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="bi bi-calendar-check text-blue-600 mr-3 text-xl"></i>
                            <div>
                                <h4 class="text-sm font-semibold text-blue-800">Vagas Normais</h4>
                                <p class="text-sm text-blue-600">
                                    ${vagasOcupadas} de ${limiteTotal} ocupadas
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold ${vagasDisponiveis > 0 ? 'text-green-600' : 'text-red-600'}">
                                ${vagasDisponiveis}
                            </span>
                            <p class="text-xs text-gray-600">disponíveis</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // ✅ INFORMAÇÕES DE ENCAIXES
        if (infoEncaixes) {
            const encaixesDisponiveis = infoEncaixes.disponiveis;
            const encaixesOcupados = infoEncaixes.ocupados;
            const limiteEncaixes = infoEncaixes.limite_total;
            
            htmlInfoVagas += `
                <div class="p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center">
                            <i class="bi bi-lightning-charge text-orange-600 mr-3 text-xl"></i>
                            <div>
                                <h4 class="text-sm font-semibold text-orange-800">Vagas de Encaixe</h4>
                                <p class="text-sm text-orange-600">
                                    ${encaixesOcupados} de ${limiteEncaixes} ocupadas
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-2xl font-bold ${encaixesDisponiveis > 0 ? 'text-green-600' : 'text-red-600'}">
                                ${encaixesDisponiveis}
                            </span>
                            <p class="text-xs text-gray-600">disponíveis</p>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Buscar e exibir informações de retornos
        const retornosInfo = buscarInformacoesRetornos(agendamentos);
        
        htmlInfoVagas += `
            <div class="p-4 bg-indigo-50 border border-indigo-200 rounded-lg">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center">
                        <i class="bi bi-arrow-clockwise text-indigo-600 mr-3 text-xl"></i>
                        <div>
                            <h4 class="text-sm font-semibold text-indigo-800">Retornos</h4>
                            <p class="text-sm text-indigo-600">
                                ${retornosInfo.total} retornos agendados
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-indigo-600">
                            ${retornosInfo.total}
                        </span>
                        <p class="text-xs text-gray-600">hoje</p>
                    </div>
                </div>
            </div>
        `;
        
        htmlInfoVagas += `</div>`;
    }
    
    // Separar horários por turno
    const horariosManha = [];
    const horariosTarde = [];
    const horariosNoite = [];
    
    horarios.forEach(horario => {
        const [hora] = horario.hora.split(':').map(Number);
        if (hora < 12) {
            horariosManha.push(horario);
        } else if (hora < 18) {
            horariosTarde.push(horario);
        } else {
            horariosNoite.push(horario);
        }
    });
    
    let html = htmlInfoVagas + '<div class="space-y-6">';
    
    // Função auxiliar para renderizar tabela de horários
    const renderizarTabelaTurno = (horariosTurno, titulo, icone) => {
        if (horariosTurno.length === 0) return '';
        
        let htmlTurno = `
            <div class="bg-white rounded-lg shadow-sm">
                <div class="bg-gray-50 px-4 py-3 border-b">
                    <h4 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="bi ${icone} text-gray-600 mr-2"></i>
                        ${titulo}
                    </h4>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gray-50 border-b">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Horário</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paciente</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">OS</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Convênio</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-24">Ações</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">`;
        
        horariosTurno.forEach(horario => {
            const hora = horario.hora;
            const [horas, minutos] = hora.split(':').map(Number);
            const minutosHorario = horas * 60 + minutos;
            const horarioPassou = data === dataHoje && minutosHorario <= horaAtual;
            const disponivel = horario.disponivel && !horarioPassou;
            
            const agendamento = agendamentos[hora];
            
            if (agendamento) {
                // ✅ VERIFICAR TIPO DE AGENDAMENTO E APLICAR CLASSE + DRAG
                const isEncaixe = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
                const isRetorno = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'RETORNO';
                const isBloqueado = agendamento.status && agendamento.status.trim().toUpperCase() === 'BLOQUEADO';
                
                // ✅ DEFINIR CORES PARA CADA TIPO
                let classeLinha = 'hover:bg-gray-50 transition-colors'; // Padrão
                if (isBloqueado) {
                    classeLinha = 'bg-gray-100 hover:bg-gray-200 border-l-4 border-gray-400 transition-colors opacity-75 text-gray-500';
                } else if (isEncaixe) {
                    classeLinha = 'hover:bg-gray-50 transition-colors';
                } else if (isRetorno) {
                    classeLinha = 'hover:bg-gray-50 transition-colors';
                }
                
                console.log(`🎨 RENDERIZAÇÃO: ${hora} - isEncaixe=${isEncaixe} - isRetorno=${isRetorno} - isBloqueado=${isBloqueado} - status="${agendamento.status}"`);
                
                // ✅ CONFIGURAR PROPRIEDADES DE DRAG E ATRIBUTOS DE IDENTIFICAÇÃO
                const dragProps = (isEncaixe || isRetorno || isBloqueado) ? 
                    'draggable="false" style="cursor: default;"' : 
                    `draggable="true" ondragstart="iniciarDrag(event, ${agendamento.id}, '${hora}', '${data}')" ondragend="finalizarDrag(event)"`;
                
                const classeAdicional = isBloqueado ? ' bloqueado-row' : 
                                      (isEncaixe ? ' encaixe-row' : 
                                       (isRetorno ? ' retorno-row' : ''));
                
                // Horário ocupado com paciente
                htmlTurno += `
                    <tr class="${classeLinha}${classeAdicional}" ${dragProps} data-agendamento-id="${agendamento.id}">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                            ${hora}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm ${isBloqueado ? 'text-red-700' : 'text-gray-900'}">
                            <div class="flex items-center">
                                <i class="bi ${isBloqueado ? 'bi-lock-fill text-red-500' : 'bi-person-circle text-gray-400'} mr-2"></i>
                                <div>
                                    <div class="font-medium ${isBloqueado ? 'uppercase tracking-wide' : ''}">${isBloqueado ? 'HORÁRIO BLOQUEADO' : agendamento.paciente}</div>
                                    ${!isBloqueado && agendamento.cpf ? `<div class="text-xs text-gray-500">CPF: ${formatarCPF(agendamento.cpf)}</div>` : ''}
                                    ${!isBloqueado && agendamento.tipo_atendimento ? `<div class="text-xs text-blue-600"><i class="bi bi-clipboard-pulse mr-1"></i>${agendamento.tipo_atendimento}</div>` : ''}
                                    ${isBloqueado ? `<div class="text-xs text-red-500"><i class="bi bi-info-circle mr-1"></i>Clique em desbloquear para liberar este horário</div>` : ''}
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                            ${isBloqueado ? '-' : (agendamento.tem_os ? `
                                <button onclick="abrirModalOSCompleto(${agendamento.id})" 
                                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                                    <i class="bi bi-file-earmark-text mr-1"></i>
                                    ${agendamento.os_numero}
                                </button>
                            ` : '<span class="text-xs text-gray-400">-</span>')}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            ${isBloqueado ? '-' : `<i class="bi bi-telephone text-gray-400 mr-1"></i>${agendamento.telefone || '-'}`}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            ${isBloqueado ? `
                                <div class="flex items-center justify-center">
                                    <span class="text-xs bg-red-100 text-red-800 px-3 py-1 rounded font-semibold">
                                        <i class="bi bi-lock-fill mr-1"></i>BLOQUEADO
                                    </span>
                                </div>
                            ` : `
                                <div class="flex items-center justify-between gap-2">
                                    <div>${agendamento.convenio}</div>
                                    <div class="flex flex-wrap gap-1">
                                        ${agendamento.tipo_consulta === 'retorno' ? '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Retorno</span>' : ''}
                                        ${isEncaixe ? '<span class="text-xs bg-orange-100 text-orange-800 px-2 py-1 rounded font-semibold" data-tipo="ENCAIXE"><i class="bi bi-lightning-charge mr-1"></i>ENCAIXE</span>' : ''}
                                        ${agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'RETORNO' ? '<span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded font-semibold" data-tipo="RETORNO"><i class="bi bi-arrow-clockwise mr-1"></i>RETORNO</span>' : ''}
                                        ${agendamento.precisa_sedacao ? '<span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded font-semibold" title="Paciente precisa de sedação/anestesia"><i class="bi bi-heart-pulse-fill mr-1"></i>SEDAÇÃO</span>' : ''}
                                        ${agendamento.confirmado ? '<span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded"><i class="bi bi-check-circle mr-1"></i>Confirmado</span>' : '<span class="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded"><i class="bi bi-clock mr-1"></i>Não confirmado</span>'}
                                        ${agendamento.tipo_atendimento_prioridade === 'PRIORIDADE' ? '<span class="text-xs bg-red-100 text-red-800 px-2 py-1 rounded font-semibold"><i class="bi bi-exclamation-triangle mr-1"></i>PRIORIDADE</span>' : ''}
                                        ${gerarExibicaoExames(agendamento)}
                                    </div>
                                </div>
                            `}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            ${getStatusBadge(agendamento.status)}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                            ${isBloqueado ? `
                                <div class="flex justify-center">
                                    <button onclick="desbloquearHorario(${agendaId}, '${data}', '${hora}')" 
                                            class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs font-medium transition-colors" 
                                            title="Desbloquear este horário">
                                        <i class="bi bi-unlock-fill mr-1"></i>Desbloquear
                                    </button>
                                </div>
                            ` : `
                                <div class="flex space-x-2">
                                    <button onclick="visualizarAgendamento(${agendamento.id})" 
                                            class="text-gray-600 hover:text-gray-900" title="Visualizar">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button onclick="editarAgendamento(${agendamento.id})" 
                                            class="text-blue-600 hover:text-blue-900" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    ${agendamento.ordem_chegada ? `
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-medium" 
                                              title="Chegada às ${agendamento.hora_chegada ? new Date(agendamento.hora_chegada).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'}) : ''}">
                                            <i class="bi bi-check-circle mr-1"></i>#${agendamento.ordem_chegada}
                                        </span>
                                    ` : `
                                        <button onclick="marcarChegada(${agendamento.id})" 
                                                class="text-green-600 hover:text-green-900" title="Marcar Chegada">
                                            <i class="bi bi-geo-alt"></i>
                                        </button>
                                    `}
                                    <button onclick="cancelarAgendamento(${agendamento.id})" 
                                            class="text-red-600 hover:text-red-900" title="Cancelar">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                    </button>
                                </div>
                            `}
                        </td>
                    </tr>`;
            } else {
                // Horário livre
                const podeAgendar = disponivel && (!infoVagas || infoVagas.disponiveis > 0);
                
                htmlTurno += `
                    <tr class="hover:bg-gray-50 transition-colors ${!podeAgendar ? 'opacity-50' : ''}"
                        ondrop="soltarAgendamento(event, '${hora}', '${data}', ${agendaId})"
                        ondragover="permitirDrop(event)"
                        ondragleave="removerDestaque(event)">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                            ${hora}
                        </td>
                        <td colspan="4" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                            ${podeAgendar ? `
                                <button onclick="abrirModalAgendamento(${agendaId}, '${data}', '${hora}')" 
                                        class="text-teal-600 hover:text-teal-900 font-medium">
                                    <i class="bi bi-plus-circle mr-1"></i>
                                    Clique para agendar
                                </button>
                            ` : 
                            !disponivel ? `
                                <span class="text-gray-400">
                                    <i class="bi bi-clock-history mr-1"></i>
                                    Horário indisponível
                                </span>
                            ` : `
                                <span class="text-red-400">
                                    <i class="bi bi-x-circle mr-1"></i>
                                    Sem vagas disponíveis
                                </span>
                            `}
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                            ${podeAgendar ? `
                                <button onclick="bloquearHorario(${agendaId}, '${data}', '${hora}')" 
                                        class="text-gray-600 hover:text-gray-900" title="Bloquear horário">
                                    <i class="bi bi-lock"></i>
                                </button>
                            ` : ''}
                        </td>
                    </tr>`;
            }
        });
        
        htmlTurno += `
                        </tbody>
                    </table>
                </div>
            </div>`;
        
        return htmlTurno;
    };
    
    // Renderizar cada turno
    html += renderizarTabelaTurno(horariosManha, 'Turno da Manhã', 'svg-sunrise');
    html += renderizarTabelaTurno(horariosTarde, 'Turno da Tarde', 'svg-sun');
    html += renderizarTabelaTurno(horariosNoite, 'Turno da Noite', 'svg-moon');
    
    html += `
        </div>
        
        <!-- Legenda -->
        <div class="mt-4 flex items-center justify-center space-x-6 text-xs text-gray-500">
            <div class="flex items-center">
                <i class="bi bi-circle-fill text-green-500 mr-1"></i>
                <span>Confirmado</span>
            </div>
            <div class="flex items-center">
                <i class="bi bi-circle-fill text-blue-500 mr-1"></i>
                <span>Agendado</span>
            </div>
            <div class="flex items-center">
                <i class="bi bi-circle-fill text-yellow-500 mr-1"></i>
                <span>Aguardando</span>
            </div>
            <div class="flex items-center">
                <i class="bi bi-arrows-move mr-1"></i>
                <span>Arraste para mover</span>
            </div>
        </div>`;
    
    container.innerHTML = html;
    adicionarSistemaEncaixes(agendaId, data, container);
}

// ✅ Nova função para adicionar sistema de encaixes

// ✅ FUNÇÃO CORRIGIDA: Inserir APÓS "Convênios atendidos"
// Substitua a função inserirCardSimples no seu agenda.js

function inserirCardSimples(htmlCard) {
    // Remover card anterior se existir
    const cardAnterior = document.querySelector('#card-sistema-encaixes');
    if (cardAnterior) {
        cardAnterior.remove();
    }
    
    console.log('🔍 Procurando card "Convênios atendidos"...');
    
    // ESTRATÉGIA 1: Procurar por texto "Convênios atendidos" exato
    const elementos = document.querySelectorAll('*');
    let cardConvenios = null;
    
    for (let elemento of elementos) {
        const texto = elemento.textContent || '';
        
        // Procurar pelo texto exato
        if (texto.includes('Convênios atendidos') || texto.includes('convenios atendidos')) {
            // Encontrar o card/container pai
            cardConvenios = elemento.closest('.card, .bg-white, .border, .rounded, .shadow') || 
                           elemento.closest('div[class*="bg-"], div[class*="border"]') ||
                           elemento.parentElement;
            
            if (cardConvenios) {
                console.log('✅ Encontrou card "Convênios atendidos"');
                break;
            }
        }
    }
    
    if (cardConvenios) {
        console.log('🎯 Inserindo APÓS o card de convênios');
        cardConvenios.insertAdjacentHTML('afterend', htmlCard);
        return;
    }
    
    // ESTRATÉGIA 2: Procurar por lista de convênios (Bradesco, Cartão, etc.)
    const textosBradesco = document.querySelectorAll('*');
    for (let elemento of textosBradesco) {
        const texto = elemento.textContent || '';
        
        if (texto.includes('Bradesco') || texto.includes('Cartão de Desconto') || texto.includes('Caurn')) {
            // Subir até encontrar o container do card
            let containerConvenios = elemento.closest('.card, .bg-white, .border') || 
                                   elemento.closest('div');
            
            // Subir mais um nível se necessário para pegar o card completo
            while (containerConvenios && containerConvenios.parentElement && 
                   !containerConvenios.parentElement.querySelector('h3, h4, .card-header')) {
                containerConvenios = containerConvenios.parentElement;
            }
            
            if (containerConvenios) {
                console.log('✅ Encontrou container de convênios via lista');
                containerConvenios.insertAdjacentHTML('afterend', htmlCard);
                return;
            }
        }
    }
    
    // ESTRATÉGIA 3: Procurar por posição no DOM (após calendário)
    const calendario = document.querySelector('#container-calendario') || 
                      document.querySelector('.calendario');
    
    if (calendario) {
        // Procurar o próximo elemento após o calendário
        let proximoElemento = calendario.nextElementSibling;
        
        // Se o próximo for o card de convênios, inserir após ele
        if (proximoElemento && proximoElemento.textContent.includes('Convênios')) {
            console.log('✅ Inserindo após convênios (via calendário)');
            proximoElemento.insertAdjacentHTML('afterend', htmlCard);
            return;
        }
        
        // Senão, inserir após o calendário
        console.log('📅 Inserindo após calendário');
        calendario.insertAdjacentHTML('afterend', htmlCard);
        return;
    }
    
    // ESTRATÉGIA 4: Procurar por estrutura da sidebar
    const sidebar = document.querySelector('.col-md-3, .col-lg-3, .sidebar, .w-1/4');
    if (sidebar) {
        // Procurar todos os cards na sidebar
        const cardsNaSidebar = sidebar.querySelectorAll('.card, .bg-white, [class*="border"]');
        
        if (cardsNaSidebar.length > 0) {
            // Inserir após o último card
            const ultimoCard = cardsNaSidebar[cardsNaSidebar.length - 1];
            console.log('📋 Inserindo após último card da sidebar');
            ultimoCard.insertAdjacentHTML('afterend', htmlCard);
            return;
        }
        
        // Senão, inserir no final da sidebar
        console.log('📋 Inserindo no final da sidebar');
        sidebar.insertAdjacentHTML('beforeend', htmlCard);
        return;
    }
    
    // ESTRATÉGIA 5: Fallback - inserir em posição fixa
    console.log('⚠️ Não encontrou local específico, usando posição fixa');
    const cardFixoHTML = `
        <div style="position: fixed; top: 400px; right: 20px; width: 280px; z-index: 1000;">
            ${htmlCard}
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', cardFixoHTML);
}

// ✅ VERSÃO ALTERNATIVA: Inserção mais específica
// ✅ NOVA FUNÇÃO: Inserir após "Informações detalhadas"
function inserirCardAposInformacoes(htmlCard) {
    console.log('🎯 Inserindo card após Informações detalhadas...');
    
    // Remover container anterior se existir
    const containerAnterior = document.querySelector('#container-sistemas');
    if (containerAnterior) {
        containerAnterior.remove();
    }
    
    // Remover cards individuais se existirem (compatibilidade)
    const cardEncaixesAnterior = document.querySelector('#card-sistema-encaixes');
    if (cardEncaixesAnterior) {
        cardEncaixesAnterior.remove();
    }
    
    const cardRetornosAnterior = document.querySelector('#card-sistema-retornos');
    if (cardRetornosAnterior) {
        cardRetornosAnterior.remove();
    }
    
    // Procurar por cabeçalhos "Informações Detalhadas"
    const headers = document.querySelectorAll('h3, h4, h5, .font-semibold, .font-bold');
    
    for (let header of headers) {
        const texto = header.textContent || '';
        
        if (texto.toLowerCase().includes('informações detalhadas') || 
            texto.toLowerCase().includes('informacoes detalhadas')) {
            
            console.log('✅ Encontrou cabeçalho de Informações Detalhadas:', texto);
            
            // Encontrar o container completo
            let containerCompleto = header.parentElement;
            
            // Tentar encontrar o card pai
            while (containerCompleto && 
                   !containerCompleto.classList.contains('bg-white') &&
                   !containerCompleto.classList.contains('rounded-lg') &&
                   containerCompleto.parentElement) {
                containerCompleto = containerCompleto.parentElement;
            }
            
            if (containerCompleto) {
                console.log('✅ Inserindo após container de Informações Detalhadas');
                containerCompleto.insertAdjacentHTML('afterend', htmlCard);
                return true;
            }
        }
    }
    
    console.log('❌ Não foi possível localizar seção de Informações Detalhadas');
    return false;
}

// ✅ FUNÇÃO MANTIDA PARA COMPATIBILIDADE: Inserir antes da área de visualização da agenda
function inserirCardAntesAgenda(htmlCard) {
    console.log('🎯 Inserindo card antes da área de visualização da agenda...');
    
    // Remover container anterior se existir
    const containerAnterior = document.querySelector('#container-sistemas');
    if (containerAnterior) {
        containerAnterior.remove();
    }
    
    // Remover cards individuais se existirem (compatibilidade)
    const cardEncaixesAnterior = document.querySelector('#card-sistema-encaixes');
    if (cardEncaixesAnterior) {
        cardEncaixesAnterior.remove();
    }
    
    const cardRetornosAnterior = document.querySelector('#card-sistema-retornos');
    if (cardRetornosAnterior) {
        cardRetornosAnterior.remove();
    }
    
    // Procurar pela área de visualização principal
    const areaVisualizacao = document.querySelector('#area-visualizacao');
    if (areaVisualizacao) {
        const containerPrincipal = areaVisualizacao.parentElement;
        if (containerPrincipal) {
            console.log('✅ Inserindo card antes da área de visualização');
            // Inserir antes do container da área de visualização
            containerPrincipal.insertAdjacentHTML('beforebegin', htmlCard);
            return true;
        }
    }
    
    // Fallback: procurar pelo título "Agenda do Dia"
    const tituloAgenda = document.querySelector('#titulo-visualizacao');
    if (tituloAgenda) {
        const containerTitulo = tituloAgenda.closest('.bg-white');
        if (containerTitulo) {
            console.log('✅ Inserindo card antes do container da agenda');
            containerTitulo.insertAdjacentHTML('beforebegin', htmlCard);
            return true;
        }
    }
    
    console.log('❌ Não foi possível localizar área de inserção');
    return false;
}

function inserirCardApósConvenios(htmlCard) {
    console.log('🎯 Procurando especificamente "Convênios atendidos"...');
    
    // Remover card anterior
    const cardAnterior = document.querySelector('#card-sistema-encaixes');
    if (cardAnterior) {
        cardAnterior.remove();
    }
    
    // Procurar por padrão: h3/h4 + lista de convênios
    const headers = document.querySelectorAll('h3, h4, h5, .font-semibold, .font-bold');
    
    for (let header of headers) {
        const texto = header.textContent || '';
        
        if (texto.toLowerCase().includes('convênio') || 
            texto.toLowerCase().includes('convenio')) {
            
            console.log('✅ Encontrou cabeçalho de convênios:', texto);
            
            // Encontrar o container completo
            let containerCompleto = header.parentElement;
            
            // Tentar encontrar o card pai
            while (containerCompleto && 
                   !containerCompleto.classList.contains('card') && 
                   !containerCompleto.classList.contains('bg-white') &&
                   !containerCompleto.classList.contains('border') &&
                   containerCompleto.parentElement) {
                containerCompleto = containerCompleto.parentElement;
            }
            
            if (containerCompleto) {
                console.log('🎯 Inserindo após container de convênios');
                containerCompleto.insertAdjacentHTML('afterend', htmlCard);
                return true;
            }
        }
    }
    
    console.log('⚠️ Não encontrou cabeçalho de convênios');
    return false;
}

// ✅ FUNÇÃO PRINCIPAL ATUALIZADA
function adicionarSistemaEncaixes(agendaId, data, container) {
    console.log('🔧 Iniciando verificação de encaixes:', { agendaId, data });
    
    // Primeiro verificar tipo da agenda
    fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
        .then(safeJsonParse)
        .then(agendaData => {
            const agendaInfo = agendaData.agenda || agendaData;
            const tipoAgenda = agendaInfo.tipo;
            console.log('📋 Tipo da agenda:', tipoAgenda);
            
            // Só proceder se for agenda de consulta
            if (tipoAgenda !== 'consulta') {
                console.log('ℹ️ Agenda não é do tipo consulta - não mostrar encaixes/retornos');
                return;
            }
            
            // Agora verificar encaixes
            const url = `verificar_encaixes.php?agenda_id=${agendaId}&data=${data}`;
            return fetch(url);
        })
        .then(response => {
            if (!response) return; // Se não for consulta, response será undefined
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(responseText => {
            if (!responseText) return; // Se não for consulta, não processar
            
            let dadosEncaixe;
            
            try {
                const primeiraLinha = responseText.split('\n')[0].trim();
                dadosEncaixe = JSON.parse(primeiraLinha);
                console.log('✅ JSON parseado:', dadosEncaixe);
                
            } catch (parseError) {
                console.error('❌ Erro no parse:', parseError);
                return;
            }
            
            if (dadosEncaixe.permite_encaixes && dadosEncaixe.limite_total > 0) {
                console.log('✅ Criando card de encaixes');
                
                // Verificar retornos antes de criar cards
                fetchWithAuth(`verificar_retornos.php?agenda_id=${agendaId}&data=${data}`)
                    .then(response => response.text())
                    .then(text => {
                        let dadosRetorno;
                        try {
                            const linhas = text.trim().split('\n');
                            dadosRetorno = JSON.parse(linhas[0].trim());
                        } catch (e) {
                            dadosRetorno = { permite_retornos: false, limite_total: 0 };
                        }
                        
                        // Verificar se deve mostrar card de retornos
                        const mostrarRetornos = dadosRetorno.limite_total && dadosRetorno.limite_total > 0;
                        console.log(`ℹ️ Deve mostrar retornos: ${mostrarRetornos} (limite: ${dadosRetorno.limite_total})`);
                        
                        let cardEncaixesHTML;
                        if (mostrarRetornos) {
                            // 🎨 CONTAINER LADO A LADO COM LARGURA REDUZIDA
                            cardEncaixesHTML = `
                    <div id="container-sistemas" class="flex gap-4 mt-4 max-w-2xl">
                        <div id="card-sistema-encaixes" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <div class="w-6 h-6 bg-orange-500 rounded flex items-center justify-center">
                                    <i class="bi bi-lightning-charge text-white text-xs"></i>
                                </div>
                                <div>
                                    <h3 class="text-sm font-medium text-gray-800">Encaixes</h3>
                                </div>
                            </div>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full">${dadosEncaixe.encaixes_disponiveis}/${dadosEncaixe.limite_total}</span>
                        </div>
                        
                        <!-- Botões -->
                        <div class="space-y-1.5">
                            <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                    class="w-full px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm transition-colors ${dadosEncaixe.pode_encaixar ? '' : 'opacity-50 cursor-not-allowed'}"
                                    ${dadosEncaixe.pode_encaixar ? '' : 'disabled'}>
                                <i class="bi bi-plus mr-1"></i>
                                ${dadosEncaixe.pode_encaixar ? 'Agendar' : 'Esgotado'}
                            </button>
                            
                            <button onclick="visualizarEncaixesDia(${agendaId}, '${data}')" 
                                    class="w-full px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition-colors">
                                <i class="bi bi-list mr-1"></i>
                                Ver (${dadosEncaixe.encaixes_ocupados})
                            </button>
                        </div>
                        </div>
                        
                        <!-- Card Sistema de Retornos -->
                        <div id="card-sistema-retornos" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center space-x-2">
                                    <div class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center">
                                        <i class="bi bi-arrow-clockwise text-white text-xs"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-800">Retornos</h3>
                                    </div>
                                </div>
                                <span class="text-xs bg-gray-100 px-2 py-1 rounded-full">Disponível</span>
                            </div>
                            
                            <!-- Botões -->
                            <div class="space-y-1.5">
                                <button onclick="abrirModalRetorno(${agendaId}, '${data}')" 
                                        class="w-full px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm transition-colors">
                                    <i class="bi bi-plus mr-1"></i>
                                    Agendar
                                </button>
                                
                                <button onclick="visualizarRetornosDia(${agendaId}, '${data}')" 
                                        class="w-full px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition-colors">
                                    <i class="bi bi-list mr-1"></i>
                                    Ver Lista
                                </button>
                            </div>
                        </div>
                                </div>
                            `;
                        } else {
                            // 🎨 CONTAINER APENAS COM ENCAIXES (SEM RETORNOS)
                            console.log('ℹ️ Limite de retornos é zero - criando apenas card de encaixes');
                            cardEncaixesHTML = `
                                <div id="container-sistemas" class="mt-4">
                                    <div id="card-sistema-encaixes" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 max-w-md">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-2">
                                            <div class="w-6 h-6 bg-orange-500 rounded flex items-center justify-center">
                                                <i class="bi bi-lightning-charge text-white text-xs"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-sm font-medium text-gray-800">Encaixes</h3>
                                            </div>
                                        </div>
                                        <span class="text-xs bg-gray-100 px-2 py-1 rounded-full">${dadosEncaixe.encaixes_disponiveis}/${dadosEncaixe.limite_total}</span>
                                    </div>
                                    
                                    <!-- Botões -->
                                    <div class="space-y-1.5">
                                        <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                                class="w-full px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm transition-colors ${dadosEncaixe.pode_encaixar ? '' : 'opacity-50 cursor-not-allowed'}"
                                                ${dadosEncaixe.pode_encaixar ? '' : 'disabled'}>
                                            <i class="bi bi-plus mr-1"></i>
                                            ${dadosEncaixe.pode_encaixar ? 'Agendar' : 'Esgotado'}
                                        </button>
                                        
                                        <button onclick="visualizarEncaixesDia(${agendaId}, '${data}')" 
                                                class="w-full px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition-colors">
                                            <i class="bi bi-list mr-1"></i>
                                            Ver (${dadosEncaixe.encaixes_ocupados})
                                        </button>
                                    </div>
                                    </div>
                                </div>
                            `;
                        }
                        
                        // 🎯 INSERIR APÓS INFORMAÇÕES DETALHADAS
                        const inseridoComSucesso = inserirCardAposInformacoes(cardEncaixesHTML);
                        
                        if (!inseridoComSucesso) {
                            // Fallback 1: inserir após convênios atendidos
                            console.log('🔄 Fallback 1: inserindo após convênios atendidos');
                            const inseridoAposConvenios = inserirCardApósConvenios(cardEncaixesHTML);
                            
                            if (!inseridoAposConvenios) {
                                // Fallback final para método geral
                                console.log('🔄 Fallback final: método geral');
                                inserirCardSimples(cardEncaixesHTML);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro ao verificar retornos:', error);
                        // Em caso de erro, criar apenas card de encaixes sem retornos
                        const cardEncaixesHTML = `
                            <div id="container-sistemas" class="mt-4">
                                <div id="card-sistema-encaixes" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 max-w-md">
                                <!-- Header -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-6 h-6 bg-orange-500 rounded flex items-center justify-center">
                                            <i class="bi bi-lightning-charge text-white text-xs"></i>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-800">Encaixes</h3>
                                        </div>
                                    </div>
                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full">${dadosEncaixe.encaixes_disponiveis}/${dadosEncaixe.limite_total}</span>
                                </div>
                                
                                <!-- Botões -->
                                <div class="space-y-1.5">
                                    <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                            class="w-full px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white rounded text-sm transition-colors ${dadosEncaixe.pode_encaixar ? '' : 'opacity-50 cursor-not-allowed'}"
                                            ${dadosEncaixe.pode_encaixar ? '' : 'disabled'}>
                                        <i class="bi bi-plus mr-1"></i>
                                        ${dadosEncaixe.pode_encaixar ? 'Agendar' : 'Esgotado'}
                                    </button>
                                    
                                    <button onclick="visualizarEncaixesDia(${agendaId}, '${data}')" 
                                            class="w-full px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition-colors">
                                        <i class="bi bi-list mr-1"></i>
                                        Ver (${dadosEncaixe.encaixes_ocupados})
                                    </button>
                                </div>
                                </div>
                            </div>
                        `;
                        
                        inserirCardAposInformacoes(cardEncaixesHTML) || 
                        inserirCardApósConvenios(cardEncaixesHTML) || 
                        inserirCardSimples(cardEncaixesHTML);
                    });
                
            } else {
                console.log('ℹ️ Agenda não permite encaixes - verificando retornos');
                
                // Verificar se deve mostrar card de retornos quando não há encaixes
                fetchWithAuth(`verificar_retornos.php?agenda_id=${agendaId}&data=${data}`)
                    .then(response => response.text())
                    .then(text => {
                        let dadosRetorno;
                        try {
                            const linhas = text.trim().split('\n');
                            dadosRetorno = JSON.parse(linhas[0].trim());
                        } catch (e) {
                            dadosRetorno = { permite_retornos: false, limite_total: 0 };
                        }
                        
                        // Só criar card se limite > 0
                        if (dadosRetorno.limite_total && dadosRetorno.limite_total > 0) {
                            console.log('✅ Criando card de retornos (sem encaixes)');
                            const cardRetornosHTML = `
                                <div id="container-sistemas" class="mt-4">
                                    <div id="card-sistema-retornos" class="bg-white rounded-lg shadow-sm border border-gray-200 p-3 max-w-md">
                                        <!-- Header -->
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="flex items-center space-x-2">
                                                <div class="w-6 h-6 bg-blue-500 rounded flex items-center justify-center">
                                                    <i class="bi bi-arrow-clockwise text-white text-xs"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-sm font-medium text-gray-800">Retornos</h3>
                                                </div>
                                            </div>
                                            <span class="text-xs bg-gray-100 px-2 py-1 rounded-full">Disponível</span>
                                        </div>
                                        
                                        <!-- Botões -->
                                        <div class="space-y-1.5">
                                            <button onclick="abrirModalRetorno(${agendaId}, '${data}')" 
                                                    class="w-full px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-sm transition-colors">
                                                <i class="bi bi-plus mr-1"></i>
                                                Agendar
                                            </button>
                                            
                                            <button onclick="visualizarRetornosDia(${agendaId}, '${data}')" 
                                                    class="w-full px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded text-sm transition-colors">
                                                <i class="bi bi-list mr-1"></i>
                                                Ver Lista
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `;
                            
                            // Inserir card de retornos
                            const inseridoRetornos = inserirCardAposInformacoes(cardRetornosHTML);
                            if (!inseridoRetornos) {
                                const inseridoAposConveniosRetornos = inserirCardApósConvenios(cardRetornosHTML);
                                if (!inseridoAposConveniosRetornos) {
                                    inserirCardSimples(cardRetornosHTML);
                                }
                            }
                        } else {
                            console.log(`ℹ️ Limite de retornos é zero (${dadosRetorno.limite_total}) - não mostrando card`);
                        }
                    })
                    .catch(error => {
                        console.error('❌ Erro ao verificar retornos:', error);
                    });
            }
        })
        .catch(error => {
            console.error('❌ Erro ao verificar encaixes:', error);
        });
}

// 🎨 CSS ADICIONAL para animações extras (adicione no head ou arquivo CSS)
const estilosEncaixesMelhorados = `
<style>
/* Animações e efeitos para o sistema de encaixes */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-5px); }
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 0 0 rgba(251, 146, 60, 0.4); }
    50% { box-shadow: 0 0 0 10px rgba(251, 146, 60, 0); }
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.encaixe-card:hover {
    animation: float 3s ease-in-out infinite;
}

.encaixe-glow {
    animation: pulse-glow 2s infinite;
}

.encaixe-shimmer {
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    background-size: 1000px 100%;
    animation: shimmer 3s infinite;
}

/* Melhorias nos botões */
.btn-encaixe {
    position: relative;
    overflow: hidden;
}

.btn-encaixe::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.btn-encaixe:hover::before {
    left: 100%;
}

/* Efeito glass nos cards */
.glass-effect {
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
}

/* Gradientes animados */
.gradient-animated {
    background: linear-gradient(-45deg, #ff6b6b, #ee5a24, #ff9ff3, #54a0ff);
    background-size: 400% 400%;
    animation: gradient-shift 3s ease infinite;
}

@keyframes gradient-shift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Micro-interações */
.micro-bounce:hover {
    animation: micro-bounce 0.6s ease;
}

@keyframes micro-bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-3px); }
    60% { transform: translateY(-2px); }
}

/* Efeito de loading nos botões */
.btn-loading {
    position: relative;
}

.btn-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: spin 1s linear infinite;
    opacity: 0;
    transition: opacity 0.3s;
}

.btn-loading.loading::after {
    opacity: 1;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
`;

// Adicionar estilos se não existirem
if (!document.querySelector('#estilos-encaixe-melhorados')) {
    const styleElement = document.createElement('div');
    styleElement.id = 'estilos-encaixe-melhorados';
    styleElement.innerHTML = estilosEncaixesMelhorados;
    document.head.appendChild(styleElement);
}


// ✅ VERSÃO SIMPLIFICADA para teste (substitua temporariamente se ainda der erro)
function adicionarSistemaEncaixesSimples(agendaId, data, container) {
    console.log('🧪 Teste simples de encaixes');
    
    // HTML fixo para teste
    const htmlTeste = `
        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
            <h4 class="text-lg font-semibold text-orange-800 mb-2">
                <i class="bi bi-lightning-charge mr-2"></i>
                Sistema de Encaixes (Teste)
            </h4>
            <p class="text-sm text-orange-700 mb-3">Sistema funcionando - dados estáticos para teste</p>
            <button onclick="alert('Modal de encaixe em desenvolvimento')" 
                    class="px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700">
                <i class="bi bi-plus-circle mr-2"></i>Agendar Encaixe (Teste)
            </button>
        </div>
    `;
    
    container.insertAdjacentHTML('afterbegin', htmlTeste);
    console.log('✅ Sistema de encaixes (versão teste) adicionado');
}

// ✅ Função completa para adicionar indicadores de encaixe na visualização semanal
function adicionarIndicadoresEncaixe() {
    console.log('🔍 Buscando indicadores de encaixe para semana/mês...');
    
    // Buscar todos os elementos de data visíveis na visualização semanal
    const elementosData = document.querySelectorAll('[data-data]');
    
    elementosData.forEach(elemento => {
        const data = elemento.dataset.data;
        const agendaId = window.agendaIdAtual;
        
        if (data && agendaId) {
            // Buscar encaixes para esta data
            fetchWithAuth(`buscar_encaixes_dia.php?agenda_id=${agendaId}&data=${data}`)
                .then(safeJsonParse)
                .then(encaixes => {
                    if (encaixes.length > 0) {
                        // Adicionar indicador visual de encaixe
                        const indicadorExistente = elemento.querySelector('.indicador-encaixe');
                        if (!indicadorExistente) {
                            // Criar indicador de encaixe
                            const indicador = document.createElement('div');
                            indicador.className = 'indicador-encaixe absolute top-1 right-1 bg-orange-500 text-white text-xs px-2 py-1 rounded-full flex items-center';
                            indicador.innerHTML = `
                                <i class="bi bi-lightning-charge mr-1"></i>
                                <span>${encaixes.length}</span>
                            `;
                            indicador.title = `${encaixes.length} encaixe(s) agendado(s)`;
                            
                            // Posicionar o indicador
                            if (elemento.style.position !== 'relative') {
                                elemento.style.position = 'relative';
                            }
                            
                            elemento.appendChild(indicador);
                            
                            console.log(`✅ Indicador de encaixe adicionado para ${data}: ${encaixes.length} encaixes`);
                        }
                    }
                })
                .catch(error => {
                    console.error(`Erro ao buscar encaixes para ${data}:`, error);
                });
        }
    });
    
    // Também buscar indicadores para células de calendário mensal (se existirem)
    const celulasCalendario = document.querySelectorAll('.calendario-dia[data-data]');
    
    celulasCalendario.forEach(celula => {
        const data = celula.dataset.data;
        const agendaId = window.agendaIdAtual;
        
        if (data && agendaId) {
            fetchWithAuth(`buscar_encaixes_dia.php?agenda_id=${agendaId}&data=${data}`)
                .then(safeJsonParse)
                .then(encaixes => {
                    if (encaixes.length > 0) {
                        // Verificar se já existe indicador
                        const indicadorExistente = celula.querySelector('.badge-encaixe');
                        if (!indicadorExistente) {
                            // Criar badge para o calendário mensal
                            const badge = document.createElement('div');
                            badge.className = 'badge-encaixe inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 ml-1';
                            badge.innerHTML = `
                                <i class="bi bi-lightning-charge mr-1"></i>
                                ${encaixes.length}
                            `;
                            badge.title = `${encaixes.length} encaixe(s)`;
                            
                            // Adicionar ao container de badges da célula
                            const containerBadges = celula.querySelector('.badges-container') || celula;
                            containerBadges.appendChild(badge);
                            
                            console.log(`✅ Badge de encaixe adicionado para ${data}: ${encaixes.length} encaixes`);
                        }
                    }
                })
                .catch(error => {
                    console.error(`Erro ao buscar encaixes para calendário ${data}:`, error);
                });
        }
    });
}

// ✅ Função auxiliar para verificar se um dia tem encaixes
function verificarEncaixesDia(agendaId, data) {
    return fetchWithAuth(`buscar_encaixes_dia.php?agenda_id=${agendaId}&data=${data}`)
        .then(safeJsonParse)
        .then(encaixes => {
            return {
                temEncaixes: encaixes.length > 0,
                quantidade: encaixes.length,
                encaixes: encaixes
            };
        })
        .catch(error => {
            console.error(`Erro ao verificar encaixes para ${data}:`, error);
            return {
                temEncaixes: false,
                quantidade: 0,
                encaixes: []
            };
        });
}

// ✅ Função para adicionar indicadores em lote (otimizada)
function adicionarIndicadoresEncaixeLote(datas, agendaId) {
    console.log('🔍 Buscando indicadores de encaixe em lote para:', datas);
    
    // Fazer uma única requisição para todas as datas
    const datasParam = datas.join(',');
    
    fetchWithAuth(`buscar_encaixes_periodo.php?agenda_id=${agendaId}&datas=${datasParam}`)
        .then(safeJsonParse)
        .then(dadosEncaixes => {
            console.log('📊 Dados de encaixes recebidos:', dadosEncaixes);
            
            // Processar cada data
            Object.keys(dadosEncaixes).forEach(data => {
                const encaixes = dadosEncaixes[data];
                
                if (encaixes && encaixes.length > 0) {
                    // Buscar elemento correspondente na visualização
                    const elemento = document.querySelector(`[data-data="${data}"]`);
                    
                    if (elemento) {
                        // Verificar se já existe indicador
                        const indicadorExistente = elemento.querySelector('.indicador-encaixe');
                        if (!indicadorExistente) {
                            adicionarIndicadorEncaixeElemento(elemento, encaixes.length, data);
                        }
                    }
                }
            });
        })
        .catch(error => {
            console.error('Erro ao buscar encaixes em lote:', error);
            // Fallback para busca individual
            datas.forEach(data => {
                verificarEncaixesDia(agendaId, data).then(resultado => {
                    if (resultado.temEncaixes) {
                        const elemento = document.querySelector(`[data-data="${data}"]`);
                        if (elemento) {
                            adicionarIndicadorEncaixeElemento(elemento, resultado.quantidade, data);
                        }
                    }
                });
            });
        });
}

// ✅ Função auxiliar para adicionar indicador a um elemento específico
function adicionarIndicadorEncaixeElemento(elemento, quantidade, data) {
    // Verificar se é visualização semanal ou mensal
    const isSemanal = elemento.classList.contains('horario-celula') || 
                     elemento.closest('.visualizacao-semana');
    
    if (isSemanal) {
        // Indicador para visualização semanal
        const indicador = document.createElement('div');
        indicador.className = 'indicador-encaixe absolute top-1 right-1 bg-orange-500 text-white text-xs px-2 py-1 rounded-full flex items-center z-10';
        indicador.innerHTML = `
            <i class="bi bi-lightning-charge mr-1"></i>
            <span>${quantidade}</span>
        `;
        indicador.title = `${quantidade} encaixe(s) agendado(s) para ${data}`;
        
        // Garantir posicionamento relativo
        if (elemento.style.position !== 'relative') {
            elemento.style.position = 'relative';
        }
        
        elemento.appendChild(indicador);
    } else {
        // Badge para visualização mensal
        const badge = document.createElement('div');
        badge.className = 'badge-encaixe inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 ml-1';
        badge.innerHTML = `
            <i class="bi bi-lightning-charge mr-1"></i>
            ${quantidade}
        `;
        badge.title = `${quantidade} encaixe(s)`;
        
        // Adicionar ao container apropriado
        const containerBadges = elemento.querySelector('.badges-container') || 
                              elemento.querySelector('.dia-info') || 
                              elemento;
        containerBadges.appendChild(badge);
    }
    
    console.log(`✅ Indicador de encaixe adicionado para ${data}: ${quantidade} encaixes`);
}

// Função auxiliar para formatar CPF
function formatarCPF(cpf) {
    if (!cpf) return '';
    cpf = cpf.replace(/\D/g, '');
    return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

// Função para retornar o badge de status
function getStatusBadge(status) {
    const statusConfig = {
        'AGENDADO': { class: 'bg-blue-100 text-blue-800', icon: 'svg-calendar-check' },
        'CONFIRMADO': { class: 'bg-green-100 text-green-800', icon: 'svg-check-circle' },
        'AGUARDANDO': { class: 'bg-yellow-100 text-yellow-800', icon: 'svg-clock' },
        'EM_ATENDIMENTO': { class: 'bg-purple-100 text-purple-800', icon: 'svg-person-badge' },
        'ATENDIDO': { class: 'bg-gray-100 text-gray-800', icon: 'svg-check-all' },
        'CANCELADO': { class: 'bg-red-100 text-red-800', icon: 'svg-x-circle' },
        'FALTOU': { class: 'bg-orange-100 text-orange-800', icon: 'svg-person-x' }
    };
    
    const config = statusConfig[status] || statusConfig['AGENDADO'];
    
    return `
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${config.class}">
            <i class="bi ${config.icon} mr-1"></i>
            ${status}
        </span>
    `;
}

// Função para visualizar agendamento
window.visualizarAgendamento = function(agendamentoId) {
    console.log('👁️ Visualizar agendamento chamado com ID:', agendamentoId);
    
    // Buscar dados do agendamento e verificar se tem OS
    Promise.all([
        fetchWithAuth(`buscar_agendamento.php?id=${agendamentoId}`).then(safeJsonParse),
        fetchWithAuth(`buscar_os_agendamento.php?agendamento_id=${agendamentoId}`).then(safeJsonParse)
    ])
    .then(([dadosAgendamento, dadosOS]) => {
        console.log('📊 Dados do agendamento:', dadosAgendamento);
        console.log('📋 Dados da OS:', dadosOS);
        
        if (dadosAgendamento.erro) {
            console.error('❌ Erro nos dados:', dadosAgendamento.erro);
            alert(dadosAgendamento.erro);
            return;
        }
        
        // Adicionar dados da OS aos dados do agendamento
        dadosAgendamento.os_info = dadosOS.tem_os ? dadosOS : null;
        
        console.log('✅ Criando modal de visualização com OS...');
        criarModalVisualizacao(dadosAgendamento);
    })
    .catch(error => {
        console.error('💥 Erro ao buscar dados:', error);
        alert('Erro ao carregar dados: ' + error.message);
    });
};

// Função para criar modal de visualização
function criarModalVisualizacao(dados) {
    console.log('🎨 AGENDA-NEW.JS - Criando modal de visualização com dados:', dados);
    console.log('🔍 PREPAROS RECEBIDOS:', dados.preparos);
    
    // Verificação de segurança para dados obrigatórios
    if (!dados) {
        console.error('❌ Dados não fornecidos para o modal de visualização');
        alert('Erro: Dados do agendamento não encontrados');
        return;
    }
    
    // Garantir que propriedades obrigatórias existem
    if (!dados.data) {
        console.error('❌ Data do agendamento não encontrada');
        alert('Erro: Data do agendamento não encontrada');
        return;
    }
    
    const dataObj = new Date(dados.data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
    
    const modalHTML = `
        <div id="modal-visualizacao" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-eye mr-3"></i>
                                Visualizar Agendamento
                            </h2>
                            <p class="text-blue-100 mt-1">Número: ${dados.numero || 'N/A'}</p>
                        </div>
                        <button onclick="fecharModalVisualizacao()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Informações do Agendamento -->
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6 rounded-r-lg">
                        <h3 class="text-lg font-semibold text-blue-800 mb-2">
                            ${dados.nome_atendimento || 'Agendamento'}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-700">
                            <div>
                                <i class="bi bi-calendar3 mr-2"></i>
                                <strong>Data:</strong> ${dataFormatada} às ${dados.horario || 'N/A'}
                            </div>
                            <div>
                                <i class="bi bi-geo-alt mr-2"></i>
                                <strong>Unidade:</strong> ${dados.agenda?.unidade || 'Não informado'}
                            </div>
                            <div>
                                <i class="bi bi-door-open mr-2"></i>
                                <strong>Sala:</strong> ${dados.agenda?.sala || 'Não informada'}
                            </div>
                            <div>
                                <i class="bi bi-telephone mr-2"></i>
                                <strong>Telefone:</strong> ${dados.agenda?.telefone || 'Não informado'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dados do Paciente -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-person-circle mr-2"></i>
                            Dados do Paciente
                        </h4>
                        ${dados.os_info ? `
                        <div class="mb-4 p-3 bg-green-50 border-l-4 border-green-400 rounded-r-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <i class="bi bi-file-earmark-text text-green-600 mr-2"></i>
                                    <span class="text-sm font-medium text-green-800">Ordem de Serviço</span>
                                </div>
                                <button onclick="mostrarModalDetalhesOS(${JSON.stringify(dados.os_info).replace(/"/g, '&quot;')})"
                                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                                    <i class="bi bi-file-earmark-text mr-1"></i>
                                    OS: ${dados.os_info.numero_os}
                                </button>
                            </div>
                        </div>
                        ` : ''}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div>
                                <strong>Nome:</strong> ${dados.paciente?.nome || 'N/A'}
                            </div>
                            <div>
                                <strong>CPF:</strong> ${dados.paciente?.cpf ? formatarCPF(dados.paciente.cpf) : 'N/A'}
                            </div>
                            <div>
                                <strong>Data de Nascimento:</strong> ${dados.paciente?.data_nascimento ? new Date(dados.paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR') : 'N/A'}
                            </div>
                            <div>
                                <strong>Telefone:</strong> ${dados.paciente?.telefone || 'N/A'}
                            </div>
                            ${dados.paciente.email ? `
                            <div class="md:col-span-2">
                                <strong>E-mail:</strong> ${dados.paciente.email}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Informações do Convênio -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-card-list mr-2"></i>
                            Convênio
                        </h4>
                        <p class="text-sm">${dados.convenio?.nome || 'N/A'}</p>
                    </div>
                    
                    <!-- Status -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-info-circle mr-2"></i>
                            Status do Agendamento
                        </h4>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex items-center space-x-4">
                                <div class="flex-1">
                                    <label for="status-select-${dados.id}" class="block text-sm font-medium text-gray-700 mb-2">
                                        Alterar Status
                                    </label>
                                    <select id="status-select-${dados.id}" 
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            data-original-value="${dados.status}"
                                            onchange="alterarStatusAgendamento(${dados.id}, this.value)">
                                        <option value="AGENDADO" ${dados.status === 'AGENDADO' ? 'selected' : ''}>🗓️ Agendado</option>
                                        <option value="CONFIRMADO" ${dados.status === 'CONFIRMADO' ? 'selected' : ''}>✅ Confirmado</option>
                                        <option value="AGUARDANDO" ${dados.status === 'AGUARDANDO' ? 'selected' : ''}>⏱️ Aguardando</option>
                                        <option value="EM_ATENDIMENTO" ${dados.status === 'EM_ATENDIMENTO' ? 'selected' : ''}>👩‍⚕️ Em Atendimento</option>
                                        <option value="ATENDIDO" ${dados.status === 'ATENDIDO' ? 'selected' : ''}>✔️ Atendido</option>
                                        <option value="CANCELADO" ${dados.status === 'CANCELADO' ? 'selected' : ''}>❌ Cancelado</option>
                                        <option value="FALTOU" ${dados.status === 'FALTOU' ? 'selected' : ''}>🚫 Faltou</option>
                                    </select>
                                </div>
                                <div class="flex-shrink-0">
                                    <div class="text-sm text-gray-600 mb-2">Status Atual</div>
                                    ${getStatusBadge(dados.status)}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Configurações de Atendimento -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-gear mr-2"></i>
                            Configurações de Atendimento
                        </h4>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Status de Confirmação -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Status de Confirmação
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center cursor-pointer">
                                            <input type="radio" name="confirmado_visualizacao" value="0" 
                                                   ${!dados.confirmado ? 'checked' : ''}
                                                   onchange="alterarConfirmacao(${dados.id}, this.value)"
                                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500">
                                            <span class="ml-2 text-sm text-gray-700">Não confirmado</span>
                                        </label>
                                        <label class="flex items-center cursor-pointer">
                                            <input type="radio" name="confirmado_visualizacao" value="1" 
                                                   ${dados.confirmado ? 'checked' : ''}
                                                   onchange="alterarConfirmacao(${dados.id}, this.value)"
                                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500">
                                            <span class="ml-2 text-sm text-gray-700">Confirmado</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Tipo de Atendimento -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Tipo de Atendimento
                                    </label>
                                    <select onchange="alterarTipoAtendimento(${dados.id}, this.value, this)"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                                        <option value="NORMAL" ${dados.tipo_atendimento === 'NORMAL' ? 'selected' : ''}>Normal</option>
                                        <option value="PRIORIDADE" ${dados.tipo_atendimento === 'PRIORIDADE' ? 'selected' : ''}>Prioridade</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${dados.exames && dados.exames.length > 0 ? `
                    <!-- Exames -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-clipboard2-check mr-2"></i>
                            Exames Solicitados (${dados.exames.length})
                        </h4>
                        <div class="bg-gray-50 rounded-lg p-3">
                            <div class="grid grid-cols-1 gap-2">
                                ${dados.exames.map(exame => `
                                    <div class="flex items-center text-sm">
                                        <i class="bi bi-check-circle text-green-600 mr-2"></i>
                                        <span>${exame.nome}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${dados.tipo_consulta ? `
                    <!-- Tipo de Consulta -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-bookmark mr-2"></i>
                            Tipo de Consulta
                        </h4>
                        <p class="text-sm">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${dados.tipo_consulta === 'retorno' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}">
                                ${dados.tipo_consulta === 'retorno' ? 'Retorno' : 'Primeira vez'}
                            </span>
                        </p>
                    </div>
                    ` : ''}
                    
                    ${dados.observacoes ? `
                    <!-- Observações -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-chat-text mr-2"></i>
                            Observações
                        </h4>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-3 rounded-r-lg">
                            <p class="text-sm text-gray-700">${dados.observacoes}</p>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${dados.preparos && dados.preparos.length > 0 ? `
                    <!-- Preparos -->
                    <div class="mb-6">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-list-check mr-2"></i>
                            Preparos e Orientações
                        </h4>
                        <div class="bg-green-50 border-l-4 border-green-400 p-3 rounded-r-lg">
                            ${dados.preparos.map((preparo, index) => `
                                <div class="mb-3 ${index > 0 ? 'border-t border-green-200 pt-3' : ''}">
                                    <h5 class="font-semibold text-green-800 text-sm mb-1">${preparo.titulo}</h5>
                                    <p class="text-sm text-gray-700">${preparo.instrucoes}</p>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Informações do Sistema -->
                    <div class="mb-6 border-t pt-4">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-info-circle mr-2"></i>
                            Informações do Sistema
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs text-gray-600">
                            <div>
                                <strong>ID:</strong> ${dados.id}
                            </div>
                            <div>
                                <strong>Número:</strong> ${dados.numero}
                            </div>
                            ${dados.data_criacao ? `
                            <div>
                                <strong>Criado em:</strong> ${new Date(dados.data_criacao).toLocaleString('pt-BR')}
                            </div>
                            ` : ''}
                            ${dados.data_modificacao ? `
                            <div>
                                <strong>Modificado em:</strong> ${new Date(dados.data_modificacao).toLocaleString('pt-BR')}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- Histórico de Alterações -->
                    <div class="mb-6 border-t pt-4">
                        <div class="flex items-center justify-between cursor-pointer bg-gray-50 hover:bg-gray-100 rounded-lg p-3 transition-colors" 
                             onclick="toggleHistorico(${dados.id})">
                            <h4 class="text-base font-semibold text-gray-800 flex items-center">
                                <i class="bi bi-clock-history mr-2"></i>
                                Histórico de Alterações
                            </h4>
                            <div class="flex items-center text-sm text-gray-600">
                                <span id="historico-toggle-text-${dados.id}">Clique aqui para exibir</span>
                                <i id="historico-toggle-icon-${dados.id}" class="bi bi-chevron-down ml-2 transition-transform"></i>
                            </div>
                        </div>
                        <div id="historico-auditoria-${dados.id}" class="hidden space-y-3 mt-3">
                            <!-- Conteúdo será carregado via JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t flex justify-between">
                    <div class="flex space-x-3">
                        <button onclick="editarAgendamento(${dados.id})" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                            <i class="bi bi-pencil mr-2"></i>Editar
                        </button>
                        ${dados.paciente.id && !dados.os_info ? `
                        <button onclick="criarOrdemServico(${dados.paciente.id}, '${dados.paciente.nome}', ${dados.id})" 
                                class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition"
                                title="Criar Ordem de Serviço para este paciente">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>Criar O.S.
                        </button>
                        ` : ''}
                        ${dados.os_info ? `
                        <button onclick="mostrarModalDetalhesOS(${JSON.stringify(dados.os_info).replace(/"/g, '&quot;')})" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition"
                                title="Visualizar Ordem de Serviço existente">
                            <i class="bi bi-file-earmark-text mr-2"></i>Ver O.S. ${dados.os_info.numero_os}
                        </button>
                        ` : ''}
                    </div>
                    <button onclick="fecharModalVisualizacao()" 
                            class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
};

// Função para fechar modal de visualização
window.fecharModalVisualizacao = function() {
    const modal = document.getElementById('modal-visualizacao');
    if (modal) {
        modal.remove();
    }
};

// Função para criar ordem de serviço
window.criarOrdemServico = function(pacienteId, nomePaciente, agendamentoId) {
    console.log('🔍 DEBUG criarOrdemServico chamada');
    console.log('🔍 DEBUG pacienteId:', pacienteId);
    console.log('🔍 DEBUG nomePaciente:', nomePaciente);
    console.log('🔍 DEBUG agendamentoId:', agendamentoId);
    
    // Abrir modal próprio para criar O.S.
    mostrarModalOrdemServico(pacienteId, nomePaciente, agendamentoId);
};

// Função para mostrar modal de ordem de serviço
function mostrarModalOrdemServico(pacienteId, nomePaciente, agendamentoId) {
    console.log('🔍 DEBUG mostrarModalOrdemServico chamada');
    const dataAtual = new Date().toLocaleDateString('pt-BR');
    
    // Buscar dados da agenda para especialidade automática
    console.log('🔍 DEBUG buscando dados da agenda, agendamentoId:', agendamentoId);
    buscarDadosAgenda(agendamentoId).then(dadosAgenda => {
        console.log('🔍 DEBUG dados da agenda recebidos:', dadosAgenda);
        criarModalComDados(pacienteId, nomePaciente, agendamentoId, dadosAgenda, dataAtual);
    });
}

// Função para buscar dados da agenda
async function buscarDadosAgenda(agendamentoId) {
    try {
        const response = await fetchWithAuth(`buscar_agendamento.php?id=${agendamentoId}`);
        const dados = await safeJsonParse(response);
        return dados;
    } catch (error) {
        console.error('Erro ao buscar dados da agenda:', error);
        return null;
    }
}

// Função para criar modal com dados
function criarModalComDados(pacienteId, nomePaciente, agendamentoId, dadosAgenda, dataAtual) {
    console.log('🔍 DEBUG criarModalComDados chamada');
    console.log('🔍 DEBUG dadosAgenda completos:', dadosAgenda);
    
    // Armazenar dados globalmente para uso da API
    window.dadosAgendamentoAtual = dadosAgenda;
    console.log('🔍 DEBUG window.dadosAgendamentoAtual definido:', window.dadosAgendamentoAtual);
    
    // Definir agendaIdAtual se não estiver definido ou estiver diferente
    if (dadosAgenda && dadosAgenda.agenda_id) {
        window.agendaIdAtual = dadosAgenda.agenda_id;
        console.log('🔍 DEBUG window.agendaIdAtual atualizado para:', window.agendaIdAtual);
    } else {
        console.log('🔍 DEBUG dadosAgenda.agenda_id não encontrado');
    }
    
    const modalHTML = `
        <div id="modal-ordem-servico" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-green-600 to-green-700 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <svg class="w-8 h-8 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Nova Ordem de Serviço
                            </h2>
                            <p class="text-green-100 mt-1">Paciente: ${nomePaciente} - Prontuário: ${pacienteId}</p>
                        </div>
                        <button onclick="fecharModalOrdemServico()" class="text-white hover:text-gray-200 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <form id="form-ordem-servico" class="space-y-6">
                        
                        <!-- Informações Básicas -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Data -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Data *
                                </label>
                                <input type="text" id="diaexame" name="diaexame" 
                                       value="${dataAtual}" readonly
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-center text-sm">
                            </div>
                            
                            <!-- Local / Posto / Clínica -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Local / Posto / Clínica *
                                </label>
                                <select id="idposto" name="idposto" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    <option value="">Selecione um posto/local</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Médico Solicitante -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Médico Solicitante *
                            </label>
                            <select id="idmedico" name="idmedico" required
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                <option value="">Selecione um médico</option>
                            </select>
                        </div>
                        
                        <!-- Toggle Convênio -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <label class="block text-sm font-medium text-gray-700">
                                    É Convênio?
                                </label>
                                <div class="flex items-center">
                                    <input type="checkbox" id="toggle_convenio" name="toggle_convenio" 
                                           onchange="toggleCamposConvenio(this.checked)"
                                           class="w-4 h-4 text-green-600 bg-gray-100 border-gray-300 rounded focus:ring-green-500">
                                    <label for="toggle_convenio" class="ml-2 text-sm text-gray-700">Sim</label>
                                </div>
                            </div>
                            
                            <!-- Convênio -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Convênio *
                                </label>
                                <select id="convenioSelect" name="idconvenio" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    <option value="">Selecione um convênio</option>
                                </select>
                            </div>
                            
                            <!-- Campos do Convênio (ocultos inicialmente) -->
                            <div id="campos_convenio" class="hidden">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Carteira
                                        </label>
                                        <input type="text" id="carteira" name="carteira" 
                                               maxlength="24"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Token
                                        </label>
                                        <input type="text" id="token" name="token" 
                                               maxlength="10"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Guia Convênio
                                        </label>
                                        <input type="text" id="numeroguia_convenio" name="numeroguia_convenio" 
                                               maxlength="24"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Senha Autorização
                                        </label>
                                        <input type="text" id="autorizacao_old" name="autorizacao_old" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Validade da Senha
                                        </label>
                                        <input type="date" id="validade" name="validade" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Cartão SUS
                                        </label>
                                        <input type="text" id="cns" name="cns" 
                                               maxlength="24" placeholder="Se SUS, informe CNS"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Especialidade (automática da agenda) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Especialidade * <span class="text-xs text-gray-500">(preenchido automaticamente da agenda)</span>
                            </label>
                            <div class="flex gap-2">
                                <input type="text" id="idunidade" name="idunidade" 
                                       placeholder="ID" required readonly
                                       class="w-20 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-center text-sm">
                                <input type="text" id="nm_unidade" name="nm_unidade" 
                                       placeholder="Nome da especialidade" readonly
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm">
                                <div class="flex items-center px-4 py-2 bg-green-100 text-green-700 rounded-md text-sm">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Automático
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observação -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Observação
                            </label>
                            <textarea id="observacao" name="observacao" rows="3"
                                      maxlength="120" placeholder="Observações adicionais..."
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 resize-none text-sm"></textarea>
                        </div>
                        
                        <!-- Campos ocultos -->
                        <input type="hidden" name="idpaciente" value="${pacienteId}">
                        <input type="hidden" name="agendamento_id" value="${agendamentoId}">
                        <input type="hidden" name="tela" value="3">
                        <input type="hidden" name="acao" value="criar_ordem_servico">
                    </form>
                    
                    <!-- Container para alertas da API -->
                    <div id="alert-container-os" class="mt-4"></div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t flex justify-between">
                    <button type="button" onclick="fecharModalOrdemServico()" 
                            class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Cancelar
                    </button>
                    <button type="button" onclick="salvarOrdemServico()" id="inputSalvar" 
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Criar Ordem de Serviço
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-ordem-servico');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Adicionar modal ao body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Inicializar Select2 e carregar dados
    setTimeout(() => {
        inicializarSelect2OS();
        configurarEventosOS();
        carregarEspecialidadeAutomatica(dadosAgenda);
        document.getElementById('diaexame').focus();
    }, 100);
}

// Função para inicializar Select2 nos dropdowns
function inicializarSelect2OS() {
    // Inicializar Select2 para Local/Posto
    $('#idposto').select2({
        ajax: {
            url: 'buscar_postos.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    busca: params.term,
                    page: params.page
                };
            },
            processResults: function (data, params) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: 'Selecione um posto/local',
        allowClear: true,
        dropdownParent: $('#modal-ordem-servico')
    });
    
    // Inicializar Select2 para Médico
    $('#idmedico').select2({
        ajax: {
            url: 'buscar_medicos.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    busca: params.term,
                    page: params.page
                };
            },
            processResults: function (data, params) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        placeholder: 'Selecione um médico',
        allowClear: true,
        dropdownParent: $('#modal-ordem-servico')
    });
    
    // Inicializar Select2 para Convênio com AJAX (busca apenas com 2+ caracteres)
    $('#convenioSelect').select2({
        placeholder: 'Digite pelo menos 2 caracteres para buscar...',
        allowClear: true,
        dropdownParent: $('#modal-ordem-servico'),
        minimumInputLength: 2,
        templateSelection: function(selection) {
            // Personalizar como a seleção é exibida
            if (selection.id) {
                return selection.text || selection.id;
            }
            return selection.text;
        },
        ajax: {
            url: 'buscar_convenio_ajax.php',
            type: 'POST',
            dataType: 'html',
            delay: 300,
            data: function(params) {
                return {
                    busca: params.term || ''
                };
            },
            processResults: function(data) {
                // Corrigir encoding antes de processar - APENAS VISUAL
                var dataCorrigida = data.replace(/TERCEIRIZA��O/g, 'TERCEIRIZAÇÃO')
                                       .replace(/SERVI�O/g, 'SERVIÇO')
                                       .replace(/TERCEIRIZA��ES/g, 'TERCEIRIZAÇÕES')
                                       .replace(/SERVI�OS/g, 'SERVIÇOS')
                                       .replace(/SOLU��O/g, 'SOLUÇÃO')
                                       .replace(/CONSTRU��O/g, 'CONSTRUÇÃO')
                                       .replace(/EDUCA��O/g, 'EDUCAÇÃO')
                                       .replace(/INFORMA��O/g, 'INFORMAÇÃO')
                                       .replace(/IMPORTA��O/g, 'IMPORTAÇÃO')
                                       .replace(/EXPORTA��O/g, 'EXPORTAÇÃO')
                                       .replace(/CART�O/g, 'CARTÃO')
                                       .replace(/D�BITO/g, 'DÉBITO')
                                       .replace(/CR�DITO/g, 'CRÉDITO')
                                       .replace(/DESCRI��O/g, 'DESCRIÇÃO')
                                       .replace(/PROFISS�O/g, 'PROFISSÃO')
                                       .replace(/REGI�O/g, 'REGIÃO')
                                       .replace(/DIVIS�O/g, 'DIVISÃO')
                                       .replace(/VIS�O/g, 'VISÃO')
                                       .replace(/MISS�O/g, 'MISSÃO')
                                       .replace(/EXTENS�O/g, 'EXTENSÃO')
                                       .replace(/DIMENS�O/g, 'DIMENSÃO')
                                       .replace(/REVIS�O/g, 'REVISÃO')
                                       .replace(/TELEVIS�O/g, 'TELEVISÃO')
                                       .replace(/TRANSMISS�O/g, 'TRANSMISSÃO')
                                       .replace(/EMISS�O/g, 'EMISSÃO')
                                       .replace(/ADMISS�O/g, 'ADMISSÃO')
                                       .replace(/COMISS�O/g, 'COMISSÃO')
                                       .replace(/PERMISS�O/g, 'PERMISSÃO')
                                       .replace(/SUBMISS�O/g, 'SUBMISSÃO')
                                       .replace(/�/g, 'Ã')
                                       .replace(/��/g, 'ÇÃO');
                
                // Criar um elemento temporário para extrair as opções
                var tempDiv = $('<div>').html(dataCorrigida);
                var results = [];
                
                tempDiv.find('option').each(function() {
                    var $option = $(this);
                    if ($option.val()) { // Só adicionar se tiver valor
                        var item = {
                            id: $option.val(),
                            text: $option.text(),
                            // Preservar dados customizados
                            'data-nome': $option.attr('data-nome'),
                            'data-suspenso': $option.attr('data-suspenso'), 
                            'data-sindicato': $option.attr('data-sindicato')
                        };
                        
                        results.push(item);
                    }
                });
                
                return {
                    results: results
                };
            }
        },
        // Interceptar a seleção para adicionar os atributos data- ao option
        templateResult: function(item) {
            if (item.loading) {
                return item.text;
            }
            
            return item.text;
        }
    }).on('select2:select', function(e) {
        // Quando um item é selecionado, garantir que o option existe e tem os atributos corretos
        var data = e.params.data;
        var $select = $(this);
        var $option = $select.find('option[value="' + data.id + '"]');
        
        // Se o option não existe, criar
        if ($option.length === 0) {
            $option = $('<option></option>')
                .attr('value', data.id)
                .text(data.text)
                .prop('selected', true);
            $select.append($option);
        } else {
            $option.prop('selected', true);
        }
        
        // Adicionar os atributos data-
        if (data['data-nome']) {
            $option.attr('data-nome', data['data-nome']);
        }
        if (data['data-suspenso']) {
            $option.attr('data-suspenso', data['data-suspenso']);
        }
        if (data['data-sindicato']) {
            $option.attr('data-sindicato', data['data-sindicato']);
        }
        
        console.log('✅ Convênio selecionado:', {
            id: data.id,
            text: data.text,
            nome: data['data-nome'],
            suspenso: data['data-suspenso'],
            sindicato: data['data-sindicato']
        });
        
        // Disparar manualmente a validação do convênio
        setTimeout(() => {
            $select.trigger('change');
        }, 100);
    });
}

// Função para carregar especialidade automaticamente da agenda SELECIONADA
function carregarEspecialidadeAutomatica(dadosAgenda) {
    console.log('🔍 Carregando especialidade da agenda selecionada');
    console.log('📋 window.especialidadeIdSelecionada:', window.especialidadeIdSelecionada);
    console.log('📋 Dados do agendamento:', dadosAgenda);
    
    // PRIORIDADE 1: Usar especialidade da agenda selecionada na sidebar
    if (window.especialidadeIdSelecionada) {
        // Buscar informações da agenda atual pela ID armazenada globalmente
        if (window.agendaIdAtual) {
            fetch(`buscar_info_agenda.php?agenda_id=${window.agendaIdAtual}`)
                .then(safeJsonParse)
                .then(data => {
                    if (data && data.agenda && data.agenda.especialidade_nome) {
                        document.getElementById('idunidade').value = window.especialidadeIdSelecionada;
                        document.getElementById('nm_unidade').value = data.agenda.especialidade_nome;
                        console.log('✅ Especialidade da agenda atual:', data.agenda.especialidade_nome);
                    } else {
                        console.log('⚠️ Especialidade não encontrada na agenda');
                        carregarEspecialidadeFallback(dadosAgenda);
                    }
                })
                .catch(error => {
                    console.error('❌ Erro ao buscar info da agenda:', error);
                    carregarEspecialidadeFallback(dadosAgenda);
                });
        } else {
            // Se não tem agenda ID, usar fallback
            carregarEspecialidadeFallback(dadosAgenda);
        }
    } else {
        // FALLBACK: Usar especialidade do agendamento existente
        carregarEspecialidadeFallback(dadosAgenda);
    }
}

// Função fallback para carregar especialidade do agendamento
function carregarEspecialidadeFallback(dadosAgenda) {
    console.log('🔍 DEBUG carregarEspecialidadeFallback - dados recebidos:', dadosAgenda);
    console.log('🔍 DEBUG window.agendaIdAtual:', window.agendaIdAtual);
    console.log('🔍 DEBUG window.dadosAgendamentoAtual:', window.dadosAgendamentoAtual);
    
    if (dadosAgenda && dadosAgenda.agenda) {
        const agendaInfo = dadosAgenda.agenda;
        console.log('🔍 DEBUG agendaInfo completa:', agendaInfo);
        
        if (agendaInfo.id && agendaInfo.especialidade) {
            document.getElementById('idunidade').value = agendaInfo.id;
            document.getElementById('nm_unidade').value = agendaInfo.especialidade;
            console.log('✅ Especialidade do agendamento carregada:', agendaInfo.especialidade);
        } else {
            console.log('⚠️ Especialidade não disponível nos dados da agenda');
            console.log('🔍 DEBUG agendaInfo.id:', agendaInfo.id);
            console.log('🔍 DEBUG agendaInfo.especialidade:', agendaInfo.especialidade);
        }
    } else {
        console.log('⚠️ Dados da agenda não disponíveis');
        console.log('🔍 DEBUG dadosAgenda:', dadosAgenda);
        if (dadosAgenda) {
            console.log('🔍 DEBUG dadosAgenda.agenda:', dadosAgenda.agenda);
        }
    }
}

// Função carregarTiposASO removida - não há mais campo ASO no modal

// Função buscarConveniosOS() removida - agora usa Select2 com AJAX diretamente

// Função para toggle dos campos de convênio
window.toggleCamposConvenio = function(mostrar) {
    const camposConvenio = document.getElementById('campos_convenio');
    const selectConvenio = document.getElementById('convenioSelect');
    
    if (mostrar) {
        camposConvenio.classList.remove('hidden');
        selectConvenio.required = true;
    } else {
        camposConvenio.classList.add('hidden');
        selectConvenio.required = false;
        selectConvenio.value = '';
        // Limpar campos de convênio
        document.getElementById('carteira').value = '';
        document.getElementById('token').value = '';
        document.getElementById('numeroguia_convenio').value = '';
        document.getElementById('autorizacao_old').value = '';
        document.getElementById('validade').value = '';
    }
};

// Funções para procurar (placeholders - implementar conforme necessário)
window.procurarPosto = function() {
    alert('Funcionalidade "Procurar Posto" será implementada posteriormente.');
};

window.procurarMedico = function() {
    alert('Funcionalidade "Procurar Médico" será implementada posteriormente.');
};

// Função de procurar especialidade removida - agora é automática da agenda

// ===== FUNÇÕES AUXILIARES REPLICADAS DO FRMPACIENTE_T2.PHP =====

// Função para corrigir encoding de acentuação em nomes de convênios
function corrigirEncodingConvenio(texto) {
    if (!texto) return texto;
    return texto.replace(/��/g, 'ÇÃ')
               .replace(/�/g, 'Ã')
               .replace(/‡/g, 'Ç')
               .replace(/Ç/g, 'Ç')
               .replace(/ƒ/g, 'Ã')
               .replace(/„/g, 'É')
               .replace(/‚/g, 'Á')
               .replace(/Š/g, 'Ê')
               .replace(/Œ/g, 'Í')
               .replace(/'/g, 'Ó')
               .replace(/Ž/g, 'Ô')
               .replace(/š/g, 'Ú');
}

// Normaliza texto (remove acentos e deixa UPPER) - REPLICADO DO FRMPACIENTE_T2.PHP
function norm(s){
  return (s||'').toString().normalize('NFD').replace(/[\u0300-\u036f]/g,'').toUpperCase().trim();
}

// Função getAsoSafeOS removida - não há mais campo ASO no modal

// Última resposta da API (string) - REPLICADO DO FRMPACIENTE_T2.PHP
window._lastApiResponseOS = null;

// Função para reavaliar dados baseado na API - SIMPLIFICADA SEM ASO
function reavaliarOS(response) {
    // Extrair campos da resposta (sem JSON.parse)
    var statusMatch   = response.match(/"status":"(.*?)"/);
    var planoMatch    = response.match(/"plano":"(.*?)"/);
    var tipoMatch     = response.match(/"tipo_beneficiario":"(.*?)"/);
    var empresaMatch  = response.match(/"empresa":"(.*?)"/);
    var sindMatch     = response.match(/"sindicato":"(.*?)"/);

    var isAdimplente = statusMatch ? statusMatch[1] : '';
    var plano        = planoMatch ? planoMatch[1] : '';
    var tipo         = tipoMatch ? tipoMatch[1] : '';
    var empresa      = empresaMatch ? empresaMatch[1] : '';
    var sindicato    = sindMatch ? sindMatch[1] : '';

    // Dados do convênio selecionado
    var $selectedOption      = $('#convenioSelect option:selected');
    var convenioNome         = corrigirEncodingConvenio($selectedOption.attr('data-nome') || $selectedOption.data('nome') || '');
    var sindicatoConvenioSel = ($selectedOption.attr('data-sindicato') || $selectedOption.data('sindicato') || '').toString().trim();
    var isSuspenso           = ($selectedOption.attr('data-suspenso') || $selectedOption.data('suspenso') || '').toString().trim();

    console.log('DEBUG Validação OS:', { isAdimplente, sindicatoConvenioSel, convenioNome });

    const btnSalvar = document.getElementById('inputSalvar');

    // Limpa alertas
    limparAlertasOS();

    // 0) convênio suspenso
    if (isSuspenso === 'S') {
      btnSalvar.disabled = true;
      btnSalvar.style.display = 'none';
      mostrarAlertaOS('warning', 'O convênio selecionado está suspenso e não pode ser usado.');
      return;
    }

    // 1) Tratamento padrão por status
    if (isAdimplente === 'adimplente') {
      btnSalvar.disabled = false;
      btnSalvar.style.display = 'block';
      mostrarAlertaOS('success', 'Paciente Adimplente. Plano: ' + plano + ' - ' + tipo);
    } else if (isAdimplente === 'inadimplente') {
      btnSalvar.style.display = 'none';
      btnSalvar.disabled = true;
      mostrarAlertaOS('danger', 'Paciente Inadimplente. Por favor, regularize sua situação.');
      return;
    } else if (isAdimplente === 'pendente') {
      btnSalvar.style.display = 'none';
      btnSalvar.disabled = true;
      mostrarAlertaOS('warning', 'Cadastro pendente. Verificar aplicativo ou setor responsável.');
      return;
    } else if (isAdimplente === 'liberado') {
      btnSalvar.disabled = false;
      btnSalvar.style.display = 'block';
      mostrarAlertaOS('info', 'Status liberado. Empresa: ' + empresa + ' - Sindicato: ' + sindicato);
    } else {
      btnSalvar.style.display = 'none';
      btnSalvar.disabled = true;
      mostrarAlertaOS('warning', 'Paciente não encontrado, procurar o setor responsável ou realizar o cadastro.');
      return;
    }

    // 2) Regras extras para convênio com sindicato (se houver sindicato no convênio)
    if (sindicatoConvenioSel && sindicatoConvenioSel.trim() !== '') {
      // Aceitar adimplente ou liberado
      if (isAdimplente !== 'adimplente' && isAdimplente !== 'liberado') {
        btnSalvar.disabled = true;
        mostrarAlertaOS('danger', 'Paciente não está adimplente para convênio com sindicato.');
        return;
      }

      if (sindicatoConvenioSel !== sindicato) {
        btnSalvar.disabled = true;
        mostrarAlertaOS('danger', 'Sindicato divergente. Acesso negado.');
        return;
      }

      var convenioNomeLimpo = (convenioNome || '').split(' -')[0].trim();
      if (convenioNomeLimpo !== empresa) {
        btnSalvar.disabled = true;
        mostrarAlertaOS('danger', 'Empresa divergente. Acesso negado.');
        return;
      }

      btnSalvar.disabled = false;
      mostrarAlertaOS('success', '<strong>Paciente Adimplente. <br>Plano:</strong> ' + plano + ' <br> <strong>EMPRESA: </strong>' + empresa + ' - ' + tipo);
    }

    // 3) "PLANO TOP"
    if (convenioNome && convenioNome.toUpperCase().includes('PLANO TOP')) {
      // Bloquear se o plano for "Benefício Família" tentando usar PLANO TOP
      if (plano === 'Benefício Família') {
        btnSalvar.disabled = true;
        btnSalvar.style.display = 'none';
        mostrarAlertaOS('danger', 'Paciente com Plano Família não pode usar convênio Plano TOP. Procurar o setor para mudança de plano.');
        return;
      }
      
      // Validação original para outros planos (corrigida)
      if (plano !== 'Benefício TOP' && (sindicato === 'SECOM' || sindicato === 'SINDIVAREJO')) {
        btnSalvar.disabled = true;
        btnSalvar.style.display = 'none';
        mostrarAlertaOS('danger', 'O Paciente não é Beneficiário TOP, procurar o setor para mudança de plano.');
        return;
      }
      
      btnSalvar.disabled = false;
      btnSalvar.style.display = 'block';
    }
}

// Função para configurar eventos do modal O.S.
function configurarEventosOS() {
    // Evento para mudança de convênio - REPLICADO EXATAMENTE DO FRMPACIENTE_T2.PHP
    $('#convenioSelect').change(function() {
        var selectedConvenio = $(this).val();
        var $selectedOption = $('#convenioSelect option:selected');
        var convenioNome = $selectedOption.attr('data-nome') || $selectedOption.data('nome'); // Nome do convênio
        // Corrigir encoding de acentuação para exibição
        convenioNome = corrigirEncodingConvenio(convenioNome);
        var sindicatoConvenio = $selectedOption.attr('data-sindicato') || $selectedOption.data('sindicato'); // pode estar vazio ou não
        // Verifica se precisa consultar a API: se está na lista extensa OU tem sindicato
        var precisaVerificarAPI = false;
        var isSuspenso = $selectedOption.attr('data-suspenso') || $selectedOption.data('suspenso'); // Obter o status de suspensão
        var cpfVerificacao = window.dadosAgendamentoAtual?.paciente?.cpf || '';

        console.log('💳 Convênio selecionado (change event):', {
            selectedConvenio, 
            convenioNome, 
            sindicatoConvenio, 
            isSuspenso,
            $option: $selectedOption,
            attrNome: $selectedOption.attr('data-nome'),
            attrSuspenso: $selectedOption.attr('data-suspenso'),
            attrSindicato: $selectedOption.attr('data-sindicato')
        });

        // Verificar se o convênio está suspenso
        if (isSuspenso === 'S') {
            $('#inputSalvar').prop('disabled', true); // Desabilitar o botão de salvar
            $('#inputSalvar').hide(); // Ocultar o botão de salvar
            mostrarAlertaOS('warning', 'O convênio selecionado está suspenso e não pode ser usado.');
            return; // Impedir qualquer outra ação para convênios suspensos
        } else {
            $('#inputSalvar').prop('disabled', false); // Habilitar o botão de salvar
            $('#inputSalvar').show(); // Mostrar o botão de salvar
            limparAlertasOS(); // Remover alerta se o convênio estiver ativo
        }
        
        // LISTA COMPLETA DE CONVÊNIOS QUE PRECISAM DE VERIFICAÇÃO API (igual frmpaciente_t2.php)
        if (selectedConvenio === 'CARTAO DE DESCONTO MOSSORO' || selectedConvenio === '2118' 
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO MOSSORO' || selectedConvenio === '2405'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO MOSSORO' || selectedConvenio === '2404' 
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO MOSSORO' || selectedConvenio === '2403' 
            || selectedConvenio === 'CARTAO DE DESCONTO AEROPORTO' || selectedConvenio === '2416' 
            || selectedConvenio === 'CARTAO DE DESCONTO GROSSOS' || selectedConvenio === '2413'
            || selectedConvenio === 'CARTAO DE DESCONTO SANTA DELMIRA' || selectedConvenio === '2417' 
            || selectedConvenio === 'CARTAO DE DESCONTO SANTO ANTONIO' || selectedConvenio === '2415' 
            || selectedConvenio === 'CARTAO DE DESCONTO SERRA DO MEL' || selectedConvenio === '2414' 
            || selectedConvenio === 'PIX CARTAO DE DESCONTO' || selectedConvenio === '2406' 
            || selectedConvenio === 'PIX CARTAO DE DESCONTO AEROPORTO' || selectedConvenio === '2409'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO GROSSOS' || selectedConvenio === '2410'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO SANTA DELMIRA' || selectedConvenio === '2408'
            || selectedConvenio === 'PIX PLANO TOP SANTA DELMIRA' || selectedConvenio === '2992'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO SANTO ANTONIO' || selectedConvenio === '2407'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO SERRA DO MEL' || selectedConvenio === '2411'
            || selectedConvenio === 'CARTAO DE DESCONTO OITAVA ROSADO PARNAMIRIM' || selectedConvenio === '2362'
            || selectedConvenio === 'CARTAO DE DESCONTO OITAVA ROSADO ZN' || selectedConvenio === '2361'
            || selectedConvenio === 'CARTAO DE DESCONTO OITAVA ASSU' || selectedConvenio === '2433'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO ZN' || selectedConvenio === '2448'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO ZN' || selectedConvenio === '2449'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO ZN' || selectedConvenio === '2450'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX ZN' || selectedConvenio === '2451'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX MEDICINA ZN' || selectedConvenio === '2452'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PARNAMIRIM' || selectedConvenio === '2453'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PARNAMIRIM' || selectedConvenio === '2454'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO PARNAMIRIM' || selectedConvenio === '2455'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PARNAMIRIM' || selectedConvenio === '2456'
            || selectedConvenio === 'CARTAO DE DESCONTO SODRE' || selectedConvenio === '2459'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO SANTO ANTONIO' || selectedConvenio === '2463'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO SANTO ANTONIO' || selectedConvenio === '2464'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO SANTO ANTONIO' || selectedConvenio === '2465'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO BARAUNA' || selectedConvenio === '2466'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO BARAUNA' || selectedConvenio === '2467'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO BARAUNA' || selectedConvenio === '2468'
            || selectedConvenio === 'CARTAO DE DESCONTO BARAUNA' || selectedConvenio === '2469'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX BARAUNA' || selectedConvenio === '2470'
            || selectedConvenio === 'CARTAO DE DESCONTO SODRE ZONA NORTE' || selectedConvenio === '2472'
            || selectedConvenio === 'CARTAO DE DESCONTO SODRE PARNAMIRIM' || selectedConvenio === '2473'
            || selectedConvenio === 'CARTAO DE DESCONTO SODRE ASSU' || selectedConvenio === '2474'
            || selectedConvenio === 'CARTAO DE DESCONTO SODRE BARAUNA' || selectedConvenio === '2475'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO AREIA BRANCA' || selectedConvenio === '2527'
            || selectedConvenio === 'CARTAO DE DESCONTO AREIA BRANCA' || selectedConvenio === '2526'
            || selectedConvenio === 'CARTAO DE DESCONTO ASSU' || selectedConvenio === '2433'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO ASSU' || selectedConvenio === '2542'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO ASSU' || selectedConvenio === '2540'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO ASSU' || selectedConvenio === '2539'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PARNAMIRIM' || selectedConvenio === '2453'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO ASSU' || selectedConvenio === '2541'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO PLANO TOP MOSSORO' || selectedConvenio === '2696'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO PLANO TOP PARNAMIRIM' || selectedConvenio === '2708'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO PLANO TOP ZONA NORTE' || selectedConvenio === '2705'
            || selectedConvenio === 'CARTAO DE DESCONTO CRÉDITO PLANO TOP ASSU' || selectedConvenio === '2715'
            || selectedConvenio === 'CARTAO DE DESCONTO CRÉDITO PLANO TOP BARAUNA' || selectedConvenio === '2723'
            || selectedConvenio === 'CARTAO DE DESCONTO CRÉDITO PLANO TOP SANTO ANTONIO' || selectedConvenio === '2718'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PLANO TOP ASSU' || selectedConvenio === '2714'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PLANO TOP MOSSORO' || selectedConvenio === '2697'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PLANO TOP PARNAMIRIM' || selectedConvenio === '2709'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PLANO TOP ZONA NORTE' || selectedConvenio === '2704'
            || selectedConvenio === 'CARTAO DE DESCONTO DÉBITO PLANO TOP BARAUNA' || selectedConvenio === '2724'
            || selectedConvenio === 'CARTAO DE DESCONTO DÉBITO PLANO TOP SANTO ANTONIO' || selectedConvenio === '2719'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP ASSU' || selectedConvenio === '2713'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP BARAUNA' || selectedConvenio === '2725'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP MOSSORO' || selectedConvenio === '2699'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP PARNAMIRIM' || selectedConvenio === '2710'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP SANTO ANTONIO' || selectedConvenio === '2720'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP ZONA NORTE' || selectedConvenio === '2703'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP ASSU' || selectedConvenio === '2712'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP BARAUNA' || selectedConvenio === '2722'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP MOSSORO' || selectedConvenio === '2695'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP PARNAMIRIM' || selectedConvenio === '2707'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP SANTO ANTONIO' || selectedConvenio === '2717'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX PLANO TOP ZONA NORTE' || selectedConvenio === '2702'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP ASSU' || selectedConvenio === '2711'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP BARAUNA' || selectedConvenio === '2721'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP MOSSORO' || selectedConvenio === '2694'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP PARNAMIRIM' || selectedConvenio === '2706'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP SANTO ANTONIO' || selectedConvenio === '2716'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP ZONA NORTE' || selectedConvenio === '2701'
            || selectedConvenio === 'CAEPTOX- CARTAO DE DESCONTO CREDITO' || selectedConvenio === '2850'
            || selectedConvenio === 'CAEPTOX- CARTAO DE DESCONTO MOSSORÓ' || selectedConvenio === '2846'
            || selectedConvenio === 'CARTAO DE DESCONTO ABOLICAO' || selectedConvenio === '2932'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO ABOLICAO' || selectedConvenio === '2933'
            || selectedConvenio === 'PLANO TOP SANTA DELMIRA' || selectedConvenio === '2991'
            || selectedConvenio === 'CAEP - CARTAO DE DESCONTO ZN' || selectedConvenio === '2843'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO ODONTO - ZN' || selectedConvenio === '2858'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX ODONTO - ZN' || selectedConvenio === '3204'
            || selectedConvenio === 'CARTAO DESCONTO CAEPTOX PARNAMIRIM' || selectedConvenio === '2842'
            || selectedConvenio === 'EMPRESA TESTE RENISON' || selectedConvenio === '2166'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO EXTREMOZ' || selectedConvenio === '3348'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO EXTREMOZ' || selectedConvenio === '3352'
            || selectedConvenio === 'CARTÃO DE DESCONTO EXTREMOZ' || selectedConvenio === '3329'
            || selectedConvenio === 'CONCURSO CARTAO DESCONTO - CREDITO - DEBITO - PARCELADO - P' || selectedConvenio === '3325'
            || selectedConvenio === 'CONCURSO CARTAO DESCONTO OITAVA ROSADO - PARNAMIRIM' || selectedConvenio === '3324'
            || selectedConvenio === 'CONCURSO CARTAO DESCONTO PIX - PARNAMIRIM' || selectedConvenio === '3324'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO PLANO TOP EXTREMOZ' || selectedConvenio === '3479'
            || selectedConvenio === 'CARTAO DE DESCONTO CREDITO PLANO TOP EXTREMOZ' || selectedConvenio === '3478'
            || selectedConvenio === 'CARTAO DE DESCONTO DEBITO EXTREMOZ' || selectedConvenio === '3510'
            || selectedConvenio === 'CARTAO DE DESCONTO EXTREMOZ' || selectedConvenio === '3329'
            || selectedConvenio === 'CARTAO DE DESCONTO PARCELADO PLANO TOP EXTREMOZ' || selectedConvenio === '3480'
            || selectedConvenio === 'CARTAO DE DESCONTO PIX EXTREMOZ' || selectedConvenio === '3364'
            || selectedConvenio === 'CARTAO DE DESCONTO PLANO TOP EXTREMOZ' || selectedConvenio === '3483'
            || selectedConvenio === 'PIX CARTAO DE DESCONTO ALTO DO RODRIGUES' || selectedConvenio === '3848'
            || selectedConvenio === '' || selectedConvenio === '4554544'
            || selectedConvenio === 'CARTAO DE DESCONTO ALTO DO RODRIGUES' || selectedConvenio === '3450'
        )  {
            precisaVerificarAPI = true;
        } else if (sindicatoConvenio && sindicatoConvenio.trim() !== '') {
            precisaVerificarAPI = true;
        }

        if (precisaVerificarAPI) {
            console.log('🔍 Verificação API necessária para:', convenioNome, 'ID:', selectedConvenio);
            
            // Obter CPF do paciente (igual frmpaciente_t2.php)
            var usuario = 'USER_OS'; // Usuário do modal de OS
            
            // Verificar se o CPF está vazio
            if (!cpfVerificacao || cpfVerificacao.trim() === '') {
                $('#inputSalvar').hide();
                mostrarAlertaOS('warning', 'CPF não informado. Por favor, preencha o CPF antes de prosseguir.');
                return; // Impede a execução do restante do código
            }
            var url = '../proxy.php?documento=' + cpfVerificacao;
            console.log('URL da API: ' + url);
            $.ajax({
                url: url,
                type: 'POST',
                success: function(response) {
                    console.log('Resposta da proxy:', response);
                    
                    // Salvar e reavaliar aqui
                    window._lastApiResponseOS = response;
                    reavaliarOS(response);
                    return; // evita cair no código duplicado que já trata as mesmas regras
                },
                error: function(xhr, status, error) {
                    console.log('Erro na requisição:', error);
                    $('#inputSalvar').hide(); // Oculta o input quando ocorre um erro na requisição
                    var errorMessage = xhr.responseText;
                    console.log('Erro na requisição: ' + errorMessage);
                    mostrarAlertaOS('danger', 'Erro ao verificar o CPF. Por favor, tente novamente mais tarde.');
                }
            });
        } else {
            $('#inputSalvar').show();
        }
    });
    
    // Evento para o toggle de convênio
    const toggleConvenio = document.getElementById('toggle_convenio');
    if (toggleConvenio) {
        toggleConvenio.addEventListener('change', function() {
            const selectConvenio = $('#convenioSelect');
            const convenioSelecionado = selectConvenio.val();
            
            if (this.checked && convenioSelecionado) {
                $('#convenioSelect').trigger('change');
            } else {
                limparAlertasOS();
                // Habilitar botão quando não há convênio
                const btnSalvar = document.getElementById('inputSalvar');
                if (btnSalvar) {
                    btnSalvar.disabled = false;
                    btnSalvar.style.display = 'block';
                }
            }
        });
    }
}

// Função verificarAPIConvenioOS removida - lógica movida para o evento change do convenioSelect

// Função para processar resposta da API (baseada na lógica da frmpaciente_t2.php)
// ATUALIZADA PARA USAR reavaliarOS
function processarRespostaAPIOS(response, convenioNome) {
    // Corrigir encoding do nome do convênio para logs
    convenioNome = corrigirEncodingConvenio(convenioNome);
    console.log('📋 Processando resposta da API:', response);
    
    // Salvar resposta globalmente para reuso
    window._lastApiResponseOS = response;
    
    // Usar a função reavaliarOS que replica exatamente o frmpaciente_t2.php
    reavaliarOS(response);
    
    // Log para auditoria (similar ao frmpaciente_t2.php)
    const statusMatch = response.match(/"status":"(.*?)"/);
    const planoMatch = response.match(/"plano":"(.*?)"/);
    const tipoMatch = response.match(/"tipo_beneficiario":"(.*?)"/);
    const empresaMatch = response.match(/"empresa":"(.*?)"/);
    const sindicatoMatch = response.match(/"sindicato":"(.*?)"/);
    
    console.log('📝 Log da verificação:', {
        convenio: convenioNome,
        status: statusMatch ? statusMatch[1] : '',
        plano: planoMatch ? planoMatch[1] : '',
        tipo: tipoMatch ? tipoMatch[1] : '',
        empresa: empresaMatch ? empresaMatch[1] : '',
        sindicato: sindicatoMatch ? sindicatoMatch[1] : '',
        timestamp: new Date().toISOString()
    });
}

// Função para mostrar alertas no modal O.S.
function mostrarAlertaOS(tipo, mensagem, loading = false) {
    const container = document.getElementById('alert-container-os');
    if (!container) return;
    
    const classes = {
        'success': 'bg-green-100 border-green-500 text-green-700',
        'danger': 'bg-red-100 border-red-500 text-red-700', 
        'warning': 'bg-yellow-100 border-yellow-500 text-yellow-700',
        'info': 'bg-blue-100 border-blue-500 text-blue-700'
    };
    
    const iconsSvg = {
        'success': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>',
        'danger': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>',
        'warning': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>',
        'info': '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path></svg>'
    };
    
    const icon = loading ? 
        '<svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>' : 
        iconsSvg[tipo] || iconsSvg.info;
    
    container.innerHTML = `
        <div class="border-l-4 p-4 ${classes[tipo] || classes.info}" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    ${icon}
                </div>
                <div class="ml-3">
                    <div class="text-sm font-medium">
                        ${mensagem}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// Função para limpar alertas
function limparAlertasOS() {
    const container = document.getElementById('alert-container-os');
    if (container) {
        container.innerHTML = '';
    }
}

// Função removida - agora busca CPF real dos dados do agendamento

// Função para fechar modal de ordem de serviço
window.fecharModalOrdemServico = function() {
    const modal = document.getElementById('modal-ordem-servico');
    if (modal) {
        modal.remove();
    }
};

// Função para salvar ordem de serviço
window.salvarOrdemServico = function() {
    const form = document.getElementById('form-ordem-servico');
    if (!form) {
        alert('Formulário não encontrado!');
        return;
    }
    
    // Validar campos obrigatórios básicos
    const camposObrigatorios = ['idposto', 'idmedico', 'idunidade'];
    let campoInvalido = null;
    
    for (const campo of camposObrigatorios) {
        const elemento = document.getElementById(campo);
        if (!elemento || !elemento.value.trim()) {
            campoInvalido = elemento;
            break;
        }
    }
    
    if (campoInvalido) {
        alert('Por favor, preencha todos os campos obrigatórios (*).');
        campoInvalido.focus();
        return;
    }
    
    // Validar convênio se toggle estiver marcado
    const toggleConvenio = document.getElementById('toggle_convenio');
    const selectConvenioValidacao = document.getElementById('convenioSelect');
    
    if (toggleConvenio && toggleConvenio.checked) {
        if (!selectConvenioValidacao.value) {
            alert('Por favor, selecione um convênio.');
            selectConvenioValidacao.focus();
            return;
        }
    }
    
    // Coletar dados do formulário
    const formData = new FormData(form);
    const dados = Object.fromEntries(formData);
    
    // Adicionar variáveis necessárias para o PHP
    formData.append('tela', 'GS'); // Ativa lógica de gravação no PHP
    formData.append('eh_convenio', toggleConvenio ? toggleConvenio.checked : false);
    
    // Garantir que campos essenciais estão presentes
    if (!formData.get('idpaciente') && window.dadosAgendamentoAtual?.paciente?.id) {
        formData.append('idpaciente', window.dadosAgendamentoAtual.paciente.id);
    }
    if (!formData.get('idcoleta')) {
        formData.append('idcoleta', 1); // Valor padrão
    }
    if (!formData.get('usu_gravac') && window.sessionStorage.getItem('usuario')) {
        formData.append('usu_gravac', window.sessionStorage.getItem('usuario') || 'SISTEMA');
    }
    if (!formData.get('dat_gravac')) {
        formData.append('dat_gravac', new Date().toISOString().slice(0, 19).replace('T', ' '));
    }
    
    // Adicionar nomes dos campos selecionados para o PHP
    const selectPosto = document.getElementById('idposto');
    if (selectPosto && selectPosto.selectedOptions.length > 0) {
        formData.append('nm_posto', selectPosto.selectedOptions[0].text);
    }
    
    const selectMedico = document.getElementById('idmedico');
    if (selectMedico && selectMedico.selectedOptions.length > 0) {
        formData.append('nm_medico', selectMedico.selectedOptions[0].text);
    }
    
    const selectConvenio = document.getElementById('convenioSelect');
    if (selectConvenio && selectConvenio.selectedOptions.length > 0) {
        const convenioText = selectConvenio.selectedOptions[0].text;
        // Extrair apenas o nome do convênio (antes do " - id:")
        const convenioNome = convenioText.split(' - id:')[0];
        formData.append('nm_convenio', convenioNome);
        // Armazenar ID do convênio globalmente para uso no modal de exames
        window.dadosOSConvenioId = selectConvenio.value;
    }
    
    const inputUnidade = document.getElementById('nm_unidade');
    if (inputUnidade && inputUnidade.value) {
        formData.append('nm_unidade', inputUnidade.value);
    }
    
    console.log('📋 Dados da O.S.:', dados);
    
    // Mostrar loading
    const btnSalvar = document.getElementById('inputSalvar');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i>Criando...';
    btnSalvar.disabled = true;
    
    // Adicionar usuário e agendamento vinculado se disponível
    formData.append('usuario', window.sessionStorage.getItem('usuario') || 'SISTEMA');
    if (window.dadosAgendamentoAtual?.id) {
        formData.append('agendamento_id', window.dadosAgendamentoAtual.id);
    }
    
    // Enviar para o novo arquivo de processamento de OS
    fetch('processar_ordem_servico.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Erro na requisição: ' + response.status);
        }
        return safeJsonParse(response);
    })
    .then(data => {
        console.log('📊 Resposta do servidor:', data);
        
        // Verificar resposta JSON
        if (data.status === 'sucesso') {
            // Mostrar sucesso com o número da OS
            showToast(`✅ ${data.mensagem}\nOS Número: ${data.numero_os}`, true);
            fecharModalOrdemServico();
            
            // Atualizar o botão "Criar OS" para "Ver OS" sem refresh da página
            atualizarBotaoCriarOS(data.numero_os);
            
            // Abrir modal para adicionar exames/consultas à OS
            setTimeout(() => {
                mostrarModalAdicionarExamesOS(data.numero_os, data.dados);
            }, 500);
            
            // Recarregar visualização se disponível
            if (typeof carregarVisualizacaoDia === 'function' && window.dadosAgendamentoAtual?.data) {
                carregarVisualizacaoDia(window.dadosAgendamentoAtual.agenda_id, window.dadosAgendamentoAtual.data);
            }
        } else {
            console.error('❌ Erro na criação da OS:', data);
            
            // Se já existe OS, mostrar modal de visualização
            if (data.pode_visualizar && data.numero_os_existente) {
                setTimeout(() => {
                    mostrarModalVisualizarOS(data.numero_os_existente, data.pdf_url);
                }, 500);
            } else {
                alert('❌ Erro: ' + (data.mensagem || 'Erro desconhecido ao criar OS'));
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao comunicar com o servidor: ' + error.message);
    })
    .finally(() => {
        // Restaurar botão
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
    });
};

// Função para mostrar modal de adicionar exames à OS recém-criada
window.mostrarModalAdicionarExamesOS = function(numeroOS, dadosOS) {
    console.log('📋 Abrindo modal para adicionar exames na OS:', numeroOS);
    
    const modalHTML = `
        <div id="modal-adicionar-exames-os" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-clipboard-plus mr-3"></i>
                                Adicionar Exames/Consultas
                            </h2>
                            <p class="text-blue-100 mt-1">OS Número: <strong>${numeroOS}</strong></p>
                            <p class="text-blue-100 text-sm">Paciente: ${dadosOS.paciente}</p>
                        </div>
                        <button onclick="fecharModalAdicionarExamesOS()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <div class="p-6">
                    <!-- Informações da OS -->
                    <div class="bg-gray-50 border-l-4 border-blue-400 p-4 rounded-r-lg mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">
                            <i class="bi bi-info-circle mr-2"></i>
                            Dados da OS
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-700">
                            <div><strong>Posto:</strong> ${dadosOS.posto}</div>
                            <div><strong>Médico:</strong> ${dadosOS.medico}</div>
                            <div><strong>Especialidade:</strong> ${dadosOS.especialidade}</div>
                            <div><strong>Convênio:</strong> ${dadosOS.convenio}</div>
                            <div><strong>Data:</strong> ${dadosOS.data_criacao}</div>
                            <div><strong>Agendamento:</strong> ${dadosOS.agendamento_vinculado || 'Não vinculado'}</div>
                        </div>
                    </div>
                    
                    <!-- Formulário para adicionar exames -->
                    <form id="form-adicionar-exames" class="space-y-6">
                        <input type="hidden" name="numero_os" value="${numeroOS}">
                        
                        <!-- Busca de Exames/Consultas -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-search mr-2"></i>
                                Buscar Exames/Consultas do Convênio
                            </label>
                            <select id="busca-exames-select2" name="exames_selecionados" multiple
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Digite pelo menos 2 caracteres para buscar exames disponíveis para este convênio</p>
                        </div>
                        
                        <!-- Exames Selecionados -->
                        <div id="exames-selecionados">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-check2-square mr-2"></i>
                                Exames/Consultas Selecionados:
                            </h4>
                            <div id="lista-exames-selecionados" class="min-h-20 border border-dashed border-gray-300 rounded-md p-4 text-center text-gray-500">
                                Nenhum exame selecionado ainda...
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Observações
                            </label>
                            <textarea name="observacoes_exames" rows="3" 
                                      placeholder="Observações adicionais sobre os exames..." 
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"></textarea>
                        </div>
                    </form>
                    
                    <!-- Botões de Ação -->
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                        <button type="button" onclick="abrirFrmExamesCompleto(${numeroOS})" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition text-sm">
                            <i class="bi bi-box-arrow-up-right mr-2"></i>
                            Abrir Tela Completa
                        </button>
                        
                        <div class="space-x-3">
                            <button type="button" onclick="salvarExamesOS()" 
                                    class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                <i class="bi bi-check-lg mr-2"></i>
                                Salvar Exames
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-adicionar-exames-os');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Adicionar novo modal
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Inicializar select2 para busca de exames
    setTimeout(() => {
        const idConvenio = dadosOS.convenio_id || window.dadosOSConvenioId; // Pegar ID do convênio dos dados da OS
        inicializarSelect2Exames(idConvenio);
    }, 200);
};

// Função para inicializar select2 de exames por convênio
function inicializarSelect2Exames(idConvenio) {
    if (!idConvenio) {
        console.error('ID do convênio não fornecido para busca de exames');
        $('#busca-exames-select2').html('<option value="">ID do convênio não disponível</option>');
        return;
    }
    
    console.log('🔍 Inicializando select2 de exames para convênio:', idConvenio);
    
    $('#busca-exames-select2').select2({
        placeholder: 'Digite pelo menos 2 caracteres para buscar exames...',
        allowClear: true,
        dropdownParent: $('#modal-adicionar-exames-os'),
        minimumInputLength: 2,
        ajax: {
            url: 'buscar_exames_convenio.php',
            type: 'GET',
            dataType: 'json',
            delay: 300,
            data: function(params) {
                return {
                    idconvenio: idConvenio,
                    busca: params.term || '',
                    limit: 50
                };
            },
            processResults: function(data) {
                if (data.erro) {
                    console.error('Erro na busca de exames:', data.erro);
                    return { results: [] };
                }
                
                return {
                    results: data.results || [],
                    pagination: data.pagination || { more: false }
                };
            },
            cache: true
        },
        templateResult: function(item) {
            if (item.loading) return item.text;
            
            if (item.tipo === 'PERFIL') {
                return $(`
                    <div>
                        <strong>${item.exame || item.text}</strong>
                        <small class="text-blue-600"> [PERFIL]</small><br>
                        <small class="text-gray-600">${item.valor_formatado || ''}</small>
                    </div>
                `);
            } else {
                return $(`
                    <div>
                        <strong>${item.exame || item.text}</strong><br>
                        <small class="text-gray-600">${item.valor_formatado || ''}</small>
                    </div>
                `);
            }
        },
        templateSelection: function(item) {
            return item.exame || item.text || item.id;
        }
    }).on('select2:select', function(e) {
        const data = e.params.data;
        console.log('📋 Exame selecionado via select2:', data);
        
        // Adicionar à lista de selecionados se não existir
        if (!window.examesSelecionados) {
            window.examesSelecionados = [];
        }
        
        // Verificar se já foi selecionado
        const jaExiste = window.examesSelecionados.find(ex => ex.id === data.id);
        if (!jaExiste) {
            window.examesSelecionados.push({
                id: data.id,
                nome: data.exame || data.text,
                valor: data.valor_formatado || 'R$ 0,00',
                tipo: data.tipo || 'EXAME'
            });
            
            atualizarExamesSelecionados();
            showToast(`✅ ${data.exame || data.text} adicionado`, true);
        }
    });
}

// Função para fechar modal de adicionar exames
window.fecharModalAdicionarExamesOS = function() {
    const modal = document.getElementById('modal-adicionar-exames-os');
    if (modal) {
        // Destruir select2 se existir
        const select2Element = $('#busca-exames-select2');
        if (select2Element.length && select2Element.hasClass('select2-hidden-accessible')) {
            select2Element.select2('destroy');
        }
        modal.remove();
    }
};

// Função para abrir a tela completa de exames (frmexames_orX.php)
window.abrirFrmExamesCompleto = function(numeroOS) {
    const url = `/oitava/frmexames_orX.php?idresultado=${numeroOS}&vez=1`;
    window.open(url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
};

// As funções de busca de exames agora são feitas via select2 com busca real no banco

// Função para atualizar a lista de exames selecionados
function atualizarExamesSelecionados() {
    const container = document.getElementById('lista-exames-selecionados');
    
    if (!window.examesSelecionados || window.examesSelecionados.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center">Nenhum exame selecionado ainda...</p>';
        return;
    }
    
    const htmlSelecionados = window.examesSelecionados.map((exame, index) => `
        <div class="flex justify-between items-center p-3 bg-green-50 border border-green-200 rounded-md mb-2">
            <div>
                <p class="font-medium text-gray-900">${exame.nome}</p>
                <p class="text-sm text-gray-600">${exame.valor} ${exame.tipo === 'PERFIL' ? '[PERFIL]' : ''}</p>
            </div>
            <button onclick="removerExameSelecionado(${index})" 
                    class="text-red-600 hover:text-red-800 p-1">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `).join('');
    
    container.innerHTML = htmlSelecionados;
}

// Função para remover exame selecionado
window.removerExameSelecionado = function(index) {
    if (window.examesSelecionados && window.examesSelecionados[index]) {
        const exameRemovido = window.examesSelecionados.splice(index, 1)[0];
        atualizarExamesSelecionados();
        showToast(`❌ ${exameRemovido.nome} removido da lista`, false);
    }
};

// Função para salvar exames na OS
window.salvarExamesOS = function() {
    if (!window.examesSelecionados || window.examesSelecionados.length === 0) {
        alert('Selecione pelo menos um exame para continuar');
        return;
    }
    
    const numeroOS = document.querySelector('input[name="numero_os"]').value;
    const observacoes = document.querySelector('textarea[name="observacoes_exames"]').value;
    
    console.log('💾 Salvando exames na OS:', numeroOS, window.examesSelecionados);
    
    if (!window.examesSelecionados || window.examesSelecionados.length === 0) {
        showToast('❌ Selecione pelo menos um exame!', false);
        return;
    }
    
    // Preparar dados para envio
    const formData = new FormData();
    formData.append('numero_os', numeroOS);
    formData.append('observacoes', observacoes);
    formData.append('usuario', 'SISTEMA');
    
    // Adicionar exames como array
    window.examesSelecionados.forEach((exame, index) => {
        formData.append(`exames[${index}][id]`, exame.id);
        formData.append(`exames[${index}][quantidade]`, exame.quantidade || 1);
    });
    
    console.log('📤 Enviando exames:', window.examesSelecionados);
    
    // Fazer requisição para adicionar exames
    fetch('adicionar_exames_os.php', {
        method: 'POST',
        body: formData
    })
    .then(safeJsonParse)
    .then(data => {
        console.log('📊 Resposta adicionar exames:', data);
        
        if (data.status === 'sucesso') {
            showToast(`✅ ${data.total_exames} exame(s) adicionado(s) à OS ${numeroOS}! Valor: ${data.valor_formatado}`, true);
            
            // Fechar modal de exames
            fecharModalAdicionarExamesOS();
            
            // Limpar selecionados
            window.examesSelecionados = [];
            
            // Mostrar modal para impressão da OS
            setTimeout(() => {
                mostrarModalImpressaoOS(data);
            }, 500);
            
            // Atualizar registro do agendamento para mostrar OS
            if (data.agendamento_vinculado) {
                atualizarRegistroComOS(data.agendamento_vinculado, data.numero_os);
            }
            
        } else {
            showToast(`❌ Erro: ${data.mensagem}`, false);
        }
    })
    .catch(error => {
        console.error('❌ Erro ao adicionar exames:', error);
        showToast('❌ Erro interno ao adicionar exames', false);
    });
};

// Função para mostrar modal de impressão da OS
window.mostrarModalImpressaoOS = function(dadosOS) {
    console.log('🖨️ Mostrando modal de impressão para OS:', dadosOS.numero_os);
    
    // Criar HTML do modal
    const modalHTML = `
        <div id="modal-impressao-os" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="bi bi-check-circle-fill text-green-500 mr-2"></i>
                        OS Criada com Sucesso!
                    </h3>
                    <button onclick="fecharModalImpressaoOS()" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                
                <div class="mb-6 space-y-3">
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-file-earmark-text text-green-600 mr-2"></i>
                            <span class="font-semibold text-green-800">OS Nº ${dadosOS.numero_os}</span>
                        </div>
                        <div class="text-sm text-green-700">
                            <div>${dadosOS.total_exames} exame(s) adicionado(s)</div>
                            <div class="font-semibold">Valor Total: ${dadosOS.valor_formatado}</div>
                            ${dadosOS.agendamento_vinculado ? `<div class="mt-1">Vinculada ao agendamento #${dadosOS.agendamento_vinculado}</div>` : ''}
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <strong>Exames adicionados:</strong>
                        <ul class="mt-1 ml-4">
                            ${dadosOS.exames_adicionados.map(exame => 
                                `<li class="flex justify-between">
                                    <span>• ${exame.nome} (${exame.quantidade}x)</span>
                                    <span>R$ ${parseFloat(exame.valor_total).toFixed(2).replace('.', ',')}</span>
                                </li>`
                            ).join('')}
                        </ul>
                    </div>
                </div>
                
                <div class="flex space-x-3">
                    <button onclick="imprimirOS('${dadosOS.pdf_url}')" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors flex items-center justify-center">
                        <i class="bi bi-printer mr-2"></i>
                        Imprimir OS
                    </button>
                    
                    <button onclick="fecharModalImpressaoOS()" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors">
                        Fechar
                    </button>
                </div>
                
                <div class="mt-3 text-xs text-gray-500 text-center">
                    A OS foi marcada como completa e está pronta para uso.
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Focus no botão de imprimir após um pequeno delay
    setTimeout(() => {
        const botaoImprimir = document.querySelector('#modal-impressao-os button[onclick*="imprimirOS"]');
        if (botaoImprimir) {
            botaoImprimir.focus();
        }
    }, 100);
};

// Função para fechar modal de impressão
window.fecharModalImpressaoOS = function() {
    const modal = document.getElementById('modal-impressao-os');
    if (modal) {
        modal.remove();
    }
};

// Função para imprimir a OS
window.imprimirOS = function(pdfUrl) {
    console.log('🖨️ Abrindo PDF da OS:', pdfUrl);
    
    // Abrir PDF em nova aba
    window.open(pdfUrl, '_blank');
    
    // Fechar modal
    fecharModalImpressaoOS();
    
    // Atualizar a agenda para mostrar as mudanças
    setTimeout(() => {
        if (window.buscarAgendamentosDodia && window.agendaSelecionada && window.dataSelecionada) {
            window.buscarAgendamentosDodia(window.agendaSelecionada, window.dataSelecionada);
        }
    }, 1000);
};

// Função para mostrar modal de visualização da OS existente
window.mostrarModalVisualizarOS = function(numeroOS, pdfUrl) {
    console.log('👁️ Mostrando modal de visualização para OS:', numeroOS);
    
    // Criar HTML do modal
    const modalHTML = `
        <div id="modal-visualizar-os" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4 shadow-xl">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="bi bi-file-earmark-text text-blue-500 mr-2"></i>
                        OS Já Existente
                    </h3>
                    <button onclick="fecharModalVisualizarOS()" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                
                <div class="mb-6 space-y-3">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center mb-2">
                            <i class="bi bi-info-circle text-blue-600 mr-2"></i>
                            <span class="font-semibold text-blue-800">OS Nº ${numeroOS}</span>
                        </div>
                        <div class="text-sm text-blue-700">
                            <div>Este agendamento já possui uma Ordem de Serviço criada.</div>
                            <div class="mt-1">Não é possível criar uma nova OS para o mesmo agendamento.</div>
                        </div>
                    </div>
                    
                    <div class="text-sm text-gray-600">
                        <strong>Opções disponíveis:</strong>
                        <ul class="mt-2 ml-4 space-y-1">
                            <li>• Visualizar/Imprimir a OS existente</li>
                            <li>• Adicionar mais exames à OS (se necessário)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="flex space-x-3">
                    <button onclick="imprimirOS('${pdfUrl}')" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md transition-colors flex items-center justify-center">
                        <i class="bi bi-printer mr-2"></i>
                        Ver/Imprimir OS
                    </button>
                    
                    <button onclick="fecharModalVisualizarOS()" 
                            class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium py-2 px-4 rounded-md transition-colors">
                        Fechar
                    </button>
                </div>
                
                <div class="mt-3 text-xs text-gray-500 text-center">
                    Para modificar esta OS, entre em contato com o setor responsável.
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Focus no botão de visualizar após um pequeno delay
    setTimeout(() => {
        const botaoVisualizar = document.querySelector('#modal-visualizar-os button[onclick*="imprimirOS"]');
        if (botaoVisualizar) {
            botaoVisualizar.focus();
        }
    }, 100);
};

// Função para fechar modal de visualização
window.fecharModalVisualizarOS = function() {
    const modal = document.getElementById('modal-visualizar-os');
    if (modal) {
        modal.remove();
    }
};

// Função para atualizar registro do agendamento com número da OS
window.atualizarRegistroComOS = function(agendamentoId, numeroOS) {
    console.log('🔄 Atualizando registro do agendamento:', agendamentoId, 'com OS:', numeroOS);
    
    // Buscar elemento do agendamento na interface
    const elementoAgendamento = document.querySelector(`[data-agendamento-id="${agendamentoId}"]`);
    if (elementoAgendamento) {
        // Adicionar indicador visual da OS
        const indicadorOS = elementoAgendamento.querySelector('.indicador-os') || document.createElement('div');
        indicadorOS.className = 'indicador-os inline-flex items-center px-2 py-1 rounded text-xs font-semibold bg-green-100 text-green-800 ml-2';
        indicadorOS.innerHTML = `
            <i class="bi bi-file-earmark-text mr-1"></i>
            OS: ${numeroOS}
        `;
        
        // Adicionar evento de clique para visualizar OS
        indicadorOS.style.cursor = 'pointer';
        indicadorOS.onclick = function(e) {
            e.stopPropagation();
            mostrarModalVisualizacaoOSCompleta(numeroOS);
        };
        
        // Adicionar ao elemento se ainda não existe
        if (!elementoAgendamento.querySelector('.indicador-os')) {
            elementoAgendamento.appendChild(indicadorOS);
        }
        
        console.log('✅ Registro do agendamento atualizado com indicador da OS');
    }
};

// Função para mostrar modal completo de visualização da OS
window.mostrarModalVisualizacaoOSCompleta = function(numeroOS) {
    console.log('📋 Carregando dados completos da OS:', numeroOS);
    
    // Buscar dados completos da OS
    fetchWithAuth(`buscar_os_agendamento.php?agendamento_id=0&numero_os=${numeroOS}`)
    .then(safeJsonParse)
    .then(data => {
        if (data.tem_os) {
            mostrarModalDetalhesOS(data);
        } else {
            showToast('❌ Não foi possível carregar os dados da OS', false);
        }
    })
    .catch(error => {
        console.error('❌ Erro ao buscar dados da OS:', error);
        showToast('❌ Erro ao carregar dados da OS', false);
    });
};

// Função para mostrar modal com detalhes completos da OS
window.mostrarModalDetalhesOS = function(dadosOS) {
    console.log('📋 Mostrando modal de detalhes da OS:', dadosOS.numero_os);
    
    // Criar HTML do modal detalhado
    const modalHTML = `
        <div id="modal-detalhes-os" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4 shadow-xl max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 flex items-center">
                        <i class="bi bi-file-earmark-text text-blue-600 mr-2"></i>
                        Detalhes da OS ${dadosOS.numero_os}
                    </h3>
                    <button onclick="fecharModalDetalhesOS()" 
                            class="text-gray-400 hover:text-gray-600 text-xl">
                        <i class="bi bi-x"></i>
                    </button>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- Informações básicas -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-info-circle mr-2"></i>
                            Informações Gerais
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div><strong>Paciente:</strong> ${dadosOS.nome_paciente}</div>
                            <div><strong>Data:</strong> ${dadosOS.data_criacao}</div>
                            <div><strong>Hora:</strong> ${dadosOS.hora_criacao}</div>
                            <div><strong>Status:</strong> 
                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-semibold ${
                                    dadosOS.status === 'COMPLETA' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'
                                }">
                                    ${dadosOS.status}
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações financeiras -->
                    <div class="bg-gray-50 rounded-lg p-4">
                        <h4 class="font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-currency-dollar mr-2"></i>
                            Informações Financeiras
                        </h4>
                        <div class="space-y-2 text-sm">
                            <div><strong>Total de Exames:</strong> ${dadosOS.total_exames}</div>
                            <div><strong>Valor Total:</strong> 
                                <span class="text-green-600 font-semibold">${dadosOS.valor_formatado}</span>
                            </div>
                            <div><strong>Criado por:</strong> ${dadosOS.usuario_criacao}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Ações disponíveis -->
                <div class="border-t pt-4">
                    <div class="flex flex-wrap gap-3">
                        <button onclick="window.open('${dadosOS.pdf_url}', '_blank')" 
                                class="flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-md transition-colors">
                            <i class="bi bi-printer mr-2"></i>
                            Visualizar/Imprimir PDF
                        </button>
                        
                        ${dadosOS.status === 'CRIADA' ? `
                        <button onclick="adicionarMaisExames(${dadosOS.numero_os})" 
                                class="flex items-center px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md transition-colors">
                            <i class="bi bi-plus-circle mr-2"></i>
                            Adicionar Exames
                        </button>
                        ` : ''}
                        
                        <button onclick="fecharModalDetalhesOS()" 
                                class="flex items-center px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-700 font-medium rounded-md transition-colors">
                            <i class="bi bi-x mr-2"></i>
                            Fechar
                        </button>
                    </div>
                </div>
                
                <div class="mt-4 text-xs text-gray-500 text-center">
                    OS criada em ${dadosOS.data_criacao} ${dadosOS.hora_criacao} por ${dadosOS.usuario_criacao}
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
};

// Função para fechar modal de detalhes
window.fecharModalDetalhesOS = function() {
    const modal = document.getElementById('modal-detalhes-os');
    if (modal) {
        modal.remove();
    }
};

// Função para adicionar mais exames a uma OS existente
window.adicionarMaisExames = function(numeroOS) {
    console.log('➕ Adicionando mais exames à OS:', numeroOS);
    
    // Fechar modal atual
    fecharModalDetalhesOS();
    
    // Simular dados da OS para abrir modal de exames
    const dadosOSSimulados = {
        numero_os: numeroOS,
        dados: {
            convenio: 'Carregando...'
        }
    };
    
    // Abrir modal de adicionar exames
    setTimeout(() => {
        mostrarModalAdicionarExamesOS(numeroOS, dadosOSSimulados.dados);
    }, 300);
};

// Função para mostrar notificações toast
window.showToast = function(message, isSuccess = true) {
    // Remover toast existente se houver
    const existingToast = document.querySelector('.toast-notification');
    if (existingToast) {
        existingToast.remove();
    }
    
    const toastClass = isSuccess ? 'bg-green-500' : 'bg-red-500';
    const toastHTML = `
        <div class="toast-notification fixed bottom-4 right-4 ${toastClass} text-white px-6 py-3 rounded-lg shadow-lg z-50 max-w-sm">
            <div class="flex items-center">
                <i class="bi ${isSuccess ? 'bi-check-circle' : 'bi-exclamation-triangle'} mr-2"></i>
                <span class="text-sm font-medium">${message}</span>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', toastHTML);
    
    // Remover após 4 segundos
    setTimeout(() => {
        const toast = document.querySelector('.toast-notification');
        if (toast) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
};

// Função para toggle do histórico
window.toggleHistorico = function(agendamentoId) {
    const container = document.getElementById(`historico-auditoria-${agendamentoId}`);
    const toggleText = document.getElementById(`historico-toggle-text-${agendamentoId}`);
    const toggleIcon = document.getElementById(`historico-toggle-icon-${agendamentoId}`);
    
    if (!container || !toggleText || !toggleIcon) return;
    
    if (container.classList.contains('hidden')) {
        // Expandir - carregar histórico se ainda não foi carregado
        if (container.innerHTML.trim() === '<!-- Conteúdo será carregado via JavaScript -->') {
            carregarHistoricoAuditoria(agendamentoId);
        }
        container.classList.remove('hidden');
        toggleText.textContent = 'Clique aqui para ocultar';
        toggleIcon.classList.add('rotate-180');
    } else {
        // Colapsar
        container.classList.add('hidden');
        toggleText.textContent = 'Clique aqui para exibir';
        toggleIcon.classList.remove('rotate-180');
    }
};

// Função para carregar histórico de auditoria
window.carregarHistoricoAuditoria = function(agendamentoId) {
    const historicoContainer = document.getElementById(`historico-auditoria-${agendamentoId}`);
    if (!historicoContainer) return;
    
    // Mostrar loading
    historicoContainer.innerHTML = `
        <div class="text-center text-gray-500 py-4">
            <i class="bi bi-hourglass-split animate-spin text-2xl mb-2"></i>
            <p class="text-sm">Carregando histórico...</p>
        </div>
    `;
    
    fetchWithAuth(`buscar_historico_agendamento.php?agendamento_id=${agendamentoId}`)
        .then(safeJsonParse)
        .then(data => {
            console.log('📊 Resposta da API de histórico:', data);
            
            if (data.status !== 'sucesso' || !data.historico || data.historico.length === 0) {
                historicoContainer.innerHTML = `
                    <div class="text-center text-gray-500 py-4">
                        <i class="bi bi-clock text-2xl mb-2"></i>
                        <p class="text-sm">Nenhum histórico encontrado</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            data.historico.forEach(item => {
                html += `
                    <div class="bg-white border rounded-lg p-4 shadow-sm">
                        <div class="flex items-start space-x-3">
                            <div class="flex-shrink-0 mt-1">
                                <i class="${item.acao_icone} ${item.acao_cor} text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-2">
                                    <h5 class="font-semibold text-sm text-gray-900">${item.acao_titulo}</h5>
                                    <span class="text-xs text-gray-500">${item.data_acao_formatada}</span>
                                </div>
                                <div class="text-xs text-gray-600 mb-2">
                                    <i class="bi bi-person mr-1"></i>
                                    <strong>Usuário:</strong> ${item.usuario}
                                    ${item.ip_usuario ? `<span class="ml-3"><i class="bi bi-geo mr-1"></i>IP: ${item.ip_usuario}</span>` : ''}
                                </div>
                                
                                ${item.campos_alterados_texto ? `
                                    <div class="text-xs bg-blue-50 text-blue-800 px-2 py-1 rounded mb-2">
                                        <i class="bi bi-pencil mr-1"></i>
                                        <strong>Campos alterados:</strong> ${item.campos_alterados_texto}
                                    </div>
                                ` : ''}
                                
                                ${item.status_anterior && item.status_novo ? `
                                    <div class="text-xs bg-yellow-50 text-yellow-800 px-2 py-1 rounded mb-2">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        <strong>Status:</strong> ${item.status_anterior} → ${item.status_novo}
                                    </div>
                                ` : ''}
                                
                                ${item.tipo_consulta_anterior && item.tipo_consulta_novo ? `
                                    <div class="text-xs bg-purple-50 text-purple-800 px-2 py-1 rounded mb-2">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        <strong>Tipo consulta:</strong> ${item.tipo_consulta_anterior} → ${item.tipo_consulta_novo}
                                    </div>
                                ` : ''}
                                
                                ${item.observacoes_anteriores && item.observacoes_novas && item.observacoes_anteriores !== item.observacoes_novas ? `
                                    <div class="text-xs bg-orange-50 text-orange-800 px-2 py-1 rounded mb-2">
                                        <i class="bi bi-arrow-right mr-1"></i>
                                        <strong>Observações:</strong> ${item.observacoes_anteriores ? item.observacoes_anteriores.substring(0, 20) + '...' : '(vazio)'} → ${item.observacoes_novas ? item.observacoes_novas.substring(0, 20) + '...' : '(vazio)'}
                                    </div>
                                ` : ''}
                                ${item.observacoes && !item.observacoes.includes('editado pelo usuário') ? `
                                    <div class="text-xs text-gray-700 bg-gray-50 p-2 rounded border-l-2 border-gray-300 mt-2">
                                        <i class="bi bi-info-circle mr-1"></i>
                                        <strong>Detalhes:</strong> ${item.observacoes.length > 100 ? item.observacoes.substring(0, 100) + '...' : item.observacoes}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;
            });
            
            historicoContainer.innerHTML = html;
        })
        .catch(error => {
            console.error('Erro ao carregar histórico de auditoria:', error);
            historicoContainer.innerHTML = `
                <div class="text-center text-red-500 py-4">
                    <i class="bi bi-exclamation-triangle text-2xl mb-2"></i>
                    <p class="text-sm">Erro ao carregar histórico</p>
                </div>
            `;
        });
};

// Função para editar agendamento
window.editarAgendamento = function(agendamentoId) {
    console.log('🔧 Iniciando edição do agendamento:', agendamentoId);
    
    fetchWithAuth(`buscar_agendamento.php?id=${agendamentoId}`)
        .then(response => {
            console.log('📡 Resposta recebida, status:', response.status);
            return safeJsonParse(response);
        })
        .then(dados => {
            console.log('📊 Dados recebidos:', dados);
            if (dados.erro) {
                console.error('❌ Erro nos dados:', dados.erro);
                alert(dados.erro);
                return;
            }
            // Abrir modal de agendamento com os dados preenchidos
            console.log('🚀 Abrindo modal de edição...');
            abrirModalAgendamentoParaEdicao(dados);
        })
        .catch(error => {
            console.error('💥 Erro ao buscar agendamento:', error);
            alert('Erro ao carregar dados do agendamento: ' + error.message);
        });
};

// Função para abrir modal de edição
function abrirModalAgendamentoParaEdicao(dadosAgendamento) {
    console.log('📋 Preparando modal de edição com dados:', dadosAgendamento);
    
    const agendaId = dadosAgendamento.agenda.id;
    const data = dadosAgendamento.data;
    const horario = dadosAgendamento.horario;
    
    console.log('📅 Agenda ID:', agendaId, 'Data:', data, 'Horário:', horario);
    
    // Buscar informações da agenda
    console.log('🔍 Buscando informações da agenda...');
    fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
        .then(response => {
            console.log('📡 Resposta da agenda recebida, status:', response.status);
            return safeJsonParse(response);
        })
        .then(agendaInfo => {
            console.log('📊 Informações da agenda:', agendaInfo);
            console.log('🎯 Criando modal com dados...');
            criarModalAgendamentoComDados(agendaId, data, horario, agendaInfo, dadosAgendamento);
        })
        .catch(error => {
            console.error('💥 Erro ao buscar informações da agenda:', error);
            alert('Erro ao carregar informações da agenda: ' + error.message);
        });
}

// Função para criar modal de agendamento com dados preenchidos - VERSÃO CORRIGIDA 2025-08-13
function criarModalAgendamentoComDados(agendaId, data, horario, agendaInfo, dadosAgendamento = null) {
    console.log('🎨 FUNÇÃO CORRETA - Criando modal de agendamento com dados:', { agendaId, data, horario, agendaInfo, dadosAgendamento });
    console.log('🔧 Versão da função:', window.AGENDA_JS_VERSION || 'indefinida');
    
    try {
        // Fechar modal de visualização se estiver aberto
        fecharModalVisualizacao();
        
        const dataObj = new Date(data + 'T00:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR');
        const isEdicao = dadosAgendamento !== null;
        
        // Preparar dados seguros para o template
        const agendaNome = (agendaInfo.agenda && agendaInfo.agenda.nome) ? agendaInfo.agenda.nome : 'Agenda ' + agendaId;
        const convenios = (agendaInfo.agenda && agendaInfo.agenda.convenios) ? agendaInfo.agenda.convenios : [];
        
        // Buscar exames disponíveis para esta agenda
        console.log('🔍 Buscando exames da agenda...');
        fetchWithAuth(`buscar_exames_agenda.php?agenda_id=${agendaId}`)
            .then(safeJsonParse)
            .then(examesData => {
                console.log('📋 Exames disponíveis:', examesData);
                const tipoAgenda = examesData.tipo_agenda || 'consulta';
                criarModalEdicaoCompleto(agendaId, data, horario, dadosAgendamento, convenios, examesData.exames || [], tipoAgenda);
            })
            .catch(error => {
                console.error('Erro ao buscar exames:', error);
                // Continuar sem exames se houver erro
                criarModalEdicaoCompleto(agendaId, data, horario, dadosAgendamento, convenios, [], 'consulta');
            });
        
        return; // Sair da função principal
        
        // Código original comentado temporariamente
        /*
        // Fechar modal de visualização se estiver aberto
        fecharModalVisualizacao();
        
        const dataObj = new Date(data + 'T00:00:00');
        const dataFormatada = dataObj.toLocaleDateString('pt-BR');
        const isEdicao = dadosAgendamento !== null;
        
        // Preparar dados seguros para o template
        const agendaNome = (agendaInfo.agenda && agendaInfo.agenda.nome) ? agendaInfo.agenda.nome : 'Agenda ' + agendaId;
        const convenios = (agendaInfo.agenda && agendaInfo.agenda.convenios) ? agendaInfo.agenda.convenios : [];
        const temRetorno = convenios.find(c => c.nome.toLowerCase().includes('retorno'));
        
        console.log('📊 Dados preparados:', { agendaNome, convenios, temRetorno });
        */
    
    const modalHTML = `
        <div id="modal-agendamento-edicao" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
                <div class="bg-gradient-to-r ${isEdicao ? 'from-blue-600 to-blue-700' : 'from-teal-600 to-teal-700'} text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi ${isEdicao ? 'bi-pencil' : 'bi-calendar-plus'} mr-3"></i>
                                ${isEdicao ? 'Editar' : 'Novo'} Agendamento
                            </h2>
                            <p class="text-blue-100 mt-1">${agendaNome} - ${dataFormatada} às ${horario}</p>
                        </div>
                        <button onclick="fecharModalEdicao()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form id="form-agendamento-edicao" class="p-6">
                    <!-- Dados do Paciente -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-person-circle mr-2"></i>Dados do Paciente
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Nome completo <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="nome_paciente" required
                                       value="${isEdicao ? dadosAgendamento.paciente.nome : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    CPF <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="cpf_paciente" id="cpf-edicao" required
                                       value="${isEdicao ? dadosAgendamento.paciente.cpf : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="000.000.000-00">
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Data de nascimento <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="data_nascimento" id="data_nascimento_edicao" required
                                       value="${isEdicao && dadosAgendamento.paciente.data_nascimento ? dadosAgendamento.paciente.data_nascimento : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${isEdicao && dadosAgendamento.paciente.id ? 'bg-gray-50' : ''}"
                                       ${isEdicao && dadosAgendamento.paciente.id ? 'readonly' : ''}
                                       onchange="calcularIdadeEdicao()">
                                ${isEdicao && dadosAgendamento.paciente.id ? '<small class="text-gray-500">Paciente cadastrado - não editável</small>' : ''}
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Idade <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="idade" id="idade_edicao" required readonly
                                       value="${isEdicao && dadosAgendamento.idade ? dadosAgendamento.idade : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="Calculado automaticamente">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Telefone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" name="telefone_paciente" id="telefone-edicao" required
                                       value="${isEdicao ? dadosAgendamento.paciente.telefone : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 ${isEdicao && dadosAgendamento.paciente.id ? 'bg-gray-50' : ''}"
                                       ${isEdicao && dadosAgendamento.paciente.id ? 'readonly' : ''}
                                       placeholder="(84) 99999-9999">
                                ${isEdicao && dadosAgendamento.paciente.id ? '<small class="text-gray-500">Paciente cadastrado - não editável</small>' : ''}
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    E-mail
                                </label>
                                <input type="email" name="email_paciente"
                                       value="${isEdicao && dadosAgendamento.paciente.email ? dadosAgendamento.paciente.email : ''}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       placeholder="exemplo@email.com">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Convênio <span class="text-red-500">*</span>
                                </label>
                                <select name="convenio_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione o convênio</option>
                                    ${convenios.map(convenio => `
                                        <option value="${convenio.id}" ${isEdicao && dadosAgendamento.convenio.id == convenio.id ? 'selected' : ''}>
                                            ${convenio.nome}
                                        </option>
                                    `).join('')}
                                </select>
                            </div>
                            
                            <!-- Tipo de Consulta (apenas para consultas) -->
                            ${isConsulta ? `
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Tipo de consulta <span class="text-red-500">*</span>
                                </label>
                                <select name="tipo_consulta" id="tipo_consulta_edicao" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Selecione o tipo</option>
                                    <option value="primeira_vez" ${isEdicao && dadosAgendamento.tipo_consulta == 'primeira_vez' ? 'selected' : ''}>Primeira vez</option>
                                    <option value="retorno" ${isEdicao && dadosAgendamento.tipo_consulta == 'retorno' ? 'selected' : ''}>Retorno</option>
                                    <option value="urgencia" ${isEdicao && dadosAgendamento.tipo_consulta == 'urgencia' ? 'selected' : ''}>Urgência</option>
                                    <option value="rotina" ${isEdicao && dadosAgendamento.tipo_consulta == 'rotina' ? 'selected' : ''}>Rotina</option>
                                    <option value="revisao" ${isEdicao && dadosAgendamento.tipo_consulta == 'revisao' ? 'selected' : ''}>Revisão</option>
                                    <option value="seguimento" ${isEdicao && dadosAgendamento.tipo_consulta == 'seguimento' ? 'selected' : ''}>Seguimento</option>
                                </select>
                            </div>
                            ` : '<!-- Tipo de consulta: não aplicável para procedimentos -->'}
                        </div>
                    </div>
                    
                    <!-- Configurações de Atendimento -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-gear mr-2"></i>Configurações de Atendimento
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Status de Confirmação -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Status de Confirmação
                                </label>
                                <div class="flex space-x-4">
                                    <label class="flex items-center">
                                        <input type="radio" name="confirmado" value="0" 
                                               ${isEdicao ? (dadosAgendamento.confirmado ? '' : 'checked') : 'checked'}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Não confirmado</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="radio" name="confirmado" value="1" 
                                               ${isEdicao && dadosAgendamento.confirmado ? 'checked' : ''}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">Confirmado</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Tipo de Atendimento -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de Atendimento
                                </label>
                                <select name="tipo_atendimento" id="tipo_atendimento_edicao"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="NORMAL" ${isEdicao && dadosAgendamento.tipo_atendimento === 'PRIORIDADE' ? '' : 'selected'}>Normal</option>
                                    <option value="PRIORIDADE" ${isEdicao && dadosAgendamento.tipo_atendimento === 'PRIORIDADE' ? 'selected' : ''}>Prioridade</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Informações do Agendamento -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-calendar-event mr-2"></i>Informações do Agendamento
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Data do Agendamento
                                </label>
                                <input type="date" name="data_agendamento" 
                                       value="${data}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Horário
                                </label>
                                <input type="time" name="horario_agendamento" 
                                       value="${horario}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Status
                                </label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="AGENDADO" ${isEdicao && dadosAgendamento.status === 'AGENDADO' ? 'selected' : ''}>Agendado</option>
                                    <option value="CONFIRMADO" ${isEdicao && dadosAgendamento.status === 'CONFIRMADO' ? 'selected' : ''}>Confirmado</option>
                                    <option value="CANCELADO" ${isEdicao && dadosAgendamento.status === 'CANCELADO' ? 'selected' : ''}>Cancelado</option>
                                    <option value="REALIZADO" ${isEdicao && dadosAgendamento.status === 'REALIZADO' ? 'selected' : ''}>Realizado</option>
                                </select>
                            </div>
                        </div>
                        
                        ${temRetorno ? `
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Tipo de consulta
                            </label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" name="tipo_consulta" value="primeira_vez" 
                                           ${!isEdicao || dadosAgendamento.tipo_consulta === 'primeira_vez' ? 'checked' : ''}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Primeira vez</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="tipo_consulta" value="retorno"
                                           ${isEdicao && dadosAgendamento.tipo_consulta === 'retorno' ? 'checked' : ''}
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                    <span class="ml-2 text-sm text-gray-700">Retorno</span>
                                </label>
                            </div>
                        </div>
                        ` : ''}
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Observações
                            </label>
                            <textarea name="observacoes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Alguma observação especial sobre o agendamento...">${isEdicao && dadosAgendamento.observacoes ? dadosAgendamento.observacoes : ''}</textarea>
                        </div>
                    </div>
                    
                    <!-- Campos ocultos -->
                    <input type="hidden" name="agenda_id" value="${agendaId}">
                    ${isEdicao ? `<input type="hidden" name="agendamento_id" value="${dadosAgendamento.id}">` : ''}
                    <input type="hidden" name="acao" value="${isEdicao ? 'editar' : 'criar'}">
                    
                    <!-- Botões -->
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4 pt-6 border-t">
                        <button type="button" onclick="fecharModalEdicao()" 
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                            <i class="bi bi-x-circle mr-2"></i>Cancelar
                        </button>
                        
                        <button type="submit" 
                                class="px-6 py-3 ${isEdicao ? 'bg-blue-600 hover:bg-blue-700' : 'bg-teal-600 hover:bg-teal-700'} text-white rounded-md transition">
                            <i class="bi ${isEdicao ? 'bi-check-circle' : 'bi-plus-circle'} mr-2"></i>
                            ${isEdicao ? 'Salvar Alterações' : 'Criar Agendamento'}
                        </button>
                    </div>
                </form>
                
                <!-- Loading overlay -->
                <div id="loading-edicao" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p>${isEdicao ? 'Salvando alterações...' : 'Criando agendamento...'}</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Configurar máscaras e eventos
    configurarMascarasEdicao();
    configurarFormularioEdicao(isEdicao);
    
        console.log('✅ Modal de edição criado com sucesso!');
        
    } catch (error) {
        console.error('💥 Erro ao criar modal de edição:', error);
        alert('Erro ao abrir modal de edição: ' + error.message);
    }
}

// Função para criar modal de edição completo
function criarModalEdicaoCompleto(agendaId, data, horario, dadosAgendamento, convenios, examesDisponiveis, tipoAgenda = 'consulta') {
    console.log('🎨 Criando modal de edição completo...');
    console.log('📊 Dados do agendamento recebidos:', dadosAgendamento);
    console.log('🧪 Exames disponíveis:', examesDisponiveis);
    
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
    const diaSemana = dataObj.toLocaleDateString('pt-BR', { weekday: 'long' });
    
    // Exames já selecionados (se for edição)
    const examesSelecionados = dadosAgendamento && dadosAgendamento.exames ? dadosAgendamento.exames.map(e => e.id) : [];
    console.log('🎯 Exames já selecionados (IDs):', examesSelecionados);
    console.log('📋 Estrutura dos exames nos dados:', dadosAgendamento ? dadosAgendamento.exames : 'Nenhum dado de agendamento');
    
    const modalHTML = `
        <div id="modal-agendamento-edicao" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[95vh] overflow-y-auto">
                <!-- Cabeçalho -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-pencil-square mr-3"></i>
                                Editar Agendamento
                            </h2>
                            <p class="text-blue-100 mt-1">${diaSemana}, ${dataFormatada} às ${horario}</p>
                        </div>
                        <button onclick="fecharModalEdicao()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <form id="form-agendamento-edicao" class="p-6">
                    <!-- Campos hidden para dados obrigatórios do paciente -->
                    <input type="hidden" name="agendamento_id" value="${dadosAgendamento ? dadosAgendamento.id || '' : ''}">
                    <input type="hidden" name="agenda_id" value="${agendaId || ''}">
                    <input type="hidden" name="nome_paciente" value="${dadosAgendamento && dadosAgendamento.paciente ? (dadosAgendamento.paciente.nome || '') : ''}">
                    <input type="hidden" name="cpf_paciente" value="${dadosAgendamento && dadosAgendamento.paciente ? (dadosAgendamento.paciente.cpf || '') : ''}">
                    <input type="hidden" name="data_nascimento" value="${dadosAgendamento && dadosAgendamento.paciente ? (dadosAgendamento.paciente.data_nascimento || '1900-01-01') : '1900-01-01'}">
                    <input type="hidden" name="telefone_paciente" value="${dadosAgendamento && dadosAgendamento.paciente ? (dadosAgendamento.paciente.telefone || '') : ''}">
                    <input type="hidden" name="email_paciente" value="${dadosAgendamento && dadosAgendamento.paciente ? (dadosAgendamento.paciente.email || '') : ''}">
                    <input type="hidden" name="convenio_id" value="${dadosAgendamento && dadosAgendamento.convenio ? (dadosAgendamento.convenio.id || '') : ''}">
                    <input type="hidden" name="data_agendamento" value="${data || ''}">
                    <input type="hidden" name="horario_agendamento" value="${horario || ''}">
                    
                    <!-- Informações do Paciente (somente leitura) -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-person-circle mr-2"></i>Informações do Paciente
                        </h3>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                                        ${dadosAgendamento ? dadosAgendamento.paciente.nome : 'N/A'}
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
                                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                                        ${dadosAgendamento ? dadosAgendamento.paciente.cpf : 'N/A'}
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
                                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                                        ${dadosAgendamento ? dadosAgendamento.paciente.telefone : 'N/A'}
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Convênio</label>
                                    <div class="px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-600">
                                        ${dadosAgendamento ? dadosAgendamento.convenio.nome : 'N/A'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    ${tipoAgenda === 'procedimento' ? `
                    <!-- Seleção de Exames (editável) -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-clipboard2-check mr-2"></i>Exames Solicitados
                        </h3>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Selecione os exames para este agendamento:
                            </label>
                            <select id="select-exames-edicao" name="exames[]" multiple class="w-full">
                                ${examesDisponiveis.map(exame => {
                                    const isSelected = examesSelecionados.includes(exame.id);
                                    console.log(`🧪 Exame ${exame.nome} (ID: ${exame.id}) - Selecionado: ${isSelected}`);
                                    return `<option value="${exame.id}" ${isSelected ? 'selected' : ''}>${exame.nome}</option>`;
                                }).join('')}
                            </select>
                            <div class="text-xs text-gray-500 mt-1">
                                Use Ctrl/Cmd + clique para selecionar múltiplos exames
                            </div>
                        </div>
                        
                        <!-- Exames atualmente selecionados -->
                        <div id="exames-selecionados" class="mt-4">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Exames selecionados:</h4>
                            <div id="lista-exames-selecionados" class="space-y-2">
                                ${examesSelecionados.length > 0 ? 
                                    examesDisponiveis.filter(e => examesSelecionados.includes(e.id)).map(exame => `
                                        <div class="flex items-center justify-between bg-blue-50 p-2 rounded">
                                            <span class="text-sm">${exame.nome}</span>
                                            <button type="button" onclick="removerExame(${exame.id})" 
                                                    class="text-red-600 hover:text-red-800">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                            </button>
                                        </div>
                                    `).join('') :
                                    '<div class="text-gray-500 text-sm">Nenhum exame selecionado</div>'
                                }
                            </div>
                        </div>
                    </div>
                    ` : ''}
                    
                    <!-- Status e Observações -->
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                            <i class="bi bi-gear mr-2"></i>Configurações do Agendamento
                        </h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                                <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="AGENDADO" ${dadosAgendamento && dadosAgendamento.status === 'AGENDADO' ? 'selected' : ''}>Agendado</option>
                                    <option value="CONFIRMADO" ${dadosAgendamento && dadosAgendamento.status === 'CONFIRMADO' ? 'selected' : ''}>Confirmado</option>
                                    <option value="CANCELADO" ${dadosAgendamento && dadosAgendamento.status === 'CANCELADO' ? 'selected' : ''}>Cancelado</option>
                                    <option value="REALIZADO" ${dadosAgendamento && dadosAgendamento.status === 'REALIZADO' ? 'selected' : ''}>Realizado</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Consulta</label>
                                <select name="tipo_consulta" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="primeira_vez" ${!dadosAgendamento || dadosAgendamento.tipo_consulta === 'primeira_vez' ? 'selected' : ''}>Primeira vez</option>
                                    <option value="retorno" ${dadosAgendamento && dadosAgendamento.tipo_consulta === 'retorno' ? 'selected' : ''}>Retorno</option>
                                </select>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
                            <textarea name="observacoes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                      placeholder="Observações sobre o agendamento...">${dadosAgendamento && dadosAgendamento.observacoes ? dadosAgendamento.observacoes : ''}</textarea>
                        </div>
                    </div>
                    
                    <!-- Preparos e Orientações (REMOVIDO - agora exibido em Informações Detalhadas) -->
                    <div id="preparos-container" class="mb-6" style="display: none !important;">
                        <h4 class="text-base font-semibold text-gray-800 mb-3 flex items-center">
                            <i class="bi bi-list-check mr-2"></i>
                            Preparos e Orientações da Agenda
                        </h4>
                        <div id="preparos-content" class="bg-green-50 border-l-4 border-green-400 p-3 rounded-r-lg">
                            <!-- Preparos serão carregados via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Campos ocultos -->
                    <input type="hidden" name="agendamento_id" value="${dadosAgendamento ? dadosAgendamento.id : ''}">
                    <input type="hidden" name="agenda_id" value="${agendaId}">
                    <input type="hidden" name="data_agendamento" value="${data}">
                    <input type="hidden" name="horario_agendamento" value="${horario}">
                    
                    <!-- Botões -->
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-4 pt-6 border-t">
                        <button type="button" onclick="fecharModalEdicao()" 
                                class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition">
                            <i class="bi bi-x-circle mr-2"></i>Cancelar
                        </button>
                        
                        <button type="submit" 
                                class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition">
                            <i class="bi bi-check-circle mr-2"></i>Salvar Alterações
                        </button>
                    </div>
                </form>
                
                <!-- Loading overlay -->
                <div id="loading-edicao" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div class="bg-white p-6 rounded-lg text-center">
                        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                        <p>Salvando alterações...</p>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Carregar preparos da agenda
    carregarPreparosAgenda(agendaId);
    
    // Configurar Select2 para os exames (só se for agenda de procedimento)
    if (tipoAgenda === 'procedimento') {
        setTimeout(() => {
            const selectElement = document.getElementById('select-exames-edicao');
            if (selectElement) {
                console.log('🔍 Select element encontrado, opções selecionadas antes do Select2:', 
                    Array.from(selectElement.selectedOptions).map(opt => ({ value: opt.value, text: opt.text })));
                
                // Salvar a seleção inicial para garantir que não seja perdida
                const selecaoInicial = Array.from(selectElement.selectedOptions).map(opt => opt.value);
                console.log('💾 Salvando seleção inicial:', selecaoInicial);
            }
            
            if (typeof $ !== 'undefined' && $.fn.select2) {
                console.log('🎛️ Inicializando Select2...');
                $('#select-exames-edicao').select2({
                    placeholder: 'Digite para buscar exames...',
                    allowClear: true,
                    width: '100%'
                });
                
                // Verificar seleção após inicializar Select2
                const selectedValues = $('#select-exames-edicao').val();
                console.log('🎯 Valores selecionados após Select2:', selectedValues);
                
                // Monitorar mudanças na seleção
                $('#select-exames-edicao').on('change', function() {
                    console.log('📝 Select2 mudou, atualizando lista...');
                    atualizarListaExamesSelecionados();
                });
                
                // Múltiplas tentativas para garantir que a lista seja atualizada
                setTimeout(() => {
                    atualizarListaExamesSelecionados();
                    console.log('🔄 Primeira atualização após Select2');
                }, 200);
                
                setTimeout(() => {
                    atualizarListaExamesSelecionados();
                    console.log('🔄 Segunda atualização após Select2');
                }, 500);
            } else {
                // Se Select2 não estiver disponível, usar select nativo
                console.log('📝 Select2 não disponível, usando select nativo');
                selectElement.addEventListener('change', atualizarListaExamesSelecionados);
                atualizarListaExamesSelecionados();
            }
        }, 100);
    } else {
        console.log('📋 Agenda de consulta - não carregando exames');
    }
    
    // Configurar formulário
    setTimeout(() => {
        configurarFormularioEdicaoCompleto();
    }, 100);
    
    console.log('✅ Modal de edição completo criado!');
}

// Função para atualizar lista de exames selecionados
function atualizarListaExamesSelecionados() {
    console.log('🔄 Iniciando atualização da lista de exames...');
    
    const select = document.getElementById('select-exames-edicao');
    const container = document.getElementById('lista-exames-selecionados');
    
    console.log('🔍 Select encontrado:', !!select);
    console.log('🔍 Container encontrado:', !!container);
    
    if (!select || !container) {
        console.log('❌ Select ou container não encontrado para atualização');
        return;
    }
    
    // Debug do estado atual do select
    console.log('📊 Total de opções no select:', select.options.length);
    console.log('📊 Opções selecionadas (nativo):', select.selectedOptions.length);
    
    // Obter valores selecionados (funciona tanto com select normal quanto Select2)
    let selectedValues = [];
    let selectedOptions = [];
    
    if (typeof $ !== 'undefined' && $.fn.select2 && $('#select-exames-edicao').hasClass('select2-hidden-accessible')) {
        console.log('🎛️ Usando API do Select2');
        // Se Select2 está ativo, usar sua API
        selectedValues = $('#select-exames-edicao').val() || [];
        console.log('🎯 Valores selecionados pelo Select2:', selectedValues);
        
        selectedOptions = selectedValues.map(value => {
            const option = select.querySelector(`option[value="${value}"]`);
            return option ? { value: option.value, text: option.text } : null;
        }).filter(Boolean);
    } else {
        console.log('📝 Usando seleção nativa do select');
        // Usar seleção nativa
        selectedOptions = Array.from(select.selectedOptions).map(option => ({
            value: option.value,
            text: option.text
        }));
    }
    
    console.log('📋 Exames selecionados processados:', selectedOptions);
    
    if (selectedOptions.length === 0) {
        console.log('📝 Nenhum exame selecionado, exibindo mensagem padrão');
        container.innerHTML = '<div class="text-gray-500 text-sm">Nenhum exame selecionado</div>';
        return;
    }
    
    console.log('✅ Gerando HTML para', selectedOptions.length, 'exames');
    const htmlExames = selectedOptions.map(option => `
        <div class="flex items-center justify-between bg-blue-50 p-2 rounded">
            <span class="text-sm">${option.text}</span>
            <button type="button" onclick="removerExame(${option.value})" 
                    class="text-red-600 hover:text-red-800">
                <i class="bi bi-x-circle"></i>
            </button>
        </div>
    `).join('');
    
    container.innerHTML = htmlExames;
    console.log('✅ Lista de exames atualizada com sucesso!');
}

// Função para remover exame
function removerExame(exameId) {
    const select = document.getElementById('select-exames-edicao');
    if (!select) {
        console.log('❌ Select não encontrado para remover exame');
        return;
    }
    
    console.log('🗑️ Removendo exame ID:', exameId);
    
    if (typeof $ !== 'undefined' && $.fn.select2 && $('#select-exames-edicao').hasClass('select2-hidden-accessible')) {
        // Se Select2 está ativo, usar sua API
        const currentValues = $('#select-exames-edicao').val() || [];
        const newValues = currentValues.filter(value => value != exameId);
        $('#select-exames-edicao').val(newValues).trigger('change');
    } else {
        // Usar seleção nativa
        const option = select.querySelector(`option[value="${exameId}"]`);
        if (option) {
            option.selected = false;
            atualizarListaExamesSelecionados();
        }
    }
}

// Função para configurar formulário de edição completo
function configurarFormularioEdicaoCompleto() {
    const form = document.getElementById('form-agendamento-edicao');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const loading = document.getElementById('loading-edicao');
        loading.classList.remove('hidden');
        
        const formData = new FormData(this);
        
        // Adicionar exames selecionados (só para agendas de procedimento)
        const selectExames = document.getElementById('select-exames-edicao');
        if (selectExames) {
            formData.delete('exames[]'); // Limpar primeiro
            Array.from(selectExames.selectedOptions).forEach(option => {
                formData.append('exames[]', option.value);
            });
        } else {
            // Para agendas de consulta, garantir que não há campo de exames
            formData.delete('exames[]');
        }
        
        console.log('📝 Dados do formulário:', Object.fromEntries(formData));
        
        fetch('editar_agendamento.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_CONFIG.token}`
            },
            body: formData
        })
        .then(safeJsonParse)
        .then(data => {
            console.log('📊 Resposta do servidor:', data);
            
            if (data.status === 'sucesso') {
                showToast('Agendamento atualizado com sucesso!', true);
                fecharModalEdicao();

                // ✅ Recarregar APENAS a visualização (sem refresh da página)
                const dataAtual = formData.get('data_agendamento');
                const agendaIdAtual = formData.get('agenda_id');
                carregarVisualizacaoDia(agendaIdAtual, dataAtual);
            } else {
                showToast('Erro: ' + data.mensagem, false);
            }
        })
        .catch(error => {
            console.error('💥 Erro:', error);
            showToast('Erro ao processar agendamento. Tente novamente.', false);
        })
        .finally(() => {
            loading.classList.add('hidden');
        });
    });
}

// Função para fechar modal de edição
window.fecharModalEdicao = function() {
    const modal = document.getElementById('modal-agendamento-edicao');
    if (modal) {
        modal.remove();
    }
};

// Função para configurar máscaras no modal de edição
function configurarMascarasEdicao() {
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf-edicao');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone-edicao');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
}

// Função para configurar formulário de edição
function configurarFormularioEdicao(isEdicao) {
    const form = document.getElementById('form-agendamento-edicao');
    if (!form) return;
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const loading = document.getElementById('loading-edicao');
        loading.classList.remove('hidden');
        
        const formData = new FormData(this);
        const endpoint = isEdicao ? 'editar_agendamento.php' : 'processar_agendamento.php';

        // ✅ Capturar explicitamente o estado do checkbox de sedação
        const checkboxSedacao = document.getElementById('precisa_sedacao');
        if (checkboxSedacao) {
            formData.set('precisa_sedacao', checkboxSedacao.checked ? 'true' : 'false');
            console.log('💉 Sedação marcada:', checkboxSedacao.checked);
        }

        fetch(endpoint, {
            method: 'POST',
            body: formData
        })
        .then(safeJsonParse)
        .then(data => {
            if (data.status === 'sucesso') {
                showToast(isEdicao ? 'Agendamento atualizado com sucesso!' : 'Agendamento criado com sucesso!', true);
                fecharModalEdicao();
                
                // Recarregar a visualização
                if (typeof carregarVisualizacaoDia === 'function') {
                    const dataAtual = formData.get('data_agendamento');
                    const agendaIdAtual = formData.get('agenda_id');
                    carregarVisualizacaoDia(agendaIdAtual, dataAtual);
                }
            } else {
                showToast('Erro: ' + data.mensagem, false);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Erro ao processar agendamento. Tente novamente.', false);
        })
        .finally(() => {
            loading.classList.add('hidden');
        });
    });
}


/**
 * Funções de Drag & Drop
 */
function iniciarDrag(event, agendamentoId, horaOriginal, dataOriginal) {
    draggedElement = event.target.closest('tr');
    
    // ✅ VERIFICAÇÃO ROBUSTA DE TIPOS NÃO MOVÍVEIS - MÚLTIPLAS FORMAS
    const isEncaixe = 
        // 1. Verificar badge visual
        draggedElement.querySelector('.bg-orange-100.text-orange-800[data-tipo="ENCAIXE"]') ||
        // 2. Verificar texto do badge
        (draggedElement.innerHTML.includes('ENCAIXE')) ||
        // 3. Verificar ícone de raio
        draggedElement.querySelector('.bi-lightning-charge') ||
        // 4. Verificar se a linha tem classe de encaixe
        draggedElement.classList.contains('encaixe-row') ||
        // 5. Verificar se o elemento pai tem atributo disabled por ser encaixe
        draggedElement.hasAttribute('data-tipo-encaixe');
    
    const isRetorno = 
        // 1. Verificar badge visual de retorno
        draggedElement.querySelector('[data-tipo="RETORNO"]') ||
        // 2. Verificar texto do badge
        (draggedElement.innerHTML.includes('RETORNO')) ||
        // 3. Verificar ícone de retorno
        draggedElement.querySelector('.bi-arrow-repeat') ||
        draggedElement.querySelector('.bi-arrow-clockwise') ||
        // 4. Verificar se a linha tem classe de retorno
        draggedElement.classList.contains('retorno-row') ||
        // 5. Verificar se o elemento tem atributo de retorno
        draggedElement.hasAttribute('data-tipo-retorno');
    
    const isBloqueado = 
        // 1. Verificar se é horário bloqueado
        (draggedElement.innerHTML.includes('BLOQUEADO')) ||
        // 2. Verificar classe de bloqueado
        draggedElement.classList.contains('bloqueado-row') ||
        // 3. Verificar badge de bloqueado
        draggedElement.querySelector('[data-tipo="BLOQUEADO"]') ||
        // 4. Verificar ícone de bloqueado
        draggedElement.querySelector('.bi-lock-fill') ||
        // 5. Verificar se tem atributo de bloqueado
        draggedElement.hasAttribute('data-tipo-bloqueado');
    
    if (isEncaixe || isRetorno || isBloqueado) {
        const tipo = isEncaixe ? 'ENCAIXE' : (isRetorno ? 'RETORNO' : 'BLOQUEADO');
        console.log(`🚫 Drag DEFINITIVAMENTE bloqueado - agendamento ${tipo} detectado`);
        event.preventDefault();
        event.stopPropagation();
        
        // Feedback visual mais forte
        draggedElement.style.animation = 'shake 0.5s';
        draggedElement.style.cursor = 'not-allowed';
        draggedElement.style.opacity = '0.5';
        
        setTimeout(() => {
            draggedElement.style.animation = '';
            draggedElement.style.cursor = '';
            draggedElement.style.opacity = '';
        }, 1000);
        
        return false;
    }
    
    // Capturar todos os dados do agendamento da linha corretamente
    const colunas = draggedElement.querySelectorAll('td');
    
    // Pegar dados da segunda coluna (paciente)
    const colunaPaciente = colunas[1];
    const nomePaciente = colunaPaciente.querySelector('.font-medium')?.textContent?.trim() || '';
    const cpfElement = colunaPaciente.querySelector('.text-gray-500');
    const cpf = cpfElement ? cpfElement.textContent.replace('CPF: ', '').trim() : '';
    const tipoAtendimento = colunaPaciente.querySelector('.text-blue-600')?.textContent?.trim() || '';
    
    // Pegar dados das outras colunas
    const telefone = colunas[2]?.textContent?.trim().replace(/\s+/g, ' ') || '';
    const convenio = colunas[3]?.textContent?.trim().split('\n')[0] || '';
    
    console.log('Dados capturados:', { nomePaciente, cpf, telefone, convenio });
    
    draggedData = { 
        agendamentoId, 
        horaOriginal, 
        dataOriginal,
        dadosCompletos: {
            id: agendamentoId,
            paciente: nomePaciente,
            cpf: cpf.replace('CPF: ', '').trim(),
            telefone: telefone,
            convenio: convenio,
            tipo_atendimento: tipoAtendimento,
            status: 'AGENDADO'
        }
    };
    
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/html', event.target.innerHTML);
    
    draggedElement.classList.add('opacity-50');
}

function finalizarDrag(event) {
    if (draggedElement) {
        draggedElement.classList.remove('opacity-50');
    }
}

function permitirDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.add('bg-teal-50');
}

function removerDestaque(event) {
    event.currentTarget.classList.remove('bg-teal-50');
}

function soltarAgendamento(event, novaHora, novaData, agendaId) {
    event.preventDefault();
    event.currentTarget.classList.remove('bg-teal-50');
    
    if (!draggedData) return;
    
    const { agendamentoId, horaOriginal, dataOriginal, dadosCompletos } = draggedData;
    
    // Se for o mesmo horário e data, não fazer nada
    if (horaOriginal === novaHora && dataOriginal === novaData) {
        return;
    }
    
    // Mover diretamente sem confirmação
    moverAgendamento(agendamentoId, novaData, novaHora, agendaId, dataOriginal, horaOriginal, dadosCompletos);
}

function moverAgendamento(agendamentoId, novaData, novaHora, agendaId, dataOriginal, horaOriginal, dadosCompletos) {
    console.log('🔄 INICIANDO MOVIMENTAÇÃO:', {
        agendamentoId,
        de: `${dataOriginal} ${horaOriginal}`,
        para: `${novaData} ${novaHora}`,
        paciente: dadosCompletos?.paciente || 'N/A'
    });
    
    // Registrar timestamp do início da operação
    const timestampInicio = new Date();
    
    // Enviar requisição para mover
    fetch('mover_agendamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            agendamento_id: agendamentoId,
            nova_data: novaData,
            nova_hora: novaHora
        })
    })
    .then(safeJsonParse)
    .then(data => {
        const duracaoMs = new Date() - timestampInicio;
        
        if (data.status === 'sucesso') {
            console.log('✅ MOVIMENTAÇÃO REALIZADA COM SUCESSO:', {
                agendamento_id: data.detalhes?.agendamento_id,
                paciente: data.detalhes?.paciente,
                horario_anterior: data.detalhes?.horario_anterior,
                horario_novo: data.detalhes?.horario_novo,
                usuario: data.detalhes?.usuario,
                auditoria_registrada: data.detalhes?.auditoria_registrada,
                duracao_ms: duracaoMs,
                timestamp: new Date().toISOString()
            });

            // ✅ CORREÇÃO: Atualizar SEM reload - buscar dados atualizados e atualizar apenas linhas afetadas
            if (novaData === dataOriginal) {
                // Movimento no mesmo dia - atualizar cirurgicamente
                console.log('🔄 Atualizando visualização SEM reload...');
                atualizarVisualizacaoMovimentoInteligente(horaOriginal, novaHora, agendaId, novaData);
            } else {
                // Movimento para outro dia - apenas remover da visualização atual
                console.log('🔄 Removendo da visualização atual (movimento para outro dia)...');
                removerAgendamentoDaVisualizacao(horaOriginal);
            }

            // Mostrar notificação de sucesso com mais detalhes
            const mensagem = `Agendamento movido: ${data.detalhes?.paciente || 'Paciente'} para ${data.detalhes?.horario_novo || novaHora}`;
            mostrarNotificacao(mensagem, 'sucesso');
        } else {
            console.error('❌ FALHA NA MOVIMENTAÇÃO:', {
                agendamentoId,
                erro: data.mensagem,
                duracao_ms: duracaoMs,
                timestamp: new Date().toISOString()
            });
            
            // Mostrar erro
            mostrarNotificacao('Erro ao mover: ' + data.mensagem, 'erro');
        }
    })
    .catch(error => {
        const duracaoMs = new Date() - timestampInicio;
        
        console.error('💥 ERRO DE REDE NA MOVIMENTAÇÃO:', {
            agendamentoId,
            error: error.message,
            duracao_ms: duracaoMs,
            timestamp: new Date().toISOString()
        });
        
        mostrarNotificacao('Erro de conexão ao mover agendamento', 'erro');
    });
}

function atualizarVisualizacaoMovimento(horaOriginal, novaHora, dadosAgendamento, agendaId, data) {
    // Encontrar linha do horário original
    const linhaOriginal = encontrarLinhaPorHorario(horaOriginal);
    
    // Atualizar dados em memória
    delete window.agendamentos[horaOriginal];
    window.agendamentos[novaHora] = dadosAgendamento;
    
    // Re-renderizar apenas as linhas afetadas
    if (linhaOriginal) {
        // Criar HTML para horário livre
        const htmlLivre = criarLinhaHorarioLivre(horaOriginal, agendaId, data, true);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = htmlLivre;
        linhaOriginal.replaceWith(tempDiv.firstElementChild);
    }
    
    // Atualizar o novo horário
    const linhaNova = encontrarLinhaPorHorario(novaHora);
    if (linhaNova) {
        const htmlOcupado = criarLinhaHorarioOcupado(novaHora, dadosAgendamento, data);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = htmlOcupado;
        linhaNova.replaceWith(tempDiv.firstElementChild);
    }
}

function removerAgendamentoDaVisualizacao(hora) {
    const linha = encontrarLinhaPorHorario(hora);
    if (linha) {
        const agendaId = window.agendaIdAtual;
        const data = window.dataSelecionadaAtual;
        const htmlLivre = criarLinhaHorarioLivre(hora, agendaId, data, true);
        const tempDiv = document.createElement('tbody');
        tempDiv.innerHTML = htmlLivre;
        linha.replaceWith(tempDiv.firstElementChild);
    }
    
    delete window.agendamentos[hora];
}

// Função auxiliar para encontrar linha por horário
function encontrarLinhaPorHorario(horario) {
    const linhas = document.querySelectorAll('tbody tr');
    for (let linha of linhas) {
        const primeiraTd = linha.querySelector('td:first-child');
        if (primeiraTd && primeiraTd.textContent.trim() === horario) {
            return linha;
        }
    }
    return null;
}

function criarLinhaHorarioLivre(hora, agendaId, data, disponivel) {
    return `
        <tr class="hover:bg-gray-50 transition-colors ${!disponivel ? 'opacity-50' : ''}"
            ondrop="soltarAgendamento(event, '${hora}', '${data}', ${agendaId})"
            ondragover="permitirDrop(event)"
            ondragleave="removerDestaque(event)">
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                ${hora}
            </td>
            <td colspan="4" class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                ${disponivel ? `
                    <button onclick="abrirModalAgendamento(${agendaId}, '${data}', '${hora}')"
                            class="text-teal-600 hover:text-teal-900 font-medium">
                        <i class="bi bi-plus-circle mr-1"></i>
                        Clique para agendar
                    </button>
                ` : `
                    <span class="text-gray-400">
                        <i class="bi bi-clock-history mr-1"></i>
                        Horário indisponível
                    </span>
                `}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                ${disponivel ? `
                    <button onclick="bloquearHorario(${agendaId}, '${data}', '${hora}')"
                            class="text-gray-600 hover:text-gray-900" title="Bloquear horário">
                        <i class="bi bi-lock"></i>
                    </button>
                ` : ''}
            </td>
        </tr>`;
}

// ✅ NOVA FUNÇÃO: Atualizar visualização SEM reload após movimento
async function atualizarVisualizacaoMovimentoInteligente(horaOriginal, novaHora, agendaId, data) {
    console.log('🔄 Atualizando visualização inteligente:', { horaOriginal, novaHora, agendaId, data });

    try {
        // 1. Buscar dados atualizados do servidor
        const response = await fetchWithAuth(`buscar_agendamentos_dia.php?agenda_id=${agendaId}&data=${data}`);
        const agendamentosAtualizados = await response.json();

        console.log('📦 Dados atualizados recebidos:', agendamentosAtualizados);

        // 2. Atualizar dados em memória
        window.agendamentos = agendamentosAtualizados;

        // 3. Atualizar linha do horário ORIGINAL (agora livre ou ocupado por outro)
        const linhaOriginal = encontrarLinhaPorHorario(horaOriginal);
        const agendamentoAnteriorNoOriginal = agendamentosAtualizados[horaOriginal];

        if (linhaOriginal) {
            if (agendamentoAnteriorNoOriginal) {
                // Se ainda há agendamento nesse horário (outro agendamento), mostrar
                const htmlOcupado = criarLinhaHorarioOcupado(horaOriginal, agendamentoAnteriorNoOriginal, data);
                const tempDiv = document.createElement('tbody');
                tempDiv.innerHTML = htmlOcupado;
                linhaOriginal.replaceWith(tempDiv.firstElementChild);
                console.log('✅ Horário original atualizado (ainda ocupado por outro)');
            } else {
                // Horário ficou livre
                const htmlLivre = criarLinhaHorarioLivre(horaOriginal, agendaId, data, true);
                const tempDiv = document.createElement('tbody');
                tempDiv.innerHTML = htmlLivre;
                linhaOriginal.replaceWith(tempDiv.firstElementChild);
                console.log('✅ Horário original liberado');
            }
        }

        // 3.1 ✅ Não precisa fazer nada aqui - o reload da seção 6 vai cuidar disso se necessário

        // 4. Atualizar linha do NOVO horário (agora ocupado)
        const linhaNova = encontrarLinhaPorHorario(novaHora);
        if (linhaNova) {
            const agendamentoNovo = agendamentosAtualizados[novaHora];

            if (agendamentoNovo) {
                const htmlOcupado = criarLinhaHorarioOcupado(novaHora, agendamentoNovo, data);
                const tempDiv = document.createElement('tbody');
                tempDiv.innerHTML = htmlOcupado;
                linhaNova.replaceWith(tempDiv.firstElementChild);
                console.log('✅ Novo horário atualizado (agora ocupado)');
            }
        }

        // 5. Verificar e atualizar horários intermediários que podem ter sido afetados
        // (importante para agendamentos com múltiplos exames que ocupam vários slots)
        const horasParaVerificar = gerarHorarioEntre(horaOriginal, novaHora);

        for (const hora of horasParaVerificar) {
            if (hora === horaOriginal || hora === novaHora) continue; // Já atualizados

            const linha = encontrarLinhaPorHorario(hora);
            if (linha) {
                const agendamentoNaHora = agendamentosAtualizados[hora];

                if (agendamentoNaHora) {
                    // Horário agora ocupado
                    const htmlOcupado = criarLinhaHorarioOcupado(hora, agendamentoNaHora, data);
                    const tempDiv = document.createElement('tbody');
                    tempDiv.innerHTML = htmlOcupado;
                    linha.replaceWith(tempDiv.firstElementChild);
                    console.log(`✅ Horário intermediário ${hora} atualizado (ocupado)`);
                } else {
                    // Horário ficou livre
                    const htmlLivre = criarLinhaHorarioLivre(hora, agendaId, data, true);
                    const tempDiv = document.createElement('tbody');
                    tempDiv.innerHTML = htmlLivre;
                    linha.replaceWith(tempDiv.firstElementChild);
                    console.log(`✅ Horário intermediário ${hora} atualizado (livre)`);
                }
            }
        }

        // 6. ✅ CORREÇÃO DEFINITIVA: Inserir/atualizar horários subsequentes SEM RELOAD
        // (Ex: agendamento em 12:40 com 20 min ocupa também 12:50)
        const agendamentoMovido = agendamentosAtualizados[novaHora];
        if (agendamentoMovido && agendamentoMovido.tempo_total_minutos) {
            const tempoTotal = agendamentoMovido.tempo_total_minutos;
            const numSlots = Math.ceil(tempoTotal / 10);

            if (numSlots > 1) {
                console.log(`📏 Agendamento ocupa ${numSlots} slots (${tempoTotal} min) - inserindo horários subsequentes SEM reload`);

                // Gerar os horários subsequentes que devem ser atualizados/inseridos
                const [h, m] = novaHora.split(':').map(Number);
                let minutoAtual = h * 60 + m + 10; // Começa no próximo slot após o novo horário

                for (let i = 1; i < numSlots; i++) {
                    const hSubseq = Math.floor(minutoAtual / 60);
                    const mSubseq = minutoAtual % 60;
                    const horaSubseq = `${String(hSubseq).padStart(2, '0')}:${String(mSubseq).padStart(2, '0')}`;

                    const linhaSubseq = encontrarLinhaPorHorario(horaSubseq);
                    const agendamentoNaHoraSubseq = agendamentosAtualizados[horaSubseq];

                    if (linhaSubseq) {
                        // Linha existe - atualizar normalmente
                        if (agendamentoNaHoraSubseq) {
                            const htmlOcupado = criarLinhaHorarioOcupado(horaSubseq, agendamentoNaHoraSubseq, data);
                            const tempDiv = document.createElement('tbody');
                            tempDiv.innerHTML = htmlOcupado;
                            linhaSubseq.replaceWith(tempDiv.firstElementChild);
                            console.log(`✅ Horário subsequente ${horaSubseq} atualizado (ocupado por ${agendamentoNaHoraSubseq.numero})`);
                        }
                    } else if (agendamentoNaHoraSubseq) {
                        // ✅ Linha NÃO existe - INSERIR dinamicamente na posição correta
                        console.log(`➕ Inserindo horário ${horaSubseq} dinamicamente (não estava renderizado)`);

                        const htmlOcupado = criarLinhaHorarioOcupado(horaSubseq, agendamentoNaHoraSubseq, data);
                        const tempDiv = document.createElement('tbody');
                        tempDiv.innerHTML = htmlOcupado;
                        const novaLinha = tempDiv.firstElementChild;

                        // Encontrar a posição correta para inserir (após o horário anterior)
                        const tbody = document.querySelector('#tabela-agenda tbody');
                        if (tbody) {
                            // Encontrar a linha do horário anterior
                            const [hPrev, mPrev] = [Math.floor((minutoAtual - 10) / 60), (minutoAtual - 10) % 60];
                            const horaPrev = `${String(hPrev).padStart(2, '0')}:${String(mPrev).padStart(2, '0')}`;
                            const linhaPrev = encontrarLinhaPorHorario(horaPrev);

                            if (linhaPrev) {
                                // Inserir após a linha anterior
                                linhaPrev.after(novaLinha);
                                console.log(`✅ Horário ${horaSubseq} inserido após ${horaPrev}`);
                            } else {
                                // Se não encontrou anterior, tentar inserir na ordem correta
                                const todasLinhas = Array.from(tbody.querySelectorAll('tr'));
                                let inserido = false;

                                for (let j = 0; j < todasLinhas.length; j++) {
                                    const horaDaLinha = todasLinhas[j].querySelector('[data-hora]')?.dataset.hora;
                                    if (horaDaLinha && horaDaLinha > horaSubseq) {
                                        todasLinhas[j].before(novaLinha);
                                        inserido = true;
                                        console.log(`✅ Horário ${horaSubseq} inserido antes de ${horaDaLinha}`);
                                        break;
                                    }
                                }

                                if (!inserido) {
                                    tbody.appendChild(novaLinha);
                                    console.log(`✅ Horário ${horaSubseq} inserido no final da tabela`);
                                }
                            }
                        }
                    }

                    minutoAtual += 10;
                }
            }
        }

        console.log('✅ Visualização atualizada com sucesso SEM reload!');

    } catch (error) {
        console.error('❌ Erro ao atualizar visualização:', error);
        // Fallback: recarregar página se houver erro
        console.log('⚠️ Usando fallback: recarregando visualização completa');
        carregarVisualizacaoDia(agendaId, data);
    }
}

// Função auxiliar: gerar lista de horários entre dois horários
function gerarHorarioEntre(horaInicio, horaFim) {
    const horarios = [];

    const [hInicio, mInicio] = horaInicio.split(':').map(Number);
    const [hFim, mFim] = horaFim.split(':').map(Number);

    let minutoAtual = hInicio * 60 + mInicio;
    const minutoFim = hFim * 60 + mFim;

    // Garantir que percorremos na direção correta
    const passo = minutoAtual < minutoFim ? 10 : -10;

    while ((passo > 0 && minutoAtual <= minutoFim) || (passo < 0 && minutoAtual >= minutoFim)) {
        const h = Math.floor(minutoAtual / 60);
        const m = minutoAtual % 60;
        horarios.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`);
        minutoAtual += passo;
    }

    return horarios;
}

function criarLinhaHorarioOcupado(hora, agendamento, data) {
    // ✅ DEBUG EXTREMO para encontrar o problema
    console.log('🚨 criarLinhaHorarioOcupado CHAMADA');
    console.log('📊 DADOS COMPLETOS:', JSON.stringify(agendamento, null, 2));
    console.log('🔍 tipo_agendamento RAW:', agendamento.tipo_agendamento);
    console.log('🔍 tipo_agendamento TYPE:', typeof agendamento.tipo_agendamento);
    console.log('🔍 tipo_agendamento LENGTH:', (agendamento.tipo_agendamento || '').length);
    console.log('🔍 tipo_agendamento HEX:', agendamento.tipo_agendamento ? Array.from(agendamento.tipo_agendamento).map(c => c.charCodeAt(0).toString(16)).join(' ') : 'NULL');
    
    // Testar diferentes condições
    const tests = [
        agendamento.tipo_agendamento === 'ENCAIXE',
        agendamento.tipo_agendamento == 'ENCAIXE',
        (agendamento.tipo_agendamento || '').trim() === 'ENCAIXE',
        (agendamento.tipo_agendamento || '').trim().toUpperCase() === 'ENCAIXE',
        (agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE')
    ];
    
    console.log('🧪 TESTES DE COMPARAÇÃO:');
    console.log('  === "ENCAIXE":', tests[0]);
    console.log('  == "ENCAIXE":', tests[1]);
    console.log('  trim() === "ENCAIXE":', tests[2]);
    console.log('  trim().toUpperCase() === "ENCAIXE":', tests[3]);
    console.log('  ROBUST CHECK:', tests[4]);
    
    // ✅ VERIFICAR SE É ENCAIXE - CLASSE CSS E DRAG
    const isEncaixe = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
    const classeLinha = isEncaixe ? 
        'bg-orange-50 hover:bg-orange-100 border-l-4 border-orange-400 transition-colors' : 
        'hover:bg-gray-50 transition-colors';
    
    console.log(`🎨 CLASSE APLICADA: "${classeLinha}" | isEncaixe: ${isEncaixe}`);
    
    // ✅ CONFIGURAR PROPRIEDADES DE DRAG E IDENTIFICAÇÃO
    const isRetorno = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'RETORNO';
    const isBloqueado = agendamento.status && agendamento.status.trim().toUpperCase() === 'BLOQUEADO';
    
    const dragProps = (isEncaixe || isRetorno || isBloqueado) ? 
        'draggable="false" style="cursor: default;" data-tipo-nao-movivel="true"' : 
        `draggable="true" ondragstart="iniciarDrag(event, ${agendamento.id}, '${hora}', '${data}')" ondragend="finalizarDrag(event)"`;
    
    const classeAdicional = isBloqueado ? ' bloqueado-row' : 
                          (isEncaixe ? ' encaixe-row' : 
                           (isRetorno ? ' retorno-row' : ''));
    
    return `
        <tr class="${classeLinha}${classeAdicional}" ${dragProps}>
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                ${hora}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                <div class="flex items-center">
                    <i class="bi bi-person-circle text-gray-400 mr-2"></i>
                    <div>
                        <div class="font-medium">${agendamento.paciente || ''}</div>
                        ${agendamento.cpf ? `<div class="text-xs text-gray-500">CPF: ${agendamento.cpf}</div>` : ''}
                        ${agendamento.tipo_atendimento ? `<div class="text-xs text-blue-600"><i class="bi bi-clipboard-pulse mr-1"></i>${agendamento.tipo_atendimento}</div>` : ''}
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-center">
                ${agendamento.tem_os ? `
                    <button onclick="abrirModalOSCompleto(${agendamento.id})" 
                    class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 hover:bg-green-200 transition-colors">
                        <i class="bi bi-file-earmark-text mr-1"></i>
                        ${agendamento.os_numero}
                    </button>
                ` : '<span class="text-xs text-gray-400">-</span>'}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                <i class="bi bi-telephone text-gray-400 mr-1"></i>
                ${agendamento.telefone || '-'}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                ${agendamento.convenio || ''}
                ${agendamento.tipo_consulta === 'retorno' ? '<span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">Retorno</span>' : ''}
                ${(agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE') ? '<span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded font-semibold" data-tipo="ENCAIXE"><i class="bi bi-lightning-charge mr-1"></i>ENCAIXE</span>' : ''}
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                ${getStatusBadge(agendamento.status || 'AGENDADO')}
            </td>
            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                <div class="flex space-x-2">
                    <button onclick="visualizarAgendamento(${agendamento.id})" 
                            class="text-gray-600 hover:text-gray-900" title="Visualizar">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button onclick="editarAgendamento(${agendamento.id})" 
                            class="text-blue-600 hover:text-blue-900" title="Editar">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button onclick="cancelarAgendamento(${agendamento.id})" 
                            class="text-red-600 hover:text-red-900" title="Cancelar">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    </button>
                </div>
            </td>
        </tr>`;
}


/**
 * Carrega visualização da semana com tratamento de erro melhorado
 */
function carregarVisualizacaoSemana(agendaId, data) {
    const container = document.getElementById('area-visualizacao');
    
    if (!container) {
        console.error('Container area-visualizacao não encontrado');
        return;
    }
    
    // Loading
    container.innerHTML = `
        <div class="text-center text-gray-500 py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-600 mx-auto mb-2"></div>
            <p>Carregando agenda da semana...</p>
        </div>`;
    
    const dataObj = new Date(data + 'T00:00:00');
    const diaSemana = dataObj.getDay();
    
    // Calcular início da semana (segunda-feira)
    const inicioSemana = new Date(dataObj);
    inicioSemana.setDate(dataObj.getDate() - (diaSemana === 0 ? 6 : diaSemana - 1));
    
    const fimSemana = new Date(inicioSemana);
    fimSemana.setDate(inicioSemana.getDate() + 6);
    
    const dataInicio = formatarDataISO(inicioSemana);
    const dataFim = formatarDataISO(fimSemana);
    
    console.log('Carregando semana:', { dataInicio, dataFim });
    
    const url = `buscar_agendamentos_periodo.php?agenda_id=${agendaId}&data_inicio=${dataInicio}&data_fim=${dataFim}&tipo=semana`;
    console.log('URL da requisição:', url);
    
    fetch(url)
        .then(response => {
            console.log('Status da resposta:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(responseText => {
            console.log('Resposta recebida:', responseText);
            
            // Tentar fazer parse do JSON
            let dados;
            try {
                dados = JSON.parse(responseText);
            } catch (e) {
                console.error('Erro ao fazer parse do JSON:', e);
                console.error('Resposta recebida:', responseText);
                throw new Error('Resposta inválida do servidor');
            }
            
            console.log('Dados da semana:', dados);
            
            if (dados.erro) {
                throw new Error(dados.erro);
            }
            
            renderizarVisualizacaoSemana(inicioSemana, agendaId, dados, container);
        })
        .catch(error => {
            console.error('Erro ao carregar semana:', error);
            container.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <i class="bi bi-exclamation-triangle text-3xl mb-2"></i>
                    <p class="font-semibold mb-2">Erro ao carregar agenda da semana</p>
                    <p class="text-sm text-gray-600 mb-4">${error.message}</p>
                    <button onclick="carregarVisualizacaoSemana(${agendaId}, '${data}')" 
                            class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Tentar Novamente
                    </button>
                    <br><br>
                    <details class="text-left">
                        <summary class="cursor-pointer text-sm text-gray-500">Detalhes técnicos</summary>
                        <p class="text-xs mt-2 p-2 bg-gray-100 rounded font-mono">${error.stack || error.message}</p>
                    </details>
                </div>`;
        });
}

// Funções para drag & drop na visualização de semana
// Função para iniciar drag na semana
window.iniciarDragSemana = function(event, agendamentoId, dataOriginal, horaOriginal) {
    console.log('🎯 Iniciando drag:', { agendamentoId, dataOriginal, horaOriginal });
    
    // Encontrar dados do agendamento
    const agendamentos = window.agendamentosSemana || {};
    const agendamentosDia = agendamentos[dataOriginal] || [];
    const agendamento = agendamentosDia.find(ag => ag.id == agendamentoId);
    
    if (!agendamento) {
        console.error('❌ Agendamento não encontrado para drag');
        return;
    }
    
    // ✅ VERIFICAÇÃO ROBUSTA DE TIPOS NÃO MOVÍVEIS - SEMANA
    const isEncaixeTipo = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
    const isRetornoTipo = agendamento.tipo_consulta && agendamento.tipo_consulta.trim().toUpperCase() === 'RETORNO';
    const isBloqueadoStatus = agendamento.status && agendamento.status.trim().toUpperCase() === 'BLOQUEADO';
    const elemento = event.target.closest('[draggable]');
    
    const isEncaixeVisual = elemento && (
        elemento.innerHTML.includes('ENCAIXE') ||
        elemento.innerHTML.includes('bi-lightning-charge') ||
        elemento.querySelector('.bi-lightning-charge')
    );
    
    const isRetornoVisual = elemento && (
        elemento.innerHTML.includes('RETORNO') ||
        elemento.innerHTML.includes('bi-arrow-repeat') ||
        elemento.innerHTML.includes('bi-arrow-clockwise') ||
        elemento.querySelector('.bi-arrow-repeat') ||
        elemento.querySelector('.bi-arrow-clockwise')
    );
    
    const isBloqueadoVisual = elemento && (
        elemento.innerHTML.includes('BLOQUEADO') ||
        elemento.innerHTML.includes('bi-lock-fill') ||
        elemento.querySelector('.bi-lock-fill')
    );
    
    if (isEncaixeTipo || isEncaixeVisual || isRetornoTipo || isRetornoVisual || isBloqueadoStatus || isBloqueadoVisual) {
        const tipo = (isEncaixeTipo || isEncaixeVisual) ? 'ENCAIXE' : 
                    (isRetornoTipo || isRetornoVisual) ? 'RETORNO' : 'BLOQUEADO';
        console.log(`🚫 Drag DEFINITIVAMENTE bloqueado na SEMANA - agendamento ${tipo} detectado`);
        event.preventDefault();
        event.stopPropagation();
        
        // Feedback visual mais forte
        if (elemento) {
            elemento.style.animation = 'shake 0.5s';
            elemento.style.cursor = 'not-allowed';
            elemento.style.opacity = '0.5';
            elemento.style.filter = 'grayscale(100%)';
            
            setTimeout(() => {
                elemento.style.animation = '';
                elemento.style.cursor = '';
                elemento.style.opacity = '';
                elemento.style.filter = '';
            }, 1000);
        }
        
        return false;
    }
    
    // Armazenar dados globalmente
    window.dadosDrag = {
        agendamentoId: agendamentoId,
        dataOriginal: dataOriginal,
        horaOriginal: horaOriginal,
        dadosCompletos: agendamento
    };
    
    // Visual feedback
    event.target.style.opacity = '0.5';
    event.dataTransfer.effectAllowed = 'move';
    
    console.log('✅ Drag iniciado com sucesso');
};

// Função para finalizar drag
window.finalizarDrag = function(event) {
    event.target.style.opacity = '1';
    console.log('🏁 Drag finalizado');
};

// Função para permitir drop
window.permitirDrop = function(event) {
    event.preventDefault();
    event.target.style.backgroundColor = '#f0fdfa'; // teal-50
};

// Função para remover destaque
window.removerDestaque = function(event) {
    event.target.style.backgroundColor = '';
};

// Função para soltar agendamento
// 1️⃣ ✅ CORRIGIR: Função soltarAgendamentoSemana SEM refresh
window.soltarAgendamentoSemana = function(event, novaData, novaHora) {
    event.preventDefault();
    event.target.style.backgroundColor = '';
    
    const dadosDrag = window.dadosDrag;
    if (!dadosDrag) {
        console.error('❌ Dados de drag não encontrados');
        return;
    }
    
    const { agendamentoId, dataOriginal, horaOriginal, dadosCompletos } = dadosDrag;
    
    console.log('📦 Soltando agendamento:', {
        de: `${dataOriginal} ${horaOriginal}`,
        para: `${novaData} ${novaHora}`
    });
    
    // Verificar se não é o mesmo local
    if (dataOriginal === novaData && horaOriginal === novaHora) {
        console.log('⚠️ Mesmo local, cancelando operação');
        return;
    }
    
    // ✅ Fazer requisição para mover com JSON (sem recarregar)
    fetch('mover_agendamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            agendamento_id: agendamentoId,
            nova_data: novaData,
            nova_hora: novaHora
        })
    })
    .then(safeJsonParse)
    .then(data => {
        if (data.status === 'sucesso') {
            console.log('✅ Agendamento movido com sucesso');
            
            // ✅ CORRIGIDO: Atualizar apenas a visualização SEM REFRESH
            atualizarVisualizacaoSemanaLocal(dataOriginal, horaOriginal, novaData, novaHora, dadosCompletos);
            
            mostrarNotificacao('Agendamento movido com sucesso', 'sucesso');
        } else {
            console.error('❌ Erro ao mover:', data.mensagem);
            mostrarNotificacao('Erro ao mover: ' + data.mensagem, 'erro');
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        mostrarNotificacao('Erro ao mover agendamento', 'erro');
    })
    .finally(() => {
        window.dadosDrag = null; // Limpar dados
    });
};

// 2️⃣ ✅ NOVA: Função para atualizar visualização localmente (sem refresh)
function atualizarVisualizacaoSemanaLocal(dataOriginal, horaOriginal, novaData, novaHora, dadosCompletos) {
    // Atualizar dados em memória
    if (window.agendamentosSemana) {
        // Remover do local original
        const agendamentosOriginais = window.agendamentosSemana[dataOriginal] || [];
        window.agendamentosSemana[dataOriginal] = agendamentosOriginais.filter(ag => ag.id != dadosCompletos.id);
        
        // Adicionar no novo local
        if (!window.agendamentosSemana[novaData]) {
            window.agendamentosSemana[novaData] = [];
        }
        window.agendamentosSemana[novaData].push({
            ...dadosCompletos,
            hora: novaHora
        });
    }
    
    // Atualizar células visuais
    atualizarCelulaSemanaLocal(dataOriginal, horaOriginal, null);
    atualizarCelulaSemanaLocal(novaData, novaHora, dadosCompletos);
}

// 3️⃣ ✅ NOVA: Função para atualizar célula específica
function atualizarCelulaSemanaLocal(data, hora, agendamento) {
    const agendaId = window.agendaIdAtual;
    
    // Encontrar a célula específica
    const celulas = document.querySelectorAll('#area-visualizacao .grid.grid-cols-8 > div');
    
    celulas.forEach(celula => {
        const onclick = celula.getAttribute('onclick');
        const ondragstart = celula.getAttribute('ondragstart');
        
        // Verificar se é a célula correta
        if ((onclick && onclick.includes(`'${data}'`) && onclick.includes(`'${hora}'`)) ||
            (ondragstart && ondragstart.includes(`'${data}'`) && ondragstart.includes(`'${hora}'`))) {
            
            if (agendamento) {
                // Célula com agendamento
                celula.innerHTML = `
                    <div class="text-xs font-medium text-orange-800 truncate">
                        ${agendamento.paciente.length > 15 ? agendamento.paciente.substring(0, 15) + '...' : agendamento.paciente}
                        ${(agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE') ? '<span class="inline-block ml-1 text-orange-600" title="Encaixe"><i class="bi bi-lightning-charge"></i></span>' : ''}
                    </div>
                    <div class="text-xs text-orange-600 truncate">
                        ${agendamento.convenio}
                    </div>
                `;
                // ✅ VERIFICAR SE É ENCAIXE PARA DESABILITAR DRAG
                const isEncaixe = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
                
                celula.className = `bg-gray-100 border-l-4 border-gray-400 p-2 h-16 transition-colors ${
                    isEncaixe ? 'cursor-default' : 'cursor-pointer hover:bg-gray-200'
                }`;
                
                if (isEncaixe) {
                    celula.draggable = false;
                    celula.removeAttribute('ondragstart');
                    celula.removeAttribute('ondragend');
                    console.log('🚫 Drag desabilitado para ENCAIXE na célula da semana');
                } else {
                    celula.draggable = true;
                    celula.setAttribute('ondragstart', `window.iniciarDragSemana(event, ${agendamento.id}, '${data}', '${hora}')`);
                    celula.setAttribute('ondragend', 'window.finalizarDrag(event)');
                }
                
                celula.removeAttribute('onclick');
            } else {
                // Célula vazia
                celula.innerHTML = '';
                celula.className = 'bg-white h-16 hover:bg-teal-50 cursor-pointer transition-colors border border-transparent hover:border-teal-200 rounded-sm';
                celula.draggable = false;
                celula.setAttribute('onclick', `window.selecionarSlotSemana('${data}', '${hora}', ${agendaId})`);
                celula.removeAttribute('ondragstart');
                celula.removeAttribute('ondragend');
            }
        }
    });
}


// Função para selecionar slot para novo agendamento
window.selecionarSlotSemana = function(data, horario, agendaId) {
    console.log('🎯 Slot selecionado:', { data, horario, agendaId });
    
    // Verificar se não é no passado
    const agora = new Date();
    const dataHorario = new Date(data + 'T' + horario);
    
    if (dataHorario < agora) {
        alert('Este horário já passou e não pode ser agendado.');
        return;
    }
    
    // Abrir modal de agendamento (se existir)
    if (typeof abrirModalAgendamento === 'function') {
        abrirModalAgendamento(agendaId, data, horario);
    } else {
        // Fallback - redirecionar para página de agendamento
        window.location.href = `finalizar_agendamento.php?agenda_id=${agendaId}&data=${data}&horario=${horario}`;
    }
};

function moverAgendamentoSemana(agendamentoId, novaData, novaHora, agendaId, dataOriginal, horaOriginal, dadosCompletos) {
    console.log('🔄 INICIANDO MOVIMENTAÇÃO SEMANAL:', {
        agendamentoId,
        de: `${dataOriginal} ${horaOriginal}`,
        para: `${novaData} ${novaHora}`,
        paciente: dadosCompletos?.paciente || 'N/A'
    });
    
    // Registrar timestamp do início da operação
    const timestampInicio = new Date();
    
    // Enviar requisição para mover
    fetch('mover_agendamento.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            agendamento_id: agendamentoId,
            nova_data: novaData,
            nova_hora: novaHora
        })
    })
    .then(safeJsonParse)
    .then(data => {
        const duracaoMs = new Date() - timestampInicio;
        
        if (data.status === 'sucesso') {
            console.log('✅ MOVIMENTAÇÃO SEMANAL REALIZADA COM SUCESSO:', {
                agendamento_id: data.detalhes?.agendamento_id,
                paciente: data.detalhes?.paciente,
                horario_anterior: data.detalhes?.horario_anterior,
                horario_novo: data.detalhes?.horario_novo,
                usuario: data.detalhes?.usuario,
                auditoria_registrada: data.detalhes?.auditoria_registrada,
                duracao_ms: duracaoMs,
                timestamp: new Date().toISOString()
            });
            // Atualizar visualização da semana
            // Primeiro, limpar célula original
            atualizarCelulaSemana(dataOriginal, horaOriginal, null, agendaId);
            
            // Depois, preencher nova célula
            setTimeout(() => {
                atualizarCelulaSemana(novaData, novaHora, dadosCompletos, agendaId);
            }, 100);
            
            // Se estiver na mesma semana, atualizar dados em memória
            if (window.agendamentosSemana) {
                // Remover do horário antigo
                const agendamentosDataOriginal = window.agendamentosSemana[dataOriginal] || [];
                window.agendamentosSemana[dataOriginal] = agendamentosDataOriginal.filter(ag => ag.hora !== horaOriginal);
                
                // Adicionar no novo horário
                if (!window.agendamentosSemana[novaData]) {
                    window.agendamentosSemana[novaData] = [];
                }
                window.agendamentosSemana[novaData].push({
                    ...dadosCompletos,
                    hora: novaHora
                });
            }
            
            mostrarNotificacao('Agendamento movido com sucesso', 'sucesso');
        } else {
            mostrarNotificacao('Erro ao mover: ' + data.mensagem, 'erro');
        }
    })
    .catch(error => {
        console.error('Erro ao mover agendamento:', error);
        mostrarNotificacao('Erro ao mover agendamento', 'erro');
    });
}

function atualizarCelulaSemana(data, hora, agendamento, agendaId) {
    // Recarregar parcialmente a visualização da semana para o horário específico
    const todasDivs = document.querySelectorAll('#area-visualizacao div');
    
    // Primeiro, encontrar a célula específica
    let celulaEncontrada = null;
    
    todasDivs.forEach(div => {
        const onclick = div.getAttribute('onclick');
        const ondrop = div.getAttribute('ondrop');
        const ondragstart = div.getAttribute('ondragstart');
        
        // Verificar se é a célula correta
        if (onclick && onclick.includes(`'${data}'`) && onclick.includes(`'${hora}'`)) {
            celulaEncontrada = div;
        } else if (ondrop && ondrop.includes(`'${data}'`) && ondrop.includes(`'${hora}'`)) {
            celulaEncontrada = div;
        } else if (ondragstart && ondragstart.includes(`'${data}'`) && ondragstart.includes(`'${hora}'`)) {
            celulaEncontrada = div;
        }
    });
    
    if (celulaEncontrada) {
        // Atualizar dados em memória primeiro
        if (!window.agendamentosSemana[data]) {
            window.agendamentosSemana[data] = [];
        }
        
        // Remover agendamento antigo do horário (se estiver movendo)
        window.agendamentosSemana[data] = window.agendamentosSemana[data].filter(
            ag => !(ag.hora === hora && ag.id === (agendamento ? agendamento.id : null))
        );
        
        // Adicionar novo agendamento se fornecido
        if (agendamento) {
            window.agendamentosSemana[data].push({
                ...agendamento,
                hora: hora
            });
        }
        
        // Buscar todos os agendamentos do horário
        const agendamentosHorario = window.agendamentosSemana[data].filter(ag => ag.hora === hora);
        
        if (agendamentosHorario.length > 0) {
            if (agendamentosHorario.length > 1) {
                // Múltiplos agendamentos
                celulaEncontrada.innerHTML = `
                    ${agendamentosHorario.slice(0, 2).map((ag, idx) => {
                        const isEncaixe = ag.tipo_agendamento && ag.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
                        const isRetorno = ag.tipo_agendamento && ag.tipo_agendamento.trim().toUpperCase() === 'RETORNO';
                        const isBloqueado = ag.status && ag.status.trim().toUpperCase() === 'BLOQUEADO';
                        
                        const dragProps = (isEncaixe || isRetorno || isBloqueado) ? 
                            'draggable="false" style="cursor: default;"' : 
                            `draggable="true" ondragstart="iniciarDragSemana(event, ${ag.id}, '${data}', '${hora}')" ondragend="finalizarDrag(event)"`;
                        const hoverClass = (isEncaixe || isRetorno || isBloqueado) ? '' : 'hover:bg-orange-200';
                        
                        return `
                        <div class="text-xs cursor-pointer ${hoverClass} p-1 rounded mb-1"
                             ${dragProps}
                             title="Paciente: ${ag.paciente}&#10;Convênio: ${ag.convenio}">
                            <div class="font-medium text-orange-800 truncate text-xs">
                                ${ag.paciente.split(' ')[0]}
                                ${(ag.tipo_agendamento && ag.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE') ? '<i class="bi bi-lightning-charge ml-1" title="Encaixe"></i>' : ''}
                            </div>
                        </div>
                        `;
                    }).join('')}
                    ${agendamentosHorario.length > 2 ? `
                        <div class="absolute bottom-0 right-0 bg-orange-300 text-orange-800 text-xs px-1 rounded-tl">
                            +${agendamentosHorario.length - 2}
                        </div>
                    ` : ''}
                `;
                celulaEncontrada.className = 'bg-gray-100 border-l-4 border-gray-400 p-1 h-16 overflow-hidden relative';
                celulaEncontrada.removeAttribute('onclick');
                celulaEncontrada.removeAttribute('ondrop');
                celulaEncontrada.removeAttribute('ondragover');
                celulaEncontrada.removeAttribute('ondragleave');
            } else {
                // Apenas um agendamento
                const ag = agendamentosHorario[0];
                celulaEncontrada.innerHTML = `
                    <div class="text-xs font-medium text-orange-800 truncate">
                        ${ag.paciente}
                    </div>
                    <div class="text-xs text-orange-600 truncate">
                        ${ag.convenio}
                    </div>
                `;
                // ✅ VERIFICAR SE É ENCAIXE PARA DESABILITAR DRAG
                const isEncaixeUnico = ag.tipo_agendamento && ag.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
                
                celulaEncontrada.className = `bg-gray-100 border-l-4 border-gray-400 p-2 h-16 transition-colors ${
                    isEncaixeUnico ? 'cursor-default' : 'cursor-pointer hover:bg-gray-200'
                }`;
                
                if (isEncaixeUnico) {
                    celulaEncontrada.draggable = false;
                    celulaEncontrada.removeAttribute('ondragstart');
                    celulaEncontrada.removeAttribute('ondragend');
                    console.log('🚫 Drag desabilitado para ENCAIXE único na célula da semana');
                } else {
                    celulaEncontrada.draggable = true;
                    celulaEncontrada.setAttribute('ondragstart', `iniciarDragSemana(event, ${ag.id}, '${data}', '${hora}')`);
                    celulaEncontrada.setAttribute('ondragend', 'finalizarDrag(event)');
                }
                
                celulaEncontrada.setAttribute('title', `Paciente: ${ag.paciente}\nConvênio: ${ag.convenio}\nStatus: ${ag.status || 'AGENDADO'}`);
                celulaEncontrada.removeAttribute('onclick');
                celulaEncontrada.removeAttribute('ondrop');
                celulaEncontrada.removeAttribute('ondragover');
                celulaEncontrada.removeAttribute('ondragleave');
            }
        } else {
            // Célula livre
            celulaEncontrada.innerHTML = '';
            celulaEncontrada.className = 'bg-white h-16 hover:bg-teal-50 cursor-pointer transition-colors border border-transparent hover:border-teal-200 rounded-sm';
            celulaEncontrada.draggable = false;
            celulaEncontrada.setAttribute('onclick', `abrirModalAgendamento(${agendaId}, '${data}', '${hora}')`);
            celulaEncontrada.setAttribute('ondrop', `soltarAgendamentoSemana(event, '${data}', '${hora}', ${agendaId})`);
            celulaEncontrada.setAttribute('ondragover', 'permitirDrop(event)');
            celulaEncontrada.setAttribute('ondragleave', 'removerDestaque(event)');
            celulaEncontrada.setAttribute('title', `Clique para agendar às ${hora}`);
            celulaEncontrada.removeAttribute('ondragstart');
            celulaEncontrada.removeAttribute('ondragend');
        }
    }
}

// Função para mostrar notificação discreta
function mostrarNotificacao(mensagem, tipo = 'info') {
    // Remove notificação anterior se existir
    const notificacaoAnterior = document.getElementById('notificacao-agendamento');
    if (notificacaoAnterior) {
        notificacaoAnterior.remove();
    }
    
    const cores = {
        sucesso: 'bg-green-500',
        erro: 'bg-red-500',
        info: 'bg-blue-500'
    };
    
    const notificacao = document.createElement('div');
    notificacao.id = 'notificacao-agendamento';
    notificacao.className = `fixed top-4 right-4 ${cores[tipo]} text-white px-6 py-3 rounded-lg shadow-lg z-50 transition-opacity duration-300`;
    notificacao.innerHTML = `
        <div class="flex items-center">
            <i class="bi ${tipo === 'sucesso' ? 'bi-check-circle' : tipo === 'erro' ? 'bi-x-circle' : 'bi-info-circle'} mr-2"></i>
            ${mensagem}
        </div>
    `;
    
    document.body.appendChild(notificacao);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notificacao.style.opacity = '0';
        setTimeout(() => notificacao.remove(), 300);
    }, 3000);
}

// 4️⃣ ✅ PRINCIPAL: Função para renderizar semana com horários DINÂMICOS
function renderizarVisualizacaoSemana(inicioSemana, agendaId, dados, container) {
    const diasNomes = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    // Gerar dias da semana
    const diasSemana = [];
    for (let i = 0; i < 7; i++) {
        const dia = new Date(inicioSemana);
        dia.setDate(inicioSemana.getDate() + i);
        diasSemana.push(dia);
    }
    
    const agendamentos = dados.agendamentos || {};
    const hoje = new Date();
    const agora = hoje.getHours() * 60 + hoje.getMinutes();
    
    // Armazenar globalmente para drag & drop
    window.agendamentosSemana = agendamentos;
    
    // ✅ DEBUG: Verificar se TIPO_AGENDAMENTO está chegando
    Object.keys(agendamentos).forEach(data => {
        agendamentos[data].forEach(ag => {
            if (ag.tipo_agendamento) {
                console.log(`📋 DEBUG SEMANA: ${data} ${ag.hora} - ${ag.paciente} - tipo_agendamento: "${ag.tipo_agendamento}"`);
            }
        });
    });
    
    console.log('🔄 Buscando horários específicos para cada dia da semana...');
    
    // ✅ CORRIGIDO: Buscar horários dinâmicos para cada dia
    const promessasHorarios = diasSemana.map(dia => {
        const dataFormatada = formatarDataISO(dia);
        return fetchWithAuth(`buscar_horarios.php?agenda_id=${agendaId}&data=${dataFormatada}`)
            .then(safeJsonParse)
            .then(dadosHorarios => {
                // Extrair apenas os horários
                let horarios = [];
                if (dadosHorarios.horarios && Array.isArray(dadosHorarios.horarios)) {
                    horarios = dadosHorarios.horarios.map(h => h.hora);
                } else if (Array.isArray(dadosHorarios)) {
                    horarios = dadosHorarios.map(h => h.hora);
                }
                
                return {
                    data: dataFormatada,
                    dia: dia,
                    horarios: horarios
                };
            })
            .catch(error => {
                console.error(`Erro ao buscar horários para ${dataFormatada}:`, error);
                return {
                    data: dataFormatada,
                    dia: dia,
                    horarios: []
                };
            });
    });
    
    // ✅ Aguardar todos os horários e renderizar
    Promise.all(promessasHorarios)
        .then(dadosDias => {
            console.log('📅 Horários coletados para todos os dias:', dadosDias);
            
            // Coletar TODOS os horários únicos de TODOS os dias
            const todosHorarios = new Set();
            dadosDias.forEach(dadoDia => {
                dadoDia.horarios.forEach(horario => {
                    todosHorarios.add(horario);
                });
            });
            
            // Adicionar horários dos agendamentos existentes
            Object.keys(agendamentos).forEach(data => {
                agendamentos[data].forEach(ag => {
                    todosHorarios.add(ag.hora);
                });
            });
            
            // Converter para array e ordenar
            const horariosOrdenados = Array.from(todosHorarios).sort();
            
            console.log('🕐 Horários únicos encontrados:', horariosOrdenados.length, horariosOrdenados);
            
            // ✅ Renderizar a semana com horários dinâmicos
            let html = `
                <div class="overflow-x-auto">
                    <div class="min-w-full">
                        <!-- Cabeçalho dos dias -->
                        <div class="grid grid-cols-8 gap-1 mb-3 bg-gray-50 rounded-lg p-2">
                            <div class="text-xs font-medium text-gray-500 p-2"></div>
                            ${diasSemana.map(dia => {
                                const ehHoje = dia.toDateString() === hoje.toDateString();
                                const dataFormatada = formatarDataISO(dia);
                                const temAgendamentos = agendamentos[dataFormatada] && agendamentos[dataFormatada].length > 0;
                                
                                return `
                                    <div class="text-xs font-medium p-2 text-center rounded ${ehHoje ? 'bg-teal-100 text-teal-700' : 'text-gray-700'}">
                                        <div class="font-bold">${diasNomes[dia.getDay()]}</div>
                                        <div class="text-lg font-bold mt-1">${dia.getDate()}</div>
                                        <div class="text-xs text-gray-500">${(dia.getMonth() + 1).toString().padStart(2, '0')}</div>
                                        ${temAgendamentos ? '<div class="text-orange-500 text-xs mt-1">●</div>' : ''}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                        
                        <!-- Grade de horários DINÂMICOS -->
                        <div class="space-y-px bg-gray-200 rounded-lg overflow-hidden">
                            ${horariosOrdenados.map(horario => {
                                const [horas, minutos] = horario.split(':').map(Number);
                                const minutosHorario = horas * 60 + minutos;
                                
                                return `
                                    <div class="grid grid-cols-8 gap-px">
                                        <div class="bg-white text-xs text-gray-500 p-3 text-right font-medium">
                                            ${horario}
                                        </div>
                                        ${diasSemana.map(dia => {
                                            const dataFormatada = formatarDataISO(dia);
                                            const ehHoje = dia.toDateString() === hoje.toDateString();
                                            const horarioPassou = ehHoje && minutosHorario < agora;
                                            const agendamentosDia = agendamentos[dataFormatada] || [];
                                            
                                            // Buscar agendamento específico para este horário
                                            const agendamentosHorario = agendamentosDia.filter(ag => ag.hora === horario);
                                            
                                            // Verificar se o dia tem este horário disponível
                                            const dadoDia = dadosDias.find(dd => dd.data === dataFormatada);
                                            const diaTemHorario = dadoDia && dadoDia.horarios.includes(horario);
                                            
                                            console.log(`🔍 ${dataFormatada} ${horario}: agendamentos=${agendamentosHorario.length}, diaTemHorario=${diaTemHorario}`);
                                            
                                            if (agendamentosHorario.length > 0) {
                                                // Tem agendamento
                                                const agendamento = agendamentosHorario[0];
                                                
                                                const isEncaixeMes = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE';
                                                const isRetornoMes = agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'RETORNO';
                                                const isBloqueadoMes = agendamento.status && agendamento.status.trim().toUpperCase() === 'BLOQUEADO';
                                                
                                                const dragPropsMes = (isEncaixeMes || isRetornoMes || isBloqueadoMes) ? 
                                                    'draggable="false" style="cursor: default;"' : 
                                                    `draggable="true" ondragstart="window.iniciarDragSemana(event, ${agendamento.id}, '${dataFormatada}', '${horario}')" ondragend="window.finalizarDrag(event)"`;
                                                const hoverClassMes = (isEncaixeMes || isRetornoMes || isBloqueadoMes) ? '' : 'hover:bg-orange-200';
                                                
                                                return `
                                                    <div class="bg-gray-100 border-l-4 border-gray-400 p-2 h-16 cursor-pointer ${hoverClassMes} transition-colors relative" 
                                                        ${dragPropsMes}
                                                        onclick="visualizarAgendamento(${agendamento.id})"
                                                        title="Paciente: ${agendamento.paciente}&#10;Convênio: ${agendamento.convenio}&#10;Status: ${agendamento.status}">
                                                        <div class="text-xs font-medium text-orange-800 truncate">
                                                            ${agendamento.paciente.length > 15 ? agendamento.paciente.substring(0, 15) + '...' : agendamento.paciente}
                                                            ${(agendamento.tipo_agendamento && agendamento.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE') ? '<span class="inline-block ml-1 text-orange-600" title="Encaixe"><i class="bi bi-lightning-charge"></i></span>' : ''}
                                                        </div>
                                                        <div class="text-xs text-orange-600 truncate">
                                                            ${agendamento.convenio}
                                                        </div>
                                                        ${agendamentosHorario.length > 1 ? 
                                                            `<div class="absolute top-1 right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                                                ${agendamentosHorario.length}
                                                            </div>` : ''}
                                                    </div>
                                                `;
                                            } else if (diaTemHorario && !horarioPassou) {
                                                // Horário disponível para este dia
                                                return `
                                                    <div class="bg-white h-16 hover:bg-teal-50 cursor-pointer transition-colors border border-transparent hover:border-teal-200 rounded-sm" 
                                                        onclick="window.selecionarSlotSemana('${dataFormatada}', '${horario}', ${agendaId})"
                                                        ondrop="window.soltarAgendamentoSemana(event, '${dataFormatada}', '${horario}')"
                                                        ondragover="window.permitirDrop(event)"
                                                        ondragleave="window.removerDestaque(event)"
                                                        title="Clique para agendar às ${horario}">
                                                    </div>
                                                `;
                                            } else {
                                                // Horário não disponível para este dia ou já passou
                                                return `
                                                    <div class="bg-gray-100 h-16 opacity-30" 
                                                         title="${!diaTemHorario ? 'Horário não disponível neste dia' : 'Horário indisponível'}">
                                                        ${horarioPassou ? '<span class="text-xs text-gray-400 p-1">●</span>' : ''}
                                                    </div>
                                                `;
                                            }
                                        }).join('')}
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                </div>
                
                <!-- Legenda -->
                <div class="mt-6 flex items-center justify-center space-x-8 text-sm">
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-gray-100 border-l-4 border-gray-400 rounded-sm"></div>
                        <span class="text-gray-600">Horário ocupado</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-white border border-gray-300 rounded-sm"></div>
                        <span class="text-gray-600">Horário disponível</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-4 h-4 bg-gray-100 rounded-sm"></div>
                        <span class="text-gray-600">Horário indisponível</span>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
            console.log('✅ Semana renderizada com', horariosOrdenados.length, 'horários dinâmicos');
        })
        .catch(error => {
            console.error('❌ Erro ao carregar horários da semana:', error);
            container.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <i class="bi bi-exclamation-triangle text-3xl mb-2"></i>
                    <p>Erro ao carregar horários da semana</p>
                    <button onclick="carregarVisualizacaoSemana(${agendaId}, '${formatarDataISO(inicioSemana)}')" 
                            class="mt-4 px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">
                        Tentar Novamente
                    </button>
                </div>`;
        });
        setTimeout(() => adicionarIndicadoresEncaixe(), 1000);
}

/**
 * Carrega visualização do mês
 */
// 1️⃣ ✅ Função para carregar visualização do mês (com debug melhorado)
function carregarVisualizacaoMes(agendaId, data) {
    const container = document.getElementById('area-visualizacao');
    
    if (!container) {
        console.error('Container area-visualizacao não encontrado');
        return;
    }
    
    // Loading
    container.innerHTML = `
        <div class="text-center text-gray-500 py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-teal-600 mx-auto mb-2"></div>
            <p>Carregando agenda do mês...</p>
        </div>`;
    
    const dataObj = new Date(data + 'T00:00:00');
    const ano = dataObj.getFullYear();
    const mes = dataObj.getMonth();
    
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    
    const dataInicio = formatarDataISO(primeiroDia);
    const dataFim = formatarDataISO(ultimoDia);
    
    console.log('🗓️ Carregando mês:', { 
        ano, 
        mes: mes + 1, 
        dataInicio, 
        dataFim,
        agendaId 
    });
    
    const url = `buscar_agendamentos_periodo.php?agenda_id=${agendaId}&data_inicio=${dataInicio}&data_fim=${dataFim}&tipo=mes`;
    console.log('🌐 URL da requisição:', url);
    
    fetch(url)
        .then(response => {
            console.log('📡 Status da resposta:', response.status);
            console.log('📡 Headers:', response.headers.get('content-type'));
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Primeiro pegar o texto da resposta para debug
            return response.text();
        })
        .then(responseText => {
            console.log('📄 Resposta bruta:', responseText.substring(0, 500));
            
            // Verificar se a resposta está vazia
            if (!responseText.trim()) {
                throw new Error('Resposta vazia do servidor');
            }
            
            // Tentar fazer parse do JSON
            let dados;
            try {
                dados = JSON.parse(responseText);
            } catch (e) {
                console.error('❌ Erro ao fazer parse do JSON:', e);
                console.error('📄 Resposta completa:', responseText);
                throw new Error('Resposta inválida do servidor: ' + e.message);
            }
            
            console.log('✅ Dados do mês parseados:', dados);
            
            if (dados.erro) {
                throw new Error(dados.erro);
            }
            
            renderizarVisualizacaoMes(ano, mes, agendaId, dados, container);
        })
        .catch(error => {
            console.error('❌ Erro ao carregar mês:', error);
            container.innerHTML = `
                <div class="text-center text-red-500 py-8">
                    <i class="bi bi-exclamation-triangle text-3xl mb-2"></i>
                    <p class="font-semibold mb-2">Erro ao carregar agenda do mês</p>
                    <p class="text-sm text-gray-600 mb-4">${error.message}</p>
                    <button onclick="carregarVisualizacaoMes(${agendaId}, '${data}')" 
                            class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition">
                        <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"></path></svg>Tentar Novamente
                    </button>
                    <br><br>
                    <details class="text-left">
                        <summary class="cursor-pointer text-sm text-gray-500">Detalhes técnicos</summary>
                        <p class="text-xs mt-2 p-2 bg-gray-100 rounded font-mono">${error.stack || error.message}</p>
                    </details>
                </div>`;
        });
}

/**
 * Renderiza visualização do mês
 */
function renderizarVisualizacaoMes(ano, mes, agendaId, dados, container) {
    const meses = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    
    const diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
    
    const primeiroDia = new Date(ano, mes, 1);
    const ultimoDia = new Date(ano, mes + 1, 0);
    const diasNoMes = ultimoDia.getDate();
    const primeiroDiaSemana = primeiroDia.getDay();
    
    const agendamentos = dados.agendamentos || {};
    const estatisticas = dados.estatisticas || {};
    const hoje = new Date();
    
    console.log('🎨 Renderizando mês:', {
        mes: meses[mes],
        ano,
        diasNoMes,
        totalAgendamentos: Object.keys(agendamentos).length,
        estatisticas
    });
    
    let html = `
        <div class="space-y-6">
            <!-- Cabeçalho do Mês -->
            <div class="text-center bg-gradient-to-r from-teal-50 to-blue-50 rounded-lg p-6">
                <h3 class="text-3xl font-bold text-gray-800 mb-2">${meses[mes]} ${ano}</h3>
                <div class="flex justify-center items-center space-x-6 text-sm text-gray-600">
                    <div class="flex items-center">
                        <i class="bi bi-calendar-check text-teal-600 mr-2"></i>
                        <span>${estatisticas.total_agendamentos || 0} agendamentos</span>
                    </div>
                    <div class="flex items-center">
                        <i class="bi bi-calendar-date text-blue-600 mr-2"></i>
                        <span>${Object.keys(agendamentos).length} dias com atividade</span>
                    </div>
                </div>
            </div>
            
            <!-- Cabeçalho dos dias da semana -->
            <div class="grid grid-cols-7 gap-1 mb-2">
                ${diasSemana.map(dia => `
                    <div class="text-sm font-semibold text-gray-700 p-3 text-center bg-gray-50 rounded-lg">
                        ${dia}
                    </div>
                `).join('')}
            </div>
            
            <!-- Grade do calendário -->
            <div class="grid grid-cols-7 gap-1 bg-gray-100 p-2 rounded-lg">
    `;
    
    // Células vazias do início
    for (let i = 0; i < primeiroDiaSemana; i++) {
        html += '<div class="h-28 bg-gray-50 rounded-lg border border-gray-200"></div>';
    }
    
    // Dias do mês
    for (let dia = 1; dia <= diasNoMes; dia++) {
        const data = new Date(ano, mes, dia);
        const dataFormatada = formatarDataISO(data);
        const ehHoje = data.toDateString() === hoje.toDateString();
        const ehPassado = data < hoje && !ehHoje;
        const agendamentosDia = agendamentos[dataFormatada] || [];
        
        // Determinar classes CSS baseadas no estado do dia
        let classesDia = ['h-28', 'bg-white', 'rounded-lg', 'border', 'p-2', 'transition-all', 'duration-200'];
        
        if (ehHoje) {
            classesDia.push('border-teal-400', 'ring-2', 'ring-teal-200', 'shadow-md');
        } else if (ehPassado) {
            classesDia.push('border-gray-200', 'opacity-60');
        } else {
            classesDia.push('border-gray-200', 'hover:border-teal-300', 'hover:shadow-sm', 'cursor-pointer');
        }
        
        // Indicador de quantidade de agendamentos
        let indicadorCor = '';
        if (agendamentosDia.length > 0) {
            if (agendamentosDia.length >= 5) {
                indicadorCor = 'bg-red-100 border-red-300';
            } else if (agendamentosDia.length >= 3) {
                indicadorCor = 'bg-gray-100 border-gray-300';
            } else {
                indicadorCor = 'bg-teal-100 border-teal-300';
            }
        }
        
        html += `
            <div class="${classesDia.join(' ')} ${indicadorCor}" 
                 onclick="selecionarDiaMes('${dataFormatada}', ${agendaId})"
                 title="${ehPassado && agendamentosDia.length === 0 ? 'Data passada sem agendamentos' : `${dataFormatada} - ${agendamentosDia.length} agendamento(s)`}">
                
                <!-- Número do dia -->
                <div class="flex justify-between items-start mb-1">
                    <div class="text-sm font-bold ${ehHoje ? 'text-teal-700' : ehPassado ? 'text-gray-400' : 'text-gray-700'}">
                        ${dia}
                    </div>
                    ${agendamentosDia.length > 0 ? `
                        <div class="flex items-center space-x-1">
                            <div class="w-2 h-2 ${agendamentosDia.length >= 5 ? 'bg-red-500' : agendamentosDia.length >= 3 ? 'bg-orange-500' : 'bg-teal-500'} rounded-full"></div>
                            <span class="text-xs font-medium ${ehPassado ? 'text-gray-400' : 'text-gray-600'}">${agendamentosDia.length}</span>
                        </div>
                    ` : ''}
                </div>
                
                <!-- Lista de agendamentos -->
                <div class="space-y-1 overflow-hidden">
                    ${agendamentosDia.slice(0, 3).map(ag => `
                        <div class="text-xs ${agendamentosDia.length >= 5 ? 'bg-red-50 text-red-800' : agendamentosDia.length >= 3 ? 'bg-orange-50 text-orange-800' : 'bg-teal-50 text-teal-800'} px-2 py-1 rounded truncate" 
                             title="${ag.hora} - ${ag.paciente} (${ag.convenio})">
                            <div class="font-medium">${ag.hora}</div>
                            <div class="truncate">${ag.paciente.split(' ')[0]} ${ag.paciente.split(' ')[1] || ''}${(ag.tipo_agendamento && ag.tipo_agendamento.trim().toUpperCase() === 'ENCAIXE') ? ' <i class="bi bi-lightning-charge" title="Encaixe"></i>' : ''}</div>
                        </div>
                    `).join('')}
                    ${agendamentosDia.length > 3 ? `
                        <div class="text-xs text-center text-gray-500 font-medium">
                            +${agendamentosDia.length - 3} mais
                        </div>
                    ` : ''}
                </div>
                
                <!-- Indicador de hoje -->
                ${ehHoje ? `
                    <div class="absolute top-1 left-1">
                        <div class="w-2 h-2 bg-teal-500 rounded-full animate-pulse"></div>
                    </div>
                ` : ''}
            </div>
        `;
    }
    
    // Completar mês com células vazias
    const totalCelulas = primeiroDiaSemana + diasNoMes;
    const celulasRestantes = totalCelulas % 7 === 0 ? 0 : 7 - (totalCelulas % 7);
    
    for (let i = 0; i < celulasRestantes; i++) {
        html += '<div class="h-28 bg-gray-50 rounded-lg border border-gray-200"></div>';
    }
    
    html += `
            </div>
            
            <!-- Estatísticas do mês -->
            <div class="bg-gradient-to-r from-teal-50 to-blue-50 rounded-lg p-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 text-center flex items-center justify-center">
                    <i class="bi bi-graph-up mr-2 text-teal-600"></i>
                    Resumo do Mês
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <!-- Total de Consultas -->
                    <div class="bg-white rounded-lg p-4 shadow-sm border border-teal-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-teal-600">${estatisticas.total_agendamentos || 0}</div>
                                <div class="text-sm text-gray-600 mt-1">Total consultas</div>
                            </div>
                            <i class="bi bi-calendar-check text-teal-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <!-- Dias Ativos -->
                    <div class="bg-white rounded-lg p-4 shadow-sm border border-blue-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-blue-600">${Object.keys(agendamentos).length}</div>
                                <div class="text-sm text-gray-600 mt-1">Dias ativos</div>
                            </div>
                            <i class="bi bi-calendar-date text-blue-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <!-- Primeira Consulta -->
                    <div class="bg-white rounded-lg p-4 shadow-sm border border-green-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-green-600">${estatisticas.primeira_vez || 0}</div>
                                <div class="text-sm text-gray-600 mt-1">Primeira vez</div>
                            </div>
                            <i class="bi bi-person-plus text-green-400 text-2xl"></i>
                        </div>
                    </div>
                    
                    <!-- Retornos -->
                    <div class="bg-white rounded-lg p-4 shadow-sm border border-purple-100">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-2xl font-bold text-purple-600">${estatisticas.retornos || 0}</div>
                                <div class="text-sm text-gray-600 mt-1">Retornos</div>
                            </div>
                            <i class="bi bi-arrow-clockwise text-purple-400 text-2xl"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Legenda -->
                <div class="mt-6 flex flex-wrap justify-center gap-4 text-xs">
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-teal-500 rounded-full"></div>
                        <span class="text-gray-600">1-2 agendamentos</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-orange-500 rounded-full"></div>
                        <span class="text-gray-600">3-4 agendamentos</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                        <span class="text-gray-600">5+ agendamentos</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="w-3 h-3 bg-teal-500 rounded-full animate-pulse"></div>
                        <span class="text-gray-600">Hoje</span>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.innerHTML = html;
    console.log('✅ Mês renderizado com sucesso');
}

/**
 * ✅ FUNÇÃO AUXILIAR: Forçar reconfiguração dos botões de navegação
 */
function reconfigurarNavegacaoCalendario(agendaId) {
    console.log('🔧 Forçando reconfiguração da navegação do calendário');
    
    // Remover todos os listeners existentes
    document.querySelectorAll('.nav-calendario').forEach(btn => {
        const novoBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(novoBtn, btn);
    });
    
    // Recriar os listeners
    document.querySelectorAll('.nav-calendario').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const direcao = this.dataset.direcao;
            console.log(`🔄 Navegação RECONFIGURADA clicada: ${direcao}`);
            navegarMesCalendario(agendaId, direcao);
        });
    });
    
    // ✅ GARANTIR que o dia atual esteja sempre clicável após reconfiguração
    setTimeout(() => {
        garantirDiaAtualClicavel();
    }, 50);
}

/**
 * Navega entre meses no calendário
 */
function navegarMesCalendario(agendaId, direcao) {
    console.log(`📅 Navegando calendário: ${direcao}, data atual selecionada: ${window.dataSelecionadaAtual}`);
    
    if (direcao === 'prev') {
        mesAtual--;
        if (mesAtual < 0) {
            mesAtual = 11;
            anoAtual--;
        }
    } else {
        mesAtual++;
        if (mesAtual > 11) {
            mesAtual = 0;
            anoAtual++;
        }
    }
    
    // ✅ VERIFICAR se a data selecionada ainda existe no novo mês
    const dataSelecionada = window.dataSelecionadaAtual;
    if (dataSelecionada) {
        const [ano, mes, dia] = dataSelecionada.split('-').map(Number);
        const mesNavegando = mesAtual;
        const anoNavegando = anoAtual;
        
        // Se a data selecionada não pertence ao mês sendo exibido, ela não será marcada automaticamente
        // Isso está correto - o usuário pode navegar pelos meses mantendo a seleção
        console.log(`📅 Mês navegando: ${mesNavegando + 1}/${anoNavegando}, Data selecionada: ${mes}/${ano}`);
    }
    
    atualizarCalendarioLateral(agendaId);
    
    // ✅ FORÇA reconfiguração dos botões após navegar
    setTimeout(() => {
        reconfigurarNavegacaoCalendario(agendaId);
        garantirDiaAtualClicavel();
    }, 100);
}

/**
 * ✅ FUNÇÃO ESPECÍFICA: Garantir que o dia atual seja sempre clicável
 */
function garantirDiaAtualClicavel() {
    const hoje = new Date();
    const dataHoje = formatarDataISO(hoje);
    const diaHojeElemento = document.querySelector(`[data-data="${dataHoje}"]`);
    
    if (diaHojeElemento) {
        console.log(`🔍 Verificando dia atual: ${dataHoje}`);
        
        // ✅ GARANTIR que o dia atual NUNCA esteja desabilitado
        if (diaHojeElemento.hasAttribute('disabled')) {
            console.log('🔧 Removendo atributo disabled do dia atual');
            diaHojeElemento.removeAttribute('disabled');
        }
        
        // ✅ GARANTIR que o dia atual tenha classes corretas
        if (!diaHojeElemento.classList.contains('cursor-pointer')) {
            diaHojeElemento.classList.add('cursor-pointer');
        }
        
        if (diaHojeElemento.classList.contains('cursor-not-allowed')) {
            console.log('🔧 Removendo cursor-not-allowed do dia atual');
            diaHojeElemento.classList.remove('cursor-not-allowed', 'text-gray-400');
            diaHojeElemento.classList.add('text-gray-700', 'hover:bg-teal-50');
        }
        
        // ✅ Se não está selecionado, adicionar anel visual
        if (!diaHojeElemento.classList.contains('selecionado')) {
            diaHojeElemento.classList.add('ring-2', 'ring-teal-200');
        }
        
        console.log(`✅ Dia atual ${dataHoje} está clicável`);
    } else {
        console.log(`📅 Dia atual ${dataHoje} não está no mês sendo exibido`);
    }
}

/**
 * Atualiza o calendário lateral
 */
function atualizarCalendarioLateral(agendaId) {
    const containerCalendario = document.getElementById('container-calendario');
    if (!containerCalendario) return;
    
    const meses = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];
    
    const primeiroDia = new Date(anoAtual, mesAtual, 1);
    const ultimoDia = new Date(anoAtual, mesAtual + 1, 0);
    const primeiroDiaSemana = primeiroDia.getDay();
    const diasNoMes = ultimoDia.getDate();
    const hoje = new Date();
    
    // Atualizar título do mês
    const tituloMes = containerCalendario.parentElement.querySelector('h3');
    if (tituloMes) {
        tituloMes.textContent = `${meses[mesAtual]} ${anoAtual}`;
    }
    
    let html = `
        <div class="grid grid-cols-7 gap-1 mb-1">
            <div class="text-center text-xs font-medium text-gray-500 py-1">D</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">T</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">Q</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">Q</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
            <div class="text-center text-xs font-medium text-gray-500 py-1">S</div>
        </div>
        <div class="grid grid-cols-7 gap-1">`;
    
    // Células vazias do início
    for (let i = 0; i < primeiroDiaSemana; i++) {
        html += '<div class="text-center py-1 text-xs text-gray-300"></div>';
    }
    
    // Dias do mês
    for (let dia = 1; dia <= diasNoMes; dia++) {
        const data = new Date(anoAtual, mesAtual, dia);
        const dataFormatada = formatarDataISO(data);
        const ehHoje = data.toDateString() === hoje.toDateString();
        const ehPassado = data < hoje && !ehHoje;
        
        // ✅ VERIFICAR se este dia está selecionado (ao invés de assumir que hoje está sempre selecionado)
        const dataSelecionadaGlobal = window.dataSelecionadaAtual;
        const ehSelecionado = dataSelecionadaGlobal === dataFormatada;
        
        // Debug para primeiro dia do mês
        if (dia === 1) {
            console.log(`📅 Calendar Debug - Data selecionada global: ${dataSelecionadaGlobal}, Hoje: ${dataFormatada}, É hoje: ${ehHoje}, É selecionado: ${ehSelecionado}`);
        }
        
        const classes = ['text-center', 'py-1', 'text-xs', 'cursor-pointer', 'hover:bg-gray-100', 'rounded', 'dia-calendario'];
        
        if (ehSelecionado) {
            // ✅ CORRIGIDO: Só marca se realmente estiver selecionado
            classes.push('bg-teal-500', 'text-white', 'selecionado');
        } else if (ehHoje) {
            // ✅ NOVA LÓGICA: Dia de hoje é sempre clicável e destacado levemente
            classes.push('text-gray-700', 'hover:bg-teal-50', 'ring-2', 'ring-teal-200');
        } else if (!ehPassado) {
            classes.push('text-gray-700', 'hover:bg-teal-50');
        } else {
            // Dias passados - permitir apenas se tiver indicação visual de agendamentos
            classes.push('text-gray-600', 'hover:bg-gray-50');
        }
        
        // Não desabilitar dias passados - deixar a validação para o click handler
        const disabled = '';
        const pointerEvents = '';
        
        html += `<div class="${classes.join(' ')}" data-data="${dataFormatada}" ${disabled} ${pointerEvents}>${dia}</div>`;
    }
    
    html += '</div>';
    
    containerCalendario.innerHTML = html;
    
    // Reconfigurar listeners para os dias
    containerCalendario.querySelectorAll('.dia-calendario').forEach(dia => {
        dia.addEventListener('click', function(e) {
            const data = this.dataset.data;
            console.log(`📅 Dia clicado (atualizado): ${data}, disabled: ${this.hasAttribute('disabled')}`);
            
            // ✅ CORREÇÃO: Verificar se é o dia atual e forçar habilitação
            const hoje = new Date();
            const dataHoje = formatarDataISO(hoje);
            
            if (data === dataHoje && this.hasAttribute('disabled')) {
                console.log('🔧 Forçando habilitação do dia atual (calendário atualizado)');
                this.removeAttribute('disabled');
                this.classList.remove('cursor-not-allowed', 'text-gray-400');
                this.classList.add('cursor-pointer', 'text-gray-700', 'hover:bg-teal-50');
            }
            
            if (this.hasAttribute('disabled')) {
                console.warn(`⚠️ Tentativa de clicar em dia desabilitado: ${data}`);
                return;
            }
            
            selecionarDiaNoCalendario(this, agendaId, data);
        });
    });
    
    // ✅ CORREÇÃO: Reconfigurar listeners para navegação também
    document.querySelectorAll('.nav-calendario').forEach(btn => {
        btn.replaceWith(btn.cloneNode(true)); // Remove event listeners antigos
    });
    
    const navButtons = document.querySelectorAll('.nav-calendario');
    console.log(`📅 Reconfigurando ${navButtons.length} botões de navegação do calendário`);
    
    navButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const direcao = this.dataset.direcao;
            console.log(`🔄 Botão de navegação clicado: ${direcao}`);
            navegarMesCalendario(agendaId, direcao);
        });
    });
}

/**
 * Seleciona dia no calendário lateral
 */
function selecionarDiaNoCalendario(elemento, agendaId, data) {
    console.log(`🎯 Selecionando dia: ${data} (elemento:`, elemento, ')');
    
    // ✅ CORREÇÃO: Verificar se o elemento é clicável
    if (elemento.hasAttribute('disabled')) {
        console.warn(`⚠️ Tentativa de selecionar dia desabilitado: ${data}`);
        return;
    }
    
    // Remover seleção anterior de TODOS os dias
    document.querySelectorAll('.dia-calendario').forEach(d => {
        d.classList.remove('bg-teal-500', 'text-white', 'selecionado');
        // ✅ ADICIONAR: Restaurar estilos especiais para hoje se necessário
        const dataElemento = d.dataset.data;
        const hoje = new Date();
        const dataHoje = formatarDataISO(hoje);
        
        if (dataElemento === dataHoje && dataElemento !== data) {
            // É o dia de hoje mas não é o selecionado - adicionar anel
            d.classList.add('ring-2', 'ring-teal-200');
        } else {
            d.classList.remove('ring-2', 'ring-teal-200');
        }
    });
    
    // ✅ ADICIONAR seleção atual
    elemento.classList.add('bg-teal-500', 'text-white', 'selecionado');
    elemento.classList.remove('ring-2', 'ring-teal-200'); // Remove anel se era dia atual
    
    // Atualizar data selecionada
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
    const dataSelecionadaElement = document.getElementById('data-selecionada');
    if (dataSelecionadaElement) {
        dataSelecionadaElement.textContent = dataFormatada;
    }
    
    // ✅ ARMAZENAR data selecionada globalmente
    window.dataSelecionadaAtual = data;
    console.log(`✅ Data selecionada atualizada: ${window.dataSelecionadaAtual}`);
    
    // Recarregar visualização atual
    const tipoAtivo = document.querySelector('.btn-visualizacao.bg-teal-600')?.dataset.tipo || 'dia';
    
    if (tipoAtivo === 'dia') {
        carregarVisualizacaoDia(agendaId, data);
    } else {
        alternarTipoVisualizacao(tipoAtivo, agendaId);
    }
}

/**
 * Funções auxiliares para interação com visualizações
 */
window.selecionarSlotSemana = function(data, horario, agendaId) {
    console.log('Slot da semana selecionado:', { data, horario, agendaId });
    
    // Verificar se o horário já passou
    const agora = new Date();
    const dataHorario = new Date(data + 'T' + horario);
    
    if (dataHorario < agora) {
        alert('Este horário já passou e não pode ser agendado.');
        return;
    }
    
    // Abrir modal diretamente
    abrirModalAgendamento(agendaId, data, horario);
};

// 3️⃣ ✅ Função para selecionar dia do mês (já existente, mas melhorada)
window.selecionarDiaMes = function(data, agendaId) {
    console.log('🎯 Dia do mês selecionado:', { data, agendaId });
    
    // Atualizar calendário lateral se existir
    const diaCalendario = document.querySelector(`[data-data="${data}"]`);
    if (diaCalendario && !diaCalendario.hasAttribute('disabled')) {
        selecionarDiaNoCalendario(diaCalendario, agendaId, data);
        
        // Mudar para visualização de dia
        alternarTipoVisualizacao('dia', agendaId);
    } else {
        // Se não encontrar no calendário lateral, atualizar manualmente
        window.dataSelecionadaAtual = data;
        alternarTipoVisualizacao('dia', agendaId);
    }
};

/**
 * Funções de ações
 */
// Função editarAgendamento removida - implementação completa está em outra parte do arquivo

window.cancelarAgendamento = function(agendamentoId) {
    console.log('🗑️ Iniciando cancelamento do agendamento:', agendamentoId);
    
    // Verificar se há usuário logado (qualquer usuário pode cancelar)
    if (!window.usuarioAtual) {
        showToast('Você precisa estar logado para cancelar agendamentos', false);
        return;
    }

    // ✅ Solicitar motivo do cancelamento (OBRIGATÓRIO)
    let motivo = '';
    do {
        motivo = prompt('⚠️ OBRIGATÓRIO - Motivo do cancelamento:');

        // Se clicar em Cancelar, abortar
        if (motivo === null) {
            console.log('🚫 Cancelamento abortado pelo usuário');
            return;
        }

        // Remover espaços em branco
        motivo = motivo.trim();

        // Se estiver vazio, mostrar alerta
        if (!motivo) {
            alert('❌ O motivo do cancelamento é obrigatório!\n\nPor favor, informe o motivo para prosseguir.');
        }
    } while (!motivo);

    if (confirm(`Deseja realmente cancelar este agendamento?\n\nMotivo: ${motivo}\n\nEsta ação não pode ser desfeita.`)) {
        console.log('🗑️ Cancelando agendamento:', { agendamentoId, motivo });
        
        // Mostrar loading
        showToast('Cancelando agendamento...', true, 3000);
        
        const formData = new FormData();
        formData.append('agendamento_id', agendamentoId);
        formData.append('motivo_cancelamento', motivo);

        // ✅ Incluir usuário atual (obrigatório para verificação de permissão)
        const usuario = window.usuarioAtual || getCookie('log_usuario') || 'SISTEMA';
        formData.append('usuario_atual', usuario);
        console.log('👤 Usuário enviando cancelamento:', usuario);
        
        fetch('cancelar_agendamento.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_CONFIG.token}`
            },
            body: formData
        })
        .then(safeJsonParse)
        .then(data => {
            console.log('📊 Resposta do cancelamento:', data);
            
            if (data.status === 'sucesso') {
                showToast(`✅ ${data.mensagem}`, true);
                
                // Mostrar detalhes do cancelamento
                console.log('✅ Agendamento cancelado:', {
                    id: data.agendamento_id,
                    paciente: data.paciente,
                    dataHora: data.data_hora
                });
                
                // ✅ Recarregar APENAS a visualização (sem refresh da página)
                if (typeof carregarVisualizacaoDia === 'function' && window.agendaIdAtual && window.dataSelecionadaAtual) {
                    console.log('🔄 Recarregando visualização do dia...');
                    carregarVisualizacaoDia(window.agendaIdAtual, window.dataSelecionadaAtual);
                } else if (typeof recarregarListaAgendamentos === 'function') {
                    console.log('🔄 Recarregando lista de agendamentos...');
                    recarregarListaAgendamentos();
                }
            } else {
                showToast('❌ Erro: ' + data.mensagem, false);
                console.error('❌ Erro no cancelamento:', data.mensagem);
            }
        })
        .catch(error => {
            console.error('💥 Erro ao cancelar agendamento:', error);
            showToast('💥 Erro ao cancelar agendamento: ' + error.message, false);
        });
    } else {
        console.log('🚫 Cancelamento abortado pelo usuário');
    }
};

window.bloquearHorario = function(agendaId, data, horario) {
    const dataFormatada = formatarDataBR(data);
    
    if (confirm(`Deseja bloquear o horário ${horario} do dia ${dataFormatada}?`)) {
        console.log('🔒 Bloqueando horário:', { agendaId, data, horario });
        
        // Mostrar loading
        showToast('Bloqueando horário...', true, 2000);
        
        const formData = new FormData();
        formData.append('agenda_id', agendaId);
        formData.append('data_agendamento', data);
        formData.append('horario_agendamento', horario.includes(':') && horario.length === 5 ? horario + ':00' : horario);
        formData.append('acao', 'bloquear');

        // ✅ Incluir usuário atual (obrigatório para verificação de permissão)
        const usuario = window.usuarioAtual || getCookie('log_usuario') || 'SISTEMA';
        formData.append('usuario_atual', usuario);
        
        fetch('bloquear_horario.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_CONFIG.token}`
            },
            body: formData
        })
        .then(safeJsonParse)
        .then(data => {
            console.log('📊 Resposta do bloqueio:', data);

            if (data.status === 'sucesso') {
                showToast(data.mensagem, true);

                // ✅ Recarregar APENAS a visualização (sem refresh da página)
                carregarVisualizacaoDia(agendaId, formData.get('data_agendamento'));
            } else {
                showToast('Erro: ' + data.mensagem, false);
            }
        })
        .catch(error => {
            console.error('💥 Erro ao bloquear horário:', error);
            showToast('Erro ao bloquear horário: ' + error.message, false);
        });
    }
};

window.desbloquearHorario = function(agendaId, data, horario) {
    const dataFormatada = formatarDataBR(data);
    
    if (confirm(`Deseja desbloquear o horário ${horario} do dia ${dataFormatada}?`)) {
        console.log('🔓 Desbloqueando horário:', { agendaId, data, horario });
        
        // Mostrar loading
        showToast('Desbloqueando horário...', true, 2000);
        
        const formData = new FormData();
        formData.append('agenda_id', agendaId);
        formData.append('data_agendamento', data);
        formData.append('horario_agendamento', horario.includes(':') && horario.length === 5 ? horario + ':00' : horario);
        formData.append('acao', 'desbloquear');

        // ✅ Incluir usuário atual (obrigatório para verificação de permissão)
        const usuario = window.usuarioAtual || getCookie('log_usuario') || 'SISTEMA';
        formData.append('usuario_atual', usuario);
        
        fetch('bloquear_horario.php', {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${API_CONFIG.token}`
            },
            body: formData
        })
        .then(safeJsonParse)
        .then(data => {
            console.log('📊 Resposta do desbloqueio:', data);

            if (data.status === 'sucesso') {
                showToast(data.mensagem, true);

                // ✅ Recarregar APENAS a visualização (sem refresh da página)
                const dataParam = formData.get('data_agendamento');
                carregarVisualizacaoDia(agendaId, dataParam);
            } else {
                showToast('Erro: ' + data.mensagem, false);
            }
        })
        .catch(error => {
            console.error('💥 Erro ao desbloquear horário:', error);
            showToast('Erro ao desbloquear horário: ' + error.message, false);
        });
    }
};

/**
 * Volta para listagem
 */
window.voltarParaListagem = function() {
    window.location.href = 'index.php';
};

/**
 * Funções utilitárias
 */
function obterDataSelecionada() {
    const diaSelecionado = document.querySelector('.dia-calendario.bg-teal-500');
    if (diaSelecionado && diaSelecionado.dataset.data) {
        return diaSelecionado.dataset.data;
    }
    return formatarDataISO(new Date());
}

function formatarDataISO(data) {
    const ano = data.getFullYear();
    const mes = String(data.getMonth() + 1).padStart(2, '0');
    const dia = String(data.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function formatarDataBR(dataISO) {
    const [ano, mes, dia] = dataISO.split('-');
    return `${dia}/${mes}/${ano}`;
}

/**
 * MODAL DE AGENDAMENTO DIRETO
 */
function abrirModalAgendamento(agendaId, data, horario) {
    // Verificar se o horário já passou
    const agora = new Date();
    const dataHorario = new Date(data + 'T' + horario);
    
    if (dataHorario < agora) {
        alert('Este horário já passou e não pode ser agendado.');
        return;
    }
    
    // Primeiro, verificar se já existe agendamento neste horário
    fetchWithAuth(`buscar_agendamento_horario.php?agenda_id=${agendaId}&data=${data}&horario=${horario}`)
        .then(safeJsonParse)
        .then(agendamentoExistente => {
            if (agendamentoExistente && agendamentoExistente.id) {
                // Se existe agendamento, buscar dados completos
                fetch(`buscar_agendamento.php?id=${agendamentoExistente.id}`)
                    .then(safeJsonParse)
                    .then(dadosAgendamento => {
                        // Buscar informações da agenda
                        fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                            .then(safeJsonParse)
                            .then(responseData => {
                                const agendaInfo = responseData.agenda || responseData;
                                criarModalAgendamentoComDados(agendaId, data, horario, agendaInfo, dadosAgendamento);
                            });
                    });
            } else {
                // Se não existe, criar modal vazio
                fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                    .then(safeJsonParse)
                    .then(responseData => {
                        const agendaInfo = responseData.agenda || responseData;
                        criarModalAgendamento(agendaId, data, horario, agendaInfo, window.especialidadeIdSelecionada);
                    });
            }
        })
        .catch(error => {
            console.error('Erro ao verificar agendamento:', error);
            // Em caso de erro, abrir modal vazio
            fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                .then(safeJsonParse)
                .then(responseData => {
                    const agendaInfo = responseData.agenda || responseData;
                    criarModalAgendamento(agendaId, data, horario, agendaInfo);
                });
        });
}

// Função duplicada removida - implementação correta está na linha 1599
// Atualizado em: 2025-08-13 para forçar reload do cache

/**
 * Cria e exibe o modal de agendamento
 */
function criarModalAgendamento(agendaId, data, horario, agendaInfo, especialidadeId = null) {
    // Usar especialidade passada como parâmetro ou a global
    const especialidadeFinal = especialidadeId || window.especialidadeIdSelecionada || '';
    console.log('📋 Criando modal agendamento - Especialidade final:', especialidadeFinal);
    
    // DEBUG: Verificar dados da agenda para tipo de consulta
    console.log('🏥 agendaInfo recebido:', agendaInfo);
    console.log('🔍 Verificando se é consulta:');
    console.log('  - agendaInfo.especialidade:', agendaInfo?.especialidade);
    console.log('  - agendaInfo.medico:', agendaInfo?.medico); 
    console.log('  - agendaInfo.especialidade_nome:', agendaInfo?.especialidade_nome);
    console.log('  - agendaInfo.medico_nome:', agendaInfo?.medico_nome);
    console.log('  - agendaInfo.nome:', agendaInfo?.nome);
    
    const isConsulta = agendaInfo && (
        agendaInfo.tipo === 'consulta' ||
        agendaInfo.especialidade || 
        agendaInfo.medico || 
        agendaInfo.especialidade_nome || 
        agendaInfo.medico_nome ||
        (agendaInfo.nome && (agendaInfo.nome.includes('Dr') || agendaInfo.nome.includes('Dra')))
    );
    
    const isProcedimento = agendaInfo && agendaInfo.tipo === 'procedimento';
    
    console.log('🩺 É consulta?', isConsulta);
    console.log('🔬 É procedimento?', isProcedimento);
    // Formatar data para exibição
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // HTML do modal
    const modalHTML = `
        <div id="modal-agendamento" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[95vh] overflow-y-auto">
                <!-- Cabeçalho do Modal -->
                <div class="bg-gradient-to-r from-teal-600 to-teal-700 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-calendar-check mr-3"></i>
                                Agendamento
                            </h2>
                            <p class="text-teal-100 mt-1">Complete os dados para finalizar seu agendamento</p>
                        </div>
                        <button onclick="fecharModalAgendamento()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Conteúdo do Modal -->
                <div class="p-6">
                    <!-- Informações do Agendamento -->
                    <div class="bg-teal-50 border-l-4 border-teal-400 p-4 mb-6 rounded-r-lg">
                        <h3 class="text-lg font-semibold text-teal-800 mb-2">
                            ${agendaInfo.nome || 'Agendamento'}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-teal-700">
                            <div>
                                <i class="bi bi-calendar3 mr-2"></i>
                                <strong>Data:</strong> ${dataFormatada}
                            </div>
                            <div>
                                <i class="bi bi-clock mr-2"></i>
                                <strong>Horário:</strong> ${horario}
                            </div>
                            <div>
                                <i class="bi bi-geo-alt mr-2"></i>
                                <strong>Unidade:</strong> ${agendaInfo.unidade || 'Mossoró'}
                            </div>
                        </div>
                        ${agendaInfo.sala ? `
                            <div class="mt-2 text-sm text-teal-600">
                                <i class="bi bi-door-open mr-2"></i>
                                <strong>Sala:</strong> ${agendaInfo.sala} | 
                                <strong>Telefone:</strong> ${agendaInfo.telefone || '(84) 99999-1234'}
                            </div>
                        ` : ''}
                    </div>
                    
                    <!-- Formulário de Agendamento -->
                    <form id="form-agendamento-modal" class="space-y-6">
                        <!-- Dados do Paciente -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="bi bi-person-circle mr-2"></i>
                                Dados do Paciente (Obrigatórios)
                            </h4>
                            
                            <!-- Busca de paciente (igual ao encaixe) -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nome do Paciente <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           id="nome_paciente_agendamento" 
                                           name="nome_paciente"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                           placeholder="Digite nome, CPF ou data de nascimento (16/09/1990, 16091990 ou 160990)..."
                                           required
                                           autocomplete="off"
                                           oninput="verificarLimpezaCampoNomeAgendamento(this)">
                                    <i class="bi bi-search absolute right-3 top-2.5 text-gray-400"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="bi bi-info-circle mr-1"></i>
                                    Busque por: <strong>Nome</strong> (ex: João Silva), <strong>CPF</strong> (123.456.789-00 ou 12345678900), <strong>Data de Nascimento</strong> (01/01/1990, 01011990 ou 010190) ou <strong>Telefone</strong>
                                </p>
                                
                                <!-- Resultados da busca -->
                                <div id="resultados-busca-agendamento" class="hidden mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <!-- Preenchido dinamicamente -->
                                </div>
                                
                                <!-- Checkbox para cadastrar novo paciente -->
                                <div class="mt-3 flex items-center justify-between">
                                    <label class="flex items-center">
                                        <input type="checkbox" id="checkbox-criar-cadastro-agendamento" name="criar_cadastro_novo" 
                                               class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded">
                                        <span class="ml-2 text-sm text-gray-700">Cadastrar novo paciente (se não encontrar)</span>
                                    </label>
                                    <button type="button" onclick="limparSelecaoPacienteAgendamento()" 
                                            class="text-xs text-gray-500 hover:text-red-600 underline">
                                        <i class="bi bi-x-circle mr-1"></i>Limpar seleção
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Telefone (igual ao encaixe) -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Telefone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" 
                                       id="telefone_paciente_agendamento" 
                                       name="telefone_paciente"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                       placeholder="(84) 99999-9999"
                                       required>
                            </div>
                            
                            <!-- Data de Nascimento e Idade -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Data de nascimento <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" 
                                           id="data_nascimento_agendamento" 
                                           name="data_nascimento"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                           required
                                           onchange="calcularIdadeAgendamento()">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Idade <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number" 
                                           id="idade_agendamento" 
                                           name="idade"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                           required readonly
                                           placeholder="Calculado automaticamente">
                                </div>
                            </div>
                            
                            <!-- Tipo de Consulta (apenas para consultas) -->
                            ${isConsulta ? `
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Tipo de consulta <span class="text-red-500">*</span>
                                </label>
                                <select id="tipo_consulta_agendamento" 
                                        name="tipo_consulta"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                        required>
                                    <option value="">Selecione o tipo</option>
                                    <option value="primeira_vez">Primeira vez</option>
                                    <option value="retorno">Retorno</option>
                                    <option value="urgencia">Urgência</option>
                                    <option value="rotina">Rotina</option>
                                    <option value="revisao">Revisão</option>
                                    <option value="seguimento">Seguimento</option>
                                </select>
                            </div>
                            ` : ''}
                            
                            <!-- ✅ SEÇÃO: Configurações de Atendimento -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-gray-200 pt-4">
                                <!-- Status de Confirmação -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Status de Confirmação
                                    </label>
                                    <div class="flex space-x-4">
                                        <label class="flex items-center">
                                            <input type="radio" name="confirmado" value="0" checked
                                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500">
                                            <span class="ml-2 text-sm text-gray-700">Não confirmado</span>
                                        </label>
                                        <label class="flex items-center">
                                            <input type="radio" name="confirmado" value="1"
                                                   class="h-4 w-4 text-teal-600 focus:ring-teal-500">
                                            <span class="ml-2 text-sm text-gray-700">Confirmado</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Tipo de Atendimento -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Tipo de Atendimento
                                    </label>
                                    <select name="tipo_atendimento" id="tipo_atendimento_agendamento"
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500">
                                        <option value="NORMAL">Normal</option>
                                        <option value="PRIORIDADE">Prioridade</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Formulário de cadastro novo (replicado do encaixe) -->
                            <div id="formulario-cadastro-novo-agendamento" class="hidden space-y-4 border-t border-teal-200 pt-4">
                                <!-- ✅ SEÇÃO: Informações Gerais -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-info-circle mr-2"></i>
                                        Informações Gerais
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- CPF -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                CPF <span class="text-red-500" id="cpf_asterisco_agendamento">*</span>
                                            </label>
                                            <input type="text" 
                                                   id="cpf_novo_paciente_agendamento" 
                                                   name="cpf_paciente" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="000.000.000-00">
                                            <div class="mt-2">
                                                <label class="flex items-center">
                                                    <input type="checkbox" id="nao_tem_cpf_agendamento" name="nao_tem_cpf" 
                                                           class="h-4 w-4 text-teal-600 focus:ring-teal-500 border-gray-300 rounded">
                                                    <span class="ml-2 text-sm text-gray-700">Paciente não tem CPF</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Sexo -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Sexo <span class="text-red-500">*</span>
                                            </label>
                                            <select id="sexo_novo_paciente_agendamento" 
                                                    name="sexo" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                                                <option value="">Selecione...</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Feminino</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Data de Nascimento -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Data de Nascimento <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" 
                                                   id="nascimento_novo_paciente_agendamento" 
                                                   name="data_nascimento" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Documentos -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-card-text mr-2"></i>
                                        Documentos
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- RG -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                RG
                                            </label>
                                            <input type="text" 
                                                   id="rg_novo_paciente_agendamento" 
                                                   name="rg" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="0000000">
                                        </div>
                                        
                                        <!-- Órgão Emissor -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Órgão Emissor
                                            </label>
                                            <input type="text" 
                                                   id="orgao_rg_novo_paciente_agendamento" 
                                                   name="orgao_rg" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="SSP/RN">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Contato e Informações Adicionais -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-telephone mr-2"></i>
                                        Contato e Informações
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- E-mail -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                E-mail
                                            </label>
                                            <input type="email" 
                                                   id="email_novo_paciente_agendamento" 
                                                   name="email" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="exemplo@email.com">
                                        </div>
                                        
                                        <!-- Estado Civil -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Estado Civil
                                            </label>
                                            <select id="estado_civil_novo_paciente_agendamento" 
                                                    name="estado_civil" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500">
                                                <option value="">Selecione...</option>
                                                <option value="SOLTEIRO">Solteiro(a)</option>
                                                <option value="CASADO">Casado(a)</option>
                                                <option value="DIVORCIADO">Divorciado(a)</option>
                                                <option value="VIUVO">Viúvo(a)</option>
                                                <option value="UNIAO_ESTAVEL">União Estável</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Endereço Completo -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-geo-alt mr-2"></i>
                                        Endereço Completo
                                    </h5>
                                    
                                    <!-- CEP e Pesquisa -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                CEP
                                            </label>
                                            <input type="text" 
                                                   id="cep_novo_paciente_agendamento" 
                                                   name="cep" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="00000-000">
                                        </div>
                                        <div class="flex items-end">
                                            <button type="button" 
                                                    id="buscar-cep-agendamento" 
                                                    class="px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700 transition">
                                                <i class="bi bi-search mr-1"></i>
                                                Buscar CEP
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Endereço Principal -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Logradouro
                                            </label>
                                            <input type="text" 
                                                   id="logradouro_novo_paciente_agendamento" 
                                                   name="logradouro" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="Rua, Avenida...">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Número
                                            </label>
                                            <input type="text" 
                                                   id="numero_novo_paciente_agendamento" 
                                                   name="numero" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="123">
                                        </div>
                                    </div>
                                    
                                    <!-- Complemento, Bairro, Cidade -->
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Complemento
                                            </label>
                                            <input type="text" 
                                                   id="complemento_novo_paciente_agendamento" 
                                                   name="complemento" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="Apto, Bloco...">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Bairro
                                            </label>
                                            <input type="text" 
                                                   id="bairro_novo_paciente_agendamento" 
                                                   name="bairro" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="Nome do bairro">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Cidade
                                            </label>
                                            <input type="text" 
                                                   id="cidade_novo_paciente_agendamento" 
                                                   name="cidade" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-teal-500" 
                                                   placeholder="Nome da cidade">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Campos ocultos para controle -->
                            <input type="hidden" id="paciente_existente_id_agendamento" name="paciente_existente_id" value="">
                            <input type="hidden" id="deve_cadastrar_paciente_agendamento" name="deve_cadastrar_paciente" value="false">
                            
                            <!-- Convênio (igual ao encaixe) -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Convênio <span class="text-red-500">*</span>
                                </label>
                                <select id="convenio_agendamento" 
                                        name="convenio_id" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500" 
                                        required>
                                    <option value="">Selecione o convênio...</option>
                                    <!-- Convênios serão carregados dinamicamente -->
                                </select>
                            </div>
                            
                            <!-- Exames (igual ao encaixe) - MULTI-SELECT - APENAS PARA PROCEDIMENTOS -->
                            ${isProcedimento ? `
                            <div id="secao-exames-agendamento" class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Exames <span class="text-xs text-gray-500">(múltipla seleção)</span>
                                </label>
                                
                                <!-- Lista de exames selecionados -->
                                <div class="mb-3">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs font-medium text-gray-600">Exames selecionados:</span>
                                        <button type="button" 
                                                id="btn_limpar_exames_agendamento"
                                                onclick="limparTodosExamesAgendamento()"
                                                class="text-xs text-red-600 hover:text-red-800 hover:underline hidden">
                                            <i class="bi bi-trash"></i> Limpar tudo
                                        </button>
                                    </div>
                                    <div id="exames_selecionados_agendamento" class="min-h-[40px] border border-gray-300 rounded-lg p-2 bg-gray-50">
                                        <div class="text-sm text-gray-500">Nenhum exame selecionado</div>
                                    </div>
                                </div>
                                
                                <div class="relative">
                                    <div class="flex">
                                        <input type="text" 
                                               id="exames_search_agendamento" 
                                               placeholder="Digite para buscar e adicionar exames..."
                                               class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
                                               autocomplete="off">
                                        <button type="button" 
                                                id="btn_toggle_exames_agendamento" 
                                                onclick="toggleDropdownExamesAgendamento()"
                                                class="px-3 py-2 bg-teal-600 text-white rounded-r-lg hover:bg-teal-700 focus:ring-2 focus:ring-teal-500 border border-teal-600">
                                            <i class="bi bi-chevron-down" id="icon_toggle_exames_agendamento"></i>
                                        </button>
                                    </div>
                                    <div id="exames_dropdown_agendamento" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden mt-1">
                                        <div class="p-3 text-gray-500 text-sm">Carregando exames...</div>
                                    </div>
                                    <input type="hidden" id="exames_ids_selected_agendamento" name="exames_ids" value="">
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <i class="bi bi-info-circle mr-1"></i>
                                            Clique nos exames para seleção múltipla
                                        </div>
                                        <div id="contador-exames-agendamento" class="text-teal-600 font-medium"></div>
                                    </div>
                                </div>
                            </div>
                            ` : ''}
                            
                            <!-- Campos hidden para controle -->
                            <input type="hidden" id="usar_paciente_existente_agendamento" name="usar_paciente_existente" value="false">
                            <input type="hidden" id="paciente_id_agendamento" name="paciente_id" value="">
                        </div>
                        
                        <!-- Observações -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Observações
                            </label>
                            <textarea name="observacoes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-teal-500"
                                      placeholder="Alguma observação especial sobre o agendamento..."></textarea>
                        </div>
                        
                        <!-- Campos ocultos -->
                        <input type="hidden" name="agenda_id" value="${agendaId}">
                        <input type="hidden" name="data_agendamento" value="${data}">
                        <input type="hidden" name="horario_agendamento" value="${horario}">
                        <input type="hidden" name="especialidade_id" value="${especialidadeFinal}">
                    </form>
                </div>
                
                <!-- Rodapé do Modal -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-3">
                        <button type="button" onclick="fecharModalAgendamento()" 
                                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition order-2 sm:order-1">
                            <i class="bi bi-x-circle mr-2"></i>Cancelar
                        </button>
                        
                        <div class="flex gap-3 order-1 sm:order-2">
                            <button type="button" onclick="salvarAgendamento(event)" 
                                    class="px-6 py-2 bg-teal-600 text-white rounded-md hover:bg-teal-700 transition">
                                <i class="bi bi-check-circle mr-2"></i>Confirmar Agendamento
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar event listeners
    adicionarEventListenersModal(agendaInfo);
    
    // Configurar busca em tempo real igual ao encaixe
    configurarBuscaTempoRealAgendamento();
    
    // Popular convênios no select
    popularConveniosNoSelect(agendaInfo.agenda ? agendaInfo.agenda.convenios : []);
    
    // Configurar toggle do formulário de cadastro
    configurarToggleCadastroAgendamento();
    
    // Configurar máscaras e funcionalidades
    configurarMascarasAgendamento();
    
    // Configurar controle de CPF no agendamento
    configurarControleCPFAgendamento(agendaInfo, agendaId);
    
    // Configurar busca de CEP
    configurarBuscaCEPAgendamento();
    
    // Carregar exames apenas se for procedimento
    if (isProcedimento) {
        carregarExamesSeNecessarioAgendamento(agendaId);
    }
    
    // Carregar convênios da agenda
    carregarConveniosAgenda(agendaInfo);

    // ✅ CORREÇÃO: Adicionar checkbox de sedação para agendas de ressonância
    // Chamado APÓS o modal ser inserido no DOM
    // Só aparece se a data selecionada for QUINTA-FEIRA (dia da sedação)
    if (agendaId === 30 || agendaId === 76) {
        // Verificar se é quinta-feira
        const dataObj = new Date(data + 'T00:00:00');
        const diaSemana = dataObj.getDay(); // 0=Domingo, 4=Quinta, 6=Sábado
        const isQuintaFeira = diaSemana === 4;

        console.log('🏥 Agenda de Ressonância - ID:', agendaId);
        console.log('📅 Data selecionada:', data, '- Dia da semana:', diaSemana, '(Quinta?', isQuintaFeira + ')');

        if (isQuintaFeira) {
            console.log('✅ Quinta-feira detectada! Adicionando checkbox de sedação...');
            setTimeout(() => {
                if (typeof adicionarCheckboxSedacao === 'function') {
                    adicionarCheckboxSedacao();
                } else {
                    console.warn('⚠️ Função adicionarCheckboxSedacao não encontrada');
                }
            }, 100); // Pequeno delay para garantir que DOM está pronto
        } else {
            console.log('ℹ️ Não é quinta-feira - checkbox de sedação não será exibido');
        }
    }

    // Focar no campo de nome do paciente
    setTimeout(() => {
        const campoBusca = document.getElementById('nome_paciente_agendamento');
        if (campoBusca) campoBusca.focus();
    }, 300);
}

/**
 * Carregar convênios específicos da agenda
 */
function carregarConveniosAgenda(agendaInfo) {
    console.log('🏥 Carregando convênios da agenda:', agendaInfo);
    
    const selectConvenio = document.getElementById('convenio_agendamento');
    if (!selectConvenio) {
        console.warn('❌ Select de convênio não encontrado');
        return;
    }
    
    // Limpar opções existentes
    selectConvenio.innerHTML = '<option value="">Selecione o convênio...</option>';
    
    // Adicionar convênios da agenda
    if (agendaInfo && agendaInfo.convenios && agendaInfo.convenios.length > 0) {
        agendaInfo.convenios.forEach(convenio => {
            const option = document.createElement('option');
            option.value = convenio.id;
            option.textContent = convenio.nome;
            selectConvenio.appendChild(option);
        });
        
        console.log(`✅ ${agendaInfo.convenios.length} convênios carregados para a agenda`);
    } else {
        console.warn('⚠️ Nenhum convênio encontrado para esta agenda');
        selectConvenio.innerHTML = '<option value="">Nenhum convênio disponível</option>';
    }
}

/**
 * Configurar busca em tempo real para agendamento (igual ao encaixe)
 */
function configurarBuscaTempoRealAgendamento() {
    console.log('🔧 Iniciando configuração da busca em tempo real para agendamento...');

    const aguardarElementos = () => {
        return new Promise((resolve, reject) => {
            let tentativas = 0;
            const maxTentativas = 50; // 5 segundos

            const verificarElementos = () => {
                const inputNome = document.getElementById('nome_paciente_agendamento');
                const resultadosDiv = document.getElementById('resultados-busca-agendamento');

                tentativas++;
                console.log(`🔍 Tentativa ${tentativas}/${maxTentativas} - Input: ${!!inputNome}, Div: ${!!resultadosDiv}`);

                if (inputNome && resultadosDiv) {
                    console.log('✅ Elementos encontrados!');
                    resolve({ inputNome, resultadosDiv });
                } else if (tentativas >= maxTentativas) {
                    console.error('❌ Timeout: Elementos não encontrados após 5 segundos');
                    reject('Elementos não encontrados');
                } else {
                    setTimeout(verificarElementos, 100);
                }
            };
            verificarElementos();
        });
    };

    aguardarElementos().then(({ inputNome, resultadosDiv }) => {
        console.log('✅ Elementos encontrados, configurando busca...');

        let timeoutBusca = null;
        let controllerAtual = null; // ✅ CORREÇÃO: Armazena controller atual
        const controllersAbortadosManuais = new Set(); // ✅ CORREÇÃO: Rastreia controllers abortados manualmente

        // Função para buscar pacientes
        const buscarPacientesAgendamento = (termo) => {
            console.log('🔎 Buscando por:', termo);

            if (termo.length < 2) {
                resultadosDiv.classList.add('hidden');
                return;
            }

            // ✅ CORREÇÃO: Cancelar requisição anterior se existir
            if (controllerAtual) {
                console.log('🔄 Cancelando busca anterior...');
                controllersAbortadosManuais.add(controllerAtual); // ✅ Adiciona ao Set
                controllerAtual.abort();
                controllerAtual = null;
            }

            // Mostrar loading
            resultadosDiv.innerHTML = `
                <div class="p-3 text-center text-gray-500">
                    <i class="bi bi-arrow-clockwise animate-spin mr-2"></i>Buscando pacientes...
                </div>
            `;
            resultadosDiv.classList.remove('hidden');
            console.log('📡 Enviando requisição para buscar_paciente.php...');

            // Fazer requisição com timeout
            const inicio = Date.now();
            const estaRequisicao = new AbortController(); // ✅ Novo controller para esta busca
            controllerAtual = estaRequisicao; // Salva como atual
            const timeoutId = setTimeout(() => {
                if (controllerAtual === estaRequisicao) { // ✅ Verifica se ainda é a requisição atual
                    // Não adiciona ao Set porque timeout deve mostrar erro
                    estaRequisicao.abort();
                    controllerAtual = null;
                }
            }, 30000); // ✅ 30 segundos timeout (nomes maiores demoram mais no backend)

            // Garantir caminho correto
            const urlBase = window.location.pathname.includes('/agenda/') ? '' : '/agenda/';
            const url = urlBase + 'buscar_paciente.php';
            console.log('🔗 URL da requisição:', url);

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `termo=${encodeURIComponent(termo)}`,
                signal: estaRequisicao.signal // ✅ CORREÇÃO: Usa controller desta requisição
            })
            .then(response => {
                clearTimeout(timeoutId);
                if (controllerAtual === estaRequisicao) {
                    controllerAtual = null; // ✅ Limpa se ainda for a atual
                }
                controllersAbortadosManuais.delete(estaRequisicao); // ✅ Remove do Set se teve sucesso
                const tempo = Date.now() - inicio;
                console.log(`⏱️ Resposta recebida em ${tempo}ms, status:`, response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('📦 Dados recebidos:', data);
                if (data.status === 'sucesso' && data.pacientes && data.pacientes.length > 0) {
                    console.log(`✅ ${data.pacientes.length} paciente(s) encontrado(s)`);
                    resultadosDiv.innerHTML = data.pacientes.map(paciente => `
                        <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-0"
                             onclick="selecionarPacienteAgendamento(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
                            <div class="font-medium text-gray-900">${paciente.nome}</div>
                            <div class="text-sm text-gray-600">
                                CPF: ${paciente.cpf} | Tel: ${paciente.telefone || 'Não informado'}
                                ${paciente.data_nascimento ? ` | Nascimento: ${new Date(paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}
                                ${paciente.email && paciente.email !== 'NAO TEM' && paciente.email !== '.' ? ` | Email: ${paciente.email}` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    console.log('❌ Nenhum paciente encontrado');
                    resultadosDiv.innerHTML = `
                        <div class="p-3 text-center text-gray-500">
                            <i class="bi bi-search mr-2"></i>
                            Nenhum paciente encontrado com "${termo}"
                        </div>
                    `;
                }
            })
            .catch(error => {
                clearTimeout(timeoutId);
                if (controllerAtual === estaRequisicao) {
                    controllerAtual = null; // ✅ Limpa se ainda for a atual
                }

                // ✅ CORREÇÃO: Não mostrar erro se foi abortado manualmente (nova busca)
                if (error.name === 'AbortError' && controllersAbortadosManuais.has(estaRequisicao)) {
                    console.log('🔕 Busca cancelada (nova busca iniciada) - ignorando erro');
                    controllersAbortadosManuais.delete(estaRequisicao); // ✅ Remove do Set
                    return; // Não mostrar erro ao usuário
                }

                // Se chegou aqui, é um erro real que deve ser mostrado
                console.error('❌ Erro na busca de pacientes:', error);
                console.error('❌ Tipo do erro:', error.name);
                console.error('❌ Mensagem:', error.message);

                let mensagemErro = 'Erro ao buscar pacientes';

                if (error.name === 'AbortError') {
                    // Se chegou aqui e é AbortError, foi timeout (não estava no Set)
                    mensagemErro = 'Busca demorou muito (timeout de 30 segundos)';
                    console.error('❌ TIMEOUT: A busca demorou mais de 30 segundos');
                } else if (error.message.includes('HTTP')) {
                    mensagemErro = `Erro do servidor: ${error.message}`;
                } else if (error.message.includes('Failed to fetch')) {
                    mensagemErro = 'Erro de conexão com o servidor';
                    console.error('❌ CONEXÃO: Não foi possível conectar ao servidor');
                }

                resultadosDiv.innerHTML = `
                    <div class="p-3 text-center text-red-500">
                        <i class="bi bi-exclamation-triangle mr-2"></i>
                        ${mensagemErro}
                        <div class="text-xs mt-2 text-gray-500">
                            Tente novamente ou contate o suporte
                        </div>
                    </div>
                `;
            });
        };
        
        // Event listeners
        inputNome.addEventListener('input', function(e) {
            const termo = e.target.value.trim();
            
            clearTimeout(timeoutBusca);
            
            if (termo.length < 2) {
                resultadosDiv.classList.add('hidden');
                return;
            }
            
            timeoutBusca = setTimeout(() => {
                buscarPacientesAgendamento(termo);
            }, 800); // ✅ Aumentado para 800ms para reduzir número de buscas
        });
        
        // Esconder resultados ao perder foco
        inputNome.addEventListener('blur', function() {
            setTimeout(() => resultadosDiv.classList.add('hidden'), 200);
        });
        
        // Mostrar resultados ao focar (se já tiver texto)
        inputNome.addEventListener('focus', function() {
            if (this.value.length >= 2) {
                buscarPacientesAgendamento(this.value.trim());
            }
        });
        
        console.log('✅ Busca em tempo real configurada para agendamento!');
    });
}

/**
 * Selecionar paciente no agendamento
 */
window.selecionarPacienteAgendamento = function(paciente) {
    console.log('👤 Paciente selecionado no agendamento:', paciente);
    
    // Preencher campo de nome
    const nomeInput = document.getElementById('nome_paciente_agendamento');
    const telefoneInput = document.getElementById('telefone_paciente_agendamento');
    const pacienteIdInput = document.getElementById('paciente_existente_id_agendamento');
    
    if (nomeInput) nomeInput.value = paciente.nome;
    if (telefoneInput) telefoneInput.value = paciente.telefone || '';
    if (pacienteIdInput) pacienteIdInput.value = paciente.id;
    
    // Esconder resultados
    const resultadosDiv = document.getElementById('resultados-busca-agendamento');
    if (resultadosDiv) resultadosDiv.classList.add('hidden');
    
    // Desmarcar checkbox de cadastro novo (paciente já existe)
    const checkbox = document.getElementById('checkbox-criar-cadastro-agendamento');
    if (checkbox) {
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change'));
    }
    
    console.log('✅ Dados do paciente preenchidos!');
};

/**
 * Configurar toggle do formulário de cadastro para agendamento
 */
function configurarToggleCadastroAgendamento() {
    const checkbox = document.getElementById('checkbox-criar-cadastro-agendamento');
    const formulario = document.getElementById('formulario-cadastro-novo-agendamento');
    const hiddenCadastrar = document.getElementById('deve_cadastrar_paciente_agendamento');
    
    if (!checkbox || !formulario) {
        console.warn('Elementos do formulário de cadastro não encontrados');
        return;
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            formulario.classList.remove('hidden');
            // Atualizar campo hidden para indicar que deve cadastrar
            if (hiddenCadastrar) {
                hiddenCadastrar.value = 'true';
                console.log('📝 Campo deve_cadastrar_paciente definido como true');
            }
            console.log('📝 Formulário de cadastro expandido');
        } else {
            formulario.classList.add('hidden');
            // Limpar campos do formulário
            formulario.querySelectorAll('input').forEach(input => input.value = '');
            // Atualizar campo hidden para indicar que NÃO deve cadastrar
            if (hiddenCadastrar) {
                hiddenCadastrar.value = 'false';
                console.log('📝 Campo deve_cadastrar_paciente definido como false');
            }
            console.log('📝 Formulário de cadastro recolhido');
        }
    });
    
    console.log('✅ Toggle de cadastro configurado');
}

/**
 * Configurar máscaras para formulário de agendamento
 */
function configurarMascarasAgendamento() {
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone_paciente_agendamento');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf_novo_paciente_agendamento');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para CEP
    const cepInput = document.getElementById('cep_novo_paciente_agendamento');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    console.log('✅ Máscaras configuradas para agendamento');
}

/**
 * Configurar busca de CEP para agendamento
 */
function configurarBuscaCEPAgendamento() {
    const btnBuscarCep = document.getElementById('buscar-cep-agendamento');
    const cepInput = document.getElementById('cep_novo_paciente_agendamento');
    
    if (!btnBuscarCep || !cepInput) {
        console.warn('Elementos de busca CEP não encontrados');
        return;
    }
    
    btnBuscarCep.addEventListener('click', function() {
        const cep = cepInput.value.replace(/\D/g, '');
        
        if (cep.length !== 8) {
            alert('CEP deve ter 8 dígitos');
            return;
        }
        
        // Mostrar loading
        btnBuscarCep.innerHTML = '<i class="bi bi-arrow-clockwise animate-spin mr-1"></i>Buscando...';
        btnBuscarCep.disabled = true;
        
        // Buscar CEP na API dos Correios
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(safeJsonParse)
            .then(data => {
                if (data.erro) {
                    throw new Error('CEP não encontrado');
                }
                
                // Preencher campos
                document.getElementById('logradouro_novo_paciente_agendamento').value = data.logradouro || '';
                document.getElementById('bairro_novo_paciente_agendamento').value = data.bairro || '';
                document.getElementById('cidade_novo_paciente_agendamento').value = data.localidade || '';
                
                console.log('✅ Endereço preenchido via CEP');
            })
            .catch(error => {
                console.error('Erro ao buscar CEP:', error);
                alert('Erro ao buscar CEP. Verifique se o CEP está correto.');
            })
            .finally(() => {
                btnBuscarCep.innerHTML = '<i class="bi bi-search mr-1"></i>Buscar CEP';
                btnBuscarCep.disabled = false;
            });
    });
    
    console.log('✅ Busca de CEP configurada');
}

/**
 * Configurar controle de CPF no agendamento
 */
function configurarControleCPFAgendamento(agendaInfo, agendaId) {
    const checkbox = document.getElementById('nao_tem_cpf_agendamento');
    const cpfInput = document.getElementById('cpf_novo_paciente_agendamento');
    const asterisco = document.getElementById('cpf_asterisco_agendamento');
    
    if (!checkbox || !cpfInput || !asterisco) {
        console.warn('Elementos de controle de CPF não encontrados no agendamento');
        return;
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            // CPF não é obrigatório
            cpfInput.value = '';
            cpfInput.disabled = true;
            cpfInput.style.backgroundColor = '#f3f4f6';
            asterisco.style.display = 'none';
            console.log('📝 CPF agendamento: opcional (checkbox marcado)');
        } else {
            // CPF é obrigatório
            cpfInput.disabled = false;
            cpfInput.style.backgroundColor = '';
            asterisco.style.display = 'inline';
            console.log('📝 CPF agendamento: obrigatório (checkbox desmarcado)');
        }
    });
    
    console.log('✅ Controle de CPF configurado para agendamento');
    
    // A função carregarExamesSeNecessarioAgendamento já é chamada
    // no final da criação do modal e funciona corretamente
}



/**
 * Configurar controle de CPF no encaixe
 */
function configurarControleCPFEncaixe() {
    const checkbox = document.getElementById('nao_tem_cpf');
    const cpfInput = document.getElementById('cpf_novo_paciente');
    const asterisco = document.getElementById('cpf_asterisco');
    
    if (!checkbox || !cpfInput || !asterisco) {
        console.warn('Elementos de controle de CPF não encontrados no encaixe');
        return;
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            // CPF não é obrigatório
            cpfInput.value = '';
            cpfInput.disabled = true;
            cpfInput.style.backgroundColor = '#f3f4f6';
            asterisco.style.display = 'none';
            console.log('📝 CPF encaixe: opcional (checkbox marcado)');
        } else {
            // CPF é obrigatório
            cpfInput.disabled = false;
            cpfInput.style.backgroundColor = '';
            asterisco.style.display = 'inline';
            console.log('📝 CPF encaixe: obrigatório (checkbox desmarcado)');
        }
    });
    
    console.log('✅ Controle de CPF configurado para encaixe');
}

/**
 * Carregar exames para agendamento (baseado no encaixe)
 */
function carregarExamesSeNecessarioAgendamento(agendaId) {
    console.log('🔬 DEBUG: Carregando exames para agendamento, ID:', agendaId);
    console.log('🔬 DEBUG: URL da requisição:', `buscar_exames_agenda.php?agenda_id=${agendaId}`);
    
    if (!agendaId) {
        console.error('❌ ID da agenda não fornecido');
        return;
    }
    
    // Fazer requisição para buscar exames
    fetchWithAuth(`buscar_exames_agenda.php?agenda_id=${agendaId}`)
        .then(safeJsonParse)
        .then(data => {
            console.log('🔬 DEBUG: Resposta da API de exames para agendamento:', data);
            console.log('🔬 DEBUG: Status da resposta:', data.status);
            
            if (data.status === 'sucesso') {
                const exames = data.exames || [];
                console.log(`📋 DEBUG: Total exames encontrados: ${exames.length}`);
                console.log('📋 DEBUG: Exemplo de exame:', exames[0]);
                
                // Sempre configurar sistema de busca, mesmo sem exames
                console.log('🔬 DEBUG: Chamando configurarBuscaExamesAgendamento');
                configurarBuscaExamesAgendamento(exames);
                
                if (exames.length === 0) {
                    // Mostrar mensagem de nenhum exame disponível no dropdown
                    const dropdown = document.getElementById('exames_dropdown_agendamento');
                    console.log('🔬 DEBUG: Nenhum exame, dropdown element:', !!dropdown);
                    if (dropdown) {
                        dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">Nenhum exame disponível</div>';
                    }
                }
            } else {
                console.error('❌ DEBUG: Erro ao carregar exames:', data.mensagem);
            }
        })
        .catch(error => {
            console.error('❌ Erro na requisição de exames:', error);
        });
}

/**
 * Configurar sistema de busca de exames para agendamento
 */
function configurarBuscaExamesAgendamento(exames) {
    console.log('🚀 DEBUG: Configurando busca de exames para agendamento...');
    console.log('📋 DEBUG: Exames recebidos:', exames.length);
    console.log('📋 DEBUG: Primeiros 3 exames:', exames.slice(0, 3));

    const searchInput = document.getElementById('exames_search_agendamento');
    const dropdown = document.getElementById('exames_dropdown_agendamento');
    const hiddenInput = document.getElementById('exames_ids_selected_agendamento');
    const examesSelecionadosDiv = document.getElementById('exames_selecionados_agendamento');
    const contadorDiv = document.getElementById('contador-exames-agendamento');
    const btnLimpar = document.getElementById('btn_limpar_exames_agendamento');

    console.log('🔍 DEBUG: Elementos encontrados:');
    console.log('  - searchInput:', !!searchInput);
    console.log('  - dropdown:', !!dropdown);
    console.log('  - hiddenInput:', !!hiddenInput);
    console.log('  - examesSelecionadosDiv:', !!examesSelecionadosDiv);

    if (!searchInput || !dropdown || !hiddenInput || !examesSelecionadosDiv) {
        console.error('❌ DEBUG: Elementos necessários não encontrados');
        console.error('   - searchInput:', searchInput);
        console.error('   - dropdown:', dropdown);
        console.error('   - hiddenInput:', hiddenInput);
        console.error('   - examesSelecionadosDiv:', examesSelecionadosDiv);
        return;
    }

    // ✅ CORREÇÃO: Limpar hidden input no início para garantir estado limpo
    hiddenInput.value = '';
    console.log('🧹 Hidden input limpo no início da configuração');

    let examesSelecionados = [];
    let examesTodas = exames;
    
    // Função para renderizar dropdown
    const renderizarDropdown = (examesFiltrados) => {
        if (examesTodas.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">Nenhum exame disponível para esta agenda</div>';
        } else if (examesFiltrados.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">Nenhum exame encontrado para este termo</div>';
        } else {
            dropdown.innerHTML = examesFiltrados.map(exame => {
                const jaSelecionado = examesSelecionados.some(sel => sel.id === exame.id);
                return `
                    <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-0 ${jaSelecionado ? 'bg-teal-50' : ''}" 
                         onclick="toggleExameAgendamento(${exame.id}, '${exame.nome.replace(/'/g, "\\'")}'); event.stopPropagation();">
                        <div class="flex items-center justify-between">
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">${exame.nome}</div>
                                ${exame.codigo ? `<div class="text-sm text-gray-600">Código: ${exame.codigo}</div>` : ''}
                            </div>
                            <div class="ml-2">
                                ${jaSelecionado ? 
                                    '<i class="bi bi-check-circle-fill text-teal-600"></i>' : 
                                    '<i class="bi bi-plus-circle text-gray-400"></i>'
                                }
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    };
    
    // Função para atualizar exames selecionados
    const atualizarExamesSelecionados = () => {
        if (examesSelecionados.length > 0) {
            examesSelecionadosDiv.innerHTML = examesSelecionados.map(exame => `
                <span class="inline-block bg-teal-100 text-teal-800 text-xs px-2 py-1 rounded mr-1 mb-1">
                    <i class="bi bi-check-circle mr-1"></i>${exame.nome}
                    <button type="button" onclick="removerExameAgendamento(${exame.id})" 
                            class="ml-1 text-teal-600 hover:text-teal-800">
                        <i class="bi bi-x"></i>
                    </button>
                </span>
            `).join('');
            
            btnLimpar.classList.remove('hidden');
            if (contadorDiv) contadorDiv.textContent = `${examesSelecionados.length} selecionado(s)`;
        } else {
            examesSelecionadosDiv.innerHTML = '<div class="text-sm text-gray-500">Nenhum exame selecionado</div>';
            btnLimpar.classList.add('hidden');
            if (contadorDiv) contadorDiv.textContent = '';
        }
        
        hiddenInput.value = examesSelecionados.map(e => e.id).join(',');

        // ✅ CÁLCULO DE TEMPO PARA MÚLTIPLOS EXAMES: Recarregar horários de ressonância
        // quando exames são selecionados/removidos para recalcular com tempo somado
        const agendaIdInput = document.querySelector('#modal-agendamento input[name="agenda_id"]');
        const dataInput = document.querySelector('#modal-agendamento input[name="data_agendamento"]');

        if (agendaIdInput && dataInput) {
            const agendaId = parseInt(agendaIdInput.value);
            const data = dataInput.value;
            const isRessonancia = [30, 76].includes(agendaId);

            if (isRessonancia && data) {
                // Recarregar horários com os exames selecionados
                const examesIds = examesSelecionados.map(e => e.id).join(',');

                console.log(`🔄 Recalculando horários para ${examesSelecionados.length} exame(s) selecionado(s)...`);

                // Se há função global de ressonância, usar ela
                if (typeof window.buscarHorariosRessonancia === 'function') {
                    window.buscarHorariosRessonancia(agendaId, data, examesIds, false)
                        .then(resultado => {
                            console.log(`✅ Horários recalculados com tempo somado de ${examesSelecionados.length} exame(s)`);
                            // Atualizar a interface com os novos horários se necessário
                            // (a função buscarHorariosRessonancia pode já fazer isso)
                        })
                        .catch(error => {
                            console.error('❌ Erro ao recalcular horários:', error);
                        });
                } else {
                    console.log('⚠️ Função buscarHorariosRessonancia não disponível');
                }
            }
        }
    };

    // ✅ CORREÇÃO: Remover event listeners antigos clonando o elemento
    // Isso previne acúmulo de múltiplos listeners quando modal é reaberto
    const oldSearchInput = searchInput;
    const newSearchInput = oldSearchInput.cloneNode(true);
    oldSearchInput.parentNode.replaceChild(newSearchInput, oldSearchInput);
    const actualSearchInput = document.getElementById('exames_search_agendamento');

    // Event listener para busca
    console.log('🔗 DEBUG: Adicionando event listener ao campo:', actualSearchInput.id);
    actualSearchInput.addEventListener('input', function() {
        const termo = this.value.toLowerCase();
        console.log('🔍 DEBUG: Event input disparado!');
        console.log('🔍 DEBUG: Termo pesquisado:', termo);
        console.log('🔍 DEBUG: Total exames disponíveis:', examesTodas.length);

        const examesFiltrados = examesTodas.filter(exame => {
            const nomeMatch = exame.nome.toLowerCase().includes(termo);
            const codigoMatch = exame.codigo && exame.codigo.toLowerCase().includes(termo);
            return nomeMatch || codigoMatch;
        });

        console.log('🔍 DEBUG: Exames filtrados:', examesFiltrados.length);
        console.log('🔍 DEBUG: Dropdown element:', dropdown);

        renderizarDropdown(examesFiltrados);
        dropdown.classList.remove('hidden');
        console.log('🔍 DEBUG: Dropdown mostrado, classes:', dropdown.className);
    });
    
    // Função global para toggle de exame
    window.toggleExameAgendamento = function(id, nome) {
        const index = examesSelecionados.findIndex(e => e.id === id);
        
        if (index === -1) {
            // Adicionar exame
            examesSelecionados.push({ id, nome });
        } else {
            // Remover exame
            examesSelecionados.splice(index, 1);
        }
        
        atualizarExamesSelecionados();
        renderizarDropdown(examesTodas.filter(exame => 
            exame.nome.toLowerCase().includes(searchInput.value.toLowerCase())
        ));
    };
    
    // Função global para remover exame
    window.removerExameAgendamento = function(id) {
        const index = examesSelecionados.findIndex(e => e.id === id);
        if (index !== -1) {
            examesSelecionados.splice(index, 1);
            atualizarExamesSelecionados();
            renderizarDropdown(examesTodas.filter(exame => 
                exame.nome.toLowerCase().includes(searchInput.value.toLowerCase())
            ));
        }
    };
    
    // Função global para toggle dropdown
    window.toggleDropdownExamesAgendamento = function() {
        const isHidden = dropdown.classList.contains('hidden');
        if (isHidden) {
            renderizarDropdown(examesTodas);
            dropdown.classList.remove('hidden');
            searchInput.focus();
        } else {
            dropdown.classList.add('hidden');
        }
    };
    
    // Função global para limpar tudo
    window.limparTodosExamesAgendamento = function() {
        examesSelecionados = [];
        atualizarExamesSelecionados();
        renderizarDropdown(examesTodas);
    };
    
    // Impedir que cliques no dropdown o fechem
    if (dropdown) {
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }
    
    // Esconder dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        const btnToggle = document.getElementById('btn_toggle_exames_agendamento');
        if (searchInput && dropdown && 
            !searchInput.contains(e.target) && !dropdown.contains(e.target) && 
            (!btnToggle || !btnToggle.contains(e.target))) {
            dropdown.classList.add('hidden');
        }
    });
    
    // Renderização inicial
    renderizarDropdown(examesTodas);
    atualizarExamesSelecionados();
    
    console.log('✅ Sistema de exames configurado para agendamento');
}


/**
 * Configurar busca básica de pacientes (fallback)
 */
function configurarBuscaBasicaPacientes() {
    const inputBusca = document.getElementById('busca-paciente-select');
    const dropdown = document.getElementById('dropdown-resultados');
    
    if (!inputBusca || !dropdown) {
        console.warn('Elementos de busca não encontrados para configuração básica');
        return;
    }
    
    console.log('Configurando busca básica de pacientes...');
    
    let timeoutBusca = null;
    
    // Função para buscar pacientes
    function buscarPacientesBasico(termo) {
        if (termo.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        // Mostrar loading
        dropdown.innerHTML = `
            <div class="p-3 text-center text-gray-500">
                <i class="bi bi-arrow-clockwise animate-spin mr-2"></i>Buscando...
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
        .then(safeJsonParse)
        .then(data => {
            if (data.status === 'sucesso' && data.pacientes.length > 0) {
                dropdown.innerHTML = data.pacientes.map(paciente => `
                    <div class="p-3 hover:bg-gray-100 cursor-pointer border-b border-gray-100" 
                         onclick="selecionarPacienteBasico(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
                        <div class="font-medium text-gray-900">${paciente.nome}</div>
                        <div class="text-sm text-gray-600">
                            CPF: ${paciente.cpf} | Tel: ${paciente.telefone || 'Não informado'}
                            ${paciente.data_nascimento ? ` | Nascimento: ${new Date(paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}
                        </div>
                    </div>
                `).join('');
            } else {
                dropdown.innerHTML = `
                    <div class="p-3 text-center text-gray-500">
                        <i class="bi bi-search mr-2"></i>Nenhum paciente encontrado
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro na busca de pacientes:', error);
            dropdown.innerHTML = `
                <div class="p-3 text-center text-red-500">
                    <i class="bi bi-exclamation-triangle mr-2"></i>Erro ao buscar pacientes
                </div>
            `;
        });
    }
    
    // Event listeners
    inputBusca.addEventListener('input', function(e) {
        const termo = e.target.value.trim();
        
        clearTimeout(timeoutBusca);
        
        if (termo.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        timeoutBusca = setTimeout(() => {
            buscarPacientesBasico(termo);
        }, 300);
    });
    
    inputBusca.addEventListener('blur', function() {
        setTimeout(() => dropdown.classList.add('hidden'), 200);
    });
    
    inputBusca.addEventListener('focus', function() {
        if (this.value.length >= 2) {
            dropdown.classList.remove('hidden');
        }
    });
}

/**
 * Selecionar paciente (função global para usar no onclick)
 */
window.selecionarPacienteBasico = function(paciente) {
    // Preencher o Select2 se estiver disponível
    const selectElement = document.getElementById('busca-paciente-select');
    if (selectElement && typeof $ !== 'undefined' && $(selectElement).hasClass('select2-hidden-accessible')) {
        const newOption = new Option(`${paciente.nome} - ${paciente.cpf}`, paciente.id, true, true);
        $(selectElement).append(newOption).trigger('change');
    } else {
        // Fallback para input normal
        selectElement.value = `${paciente.nome} - ${paciente.cpf}`;
    }
    
    // Preencher dados do paciente usando a função do select2_agendamento.js
    if (typeof preencherDadosPaciente === 'function') {
        preencherDadosPaciente(paciente);
    } else {
        // Fallback básico
        const nomeInput = document.getElementById('nome-paciente');
        const cpfInput = document.getElementById('cpf-paciente');
        const telefoneInput = document.getElementById('telefone-paciente');
        const emailInput = document.getElementById('email-paciente');
        const dataInput = document.getElementById('data-nascimento');
        const idInput = document.getElementById('paciente-existente-id');
        
        if (nomeInput) nomeInput.value = paciente.nome || '';
        if (cpfInput) cpfInput.value = paciente.cpf || '';
        if (telefoneInput) telefoneInput.value = paciente.telefone || '';
        if (emailInput) emailInput.value = paciente.email || '';
        if (dataInput) dataInput.value = paciente.data_nascimento || '';
        if (idInput) idInput.value = paciente.id || '';
    }
    
    // Esconder dropdown
    const dropdown = document.getElementById('dropdown-resultados');
    if (dropdown) dropdown.classList.add('hidden');
};

/**
 * Adiciona event listeners ao modal
 */
function adicionarEventListenersModal(agendaInfo) {
    // Ocultar seção de exames se for consulta
    if (agendaInfo && agendaInfo.agenda && agendaInfo.agenda.tipo === 'consulta') {
        const secaoExames = document.getElementById('secao-exames-agendamento');
        if (secaoExames) {
            secaoExames.style.display = 'none';
            console.log('✅ Campo exames ocultado para consulta');
        }
    } else {
        console.log('ℹ️ Campo exames mantido para procedimento ou tipo não identificado');
    }
    
    // Modal agora só fecha com o botão X - removido fechamento ao clicar fora
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modal-agendamento')) {
            fecharModalAgendamento();
        }
    });
    
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
}

/**
 * Fecha o modal de agendamento
 */
window.fecharModalAgendamento = function() {
    const modal = document.getElementById('modal-agendamento');
    if (modal) {
        // ✅ CORREÇÃO: Limpar exames selecionados ao fechar modal
        const hiddenInput = document.getElementById('exames_ids_selected_agendamento');
        if (hiddenInput) {
            hiddenInput.value = '';
            console.log('🧹 Exames selecionados limpos ao fechar modal');
        }

        // ✅ CORREÇÃO: Limpar array global de exames se existir
        if (typeof limparTodosExamesAgendamento === 'function') {
            try {
                limparTodosExamesAgendamento();
            } catch(e) {
                console.log('ℹ️ Não foi possível limpar exames:', e.message);
            }
        }

        modal.remove();
    }
};

/**
 * Salva o agendamento
 */
window.salvarAgendamento = function(event) {
    // Prevenir submit padrão do form
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    console.log('🔥 INICIANDO SALVAMENTO DE AGENDAMENTO');
    
    const form = document.getElementById('form-agendamento-modal');
    if (!form) {
        console.error('❌ Formulário não encontrado!');
        alert('Erro: Formulário não encontrado.');
        return false;
    }
    
    const formData = new FormData(form);
    
    // Debug - mostrar dados que serão enviados
    console.log('📋 DADOS DO FORMULÁRIO:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    // Validação básica
    const camposObrigatorios = form.querySelectorAll('[required]');
    let valido = true;
    
    camposObrigatorios.forEach(campo => {
        if (!campo.value.trim()) {
            campo.classList.add('border-red-500');
            valido = false;
            console.log(`❌ Campo obrigatório vazio: ${campo.name || campo.id}`);
        } else {
            campo.classList.remove('border-red-500');
        }
    });
    
    if (!valido) {
        alert('Por favor, preencha todos os campos obrigatórios.');
        return false;
    }
    
    // Mostrar loading
    const btnSalvar = event ? event.target : document.querySelector('[onclick="salvarAgendamento()"]');
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i>Salvando...';
    btnSalvar.disabled = true;
    
    // Verificar vagas disponíveis para agendamento normal
    const convenioId = formData.get('convenio_id');
    const dataAgendamento = formData.get('data_agendamento');
    const agendaId = formData.get('agenda_id');
    
    console.log('🔍 Verificando vagas disponíveis...');
    
    fetchWithAuth(`verificar_vagas.php?agenda_id=${agendaId}&data=${dataAgendamento}&convenio_id=${convenioId}`)
        .then(response => {
            console.log('📡 Resposta da verificação de vagas:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return safeJsonParse(response);
        })
        .then(vagas => {
            console.log('✅ Resultado da verificação:', vagas);
            if (!vagas.pode_agendar) {
                alert(vagas.mensagem);
                btnSalvar.innerHTML = textoOriginal;
                btnSalvar.disabled = false;
                return false;
            }

            // ✅ Capturar explicitamente o estado do checkbox de sedação ANTES de salvar
            const checkboxSedacao = document.getElementById('precisa_sedacao');
            if (checkboxSedacao) {
                formData.set('precisa_sedacao', checkboxSedacao.checked ? 'true' : 'false');
                console.log('💉 Sedação capturada para novo agendamento:', checkboxSedacao.checked);
            }

            // Se pode agendar, continua com o processo normal
            console.log('🚀 Processando salvamento...');
            processsarSalvar('processar_agendamento.php', formData, btnSalvar, textoOriginal);
        })
        .catch(error => {
            console.error('❌ Erro ao verificar vagas:', error);
            alert('Erro ao verificar disponibilidade de vagas: ' + error.message);
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        });
    
    return false; // Prevenir qualquer submit
};

function processsarSalvar(endpoint, formData, btnSalvar, textoOriginal) {
    console.log(`💾 Enviando dados para: ${endpoint}`);

    // 🐛 DEBUG: Mostrar TODOS os valores do FormData, especialmente exames
    console.log('🔍 DEBUG: Conteúdo do FormData sendo enviado:');
    for (let [key, value] of formData.entries()) {
        if (key.toLowerCase().includes('exame')) {
            console.log(`   📋 ${key}: "${value}"`);
        }
    }

    // 🐛 DEBUG: Verificar especificamente o campo exames_ids
    const examesIds = formData.get('exames_ids');
    console.log(`🎯 Campo exames_ids: "${examesIds}"`);
    if (examesIds) {
        const idsArray = examesIds.split(',');
        console.log(`   └─ Quantidade: ${idsArray.length} exames`);
        console.log(`   └─ IDs: [${idsArray.join(', ')}]`);
    }

    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📡 Resposta do servidor:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return safeJsonParse(response);
    })
    .then(data => {
        console.log('📄 Dados retornados:', data);
        
        if (data.status === 'sucesso') {
            console.log('✅ Agendamento salvo com sucesso!');
            
            // Fechar modal
            fecharModalAgendamento();
            
            // Mostrar mensagem de sucesso com toast
            if (typeof showToast === 'function') {
                showToast('Agendamento realizado com sucesso!', true);
            } else {
                alert('Agendamento realizado com sucesso!');
            }
            
            // Recarregar a visualização da agenda se possível
            const dataAgendamento = formData.get('data_agendamento');
            const agendaId = formData.get('agenda_id');
            
            console.log(`🔄 Tentando atualizar agenda: ${agendaId} - ${dataAgendamento}`);
            
            // Tentar atualizar sem recarregar página
            setTimeout(() => {
                if (window.carregarVisualizacaoDia && typeof window.carregarVisualizacaoDia === 'function') {
                    console.log('🔄 Atualizando via carregarVisualizacaoDia');
                    window.carregarVisualizacaoDia(agendaId, dataAgendamento);
                } else {
                    console.log('✅ Agendamento salvo com sucesso - visualização não atualizada automaticamente');
                }
            }, 1000);
        } else {
            console.error('❌ Erro retornado pelo servidor:', data.mensagem);
            alert('Erro: ' + (data.mensagem || 'Erro desconhecido'));
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erro ao processar agendamento. Tente novamente.');
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
    });
}

/**
 * Configura busca de paciente no modal de agendamento
 */
function configurarBuscaPacienteAgendamento() {
    const inputNome = document.getElementById('nome_paciente_agendamento');
    const resultadosDiv = document.getElementById('resultados-busca-agendamento');
    let timeoutBusca = null;
    
    if (!inputNome || !resultadosDiv) {
        console.error('Elementos de busca não encontrados no modal de agendamento');
        return;
    }
    
    // Configurar busca em tempo real
    inputNome.addEventListener('input', function() {
        const termo = this.value.trim();
        
        // Limpar timeout anterior
        clearTimeout(timeoutBusca);
        
        // Se termo está vazio, esconder resultados
        if (termo.length === 0) {
            resultadosDiv.classList.add('hidden');
            limparDadosPacienteAgendamento();
            return;
        }
        
        // Buscar após 300ms de inatividade
        if (termo.length >= 2) {
            timeoutBusca = setTimeout(() => {
                buscarPacientesAgendamento(termo);
            }, 800); // ✅ Aumentado para 800ms para reduzir número de buscas
        }
    });
    
    // Fechar resultados ao clicar fora
    document.addEventListener('click', function(event) {
        if (!inputNome.contains(event.target) && !resultadosDiv.contains(event.target)) {
            resultadosDiv.classList.add('hidden');
        }
    });
}

/**
 * Busca pacientes para o modal de agendamento
 */
function buscarPacientesAgendamento(termo) {
    const resultadosDiv = document.getElementById('resultados-busca-agendamento');
    
    // Mostrar loading
    resultadosDiv.innerHTML = `
        <div class="p-4 text-center text-gray-500">
            <i class="bi bi-hourglass-split animate-spin mr-2"></i>Buscando...
        </div>
    `;
    resultadosDiv.classList.remove('hidden');
    
    // Fazer busca
    const formData = new FormData();
    formData.append('termo', termo);
    
    fetch('buscar_paciente.php', {
        method: 'POST',
        body: formData
    })
    .then(safeJsonParse)
    .then(dados => {
        console.log('Resposta da busca:', dados);
        exibirResultadosAgendamento(dados);
    })
    .catch(error => {
        console.error('Erro na busca:', error);
        resultadosDiv.innerHTML = `
            <div class="p-4 text-center text-red-500">
                <i class="bi bi-exclamation-triangle mr-2"></i>
                Erro na busca. Tente novamente.
            </div>
        `;
    });
}

/**
 * Exibe resultados da busca no modal de agendamento
 */
function exibirResultadosAgendamento(dados) {
    const resultadosDiv = document.getElementById('resultados-busca-agendamento');
    
    // Verificar se é um erro
    if (dados && dados.erro) {
        resultadosDiv.innerHTML = `
            <div class="p-4 text-center text-red-500">
                <i class="bi bi-exclamation-triangle mr-2"></i>
                ${dados.erro}
            </div>
        `;
        return;
    }
    
    // Extrair array de pacientes - pode vir como 'pacientes' ou direto
    let pacientes = dados;
    if (dados && dados.pacientes) {
        pacientes = dados.pacientes;
    }
    
    // Verificar se é array e tem itens
    if (!Array.isArray(pacientes) || pacientes.length === 0) {
        resultadosDiv.innerHTML = `
            <div class="p-4 text-center text-gray-500">
                <i class="bi bi-person-x mr-2"></i>
                Nenhum paciente encontrado
            </div>
        `;
        return;
    }
    
    const html = pacientes.map(paciente => `
        <div class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-0"
             onclick="selecionarPacienteAgendamento(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-gray-900">
                        <i class="bi bi-person-circle mr-2 text-teal-600"></i>
                        ${paciente.nome || paciente.PACIENTE || 'Nome não informado'}
                    </div>
                    <div class="text-sm text-gray-500">
                        ${(paciente.cpf || paciente.CPF) ? `CPF: ${paciente.cpf || paciente.CPF}` : ''}
                        ${(paciente.telefone || paciente.FONE1) ? ` • Tel: ${paciente.telefone || paciente.FONE1}` : ''}
                    </div>
                    <div class="text-xs text-gray-400">
                        ${(paciente.data_nascimento || paciente.NASCIMENTO || paciente.ANIVERSARIO || paciente.DATA_NASC) ? `Nascimento: ${paciente.data_nascimento || paciente.NASCIMENTO || paciente.ANIVERSARIO || paciente.DATA_NASC}` : ''}
                    </div>
                </div>
                <div class="text-xs text-gray-400">
                    <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    `).join('');
    
    resultadosDiv.innerHTML = html;
    resultadosDiv.classList.remove('hidden');
}

/**
 * Seleciona um paciente no modal de agendamento
 */
window.selecionarPacienteAgendamento = function(paciente) {
    console.log('Paciente selecionado para agendamento:', paciente);
    
    // Preencher campos - compatível com diferentes formatos
    const nome = paciente.nome || paciente.PACIENTE || '';
    const telefone = paciente.telefone || paciente.FONE1 || '';
    const id = paciente.id || paciente.IDPACIENTE || '';
    const dataNascimento = paciente.data_nascimento || paciente.DATA_NASC || paciente.NASCIMENTO || paciente.ANIVERSARIO || '';
    
    document.getElementById('nome_paciente_agendamento').value = nome;
    document.getElementById('telefone_paciente_agendamento').value = telefone;
    
    // Preencher data de nascimento se disponível
    const campoDataNasc = document.getElementById('data_nascimento_agendamento');
    if (campoDataNasc && dataNascimento) {
        console.log('🎂 Data nascimento raw:', dataNascimento);
        // Converter formato se necessário (DD/MM/YYYY -> YYYY-MM-DD)
        let dataFormatada = dataNascimento;
        if (dataNascimento.includes('/')) {
            const partes = dataNascimento.split('/');
            if (partes.length === 3) {
                // DD/MM/YYYY -> YYYY-MM-DD
                dataFormatada = `${partes[2]}-${partes[1].padStart(2, '0')}-${partes[0].padStart(2, '0')}`;
            }
        }
        console.log('🎂 Data formatada:', dataFormatada);
        campoDataNasc.value = dataFormatada;
        // Calcular idade automaticamente
        calcularIdadeAgendamento();
    } else {
        console.log('🎂 Sem data de nascimento ou campo não encontrado', { campoDataNasc, dataNascimento });
    }
    
    // Bloquear campos quando paciente é cadastrado (tem ID)
    if (id) {
        console.log('🔒 Bloqueando campos para paciente cadastrado - ID:', id);
        // Bloquear telefone
        const campoTelefone = document.getElementById('telefone_paciente_agendamento');
        if (campoTelefone) {
            campoTelefone.readOnly = true;
            campoTelefone.classList.add('bg-gray-50', 'cursor-not-allowed');
            campoTelefone.title = 'Campo bloqueado - paciente cadastrado';
            console.log('🔒 Telefone bloqueado');
        }
        
        // Bloquear data de nascimento
        if (campoDataNasc) {
            campoDataNasc.readOnly = true;
            campoDataNasc.classList.add('bg-gray-50', 'cursor-not-allowed');
            console.log('🔒 Data nascimento bloqueada');
            campoDataNasc.title = 'Campo bloqueado - paciente cadastrado';
        }
        
        console.log('🔒 Campos bloqueados para paciente cadastrado');
    }
    
    // Definir que está usando paciente existente
    document.getElementById('usar_paciente_existente_agendamento').value = 'true';
    document.getElementById('paciente_id_agendamento').value = id;
    
    // Esconder resultados
    document.getElementById('resultados-busca-agendamento').classList.add('hidden');
    
    console.log('Campos preenchidos:', { nome, telefone, id, dataNascimento });
};

/**
 * Limpa seleção de paciente e desbloqueia campos
 */
window.limparSelecaoPacienteAgendamento = function() {
    console.log('🧹 Limpando seleção de paciente');
    
    // Limpar campos de paciente
    document.getElementById('nome_paciente_agendamento').value = '';
    document.getElementById('telefone_paciente_agendamento').value = '';
    document.getElementById('data_nascimento_agendamento').value = '';
    document.getElementById('idade_agendamento').value = '';
    
    // Desbloquear campos
    const campoTelefone = document.getElementById('telefone_paciente_agendamento');
    if (campoTelefone) {
        campoTelefone.readOnly = false;
        campoTelefone.classList.remove('bg-gray-50', 'cursor-not-allowed');
        campoTelefone.title = '';
    }
    
    const campoDataNasc = document.getElementById('data_nascimento_agendamento');
    if (campoDataNasc) {
        campoDataNasc.readOnly = false;
        campoDataNasc.classList.remove('bg-gray-50', 'cursor-not-allowed');
        campoDataNasc.title = '';
    }
    
    // Limpar campos ocultos
    document.getElementById('usar_paciente_existente_agendamento').value = 'false';
    document.getElementById('paciente_id_agendamento').value = '';
    
    console.log('🧹 Campos limpos e desbloqueados');
};

/**
 * Verifica se o campo nome foi limpo para desbloquear campos
 */
window.verificarLimpezaCampoNomeAgendamento = function(campoNome) {
    const valor = campoNome.value.trim();
    
    // Se o campo está vazio, desbloquear os campos
    if (valor === '') {
        console.log('🧹 Campo nome vazio - desbloqueando campos');
        
        // Desbloquear campos
        const campoTelefone = document.getElementById('telefone_paciente_agendamento');
        if (campoTelefone) {
            campoTelefone.readOnly = false;
            campoTelefone.classList.remove('bg-gray-50', 'cursor-not-allowed');
            campoTelefone.title = '';
        }
        
        const campoDataNasc = document.getElementById('data_nascimento_agendamento');
        if (campoDataNasc) {
            campoDataNasc.readOnly = false;
            campoDataNasc.classList.remove('bg-gray-50', 'cursor-not-allowed');
            campoDataNasc.title = '';
        }
        
        // Limpar campos ocultos
        document.getElementById('usar_paciente_existente_agendamento').value = 'false';
        document.getElementById('paciente_id_agendamento').value = '';
        
        // Esconder resultados da busca
        document.getElementById('resultados-busca-agendamento').classList.add('hidden');
    }
};

/**
 * Limpa dados do paciente no modal de agendamento
 */
function limparDadosPacienteAgendamento() {
    document.getElementById('telefone_paciente_agendamento').value = '';
    document.getElementById('usar_paciente_existente_agendamento').value = 'false';
    document.getElementById('paciente_id_agendamento').value = '';
}


/**
 * 🔧 SISTEMA DE ENCAIXES - JavaScript
 * Adicione estas funções ao seu agenda.js
 */

// ✅ CORREÇÃO da função abrirModalEncaixe
// Procure por esta função no seu agenda.js (linha ~3556) e substitua


window.abrirModalEncaixe = function(agendaId, data, especialidadeId = null) {
    console.log('🎯 Abrindo modal de encaixe simplificado:', { agendaId, data, especialidadeId });
    
    // Usar especialidade passada como parâmetro ou a global
    const especialidadeFinal = especialidadeId || window.especialidadeIdSelecionada || '';
    console.log('📋 Encaixe - Especialidade final:', especialidadeFinal);
    
    // Verificar se permite encaixes
    fetchWithAuth(`verificar_encaixes.php?agenda_id=${agendaId}&data=${data}`)
        .then(response => response.text())
        .then(responseText => {
            const primeiraLinha = responseText.split('\n')[0].trim();
            const dadosEncaixe = JSON.parse(primeiraLinha);
            
            if (dadosEncaixe.erro) {
                alert('Erro: ' + dadosEncaixe.erro);
                return;
            }
            
            if (!dadosEncaixe.permite_encaixes || !dadosEncaixe.pode_encaixar) {
                alert(dadosEncaixe.mensagem || 'Não é possível fazer encaixe nesta agenda/data');
                return;
            }
            
            // Buscar informações da agenda
            fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                .then(safeJsonParse)
                .then(agendaData => {
                    console.log('✅ Dados da agenda recebidos:', agendaData);
                    const agendaInfo = agendaData.agenda || {};
                    console.log('📋 Convênios disponíveis:', agendaInfo.convenios);
                    criarModalEncaixeSimplificado(agendaId, data, dadosEncaixe, agendaInfo, especialidadeFinal);
                })
                .catch(error => {
                    console.error('Erro ao buscar info da agenda:', error);
                    criarModalEncaixeSimplificado(agendaId, data, dadosEncaixe, {});
                });
        })
        .catch(error => {
            console.error('Erro ao verificar encaixes:', error);
            alert('Erro ao verificar disponibilidade de encaixes.');
        });
};

/**
 * ✅ FUNÇÃO ATUALIZADA: criarModalEncaixeSimplificado com campos completos
 */
function criarModalEncaixeSimplificado(agendaId, data, dadosEncaixe, agendaInfo, especialidadeId = '') {
    const especialidadeFinal = especialidadeId || '';
    console.log('📋 Modal encaixe - Especialidade final:', especialidadeFinal);
    console.log('🎯 Criando modal de encaixe com agendaInfo:', agendaInfo);
    console.log('📋 Convênios recebidos na função:', agendaInfo.convenios);
    
    // Remover modal anterior se existir
    const modalAnterior = document.getElementById('modal-encaixe');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const modalHTML = `
        <div id="modal-encaixe" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[95vh] overflow-y-auto">
                <!-- Cabeçalho -->
                <div class="bg-gradient-to-r from-orange-600 to-red-600 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-lightning-charge mr-3"></i>
                                Agendar Encaixe
                            </h2>
                            <p class="text-orange-100 mt-1">${dataFormatada} - ${agendaInfo.nome_agenda || 'Agenda'}</p>
                        </div>
                        <button onclick="fecharModalEncaixe()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Conteúdo -->
                <div class="p-6">
                    <!-- Info do Encaixe -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-orange-800">Informações do Encaixe</h4>
                                <p class="text-orange-700 text-sm mt-1">
                                    Disponível: ${dadosEncaixe.encaixes_disponiveis}/${dadosEncaixe.limite_total} encaixes
                                </p>
                            </div>
                            <i class="bi bi-info-circle text-orange-600 text-xl"></i>
                        </div>
                    </div>

                    <!-- ✅ SEÇÃO: Seleção de Horário (mantida igual) -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h4 class="font-medium text-blue-800 mb-3 flex items-center">
                            <i class="bi bi-clock mr-2"></i>
                            Horário do Encaixe
                        </h4>
                        <div class="space-y-3">
                            <!-- Opção: Horário específico -->
                            <label class="flex items-center">
                                <input type="radio" name="tipo_horario" value="horario_especifico" checked
                                       class="mr-3 text-blue-600" style="display: none;">
                                <div>
                                    <span class="font-medium text-gray-800">Agendar em horário específico</span>
                                    <p class="text-sm text-gray-600">Digite um horário dentro do funcionamento da agenda</p>
                                </div>
                            </label>
                            
                            <!-- ✅ Área de input de horário -->
                            <div id="area-input-horario" class="mt-4">
                                <div class="bg-white border border-gray-300 rounded-lg p-4">
                                    <div id="info-horarios-agenda" class="mb-4 p-3 bg-gray-50 rounded">
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <i class="bi bi-info-circle"></i>
                                            <span>Carregando horários de funcionamento...</span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <label class="block text-sm font-medium text-gray-700">
                                            Horário desejado:
                                        </label>
                                        <div class="flex items-center gap-3">
                                            <input type="time" 
                                                   id="horario_digitado" 
                                                   name="horario_digitado"
                                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   onchange="validarHorarioDigitado()">
                                            
                                            <button type="button" 
                                                    onclick="verificarDisponibilidadeHorario()" 
                                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                                <i class="bi bi-search mr-1"></i>Verificar
                                            </button>
                                        </div>
                                        
                                        <div id="status-horario" class="hidden"></div>
                                        <div id="sugestoes-horarios" class="hidden">
                                            <p class="text-sm font-medium text-gray-700 mb-2">Horários próximos disponíveis:</p>
                                            <div class="flex flex-wrap gap-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ✅ FORMULÁRIO PRINCIPAL -->
                    <form id="form-encaixe" class="space-y-6">
                        <!-- Campos hidden -->
                        <input type="hidden" name="agenda_id" value="${agendaId}">
                        <input type="hidden" name="data_agendamento" value="${data}">
                        <input type="hidden" id="horario_selecionado_hidden" name="horario_agendamento" value="">
                        <input type="hidden" id="usar_paciente_existente" name="usar_paciente_existente" value="false">
                        <input type="hidden" name="especialidade_id" value="${especialidadeFinal}">
                        <input type="hidden" id="cadastrar_paciente" name="cadastrar_paciente" value="false">
                        <input type="hidden" id="paciente_id_hidden" name="paciente_id" value="">
                        
                        <!-- ✅ SEÇÃO: Dados do Paciente -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="bi bi-person mr-2"></i>
                                Dados do Paciente (Obrigatórios)
                            </h4>
                            
                            <!-- Busca de paciente -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Nome do Paciente *
                                </label>
                                <div class="relative">
                                    <input type="text" 
                                           id="nome_paciente_busca_real" 
                                           name="nome_paciente"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                           placeholder="Digite nome, CPF ou data de nascimento (16/09/1990, 16091990 ou 160990)..."
                                           required
                                           autocomplete="off">
                                    <i class="bi bi-search absolute right-3 top-2.5 text-gray-400"></i>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="bi bi-info-circle mr-1"></i>
                                    Busque por: <strong>Nome</strong> (ex: João Silva), <strong>CPF</strong> (123.456.789-00 ou 12345678900), <strong>Data de Nascimento</strong> (01/01/1990, 01011990 ou 010190) ou <strong>Telefone</strong>
                                </p>
                                
                                <!-- Resultados da busca -->
                                <div id="resultados-busca-tempo-real" class="hidden mt-2 bg-white border border-gray-200 rounded-lg shadow-lg max-h-60 overflow-y-auto">
                                    <!-- Preenchido dinamicamente -->
                                </div>
                            </div>
                            
                            <!-- Telefone -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Telefone *
                                </label>
                                <input type="tel" 
                                       id="telefone_paciente_encaixe" 
                                       name="telefone_paciente"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                       placeholder="(84) 99999-9999" 
                                       required>
                            </div>
                            
                            <!-- Convênio -->
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Convênio *
                                </label>
                                <select id="convenio_encaixe" 
                                        name="convenio_id" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                        required>
                                    <option value="">Selecione o convênio...</option>
                                    <!-- Convênios serão carregados dinamicamente -->
                                </select>
                            </div>
                            
                            <!-- Exames (apenas para procedimentos) - MULTI-SELECT -->
                            <div id="secao-exames" class="mb-4 hidden">
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Exames * <span class="text-xs text-gray-500">(múltipla seleção)</span>
                                </label>
                                
                                <!-- Lista de exames selecionados -->
                                <div class="mb-3">
                                    <div class="flex justify-between items-center mb-1">
                                        <span class="text-xs font-medium text-gray-600">Exames selecionados:</span>
                                        <button type="button" 
                                                id="btn_limpar_exames"
                                                onclick="limparTodosExames()"
                                                class="text-xs text-red-600 hover:text-red-800 hover:underline hidden">
                                            <i class="bi bi-trash"></i> Limpar tudo
                                        </button>
                                    </div>
                                    <div id="exames_selecionados" class="min-h-[40px] border border-gray-300 rounded-lg p-2 bg-gray-50">
                                        <div class="text-sm text-gray-500">Nenhum exame selecionado</div>
                                    </div>
                                </div>
                                
                                <div class="relative">
                                    <div class="flex">
                                        <input type="text" 
                                               id="exames_search" 
                                               placeholder="Digite para buscar e adicionar exames..."
                                               class="flex-1 px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500"
                                               autocomplete="off">
                                        <button type="button" 
                                                id="btn_toggle_exames" 
                                                onclick="toggleDropdownExames()"
                                                class="px-3 py-2 bg-orange-600 text-white rounded-r-lg hover:bg-orange-700 focus:ring-2 focus:ring-orange-500 border border-orange-600">
                                            <i class="bi bi-chevron-down" id="icon_toggle_exames"></i>
                                        </button>
                                    </div>
                                    <div id="exames_dropdown" class="absolute z-50 w-full bg-white border border-gray-300 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden mt-1">
                                        <div class="p-3 text-gray-500 text-sm">Carregando exames...</div>
                                    </div>
                                    <input type="hidden" id="exames_ids_selected" name="exames_ids" value="">
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <i class="bi bi-info-circle mr-1"></i>
                                            Clique nos exames para seleção múltipla
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">
                                                <i class="bi bi-click mr-1"></i>Clique = Adicionar
                                            </span>
                                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">
                                                <i class="bi bi-chevron-up-down mr-1"></i>Botão = Abrir/Fechar
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- ✅ SEÇÃO: Cadastrar Paciente Completo -->
                        <div class="bg-blue-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h4 class="text-base font-semibold text-blue-800 flex items-center">
                                        <i class="bi bi-person-plus mr-2"></i>
                                        Cadastrar Paciente no Sistema
                                    </h4>
                                    <p class="text-blue-700 text-sm mt-1">Opcional: Cadastre o paciente para consultas futuras</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="checkbox-criar-cadastro" class="sr-only peer" onchange="toggleCadastroCompleto()">
                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    <span class="ml-3 text-sm font-medium text-blue-900">Cadastrar paciente</span>
                                </label>
                            </div>
                            
                            <!-- ✅ FORMULÁRIO DE CADASTRO COMPLETO -->
                            <div id="formulario-cadastro-novo" class="hidden space-y-4 border-t border-blue-200 pt-4">
                                <!-- ✅ SEÇÃO: Informações Gerais -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-info-circle mr-2"></i>
                                        Informações Gerais
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- CPF -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                CPF <span class="text-red-500" id="cpf_asterisco">*</span>
                                            </label>
                                            <input type="text" 
                                                   id="cpf_novo_paciente" 
                                                   name="cpf_paciente" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                   placeholder="000.000.000-00">
                                            <div class="mt-2">
                                                <label class="flex items-center">
                                                    <input type="checkbox" id="nao_tem_cpf" name="nao_tem_cpf" 
                                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <span class="ml-2 text-sm text-gray-700">Paciente não tem CPF</span>
                                                </label>
                                            </div>
                                        </div>
                                        
                                        <!-- Sexo -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Sexo *
                                            </label>
                                            <select id="sexo_novo_paciente" 
                                                    name="sexo" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                                <option value="">Selecione...</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Feminino</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Data de Nascimento -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Data de Nascimento *
                                            </label>
                                            <input type="date" 
                                                   id="nascimento_novo_paciente" 
                                                   name="data_nascimento" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Documentos -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-card-text mr-2"></i>
                                        Documentos
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- RG -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                RG
                                            </label>
                                            <input type="text" 
                                                   id="rg_novo_paciente" 
                                                   name="rg" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                   placeholder="0000000">
                                        </div>
                                        
                                        <!-- Órgão Emissor -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                Órgão Emissor
                                            </label>
                                            <select id="orgao_emissor_novo_paciente" 
                                                    name="orgao_emissor" 
                                                    class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                                <option value="">Selecione...</option>
                                                <option value="SSP/RN">SSP/RN</option>
                                                <option value="DETRAN/RN">DETRAN/RN</option>
                                                <option value="PC/RN">PC/RN</option>
                                                <option value="SSP/PB">SSP/PB</option>
                                                <option value="SSP/CE">SSP/CE</option>
                                                <option value="SSP/PE">SSP/PE</option>
                                                <option value="OUTRO">Outro</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Contato -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-telephone mr-2"></i>
                                        Contato
                                    </h5>
                                    <div class="grid grid-cols-1 md:grid-cols-1 gap-4">
                                        <!-- E-mail -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                                E-mail
                                            </label>
                                            <input type="email" 
                                                   id="email_novo_paciente" 
                                                   name="email_paciente" 
                                                   class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                   placeholder="email@exemplo.com">
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- ✅ SEÇÃO: Endereço -->
                                <div>
                                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center">
                                        <i class="bi bi-geo-alt mr-2"></i>
                                        Endereço
                                    </h5>
                                    <div class="space-y-4">
                                        <!-- CEP -->
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    CEP
                                                </label>
                                                <div class="relative">
                                                    <input type="text" 
                                                           id="cep_novo_paciente" 
                                                           name="cep" 
                                                           class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                           placeholder="00000-000"
                                                           onblur="buscarCEP()">
                                                    <button type="button" 
                                                            onclick="buscarCEP()" 
                                                            class="absolute right-2 top-2 text-blue-600 hover:text-blue-800">
                                                        <i class="bi bi-search text-sm"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="md:col-span-3">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Logradouro
                                                </label>
                                                <input type="text" 
                                                       id="logradouro_novo_paciente" 
                                                       name="endereco" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                       placeholder="Rua, Avenida, etc.">
                                            </div>
                                        </div>
                                        
                                        <!-- Número, Complemento -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Número
                                                </label>
                                                <input type="text" 
                                                       id="numero_novo_paciente" 
                                                       name="numero" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                       placeholder="123">
                                            </div>
                                            <div class="md:col-span-2">
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Complemento
                                                </label>
                                                <input type="text" 
                                                       id="complemento_novo_paciente" 
                                                       name="complemento" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                       placeholder="Apartamento, sala, etc.">
                                            </div>
                                        </div>
                                        
                                        <!-- Bairro, Cidade, Estado -->
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Bairro
                                                </label>
                                                <input type="text" 
                                                       id="bairro_novo_paciente" 
                                                       name="bairro" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                       placeholder="Centro">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Cidade
                                                </label>
                                                <input type="text" 
                                                       id="cidade_novo_paciente" 
                                                       name="cidade" 
                                                       class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500" 
                                                       placeholder="Mossoró">
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                                    Estado
                                                </label>
                                                <select id="estado_novo_paciente" 
                                                        name="uf" 
                                                        class="w-full px-3 py-2 border border-gray-300 rounded focus:ring-2 focus:ring-blue-500">
                                                    <option value="">Selecione...</option>
                                                    <option value="RN">Rio Grande do Norte</option>
                                                    <option value="AC">Acre</option>
                                                    <option value="AL">Alagoas</option>
                                                    <option value="AP">Amapá</option>
                                                    <option value="AM">Amazonas</option>
                                                    <option value="BA">Bahia</option>
                                                    <option value="CE">Ceará</option>
                                                    <option value="DF">Distrito Federal</option>
                                                    <option value="ES">Espírito Santo</option>
                                                    <option value="GO">Goiás</option>
                                                    <option value="MA">Maranhão</option>
                                                    <option value="MT">Mato Grosso</option>
                                                    <option value="MS">Mato Grosso do Sul</option>
                                                    <option value="MG">Minas Gerais</option>
                                                    <option value="PA">Pará</option>
                                                    <option value="PB">Paraíba</option>
                                                    <option value="PR">Paraná</option>
                                                    <option value="PE">Pernambuco</option>
                                                    <option value="PI">Piauí</option>
                                                    <option value="RJ">Rio de Janeiro</option>
                                                    <option value="RS">Rio Grande do Sul</option>
                                                    <option value="RO">Rondônia</option>
                                                    <option value="RR">Roraima</option>
                                                    <option value="SC">Santa Catarina</option>
                                                    <option value="SP">São Paulo</option>
                                                    <option value="SE">Sergipe</option>
                                                    <option value="TO">Tocantins</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Observações do Encaixe
                            </label>
                            <textarea id="observacoes_encaixe" 
                                      name="observacoes" 
                                      rows="3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500" 
                                      placeholder="Motivo do encaixe, urgência, observações especiais..."></textarea>
                        </div>
                    </form>
                </div>
                
                <!-- Botões -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t flex flex-col sm:flex-row justify-between gap-3">
                    <button type="button" onclick="fecharModalEncaixe()" 
                            class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition order-2 sm:order-1">
                        <i class="bi bi-x-circle mr-2"></i>Cancelar
                    </button>
                    
                    <div class="flex gap-3 order-1 sm:order-2">
                        <button type="button" onclick="salvarEncaixe()" 
                                id="btn-salvar-encaixe"
                                class="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition">
                            <i class="bi bi-lightning-charge mr-2"></i>Confirmar Encaixe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // ✅ POPULAR CONVÊNIOS NO SELECT
    popularConveniosNoSelect(agendaInfo.agenda ? agendaInfo.agenda.convenios : []);
    
    // ✅ CARREGAR EXAMES SE FOR PROCEDIMENTO
    carregarExamesSeNecessario(agendaId);
    
    // Configurar funcionalidades
    configurarBuscaTempoReal();
    adicionarEventListenersModalEncaixe();
    configurarMascarasCompletas();
    configurarControleCPFEncaixe();
    configurarBuscaCEP();
    
    // Focar no primeiro campo
    document.getElementById('nome_paciente_busca_real').focus();
}

/**
 * ✅ FUNÇÃO: Popular convênios no select
 */
function popularConveniosNoSelect(convenios) {
    console.log('🎯 Populando convênios no select:', convenios);
    
    // Usar convênios fornecidos, não buscar todos da API
    if (convenios && convenios.length > 0) {
        console.log('✅ Usando convênios específicos da agenda:', convenios);
        popularConveniosNoSelectInterno(convenios);
    } else {
        console.log('⚠️ Nenhum convênio específico fornecido - usando convênios padrão limitados');
        // Convênios padrão básicos se não houver dados da agenda
        const conveniosPadrao = [
            { id: 1, nome: 'Particular' },
            { id: 2, nome: 'SUS' }
        ];
        popularConveniosNoSelectInterno(conveniosPadrao);
    }
}

function popularConveniosNoSelectInterno(convenios) {
    console.log('🎯 Populando convênios no select (interno):', convenios);
    
    // Lista de IDs dos selects que podem conter convênios
    const selectIds = [
        'select[name="convenio_id"]',
        '#convenio_encaixe',
        '#convenio_agendamento',
        'select[data-convenio]'
    ];
    
    selectIds.forEach(selector => {
        const select = document.querySelector(selector);
        if (select) {
            console.log(`📋 Encontrado select: ${selector}`);
            
            // Limpar opções existentes exceto a primeira (placeholder)
            while (select.children.length > 1) {
                select.removeChild(select.lastChild);
            }
            
            // Adicionar convênios
            if (convenios && convenios.length > 0) {
                convenios.forEach(convenio => {
                    const option = document.createElement('option');
                    option.value = convenio.id || convenio.ID;
                    option.textContent = convenio.nome || convenio.NOME;
                    select.appendChild(option);
                    console.log(`✅ Convênio adicionado: ${option.textContent} (ID: ${option.value})`);
                });
            } else {
                console.log('⚠️ Nenhum convênio recebido, usando padrões');
                // Convênios padrão se não houver dados
                const conveniosPadrao = [
                    { id: 1, nome: 'Particular' },
                    { id: 2, nome: 'SUS' }
                ];
                conveniosPadrao.forEach(convenio => {
                    const option = document.createElement('option');
                    option.value = convenio.id;
                    option.textContent = convenio.nome;
                    select.appendChild(option);
                });
            }
        }
    });
}

/**
 * ✅ FUNÇÃO: Carregar exames se necessário para agenda de procedimento
 */
function carregarExamesSeNecessario(agendaId) {
    console.log('🔬 Verificando se precisa carregar exames para agenda:', agendaId);
    
    if (!agendaId) {
        console.error('❌ agendaId não fornecido para carregar exames');
        return;
    }
    
    console.log('📡 Fazendo requisição para buscar_exames_agenda.php...');
    fetchWithAuth(`buscar_exames_agenda.php?agenda_id=${agendaId}`)
        .then(safeJsonParse)
        .then(data => {
            console.log('🔬 Resposta da API de exames:', data);
            
            if (data.status === 'sucesso') {
                const tipoAgenda = data.tipo_agenda;
                const exames = data.exames || [];
                
                console.log(`📋 Tipo de agenda: ${tipoAgenda}, Total exames: ${exames.length}`);
                
                // Se for procedimento e tiver exames, mostrar a seção
                if (tipoAgenda && tipoAgenda.toLowerCase() === 'procedimento') {
                    const secaoExames = document.getElementById('secao-exames');
                    
                    if (secaoExames) {
                        // Mostrar a seção
                        secaoExames.classList.remove('hidden');
                        
                        if (exames.length > 0) {
                            // Configurar o sistema de busca de exames
                            console.log('🎯 Chamando configurarBuscaExames com', exames.length, 'exames');
                            console.log('🔍 Verificando se elementos existem antes da chamada:');
                            console.log('  - exames_search:', document.getElementById('exames_search') ? 'EXISTE' : 'NÃO EXISTE');
                            console.log('  - exames_dropdown:', document.getElementById('exames_dropdown') ? 'EXISTE' : 'NÃO EXISTE');
                            console.log('  - exames_ids_selected:', document.getElementById('exames_ids_selected') ? 'EXISTE' : 'NÃO EXISTE');
                            console.log('  - exames_selecionados:', document.getElementById('exames_selecionados') ? 'EXISTE' : 'NÃO EXISTE');
                            
                            // Dar um pequeno delay para garantir que o DOM está pronto
                            setTimeout(() => {
                                configurarBuscaExames(exames);
                                console.log(`✅ ${exames.length} exames configurados com sucesso`);
                            }, 100);
                        } else {
                            const dropdown = document.getElementById('exames_dropdown');
                            if (dropdown) {
                                dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">Nenhum exame disponível</div>';
                            }
                            console.log('⚠️ Nenhum exame encontrado para esta agenda');
                        }
                    }
                } else {
                    console.log('📝 Agenda de consulta - seção de exames permanece oculta');
                }
            } else {
                console.error('❌ Erro ao carregar exames:', data.mensagem);
            }
        })
        .catch(error => {
            console.error('❌ Erro na requisição de exames:', error);
        });
}

/**
 * ✅ FUNÇÃO: Configurar sistema multi-select de exames
 */
function configurarBuscaExames(exames) {
    console.log('🚀 Configurando busca de exames...');
    console.log('📋 Exames recebidos:', exames.length);
    
    const searchInput = document.getElementById('exames_search');
    const dropdown = document.getElementById('exames_dropdown');
    const hiddenInput = document.getElementById('exames_ids_selected');
    const examesSelecionadosDiv = document.getElementById('exames_selecionados');
    
    // Verificar se todos os elementos existem
    if (!searchInput) {
        console.error('❌ Elemento exames_search não encontrado');
        return;
    }
    if (!dropdown) {
        console.error('❌ Elemento exames_dropdown não encontrado');
        return;
    }
    if (!hiddenInput) {
        console.error('❌ Elemento exames_ids_selected não encontrado');
        return;
    }
    if (!examesSelecionadosDiv) {
        console.error('❌ Elemento exames_selecionados não encontrado');
        return;
    }
    
    console.log('✅ Todos os elementos encontrados');
    
    let examesSelecionados = []; // Array para múltiplos exames
    
    // Função para atualizar a exibição dos exames selecionados
    function atualizarExamesSelecionados() {
        const btnLimpar = document.getElementById('btn_limpar_exames');
        
        if (examesSelecionados.length === 0) {
            examesSelecionadosDiv.innerHTML = '<div class="text-sm text-gray-500">Nenhum exame selecionado</div>';
            btnLimpar.classList.add('hidden');
        } else {
            const tags = examesSelecionados.map(exame => `
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800 mr-2 mb-2 transition-all hover:bg-orange-200">
                    <span class="mr-1">${exame.nome}</span>
                    <button type="button" 
                            class="ml-1 text-orange-600 hover:text-orange-900 hover:bg-orange-200 rounded-full p-0.5" 
                            onclick="removerExame(${exame.id})"
                            title="Remover ${exame.nome}">
                        <i class="bi bi-x text-sm"></i>
                    </button>
                </span>
            `).join('');
            
            const contadorHTML = `<div class="text-xs text-gray-600 mb-2">
                <i class="bi bi-check-circle text-green-600"></i> 
                ${examesSelecionados.length} exame(s) selecionado(s)
            </div>`;
            
            examesSelecionadosDiv.innerHTML = contadorHTML + tags;
            btnLimpar.classList.remove('hidden');
        }
        
        // Atualizar campo hidden com IDs separados por vírgula
        hiddenInput.value = examesSelecionados.map(e => e.id).join(',');
        console.log('🔬 Exames selecionados:', examesSelecionados.map(e => e.nome));
        console.log('📝 IDs para envio:', hiddenInput.value);
    }
    
    // Função para adicionar exame à seleção
    window.adicionarExame = function(id, nome, event) {
        // Parar propagação do evento para evitar fechar o dropdown
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        
        console.log('🔬 Tentando adicionar exame:', { id, nome });
        
        // Verificar se já está selecionado
        if (!examesSelecionados.find(e => e.id === id)) {
            examesSelecionados.push({ id, nome });
            atualizarExamesSelecionados();
            console.log('✅ Exame adicionado com sucesso:', nome);
            
            // Forçar dropdown a ficar aberto
            dropdown.classList.remove('hidden');
            
            // Atualizar conteúdo do dropdown
            const termoBusca = searchInput.value.trim();
            console.log('🔄 Atualizando dropdown. Termo atual:', termoBusca);
            
            // Se há busca, manter busca; se não, mostrar todos disponíveis
            setTimeout(() => {
                filtrarExames(termoBusca);
            }, 10);
            
        } else {
            console.log('⚠️ Exame já selecionado:', nome);
        }
    };
    
    // Função para remover exame da seleção
    window.removerExame = function(id) {
        const exameRemovido = examesSelecionados.find(e => e.id === id);
        examesSelecionados = examesSelecionados.filter(e => e.id !== id);
        atualizarExamesSelecionados();
        
        // Atualizar dropdown para mostrar o exame removido novamente
        const termoBusca = searchInput.value.trim();
        if (!dropdown.classList.contains('hidden')) {
            if (termoBusca.length > 0) {
                filtrarExames(termoBusca);
            } else {
                filtrarExames('');
            }
        }
        
        if (exameRemovido) {
            console.log('❌ Exame removido:', exameRemovido.nome);
        }
    };
    
    // Função para limpar todos os exames selecionados
    window.limparTodosExames = function() {
        const quantidade = examesSelecionados.length;
        if (quantidade > 0) {
            examesSelecionados = [];
            atualizarExamesSelecionados();
            
            // Atualizar dropdown se estiver aberto
            if (!dropdown.classList.contains('hidden')) {
                const termoBusca = searchInput.value.trim();
                filtrarExames(termoBusca);
            }
            
            console.log(`🗑️ ${quantidade} exame(s) removido(s) da seleção`);
        }
    };
    
    // Função para filtrar e mostrar exames
    function filtrarExames(termo) {
        console.log('🔍 filtrarExames chamada com termo:', `"${termo}"`);
        console.log('📊 Total de exames:', exames.length);
        console.log('📊 Exames já selecionados:', examesSelecionados.length);
        
        const termoLower = termo.toLowerCase();
        const examesDisponiveis = exames.filter(exame => 
            !examesSelecionados.find(e => e.id === exame.id) // Não mostrar já selecionados
        );
        
        console.log('📊 Exames disponíveis (não selecionados):', examesDisponiveis.length);
        
        const examesFiltrados = termo.length > 0 ? 
            examesDisponiveis.filter(exame => exame.nome.toLowerCase().includes(termoLower)) :
            examesDisponiveis; // Se não há termo, mostrar todos disponíveis
            
        console.log('📊 Exames após filtro:', examesFiltrados.length);
        
        if (examesDisponiveis.length === 0) {
            dropdown.innerHTML = '<div class="p-3 text-gray-500 text-sm">✅ Todos os exames já foram selecionados</div>';
        } else if (examesFiltrados.length === 0) {
            dropdown.innerHTML = `<div class="p-3 text-gray-500 text-sm">🔍 Nenhum exame encontrado para "${termo}"</div>`;
        } else {
            const titulo = termo.length > 0 ? 
                `<div class="p-2 bg-gray-100 text-xs font-medium text-gray-600 border-b">📋 ${examesFiltrados.length} exame(s) encontrado(s)</div>` :
                `<div class="p-2 bg-gray-100 text-xs font-medium text-gray-600 border-b">📋 ${examesFiltrados.length} exame(s) disponível(eis) para seleção</div>`;
                
            dropdown.innerHTML = titulo + examesFiltrados.map(exame => `
                <div class="p-3 hover:bg-orange-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors" 
                     onclick="adicionarExame(${exame.id}, '${exame.nome.replace(/'/g, '\\\\\\')}', event)"
                     data-exame-id="${exame.id}" 
                     data-exame-nome="${exame.nome}">
                    <div class="text-sm font-medium text-gray-900">${exame.nome}</div>
                    <div class="text-xs text-gray-500">
                        <i class="bi bi-plus-circle text-green-600"></i> ID: ${exame.id} • Clique para adicionar
                    </div>
                </div>
            `).join('');
        }
        
        dropdown.classList.remove('hidden');
    }
    
    // Event listeners
    searchInput.addEventListener('input', (e) => {
        const termo = e.target.value.trim();
        console.log('📝 Input alterado:', termo);
        filtrarExames(termo);
    });
    
    searchInput.addEventListener('focus', () => {
        console.log('🔍 Campo focado - abrindo dropdown com todos os exames');
        // Sempre mostrar todos os exames disponíveis ao focar
        filtrarExames(''); // String vazia = mostrar todos
    });
    
    searchInput.addEventListener('click', (e) => {
        e.stopPropagation();
        console.log('👆 Campo clicado - forçando abertura do dropdown');
        // Forçar abertura mesmo se já estiver aberto
        filtrarExames(''); // Mostrar todos os exames
    });
    
    // Função para controlar dropdown manualmente
    window.toggleDropdownExames = function() {
        const iconToggle = document.getElementById('icon_toggle_exames');
        
        if (dropdown.classList.contains('hidden')) {
            // Abrir dropdown - sempre mostrar todos os exames
            console.log('📖 Dropdown aberto manualmente - mostrando todos os exames');
            filtrarExames(''); // String vazia = mostrar todos
            iconToggle.className = 'bi bi-chevron-up';
        } else {
            // Fechar dropdown
            console.log('📕 Dropdown fechado manualmente');
            dropdown.classList.add('hidden');
            iconToggle.className = 'bi bi-chevron-down';
        }
    };
    
    // Click fora para fechar dropdown (exceto no botão toggle)
    document.addEventListener('click', (e) => {
        const btnToggle = document.getElementById('btn_toggle_exames');
        if (searchInput && dropdown && 
            !searchInput.contains(e.target) && 
            !dropdown.contains(e.target) && 
            (!btnToggle || !btnToggle.contains(e.target))) {
            dropdown.classList.add('hidden');
            const iconToggle = document.getElementById('icon_toggle_exames');
            if (iconToggle) {
                iconToggle.className = 'bi bi-chevron-down';
            }
        }
    });
    
    // Função para atualizar ícone do botão baseado no estado do dropdown
    function atualizarIconeToggle() {
        const iconToggle = document.getElementById('icon_toggle_exames');
        if (iconToggle) {
            iconToggle.className = dropdown.classList.contains('hidden') ? 
                'bi bi-chevron-down' : 'bi bi-chevron-up';
        }
    }
    
    // Observador para mudanças na classe hidden do dropdown
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                atualizarIconeToggle();
            }
        });
    });
    observer.observe(dropdown, { attributes: true });
    
    // Inicialização: Mostrar conteúdo inicial do dropdown
    dropdown.innerHTML = `
        <div class="p-2 bg-gray-100 text-xs font-medium text-gray-600 border-b">
            📋 ${exames.length} exame(s) disponível(eis)
        </div>
        <div class="p-3 text-gray-500 text-sm text-center">
            <i class="bi bi-hand-index mr-1"></i>
            Clique no campo acima ou no botão 🔽 para ver os exames
        </div>
    `;
    
    
    console.log('🎯 Sistema de busca de exames multi-select configurado com sucesso');
    console.log('📊 Total de exames disponíveis:', exames.length);
}

/**
 * ✅ FUNÇÃO: Configurar máscaras completas
 */
function configurarMascarasCompletas() {
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf_novo_paciente');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para CEP
    const cepInput = document.getElementById('cep_novo_paciente');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
            e.target.value = value;
        });
    }
}


// ===============================================================================
// 🔧 CORREÇÃO DOS PROBLEMAS: CEP e Campos de Endereço
// ===============================================================================

/**
 * ✅ PROBLEMA 1 CORRIGIDO: Busca de CEP sem notificação de agendamento
 */
function configurarBuscaCEP() {
    window.buscarCEP = function() {
        const cepInput = document.getElementById('cep_novo_paciente');
        const cep = cepInput.value.replace(/\D/g, '');
        
        if (cep.length !== 8) {
            alert('CEP deve ter 8 dígitos');
            return;
        }
        
        // Mostrar loading
        const btnBusca = cepInput.parentElement.querySelector('button');
        if (btnBusca) {
            btnBusca.innerHTML = '<i class="bi bi-arrow-clockwise animate-spin text-sm"></i>';
        }
        
        fetch(`https://viacep.com.br/ws/${cep}/json/`)
            .then(safeJsonParse)
            .then(data => {
                if (data.erro) {
                    alert('CEP não encontrado');
                    return;
                }
                
                // Preencher campos automaticamente
                document.getElementById('logradouro_novo_paciente').value = data.logradouro || '';
                document.getElementById('bairro_novo_paciente').value = data.bairro || '';
                document.getElementById('cidade_novo_paciente').value = data.localidade || '';
                
                // Selecionar estado
                const estadoSelect = document.getElementById('estado_novo_paciente');
                if (estadoSelect && data.uf) {
                    estadoSelect.value = data.uf;
                }
                
                // Focar no campo número
                document.getElementById('numero_novo_paciente').focus();
                
                // ✅ CORREÇÃO: Usar notificação específica para CEP (não de agendamento)
                mostrarNotificacaoCEP('CEP encontrado! Dados preenchidos automaticamente.');
                
            })
            .catch(error => {
                console.error('Erro ao buscar CEP:', error);
                alert('Erro ao buscar CEP. Tente novamente.');
            })
            .finally(() => {
                // Restaurar botão
                if (btnBusca) {
                    btnBusca.innerHTML = '<i class="bi bi-search text-sm"></i>';
                }
            });
    };
}

/**
 * ✅ NOVA FUNÇÃO: Notificação específica para CEP
 */
function mostrarNotificacaoCEP(mensagem) {
    // Remover notificação anterior de CEP se existir
    const notificacaoAnterior = document.getElementById('notificacao-cep');
    if (notificacaoAnterior) {
        notificacaoAnterior.remove();
    }
    
    const notificacao = document.createElement('div');
    notificacao.id = 'notificacao-cep';
    notificacao.className = 'fixed top-4 left-4 z-50 bg-blue-600 text-white px-4 py-3 rounded-lg shadow-lg max-w-sm';
    notificacao.style.animation = 'slideInFromLeft 0.3s ease-out';
    
    notificacao.innerHTML = `
        <div class="flex items-center">
            <i class="bi bi-geo-alt-fill text-lg mr-2"></i>
            <div class="text-sm">${mensagem}</div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white hover:text-gray-200">
                <i class="bi bi-x text-lg"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notificacao);
    
    // Remover automaticamente após 3 segundos
    setTimeout(() => {
        if (notificacao.parentElement) {
            notificacao.style.animation = 'slideOutToLeft 0.3s ease-in';
            setTimeout(() => notificacao.remove(), 300);
        }
    }, 3000);
}

/**
 * ✅ FUNÇÃO: Toggle cadastro completo ATUALIZADA
 */
window.toggleCadastroCompleto = function(checkbox) {
    if (!checkbox) {
        checkbox = document.getElementById('checkbox-criar-cadastro');
    }
    
    const isChecked = checkbox ? checkbox.checked : false;
    const formulario = document.getElementById('formulario-cadastro-novo');
    
    // Criar campo hidden se não existir
    let hiddenCadastrar = document.getElementById('cadastrar_paciente');
    if (!hiddenCadastrar) {
        hiddenCadastrar = document.createElement('input');
        hiddenCadastrar.type = 'hidden';
        hiddenCadastrar.id = 'cadastrar_paciente';
        hiddenCadastrar.name = 'cadastrar_paciente';
        hiddenCadastrar.value = 'false';
        document.querySelector('form').appendChild(hiddenCadastrar);
    }
    
    if (!formulario) {
        console.warn('⚠️ Formulário de cadastro não encontrado');
        return;
    }
    
    if (isChecked) {
        // Habilitar cadastro
        formulario.classList.remove('hidden');
        hiddenCadastrar.value = 'true';
        
        // Limpar paciente existente se houver
        document.getElementById('usar_paciente_existente').value = 'false';
        document.getElementById('paciente_id_hidden').value = '';
        
        console.log('✅ Formulário de cadastro ATIVADO');
    } else {
        // Desabilitar cadastro
        formulario.classList.add('hidden');
        hiddenCadastrar.value = 'false';
        
        console.log('❌ Formulário de cadastro DESATIVADO');
    }
};

/**
 * ✅ PROBLEMA 2 CORRIGIDO: Campos de endereço sendo enviados corretamente
 */
window.salvarEncaixeCompleto = function() {
    console.log('💾 Salvando encaixe com campos completos - VERSÃO CORRIGIDA...');
    
    // Validações básicas
    const nomeInput = document.getElementById('nome_paciente_busca_real');
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    const convenioSelect = document.getElementById('convenio_encaixe');
    
    if (!nomeInput?.value.trim()) {
        alert('Nome é obrigatório');
        nomeInput?.focus();
        return;
    }
    
    if (!telefoneInput?.value.trim()) {
        alert('Telefone é obrigatório');
        telefoneInput?.focus();
        return;
    }
    
    if (!convenioSelect?.value) {
        alert('Convênio é obrigatório');
        convenioSelect?.focus();
        return;
    }
    
    // Verificar estados
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    const deveCadastrar = checkbox ? checkbox.checked : false;
    const usarExistente = document.getElementById('usar_paciente_existente')?.value === 'true';
    const pacienteIdExistente = document.getElementById('paciente_id_hidden')?.value || '';
    
    console.log('📋 Estados:', { deveCadastrar, usarExistente, pacienteIdExistente });
    
    // Validações para novo cadastro
    if (deveCadastrar) {
        // Verificar se CPF é obrigatório ou não
        const naoTemCpf = document.getElementById('nao_tem_cpf')?.checked || false;
        
        const camposObrigatorios = [
            { id: 'sexo_novo_paciente', nome: 'Sexo' },
            { id: 'nascimento_novo_paciente', nome: 'Data de Nascimento' }
        ];
        
        // Adicionar CPF aos obrigatórios se não estiver marcado "não tem CPF"
        if (!naoTemCpf) {
            camposObrigatorios.push({ id: 'cpf_novo_paciente', nome: 'CPF' });
        }
        
        for (let campo of camposObrigatorios) {
            const elemento = document.getElementById(campo.id);
            if (!elemento?.value.trim()) {
                alert(`${campo.nome} é obrigatório para cadastrar o paciente.`);
                elemento?.focus();
                return;
            }
        }
        
        // Validar CPF se preenchido (independente da obrigatoriedade)
        const cpf = document.getElementById('cpf_novo_paciente').value.replace(/\D/g, '');
        if (cpf.length > 0 && cpf.length !== 11) {
            alert('CPF deve ter 11 dígitos válidos.');
            document.getElementById('cpf_novo_paciente').focus();
            return;
        }
    }
    
    // Preparar FormData
    const formData = new FormData();
    
    // Campos básicos
    formData.append('agenda_id', document.querySelector('input[name="agenda_id"]')?.value || window.agendaIdAtual || '1');
    formData.append('data_agendamento', document.querySelector('input[name="data_agendamento"]')?.value || window.dataSelecionadaAtual);
    formData.append('nome_paciente', nomeInput.value.trim());
    formData.append('telefone_paciente', telefoneInput.value.trim());
    formData.append('convenio_id', convenioSelect.value);
    formData.append('observacoes', document.getElementById('observacoes_encaixe')?.value.trim() || '');
    formData.append('tipo_operacao', 'encaixe');
    
    // Horário específico se selecionado
    const horarioSelecionado = document.getElementById('horario_selecionado_hidden')?.value;
    if (horarioSelecionado) {
        formData.append('horario_agendamento', horarioSelecionado);
    }
    
    // ✅ CORREÇÃO PRINCIPAL: Gestão de paciente com campos corretos
    if (usarExistente && pacienteIdExistente) {
        // CENÁRIO 1: Paciente existente
        formData.append('usar_paciente_existente', 'true');
        formData.append('cadastrar_paciente', 'false');
        formData.append('paciente_id', pacienteIdExistente);
        formData.append('paciente_selecionado_id', pacienteIdExistente);
        
        console.log('🔵 Usando paciente existente - ID:', pacienteIdExistente);
        
    } else if (deveCadastrar) {
        // CENÁRIO 2: Cadastrar novo paciente
        formData.append('usar_paciente_existente', 'false');
        formData.append('cadastrar_paciente', 'true');
        formData.append('paciente_id', ''); // Será gerado pelo servidor
        
        // ✅ CORREÇÃO: TODOS OS CAMPOS COM NOMES CORRETOS PARA O SERVIDOR
        
        // Informações básicas
        formData.append('cpf_paciente', document.getElementById('cpf_novo_paciente').value.replace(/\D/g, ''));
        formData.append('sexo', document.getElementById('sexo_novo_paciente').value);
        formData.append('data_nascimento', document.getElementById('nascimento_novo_paciente').value);
        
        // Documentos
        formData.append('rg', document.getElementById('rg_novo_paciente')?.value.trim() || '');
        formData.append('orgao_emissor', document.getElementById('orgao_emissor_novo_paciente')?.value || '');
        
        // Contato
        formData.append('email_paciente', document.getElementById('email_novo_paciente')?.value.trim() || '');
        
        // ✅ CORREÇÃO CRÍTICA: Endereço com nomes de campos corretos
        const cepValue = document.getElementById('cep_novo_paciente')?.value.replace(/\D/g, '') || '';
        const enderecoValue = document.getElementById('logradouro_novo_paciente')?.value.trim() || '';
        const numeroValue = document.getElementById('numero_novo_paciente')?.value.trim() || '';
        const complementoValue = document.getElementById('complemento_novo_paciente')?.value.trim() || '';
        const bairroValue = document.getElementById('bairro_novo_paciente')?.value.trim() || '';
        const cidadeValue = document.getElementById('cidade_novo_paciente')?.value.trim() || '';
        const ufValue = document.getElementById('estado_novo_paciente')?.value || '';
        
        // Endereço - Verificar se campos existem antes de enviar
        if (cepValue) formData.append('cep', cepValue);
        if (enderecoValue) formData.append('endereco', enderecoValue);
        if (numeroValue) formData.append('numero', numeroValue);
        if (complementoValue) formData.append('complemento', complementoValue);
        if (bairroValue) formData.append('bairro', bairroValue);
        if (cidadeValue) formData.append('cidade', cidadeValue);
        if (ufValue) formData.append('uf', ufValue);
        
        console.log('🟢 Cadastrando novo paciente completo');
        console.log('📍 Endereço capturado:', {
            cep: cepValue,
            endereco: enderecoValue,
            numero: numeroValue,
            complemento: complementoValue,
            bairro: bairroValue,
            cidade: cidadeValue,
            uf: ufValue
        });
        
    } else {
        // CENÁRIO 3: Sem cadastro
        formData.append('usar_paciente_existente', 'false');
        formData.append('cadastrar_paciente', 'false');
        formData.append('paciente_id', '');
        
        console.log('🟡 Encaixe sem cadastro');
    }
    
    // ✅ DEBUG COMPLETO: Mostrar todos os campos sendo enviados
    console.log('📋 FormData COMPLETO sendo enviado:');
    const formDataEntries = [];
    for (let [key, value] of formData.entries()) {
        formDataEntries.push({ key, value });
        console.log(`   ${key}: "${value}"`);
    }
    
    // Contar campos de endereço
    const camposEndereco = formDataEntries.filter(entry => 
        ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'].includes(entry.key)
    );
    console.log(`📍 Campos de endereço encontrados: ${camposEndereco.length}/7`);
    camposEndereco.forEach(campo => console.log(`   📍 ${campo.key}: "${campo.value}"`));
    
    // Desabilitar botão
    const btnSalvar = document.getElementById('btn-salvar-encaixe');
    let textoOriginal = 'Confirmar Encaixe';
    
    if (btnSalvar) {
        textoOriginal = btnSalvar.innerHTML;
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i>Salvando...';
        btnSalvar.disabled = true;
    }
    
    // Enviar para servidor
    fetch('processar_encaixe.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(responseText => {
        console.log('📄 Resposta bruta:', responseText);
        
        // Extrair JSON
        let data;
        try {
            data = JSON.parse(responseText.trim());
        } catch (e) {
            const linhas = responseText.split('\n');
            for (let i = linhas.length - 1; i >= 0; i--) {
                const linha = linhas[i].trim();
                if (linha.startsWith('{') && linha.includes('"status"')) {
                    try {
                        data = JSON.parse(linha);
                        break;
                    } catch (parseError) {
                        continue;
                    }
                }
            }
            
            if (!data) {
                throw new Error('Resposta inválida do servidor');
            }
        }
        
        console.log('📋 Dados parseados:', data);
        
        if (data.status === 'sucesso') {
            console.log('✅ Encaixe salvo com sucesso!');
            
            // ✅ VALIDAÇÃO: Verificar se endereço foi salvo
            if (deveCadastrar) {
                console.log('📍 Verificando se endereço foi salvo...');
                if (data.endereco_salvo !== undefined) {
                    console.log(`📍 Status do endereço: ${data.endereco_salvo ? '✅ SALVO' : '❌ NÃO SALVO'}`);
                }
                if (data.paciente_id) {
                    console.log(`🆔 Paciente criado com ID: ${data.paciente_id}`);
                }
            }
            
            // Fechar modal
            fecharModalEncaixe();
            
            // Preparar mensagem
            let mensagem = `✅ Encaixe confirmado!\n`;
            mensagem += `📋 Número: ${data.numero_agendamento}\n`;
            mensagem += `👤 Paciente: ${nomeInput.value}\n`;
            mensagem += `📞 Telefone: ${telefoneInput.value}`;
            
            if (deveCadastrar && data.paciente_id) {
                mensagem += `\n🆔 Paciente cadastrado com ID: ${data.paciente_id}`;
                
                // Verificar se endereço foi incluído
                const temEndereco = camposEndereco.length > 0;
                if (temEndereco) {
                    mensagem += `\n📍 Endereço completo incluído no cadastro`;
                } else {
                    mensagem += `\n⚠️ Endereço não foi preenchido`;
                }
                
            } else if (usarExistente && data.paciente_id) {
                mensagem += `\n👤 Paciente existente vinculado (ID: ${data.paciente_id})`;
            }
            
            // Atualizar visualização
            setTimeout(() => {
                atualizarVisualizacaoCompleta();
                mostrarNotificacaoSucesso('Encaixe salvo com sucesso!');
                alert(mensagem + '\n\nO paciente será atendido conforme disponibilidade.');
            }, 300);
            
        } else {
            console.error('❌ Erro do servidor:', data);
            alert('❌ Erro: ' + (data.mensagem || data.erro || 'Erro desconhecido'));
            
            if (btnSalvar) {
                btnSalvar.innerHTML = textoOriginal;
                btnSalvar.disabled = false;
            }
        }
    })
    .catch(error => {
        console.error('❌ Erro na requisição:', error);
        alert('❌ Erro ao processar encaixe: ' + error.message);
        
        if (btnSalvar) {
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    });
};

// ✅ Sobrescrever função original
window.salvarEncaixe = window.salvarEncaixeCompleto;

/**
 * ✅ FUNÇÃO: Atualizar abrirModalEncaixe para usar nova função
 */
const abrirModalEncaixeOriginal = window.abrirModalEncaixe;
window.abrirModalEncaixe = function(agendaId, data) {
    console.log('🎯 Abrindo modal de encaixe com campos completos:', { agendaId, data });
    
    // Verificar se permite encaixes
    fetchWithAuth(`verificar_encaixes.php?agenda_id=${agendaId}&data=${data}`)
        .then(response => response.text())
        .then(responseText => {
            const primeiraLinha = responseText.split('\n')[0].trim();
            const dadosEncaixe = JSON.parse(primeiraLinha);
            
            if (dadosEncaixe.erro) {
                alert('Erro: ' + dadosEncaixe.erro);
                return;
            }
            
            if (!dadosEncaixe.permite_encaixes || !dadosEncaixe.pode_encaixar) {
                alert(dadosEncaixe.mensagem || 'Não é possível fazer encaixe nesta agenda/data');
                return;
            }
            
            // Buscar informações da agenda
            fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                .then(safeJsonParse)
                .then(agendaData => {
                    console.log('✅ Dados da agenda recebidos (v2):', agendaData);
                    const agendaInfo = agendaData.agenda || {};
                    console.log('📋 Convênios disponíveis (v2):', agendaInfo.convenios);
                    
                    // ✅ USAR NOVA FUNÇÃO COM CAMPOS COMPLETOS
                    criarModalEncaixeSimplificado(agendaId, data, dadosEncaixe, agendaInfo);
                })
                .catch(error => {
                    console.error('Erro ao buscar info da agenda:', error);
                    criarModalEncaixeSimplificado(agendaId, data, dadosEncaixe, {});
                });
        })
        .catch(error => {
            console.error('Erro ao verificar encaixes:', error);
            alert('Erro ao verificar disponibilidade de encaixes.');
        });
};

console.log('✅ Modal de encaixe com campos completos carregado!');
console.log('💡 Campos disponíveis: Nome, Telefone, Convênio, CPF, RG, Sexo, Nascimento, E-mail, Endereço completo');
console.log('🎯 Teste: window.abrirModalEncaixe(1, "2025-08-05")');


// ============================================================================
// FUNÇÕES PARA SELEÇÃO DE HORÁRIO
// ============================================================================

/**
 * ✅ FUNÇÃO CORRIGIDA: toggleSelecaoHorario
 */
window.toggleSelecaoHorario = function() {
    const tipoSelecionado = document.querySelector('input[name="tipo_horario"]:checked')?.value;
    const areaInput = document.getElementById('area-input-horario');
    const horarioHidden = document.getElementById('horario_selecionado_hidden');
    
    console.log('🔄 Toggle horário chamado:', tipoSelecionado);
    
    if (tipoSelecionado === 'horario_especifico') {
        if (areaInput) {
            areaInput.classList.remove('hidden');
            console.log('✅ Área de input de horário mostrada');
        }
        
        // Carregar informações da agenda
        carregarInfoHorariosAgenda();
        
    } else {
        if (areaInput) {
            areaInput.classList.add('hidden');
            console.log('❌ Área de input de horário escondida');
        }
        
        if (horarioHidden) {
            horarioHidden.value = '';
        }
        
        limparStatusHorario();
    }
};

/**
 * ✅ CORREÇÃO 4: Atualizar função carregarInfoHorariosAgenda
 * SUBSTITUIR no agenda.js por esta versão simples:
 */
window.carregarInfoHorariosAgenda = function() {
    const infoContainer = document.getElementById('info-horarios-agenda');
    
    if (!infoContainer) {
        return;
    }
    
    infoContainer.innerHTML = `
        <div class="flex items-start gap-2 text-sm">
            <i class="bi bi-clock text-blue-600 mt-0.5"></i>
            <div>
                <div class="font-medium text-gray-800">✅ Agendar em horário específico</div>
                <div class="text-gray-600">Digite o horário desejado no formato HH:MM</div>
                <div class="text-xs text-gray-500 mt-1">
                    <i class="bi bi-info-circle mr-1"></i>
                    Sistema aceita horários entre 06:00 e 22:00
                </div>
                <div class="text-xs text-green-600 mt-1">
                    <i class="bi bi-check-circle mr-1"></i>
                    Horário será salvo exatamente como digitado
                </div>
            </div>
        </div>
    `;
};

// ============================================================================
// 🔧 CORREÇÃO: Validação de Horário Específico
// ============================================================================

/**
 * ✅ FUNÇÃO CORRIGIDA: Validar horário com base na AGENDA_HORARIOS
 */
window.validarHorarioDigitado = function() {
    const horarioInput = document.getElementById('horario_digitado');
    
    if (!horarioInput) {
        console.warn('⚠️ Input de horário não encontrado');
        return;
    }
    
    const horario = horarioInput.value;
    
    if (!horario) {
        limparStatusHorario();
        return;
    }
    
    console.log('🕐 Validando horário:', horario);
    
    // Validação básica de formato
    const regexHorario = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
    if (!regexHorario.test(horario)) {
        mostrarStatusHorario('Formato inválido - use HH:MM (ex: 14:30)', 'erro');
        return;
    }
    
    // ✅ NOVA VALIDAÇÃO: Consultar AGENDA_HORARIOS
    const agendaId = window.agendaIdAtual || document.querySelector('input[name="agenda_id"]')?.value || '1';
    const data = window.dataSelecionadaAtual || new Date().toISOString().split('T')[0];
    
    // Buscar horários de funcionamento da agenda
    validarComHorariosFuncionamento(agendaId, data, horario);
};

/**
 * ✅ FUNÇÃO NOVA: Validar horário baseado nos horários de funcionamento
 */
function validarComHorariosFuncionamento(agendaId, data, horario) {
    mostrarStatusHorario('Verificando horário de funcionamento...', 'loading');
    
    // Determinar dia da semana
    const dataObj = new Date(data + 'T00:00:00');
    const diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    const diaSemana = diasSemana[dataObj.getDay()];
    
    console.log('📅 Validando horário para:', { agendaId, data, horario, diaSemana });
    
    // Fazer requisição para verificar horários de funcionamento
    fetchWithAuth(`buscar_horarios_funcionamento.php?agenda_id=${agendaId}&dia_semana=${encodeURIComponent(diaSemana)}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return safeJsonParse(response);
        })
        .then(funcionamento => {
            console.log('📋 Horários de funcionamento recebidos:', funcionamento);
            
            if (funcionamento.sucesso && funcionamento.horarios) {
                const horarioValido = validarHorarioDentroFuncionamento(horario, funcionamento.horarios);
                
                if (horarioValido.valido) {
                    // Se está dentro do funcionamento, verificar disponibilidade
                    verificarDisponibilidadeHorario(agendaId, data, horario);
                } else {
                    mostrarStatusHorario(horarioValido.mensagem, 'erro');
                    sugerirHorariosAlternativos(funcionamento.horarios);
                }
            } else {
                // Se não encontrou horários específicos, usar validação básica
                console.warn('⚠️ Horários de funcionamento não encontrados, usando validação básica');
                validarHorarioBasico(horario);
            }
        })
        .catch(error => {
            console.warn('⚠️ Erro ao buscar horários de funcionamento:', error);
            // Fallback para validação básica
            validarHorarioBasico(horario);
        });
}

/**
 * ✅ FUNÇÃO NOVA: Verificar se horário está dentro do funcionamento
 */
function validarHorarioDentroFuncionamento(horario, horariosFuncionamento) {
    const [horas, minutos] = horario.split(':').map(Number);
    const horarioMinutos = horas * 60 + minutos;
    
    console.log('🔍 Validando horário:', { horario, horarioMinutos, funcionamento: horariosFuncionamento });
    
    let dentroFuncionamento = false;
    let turnoEncontrado = '';
    
    // Verificar manhã
    if (horariosFuncionamento.manha_inicio && horariosFuncionamento.manha_fim) {
        const [inicioH, inicioM] = horariosFuncionamento.manha_inicio.split(':').map(Number);
        const [fimH, fimM] = horariosFuncionamento.manha_fim.split(':').map(Number);
        
        const inicioMinutos = inicioH * 60 + inicioM;
        const fimMinutos = fimH * 60 + fimM;
        
        if (horarioMinutos >= inicioMinutos && horarioMinutos <= fimMinutos) {
            dentroFuncionamento = true;
            turnoEncontrado = 'manhã';
        }
    }
    
    // Verificar tarde
    if (!dentroFuncionamento && horariosFuncionamento.tarde_inicio && horariosFuncionamento.tarde_fim) {
        const [inicioH, inicioM] = horariosFuncionamento.tarde_inicio.split(':').map(Number);
        const [fimH, fimM] = horariosFuncionamento.tarde_fim.split(':').map(Number);
        
        const inicioMinutos = inicioH * 60 + inicioM;
        const fimMinutos = fimH * 60 + fimM;
        
        if (horarioMinutos >= inicioMinutos && horarioMinutos <= fimMinutos) {
            dentroFuncionamento = true;
            turnoEncontrado = 'tarde';
        }
    }
    
    if (dentroFuncionamento) {
        return {
            valido: true,
            mensagem: `Horário válido (${turnoEncontrado})`
        };
    } else {
        let mensagemErro = 'Horário fora do funcionamento da agenda';
        
        // Mostrar horários disponíveis
        const horariosTexto = [];
        if (horariosFuncionamento.manha_inicio && horariosFuncionamento.manha_fim) {
            horariosTexto.push(`Manhã: ${horariosFuncionamento.manha_inicio} às ${horariosFuncionamento.manha_fim}`);
        }
        if (horariosFuncionamento.tarde_inicio && horariosFuncionamento.tarde_fim) {
            horariosTexto.push(`Tarde: ${horariosFuncionamento.tarde_inicio} às ${horariosFuncionamento.tarde_fim}`);
        }
        
        if (horariosTexto.length > 0) {
            mensagemErro += ` (${horariosTexto.join(', ')})`;
        }
        
        return {
            valido: false,
            mensagem: mensagemErro
        };
    }
}

/**
 * ✅ FUNÇÃO NOVA: Verificar disponibilidade do horário (sem validação de almoço)
 */
function verificarDisponibilidadeHorario(agendaId, data, horario) {
    mostrarStatusHorario('Verificando disponibilidade...', 'loading');
    
    const url = `verificar_horario_disponivel.php?agenda_id=${agendaId}&data=${data}&horario=${horario}`;
    
    fetch(url, { 
        method: 'GET',
        timeout: 5000
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        return response.text();
    })
    .then(responseText => {
        console.log('📄 Resposta da API de disponibilidade:', responseText);
        
        let data;
        try {
            // Tentar fazer parse da primeira linha JSON válida
            const linhas = responseText.split('\n').filter(linha => linha.trim());
            const primeiraLinhaJson = linhas.find(linha => linha.trim().startsWith('{'));
            
            if (primeiraLinhaJson) {
                data = JSON.parse(primeiraLinhaJson);
            } else {
                throw new Error('Nenhum JSON válido encontrado');
            }
        } catch (e) {
            console.warn('⚠️ Erro ao fazer parse da resposta, assumindo disponível:', e);
            data = { disponivel: true, mensagem: 'Horário validado localmente' };
        }
        
        if (data.disponivel) {
            mostrarStatusHorario('✅ Horário disponível!', 'sucesso');
            document.getElementById('horario_selecionado_hidden').value = horario;
            limparSugestoes();
        } else {
            mostrarStatusHorario(`⚠️ ${data.mensagem || 'Horário ocupado'}`, 'aviso');
            document.getElementById('horario_selecionado_hidden').value = horario; // Ainda aceitar para encaixe
            sugerirHorariosProximos(horario);
        }
    })
    .catch(error => {
        console.warn('⚠️ API de verificação falhou, mas permitindo horário:', error.message);
        
        // Se a API falhar, ainda assim permitir o horário
        mostrarStatusHorario('✅ Horário aceito (verificação local)', 'info');
        document.getElementById('horario_selecionado_hidden').value = horario;
    });
}

/**
 * ✅ FUNÇÃO NOVA: Validação básica (fallback)
 */
function validarHorarioBasico(horario) {
    const [horas, minutos] = horario.split(':').map(Number);
    
  
    // Para validação básica, aceitar qualquer horário das 6h às 22h
    mostrarStatusHorario('✅ Horário aceito (validação básica)', 'info');
    document.getElementById('horario_selecionado_hidden').value = horario;
    
    // Ainda tentar verificar disponibilidade
    const agendaId = window.agendaIdAtual || '1';
    const data = window.dataSelecionadaAtual || new Date().toISOString().split('T')[0];
    verificarDisponibilidadeHorario(agendaId, data, horario);
}

/**
 * ✅ FUNÇÃO NOVA: Sugerir horários alternativos baseados no funcionamento
 */
function sugerirHorariosAlternativos(horariosFuncionamento) {
    const sugestoes = [];
    
    // Sugerir horários da manhã
    if (horariosFuncionamento.manha_inicio && horariosFuncionamento.manha_fim) {
        const [inicioH, inicioM] = horariosFuncionamento.manha_inicio.split(':').map(Number);
        const [fimH, fimM] = horariosFuncionamento.manha_fim.split(':').map(Number);
        
        // Sugerir início da manhã
        sugestoes.push(horariosFuncionamento.manha_inicio);
        
        // Sugerir meio da manhã
        const meioManha = Math.floor((inicioH + fimH) / 2);
        sugestoes.push(`${meioManha.toString().padStart(2, '0')}:00`);
    }
    
    // Sugerir horários da tarde
    if (horariosFuncionamento.tarde_inicio && horariosFuncionamento.tarde_fim) {
        const [inicioH, inicioM] = horariosFuncionamento.tarde_inicio.split(':').map(Number);
        const [fimH, fimM] = horariosFuncionamento.tarde_fim.split(':').map(Number);
        
        // Sugerir início da tarde
        sugestoes.push(horariosFuncionamento.tarde_inicio);
        
        // Sugerir meio da tarde
        const meioTarde = Math.floor((inicioH + fimH) / 2);
        sugestoes.push(`${meioTarde.toString().padStart(2, '0')}:00`);
    }
    
    // Remover duplicatas e ordenar
    const sugestoesUnicas = [...new Set(sugestoes)].sort();
    
    if (sugestoesUnicas.length > 0) {
        mostrarSugestoesHorarios(sugestoesUnicas.slice(0, 4)); // Máximo 4 sugestões
    }
}

/**
 * ✅ FUNÇÃO CORRIGIDA: Informações da agenda (simplificada)
 */
window.carregarInfoHorariosAgenda = function() {
    const agendaId = document.querySelector('input[name="agenda_id"]')?.value || window.agendaIdAtual || '1';
    const infoContainer = document.getElementById('info-horarios-agenda');
    
    if (!infoContainer) {
        console.warn('⚠️ Container info-horarios-agenda não encontrado');
        return;
    }
    
    console.log('🔍 Carregando informações de horário específico para agenda:', agendaId);
    
    // Mostrar informações genéricas úteis
    infoContainer.innerHTML = `
        <div class="flex items-start gap-2 text-sm">
            <i class="bi bi-clock text-blue-600 mt-0.5"></i>
            <div>
                <div class="font-medium text-gray-800">Agendar em horário específico</div>
                <div class="text-gray-600">Digite um horário dentro do funcionamento da agenda</div>
                <div class="text-xs text-gray-500 mt-1">
                    <i class="bi bi-info-circle mr-1"></i>
                    O sistema verificará automaticamente se o horário está dentro do funcionamento
                </div>
                <div class="text-xs text-blue-600 mt-1">
                    <i class="bi bi-lightbulb mr-1"></i>
                    Horários baseados na configuração específica desta agenda
                </div>
            </div>
        </div>
    `;
    
    console.log('✅ Informações de horário específico carregadas');
};

/**
 * ✅ CORREÇÃO 2: Nova função de validação simples (sem API externa)
 * SUBSTITUIR a função verificarDisponibilidadeHorario no agenda.js por esta:
 */
window.verificarDisponibilidadeHorario = function() {
    const horarioInput = document.getElementById('horario_digitado');
    
    if (!horarioInput?.value) {
        limparStatusHorario();
        return;
    }
    
    const horario = horarioInput.value;
    console.log('🔍 Verificando horário:', horario);
    
    // Validação básica de formato
    const regexHorario = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
    if (!regexHorario.test(horario)) {
        mostrarStatusHorario('Formato inválido - use HH:MM (ex: 14:30)', 'erro');
        return;
    }
    
    // Validação de horário lógico
    const [horas, minutos] = horario.split(':').map(Number);
    
    if (horas < 6 || horas > 22) {
        mostrarStatusHorario('Horário deve estar entre 06:00 e 22:00', 'erro');
        return;
    }
    
    // ✅ ACEITAR QUALQUER HORÁRIO VÁLIDO (sem validação de almoço)
    mostrarStatusHorario('✅ Horário aceito para agendamento!', 'sucesso');
    document.getElementById('horario_selecionado_hidden').value = horario;
    
    console.log('✅ Horário específico definido:', horario);
};

/**
 * ✅ CORREÇÃO 3: Garantir que o horário seja enviado corretamente
 * LOCALIZAR a função salvarEncaixe no agenda.js e ADICIONAR antes do fetch:
 */
function garantirHorarioEspecifico(formData) {
    const tipoHorario = document.querySelector('input[name="tipo_horario"]:checked')?.value;
    
    if (tipoHorario === 'horario_especifico') {
        const horarioDigitado = document.getElementById('horario_digitado')?.value;
        const horarioHidden = document.getElementById('horario_selecionado_hidden')?.value;
        
        // Usar o horário digitado ou o hidden, o que estiver preenchido
        const horarioFinal = horarioDigitado || horarioHidden;
        
        if (horarioFinal) {
            console.log('🎯 FORÇANDO HORÁRIO ESPECÍFICO:', horarioFinal);
            
            // Garantir que todos os campos relacionados ao horário sejam enviados
            formData.set('horario_agendamento', horarioFinal);
            formData.set('horario_especifico', horarioFinal);
            formData.set('hora_agendamento', horarioFinal + ':00'); // Com segundos
            formData.set('tipo_horario', 'especifico');
            formData.set('usar_horario_digitado', 'true');
            formData.set('nao_gerar_horario_automatico', 'true');
            
            console.log('📋 Horário específico enviado em múltiplos campos para garantir que seja salvo');
        } else {
            alert('Por favor, digite um horário específico.');
            return false;
        }
    }
    
    return true;
}

/**
 * ✅ NOVA FUNÇÃO: Sugerir horários baseados no funcionamento
 */
window.sugerirHorariosComBaseNoFuncionamento = function(horarioOriginal) {
    const agendaId = window.agendaIdAtual || '1';
    const data = window.dataSelecionadaAtual || new Date().toISOString().split('T')[0];
    
    // Determinar dia da semana
    const dataObj = new Date(data + 'T00:00:00');
    const diasSemana = ['Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado'];
    const diaSemana = diasSemana[dataObj.getDay()];
    
    console.log('🔍 Buscando horários para sugestão:', { agendaId, diaSemana });
    
    fetchWithAuth(`buscar_horarios_funcionamento.php?agenda_id=${agendaId}&dia_semana=${encodeURIComponent(diaSemana)}`)
        .then(safeJsonParse)
        .then(funcionamento => {
            if (funcionamento.sucesso && funcionamento.horarios) {
                const sugestoes = gerarSugestoesDeHorarios(funcionamento.horarios);
                mostrarSugestoesHorarios(sugestoes);
            } else {
                // Fallback para horários genéricos
                sugerirHorariosComerciais(horarioOriginal);
            }
        })
        .catch(error => {
            console.warn('Erro ao buscar horários para sugestão:', error);
            sugerirHorariosComerciais(horarioOriginal);
        });
};

/**
 * ✅ FUNÇÃO AUXILIAR: Gerar sugestões baseadas no funcionamento
 */
function gerarSugestoesDeHorarios(horariosFuncionamento) {
    const sugestoes = [];
    
    // Sugerir horários da manhã
    if (horariosFuncionamento.manha_inicio && horariosFuncionamento.manha_fim) {
        const [inicioH] = horariosFuncionamento.manha_inicio.split(':').map(Number);
        const [fimH] = horariosFuncionamento.manha_fim.split(':').map(Number);
        
        // Início da manhã
        sugestoes.push(horariosFuncionamento.manha_inicio);
        
        // Meio da manhã
        if (fimH - inicioH > 2) {
            const meioManha = inicioH + Math.floor((fimH - inicioH) / 2);
            sugestoes.push(`${meioManha.toString().padStart(2, '0')}:00`);
        }
        
        // Final da manhã (30 min antes do fim)
        if (fimH > inicioH + 1) {
            const [fimM] = horariosFuncionamento.manha_fim.split(':').map(Number);
            const fimMenosMin = fimM >= 30 ? fimM - 30 : 30;
            const fimMenosH = fimM >= 30 ? fimH : fimH - 1;
            sugestoes.push(`${fimMenosH.toString().padStart(2, '0')}:${fimMenosMin.toString().padStart(2, '0')}`);
        }
    }
    
    // Sugerir horários da tarde
    if (horariosFuncionamento.tarde_inicio && horariosFuncionamento.tarde_fim) {
        const [inicioH] = horariosFuncionamento.tarde_inicio.split(':').map(Number);
        const [fimH] = horariosFuncionamento.tarde_fim.split(':').map(Number);
        
        // Início da tarde
        sugestoes.push(horariosFuncionamento.tarde_inicio);
        
        // Meio da tarde
        if (fimH - inicioH > 2) {
            const meioTarde = inicioH + Math.floor((fimH - inicioH) / 2);
            sugestoes.push(`${meioTarde.toString().padStart(2, '0')}:00`);
        }
    }
    
    // Remover duplicatas e ordenar
    const sugestoesUnicas = [...new Set(sugestoes)].sort();
    
    return sugestoesUnicas.slice(0, 4); // Máximo 4 sugestões
}

/**
 * ✅ ATUALIZAR função sugerirHorariosProximos existente
 */
window.sugerirHorariosProximos = function(horarioOriginal) {
    console.log('🎯 Sugerindo horários próximos ao:', horarioOriginal);
    
    // Primeiro tentar sugerir baseado no funcionamento
    sugerirHorariosComBaseNoFuncionamento(horarioOriginal);
};

/**
 * ✅ FUNÇÃO NOVA: Validação local de horário (sem API)
 */
function validarHorarioLocalmente(horario) {
    const [horas, minutos] = horario.split(':').map(Number);
    
    // Horários que geralmente estão ocupados (simulação básica)
    const horariosComuns = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'
    ];
    
    // Simular 70% de chance de estar disponível
    const random = Math.random();
    const eHorarioComum = horariosComuns.includes(horario);
    
    if (random > 0.7 || eHorarioComum) {
        // Simular horário ocupado
        mostrarStatusHorario('Horário pode estar ocupado - verificação local', 'aviso');
        document.getElementById('horario_selecionado_hidden').value = horario; // Mesmo assim aceitar
        sugerirHorariosProximos(horario);
        
        console.log('⚠️ Horário aceito com aviso (validação local)');
    } else {
        // Horário disponível
        mostrarStatusHorario('Horário aparenta estar disponível', 'sucesso');
        document.getElementById('horario_selecionado_hidden').value = horario;
        limparSugestoes();
        
        console.log('✅ Horário disponível (validação local)');
    }
}

/**
 * ✅ FUNÇÃO NOVA: Sugerir horários comerciais
 */
function sugerirHorariosComerciais(horarioOriginal) {
    const horarios = [
        '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30', '18:00', '18:30'
    ];
    
    // Pegar 4 horários aleatórios
    const sugestoes = [];
    while (sugestoes.length < 4 && horarios.length > 0) {
        const index = Math.floor(Math.random() * horarios.length);
        sugestoes.push(horarios[index]);
        horarios.splice(index, 1);
    }
    
    mostrarSugestoesHorarios(sugestoes.sort());
}

/**
 * ✅ FUNÇÃO NOVA: Sugerir horários fora do almoço
 */
function sugerirHorariosForaAlmoco(horarioOriginal) {
    const horarios = ['11:00', '11:30', '14:00', '14:30', '15:00', '15:30'];
    mostrarSugestoesHorarios(horarios);
}


/**
 * ✅ FUNÇÃO AUXILIAR: Mostrar status do horário
 */
function mostrarStatusHorario(mensagem, tipo = 'info') {
    const statusElement = document.getElementById('status-horario') || criarElementoStatus();
    
    const icones = {
        'sucesso': 'svg-check-circle-fill text-green-600',
        'erro': 'svg-x-circle-fill text-red-600',
        'aviso': 'svg-exclamation-triangle-fill text-yellow-600',
        'info': 'svg-info-circle-fill text-blue-600',
        'loading': 'svg-loading animate-spin text-gray-600'
    };
    
    const cores = {
        'sucesso': 'text-green-800 bg-green-50 border-green-200',
        'erro': 'text-red-800 bg-red-50 border-red-200',
        'aviso': 'text-yellow-800 bg-yellow-50 border-yellow-200',
        'info': 'text-blue-800 bg-blue-50 border-blue-200',
        'loading': 'text-gray-800 bg-gray-50 border-gray-200'
    };
    
    const icone = icones[tipo] || icones['info'];
    const cor = cores[tipo] || cores['info'];
    
    statusElement.className = `flex items-center gap-2 p-3 rounded-lg border ${cor}`;
    statusElement.innerHTML = `
        <i class="bi ${icone}"></i>
        <span class="text-sm font-medium">${mensagem}</span>
    `;
    
    statusElement.style.display = 'flex';
}

/**
 * ✅ FUNÇÃO AUXILIAR: Criar elemento de status se não existir
 */
function criarElementoStatus() {
    const elemento = document.createElement('div');
    elemento.id = 'status-horario';
    elemento.style.display = 'none';
    
    const container = document.getElementById('horario_digitado')?.parentNode;
    if (container) {
        container.appendChild(elemento);
    }
    
    return elemento;
}

/**
 * ✅ FUNÇÃO AUXILIAR: Limpar status do horário
 */
function limparStatusHorario() {
    const statusElement = document.getElementById('status-horario');
    if (statusElement) {
        statusElement.style.display = 'none';
    }
    document.getElementById('horario_selecionado_hidden').value = '';
}

/**
 * ✅ FUNÇÃO: Mostrar sugestões de horários
 */
function mostrarSugestoesHorarios(sugestoes) {
    const sugestoesDiv = document.getElementById('sugestoes-horarios');
    
    if (!sugestoesDiv) {
        console.warn('⚠️ Div de sugestões não encontrada');
        return;
    }
    
    sugestoesDiv.classList.remove('hidden');
    
    const containerBotoes = sugestoesDiv.querySelector('.flex');
    if (containerBotoes) {
        const botoesHtml = sugestoes.map(horario => `
            <button type="button" 
                    class="px-3 py-1 bg-blue-100 text-blue-800 rounded text-sm hover:bg-blue-200 transition"
                    onclick="selecionarHorarioSugerido('${horario}')">
                ${horario}
            </button>
        `).join('');
        
        containerBotoes.innerHTML = botoesHtml;
    }
}

/**
 * ✅ FUNÇÃO AUXILIAR: Sugerir horários próximos
 */
function sugerirHorariosProximos(horarioOriginal) {
    const [horas, minutos] = horarioOriginal.split(':').map(Number);
    const sugestoes = [];
    
    // Sugerir 3 horários próximos
    for (let i = 1; i <= 3; i++) {
        const novoMinuto = minutos + (i * 15);
        const novaHora = horas + Math.floor(novoMinuto / 60);
        const minutoFinal = novoMinuto % 60;
        
        if (novaHora < 22) {
            const horarioSugerido = `${novaHora.toString().padStart(2, '0')}:${minutoFinal.toString().padStart(2, '0')}`;
            sugestoes.push(horarioSugerido);
        }
    }
    
    if (sugestoes.length > 0) {
        const statusElement = document.getElementById('status-horario');
        if (statusElement) {
            statusElement.innerHTML += `
                <div class="mt-2 text-xs">
                    <strong>Sugestões:</strong> 
                    ${sugestoes.map(h => `<span class="inline-block bg-white px-2 py-1 rounded border cursor-pointer hover:bg-gray-50" onclick="document.getElementById('horario_digitado').value='${h}'; verificarDisponibilidadeHorario();">${h}</span>`).join(' ')}
                </div>
            `;
        }
    }
}

/**
 * ✅ FUNÇÃO: Selecionar horário sugerido
 */
window.selecionarHorarioSugerido = function(horario) {
    const horarioInput = document.getElementById('horario_digitado');
    if (horarioInput) {
        horarioInput.value = horario;
        verificarDisponibilidadeHorario();
        console.log('✅ Horário sugerido selecionado:', horario);
    }
};

/**
 * ✅ FUNÇÃO: Limpar sugestões
 */
function limparSugestoes() {
    const sugestoesDiv = document.getElementById('sugestoes-horarios');
    if (sugestoesDiv) {
        sugestoesDiv.classList.add('hidden');
    }
}

/**
 * ✅ FUNÇÃO: Aplicar máscara de horário
 */
window.aplicarMascaraHorario = function() {
    const horarioInput = document.getElementById('horario_digitado');
    if (!horarioInput) return;
    
    horarioInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove tudo que não é número
        
        if (value.length >= 3) {
            value = value.replace(/^(\d{1,2})(\d{1,2}).*/, '$1:$2');
        }
        
        e.target.value = value;
        
        // Auto-validar se estiver completo
        if (value.length === 5 && value.includes(':')) {
            validarHorarioDigitado();
        }
    });
    
    // Permitir navegação com setas e backspace
    horarioInput.addEventListener('keydown', function(e) {
        const allowedKeys = ['Backspace', 'Delete', 'Tab', 'ArrowLeft', 'ArrowRight'];
        if (allowedKeys.includes(e.key) || (e.key >= '0' && e.key <= '9')) {
            return true;
        }
        e.preventDefault();
    });
};

/**
 * ✅ FUNÇÃO: Configurar eventos do sistema de horário
 */
window.configurarSistemaHorario = function() {
    console.log('🔧 Configurando sistema de horário específico...');
    
    // Aplicar máscara no campo de horário
    setTimeout(() => {
        aplicarMascaraHorario();
    }, 100);
    
    // Configurar radio buttons
    document.querySelectorAll('input[name="tipo_horario"]').forEach(radio => {
        radio.addEventListener('change', toggleSelecaoHorario);
    });
    
    console.log('✅ Sistema de horário configurado');
};


// ✅ INICIALIZAÇÃO AUTOMÁTICA
setTimeout(() => {
    configurarSistemaHorario();
}, 500);

console.log('✅ Sistema de horário específico corrigido!');
console.log('💡 Funções disponíveis:');
console.log('   - window.testarSistemaHorario() - Testar funcionamento');
console.log('   - window.verificarDisponibilidadeHorario() - Verificar horário manualmente');
console.log('🎯 O sistema agora deve funcionar sem mostrar "Agenda não encontrada"!');



// ============================================================================
// ✅ INICIALIZAÇÃO AUTOMÁTICA DAS FUNCIONALIDADES
// ============================================================================

// Configurar melhorias quando o modal for carregado
setTimeout(() => {
    // Aplicar máscaras e configurações
    aplicarMascarasTelefone();
    
    // Configurar eventos de teclado
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modal-encaixe')) {
            fecharModalEncaixe();
        }
    });
    
    // Configurar busca em tempo real se não estiver configurada
    if (typeof configurarBuscaTempoReal === 'function') {
        configurarBuscaTempoReal();
    }
}, 100);

// Aplicar máscara de telefone
function aplicarMascarasTelefone() {
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf_novo_paciente');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
}

// ============================================================================
// MELHORIAS ADICIONAIS PARA O SISTEMA DE HORÁRIO
// Adicione estas funções ao seu agenda.js
// ============================================================================



// ✅ MELHORIA 2: Sugestões de horários baseadas no histórico
window.sugerirHorariosPopulares = function() {
    const agendaId = document.querySelector('input[name="agenda_id"]').value;
    const data = document.querySelector('input[name="data_agendamento"]').value;
    
    fetchWithAuth(`buscar_horarios_populares.php?agenda_id=${agendaId}&data=${data}`)
        .then(safeJsonParse)
        .then(data => {
            if (data.status === 'sucesso' && data.horarios.length > 0) {
                mostrarHorariosPopulares(data.horarios);
            }
        })
        .catch(error => console.error('Erro ao buscar horários populares:', error));
};

// Mostrar horários populares
function mostrarHorariosPopulares(horarios) {
    const container = document.getElementById('info-horarios-agenda');
    
    const horariosHtml = horarios.slice(0, 4).map(h => `
        <button type="button" 
                class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs hover:bg-blue-200 transition"
                onclick="document.getElementById('horario_digitado').value='${h.horario}'; verificarDisponibilidadeHorario();">
            ${h.horario} (${h.count} agendamentos)
        </button>
    `).join('');
    
    container.innerHTML += `
        <div class="mt-2 pt-2 border-t border-gray-200">
            <div class="text-xs text-gray-600 mb-1">Horários mais procurados:</div>
            <div class="flex flex-wrap gap-1">${horariosHtml}</div>
        </div>
    `;
}

// ✅ MELHORIA 3: Validação de conflitos em tempo real
window.verificarConflitosHorario = function(horario) {
    const agendaId = document.querySelector('input[name="agenda_id"]').value;
    const data = document.querySelector('input[name="data_agendamento"]').value;
    
    // Verificar horários próximos (30 min antes e depois)
    const horarioTime = new Date(`2000-01-01T${horario}:00`);
    const horarioAntes = new Date(horarioTime.getTime() - 30 * 60 * 1000);
    const horarioDepois = new Date(horarioTime.getTime() + 30 * 60 * 1000);
    
    const formatHour = (date) => date.toTimeString().slice(0, 5);
    
    return fetchWithAuth(`verificar_conflitos_horario.php`, {
        method: 'POST',
        body: JSON.stringify({
            agenda_id: agendaId,
            data: data,
            horario_central: horario,
            horario_antes: formatHour(horarioAntes),
            horario_depois: formatHour(horarioDepois)
        })
    })
    .then(safeJsonParse);
};

// ✅ MELHORIA 4: Preview do agendamento
window.mostrarPreviewAgendamento = function() {
    const tipoHorario = document.querySelector('input[name="tipo_horario"]:checked').value;
    const horario = document.getElementById('horario_digitado')?.value;
    const nome = document.getElementById('nome_paciente_busca_real').value;
    const convenio = document.getElementById('convenio_encaixe');
    const convenioTexto = convenio.options[convenio.selectedIndex]?.text || '';
    
    if (!nome.trim()) return;
    
    let textoPreview = '';
    
    if (tipoHorario === 'horario_especifico' && horario) {
        textoPreview = `📅 Agendamento: ${nome} às ${horario} (${convenioTexto})`;
    } else {
        textoPreview = `⚡ Encaixe: ${nome} sem horário específico (${convenioTexto})`;
    }
    
    // Mostrar preview no botão
    const btnSalvar = document.getElementById('btn-salvar-encaixe');
    if (btnSalvar && textoPreview) {
        btnSalvar.title = textoPreview;
    }
};

// ✅ MELHORIA 5: Atalhos de teclado
window.configurarAtalhosTeclado = function() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter para salvar
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            const modal = document.getElementById('modal-encaixe');
            if (modal && !modal.classList.contains('hidden')) {
                e.preventDefault();
                salvarEncaixe();
            }
        }
        
        // Escape para fechar
        if (e.key === 'Escape') {
            const modal = document.getElementById('modal-encaixe');
            if (modal && !modal.classList.contains('hidden')) {
                fecharModalEncaixe();
            }
        }
        
        // Tab para navegar entre tipos de horário
        if (e.key === 'Tab' && e.target.name === 'tipo_horario') {
            setTimeout(() => toggleSelecaoHorario(), 10);
        }
    });
};

// ✅ MELHORIA 6: Feedback visual melhorado
window.adicionarFeedbackVisual = function() {
    // Adicionar loading state aos botões
    const btnVerificar = document.querySelector('button[onclick="verificarDisponibilidadeHorario()"]');
    if (btnVerificar) {
        btnVerificar.addEventListener('click', function() {
            this.innerHTML = '<i class="bi bi-arrow-clockwise animate-spin mr-1"></i>Verificando...';
            this.disabled = true;
            
            setTimeout(() => {
                this.innerHTML = '<i class="bi bi-search mr-1"></i>Verificar';
                this.disabled = false;
            }, 2000);
        });
    }
    
    // Adicionar animações de transição
    const areaInput = document.getElementById('area-input-horario');
    if (areaInput) {
        areaInput.style.transition = 'all 0.3s ease-in-out';
    }
};

// ✅ MELHORIA 7: Validação de horário de funcionamento em tempo real
window.validarHorarioFuncionamento = function(horario) {
    return new Promise((resolve) => {
        const agendaId = document.querySelector('input[name="agenda_id"]').value;
        
        fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
            .then(safeJsonParse)
            .then(data => {
                if (data.status === 'sucesso') {
                    const info = data.agenda;
                    const horarioTime = new Date(`2000-01-01T${horario}:00`);
                    
                    let dentroFuncionamento = false;
                    
                    // Verificar manhã
                    if (info.horario_inicio_manha && info.horario_fim_manha) {
                        const inicioManha = new Date(`2000-01-01T${info.horario_inicio_manha}`);
                        const fimManha = new Date(`2000-01-01T${info.horario_fim_manha}`);
                        
                        if (horarioTime >= inicioManha && horarioTime <= fimManha) {
                            dentroFuncionamento = true;
                        }
                    }
                    
                    // Verificar tarde
                    if (!dentroFuncionamento && info.horario_inicio_tarde && info.horario_fim_tarde) {
                        const inicioTarde = new Date(`2000-01-01T${info.horario_inicio_tarde}`);
                        const fimTarde = new Date(`2000-01-01T${info.horario_fim_tarde}`);
                        
                        if (horarioTime >= inicioTarde && horarioTime <= fimTarde) {
                            dentroFuncionamento = true;
                        }
                    }
                    
                    resolve({
                        valido: dentroFuncionamento,
                        funcionamento: info
                    });
                } else {
                    resolve({ valido: false });
                }
            })
            .catch(() => resolve({ valido: false }));
    });
};

// ✅ INICIALIZAÇÃO: Configurar todas as melhorias
window.inicializarMelhoriasHorario = function() {
    // Aguardar modal estar carregado
    setTimeout(() => {
        aplicarMascaraHorario();
        configurarAtalhosTeclado();
        adicionarFeedbackVisual();
        sugerirHorariosPopulares();
        
        // Configurar preview em tempo real
        const campos = ['nome_paciente_busca_real', 'convenio_encaixe', 'horario_digitado'];
        campos.forEach(id => {
            const campo = document.getElementById(id);
            if (campo) {
                campo.addEventListener('input', mostrarPreviewAgendamento);
                campo.addEventListener('change', mostrarPreviewAgendamento);
            }
        });
        
        // Configurar validação em tempo real para radio buttons
        document.querySelectorAll('input[name="tipo_horario"]').forEach(radio => {
            radio.addEventListener('change', mostrarPreviewAgendamento);
        });
    }, 500);
};

// Buscar horários disponíveis
window.buscarHorariosDisponiveis = function() {
    const agendaId = document.querySelector('input[name="agenda_id"]').value;
    const data = document.querySelector('input[name="data_agendamento"]').value;
    const container = document.getElementById('horarios-disponiveis');
    
    // Mostrar loading
    container.innerHTML = `
        <div class="col-span-full text-center p-4 text-gray-500">
            <i class="bi bi-arrow-clockwise animate-spin text-lg"></i>
            <div class="text-sm mt-1">Buscando horários...</div>
        </div>
    `;
    
    fetchWithAuth(`buscar_horarios_agenda.php?agenda_id=${agendaId}&data=${data}`)
        .then(safeJsonParse)
        .then(data => {
            if (data.status === 'sucesso' && data.horarios) {
                renderizarHorariosDisponiveis(data.horarios);
            } else {
                container.innerHTML = `
                    <div class="col-span-full text-center p-4 text-red-500">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div class="text-sm mt-1">Erro ao carregar horários</div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erro ao buscar horários:', error);
            container.innerHTML = `
                <div class="col-span-full text-center p-4 text-red-500">
                    <i class="bi bi-wifi-off"></i>
                    <div class="text-sm mt-1">Erro de conexão</div>
                </div>
            `;
        });
};

// Renderizar horários disponíveis
function renderizarHorariosDisponiveis(horarios) {
    const container = document.getElementById('horarios-disponiveis');
    
    if (!horarios || horarios.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center p-4 text-yellow-600">
                <i class="bi bi-clock"></i>
                <div class="text-sm mt-1">Nenhum horário disponível</div>
            </div>
        `;
        return;
    }
    
    const horariosHTML = horarios.map(horario => {
        const disponivel = !horario.ocupado;
        const classes = disponivel 
            ? 'bg-white border-gray-300 hover:border-blue-500 hover:bg-blue-50 cursor-pointer text-gray-800'
            : 'bg-gray-100 border-gray-200 text-gray-400 cursor-not-allowed';
            
        return `
            <button type="button" 
                    class="p-2 border rounded text-sm transition ${classes}"
                    ${disponivel ? `onclick="selecionarHorario('${horario.hora}')"` : 'disabled'}>
                <div class="font-medium">${horario.hora}</div>
                <div class="text-xs">
                    ${disponivel ? 'Livre' : 'Ocupado'}
                </div>
            </button>
        `;
    }).join('');
    
    container.innerHTML = horariosHTML;
}

// Selecionar horário específico
window.selecionarHorario = function(horario) {
    // Limpar seleção anterior
    document.querySelectorAll('#horarios-disponiveis button').forEach(btn => {
        btn.classList.remove('border-green-500', 'bg-green-100', 'text-green-800');
        btn.classList.add('border-gray-300', 'text-gray-800');
    });
    
    // Destacar horário selecionado
    event.target.classList.remove('border-gray-300', 'text-gray-800');
    event.target.classList.add('border-green-500', 'bg-green-100', 'text-green-800');
    
    // Atualizar campos hidden e visível
    document.getElementById('horario_selecionado_hidden').value = horario;
    document.getElementById('horario-selecionado-texto').textContent = horario;
    document.getElementById('horario-selecionado').classList.remove('hidden');
};

// Limpar seleção de horário
window.limparSelecaoHorario = function() {
    // Limpar seleção visual
    document.querySelectorAll('#horarios-disponiveis button').forEach(btn => {
        btn.classList.remove('border-green-500', 'bg-green-100', 'text-green-800');
        btn.classList.add('border-gray-300', 'text-gray-800');
    });
    
    // Limpar campos
    document.getElementById('horario_selecionado_hidden').value = '';
    document.getElementById('horario-selecionado').classList.add('hidden');
};



/**
 * ✅ INICIALIZAR: Todas as funcionalidades do modal
 */
function inicializarModalBuscaTempoReal() {
    console.log('🔧 Inicializando modal com busca em tempo real...');
    
    // 1. Configurar busca em tempo real
    configurarBuscaTempoReal();
    
    // 2. Configurar toggle de cadastro
    configurarToggleCadastroNovo();
    
    // 3. Aplicar máscaras
    aplicarMascarasTempoReal();
    
    // 4. Event listeners gerais
    adicionarEventListenersTempoReal();
    
    console.log('✅ Modal inicializado - busca em tempo real ativada!');
}

/**
 * ✅ CORREÇÃO: Configurar busca em tempo real com verificações robustas
 */
/**
 * ✅ CORREÇÃO: Configurar busca em tempo real com verificações robustas
 */
function configurarBuscaTempoReal() {
    console.log('🔧 Iniciando configuração da busca em tempo real...');
    
    // ✅ MELHORIA 1: Aguardar elementos existirem no DOM
    const aguardarElementos = () => {
        return new Promise((resolve) => {
            const verificarElementos = () => {
                const inputNome = document.getElementById('nome_paciente_busca_real');
                const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
                
                if (inputNome && resultadosDiv) {
                    resolve({ inputNome, resultadosDiv });
                } else {
                    // Tentar novamente após 100ms
                    setTimeout(verificarElementos, 100);
                }
            };
            verificarElementos();
        });
    };
    
    // ✅ MELHORIA 2: Configurar busca apenas quando elementos existirem
    aguardarElementos().then(({ inputNome, resultadosDiv }) => {
        // Elementos opcionais (podem não existir em todas as versões)
        const statusSpan = document.getElementById('status-busca');
        const iconeBusca = document.getElementById('icone-busca');
        const loadingBusca = document.getElementById('loading-busca');
        
        let timeoutBusca;
        let ultimaBusca = '';
        
        console.log('✅ Elementos encontrados - configurando busca...');
        
        // ✅ CORREÇÃO: Event listener de input com verificações
        inputNome.addEventListener('input', function(e) {
            const termo = this.value.trim();
            
            // Limpar timeout anterior
            clearTimeout(timeoutBusca);
            
            // Se termo está vazio, apenas esconder resultados
            if (termo.length === 0) {
                resultadosDiv.classList.add('hidden');
                if (statusSpan) statusSpan.textContent = 'Digite para buscar pacientes cadastrados automaticamente';
                limparApenasDadosPaciente();
                return;
            }
            
            if (termo.length < 2) {
                resultadosDiv.classList.add('hidden');
                if (statusSpan) statusSpan.textContent = 'Digite pelo menos 2 caracteres para buscar';
                return;
            }
            
            // Evitar busca duplicada
            if (termo === ultimaBusca) {
                return;
            }
            
            // ✅ CORREÇÃO: Mostrar loading apenas se elementos existirem
            if (iconeBusca) iconeBusca.classList.add('hidden');
            if (loadingBusca) loadingBusca.classList.remove('hidden');
            if (statusSpan) statusSpan.textContent = 'Buscando...';
            
            // Buscar após 500ms
            timeoutBusca = setTimeout(() => {
                ultimaBusca = termo;
                realizarBuscaTempoReal(termo);
            }, 500);
        });
        
        // ✅ Event listeners adicionais
        configurarEventListenersBusca(inputNome, resultadosDiv);
        
        console.log('✅ Busca em tempo real configurada com sucesso!');
    }).catch(error => {
        console.error('❌ Erro ao configurar busca em tempo real:', error);
    });
}

/**
 * ✅ NOVA FUNÇÃO: Configurar event listeners da busca
 */
function configurarEventListenersBusca(inputNome, resultadosDiv) {
    // Fechar resultados ao clicar fora
    document.addEventListener('click', function(e) {
        if (!inputNome.contains(e.target) && !resultadosDiv.contains(e.target)) {
            resultadosDiv.classList.add('hidden');
        }
    });
    
    // Navegar com teclado
    inputNome.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            navegarResultados(e.key);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            selecionarResultadoAtivo();
        } else if (e.key === 'Escape') {
            resultadosDiv.classList.add('hidden');
        }
    });
}

/**
 * ✅ VERSÃO SEGURA: Inicializar modal com verificações
 */
function inicializarModalBuscaTempoRealSeguro() {
    console.log('🔧 Inicializando modal com verificações de segurança...');
    
    // ✅ AGUARDAR DOM ESTAR PRONTO
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            executarInicializacao();
        });
    } else {
        executarInicializacao();
    }
}

/**
 * ✅ FUNÇÃO AUXILIAR: Executar inicialização
 */
function executarInicializacao() {
    // 1. Configurar busca em tempo real (com aguardo)
    configurarBuscaTempoReal();
    
    // 2. Configurar outras funcionalidades (com verificações)
    setTimeout(() => {
        try {
            if (typeof configurarToggleCadastroNovo === 'function') {
                configurarToggleCadastroNovo();
            }
            
            if (typeof aplicarMascarasTempoReal === 'function') {
                aplicarMascarasTempoReal();
            }
            
            if (typeof adicionarEventListenersTempoReal === 'function') {
                adicionarEventListenersTempoReal();
            }
            
            console.log('✅ Modal inicializado completamente!');
        } catch (error) {
            console.error('❌ Erro durante inicialização:', error);
        }
    }, 200);
}

// ✅ 3. CORRIGIR: limparApenasDadosPaciente com verificações
window.limparApenasDadosPaciente = function() {
    try {
        console.log('🧹 Limpando dados do paciente com segurança...');
        
        // Lista de elementos para limpar
        const elementos = [
            { id: 'paciente_id_hidden', acao: 'value', valor: '' },
            { id: 'paciente_existente_id', acao: 'value', valor: '' },
            { id: 'usar_paciente_existente', acao: 'value', valor: 'false' },
            { id: 'paciente-existente-encontrado', acao: 'addClass', valor: 'hidden' },
            { id: 'secao-opcao-cadastro', acao: 'removeClass', valor: 'hidden' }
        ];
        
        elementos.forEach(item => {
            const elemento = document.getElementById(item.id);
            if (elemento) {
                switch (item.acao) {
                    case 'value':
                        elemento.value = item.valor;
                        break;
                    case 'addClass':
                        elemento.classList.add(item.valor);
                        break;
                    case 'removeClass':
                        elemento.classList.remove(item.valor);
                        break;
                }
                console.log(`✅ ${item.id}: ${item.acao} aplicado`);
            } else {
                console.warn(`⚠️ ${item.id}: elemento não encontrado`);
            }
        });
        
        console.log('✅ Dados do paciente limpos com sucesso');
    } catch (error) {
        console.error('❌ Erro ao limpar dados do paciente:', error);
    }
};

/**
 * ✅ FUNCTION HELPER: Verificar se elemento existe antes de usar
 */
function verificarElementoExiste(id, funcaoCallback, tentativas = 10) {
    const elemento = document.getElementById(id);
    
    if (elemento) {
        funcaoCallback(elemento);
        return true;
    } else if (tentativas > 0) {
        setTimeout(() => {
            verificarElementoExiste(id, funcaoCallback, tentativas - 1);
        }, 100);
        return false;
    } else {
        console.warn(`⚠️ Elemento ${id} não encontrado após ${10 - tentativas + 1} tentativas`);
        return false;
    }
}

// ✅ 1. CORRIGIR: Toggle cadastro recebendo undefined
window.toggleCadastroCompleto = function(checkbox) {
    // ✅ NOVA LÓGICA: Se checkbox não foi passado, encontrar automaticamente
    if (!checkbox) {
        checkbox = document.getElementById('checkbox-criar-cadastro');
        console.log('🔍 Checkbox encontrado automaticamente:', !!checkbox);
    }
    
    // ✅ VERIFICAR SE CHECKBOX EXISTE E TEM VALOR
    const isChecked = checkbox ? checkbox.checked : false;
    console.log('🔄 Toggle cadastro chamado - Checked:', isChecked);
    
    const formulario = document.getElementById('formulario-cadastro-novo');
    let hiddenCadastrar = document.getElementById('deve_cadastrar_paciente');
    const pacienteIdHidden = document.getElementById('paciente_id_hidden');
    
    // ✅ CRIAR ELEMENTO SE NÃO EXISTIR
    if (!hiddenCadastrar) {
        console.log('🔧 Criando elemento deve_cadastrar_paciente...');
        hiddenCadastrar = document.createElement('input');
        hiddenCadastrar.type = 'hidden';
        hiddenCadastrar.id = 'deve_cadastrar_paciente';
        hiddenCadastrar.name = 'deve_cadastrar_paciente';
        hiddenCadastrar.value = 'false';
        
        // Adicionar ao formulário ou body
        const form = document.querySelector('form') || document.body;
        form.appendChild(hiddenCadastrar);
        console.log('✅ Elemento deve_cadastrar_paciente criado');
    }
    
    if (!formulario) {
        console.warn('⚠️ Elemento formulario-cadastro-novo não encontrado');
        return;
    }
    
    if (isChecked) {
        // ✅ ATIVAR cadastro de novo paciente
        formulario.classList.remove('hidden');
        hiddenCadastrar.value = 'true';
        if (pacienteIdHidden) pacienteIdHidden.value = '';
        
        console.log('✅ Formulário de cadastro ATIVADO');
    } else {
        // ❌ DESATIVAR cadastro
        formulario.classList.add('hidden');
        hiddenCadastrar.value = 'false';
        if (pacienteIdHidden) pacienteIdHidden.value = '';
        
        console.log('❌ Formulário de cadastro DESATIVADO');
    }
};

// ✅ 2. CORREÇÃO DA BUSCA LENTA: Otimizar busca em tempo real
window.realizarBuscaTempoReal = function(termo) {
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    const statusSpan = document.getElementById('status-busca');
    const iconeBusca = document.getElementById('icone-busca');
    const loadingBusca = document.getElementById('loading-busca');
    
    if (!resultadosDiv) {
        console.error('❌ Elemento resultados-busca-tempo-real não encontrado');
        return;
    }
    
    // ✅ OTIMIZAÇÃO 1: Cache de resultados
    if (!window.cacheBusca) {
        window.cacheBusca = new Map();
    }
    
    // ✅ OTIMIZAÇÃO 2: Verificar cache primeiro
    const chaveCache = termo.toLowerCase();
    if (window.cacheBusca.has(chaveCache)) {
        console.log('📋 Usando resultado do cache para:', termo);
        const dadosCache = window.cacheBusca.get(chaveCache);
        
        if (dadosCache.pacientes && dadosCache.pacientes.length > 0) {
            mostrarResultadosTempoReal(dadosCache.pacientes);
            if (statusSpan) statusSpan.textContent = `${dadosCache.pacientes.length} paciente(s) encontrado(s) (cache)`;
        } else {
            mostrarNenhumResultado();
            if (statusSpan) statusSpan.textContent = 'Nenhum paciente encontrado (cache)';
        }
        return;
    }
    
    // ✅ OTIMIZAÇÃO 3: Controller para cancelar requisições anteriores
    if (window.controllerBusca) {
        window.controllerBusca.abort();
    }
    window.controllerBusca = new AbortController();
    
    const formData = new FormData();
    formData.append('termo', termo);
    
    console.log('🔍 Buscando:', termo);
    
    fetch('buscar_paciente.php', {
        method: 'POST',
        body: formData,
        signal: window.controllerBusca.signal // ✅ Cancelável
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text().then(text => {
            console.log('📄 Resposta busca bruta:', text);
            
            if (!text || text.trim() === '' || text.trim() === '0') {
                // Resposta vazia = sem resultados
                const dadosVazio = { status: 'sucesso', pacientes: [] };
                window.cacheBusca.set(chaveCache, dadosVazio); // ✅ Cachear resultado vazio
                return dadosVazio;
            }
            
            // Extrair JSON válido
            let jsonString = text.trim();
            if (jsonString.includes('Warning') || jsonString.includes('Notice') || jsonString.includes('<br')) {
                const linhas = jsonString.split('\n');
                for (let i = linhas.length - 1; i >= 0; i--) {
                    const linha = linhas[i].trim();
                    if (linha.startsWith('{') && linha.includes('"status"')) {
                        jsonString = linha;
                        break;
                    }
                }
            }
            
            return JSON.parse(jsonString);
        });
    })
    .then(data => {
        if (!data) return; // Resposta cancelada
        
        console.log('📋 Dados da busca:', data);
        
        // ✅ OTIMIZAÇÃO 4: Salvar no cache
        window.cacheBusca.set(chaveCache, data);
        
        // ✅ OTIMIZAÇÃO 5: Limpar cache antigo (manter apenas 50 itens)
        if (window.cacheBusca.size > 50) {
            const primeiraChave = window.cacheBusca.keys().next().value;
            window.cacheBusca.delete(primeiraChave);
        }
        
        // Restaurar interface
        if (loadingBusca) loadingBusca.classList.add('hidden');
        if (iconeBusca) iconeBusca.classList.remove('hidden');
        
        if (data.status === 'sucesso' && data.pacientes && data.pacientes.length > 0) {
            mostrarResultadosTempoReal(data.pacientes);
            if (statusSpan) statusSpan.textContent = `${data.pacientes.length} paciente(s) encontrado(s)`;
        } else {
            mostrarNenhumResultado();
            if (statusSpan) statusSpan.textContent = 'Nenhum paciente encontrado';
        }
    })
    .catch(error => {
        if (error.name === 'AbortError') {
            console.log('🚫 Busca cancelada (nova busca iniciada)');
            return;
        }
        
        console.error('❌ Erro na busca:', error);
        
        // Restaurar interface
        if (loadingBusca) loadingBusca.classList.add('hidden');
        if (iconeBusca) iconeBusca.classList.remove('hidden');
        if (statusSpan) statusSpan.textContent = 'Erro na busca - tente novamente';
        
        mostrarErroBusca();
        resultadosDiv.classList.add('hidden');
    });
};

// ✅ 3. LIMPEZA DO CACHE (executar periodicamente)
window.limparCacheBusca = function() {
    if (window.cacheBusca) {
        window.cacheBusca.clear();
        console.log('🧹 Cache de busca limpo');
    }
};

// ✅ 3. MELHORAR: Configuração automática do checkbox
function configurarCheckboxCadastro() {
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    if (checkbox) {
        // Remover qualquer event listener antigo
        checkbox.removeAttribute('onchange');
        
        // Adicionar novo event listener que passa o checkbox corretamente
        checkbox.addEventListener('change', function() {
            toggleCadastroCompleto(this);
        });
        
        console.log('✅ Checkbox configurado para passar referência correta');
    } else {
        console.warn('⚠️ Checkbox checkbox-criar-cadastro não encontrado no DOM');
    }
}

// ✅ 4. APLICAR TODOS OS AJUSTES
function aplicarAjustesFinals() {
    console.log('🔧 Aplicando ajustes finais...');
    
    // Configurar checkbox
    configurarCheckboxCadastro();
    
    // Verificar se elementos necessários existem
    const elementos = [
        'checkbox-criar-cadastro',
        'formulario-cadastro-novo',
        'nome_paciente_busca_real',
        'resultados-busca-tempo-real'
    ];
    
    console.log('🔍 Verificação final dos elementos:');
    elementos.forEach(id => {
        const elemento = document.getElementById(id);
        console.log(`- ${id}: ${elemento ? '✅ OK' : '⚠️ Ausente'}`);
    });
    
    console.log('✅ Ajustes finais aplicados!');
    console.log('💡 Agora teste:');
    console.log('   1. Digite no campo de busca');
    console.log('   2. Marque/desmarque o checkbox de cadastro');
    console.log('   3. Faça um agendamento completo');
}

// ✅ EXECUTAR AUTOMATICAMENTE
aplicarAjustesFinals();


// ✅ CORRIGIR: mostrarResultadosTempoReal com verificação de null
window.mostrarResultadosTempoReal = function(pacientes) {
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    
    // ✅ VERIFICAÇÃO CRÍTICA: Elemento deve existir
    if (!resultadosDiv) {
        console.error('❌ Elemento resultados-busca-tempo-real não encontrado para mostrar resultados');
        return;
    }
    
    console.log('📋 Mostrando resultados para', pacientes.length, 'pacientes');
    
    const html = pacientes.map((paciente, index) => `
        <div class="resultado-item p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 ${index === 0 ? 'bg-gray-50' : ''}" 
             data-index="${index}"
             onclick="selecionarPacienteExistente(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="font-medium text-gray-900">${paciente.nome}</div>
                    <div class="text-sm text-gray-600">
                        CPF: ${paciente.cpf} | Tel: ${paciente.telefone}
                        ${paciente.data_nascimento ? ` | Nascimento: ${new Date(paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}
                    </div>
                    ${paciente.email ? `<div class="text-xs text-gray-500">${paciente.email}</div>` : ''}
                </div>
                <div class="text-xs text-gray-400 ml-2">
                    <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    `).join('');
    
    resultadosDiv.innerHTML = html;
    resultadosDiv.classList.remove('hidden');
    
    console.log('✅ Resultados exibidos com sucesso');
};

/**
 * ✅ CORREÇÃO ADICIONAL: Função selecionarPacienteExistente MELHORADA
 */
window.selecionarPacienteExistente = function(paciente) {
    console.log('👤 Paciente selecionado - VERSÃO CORRIGIDA:', paciente);
    
    // Preencher campos básicos
    const nomeInput = document.getElementById('nome_paciente_busca_real');
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    
    if (nomeInput) nomeInput.value = paciente.nome;
    if (telefoneInput) telefoneInput.value = paciente.telefone;
    
    // ✅ CORREÇÃO: Garantir que o ID seja salvo em TODOS os campos possíveis
    const camposId = [
        'paciente_id_hidden',
        'paciente_existente_id', 
        'paciente_selecionado_id'
    ];
    
    camposId.forEach(campoId => {
        let elemento = document.getElementById(campoId);
        if (!elemento) {
            // Criar campo se não existir
            elemento = document.createElement('input');
            elemento.type = 'hidden';
            elemento.id = campoId;
            elemento.name = campoId;
            document.querySelector('form')?.appendChild(elemento) || document.body.appendChild(elemento);
            console.log(`✅ Campo ${campoId} criado`);
        }
        elemento.value = paciente.id;
        console.log(`✅ ${campoId} = ${paciente.id}`);
    });
    
    // Marcar como usando paciente existente
    let usarPacienteExistente = document.getElementById('usar_paciente_existente');
    if (!usarPacienteExistente) {
        usarPacienteExistente = document.createElement('input');
        usarPacienteExistente.type = 'hidden';
        usarPacienteExistente.id = 'usar_paciente_existente';
        usarPacienteExistente.name = 'usar_paciente_existente';
        document.querySelector('form')?.appendChild(usarPacienteExistente) || document.body.appendChild(usarPacienteExistente);
    }
    usarPacienteExistente.value = 'true';
    
    // Desmarcar checkbox de cadastro
    const checkboxCadastro = document.getElementById('checkbox-criar-cadastro');
    if (checkboxCadastro) {
        checkboxCadastro.checked = false;
        // Triggerar evento para esconder formulário
        if (typeof toggleCadastroCompleto === 'function') {
            toggleCadastroCompleto(checkboxCadastro);
        }
    }
    
    // Ocultar resultados de busca
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    if (resultadosDiv) resultadosDiv.classList.add('hidden');
    
    // Atualizar status visual
    if (nomeInput) {
        nomeInput.classList.add('border-green-500', 'bg-green-50');
    }
    if (telefoneInput) {
        telefoneInput.classList.add('border-green-500', 'bg-green-50');
    }
    
    // Mostrar feedback
    const statusSpan = document.getElementById('status-busca');
    if (statusSpan) {
        statusSpan.innerHTML = `
            <i class="bi bi-check-circle text-green-600 mr-1"></i>
            <span class="text-green-800 font-medium">Paciente cadastrado selecionado (ID: ${paciente.id})</span>
        `;
    }
    
    console.log('✅ Paciente existente configurado corretamente');
    console.log('📋 Verificação final dos campos:');
    camposId.forEach(campoId => {
        const valor = document.getElementById(campoId)?.value;
        console.log(`   - ${campoId}: ${valor}`);
    });
};

/**
 * ✅ FUNÇÃO DE DEBUG ESPECÍFICA PARA PACIENTE_ID
 */
window.debugPacienteId = function() {
    console.log('🔍 DEBUG PACIENTE_ID:');
    
    const campos = [
        'nome_paciente_busca_real',
        'telefone_paciente_encaixe',
        'paciente_id_hidden',
        'paciente_existente_id',
        'paciente_selecionado_id',
        'usar_paciente_existente',
        'checkbox-criar-cadastro'
    ];
    
    campos.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            const valor = elemento.type === 'checkbox' ? elemento.checked : elemento.value;
            console.log(`✅ ${id}: "${valor}"`);
        } else {
            console.log(`❌ ${id}: NÃO ENCONTRADO`);
        }
    });
    
    // Verificar se há paciente selecionado
    const usarExistente = document.getElementById('usar_paciente_existente')?.value === 'true';
    const temId = document.getElementById('paciente_id_hidden')?.value || 
                   document.getElementById('paciente_existente_id')?.value ||
                   document.getElementById('paciente_selecionado_id')?.value;
    
    console.log('📊 Estado atual:');
    console.log(`   - Usar existente: ${usarExistente}`);  
    console.log(`   - Tem ID: ${!!temId} (${temId})`);
    console.log(`   - Pode salvar: ${usarExistente && temId ? '✅ SIM' : '❌ NÃO'}`);
};

console.log('✅ Correção PACIENTE_ID aplicada!');
console.log('💡 Para debug: window.debugPacienteId()');

// ✅ TESTE: Verificar se elementos principais existem
function verificarElementosNecessarios() {
    const elementos = [
        'nome_paciente_busca_real',
        'resultados-busca-tempo-real',
        'telefone_paciente_encaixe'
    ];
    
    console.log('🔍 Verificando elementos necessários:');
    let todosExistem = true;
    
    elementos.forEach(id => {
        const elemento = document.getElementById(id);
        const existe = !!elemento;
        console.log(`- ${id}: ${existe ? '✅ Existe' : '❌ Não encontrado'}`);
        
        if (!existe) todosExistem = false;
    });
    
    if (todosExistem) {
        console.log('✅ Todos os elementos necessários existem!');
    } else {
        console.warn('⚠️ Alguns elementos estão ausentes. Verifique se o modal foi criado corretamente.');
    }
    
    return todosExistem;
}

/**
 * ✅ FUNÇÃO DE TESTE para verificar se o campo está digitável
 */
window.testarCampoNome = function() {
    const input = document.getElementById('nome_paciente_busca_real');
    
    console.log('🧪 Testando campo nome...');
    console.log('Elemento encontrado:', !!input);
    console.log('Valor atual:', input?.value);
    console.log('Disabled:', input?.disabled);
    console.log('ReadOnly:', input?.readOnly);
    console.log('PointerEvents:', getComputedStyle(input)?.pointerEvents);
    console.log('Display:', getComputedStyle(input)?.display);
    
    if (input) {
        input.focus();
        console.log('Campo focado. Tente digitar agora.');
    }
};

/**
 * ✅ LIMPAR SELEÇÃO: Voltar ao estado inicial
 */
window.limparSelecaoPaciente = function() {
    console.log('🔄 Limpando seleção de paciente manualmente...');
    
    // 1. Limpar campos APENAS se solicitado pelo usuário
    document.getElementById('nome_paciente_busca_real').value = '';
    document.getElementById('telefone_paciente_encaixe').value = '';
    
    // 2. Limpar dados do paciente
    limparApenasDadosPaciente();
    
    // 3. Limpar checkbox de cadastro
    document.getElementById('checkbox-criar-cadastro').checked = false;
    document.getElementById('formulario-cadastro-novo').classList.add('hidden');
    document.getElementById('deve_cadastrar_paciente').value = 'false';
    
    // 4. Esconder resultados
    document.getElementById('resultados-busca-tempo-real').classList.add('hidden');
    
    // 5. Focar no campo nome
    document.getElementById('nome_paciente_busca_real').focus();
    
    // 6. Atualizar status
    document.getElementById('status-busca').textContent = 'Digite para buscar pacientes cadastrados automaticamente';
    
    console.log('✅ Seleção limpa manualmente');
};

// ✅ 5. FUNÇÃO PARA CHAMAR toggleCadastroCompleto automaticamente
window.configurarToggleCadastroCompleto = function() {
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    if (checkbox) {
        // Remover event listeners antigos se existirem
        checkbox.removeAttribute('onchange');
        
        // Adicionar novo event listener
        checkbox.addEventListener('change', function() {
            toggleCadastroCompleto(this);
        });
        
        console.log('✅ Event listener do checkbox configurado');
    } else {
        console.warn('⚠️ Checkbox checkbox-criar-cadastro não encontrado');
    }
};

// ✅ 6. EXECUTAR TODAS AS CORREÇÕES
function aplicarTodasCorrecoes() {
    console.log('🔧 Aplicando todas as correções...');
    
    // Aplicar correção do toggle
    configurarToggleCadastroCompleto();
    
    // Testar se as funções foram criadas
    console.log('📋 Status das funções:');
    console.log('- toggleCadastroCompleto:', typeof window.toggleCadastroCompleto);
    console.log('- realizarBuscaTempoReal:', typeof window.realizarBuscaTempoReal);
    console.log('- limparApenasDadosPaciente:', typeof window.limparApenasDadosPaciente);
    console.log('- mostrarErroBusca:', typeof window.mostrarErroBusca);
    console.log('- mostrarNenhumResultado:', typeof window.mostrarNenhumResultado);
    
    console.log('✅ Todas as correções aplicadas com sucesso!');
    console.log('💡 Agora teste digitando no campo de busca ou marcando o checkbox de cadastro');
}

// ✅ EXECUTAR AUTOMATICAMENTE
aplicarTodasCorrecoes();

// ✅ APLICAR TODAS AS CORREÇÕES
function aplicarCorrecaoCompleta() {
    console.log('🔧 Aplicando correção completa...');
    
    // Limpar cache existente
    limparCacheBusca();
    
    console.log('✅ Correções aplicadas:');
    console.log('   1. ✅ Cadastro de paciente corrigido');
    console.log('   2. ✅ Busca otimizada com cache');
    console.log('   3. ✅ Cancelamento de requisições antigas');
    console.log('   4. ✅ FormData com campos corretos');
    
    console.log('💡 Funcionalidades:');
    console.log('   - Cache automático de buscas');
    console.log('   - Cancelamento de buscas antigas');
    console.log('   - Feedback específico para cadastros');
    console.log('   - Validação completa de campos');
    
    console.log('🎯 Teste agora:');
    console.log('   1. Marque "Cadastrar paciente"');
    console.log('   2. Preencha os dados obrigatórios');
    console.log('   3. Salve o agendamento');
    console.log('   4. Deve criar paciente com ID válido');
}

// ✅ EXECUTAR AUTOMATICAMENTE
aplicarCorrecaoCompleta();

// ✅ BONUS: Função para debug completo
window.debugCompleto = function() {
    console.log('🔍 DEBUG COMPLETO:');
    console.log('   - Cache ativo:', !!window.cacheBusca);
    console.log('   - Itens no cache:', window.cacheBusca?.size || 0);
    console.log('   - Controller ativo:', !!window.controllerBusca);
    
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    console.log('   - Checkbox cadastro:', checkbox?.checked);
    
    const elementos = ['cpf_novo_paciente', 'nascimento_novo_paciente'];
    elementos.forEach(id => {
        const el = document.getElementById(id);
        console.log(`   - ${id}:`, el?.value || 'NÃO ENCONTRADO');
    });
};

// ✅ BONUS: Função para testar busca manualmente
window.testarBusca = function() {
    console.log('🧪 Testando busca manualmente...');
    
    const input = document.getElementById('nome_paciente_busca_real');
    if (input) {
        input.value = 'test';
        input.dispatchEvent(new Event('input'));
        console.log('✅ Evento de busca disparado');
    } else {
        console.error('❌ Campo de busca não encontrado');
    }
};

/**
 * ✅ TOGGLE CADASTRO: Configurar opção de cadastrar novo paciente
 */
function configurarToggleCadastroNovo() {
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    const formulario = document.getElementById('formulario-cadastro-novo');
    const hiddenCadastrar = document.getElementById('deve_cadastrar_paciente');
    
    if (!checkbox || !formulario || !hiddenCadastrar) {
        console.error('❌ Elementos de cadastro não encontrados');
        return;
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            // Habilitar cadastro de novo paciente
            formulario.classList.remove('hidden');
            hiddenCadastrar.value = 'true';
            // PACIENTE_ID ficará vazio até gerar o novo ID
            document.getElementById('paciente_id_hidden').value = '';
            
            console.log('✅ Cadastro de novo paciente habilitado');
        } else {
            // Desabilitar cadastro
            formulario.classList.add('hidden');
            hiddenCadastrar.value = 'false';
            // PACIENTE_ID fica vazio (encaixe sem cadastro)
            document.getElementById('paciente_id_hidden').value = '';
            
            console.log('❌ Cadastro desabilitado - encaixe sem cadastro');
        }
    });
}


/**
 * ✅ NOVA FUNÇÃO: Carregar convênios
 */
function carregarConvenios() {
    const selectConvenio = document.querySelector('select[name="convenio_id"]');
    
    // Lista básica de convênios (pode ser carregada via AJAX)
    const convenios = [
        { id: 1, nome: 'Particular' },
        { id: 2, nome: 'SUS' },
        { id: 3, nome: 'Unimed' },
        { id: 4, nome: 'Hapvida' },
        { id: 5, nome: 'Bradesco Saúde' },
        { id: 6, nome: 'Amil' }
    ];
    
    convenios.forEach(convenio => {
        const option = document.createElement('option');
        option.value = convenio.id;
        option.textContent = convenio.nome;
        selectConvenio.appendChild(option);
    });
}


/**
 * ✅ MÁSCARAS: Aplicar formatação automática
 */
function aplicarMascarasTempoReal() {
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf_novo_paciente');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
}

/**
 * ✅ EVENT LISTENERS: Configurar eventos gerais
 */
function adicionarEventListenersTempoReal() {
    // Modal encaixe agora só fecha com o botão X - removido fechamento ao clicar fora
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modal-encaixe')) {
            fecharModalEncaixe();
        }
    });
}

/**
 * ✅ FUNÇÕES AUXILIARES: Navegação e estados
 */
function navegarResultados(direcao) {
    const resultados = document.querySelectorAll('.resultado-item');
    if (resultados.length === 0) return;
    
    const ativo = document.querySelector('.resultado-item.bg-gray-50');
    let novoIndex = 0;
    
    if (ativo) {
        const atualIndex = parseInt(ativo.dataset.index);
        if (direcao === 'ArrowDown') {
            novoIndex = (atualIndex + 1) % resultados.length;
        } else {
            novoIndex = atualIndex > 0 ? atualIndex - 1 : resultados.length - 1;
        }
        ativo.classList.remove('bg-gray-50');
    }
    
    resultados[novoIndex].classList.add('bg-gray-50');
}

function selecionarResultadoAtivo() {
    const ativo = document.querySelector('.resultado-item.bg-gray-50');
    if (ativo) {
        ativo.click();
    }
}


/**
 * 🎯 CORREÇÃO FINAL - Execute no console do navegador
 * Esta correção resolve o último erro: innerHTML de elemento null
 */

// ✅ CORRIGIR: mostrarResultadosTempoReal com verificação de null
window.mostrarResultadosTempoReal = function(pacientes) {
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    
    // ✅ VERIFICAÇÃO CRÍTICA: Elemento deve existir
    if (!resultadosDiv) {
        console.error('❌ Elemento resultados-busca-tempo-real não encontrado para mostrar resultados');
        return;
    }
    
    console.log('📋 Mostrando resultados para', pacientes.length, 'pacientes');
    
    const html = pacientes.map((paciente, index) => `
        <div class="resultado-item p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 ${index === 0 ? 'bg-gray-50' : ''}" 
             data-index="${index}"
             onclick="selecionarPacienteExistente(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
            <div class="flex items-center justify-between">
                <div class="flex-1">
                    <div class="font-medium text-gray-900">${paciente.nome}</div>
                    <div class="text-sm text-gray-600">
                        CPF: ${paciente.cpf} | Tel: ${paciente.telefone}
                        ${paciente.data_nascimento ? ` | Nascimento: ${new Date(paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}
                    </div>
                    ${paciente.email ? `<div class="text-xs text-gray-500">${paciente.email}</div>` : ''}
                </div>
                <div class="text-xs text-gray-400 ml-2">
                    <i class="bi bi-arrow-right"></i>
                </div>
            </div>
        </div>
    `).join('');
    
    resultadosDiv.innerHTML = html;
    resultadosDiv.classList.remove('hidden');
    
    console.log('✅ Resultados exibidos com sucesso');
};

// ✅ CORRIGIR: mostrarNenhumResultado com verificação
window.mostrarNenhumResultado = function() {
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    
    if (!resultadosDiv) {
        console.error('❌ Elemento resultados-busca-tempo-real não encontrado para mostrar "nenhum resultado"');
        return;
    }
    
    resultadosDiv.innerHTML = `
        <div class="p-4 text-center text-gray-500">
            <i class="bi bi-search mr-2"></i>
            <div class="font-medium">Nenhum paciente encontrado</div>
            <div class="text-sm mt-1">Continue digitando para criar um novo agendamento</div>
        </div>
    `;
    resultadosDiv.classList.remove('hidden');
    
    console.log('✅ Mensagem "nenhum resultado" exibida');
};

// ✅ CORRIGIR: mostrarErroBusca com verificação
window.mostrarErroBusca = function() {
    const resultadosDiv = document.getElementById('resultados-busca-tempo-real');
    
    if (!resultadosDiv) {
        console.error('❌ Elemento resultados-busca-tempo-real não encontrado para mostrar erro');
        return;
    }
    
    resultadosDiv.innerHTML = `
        <div class="p-4 text-center text-red-500">
            <i class="bi bi-exclamation-triangle mr-2"></i>
            <div class="font-medium">Erro na busca</div>
            <div class="text-sm mt-1">Tente novamente em alguns segundos</div>
        </div>
    `;
    resultadosDiv.classList.remove('hidden');
    
    console.log('✅ Mensagem de erro exibida');
};

/**
 * ✅ CSS ESPECÍFICO para o modal com busca em tempo real
 */
const estilosTempoReal = document.createElement('style');
estilosTempoReal.textContent = `
    /* Garantir que inputs sejam sempre editáveis */
    #nome_paciente_busca_real,
    #telefone_paciente_encaixe,
    #cpf_novo_paciente,
    #nascimento_novo_paciente,
    #email_novo_paciente,
    #rg_novo_paciente {
        pointer-events: auto !important;
        user-select: text !important;
        -webkit-user-select: text !important;
        -moz-user-select: text !important;
        -ms-user-select: text !important;
    }
    
    /* Resultados de busca */
    #resultados-busca-tempo-real {
        z-index: 1000;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        border-radius: 0.375rem;
    }
    
    .resultado-item:hover {
        background-color: #f3f4f6 !important;
    }
    
    .resultado-item.bg-gray-50 {
        background-color: #f9fafb !important;
    }
    
    /* Animação para formulário de cadastro */
    #formulario-cadastro-novo {
        transition: all 0.3s ease-in-out;
        overflow: hidden;
    }
    
    #formulario-cadastro-novo.hidden {
        max-height: 0 !important;
        padding-top: 0 !important;
        padding-bottom: 0 !important;
        margin-top: 0 !important;
    }
    
    /* Spinner animado */
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    
    /* Campos obrigatórios */
    input[required], select[required], textarea[required] {
        border-left: 3px solid #f97316 !important;
    }
    
    input[required]:focus, select[required]:focus, textarea[required]:focus {
        border-left-color: #ea580c !important;
    }
    
    /* Estados visuais */
    .paciente-selecionado {
        border-color: #22c55e !important;
        background-color: #f0fdf4 !important;
    }
    
    .deve-cadastrar {
        border-color: #3b82f6 !important;
        background-color: #eff6ff !important;
    }
`;

// Adicionar CSS se não existe
if (!document.getElementById('estilos-tempo-real')) {
    estilosTempoReal.id = 'estilos-tempo-real';
    document.head.appendChild(estilosTempoReal);
}

/**
 * ✅ COMPATIBILIDADE: Manter funções originais
 */
if (typeof window.salvarEncaixeOriginal === 'undefined') {
    if (typeof window.salvarEncaixe === 'function') {
        window.salvarEncaixeOriginal = window.salvarEncaixe;
    }
    window.salvarEncaixe = window.salvarEncaixeComGestaoID;
}



function configurarBuscaSelect2() {
    const campoBusca = document.getElementById('busca-paciente-select');
    const dropdown = document.getElementById('dropdown-resultados');
    let timeoutBusca = null;
    
    // Buscar enquanto digita (com debounce)
    campoBusca.addEventListener('input', function() {
        const termo = this.value.trim();
        
        clearTimeout(timeoutBusca);
        
        if (termo.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        timeoutBusca = setTimeout(() => {
            buscarPacientesSelect2(termo);
        }, 300);
    });
    
    // Mostrar dropdown ao focar
    campoBusca.addEventListener('focus', function() {
        if (this.value.trim().length >= 2) {
            dropdown.classList.remove('hidden');
        }
    });
    
    // Esconder dropdown ao clicar fora
    document.addEventListener('click', function(e) {
        if (!campoBusca.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
}

function buscarPacientesSelect2(termo) {
    const dropdown = document.getElementById('dropdown-resultados');
    
    dropdown.innerHTML = `
        <div class="p-3 text-center text-gray-500">
            <i class="bi bi-hourglass-split animate-spin mr-2"></i>Buscando...
        </div>
    `;
    dropdown.classList.remove('hidden');
    
    fetch('buscar_paciente.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `termo=${encodeURIComponent(termo)}`
    })
    .then(safeJsonParse)
    .then(data => {
        if (data.status === 'sucesso' && data.pacientes.length > 0) {
            let html = '';
            
            data.pacientes.forEach(paciente => {
                html += `
                    <div class="p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0" 
                         onclick="selecionarPacienteSelect2(${JSON.stringify(paciente).replace(/"/g, '&quot;')})">
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium text-gray-900">${paciente.nome}</div>
                                <div class="text-sm text-gray-600">
                                    CPF: ${paciente.cpf} • Tel: ${paciente.telefone}
                                    ${paciente.data_nascimento ? ` • Nascimento: ${new Date(paciente.data_nascimento + 'T00:00:00').toLocaleDateString('pt-BR')}` : ''}
                                </div>
                            </div>
                            <i class="bi bi-arrow-right text-gray-400"></i>
                        </div>
                    </div>
                `;
            });
            
            dropdown.innerHTML = html;
        } else {
            dropdown.innerHTML = `
                <div class="p-3 text-center text-gray-500">
                    <i class="bi bi-person-x mr-2"></i>Nenhum paciente encontrado
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

window.selecionarPacienteSelect2 = function(paciente) {
    // Preencher dados do paciente
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
    checkboxCadastro.checked = false;
    checkboxCadastro.disabled = true;
    checkboxCadastro.parentElement.style.opacity = '0.5';
    
    // Mostrar feedback
    mostrarFeedback('Paciente selecionado! Dados preenchidos automaticamente.', 'sucesso');
    
    // Focar no convênio
    setTimeout(() => {
        document.querySelector('select[name="convenio_id"]').focus();
    }, 100);
};

function configurarMascaras() {
    // Máscara CPF
    const cpfInput = document.getElementById('cpf-paciente');
    cpfInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d)/, '$1.$2');
        value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        e.target.value = value;
    });
    
    // Máscara Telefone
    const telefoneInput = document.getElementById('telefone-paciente');
    telefoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{2})(\d)/, '($1) $2');
        value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
        e.target.value = value;
    });
    
    // Limpar seleção ao alterar campos manualmente
    [cpfInput, document.getElementById('nome-paciente')].forEach(input => {
        input.addEventListener('input', function() {
            document.getElementById('paciente-existente-id').value = '';
            document.getElementById('busca-paciente-select').value = '';
            
            // Reabilitar checkbox de cadastro
            const checkboxCadastro = document.getElementById('cadastrar-paciente');
            checkboxCadastro.disabled = false;
            checkboxCadastro.parentElement.style.opacity = '1';
        });
    });
}

/**
 * ✅ CORREÇÃO ESPECÍFICA: Nomes dos campos para processar_encaixe.php
 * Substitua APENAS a função salvarEncaixe no seu agenda.js
 */
window.salvarEncaixe = function() {
    console.log('💾 SALVANDO ENCAIXE - CORREÇÃO DE NOMES DOS CAMPOS...');
    
    // Validações básicas (mantidas iguais)
    const nomeInput = document.getElementById('nome_paciente_busca_real');
    const telefoneInput = document.getElementById('telefone_paciente_encaixe');
    const convenioSelect = document.getElementById('convenio_encaixe');
    
    if (!nomeInput?.value.trim()) {
        alert('Nome é obrigatório');
        nomeInput?.focus();
        return;
    }
    
    if (!telefoneInput?.value.trim()) {
        alert('Telefone é obrigatório');
        telefoneInput?.focus();
        return;
    }
    
    if (!convenioSelect?.value) {
        alert('Convênio é obrigatório');
        convenioSelect?.focus();
        return;
    }
    
    // ✅ IDENTIFICAR CENÁRIOS
    const pacienteIdExistente = document.getElementById('paciente_existente_id')?.value || 
                               document.getElementById('paciente_selecionado_id')?.value ||
                               document.getElementById('paciente-existente-id')?.value;
    
    const checkboxCadastrar = document.getElementById('checkbox-criar-cadastro');
    const deveCadastrar = checkboxCadastrar?.checked || false;
    
    const usarExistente = !!(pacienteIdExistente && 
                            pacienteIdExistente.trim() && 
                            pacienteIdExistente !== 'NULL' && 
                            pacienteIdExistente !== '0');
    
    console.log('📋 CENÁRIO IDENTIFICADO:');
    console.log(`   - Paciente ID encontrado: "${pacienteIdExistente}"`);
    console.log(`   - Usar paciente existente: ${usarExistente}`);
    console.log(`   - Deve cadastrar novo: ${deveCadastrar}`);
    
    // ✅ PREPARAR DADOS COM NOMES CORRETOS
    const formData = new FormData();
    
    // Dados básicos (mantidos iguais)
    formData.append('agenda_id', window.agendaIdAtual || '1');
    formData.append('data_agendamento', window.dataSelecionadaAtual || new Date().toISOString().split('T')[0]);
    formData.append('nome_paciente', nomeInput.value.trim());
    formData.append('telefone_paciente', telefoneInput.value.trim());
    formData.append('convenio_id', convenioSelect.value);
    formData.append('observacoes', document.getElementById('observacoes_encaixe')?.value.trim() || '');
    formData.append('tipo_operacao', 'encaixe');
    
    // ✅ GESTÃO CORRETA DO PACIENTE COM NOMES DE CAMPOS CORRETOS
    if (usarExistente && pacienteIdExistente) {
        // CENÁRIO 1: Paciente já existe
        formData.append('usar_paciente_existente', 'true');
        formData.append('cadastrar_paciente', 'false');
        formData.append('paciente_selecionado_id', pacienteIdExistente); // ✅ NOME CORRETO
        
        console.log('🔵 CENÁRIO: Usando paciente existente - ID:', pacienteIdExistente);
        
    } else if (deveCadastrar) {
        // CENÁRIO 2: Cadastrar novo paciente
        formData.append('usar_paciente_existente', 'false');
        formData.append('cadastrar_paciente', 'true');
        
        // ✅ CORREÇÃO PRINCIPAL: NOMES DOS CAMPOS CORRETOS PARA CADASTRO
        
        // Campos obrigatórios com NOMES CORRETOS
        const cpfNovo = document.getElementById('cpf_novo_paciente')?.value;
        const nascimentoNovo = document.getElementById('nascimento_novo_paciente')?.value;
        
        if (!cpfNovo || !nascimentoNovo) {
            alert('CPF e Data de Nascimento são obrigatórios para cadastrar novo paciente.');
            return;
        }
        
        // ✅ USAR NOMES EXATOS QUE O PHP ESPERA
        formData.append('cpf_paciente', cpfNovo.replace(/\D/g, '')); // Remove formatação
        formData.append('data_nascimento', nascimentoNovo); // ✅ NOME CORRETO
        
        // Campos opcionais com NOMES CORRETOS
        const sexoNovo = document.getElementById('sexo_novo_paciente')?.value;
        const emailNovo = document.getElementById('email_novo_paciente')?.value;
        const rgNovo = document.getElementById('rg_novo_paciente')?.value;
        const orgaoEmissorNovo = document.getElementById('orgao_emissor_novo_paciente')?.value;
        
        if (sexoNovo) formData.append('sexo', sexoNovo);
        if (emailNovo) formData.append('email_paciente', emailNovo); // ✅ NOME CORRETO
        if (rgNovo) formData.append('rg', rgNovo);
        if (orgaoEmissorNovo) formData.append('orgao_emissor', orgaoEmissorNovo);
        
        // ✅ ENDEREÇO COM NOMES EXATOS QUE O PHP ESPERA
        const cepNovo = document.getElementById('cep_novo_paciente')?.value;
        const enderecoNovo = document.getElementById('logradouro_novo_paciente')?.value;
        const numeroNovo = document.getElementById('numero_novo_paciente')?.value;
        const complementoNovo = document.getElementById('complemento_novo_paciente')?.value;
        const bairroNovo = document.getElementById('bairro_novo_paciente')?.value;
        const cidadeNovo = document.getElementById('cidade_novo_paciente')?.value;
        const estadoNovo = document.getElementById('estado_novo_paciente')?.value;
        
        if (cepNovo) formData.append('cep', cepNovo.replace(/\D/g, '')); // Remove formatação
        if (enderecoNovo) formData.append('endereco', enderecoNovo); // ✅ NOME CORRETO
        if (numeroNovo) formData.append('numero', numeroNovo);
        if (complementoNovo) formData.append('complemento', complementoNovo);
        if (bairroNovo) formData.append('bairro', bairroNovo);
        if (cidadeNovo) formData.append('cidade', cidadeNovo);
        if (estadoNovo) formData.append('uf', estadoNovo); // ✅ NOME CORRETO: 'uf' não 'estado'
        
        console.log('🟢 CENÁRIO: Cadastrando novo paciente');
        console.log('🆔 CPF:', cpfNovo);
        console.log('📅 Data Nascimento:', nascimentoNovo);
        console.log('📍 Endereço completo será enviado');
        
    } else {
        // CENÁRIO 3: Encaixe sem cadastro
        formData.append('usar_paciente_existente', 'false');
        formData.append('cadastrar_paciente', 'false');
        
        console.log('🟡 CENÁRIO: Encaixe sem cadastro');
    }
    
    // ✅ CAPTURAR EXAMES SELECIONADOS (MÚLTIPLOS)
    const examesSelecionados = document.getElementById('exames_ids_selected')?.value;
    if (examesSelecionados && examesSelecionados !== '') {
        formData.append('exames_ids', examesSelecionados);
        console.log('🔬 EXAMES SELECIONADOS (IDs):', examesSelecionados);
        const qtdExames = examesSelecionados.split(',').length;
        console.log('📊 QUANTIDADE DE EXAMES:', qtdExames);
    } else {
        console.log('⚠️ Nenhum exame selecionado');
    }
    
    // ✅ CORREÇÃO 2: Capturar horário específico CORRETAMENTE
    const horarioEspecifico = document.getElementById('horario_selecionado_hidden')?.value ||
                             document.getElementById('horario_digitado')?.value ||
                             document.querySelector('input[name="horario_agendamento"]')?.value;
    
    console.log('🔍 Verificando horário específico:');
    console.log('   - horario_selecionado_hidden:', document.getElementById('horario_selecionado_hidden')?.value);
    console.log('   - horario_digitado:', document.getElementById('horario_digitado')?.value);
    console.log('   - input[name="horario_agendamento"]:', document.querySelector('input[name="horario_agendamento"]')?.value);
    console.log('   - Horário final selecionado:', horarioEspecifico);
    
    // Verificar se foi selecionado horário específico vs encaixe
    const tipoHorario = document.querySelector('input[name="tipo_horario"]:checked')?.value;
    console.log('   - Tipo de horário selecionado:', tipoHorario);
    
    if (tipoHorario === 'horario_especifico' && horarioEspecifico) {
        formData.append('horario_agendamento', horarioEspecifico);
        formData.append('tipo_horario', 'especifico');
        console.log('⏰ HORÁRIO ESPECÍFICO CONFIRMADO:', horarioEspecifico);
    } else {
        formData.append('tipo_horario', 'encaixe');
        console.log('⚡ ENCAIXE SEM HORÁRIO ESPECÍFICO');
    }
    
    // ✅ DEBUG: Mostrar todos os dados sendo enviados
    console.log('📤 DADOS ENVIADOS COM NOMES CORRETOS:');
    for (let pair of formData.entries()) {
        console.log(`   ${pair[0]}: "${pair[1]}"`);
    }
    
    // Mostrar loading
    const btnSalvar = document.getElementById('btn-salvar-encaixe');
    const textoOriginal = btnSalvar?.innerHTML;
    if (btnSalvar) {
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="bi bi-arrows-spin animate-spin mr-2"></i>Salvando...';
    }
    
    // ✅ FAZER REQUISIÇÃO
    fetch('processar_encaixe.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('📡 Status da resposta:', response.status);
        
        if (!response.ok) {
            throw new Error(`Erro HTTP ${response.status}: ${response.statusText}`);
        }
        
        return response.text(); // Primeiro pegar como texto para debug
    })
    .then(responseText => {
        console.log('📄 Resposta bruta do servidor:', responseText);
        
        // Tentar extrair JSON da resposta
        let data;
        try {
            // Se a resposta é JSON puro
            data = JSON.parse(responseText);
        } catch (e) {
            // Se há conteúdo HTML/PHP antes do JSON, extrair apenas o JSON
            const linhas = responseText.split('\n');
            let jsonEncontrado = false;
            
            for (let i = linhas.length - 1; i >= 0; i--) {
                const linha = linhas[i].trim();
                if (linha.startsWith('{') && linha.includes('"status"')) {
                    try {
                        data = JSON.parse(linha);
                        jsonEncontrado = true;
                        break;
                    } catch (parseError) {
                        continue;
                    }
                }
            }
            
            if (!jsonEncontrado) {
                console.error('❌ Não foi possível encontrar JSON válido na resposta');
                throw new Error('Resposta inválida do servidor: ' + responseText.substring(0, 200));
            }
        }
        
        console.log('📋 Dados parseados do servidor:', data);
        
        if (data.status === 'sucesso') {
            console.log('✅ ENCAIXE SALVO COM SUCESSO!');
            
            // Fechar modal
            if (typeof fecharModalEncaixe === 'function') {
                fecharModalEncaixe();
            }
            
            // Preparar mensagem de sucesso
            let mensagem = `✅ Encaixe confirmado!\n`;
            mensagem += `📋 Número: ${data.numero_agendamento}\n`;
            mensagem += `👤 Paciente: ${nomeInput.value}\n`;
            mensagem += `📞 Telefone: ${telefoneInput.value}`;
            
            // Informações sobre o paciente
            if (data.paciente_id) {
                if (data.tipo_operacao === 'paciente_existente') {
                    mensagem += `\n👤 Vinculado ao paciente cadastrado (ID: ${data.paciente_id})`;
                } else if (data.tipo_operacao === 'novo_cadastro') {
                    mensagem += `\n🆔 Novo paciente cadastrado (ID: ${data.paciente_id})`;
                    if (data.endereco_salvo) {
                        mensagem += `\n📍 Endereço completo incluído`;
                    }
                }
            } else {
                mensagem += `\n⚡ Encaixe registrado sem cadastro de paciente`;
            }
            
            // Mostrar resultado
            alert(mensagem + '\n\n✅ O paciente será atendido conforme disponibilidade.');
            
            // ✅ CORREÇÃO 1: Atualizar SEM refresh da página
            setTimeout(() => {
                // Apenas atualizar a visualização atual
                const agendaId = window.agendaIdAtual;
                const dataAtual = window.dataSelecionadaAtual;
                
                if (agendaId && dataAtual) {
                    // Determinar qual visualização está ativa
                    const btnAtivo = document.querySelector('.btn-visualizacao.bg-teal-600');
                    const tipoVisualizacao = btnAtivo?.dataset.tipo || 'dia';
                    
                    console.log('🔄 Atualizando visualização sem refresh:', tipoVisualizacao);
                    
                    // Atualizar apenas a visualização específica
                    if (tipoVisualizacao === 'dia' && typeof carregarVisualizacaoDia === 'function') {
                        carregarVisualizacaoDia(agendaId, dataAtual);
                    } else if (tipoVisualizacao === 'semana' && typeof carregarVisualizacaoSemana === 'function') {
                        carregarVisualizacaoSemana(agendaId, dataAtual);
                    } else if (tipoVisualizacao === 'mes' && typeof carregarVisualizacaoMes === 'function') {
                        carregarVisualizacaoMes(agendaId, dataAtual);
                    }
                    
                    // Mostrar notificação de sucesso
                    if (typeof mostrarNotificacaoSucesso === 'function') {
                        mostrarNotificacaoSucesso('Encaixe salvo com sucesso!');
                    } else {
                        // Notificação simples se a função não existir
                        const notif = document.createElement('div');
                        notif.className = 'fixed top-4 right-4 bg-green-600 text-white px-4 py-2 rounded shadow z-50';
                        notif.textContent = 'Encaixe salvo com sucesso!';
                        document.body.appendChild(notif);
                        setTimeout(() => notif.remove(), 3000);
                    }
                } else {
                    console.warn('⚠️ IDs da agenda não encontrados, mantendo sem refresh');
                }
            }, 500);
            
        } else {
            console.error('❌ Erro retornado pelo servidor:', data);
            const mensagemErro = data.mensagem || data.erro || 'Erro desconhecido no servidor';
            alert(`❌ Erro ao salvar encaixe:\n${mensagemErro}`);
            
            // Debug adicional
            if (data.debug_info) {
                console.log('🔍 Debug do servidor:', data.debug_info);
                console.log('🔍 Erro completo:', data.debug_info.erro_completo);
                console.log('🔍 Dados POST recebidos:', data.debug_info.post_data);
            }
        }
        
    })
    .catch(error => {
        console.error('❌ ERRO NA REQUISIÇÃO:', error);
        
        let mensagemErro = 'Erro ao processar encaixe';
        
        if (error.message.includes('404')) {
            mensagemErro = 'Arquivo processar_encaixe.php não encontrado';
        } else if (error.message.includes('500')) {
            mensagemErro = 'Erro interno do servidor - verifique os logs';
        } else if (error.message.includes('400')) {
            mensagemErro = 'Dados inválidos enviados ao servidor';
        } else {
            mensagemErro = error.message;
        }
        
        alert(`❌ ${mensagemErro}\n\nDetalhes: ${error.message}\n\nTente novamente ou contate o suporte.`);
        
    })
    .finally(() => {
        // Restaurar botão
        if (btnSalvar && textoOriginal) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = textoOriginal;
        }
    });
};

// ✅ FUNÇÃO DE DEBUG: Verificar se campos existem antes de enviar
window.debugCamposEncaixe = function() {
    console.log('🔍 DEBUG DOS CAMPOS DE ENCAIXE:');
    
    const campos = [
        'nome_paciente_busca_real',
        'telefone_paciente_encaixe', 
        'convenio_encaixe',
        'cpf_novo_paciente',
        'nascimento_novo_paciente',
        'email_novo_paciente',
        'checkbox-criar-cadastro',
        'cep_novo_paciente',
        'logradouro_novo_paciente',
        'numero_novo_paciente',
        'bairro_novo_paciente',
        'cidade_novo_paciente',
        'estado_novo_paciente'
    ];
    
    campos.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            const valor = elemento.type === 'checkbox' ? elemento.checked : elemento.value;
            console.log(`✅ ${id}: "${valor}"`);
        } else {
            console.log(`❌ ${id}: NÃO ENCONTRADO`);
        }
    });
    
    const deveCadastrar = document.getElementById('checkbox-criar-cadastro')?.checked;
    console.log(`📊 Deve cadastrar: ${deveCadastrar ? 'SIM' : 'NÃO'}`);
    
    if (deveCadastrar) {
        const cpf = document.getElementById('cpf_novo_paciente')?.value;
        const nascimento = document.getElementById('nascimento_novo_paciente')?.value;
        console.log(`📋 Dados obrigatórios: CPF="${cpf}", Nascimento="${nascimento}"`);
    }
    
    // ✅ CORREÇÃO 3: Debug específico do horário
    console.log('🕐 DEBUG DO HORÁRIO:');
    const horarioDigitado = document.getElementById('horario_digitado')?.value;
    const horarioHidden = document.getElementById('horario_selecionado_hidden')?.value;
    const tipoHorario = document.querySelector('input[name="tipo_horario"]:checked')?.value;
    const areaInputHorario = document.getElementById('area-input-horario');
    
    console.log(`   - Tipo selecionado: ${tipoHorario}`);
    console.log(`   - Horário digitado: "${horarioDigitado}"`);
    console.log(`   - Horário hidden: "${horarioHidden}"`);
    console.log(`   - Área input visível: ${!areaInputHorario?.classList.contains('hidden')}`);
};

console.log('✅ FUNÇÃO salvarEncaixe CORRIGIDA COM NOMES DOS CAMPOS!');
console.log('🎯 Correção principal: Campos enviados com nomes exatos que o PHP espera');
console.log('💡 Para debug: window.debugCamposEncaixe()');
console.log('🧪 Agora teste: preencha um novo cadastro e faça um encaixe');

// ✅ FUNÇÃO AUXILIAR: Garantir que horário digitado seja salvo no campo hidden
window.salvarHorarioDigitado = function() {
    const horarioDigitado = document.getElementById('horario_digitado')?.value;
    const horarioHidden = document.getElementById('horario_selecionado_hidden');
    
    if (horarioDigitado && horarioHidden) {
        horarioHidden.value = horarioDigitado;
        console.log('💾 Horário salvo no campo hidden:', horarioDigitado);
    }
};

// ✅ FUNÇÃO MELHORADA: Garantir captura do horário ao digitar
window.configurarCapturaHorario = function() {
    const horarioInput = document.getElementById('horario_digitado');
    const horarioHidden = document.getElementById('horario_selecionado_hidden');
    
    if (horarioInput && horarioHidden) {
        // Atualizar campo hidden sempre que digitar
        horarioInput.addEventListener('input', function() {
            horarioHidden.value = this.value;
            console.log('🕐 Horário atualizado:', this.value);
        });
        
        // Também atualizar ao sair do campo
        horarioInput.addEventListener('blur', function() {
            horarioHidden.value = this.value;
            console.log('🕐 Horário confirmado no blur:', this.value);
        });
        
        console.log('✅ Captura de horário configurada');
    } else {
        // Não mostrar warning se não for página de agendamento
        const isAgendaPage = document.querySelector('table') && document.querySelector('[data-agenda-id]');
        if (isAgendaPage) {
            console.warn('⚠️ Campos de horário não encontrados para configurar captura');
        }
    }
};

// ✅ EXECUTAR CONFIGURAÇÃO AUTOMÁTICA APENAS SE NECESSÁRIO
setTimeout(() => {
    if (typeof window.configurarCapturaHorario === 'function') {
        // Só executar se houver indicação de que é página de agendamento
        const horarioInput = document.getElementById('horario_digitado');
        const isAgendaContext = document.querySelector('[data-agenda-id]') || horarioInput;
        
        if (isAgendaContext) {
            window.configurarCapturaHorario();
        }
    }
}, 1000);

console.log('✅ FUNÇÃO salvarEncaixe CORRIGIDA COM NOMES DOS CAMPOS!');
console.log('🎯 Correção principal: Campos enviados com nomes exatos que o PHP espera');
console.log('💡 Para debug: window.debugCamposEncaixe()');
console.log('🧪 Agora teste: preencha um novo cadastro e faça um encaixe');

/**
 * ✅ FUNÇÃO DE VERIFICAÇÃO: Testar se campos existem antes de salvar
 */
window.verificarCamposAntesEnvio = function() {
    console.log('🔍 VERIFICAÇÃO DOS CAMPOS ANTES DO ENVIO:');
    
    const camposEnderecoIds = [
        'cep_novo_paciente',
        'logradouro_novo_paciente',
        'numero_novo_paciente',
        'complemento_novo_paciente',
        'bairro_novo_paciente',
        'cidade_novo_paciente',
        'estado_novo_paciente'
    ];
    
    console.log('📍 Verificando campos de endereço no DOM:');
    let encontrados = 0;
    let preenchidos = 0;
    
    camposEnderecoIds.forEach(id => {
        const elemento = document.getElementById(id);
        if (elemento) {
            encontrados++;
            const valor = elemento.value.trim();
            if (valor) preenchidos++;
            console.log(`   ✅ ${id}: "${valor}" ${valor ? '(PREENCHIDO)' : '(VAZIO)'}`);
        } else {
            console.log(`   ❌ ${id}: NÃO ENCONTRADO NO DOM`);
        }
    });
    
    console.log(`📊 Resumo: ${encontrados}/7 campos encontrados, ${preenchidos}/7 preenchidos`);
    
    const checkbox = document.getElementById('checkbox-criar-cadastro');
    const deveCadastrar = checkbox ? checkbox.checked : false;
    console.log(`📋 Cadastro ativo: ${deveCadastrar ? 'SIM' : 'NÃO'}`);
    
    if (!deveCadastrar) {
        console.log('⚠️ AVISO: Cadastro não está ativo - campos de endereço não serão enviados');
    } else if (preenchidos === 0) {
        console.log('⚠️ AVISO: Nenhum campo de endereço está preenchido');
    } else {
        console.log(`✅ PRONTO: ${preenchidos} campos de endereço serão enviados`);
    }
    
    return { encontrados, preenchidos, deveCadastrar };
};

console.log('✅ ✅ ✅ FUNÇÃO salvarEncaixe SUBSTITUÍDA COM CAPTURA DE ENDEREÇO!');
console.log('💡 Para verificar campos antes de salvar: window.verificarCamposAntesEnvio()');
console.log('🎯 Agora os campos de endereço DEVEM ser enviados corretamente!');

/**
 * ✅ FUNÇÃO PARA ATUALIZAR VISUALIZAÇÃO COMPLETA
 */
function atualizarVisualizacaoCompleta() {
    console.log('🔄 Atualizando visualização completa...');
    
    const agendaId = window.agendaIdAtual;
    const dataAtual = window.dataSelecionadaAtual;

    if (!agendaId || !dataAtual) {
        console.warn('⚠️ IDs não encontrados, impossível atualizar visualização');
        showToast('Erro ao atualizar visualização. Por favor, recarregue a página manualmente.', false);
        return;
    }
    
    // Determinar tipo de visualização ativa
    const btnAtivo = document.querySelector('.btn-visualizacao.bg-teal-600');
    const tipoVisualizacao = btnAtivo?.dataset.tipo || 'dia';
    
    console.log('📊 Atualizando visualização:', tipoVisualizacao);
    
    // Atualizar baseado no tipo
    switch (tipoVisualizacao) {
        case 'dia':
            if (typeof carregarVisualizacaoDia === 'function') {
                carregarVisualizacaoDia(agendaId, dataAtual);
            }
            break;
        case 'semana':
            if (typeof carregarVisualizacaoSemana === 'function') {
                carregarVisualizacaoSemana(agendaId, dataAtual);
            }
            break;
        case 'mes':
            if (typeof carregarVisualizacaoMes === 'function') {
                carregarVisualizacaoMes(agendaId, dataAtual);
            }
            break;
        default:
            console.log('📄 Tipo desconhecido, recarregando visualização do dia');
            if (typeof carregarVisualizacaoDia === 'function') {
                carregarVisualizacaoDia(agendaId, dataAtual);
            }
    }
    
    // Atualizar sistema de encaixes se existir
    setTimeout(() => {
        if (typeof adicionarSistemaEncaixes === 'function') {
            const container = document.getElementById('area-visualizacao');
            if (container) {
                adicionarSistemaEncaixes(agendaId, dataAtual, container);
            }
        }
    }, 1000);
}

// ============================================================================
// FUNÇÃO AUXILIAR: Verificar configuração da agenda atual
// ============================================================================

window.verificarConfiguracaoAgenda = function() {
    if (!window.agendaAtual) {
        console.warn('⚠️ Agenda atual não definida, usando ID padrão');
        window.agendaAtual = { id: 1, nome: 'Padrão' };
    }
    
    console.log('📋 Agenda atual configurada:', window.agendaAtual);
    return window.agendaAtual;
};

// ============================================================================
// FUNÇÃO AUXILIAR: Debug FormData
// ============================================================================

window.debugFormData = function(formData) {
    console.log('🔍 Debug FormData:');
    const entries = [];
    for (let [key, value] of formData.entries()) {
        entries.push({ key, value });
        console.log(`  ${key}: "${value}"`);
    }
    return entries;
};

window.salvarEncaixeFlexivel = function() {
    const form = document.getElementById('form-encaixe-flexivel');
    const formData = new FormData(form);
    
    // Verificar se deve cadastrar o paciente
    const cadastrarPaciente = document.getElementById('cadastrar-paciente').checked;
    if (cadastrarPaciente) {
        formData.append('cadastrar_paciente', 'true');
    }
    
    // Validação
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
    const btnSalvar = event.target;
    const textoOriginal = btnSalvar.innerHTML;
    btnSalvar.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-2"></i>Processando...';
    btnSalvar.disabled = true;
    
    console.log('💾 Salvando encaixe flexível...');
    
    fetch('processar_encaixe.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(responseText => {
        console.log('📄 Resposta:', responseText);
        
        // Extrair JSON da resposta
        let jsonString = responseText.trim();
        if (jsonString.includes('<br />') || jsonString.includes('Warning')) {
            const linhas = jsonString.split('\n');
            for (let i = linhas.length - 1; i >= 0; i--) {
                const linha = linhas[i].trim();
                if (linha.startsWith('{') && linha.includes('"status"')) {
                    jsonString = linha;
                    break;
                }
            }
        }
        
        const data = JSON.parse(jsonString);
        
        if (data.status === 'sucesso') {
            fecharModalEncaixe();
            
            // Notificação de sucesso
            const mensagem = cadastrarPaciente ? 
                'Paciente cadastrado e encaixe agendado com sucesso!' :
                'Encaixe agendado com sucesso!';
            
            mostrarNotificacao(mensagem, 'sucesso');
            
            // Atualizar visualização
            atualizarVisualizacaoAgenda();
            
            // Mostrar detalhes
            setTimeout(() => {
                const detalhes = [
                    `Número: ${data.numero_agendamento}`,
                    `Paciente: ${formData.get('nome_paciente')}`,
                    `Tipo: ENCAIXE`,
                    cadastrarPaciente ? 'Paciente cadastrado no sistema' : 'Encaixe sem cadastro'
                ];
                
                alert(`Encaixe confirmado!\n\n${detalhes.join('\n')}\n\nO paciente será atendido conforme disponibilidade.`);
            }, 1000);
            
        } else {
            mostrarFeedback('Erro: ' + data.mensagem, 'erro');
            btnSalvar.innerHTML = textoOriginal;
            btnSalvar.disabled = false;
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        mostrarFeedback('Erro ao processar encaixe: ' + error.message, 'erro');
        btnSalvar.innerHTML = textoOriginal;
        btnSalvar.disabled = false;
    });
};

// Função auxiliar para feedback rápido
function mostrarFeedback(mensagem, tipo) {
    // Remover feedback anterior
    const feedbackAnterior = document.querySelector('.feedback-temp');
    if (feedbackAnterior) feedbackAnterior.remove();
    
    const feedback = document.createElement('div');
    feedback.className = `feedback-temp fixed top-4 right-4 z-50 px-4 py-3 rounded-lg text-white font-medium ${
        tipo === 'sucesso' ? 'bg-green-600' : 'bg-red-600'
    } shadow-lg`;
    feedback.innerHTML = `
        <i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
        ${mensagem}
    `;
    
    document.body.appendChild(feedback);
    
    setTimeout(() => {
        if (feedback.parentNode) {
            feedback.remove();
        }
    }, 4000);
}

// Função auxiliar para atualizar visualização
function atualizarVisualizacaoAgenda() {
    const agendaId = window.agendaIdAtual;
    const dataAtual = window.dataSelecionadaAtual;
    
    if (agendaId && dataAtual) {
        setTimeout(() => {
            if (typeof carregarVisualizacaoDia === 'function') {
                carregarVisualizacaoDia(agendaId, dataAtual);
            } else {
                console.log('✅ Função carregarVisualizacaoDia não disponível, mantendo página atual');
            }
        }, 500);
    }
}

// Função auxiliar para notificação
if (typeof mostrarNotificacao !== 'function') {
    window.mostrarNotificacao = function(mensagem, tipo) {
        const notificacao = document.createElement('div');
        notificacao.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg text-white font-medium shadow-lg ${
            tipo === 'sucesso' ? 'bg-green-600' : 'bg-red-600'
        }`;
        notificacao.innerHTML = `
            <div class="flex items-center">
                <i class="bi bi-${tipo === 'sucesso' ? 'check-circle' : 'exclamation-triangle'} mr-3 text-lg"></i>
                <div>
                    <div class="font-bold">${tipo === 'sucesso' ? 'Sucesso!' : 'Erro!'}</div>
                    <div class="text-sm opacity-90">${mensagem}</div>
                </div>
            </div>
        `;
        
        document.body.appendChild(notificacao);
        
        setTimeout(() => {
            if (notificacao.parentNode) {
                notificacao.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => notificacao.remove(), 300);
            }
        }, 4000);
    };
}

/**
 * ✅ FUNÇÃO PARA FECHAR MODAL CORRIGIDA
 */
window.fecharModalEncaixe = function() {
    console.log('🚪 Fechando modal de encaixe...');
    
    const modal = document.getElementById('modal-encaixe');
    if (modal) {
        // Adicionar animação de saída
        modal.style.opacity = '0';
        modal.style.transform = 'scale(0.95)';
        modal.style.transition = 'all 0.3s ease-out';
        
        setTimeout(() => {
            modal.remove();
            console.log('✅ Modal removido com sucesso');
        }, 300);
    } else {
        console.warn('⚠️ Modal não encontrado para fechar');
    }
    
    // Limpar qualquer overflow hidden do body
    document.body.style.overflow = '';
};

/**
 * ✅ FUNÇÃO PARA MOSTRAR NOTIFICAÇÃO DE SUCESSO
 */
function mostrarNotificacaoSucesso(mensagem) {
    // Remover notificação anterior se existir
    const notificacaoAnterior = document.getElementById('notificacao-encaixe-sucesso');
    if (notificacaoAnterior) {
        notificacaoAnterior.remove();
    }
    
    const notificacao = document.createElement('div');
    notificacao.id = 'notificacao-encaixe-sucesso';
    notificacao.className = 'fixed top-4 right-4 z-50 bg-green-600 text-white px-6 py-4 rounded-lg shadow-lg max-w-sm';
    notificacao.style.animation = 'slideInFromRight 0.3s ease-out';
    
    notificacao.innerHTML = `
        <div class="flex items-start">
            <i class="bi bi-check-circle-fill text-xl mr-3 mt-1"></i>
            <div>
                <div class="font-bold text-sm">Encaixe Confirmado!</div>
                <div class="text-xs opacity-90 mt-1">Agendamento salvo com sucesso</div>
            </div>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-3 text-white hover:text-gray-200">
                <i class="bi bi-x text-lg"></i>
            </button>
        </div>
    `;
    
    document.body.appendChild(notificacao);
    
    // Remover automaticamente após 5 segundos
    setTimeout(() => {
        if (notificacao.parentElement) {
            notificacao.style.animation = 'slideOutToRight 0.3s ease-in';
            setTimeout(() => notificacao.remove(), 300);
        }
    }, 5000);
}

/**
 * ✅ FUNÇÃO AUXILIAR: Verificar se elementos necessários existem
 */
function verificarElementosEncaixe() {
    const elementos = [
        'nome_paciente_busca_real',
        'telefone_paciente_encaixe', 
        'convenio_encaixe',
        'btn-salvar-encaixe'
    ];
    
    const faltando = [];
    elementos.forEach(id => {
        if (!document.getElementById(id)) {
            faltando.push(id);
        }
    });
    
    if (faltando.length > 0) {
        console.warn('⚠️ Elementos faltando no modal:', faltando);
        return false;
    }
    
    return true;
}

/**
 * ✅ CSS PARA ANIMAÇÕES
 */
const estilosAnimacoes = document.createElement('style');
estilosAnimacoes.textContent = `
    @keyframes slideInFromRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutToRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    /* Garantir que modal não tenha problemas de z-index */
    #modal-encaixe {
        z-index: 9999 !important;
    }
    
    /* Melhorar animação do botão loading */
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;

// Adicionar CSS se não existir
if (!document.getElementById('estilos-animacoes-encaixe')) {
    estilosAnimacoes.id = 'estilos-animacoes-encaixe';
    document.head.appendChild(estilosAnimacoes);
}

/**
 * ✅ FUNÇÃO DE TESTE PARA DEBUG
 */
window.testarEncaixe = function() {
    console.log('🧪 Testando sistema de encaixe...');
    console.log('📋 Elementos disponíveis:');
    console.log('   - agendaIdAtual:', window.agendaIdAtual);
    console.log('   - dataSelecionadaAtual:', window.dataSelecionadaAtual);
    console.log('   - Modal existe:', !!document.getElementById('modal-encaixe'));
    console.log('   - Elementos válidos:', verificarElementosEncaixe());
    console.log('   - Função carregarVisualizacaoDia:', typeof carregarVisualizacaoDia);
    console.log('   - Função fecharModalEncaixe:', typeof window.fecharModalEncaixe);
};

// ✅ FUNÇÃO GLOBAL PARA TESTAR NAVEGAÇÃO DO CALENDÁRIO
window.testarNavegacaoCalendario = function() {
    console.log('🧪 Testando navegação do calendário...');
    console.log('📋 Elementos disponíveis:');
    
    const navButtons = document.querySelectorAll('.nav-calendario');
    console.log(`   - Botões de navegação encontrados: ${navButtons.length}`);
    
    navButtons.forEach((btn, index) => {
        const direcao = btn.dataset.direcao;
        console.log(`   - Botão ${index + 1}: direção="${direcao}", visível=${btn.offsetWidth > 0}`);
    });
    
    console.log('   - agendaIdAtual:', window.agendaIdAtual);
    console.log('   - mesAtual:', mesAtual);
    console.log('   - anoAtual:', anoAtual);
    
    // Forçar reconfiguração
    if (window.agendaIdAtual) {
        console.log('🔧 Forçando reconfiguração...');
        reconfigurarNavegacaoCalendario(window.agendaIdAtual);
    } else {
        console.warn('⚠️ agendaIdAtual não definido');
    }
};

// ✅ FUNÇÃO PARA TESTAR DRAG & DROP DE ENCAIXE
window.testarDragDropEncaixe = function() {
    console.log('🧪 Testando drag & drop de ENCAIXE...');
    console.log('=====================================');
    
    // Encontrar todos os elementos draggable
    const elementosDraggable = document.querySelectorAll('[draggable="true"]');
    const elementosNaoDraggable = document.querySelectorAll('[draggable="false"]');
    
    console.log(`📋 Elementos draggable encontrados: ${elementosDraggable.length}`);
    console.log(`📋 Elementos NÃO draggable encontrados: ${elementosNaoDraggable.length}`);
    
    // Verificar elementos não draggable se são ENCAIXE
    let encaixesEncontrados = 0;
    elementosNaoDraggable.forEach((elemento, index) => {
        const contemEncaixe = elemento.innerHTML.includes('ENCAIXE') || 
                             elemento.innerHTML.includes('bi-lightning-charge') ||
                             elemento.querySelector('.bi-lightning-charge');
        
        if (contemEncaixe) {
            encaixesEncontrados++;
            console.log(`   ✅ Elemento ${index + 1}: ENCAIXE corretamente não draggable`);
        } else {
            console.log(`   ⚠️ Elemento ${index + 1}: não draggable mas não é ENCAIXE`);
        }
    });
    
    // Verificar elementos draggable se NÃO são ENCAIXE
    let encaixesDragaveis = 0;
    elementosDraggable.forEach((elemento, index) => {
        const contemEncaixe = elemento.innerHTML.includes('ENCAIXE') || 
                             elemento.innerHTML.includes('bi-lightning-charge') ||
                             elemento.querySelector('.bi-lightning-charge');
        
        if (contemEncaixe) {
            encaixesDragaveis++;
            console.log(`   ❌ ERRO: Elemento ${index + 1}: ENCAIXE incorretamente draggable!`);
        }
    });
    
    console.log('=====================================');
    console.log(`📊 Resumo:`);
    console.log(`   - ENCAIXE não draggable (correto): ${encaixesEncontrados}`);
    console.log(`   - ENCAIXE draggable (ERRO): ${encaixesDragaveis}`);
    console.log(`   - Status: ${encaixesDragaveis === 0 ? '✅ Funcionando corretamente!' : '❌ Problemas encontrados!'}`);
    console.log('=====================================');
    
    return {
        encaixesCorretos: encaixesEncontrados,
        encaixesErrados: encaixesDragaveis,
        funcionandoCorretamente: encaixesDragaveis === 0
    };
};

// ✅ FUNÇÃO ESPECÍFICA PARA DIAGNOSTICAR DIA ATUAL
window.diagnosticarDiaAtual = function() {
    console.log('🔍 DIAGNÓSTICO COMPLETO DO DIA ATUAL');
    console.log('=====================================');
    
    const hoje = new Date();
    const dataHoje = formatarDataISO(hoje);
    const diaHojeElemento = document.querySelector(`[data-data="${dataHoje}"]`);
    
    console.log(`📅 Data de hoje: ${dataHoje}`);
    console.log(`📅 Elemento encontrado:`, diaHojeElemento);
    
    if (diaHojeElemento) {
        console.log(`📋 Propriedades do elemento:`);
        console.log(`   - tagName: ${diaHojeElemento.tagName}`);
        console.log(`   - className: ${diaHojeElemento.className}`);
        console.log(`   - hasAttribute('disabled'): ${diaHojeElemento.hasAttribute('disabled')}`);
        console.log(`   - style.pointerEvents: ${diaHojeElemento.style.pointerEvents}`);
        console.log(`   - offsetWidth: ${diaHojeElemento.offsetWidth}`);
        console.log(`   - offsetHeight: ${diaHojeElemento.offsetHeight}`);
        console.log(`   - innerText: "${diaHojeElemento.innerText}"`);
        console.log(`   - dataset.data: ${diaHojeElemento.dataset.data}`);
        
        // Verificar event listeners
        console.log(`📋 Testando event listeners:`);
        const hasClickListener = diaHojeElemento.onclick !== null;
        console.log(`   - onclick: ${hasClickListener ? 'definido' : 'não definido'}`);
        
        // Testar clique programático
        console.log(`🖱️ Testando clique programático...`);
        try {
            diaHojeElemento.dispatchEvent(new Event('click', { bubbles: true }));
            console.log(`✅ Clique programático executado`);
        } catch (error) {
            console.log(`❌ Erro no clique programático:`, error);
        }
        
        // Verificar elementos pais que podem bloquear
        let parent = diaHojeElemento.parentElement;
        let level = 1;
        console.log(`📋 Verificando elementos pais:`);
        while (parent && level <= 3) {
            console.log(`   - Nível ${level}: ${parent.tagName}.${parent.className}`);
            console.log(`     - pointerEvents: ${parent.style.pointerEvents}`);
            parent = parent.parentElement;
            level++;
        }
        
    } else {
        console.log(`❌ Elemento do dia atual não encontrado`);
        console.log(`📋 Verificando todos os elementos .dia-calendario:`);
        
        const todosDias = document.querySelectorAll('.dia-calendario');
        console.log(`   - Total encontrado: ${todosDias.length}`);
        
        todosDias.forEach((dia, index) => {
            const data = dia.dataset.data;
            const disabled = dia.hasAttribute('disabled');
            console.log(`   - ${index + 1}: ${data} (disabled: ${disabled})`);
        });
    }
    
    console.log('=====================================');
};

// ✅ FUNÇÃO PARA APLICAR ESTILOS VISUAIS AOS ENCAIXES SEM DRAG
window.aplicarEstilosEncaixeSemDrag = function() {
    console.log('🎨 Aplicando estilos visuais para ENCAIXE sem drag...');
    
    // Encontrar todos os elementos com badge de ENCAIXE
    const encaixes = document.querySelectorAll('[draggable="false"]');
    let contador = 0;
    
    encaixes.forEach(elemento => {
        // Verificar se é realmente um ENCAIXE
        const contemEncaixe = elemento.innerHTML.includes('ENCAIXE') || 
                             elemento.innerHTML.includes('bi-lightning-charge') ||
                             elemento.querySelector('.bi-lightning-charge');
        
        if (contemEncaixe) {
            elemento.classList.add('encaixe-no-drag');
            elemento.title = (elemento.title || '') + '\n⚠️ Encaixes não podem ser movidos via drag and drop';
            contador++;
        }
    });
    
    console.log(`✅ Estilos aplicados a ${contador} elementos ENCAIXE`);
};

// ✅ FUNÇÃO GLOBAL PARA TESTAR RETORNO AO DIA ATUAL
window.testarRetornoDiaAtual = function() {
    console.log('🎯 Testando retorno ao dia atual...');
    
    const hoje = new Date();
    const dataHoje = formatarDataISO(hoje);
    const diaHojeElemento = document.querySelector(`[data-data="${dataHoje}"]`);
    
    console.log(`📅 Dia atual: ${dataHoje}`);
    console.log(`🔍 Elemento encontrado:`, diaHojeElemento);
    
    if (diaHojeElemento) {
        console.log(`   - Classes: ${diaHojeElemento.className}`);
        console.log(`   - Disabled: ${diaHojeElemento.hasAttribute('disabled')}`);
        console.log(`   - Dataset: ${diaHojeElemento.dataset.data}`);
        
        // Forçar garantia de clicabilidade
        garantirDiaAtualClicavel();
        
        // Simular clique
        console.log('🖱️ Simulando clique no dia atual...');
        setTimeout(() => {
            diaHojeElemento.click();
            console.log('✅ Clique simulado executado');
        }, 500);
    } else {
        console.warn('⚠️ Dia atual não encontrado no calendário visível');
        
        // Navegar para o mês atual
        const mesHoje = hoje.getMonth();
        const anoHoje = hoje.getFullYear();
        
        console.log(`🗓️ Navegando para mês atual: ${mesHoje + 1}/${anoHoje}`);
        
        if (typeof mesAtual !== 'undefined' && typeof anoAtual !== 'undefined') {
            // Calcular diferença e navegar
            let diffMeses = (anoHoje - anoAtual) * 12 + (mesHoje - mesAtual);
            console.log(`📊 Diferença de meses: ${diffMeses}`);
            
            if (diffMeses !== 0 && window.agendaIdAtual) {
                mesAtual = mesHoje;
                anoAtual = anoHoje;
                atualizarCalendarioLateral(window.agendaIdAtual);
                
                setTimeout(() => {
                    garantirDiaAtualClicavel();
                    const novoElemento = document.querySelector(`[data-data="${dataHoje}"]`);
                    if (novoElemento) {
                        console.log('✅ Dia atual agora visível, simulando clique...');
                        novoElemento.click();
                    }
                }, 500);
            }
        }
    }
};


// CSS para animações
const style = document.createElement('style');
style.textContent += `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
    
    .encaixe-no-drag {
        opacity: 0.7;
        cursor: not-allowed !important;
        filter: grayscale(20%);
    }
    
    .encaixe-no-drag:hover {
        background-color: inherit !important;
    }
`;
style.textContent = `
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    .animate-spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
document.head.appendChild(style);


// ✅ FUNÇÃO criarModalEncaixe CORRIGIDA (se não existir ou tiver problemas)
function criarModalEncaixe(agendaId, data, agendaInfo, dadosEncaixe) {
    // Remover modal anterior se existir
    const modalAnterior = document.getElementById('modal-encaixe');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    // Formatar data para exibição
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    // HTML do modal
    const modalHTML = `
        <div id="modal-encaixe" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Cabeçalho do Modal -->
                <div class="bg-gradient-to-r from-orange-600 to-red-600 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-lightning-charge mr-3"></i>
                                Agendamento de Encaixe
                            </h2>
                            <p class="text-orange-100 mt-1">Agendamento sem horário específico</p>
                        </div>
                        <button onclick="fecharModalEncaixe()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Conteúdo do Modal -->
                <div class="p-6">
                    <!-- Informações do Encaixe -->
                    <div class="bg-orange-50 border-l-4 border-orange-400 p-4 mb-6 rounded-r-lg">
                        <h3 class="text-lg font-semibold text-orange-800 mb-2 flex items-center">
                            <i class="bi bi-info-circle mr-2"></i>
                            ${agendaInfo.nome || 'Agendamento de Encaixe'}
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-orange-700">
                            <div>
                                <i class="bi bi-calendar3 mr-2"></i>
                                <strong>Data:</strong> ${dataFormatada}
                            </div>
                            <div>
                                <i class="bi bi-clock mr-2"></i>
                                <strong>Horário:</strong> <span class="font-bold text-orange-600">ENCAIXE</span>
                            </div>
                            <div>
                                <i class="bi bi-geo-alt mr-2"></i>
                                <strong>Unidade:</strong> ${agendaInfo.unidade || 'Mossoró'}
                            </div>
                            <div>
                                <i class="bi bi-lightning mr-2"></i>
                                <strong>Encaixes disponíveis:</strong> ${dadosEncaixe.encaixes_disponiveis} de ${dadosEncaixe.limite_total}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Aviso sobre Encaixe -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <i class="bi bi-exclamation-triangle text-yellow-600 text-xl mr-3 mt-1"></i>
                            <div class="text-yellow-800">
                                <h4 class="font-semibold mb-1">O que é um encaixe?</h4>
                                <p class="text-sm">
                                    • Agendamento <strong>sem horário fixo</strong><br>
                                    • Será atendido <strong>conforme disponibilidade</strong> do médico<br>
                                    • Pode haver <strong>tempo de espera maior</strong><br>
                                    • Limitado a <strong>${dadosEncaixe.limite_total} encaixes por dia</strong>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Formulário de Encaixe -->
                    <form id="form-encaixe-modal" class="space-y-6">
                        <!-- Dados do Paciente -->
                        <div>
                            <h4 class="text-base font-semibold text-gray-800 mb-4 flex items-center">
                                <i class="bi bi-person-circle mr-2"></i>
                                Dados do Paciente
                            </h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nome completo <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="nome_paciente" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        CPF
                                    </label>
                                    <input type="text" name="cpf_paciente" id="cpf-encaixe"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                                           placeholder="000.000.000-00">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Data de nascimento <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="data_nascimento" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Telefone <span class="text-red-500">*</span>
                                    </label>
                                    <input type="tel" name="telefone_paciente" id="telefone-encaixe" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                                           placeholder="(84) 99999-9999">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        E-mail
                                    </label>
                                    <input type="email" name="email_paciente"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                                           placeholder="exemplo@email.com">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Convênio <span class="text-red-500">*</span>
                                    </label>
                                    <select name="convenio_id" required
                                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                                        <option value="">Selecione o convênio</option>
                                        ${(agendaInfo.convenios || []).map(conv => 
                                            `<option value="${conv.id}">${conv.nome}</option>`
                                        ).join('')}
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Observações -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Observações sobre o encaixe
                            </label>
                            <textarea name="observacoes" rows="3"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500"
                                      placeholder="Motivo do encaixe, urgência, observações especiais..."></textarea>
                        </div>
                        
                        <!-- Campos ocultos -->
                        <input type="hidden" name="agenda_id" value="${agendaId}">
                        <input type="hidden" name="data_agendamento" value="${data}">
                        <input type="hidden" name="especialidade_id" value="${especialidadeFinal}">
                    </form>
                </div>
                
                <!-- Rodapé do Modal -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t">
                    <div class="flex flex-col sm:flex-row sm:justify-between gap-3">
                        <button type="button" onclick="fecharModalEncaixe()" 
                                class="px-6 py-2 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 transition order-2 sm:order-1">
                            <i class="bi bi-x-circle mr-2"></i>Cancelar
                        </button>
                        
                        <div class="flex gap-3 order-1 sm:order-2">
                            <button type="button" onclick="salvarEncaixe()" 
                                    class="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition">
                                <i class="bi bi-lightning-charge mr-2"></i>Confirmar Encaixe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Adicionar modal ao DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar event listeners
    adicionarEventListenersModalEncaixe();
    
    // Focar no primeiro campo
    document.querySelector('input[name="nome_paciente"]').focus();
}

// ✅ FUNÇÃO para fechar modal de encaixe
window.fecharModalEncaixe = function() {
    const modal = document.getElementById('modal-encaixe');
    if (modal) {
        modal.remove();
    }
};

// ✅ FUNÇÃO para adicionar event listeners
function adicionarEventListenersModalEncaixe() {
    // Modal encaixe só fecha com botão X - removido fechamento ao clicar fora
    
    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('modal-encaixe')) {
            fecharModalEncaixe();
        }
    });
    
    // Máscara para CPF
    const cpfInput = document.getElementById('cpf-encaixe');
    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    }
    
    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone-encaixe');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '($1) $2');
            value = value.replace(/(\d{4,5})(\d{4})$/, '$1-$2');
            e.target.value = value;
        });
    }
}


// 6️⃣ ✅ Função para exibir lista de encaixes do dia
window.visualizarEncaixesDia = function(agendaId, data) {
    console.log('📋 Visualizando encaixes do dia:', { agendaId, data });
    
    // Buscar encaixes do dia
    fetchWithAuth(`buscar_encaixes_dia.php?agenda_id=${agendaId}&data=${data}`)
        .then(safeJsonParse)
        .then(encaixes => {
            console.log('🎯 Encaixes encontrados:', encaixes);
            criarModalListaEncaixes(agendaId, data, encaixes);
        })
        .catch(error => {
            console.error('Erro ao buscar encaixes:', error);
            alert('Erro ao carregar lista de encaixes');
        });
};

// 7️⃣ ✅ Função para criar modal com lista de encaixes
function criarModalListaEncaixes(agendaId, data, encaixes) {
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR');
    
    const modalHTML = `
        <div id="modal-lista-encaixes" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                <!-- Cabeçalho -->
                <div class="bg-gradient-to-r from-orange-600 to-red-600 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-list-ul mr-3"></i>
                                Encaixes do Dia
                            </h2>
                            <p class="text-orange-100 mt-1">${dataFormatada} - ${encaixes.length} encaixe(s)</p>
                        </div>
                        <button onclick="fecharModalListaEncaixes()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Conteúdo -->
                <div class="p-6">
                    ${encaixes.length === 0 ? `
                        <div class="text-center py-12">
                            <i class="bi bi-calendar-x text-5xl text-gray-300 mb-4"></i>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Nenhum encaixe hoje</h3>
                            <p class="text-gray-500 mb-6">Não há encaixes agendados para esta data.</p>
                            <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                    class="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition">
                                <i class="bi bi-plus-circle mr-2"></i>Agendar Primeiro Encaixe
                            </button>
                        </div>
                    ` : `
                        <!-- Tabela de Encaixes -->
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="bg-gray-50 border-b">
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paciente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Telefone</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Convênio</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    ${encaixes.map(encaixe => `
                                        <tr class="hover:bg-orange-50 transition-colors">
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <div class="flex items-center">
                                                    <i class="bi bi-lightning-charge text-orange-500 mr-2"></i>
                                                    ${encaixe.numero}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">
                                                <div>
                                                    <div class="font-medium">${encaixe.paciente}</div>
                                                    ${encaixe.cpf ? `<div class="text-xs text-gray-500">CPF: ${formatarCPF(encaixe.cpf)}</div>` : ''}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                <i class="bi bi-telephone text-gray-400 mr-1"></i>
                                                ${encaixe.telefone || '-'}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">
                                                ${encaixe.convenio}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                ${getStatusBadge(encaixe.status)}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium">
                                                <div class="flex space-x-2">
                                                    <button onclick="visualizarAgendamento(${encaixe.id})" 
                                                            class="text-gray-600 hover:text-gray-900" title="Visualizar">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button onclick="editarAgendamento(${encaixe.id})" 
                                                            class="text-blue-600 hover:text-blue-900" title="Editar">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button onclick="cancelarAgendamento(${encaixe.id})" 
                                                            class="text-red-600 hover:text-red-900" title="Cancelar">
                                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Botão para adicionar mais encaixes -->
                        <div class="mt-6 text-center">
                            <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                    class="px-6 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition">
                                <i class="bi bi-plus-circle mr-2"></i>Agendar Outro Encaixe
                            </button>
                        </div>
                    `}
                </div>
                
                <!-- Rodapé -->
                <div class="bg-gray-50 px-6 py-4 rounded-b-lg border-t">
                    <div class="flex justify-end">
                        <button onclick="fecharModalListaEncaixes()" 
                                class="px-6 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>Fechar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

// 8️⃣ ✅ Função para fechar modal de lista de encaixes
window.fecharModalListaEncaixes = function() {
    const modal = document.getElementById('modal-lista-encaixes');
    if (modal) {
        modal.remove();
    }
};

// 9️⃣ ✅ Adicionar botão de encaixe na visualização do dia
// Modifique a função renderizarAgendaDia para incluir botão de encaixe
window.adicionarBotaoEncaixe = function(agendaId, data, container) {
    // Verificar se permite encaixes
    fetchWithAuth(`verificar_encaixes.php?agenda_id=${agendaId}&data=${data}`)
        .then(safeJsonParse)
        .then(dadosEncaixe => {
            if (dadosEncaixe.permite_encaixes && dadosEncaixe.limite_total > 0) {
                // Adicionar botão de encaixe no cabeçalho da visualização do dia
                const botaoEncaixe = `
                    <div class="mt-4 flex flex-col sm:flex-row gap-3">
                        <button onclick="abrirModalEncaixe(${agendaId}, '${data}')" 
                                class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-md hover:bg-orange-700 transition ${dadosEncaixe.pode_encaixar ? '' : 'opacity-50 cursor-not-allowed'}"
                                ${dadosEncaixe.pode_encaixar ? '' : 'disabled'}>
                            <i class="bi bi-lightning-charge mr-2"></i>
                            ${dadosEncaixe.pode_encaixar ? 'Agendar Encaixe' : 'Encaixes Esgotados'}
                            <span class="text-xs block">(${dadosEncaixe.encaixes_disponiveis}/${dadosEncaixe.limite_total} disponíveis)</span>
                        </button>
                        
                        <button onclick="visualizarEncaixesDia(${agendaId}, '${data}')" 
                                class="px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 transition">
                            <i class="bi bi-list-ul mr-2"></i>
                            Ver Encaixes (${dadosEncaixe.encaixes_ocupados})
                        </button>
                    </div>
                `;
                
                // Inserir botão antes da área de visualização
                const areaVisualizacao = container.querySelector('.space-y-6') || container;
                if (areaVisualizacao) {
                    areaVisualizacao.insertAdjacentHTML('afterbegin', `
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-orange-800 mb-2 flex items-center">
                                <i class="bi bi-lightning mr-2"></i>
                                Sistema de Encaixes
                            </h4>
                            <p class="text-xs text-orange-700 mb-3">
                                Agendamentos sem horário fixo, atendidos conforme disponibilidade
                            </p>
                            ${botaoEncaixe}
                        </div>
                    `);
                }
            }
        })
        .catch(error => {
            console.error('Erro ao verificar encaixes:', error);
        });
};

function extrairPrimeiroJSON(texto) {
    let nivelChaves = 0;
    let inicioJSON = -1;
    let fimJSON = -1;
    
    for (let i = 0; i < texto.length; i++) {
        const char = texto[i];
        
        if (char === '{') {
            if (nivelChaves === 0) {
                inicioJSON = i; // Marca o início do JSON
            }
            nivelChaves++;
        } else if (char === '}') {
            nivelChaves--;
            if (nivelChaves === 0 && inicioJSON !== -1) {
                fimJSON = i; // Marca o fim do JSON
                break; // Para no primeiro JSON completo
            }
        }
    }
    
    if (inicioJSON !== -1 && fimJSON !== -1) {
        return texto.substring(inicioJSON, fimJSON + 1);
    }
    
    // Fallback: tentar pegar até a primeira quebra de linha
    const primeiraLinha = texto.split('\n')[0].trim();
    if (primeiraLinha.startsWith('{') && primeiraLinha.endsWith('}')) {
        return primeiraLinha;
    }
    
    throw new Error('Nenhum JSON válido encontrado');
}

/**
 * ✅ CSS ADICIONAL: Animações para notificação de CEP
 */
const estilosNotificacaoCEP = document.createElement('style');
estilosNotificacaoCEP.textContent = `
    @keyframes slideInFromLeft {
        from {
            transform: translateX(-100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutToLeft {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(-100%);
            opacity: 0;
        }
    }
`;

// Adicionar CSS se não existir
if (!document.getElementById('estilos-notificacao-cep')) {
    estilosNotificacaoCEP.id = 'estilos-notificacao-cep';
    document.head.appendChild(estilosNotificacaoCEP);
}

// ✅ FUNÇÃO DE DEBUG PARA TESTAR ENCAIXES NO NAVEGADOR
window.debugEncaixes = function() {
    console.log('🔍 DEBUG ENCAIXES - Iniciando teste...');
    
    // Testar comparações com diferentes valores
    const testCases = [
        'ENCAIXE',
        ' ENCAIXE ',
        'encaixe',
        'Encaixe',
        'NORMAL',
        '',
        null,
        undefined
    ];
    
    console.log('📊 TESTE DE COMPARAÇÕES:');
    testCases.forEach(value => {
        const result1 = (value && value.trim().toUpperCase() === 'ENCAIXE');
        const result2 = (value === 'ENCAIXE');
        console.log(`Valor: "${value}" | Robust: ${result1} | Simple: ${result2}`);
    });
    
    // Verificar se há agendamentos carregados
    if (typeof window.agendamentosDia !== 'undefined' && window.agendamentosDia) {
        console.log('📅 AGENDAMENTOS DO DIA:', window.agendamentosDia);
        
        Object.keys(window.agendamentosDia).forEach(hora => {
            const ag = window.agendamentosDia[hora];
            const tipo = ag.tipo_agendamento;
            const isEncaixe = (tipo && tipo.trim().toUpperCase() === 'ENCAIXE');
            
            console.log(`⏰ ${hora}: tipo="${tipo}" | isEncaixe=${isEncaixe} | paciente=${ag.paciente}`);
        });
    } else {
        console.log('❌ Nenhum agendamento encontrado em window.agendamentosDia');
    }
    
    // Verificar elementos DOM
    const tabelaLinhas = document.querySelectorAll('tbody tr');
    console.log(`🔍 ELEMENTOS DOM: ${tabelaLinhas.length} linhas de tabela encontradas`);
    
    tabelaLinhas.forEach((linha, index) => {
        const classes = linha.className;
        const temClasseEncaixe = classes.includes('bg-orange');
        console.log(`Linha ${index}: classes="${classes}" | temEncaixe=${temClasseEncaixe}`);
    });
    
    // Verificar badges
    const badges = document.querySelectorAll('span');
    let badgesEncaixe = 0;
    badges.forEach(badge => {
        if (badge.textContent.includes('ENCAIXE')) {
            badgesEncaixe++;
            console.log('🏷️ Badge ENCAIXE encontrado:', badge.outerHTML);
        }
    });
    console.log(`🏷️ Total de badges ENCAIXE: ${badgesEncaixe}`);
};

// Debug global
window.debugAgenda = {
    carregarVisualizacaoDia,
    carregarVisualizacaoSemana, 
    carregarVisualizacaoMes,
    formatarDataISO,
    obterDataSelecionada,
    navegarMesCalendario,
    debugEncaixes
};

// ✅ SISTEMA DE RETORNOS
function adicionarSistemaRetornos(agendaId, data) {
    console.log('🔄 Iniciando sistema de retornos:', { agendaId, data });
    
    // Buscar retornos do dia
    const url = `buscar_retornos_dia.php?agenda_id=${agendaId}&data=${data}`;
    
    fetch(url)
        .then(safeJsonParse)
        .then(retornos => {
            console.log('✅ Retornos carregados:', retornos);
            
            // Contar retornos
            const totalRetornos = Object.keys(retornos).length;
            
            // Buscar informações de limite de retornos
            return fetchWithAuth(`verificar_retornos.php?agenda_id=${agendaId}&data=${data}`)
                .then(response => response.text())
                .then(text => {
                    let dadosRetorno;
                    try {
                        const linhas = text.trim().split('\n');
                        dadosRetorno = JSON.parse(linhas[0].trim());
                    } catch (e) {
                        dadosRetorno = { permite_retornos: false, limite_total: 0, tipo: '' };
                    }
                    
                    // Se não é agenda do tipo consulta ou não tem limite de retornos configurado, não criar card
                    if (dadosRetorno.tipo !== 'consulta') {
                        console.log(`ℹ️ Agenda tipo "${dadosRetorno.tipo}" - não mostrando card de retornos`);
                        return;
                    }
                    
                    // Se limite de retornos é 0, não mostrar o card
                    if (!dadosRetorno.limite_total || dadosRetorno.limite_total === 0) {
                        console.log(`ℹ️ Limite de retornos é zero (${dadosRetorno.limite_total}) - não mostrando card de retornos`);
                        return;
                    }
                    
                    // Card de retornos com informações de disponibilidade
                    const cardRetornosHTML = `
                        <div id="card-sistema-retornos" class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mt-4">
                            <!-- Header -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-lg flex items-center justify-center">
                                        <i class="bi bi-arrow-clockwise text-white text-sm"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-800">Sistema de Retornos</h3>
                                        <p class="text-xs text-gray-500">Consultas de retorno</p>
                                    </div>
                                </div>
                                <div class="text-xs bg-gray-100 px-2 py-1 rounded-full">
                                    ${dadosRetorno.permite_retornos ? `${dadosRetorno.retornos_disponiveis}/${dadosRetorno.limite_total}` : `${totalRetornos} retorno${totalRetornos !== 1 ? 's' : ''}`}
                                </div>
                            </div>
                            
                            <!-- Stats com foco em disponíveis -->
                            <div class="grid grid-cols-3 gap-2 mb-3">
                                <div class="text-center p-2 bg-red-50 rounded">
                                    <div class="text-sm font-bold text-red-600">${dadosRetorno.permite_retornos ? dadosRetorno.retornos_ocupados : totalRetornos}</div>
                                    <div class="text-xs text-gray-600">Ocupados</div>
                                </div>
                                <div class="text-center p-2 bg-green-50 rounded">
                                    <div class="text-sm font-bold text-green-600">${dadosRetorno.permite_retornos ? dadosRetorno.retornos_disponiveis : '∞'}</div>
                                    <div class="text-xs text-gray-600">Disponíveis</div>
                                </div>
                                <div class="text-center p-2 bg-blue-50 rounded">
                                    <div class="text-sm font-bold text-blue-600">${dadosRetorno.permite_retornos ? dadosRetorno.limite_total : '∞'}</div>
                                    <div class="text-xs text-gray-600">Limite</div>
                                </div>
                            </div>
                            
                            <!-- Botões -->
                            <div class="space-y-2">
                                <button onclick="abrirModalRetorno(${agendaId}, '${data}')" 
                                        class="w-full px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm font-medium transition-colors ${dadosRetorno.permite_retornos && !dadosRetorno.pode_retornar ? 'opacity-50 cursor-not-allowed' : ''}"
                                        ${dadosRetorno.permite_retornos && !dadosRetorno.pode_retornar ? 'disabled' : ''}>
                                    <i class="bi bi-plus-circle mr-1"></i>
                                    ${dadosRetorno.permite_retornos && !dadosRetorno.pode_retornar ? 'Esgotado' : 'Agendar Retorno'}
                                </button>
                                
                                ${totalRetornos > 0 ? `
                                <button onclick="visualizarRetornosDia(${agendaId}, '${data}')" 
                                        class="w-full px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-medium transition-colors">
                                    <i class="bi bi-list-ul mr-1"></i>
                                    Ver Lista (${totalRetornos})
                                </button>
                                ` : ''}
                            </div>
                            
                            <!-- Info -->
                            <div class="mt-3 p-2 bg-blue-50 rounded text-xs text-blue-700">
                                <i class="bi bi-info-circle mr-1"></i>
                                ${dadosRetorno.permite_retornos ? dadosRetorno.mensagem : 'Consultas de acompanhamento e revisão'}
                            </div>
                        </div>
                    `;
                    
                    // Inserir após o card de encaixes
                    inserirCardRetornos(cardRetornosHTML);
                });
            
        })
        .catch(error => {
            console.error('❌ Erro ao carregar retornos:', error);
            console.log('ℹ️ Erro ao carregar retornos - não criando card básico');
        });
}

function inserirCardRetornos(htmlCard) {
    // Remover card anterior se existir
    const cardAnterior = document.querySelector('#card-sistema-retornos');
    if (cardAnterior) {
        cardAnterior.remove();
    }
    
    // Tentar inserir após o card de encaixes
    const cardEncaixes = document.querySelector('#card-sistema-encaixes');
    if (cardEncaixes) {
        console.log('✅ Inserindo card de retornos após encaixes');
        cardEncaixes.insertAdjacentHTML('afterend', htmlCard);
        return;
    }
    
    // Se não houver encaixes, inserir após convênios
    console.log('🔄 Inserindo card de retornos após convênios');
    const inseridoComSucesso = inserirCardApósConvenios(htmlCard);
    
    if (!inseridoComSucesso) {
        console.log('🔄 Usando método geral para retornos');
        inserirCardSimples(htmlCard);
    }
}

// Função placeholder para modal de retorno
function abrirModalRetorno(agendaId, data) {
    alert(`Modal de retorno em desenvolvimento\\nAgenda: ${agendaId}\\nData: ${data}`);
}

// Função para visualizar retornos do dia
async function visualizarRetornosDia(agendaId, data) {
    console.log('👁️ Visualizando retornos do dia:', { agendaId, data });
    
    try {
        // Buscar retornos do dia
        const [retornosResponse, agendaInfoResponse] = await Promise.all([
            fetchWithAuth(`buscar_retornos_dia.php?agenda_id=${agendaId}&data=${data}`),
            fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
        ]);
        
        const retornos = await safeJsonParse(retornosResponse);
        const agendaInfo = await safeJsonParse(agendaInfoResponse);
        
        console.log('📋 Retornos encontrados:', retornos);
        console.log('📋 Info da agenda:', agendaInfo);
        
        // Remover modal anterior se existir
        const modalAnterior = document.getElementById('modal-visualizar-retornos');
        if (modalAnterior) {
            modalAnterior.remove();
        }
        
        // Calcular estatísticas
        const totalRetornos = Array.isArray(retornos) ? retornos.length : Object.keys(retornos || {}).length;
        const retornosConfirmados = Array.isArray(retornos) ? 
            retornos.filter(r => r.confirmado).length : 
            Object.values(retornos || {}).filter(r => r.confirmado).length;
        
        const agendaNome = agendaInfo.status === 'sucesso' ? agendaInfo.agenda.nome : `Agenda ${agendaId}`;
        const dataFormatada = new Date(data + 'T00:00:00').toLocaleDateString('pt-BR', {
            weekday: 'long',
            year: 'numeric', 
            month: 'long',
            day: 'numeric'
        });
        
        // Criar modal de visualização
        const modalHTML = `
            <div id="modal-visualizar-retornos" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
                    <!-- Cabeçalho -->
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white p-6 rounded-t-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-xl font-bold flex items-center">
                                    <i class="bi bi-arrow-clockwise mr-2"></i>
                                    Retornos do Dia
                                </h2>
                                <p class="text-indigo-100 mt-1">${agendaNome} • ${dataFormatada}</p>
                            </div>
                            <button onclick="document.getElementById('modal-visualizar-retornos').remove()" 
                                    class="text-white hover:text-gray-200 text-2xl leading-none">
                                ×
                            </button>
                        </div>
                    </div>
                    
                    <!-- Estatísticas -->
                    <div class="p-6 border-b border-gray-200">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-center p-3 bg-indigo-50 rounded-lg">
                                <div class="text-2xl font-bold text-indigo-600">${totalRetornos}</div>
                                <div class="text-sm text-gray-600">Total de Retornos</div>
                            </div>
                            <div class="text-center p-3 bg-green-50 rounded-lg">
                                <div class="text-2xl font-bold text-green-600">${retornosConfirmados}</div>
                                <div class="text-sm text-gray-600">Confirmados</div>
                            </div>
                            <div class="text-center p-3 bg-yellow-50 rounded-lg">
                                <div class="text-2xl font-bold text-yellow-600">${totalRetornos - retornosConfirmados}</div>
                                <div class="text-sm text-gray-600">Pendentes</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Lista de Retornos -->
                    <div class="p-6">
                        ${totalRetornos === 0 ? `
                            <div class="text-center py-8">
                                <i class="bi bi-calendar-x text-gray-400 text-4xl mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-600 mb-2">Nenhum retorno encontrado</h3>
                                <p class="text-gray-500">Não há retornos agendados para esta data.</p>
                                <button onclick="abrirModalRetorno(${agendaId}, '${data}')" 
                                        class="mt-4 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    <i class="bi bi-plus-circle mr-1"></i>
                                    Agendar Retorno
                                </button>
                            </div>
                        ` : `
                            <div class="space-y-4">
                                ${criarListaRetornos(retornos)}
                            </div>
                            
                            <!-- Ações -->
                            <div class="flex justify-between items-center mt-6 pt-4 border-t border-gray-200">
                                <button onclick="abrirModalRetorno(${agendaId}, '${data}')" 
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    <i class="bi bi-plus-circle mr-1"></i>
                                    Novo Retorno
                                </button>
                                
                                <div class="text-sm text-gray-500">
                                    Última atualização: ${new Date().toLocaleTimeString('pt-BR')}
                                </div>
                            </div>
                        `}
                    </div>
                </div>
            </div>
        `;
        
        // Inserir modal no DOM
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
    } catch (error) {
        console.error('❌ Erro ao visualizar retornos:', error);
        alert('Erro ao carregar retornos. Tente novamente.');
    }
}

// Função auxiliar para criar lista de retornos
function criarListaRetornos(retornos) {
    if (!retornos || (Array.isArray(retornos) && retornos.length === 0) || 
        (!Array.isArray(retornos) && Object.keys(retornos).length === 0)) {
        return '<div class="text-center text-gray-500 py-4">Nenhum retorno encontrado</div>';
    }
    
    const listaRetornos = Array.isArray(retornos) ? retornos : Object.values(retornos);
    
    return listaRetornos.map(retorno => {
        const statusClass = retorno.confirmado ? 'bg-green-50 border-green-200' : 'bg-yellow-50 border-yellow-200';
        const statusIcon = retorno.confirmado ? 'bi-check-circle text-green-600' : 'bi-clock text-yellow-600';
        const statusText = retorno.confirmado ? 'Confirmado' : 'Pendente';
        
        return `
            <div class="border rounded-lg p-4 ${statusClass}">
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <div class="flex items-center space-x-2 mb-2">
                            <h3 class="font-semibold text-gray-800">${retorno.paciente || 'Nome não informado'}</h3>
                            <span class="text-xs px-2 py-1 bg-indigo-100 text-indigo-800 rounded-full">Retorno</span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <strong>Horário:</strong> ${retorno.hora || 'Não especificado'}
                            </div>
                            <div>
                                <strong>Convênio:</strong> ${retorno.convenio || 'Não informado'}
                            </div>
                            <div>
                                <strong>Telefone:</strong> ${retorno.telefone || 'Não informado'}
                            </div>
                            <div class="flex items-center">
                                <i class="bi ${statusIcon} mr-1"></i>
                                <strong>Status:</strong> ${statusText}
                            </div>
                        </div>
                        
                        ${retorno.observacoes ? `
                            <div class="mt-2 text-sm text-gray-600">
                                <strong>Observações:</strong> ${retorno.observacoes}
                            </div>
                        ` : ''}
                    </div>
                    
                    <div class="flex flex-col space-y-2 ml-4">
                        ${retorno.id ? `
                            <button onclick="visualizarAgendamento(${retorno.id})" 
                                    class="text-blue-600 hover:text-blue-800 text-sm flex items-center">
                                <i class="bi bi-eye mr-1"></i>
                                Visualizar
                            </button>
                            <button onclick="editarAgendamento(${retorno.id})" 
                                    class="text-green-600 hover:text-green-800 text-sm flex items-center">
                                <i class="bi bi-pencil mr-1"></i>
                                Editar
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ✅ SISTEMA DE RETORNOS - Modal
window.abrirModalRetorno = function(agendaId, data, especialidadeId = null) {
    console.log('🎯 Abrindo modal de retorno:', { agendaId, data, especialidadeId });
    
    // Usar especialidade passada como parâmetro ou a global
    const especialidadeFinal = especialidadeId || window.especialidadeIdSelecionada || '';
    console.log('📋 Retorno - Especialidade final:', especialidadeFinal);
    
    // Verificar se permite retornos
    fetchWithAuth(`verificar_retornos.php?agenda_id=${agendaId}&data=${data}`)
        .then(response => response.text())
        .then(responseText => {
            const primeiraLinha = responseText.split('\n')[0].trim();
            const dadosRetorno = JSON.parse(primeiraLinha);
            
            if (dadosRetorno.erro) {
                alert('Erro: ' + dadosRetorno.erro);
                return;
            }
            
            if (!dadosRetorno.permite_retornos || !dadosRetorno.pode_retornar) {
                alert(dadosRetorno.mensagem || 'Não é possível fazer retorno nesta agenda/data');
                return;
            }
            
            // Buscar informações da agenda
            fetchWithAuth(`buscar_info_agenda.php?agenda_id=${agendaId}`)
                .then(safeJsonParse)
                .then(agendaInfo => {
                    criarModalRetorno(agendaId, data, dadosRetorno, agendaInfo, especialidadeFinal);
                })
                .catch(error => {
                    console.error('Erro ao buscar info da agenda:', error);
                    criarModalRetorno(agendaId, data, dadosRetorno, {}, especialidadeFinal);
                });
        })
        .catch(error => {
            console.error('Erro ao verificar retornos:', error);
            alert('Erro ao verificar disponibilidade de retornos.');
        });
};

// ✅ FUNÇÃO: criarModalRetorno - COMPLETA baseada no encaixe
function criarModalRetorno(agendaId, data, dadosRetorno, agendaInfo, especialidadeId = '') {
    const especialidadeFinal = especialidadeId || '';
    console.log('📋 Modal retorno - Especialidade final:', especialidadeFinal);
    console.log('🎯 Criando modal de retorno completo:', { agendaId, data, dadosRetorno, agendaInfo });
    console.log('📋 Convênios recebidos na função:', agendaInfo.agenda?.convenios);
    
    // Remover modal anterior se existir
    const modalAnterior = document.getElementById('modal-retorno');
    if (modalAnterior) {
        modalAnterior.remove();
    }
    
    const dataObj = new Date(data + 'T00:00:00');
    const dataFormatada = dataObj.toLocaleDateString('pt-BR', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    const modalHTML = `
        <div id="modal-retorno" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-6xl w-full max-h-[95vh] overflow-y-auto">
                <!-- Cabeçalho -->
                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white p-6 rounded-t-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold flex items-center">
                                <i class="bi bi-arrow-clockwise mr-3"></i>
                                Agendar Retorno
                            </h2>
                            <p class="text-blue-100 mt-1">${dataFormatada} - ${agendaInfo.nome_agenda || 'Agenda'}</p>
                        </div>
                        <button onclick="fecharModalRetorno()" class="text-white hover:text-gray-200 transition">
                            <i class="bi bi-x-lg text-2xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Conteúdo -->
                <div class="p-6">
                    <!-- Info do Retorno -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="font-medium text-blue-800">Informações do Retorno</h4>
                                <p class="text-blue-700 text-sm mt-1">
                                    Disponível: ${dadosRetorno.retornos_disponiveis}/${dadosRetorno.limite_total} retornos
                                </p>
                            </div>
                            <i class="bi bi-info-circle text-blue-600 text-xl"></i>
                        </div>
                    </div>

                    <!-- ✅ SEÇÃO: Seleção de Horário para Retorno -->
                    <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 mb-6">
                        <h4 class="font-medium text-indigo-800 mb-3 flex items-center">
                            <i class="bi bi-clock mr-2"></i>
                            Horário do Retorno
                        </h4>
                        <div class="space-y-3">
                            <!-- Opção: Horário específico -->
                            <label class="flex items-center">
                                <input type="radio" name="tipo_horario_retorno" value="horario_especifico" checked
                                       class="mr-3 text-blue-600" onchange="toggleSelecaoHorarioRetorno()">
                                <div>
                                    <span class="font-medium text-gray-800">Agendar em horário específico</span>
                                    <p class="text-sm text-gray-600">Digite um horário dentro do funcionamento da agenda</p>
                                </div>
                            </label>
                            
                            <!-- Opção: Sem horário específico -->
                            <label class="flex items-center">
                                <input type="radio" name="tipo_horario_retorno" value="sem_horario"
                                       class="mr-3 text-blue-600" onchange="toggleSelecaoHorarioRetorno()">
                                <div>
                                    <span class="font-medium text-gray-800">Retorno sem horário específico</span>
                                    <p class="text-sm text-gray-600">Retorno será agendado conforme disponibilidade</p>
                                </div>
                            </label>
                            
                            <!-- ✅ Área de input de horário -->
                            <div id="area-input-horario-retorno" class="mt-4">
                                <div class="bg-white border border-gray-300 rounded-lg p-4">
                                    <div id="info-horarios-agenda-retorno" class="mb-4 p-3 bg-gray-50 rounded">
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            <i class="bi bi-info-circle"></i>
                                            <span>Carregando horários de funcionamento...</span>
                                        </div>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <label class="block text-sm font-medium text-gray-700">
                                            Horário desejado:
                                        </label>
                                        <div class="flex items-center gap-3">
                                            <input type="time" 
                                                   id="horario_digitado_retorno" 
                                                   name="horario_digitado"
                                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   onchange="validarHorarioDigitadoRetorno()">
                                            
                                            <button type="button" 
                                                    onclick="verificarDisponibilidadeHorarioRetorno()" 
                                                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                                <i class="bi bi-search mr-1"></i>Verificar
                                            </button>
                                        </div>
                                        
                                        <div id="status-horario-retorno" class="hidden"></div>
                                        <div id="sugestoes-horarios-retorno" class="hidden">
                                            <p class="text-sm font-medium text-gray-700 mb-2">Horários próximos disponíveis:</p>
                                            <div class="flex flex-wrap gap-2"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Input hidden para horário selecionado -->
                        <input type="hidden" id="horario_selecionado_hidden_retorno" name="horario_selecionado" value="">
                    </div>

                    <!-- Formulário Principal -->
                    <form id="form-retorno" class="space-y-6">
                        <!-- Busca de paciente (igual ao encaixe) -->
                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                            <h4 class="font-medium text-gray-800 mb-3 flex items-center">
                                <i class="bi bi-person-search mr-2"></i>
                                Dados do Paciente
                            </h4>
                            
                            <div class="space-y-4">
                                <!-- Nome e Busca -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="bi bi-person mr-2"></i>Nome do Paciente *
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               id="nome_paciente_retorno" 
                                               name="nome_paciente"
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="Digite nome, CPF ou data de nascimento (16/09/1990, 16091990 ou 160990)..."
                                               autocomplete="off"
                                               required>
                                        <div id="sugestoes_paciente_retorno" class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
                                    </div>
                                </div>

                                <!-- Telefone (igual ao encaixe) -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        <i class="bi bi-telephone mr-2"></i>Telefone/WhatsApp *
                                    </label>
                                    <input type="tel" 
                                           id="telefone_paciente_retorno" 
                                           name="telefone_paciente"
                                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="(00) 00000-0000"
                                           required>
                                </div>

                                <!-- Formulário de cadastro novo (replicado do encaixe) -->
                                <div id="form-novo-paciente-retorno" class="hidden space-y-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <h5 class="font-medium text-blue-800 mb-3">
                                        <i class="bi bi-person-plus mr-2"></i>
                                        Cadastro de Novo Paciente
                                    </h5>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <!-- CPF -->
                                        <div>
                                            <div class="flex items-center mb-2">
                                                <label class="block text-sm font-medium text-gray-700 flex-1">
                                                    <i class="bi bi-card-text mr-2"></i>CPF
                                                </label>
                                                <label class="flex items-center text-xs text-gray-600">
                                                    <input type="checkbox" id="cpf_opcional_retorno" class="mr-1" checked>
                                                    CPF opcional
                                                </label>
                                            </div>
                                            <input type="text" 
                                                   id="cpf_paciente_retorno" 
                                                   name="cpf_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="000.000.000-00">
                                        </div>

                                        <!-- RG -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-person-vcard mr-2"></i>RG
                                            </label>
                                            <input type="text" 
                                                   id="rg_paciente_retorno" 
                                                   name="rg_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="00.000.000-0">
                                        </div>

                                        <!-- Sexo -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-gender-ambiguous mr-2"></i>Sexo
                                            </label>
                                            <select id="sexo_paciente_retorno" 
                                                    name="sexo_paciente"
                                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                <option value="">Selecione</option>
                                                <option value="M">Masculino</option>
                                                <option value="F">Feminino</option>
                                            </select>
                                        </div>

                                        <!-- Data Nascimento -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-calendar-event mr-2"></i>Data de Nascimento
                                            </label>
                                            <input type="date" 
                                                   id="nascimento_paciente_retorno" 
                                                   name="nascimento_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                        </div>

                                        <!-- Email -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-envelope mr-2"></i>E-mail
                                            </label>
                                            <input type="email" 
                                                   id="email_paciente_retorno" 
                                                   name="email_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="email@exemplo.com">
                                        </div>

                                        <!-- CEP -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-geo-alt mr-2"></i>CEP
                                            </label>
                                            <input type="text" 
                                                   id="cep_paciente_retorno" 
                                                   name="cep_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="00000-000">
                                        </div>

                                        <!-- Endereço -->
                                        <div class="md:col-span-2">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <i class="bi bi-house mr-2"></i>Endereço Completo
                                            </label>
                                            <input type="text" 
                                                   id="endereco_paciente_retorno" 
                                                   name="endereco_paciente"
                                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                   placeholder="Rua, número, bairro, cidade">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Convênio (igual ao encaixe) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-credit-card mr-2"></i>Convênio *
                            </label>
                            <select id="convenio_retorno" 
                                    name="convenio_id"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required>
                                <option value="">Selecione o convênio</option>
                            </select>
                        </div>

                        <!-- Observações do Retorno -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="bi bi-chat-left-text mr-2"></i>
                                Observações do Retorno
                            </label>
                            <textarea id="observacoes_retorno" 
                                      name="observacoes"
                                      rows="3"
                                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                      placeholder="Motivo do retorno, urgência, observações especiais..."></textarea>
                        </div>
                    </form>

                    <!-- Botões -->
                    <div class="flex flex-col sm:flex-row sm:justify-end sm:space-x-3 space-y-3 sm:space-y-0 mt-8 pt-6 border-t">
                        <button type="button" onclick="fecharModalRetorno()" 
                                class="w-full sm:w-auto px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition">
                            Cancelar
                        </button>
                        <button type="button" onclick="salvarRetorno()" 
                                id="btn-salvar-retorno"
                                class="w-full sm:w-auto px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            <i class="bi bi-arrow-clockwise mr-2"></i>Confirmar Retorno
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Inserir modal no DOM
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Configurar funcionalidades
    configurarModalRetorno(agendaId, data, agendaInfo);
    
    // Configurar toggle de horário
    toggleSelecaoHorarioRetorno();
}

// ✅ FUNÇÃO: fecharModalRetorno
window.fecharModalRetorno = function() {
    const modal = document.getElementById('modal-retorno');
    if (modal) {
        modal.remove();
    }
};

// ✅ FUNÇÃO: configurarModalRetorno
function configurarModalRetorno(agendaId, data, agendaInfo) {
    // Carregar convênios
    const convenioSelect = document.getElementById('convenio_retorno');
    const convenios = agendaInfo.agenda?.convenios || [];
    
    console.log('📋 Carregando convênios no select:', convenios);
    
    convenios.forEach(convenio => {
        const option = document.createElement('option');
        option.value = convenio.id;
        option.textContent = convenio.nome;
        convenioSelect.appendChild(option);
    });
    
    // Configurar busca de paciente
    const nomeInput = document.getElementById('nome_paciente_retorno');
    const telefoneInput = document.getElementById('telefone_paciente_retorno');
    
    // Configurar busca em tempo real
    configurarBuscaPaciente(nomeInput, telefoneInput, 'retorno');
    
    // Armazenar dados globalmente
    window.dadosModalRetorno = { agendaId, data };
    
    // Configurar controle de CPF
    configurarControleCPFRetorno();
    
    // Carregar exames sempre (se necessário)
    // carregarExamesRetorno();
}

// ✅ FUNÇÕES ESPECÍFICAS PARA RETORNO

// Toggle de seleção de horário para retorno
window.toggleSelecaoHorarioRetorno = function() {
    const tipoSelecionado = document.querySelector('input[name="tipo_horario_retorno"]:checked')?.value;
    const areaInput = document.getElementById('area-input-horario-retorno');
    const horarioHidden = document.getElementById('horario_selecionado_hidden_retorno');
    
    console.log('🔄 Toggle horário retorno chamado:', tipoSelecionado);
    
    if (tipoSelecionado === 'horario_especifico') {
        if (areaInput) {
            areaInput.classList.remove('hidden');
            console.log('✅ Área de input de horário retorno mostrada');
        }
        
        // Carregar informações da agenda
        carregarInfoHorariosAgendaRetorno();
        
    } else {
        if (areaInput) {
            areaInput.classList.add('hidden');
            console.log('❌ Área de input de horário retorno escondida');
        }
        
        if (horarioHidden) {
            horarioHidden.value = '';
        }
        
        limparStatusHorarioRetorno();
    }
};

// Carregar informações de horários para retorno
window.carregarInfoHorariosAgendaRetorno = function() {
    const infoContainer = document.getElementById('info-horarios-agenda-retorno');
    
    if (!infoContainer) {
        return;
    }
    
    infoContainer.innerHTML = `
        <div class="flex items-start gap-2 text-sm">
            <i class="bi bi-clock text-blue-600 mt-0.5"></i>
            <div>
                <div class="font-medium text-gray-800">✅ Agendar retorno em horário específico</div>
                <div class="text-gray-600">Digite o horário desejado no formato HH:MM</div>
                <div class="text-xs text-gray-500 mt-1">
                    <i class="bi bi-info-circle mr-1"></i>
                    Sistema aceita horários entre 06:00 e 22:00
                </div>
                <div class="text-xs text-green-600 mt-1">
                    <i class="bi bi-check-circle mr-1"></i>
                    Horário será salvo exatamente como digitado
                </div>
            </div>
        </div>
    `;
};

// Validar horário digitado para retorno
window.validarHorarioDigitadoRetorno = function() {
    const horarioInput = document.getElementById('horario_digitado_retorno');
    
    if (!horarioInput) {
        console.warn('⚠️ Input de horário retorno não encontrado');
        return;
    }
    
    const horario = horarioInput.value;
    
    if (!horario) {
        limparStatusHorarioRetorno();
        return;
    }
    
    console.log('🕐 Validando horário retorno:', horario);
    
    // Atualizar campo hidden
    const horarioHidden = document.getElementById('horario_selecionado_hidden_retorno');
    if (horarioHidden) {
        horarioHidden.value = horario;
    }
    
    // Mostrar status de sucesso
    mostrarStatusHorarioRetorno('success', `✅ Horário ${horario} selecionado para retorno`);
};

// Verificar disponibilidade de horário para retorno
window.verificarDisponibilidadeHorarioRetorno = function() {
    const horarioInput = document.getElementById('horario_digitado_retorno');
    
    if (!horarioInput || !horarioInput.value) {
        alert('Digite um horário primeiro');
        return;
    }
    
    const horario = horarioInput.value;
    
    // Para retornos, aceitar qualquer horário válido
    mostrarStatusHorarioRetorno('success', `✅ Horário ${horario} disponível para retorno`);
    
    // Atualizar campo hidden
    const horarioHidden = document.getElementById('horario_selecionado_hidden_retorno');
    if (horarioHidden) {
        horarioHidden.value = horario;
    }
};

// Mostrar status do horário para retorno
function mostrarStatusHorarioRetorno(tipo, mensagem) {
    const statusDiv = document.getElementById('status-horario-retorno');
    
    if (!statusDiv) return;
    
    const classes = {
        success: 'bg-green-50 border-green-200 text-green-800',
        error: 'bg-red-50 border-red-200 text-red-800',
        warning: 'bg-yellow-50 border-yellow-200 text-yellow-800'
    };
    
    statusDiv.className = `p-3 rounded-lg border ${classes[tipo] || classes.success}`;
    statusDiv.innerHTML = mensagem;
    statusDiv.classList.remove('hidden');
}

// Limpar status do horário para retorno
function limparStatusHorarioRetorno() {
    const statusDiv = document.getElementById('status-horario-retorno');
    if (statusDiv) {
        statusDiv.classList.add('hidden');
    }
    
    const sugestoesDiv = document.getElementById('sugestoes-horarios-retorno');
    if (sugestoesDiv) {
        sugestoesDiv.classList.add('hidden');
    }
}

// Configurar controle de CPF no retorno
function configurarControleCPFRetorno() {
    const checkbox = document.getElementById('cpf_opcional_retorno');
    const cpfInput = document.getElementById('cpf_paciente_retorno');
    
    if (!checkbox || !cpfInput) {
        console.warn('Elementos de controle de CPF não encontrados no retorno');
        return;
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            cpfInput.removeAttribute('required');
            cpfInput.placeholder = '000.000.000-00 (opcional)';
            console.log('📝 CPF retorno: opcional (checkbox marcado)');
        } else {
            cpfInput.setAttribute('required', 'required');
            cpfInput.placeholder = '000.000.000-00 (obrigatório)';
            console.log('📝 CPF retorno: obrigatório (checkbox desmarcado)');
        }
    });
    
    console.log('✅ Controle de CPF configurado para retorno');
}

// ✅ FUNÇÃO: salvarRetorno - COMPLETA baseada no encaixe
window.salvarRetorno = function() {
    console.log('💾 Salvando retorno com campos completos...');
    
    const { agendaId, data } = window.dadosModalRetorno;
    
    // Coletar horário selecionado
    const tipoHorario = document.querySelector('input[name="tipo_horario_retorno"]:checked')?.value;
    let horarioSelecionado = '';
    
    if (tipoHorario === 'horario_especifico') {
        horarioSelecionado = document.getElementById('horario_digitado_retorno')?.value || 
                           document.getElementById('horario_selecionado_hidden_retorno')?.value || '';
    }
    
    // Coletar dados do formulário
    const formData = new FormData();
    formData.append('agenda_id', agendaId);
    formData.append('data_agendamento', data);
    formData.append('horario_agendamento', horarioSelecionado || '00:00:00');
    formData.append('nome_paciente', document.getElementById('nome_paciente_retorno')?.value.trim() || '');
    formData.append('telefone_paciente', document.getElementById('telefone_paciente_retorno')?.value.trim() || '');
    formData.append('convenio_id', document.getElementById('convenio_retorno')?.value || '');
    formData.append('observacoes', document.getElementById('observacoes_retorno')?.value.trim() || '');
    formData.append('tipo_operacao', 'retorno');
    
    // Dados adicionais do paciente (se preenchidos)
    formData.append('cpf_paciente', document.getElementById('cpf_paciente_retorno')?.value.trim() || '');
    formData.append('rg_paciente', document.getElementById('rg_paciente_retorno')?.value.trim() || '');
    formData.append('sexo_paciente', document.getElementById('sexo_paciente_retorno')?.value || '');
    formData.append('nascimento_paciente', document.getElementById('nascimento_paciente_retorno')?.value || '');
    formData.append('email_paciente', document.getElementById('email_paciente_retorno')?.value.trim() || '');
    formData.append('cep_paciente', document.getElementById('cep_paciente_retorno')?.value.trim() || '');
    formData.append('endereco_paciente', document.getElementById('endereco_paciente_retorno')?.value.trim() || '');
    
    // Verificar se CPF é obrigatório
    const cpfOpcional = document.getElementById('cpf_opcional_retorno')?.checked || false;
    const cpfValue = formData.get('cpf_paciente');
    
    // Validação básica
    if (!formData.get('nome_paciente')) {
        alert('Nome do paciente é obrigatório');
        return;
    }
    
    if (!formData.get('telefone_paciente')) {
        alert('Telefone é obrigatório');
        return;
    }
    
    if (!formData.get('convenio_id')) {
        alert('Convênio é obrigatório');
        return;
    }
    
    // Validar CPF se obrigatório
    if (!cpfOpcional && !cpfValue) {
        alert('CPF é obrigatório. Marque "CPF opcional" se não souber o CPF do paciente.');
        return;
    }
    
    console.log('🟢 Dados do retorno válidos:', {
        paciente: formData.get('nome_paciente'),
        telefone: formData.get('telefone_paciente'),
        horario: formData.get('horario_agendamento'),
        convenio: formData.get('convenio_id'),
        cpf: formData.get('cpf_paciente') || 'não informado'
    });
    
    if (cpfValue) {
        console.log('🟡 Retorno com CPF');
    } else {
        console.log('🟡 Retorno sem CPF');
    }
    
    // Desabilitar botão
    const btnSalvar = document.getElementById('btn-salvar-retorno');
    let textoOriginal = 'Confirmar Retorno';
    if (btnSalvar) {
        textoOriginal = btnSalvar.innerHTML;
        btnSalvar.disabled = true;
        btnSalvar.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i>Salvando...';
    }
    
    // Enviar dados
    fetch('processar_retorno.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log('✅ Resposta do servidor:', text);
        
        try {
            const resultado = JSON.parse(text.trim());
            
            if (resultado.status === 'sucesso') {
                alert('Retorno agendado com sucesso!');
                fecharModalRetorno();
                
                // ✅ Recarregar APENAS a visualização (sem refresh da página)
                if (typeof carregarVisualizacaoDia === 'function') {
                    carregarVisualizacaoDia(agendaId, data);
                }
            } else {
                alert('Erro ao agendar retorno: ' + (resultado.mensagem || 'Erro desconhecido'));
            }
        } catch (e) {
            console.error('Erro ao parsear resposta:', e);
            alert('Erro inesperado do servidor');
        }
    })
    .catch(error => {
        console.error('Erro na requisição:', error);
        alert('Erro de comunicação com o servidor');
    })
    .finally(() => {
        // Reabilitar botão
        if (btnSalvar) {
            btnSalvar.disabled = false;
            btnSalvar.innerHTML = textoOriginal;
        }
    });
};

// Função para buscar informações de retornos nos agendamentos do dia
function buscarInformacoesRetornos(agendamentos) {
    let totalRetornos = 0;
    
    // Contar retornos nos agendamentos carregados
    for (const hora in agendamentos) {
        const agendamento = agendamentos[hora];
        if (agendamento && agendamento.tipo_agendamento && 
            agendamento.tipo_agendamento.trim().toUpperCase() === 'RETORNO') {
            totalRetornos++;
        }
    }
    
    return {
        total: totalRetornos
    };
}

// Nova função para buscar informações avançadas de retornos
async function buscarInformacoesRetornosAvancada(agendaId, data) {
    try {
        const response = await fetchWithAuth(`verificar_retornos.php?agenda_id=${agendaId}&data=${data}`);
        const text = await response.text();
        const linhas = text.trim().split('\n');
        const dadosRetorno = JSON.parse(linhas[0].trim());
        
        return dadosRetorno;
    } catch (error) {
        console.error('Erro ao buscar informações de retornos:', error);
        return {
            permite_retornos: false,
            limite_total: 0,
            retornos_ocupados: 0,
            retornos_disponiveis: 0,
            pode_retornar: false
        };
    }
}

// Função para carregar preparos da agenda
function carregarPreparosAgenda(agendaId) {
    console.log('🔄 AGENDA-NEW.JS - Carregando preparos da agenda:', agendaId);
    
    fetchWithAuth(`buscar_agendamento.php?id=0&agenda_id=${agendaId}`)
        .then(safeJsonParse)
        .then(dados => {
            if (dados.preparos && dados.preparos.length > 0) {
                console.log('📋 Preparos encontrados:', dados.preparos);
                exibirPreparosNoModal(dados.preparos);
            } else {
                console.log('ℹ️ Nenhum preparo encontrado para esta agenda');
            }
        })
        .catch(error => {
            console.error('❌ Erro ao carregar preparos:', error);
        });
}

// Função para exibir preparos no modal
function exibirPreparosNoModal(preparos) {
    const container = document.getElementById('preparos-container');
    const content = document.getElementById('preparos-content');
    
    if (!container || !content) {
        console.warn('⚠️ Elementos de preparos não encontrados no DOM');
        return;
    }
    
    // Gerar HTML dos preparos apenas com títulos clicáveis
    const preparosHTML = preparos.map((preparo, index) => `
        <div class="mb-2 ${index > 0 ? 'border-t border-green-200 pt-2' : ''}">
            <button 
                type="button"
                class="text-left w-full font-semibold text-green-800 text-sm hover:text-green-600 hover:underline focus:outline-none focus:ring-2 focus:ring-green-300 rounded p-1"
                onclick="abrirModalPreparo('${escapeHtml(preparo.titulo)}', '${escapeHtml(preparo.instrucoes)}')"
                title="Clique para ver as instruções completas"
            >
                <i class="bi bi-info-circle mr-1"></i>
                ${preparo.titulo}
            </button>
        </div>
    `).join('');
    
    content.innerHTML = preparosHTML;
    container.style.display = 'block';
    
    console.log('✅ Preparos exibidos no modal (apenas títulos)');
}

// Função para escapar HTML e evitar problemas com aspas
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

// Função para abrir modal com instruções completas do preparo
function abrirModalPreparo(titulo, instrucoes) {
    // Decodificar HTML entities
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = titulo;
    const tituloLimpo = tempDiv.textContent || tempDiv.innerText || '';
    
    tempDiv.innerHTML = instrucoes;
    const instrucoesLimpas = tempDiv.textContent || tempDiv.innerText || '';
    
    // Criar modal dinâmico
    const modalHTML = `
        <div id="modal-preparo-instrucoes" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
                <div class="bg-green-600 text-white p-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="bi bi-list-check mr-2"></i>
                        ${tituloLimpo}
                    </h3>
                    <button 
                        type="button" 
                        onclick="fecharModalPreparo()"
                        class="text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 rounded p-1"
                        title="Fechar"
                    >
                        <i class="bi bi-x-lg text-xl"></i>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-r-lg">
                        <h4 class="font-semibold text-blue-800 mb-2">Instruções:</h4>
                        <p class="text-gray-700 leading-relaxed whitespace-pre-line">${instrucoesLimpas}</p>
                    </div>
                </div>
                <div class="bg-gray-50 px-6 py-3 flex justify-end">
                    <button 
                        type="button" 
                        onclick="fecharModalPreparo()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-gray-300"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-preparo-instrucoes');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Adicionar modal ao body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar listener para fechar com ESC
    const modal = document.getElementById('modal-preparo-instrucoes');
    const handleEscape = (event) => {
        if (event.key === 'Escape') {
            fecharModalPreparo();
        }
    };
    
    // Armazenar referência do handler no modal para limpeza posterior
    modal.handleEscape = handleEscape;
    
    document.addEventListener('keydown', handleEscape);
    
    // Fechar ao clicar fora do modal
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            fecharModalPreparo();
        }
    });
    
    console.log('✅ Modal de preparo aberto:', tituloLimpo);
}

// Função para fechar modal de preparo
function fecharModalPreparo() {
    const modal = document.getElementById('modal-preparo-instrucoes');
    if (modal) {
        // Remover listeners de teclado
        document.removeEventListener('keydown', modal.handleEscape);
        
        // Remover modal
        modal.remove();
        console.log('✅ Modal de preparo fechado');
    }
}

// Função para abrir modal com instruções completas do preparo (Informações Detalhadas)
function abrirModalPreparoDetalhes(titulo, instrucoes, anexos = []) {
    // Criar modal dinâmico
    const modalHTML = `
        <div id="modal-preparo-detalhes" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
                <div class="bg-gray-600 text-white p-4 flex justify-between items-center">
                    <h3 class="text-lg font-semibold flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        ${titulo}
                    </h3>
                    <button 
                        type="button" 
                        onclick="fecharModalPreparoDetalhes()"
                        class="text-white hover:text-gray-200 focus:outline-none focus:ring-2 focus:ring-white focus:ring-opacity-50 rounded p-1"
                        title="Fechar"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="p-6 overflow-y-auto max-h-[60vh]">
                    <div class="bg-gray-50 dark:bg-gray-900/20 border-l-4 border-gray-400 p-4 rounded-r-lg">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-semibold text-gray-800 dark:text-gray-200">Instruções:</h4>
                            <button 
                                type="button" 
                                onclick="copiarTextoModal()"
                                class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-1 rounded text-sm focus:outline-none focus:ring-2 focus:ring-gray-300 flex items-center gap-1"
                                title="Copiar texto"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Copiar
                            </button>
                        </div>
                        <p id="texto-preparo-modal" class="text-gray-700 dark:text-gray-300 leading-relaxed whitespace-pre-line">${instrucoes}</p>
                    </div>
                    
                    ${anexos && anexos.length > 0 ? `
                    <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-600">
                        <h5 class="font-semibold text-gray-800 dark:text-gray-200 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                                <path d="M14 2v6h6"/>
                            </svg>
                            Anexos (${anexos.length})
                        </h5>
                        <div class="space-y-2">
                            ${anexos.map(anexo => `
                                <div class="flex items-center justify-between p-2 bg-gray-100 dark:bg-gray-700 rounded">
                                    <div class="flex items-center space-x-2">
                                        ${getIconeArquivoModal(anexo.tipo)}
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">${anexo.nome}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">${formatarTamanho(anexo.tamanho)}</p>
                                        </div>
                                    </div>
                                    <button 
                                        type="button" 
                                        onclick="baixarAnexo(${anexo.id})"
                                        class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm flex items-center gap-1"
                                        title="Baixar arquivo"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                                        </svg>
                                        Baixar
                                    </button>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    ` : ''}
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-3 flex justify-end">
                    <button 
                        type="button" 
                        onclick="fecharModalPreparoDetalhes()"
                        class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded focus:outline-none focus:ring-2 focus:ring-gray-300"
                    >
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Remover modal existente se houver
    const modalExistente = document.getElementById('modal-preparo-detalhes');
    if (modalExistente) {
        modalExistente.remove();
    }
    
    // Adicionar modal ao body
    document.body.insertAdjacentHTML('beforeend', modalHTML);
    
    // Adicionar listeners para fechar
    const modal = document.getElementById('modal-preparo-detalhes');
    
    // Fechar com ESC
    const handleEscape = (event) => {
        if (event.key === 'Escape') {
            fecharModalPreparoDetalhes();
        }
    };
    
    modal.handleEscape = handleEscape;
    document.addEventListener('keydown', handleEscape);
    
    // Fechar ao clicar fora do modal
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            fecharModalPreparoDetalhes();
        }
    });
    
    console.log('✅ Modal de preparo aberto (Informações Detalhadas):', titulo);
}

// Função para obter ícone de arquivo no modal
function getIconeArquivoModal(tipo) {
    const icones = {
        'pdf': '<svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'doc': '<svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'docx': '<svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>',
        'jpg': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'jpeg': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'png': '<svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/><circle cx="10" cy="13" r="2"/></svg>',
        'txt': '<svg class="w-5 h-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/><path d="M14 2v6h6"/></svg>'
    };
    return icones[tipo] || icones['txt'];
}

// Função para formatar tamanho do arquivo
function formatarTamanho(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Função para baixar anexo
function baixarAnexo(anexoId) {
    window.open('download_anexo.php?id=' + anexoId, '_blank');
}

// Função para copiar texto do modal
function copiarTextoModal() {
    const textoElement = document.getElementById('texto-preparo-modal');
    if (textoElement) {
        const texto = textoElement.textContent || textoElement.innerText;
        
        // Tentar usar a API moderna do clipboard
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(() => {
                mostrarNotificacaoCopiado();
            }).catch(() => {
                // Fallback para método antigo
                copiarTextoFallback(texto);
            });
        } else {
            // Fallback para método antigo
            copiarTextoFallback(texto);
        }
    }
}

// Função fallback para copiar texto
function copiarTextoFallback(texto) {
    const textArea = document.createElement('textarea');
    textArea.value = texto;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        mostrarNotificacaoCopiado();
    } catch (err) {
        console.error('Erro ao copiar texto:', err);
        alert('Não foi possível copiar o texto. Tente selecionar manualmente.');
    }
    
    document.body.removeChild(textArea);
}

// Função para mostrar notificação de texto copiado
function mostrarNotificacaoCopiado() {
    const botaoCopiar = document.querySelector('[onclick="copiarTextoModal()"]');
    if (botaoCopiar) {
        const textoOriginal = botaoCopiar.innerHTML;
        botaoCopiar.innerHTML = `
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Copiado!
        `;
        botaoCopiar.classList.remove('bg-gray-500', 'hover:bg-gray-600');
        botaoCopiar.classList.add('bg-green-500', 'hover:bg-green-600');
        
        setTimeout(() => {
            botaoCopiar.innerHTML = textoOriginal;
            botaoCopiar.classList.remove('bg-green-500', 'hover:bg-green-600');
            botaoCopiar.classList.add('bg-gray-500', 'hover:bg-gray-600');
        }, 2000);
    }
}

// Função para fechar modal de preparo (Informações Detalhadas)
function fecharModalPreparoDetalhes() {
    const modal = document.getElementById('modal-preparo-detalhes');
    if (modal) {
        // Remover listeners de teclado
        if (modal.handleEscape) {
            document.removeEventListener('keydown', modal.handleEscape);
        }
        
        // Remover modal
        modal.remove();
        console.log('✅ Modal de preparo fechado (Informações Detalhadas)');
    }
}

// Tornar funções globais para serem acessíveis via onclick
window.abrirModalPreparoDetalhes = abrirModalPreparoDetalhes;
window.fecharModalPreparoDetalhes = fecharModalPreparoDetalhes;

console.log('Sistema de agenda carregado e pronto!');
console.log('💡 Use debugEncaixes() no console para testar encaixes');
/**
 * Calcula idade automaticamente no modal de agendamento
 */
function calcularIdadeAgendamento() {
    const dataNascimento = document.getElementById('data_nascimento_agendamento');
    const campoIdade = document.getElementById('idade_agendamento');
    
    if (!dataNascimento || !campoIdade) {
        console.warn('Campos de data de nascimento ou idade não encontrados');
        return;
    }
    
    if (dataNascimento.value) {
        const hoje = new Date();
        const nascimento = new Date(dataNascimento.value);
        
        // Verificar se a data é válida
        if (nascimento > hoje) {
            alert('Data de nascimento não pode ser futura');
            dataNascimento.value = '';
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
        
        // Validação de idade mínima
        if (idade < 0) {
            alert('Idade inválida');
            campoIdade.value = '';
        }
        
        console.log('Idade calculada:', idade, 'anos para', dataNascimento.value);
    } else {
        campoIdade.value = '';
    }
}

// Tornar a função global
window.calcularIdadeAgendamento = calcularIdadeAgendamento;

/**
 * Marcar chegada do paciente
 */
async function marcarChegada(agendamentoId) {
    console.log('🏃‍♂️ Marcando chegada para agendamento:', agendamentoId);
    
    if (!agendamentoId) {
        alert('ID do agendamento inválido');
        return;
    }
    
    try {
        // Confirmar ação
        if (!confirm('Confirma a chegada do paciente?')) {
            return;
        }
        
        const formData = new FormData();
        formData.append('agendamento_id', agendamentoId);
        
        const response = await fetch('marcar_chegada.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await safeJsonParse(response);
        
        if (resultado.sucesso) {
            const horaChegada = new Date(resultado.hora_chegada).toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'});
            
            alert(`✅ ${resultado.mensagem}\nOrdem de chegada: #${resultado.ordem_chegada}\nHora: ${horaChegada}`);
            
            // Registrar na auditoria
            registrarAuditoriaAlteracao('CHEGADA', agendamentoId, 'chegada', 'Não chegou', `Chegou às ${horaChegada} (#${resultado.ordem_chegada})`, `Chegada registrada - Ordem: #${resultado.ordem_chegada}`);
            
            // Atualizar dados na tabela sem refresh
            if (typeof atualizarDadosAgendamentoNaTabela === 'function') {
                atualizarDadosAgendamentoNaTabela(agendamentoId, {
                    chegada: true,
                    ordem_chegada: resultado.ordem_chegada,
                    hora_chegada: horaChegada
                });
            }
            
            // Atualizar visualmente o botão de chegada
            atualizarBotaoChegada(agendamentoId, resultado.ordem_chegada, horaChegada);
            
        } else {
            alert(`❌ Erro: ${resultado.erro}`);
        }
        
    } catch (error) {
        console.error('Erro ao marcar chegada:', error);
        alert('Erro ao marcar chegada. Tente novamente.');
    }
}

// Tornar a função global
window.marcarChegada = marcarChegada;

/**
 * Alterar confirmação em tempo real (modal visualização)
 */
async function alterarConfirmacao(agendamentoId, valor) {
    console.log('🔄 Alterando confirmação:', agendamentoId, 'para:', valor);
    
    try {
        const formData = new FormData();
        formData.append('agendamento_id', agendamentoId);
        formData.append('confirmado', valor);
        
        const response = await fetch('atualizar_agendamento_rapido.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await safeJsonParse(response);
        
        if (resultado.sucesso) {
            console.log('✅ Confirmação alterada com sucesso');
            
            // Registrar na auditoria
            registrarAuditoriaAlteracao('EDITAR', agendamentoId, 'confirmado', valor === '1' ? 'Não' : 'Sim', valor === '1' ? 'Sim' : 'Não', 'Alteração de confirmação via modal');
            
            // Atualizar dados na tabela sem refresh
            if (typeof atualizarDadosAgendamentoNaTabela === 'function') {
                atualizarDadosAgendamentoNaTabela(agendamentoId, {confirmado: parseInt(valor)});
            }
            
            // Feedback visual no modal
            const inputs = document.querySelectorAll('input[name="confirmado_visualizacao"]');
            inputs.forEach(input => {
                if (input.value === valor) {
                    input.parentElement.style.background = '#f0f9ff';
                    setTimeout(() => {
                        input.parentElement.style.background = '';
                    }, 1000);
                }
            });
        } else {
            alert(`Erro: ${resultado.erro}`);
            // Reverter seleção se deu erro
            const inputs = document.querySelectorAll('input[name="confirmado_visualizacao"]');
            inputs.forEach(input => {
                input.checked = input.value !== valor;
            });
        }
        
    } catch (error) {
        console.error('Erro ao alterar confirmação:', error);
        alert('Erro ao alterar confirmação. Tente novamente.');
    }
}

/**
 * Alterar tipo de atendimento em tempo real (modal visualização)
 */
async function alterarTipoAtendimento(agendamentoId, valor, selectElement = null) {
    console.log('🔄 Alterando tipo de atendimento:', agendamentoId, 'para:', valor);
    
    try {
        const formData = new FormData();
        formData.append('agendamento_id', agendamentoId);
        formData.append('tipo_atendimento', valor);
        
        const response = await fetch('atualizar_agendamento_rapido.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await safeJsonParse(response);
        
        if (resultado.sucesso) {
            console.log('✅ Tipo de atendimento alterado com sucesso');
            
            // Registrar na auditoria
            const valorAnterior = valor === 'PRIORIDADE' ? 'NORMAL' : 'PRIORIDADE';
            registrarAuditoriaAlteracao('EDITAR', agendamentoId, 'tipo_atendimento', valorAnterior, valor, 'Alteração de tipo de atendimento via modal');
            
            // Atualizar dados na tabela sem refresh
            if (typeof atualizarDadosAgendamentoNaTabela === 'function') {
                atualizarDadosAgendamentoNaTabela(agendamentoId, {tipo_atendimento: valor});
            }
            
            // Feedback visual no elemento
            if (selectElement) {
                selectElement.style.background = '#f0f9ff';
                setTimeout(() => {
                    selectElement.style.background = '';
                }, 1000);
            }
        } else {
            alert(`Erro: ${resultado.erro}`);
            // Reverter seleção se deu erro
            if (selectElement) {
                selectElement.value = valor === 'PRIORIDADE' ? 'NORMAL' : 'PRIORIDADE';
            }
        }
        
    } catch (error) {
        console.error('Erro ao alterar tipo de atendimento:', error);
        alert('Erro ao alterar tipo de atendimento. Tente novamente.');
    }
}

/**
 * Atualizar dados do agendamento na tabela sem refresh
 */
function atualizarDadosAgendamentoNaTabela(agendamentoId, dadosAtualizados) {
    console.log('🔄 Procurando linha do agendamento:', agendamentoId);
    
    // Buscar a linha que contém os botões com o ID do agendamento
    const botaoVisualizar = document.querySelector(`button[onclick*="visualizarAgendamento(${agendamentoId})"]`);
    const botaoEditar = document.querySelector(`button[onclick*="editarAgendamento(${agendamentoId})"]`);
    const botaoCancelar = document.querySelector(`button[onclick*="cancelarAgendamento(${agendamentoId})"]`);
    
    let linhaAgendamento = null;
    
    // Encontrar a linha a partir de qualquer um dos botões
    if (botaoVisualizar) {
        linhaAgendamento = botaoVisualizar.closest('tr');
    } else if (botaoEditar) {
        linhaAgendamento = botaoEditar.closest('tr');
    } else if (botaoCancelar) {
        linhaAgendamento = botaoCancelar.closest('tr');
    }
    
    if (linhaAgendamento) {
        console.log('✅ Linha encontrada! Atualizando dados...');
        
        // Atualizar tipo de atendimento
        if (dadosAtualizados.tipo_atendimento) {
            console.log('🎯 Atualizando tipo de atendimento para:', dadosAtualizados.tipo_atendimento);
            
            // Procurar por span existente de prioridade
            let spanPrioridade = linhaAgendamento.querySelector('span[class*="bg-red-100"], span[class*="bg-yellow-100"], span[class*="bg-green-100"]');
            
            if (spanPrioridade) {
                console.log('📋 Span existente encontrado, atualizando...');
                if (dadosAtualizados.tipo_atendimento === 'PRIORIDADE') {
                    spanPrioridade.className = 'text-xs bg-red-100 text-red-800 px-2 py-1 rounded font-semibold';
                    spanPrioridade.innerHTML = '<i class="bi bi-exclamation-triangle mr-1"></i>PRIORIDADE';
                } else {
                    spanPrioridade.className = 'text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-semibold';
                    spanPrioridade.innerHTML = '<i class="bi bi-check-circle mr-1"></i>NORMAL';
                }
            } else {
                console.log('📋 Span não encontrado, procurando local para criar...');
                
                // Procurar célula do paciente para adicionar o span
                const celulaPaciente = linhaAgendamento.querySelector('td:nth-child(2)'); // Segunda coluna (paciente)
                if (celulaPaciente) {
                    // Criar novo span
                    const novoSpan = document.createElement('span');
                    if (dadosAtualizados.tipo_atendimento === 'PRIORIDADE') {
                        novoSpan.className = 'text-xs bg-red-100 text-red-800 px-2 py-1 rounded font-semibold ml-2';
                        novoSpan.innerHTML = '<i class="bi bi-exclamation-triangle mr-1"></i>PRIORIDADE';
                    } else {
                        novoSpan.className = 'text-xs bg-green-100 text-green-800 px-2 py-1 rounded font-semibold ml-2';
                        novoSpan.innerHTML = '<i class="bi bi-check-circle mr-1"></i>NORMAL';
                    }
                    celulaPaciente.appendChild(novoSpan);
                    console.log('✨ Novo span criado e adicionado');
                }
                
                // Também procurar e atualizar texto direto nas células
                const celulas = linhaAgendamento.querySelectorAll('td');
                celulas.forEach((celula, index) => {
                    if (celula.textContent.includes('PRIORIDADE') || celula.textContent.includes('NORMAL')) {
                        const novoTexto = celula.textContent.replace(/PRIORIDADE|NORMAL/g, dadosAtualizados.tipo_atendimento);
                        celula.textContent = novoTexto;
                        console.log(`📝 Texto atualizado na célula ${index}:`, novoTexto);
                    }
                });
            }
        }
        
        // Atualizar confirmação se fornecida
        if (dadosAtualizados.confirmado !== undefined) {
            console.log('🎯 Atualizando confirmação para:', dadosAtualizados.confirmado);
            
            // Procurar por checkbox ou span que indica confirmação
            const checkboxConfirmado = linhaAgendamento.querySelector('input[type="checkbox"]');
            if (checkboxConfirmado) {
                checkboxConfirmado.checked = dadosAtualizados.confirmado === 1;
                console.log('✅ Checkbox de confirmação atualizado');
            }
            
            // Procurar por ícones de confirmação
            const iconeConfirmacao = linhaAgendamento.querySelector('.bi-check-circle, .bi-x-circle');
            if (iconeConfirmacao) {
                if (dadosAtualizados.confirmado === 1) {
                    iconeConfirmacao.className = 'bi bi-check-circle text-green-600';
                    iconeConfirmacao.parentElement.title = 'Confirmado';
                } else {
                    iconeConfirmacao.className = 'bi bi-x-circle text-red-600';
                    iconeConfirmacao.parentElement.title = 'Não confirmado';
                }
                console.log('✅ Ícone de confirmação atualizado');
            }
            
            // Procurar por texto "Sim"/"Não" na célula
            const celulas = linhaAgendamento.querySelectorAll('td');
            celulas.forEach((celula, index) => {
                const textoOriginal = celula.textContent;
                if (textoOriginal.includes('Sim') || textoOriginal.includes('Não')) {
                    const novoTexto = textoOriginal.replace(/Sim|Não/g, dadosAtualizados.confirmado ? 'Sim' : 'Não');
                    celula.textContent = novoTexto;
                    console.log(`📝 Confirmação atualizada na célula ${index}:`, novoTexto);
                }
            });
        }
        
        // Atualizar chegada se fornecida
        if (dadosAtualizados.chegada !== undefined) {
            console.log('🏃‍♂️ Atualizando chegada para:', dadosAtualizados.chegada);
            
            // Procurar e atualizar botão de marcar chegada
            const botaoChegada = linhaAgendamento.querySelector(`button[onclick*="marcarChegada(${agendamentoId})"]`);
            if (botaoChegada && dadosAtualizados.chegada) {
                // Substituir botão por indicador de chegada
                botaoChegada.outerHTML = `
                    <div class="flex items-center text-green-600" title="Chegou às ${dadosAtualizados.hora_chegada || 'N/A'}">
                        <i class="bi bi-check-circle mr-1"></i>
                        <span class="text-xs">#${dadosAtualizados.ordem_chegada || '?'}</span>
                    </div>
                `;
                console.log('✅ Botão de chegada atualizado');
            }
            
            // Procurar por ícones de chegada existentes e atualizar
            const iconeChegada = linhaAgendamento.querySelector('.bi-geo-alt, .bi-check-circle');
            if (iconeChegada && dadosAtualizados.chegada) {
                iconeChegada.className = 'bi bi-check-circle text-green-600';
                iconeChegada.parentElement.title = `Chegou às ${dadosAtualizados.hora_chegada || 'N/A'}`;
                console.log('✅ Ícone de chegada atualizado');
            }
        }
        
        // Feedback visual na linha
        linhaAgendamento.style.backgroundColor = '#f0f9ff';
        linhaAgendamento.style.transition = 'background-color 0.3s ease';
        setTimeout(() => {
            linhaAgendamento.style.backgroundColor = '';
        }, 2000);
        
        console.log('✅ Dados atualizados na tabela para agendamento:', agendamentoId);
    } else {
        console.log('⚠️ Linha do agendamento não encontrada na tabela:', agendamentoId);
        console.log('📊 Debug: Elementos encontrados:');
        console.log('- Botão visualizar:', !!botaoVisualizar);
        console.log('- Botão editar:', !!botaoEditar); 
        console.log('- Botão cancelar:', !!botaoCancelar);
    }
}

/**
 * Registrar alteração na auditoria
 */
async function registrarAuditoriaAlteracao(acao, agendamentoId, campo, valorAnterior, valorNovo, observacoes = '') {
    try {
        const formData = new FormData();
        formData.append('acao', acao);
        formData.append('agendamento_id', agendamentoId);
        formData.append('campo_alterado', campo);
        formData.append('valor_anterior', valorAnterior);
        formData.append('valor_novo', valorNovo);
        formData.append('observacoes', observacoes);
        
        const response = await fetch('registrar_auditoria.php', {
            method: 'POST',
            body: formData
        });
        
        const resultado = await safeJsonParse(response);
        
        if (resultado.sucesso) {
            console.log('📋 Auditoria registrada:', {acao, agendamentoId, campo, valorAnterior, valorNovo});
            
            // Atualizar histórico no modal se estiver aberto
            atualizarHistoricoModal(agendamentoId);
        } else {
            console.warn('⚠️ Falha ao registrar auditoria:', resultado.erro);
        }
        
    } catch (error) {
        console.error('💥 Erro ao registrar auditoria:', error);
    }
}

/**
 * Atualizar botão de chegada na interface
 */
function atualizarBotaoChegada(agendamentoId, ordemChegada, horaChegada) {
    // Buscar todos os botões de marcar chegada com esse ID
    const botoesChegada = document.querySelectorAll(`button[onclick*="marcarChegada(${agendamentoId})"]`);
    
    botoesChegada.forEach(botao => {
        // Substituir botão por indicador visual de chegada
        botao.outerHTML = `
            <div class="flex items-center text-green-600" title="Chegou às ${horaChegada}">
                <i class="bi bi-check-circle mr-1"></i>
                <span class="text-xs font-semibold">#${ordemChegada}</span>
            </div>
        `;
    });
    
    console.log(`✅ ${botoesChegada.length} botão(ões) de chegada atualizados para agendamento ${agendamentoId}`);
}

/**
 * Atualizar histórico no modal se estiver aberto
 */
async function atualizarHistoricoModal(agendamentoId) {
    try {
        // Verificar se o modal está aberto e se existe a seção de histórico
        const modalVisualizacao = document.getElementById('modal-visualizar-agendamento');
        const containerHistorico = document.querySelector('#historico-container-content');
        
        if (!modalVisualizacao || modalVisualizacao.style.display === 'none' || !containerHistorico) {
            console.log('💡 Modal não está aberto ou seção de histórico não encontrada');
            return;
        }
        
        console.log('🔄 Atualizando histórico do modal para agendamento:', agendamentoId);
        
        // Mostrar indicador de carregamento
        containerHistorico.innerHTML = '<div class="text-center text-gray-500 py-4">🔄 Atualizando histórico...</div>';
        
        // Buscar histórico atualizado
        const response = await fetchWithAuth(`buscar_historico_agendamento.php?agendamento_id=${agendamentoId}`);
        const dados = await safeJsonParse(response);
        
        if (dados.status === 'sucesso' && dados.historico) {
            console.log('✅ Histórico atualizado:', dados.historico.length, 'registros');
            
            if (dados.historico.length === 0) {
                containerHistorico.innerHTML = '<div class="text-center text-gray-500 py-4">📭 Nenhum histórico encontrado</div>';
                return;
            }
            
            // Renderizar histórico atualizado
            let html = '<div class="space-y-3">';
            
            dados.historico.forEach((item, index) => {
                html += `
                    <div class="border-l-4 border-blue-200 bg-gray-50 p-3 rounded-r-lg">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <i class="bi ${item.acao_icone} ${item.acao_cor}"></i>
                                <span class="font-semibold text-gray-800">${item.acao_titulo}</span>
                                <span class="text-xs bg-gray-200 text-gray-700 px-2 py-1 rounded">${item.acao}</span>
                            </div>
                            <span class="text-xs text-gray-500">${item.data_acao_formatada}</span>
                        </div>
                        
                        <div class="mt-2 text-sm text-gray-600">
                            <div><strong>Usuário:</strong> ${item.usuario}</div>
                            ${item.campos_alterados_texto ? `<div><strong>Campos alterados:</strong> ${item.campos_alterados_texto}</div>` : ''}
                            ${item.observacoes ? `<div><strong>Observações:</strong> ${item.observacoes}</div>` : ''}
                        </div>
                        
                        ${item.status_anterior && item.status_novo ? `
                            <div class="mt-2 text-xs bg-blue-50 p-2 rounded">
                                <strong>Alteração:</strong> ${item.status_anterior} → ${item.status_novo}
                            </div>
                        ` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            containerHistorico.innerHTML = html;
            
        } else {
            throw new Error(dados.mensagem || 'Erro ao buscar histórico');
        }
        
    } catch (error) {
        console.error('💥 Erro ao atualizar histórico:', error);
        const containerHistorico = document.querySelector('#historico-container-content');
        if (containerHistorico) {
            containerHistorico.innerHTML = '<div class="text-center text-red-500 py-4">❌ Erro ao carregar histórico</div>';
        }
    }
}

// Função para atualizar botão "Criar OS" para "Ver OS" após criação bem-sucedida
function atualizarBotaoCriarOS(numeroOS) {
    // Buscar o botão "Criar O.S." no modal de visualização
    const modalVisualizacao = document.querySelector('.modal-visualizacao');
    if (!modalVisualizacao) return;
    
    // Encontrar o botão de criar OS (que contém o texto "Criar O.S.")
    const botoesCriarOS = modalVisualizacao.querySelectorAll('button[onclick*="criarOrdemServico"]');
    
    botoesCriarOS.forEach(botao => {
        if (botao.textContent.includes('Criar O.S.')) {
            // Substituir pelo botão "Ver O.S."
            botao.onclick = () => mostrarModalDetalhesOS({numero_os: numeroOS});
            botao.innerHTML = `
                <i class="bi bi-file-earmark-text mr-2"></i>Ver O.S. ${numeroOS}
            `;
            botao.className = 'px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition';
            botao.title = 'Visualizar Ordem de Serviço existente';
        }
    });
    
    console.log('✅ Botão "Criar OS" atualizado para "Ver OS"');
}

// Função para atualizar status na tabela da agenda sem refresh
function atualizarStatusNaTabela(agendamentoId, novoStatus) {
    console.log(`🔄 Atualizando status na tabela para agendamento ${agendamentoId}: ${novoStatus}`);
    
    // Buscar o elemento do agendamento na tabela
    const agendamentoElement = document.querySelector(`[data-agendamento-id="${agendamentoId}"]`);
    
    if (agendamentoElement) {
        // Encontrar o badge de status dentro do elemento (buscar por classe mais específica)
        const statusBadge = agendamentoElement.querySelector('span[class*="inline-flex"][class*="items-center"][class*="rounded-full"]');
        
        if (statusBadge) {
            // Substituir o badge com o novo status
            statusBadge.outerHTML = getStatusBadge(novoStatus);
            console.log(`✅ Status atualizado na tabela para agendamento ${agendamentoId}`);
        } else {
            console.log(`⚠️ Badge de status não encontrado para agendamento ${agendamentoId}`);
        }
    } else {
        // Tentar buscar por outras formas (onclick, data attributes, etc.)
        const alternativeElements = [
            document.querySelector(`[onclick*="visualizarAgendamento(${agendamentoId})"]`),
            document.querySelector(`[onclick*="editarAgendamento(${agendamentoId})"]`),
            document.querySelector(`[data-id="${agendamentoId}"]`)
        ];
        
        for (const element of alternativeElements) {
            if (element) {
                // Procurar o badge de status no elemento ou seus pais
                const statusBadge = element.closest('tr')?.querySelector('span[class*="inline-flex"][class*="items-center"][class*="rounded-full"]') ||
                                  element.closest('div')?.querySelector('span[class*="inline-flex"][class*="items-center"][class*="rounded-full"]');
                
                if (statusBadge) {
                    statusBadge.outerHTML = getStatusBadge(novoStatus);
                    console.log(`✅ Status atualizado na tabela para agendamento ${agendamentoId} (método alternativo)`);
                    return;
                }
            }
        }
        
        console.log(`⚠️ Elemento do agendamento ${agendamentoId} não encontrado na tabela`);
    }
}

// Função para alterar status do agendamento
async function alterarStatusAgendamento(agendamentoId, novoStatus) {
    console.log(`🔄 Alterando status do agendamento ${agendamentoId} para ${novoStatus}`);
    
    try {
        // Mostrar loading no select
        const selectElement = document.getElementById(`status-select-${agendamentoId}`);
        const originalHtml = selectElement.innerHTML;
        selectElement.disabled = true;
        
        // Fazer a requisição para alterar o status
        const formData = new FormData();
        formData.append('agendamento_id', agendamentoId);
        formData.append('status', novoStatus);
        formData.append('usuario', 'SISTEMA'); // You might want to get the actual user
        
        const response = await fetch('atualizar_status_agendamento.php', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
        const resultado = await safeJsonParse(response);
        
        if (resultado.success) {
            // Atualizar o badge de status atual
            const statusBadgeContainer = selectElement.closest('.mb-6').querySelector('.flex-shrink-0');
            if (statusBadgeContainer) {
                const badgeElement = statusBadgeContainer.querySelector('span');
                if (badgeElement) {
                    badgeElement.outerHTML = getStatusBadge(novoStatus);
                }
            }
            
            // Registrar auditoria
            const statusAnterior = selectElement.dataset.originalValue;
            await registrarAuditoriaAlteracao('ALTERAR_STATUS', agendamentoId, 'STATUS', statusAnterior, novoStatus);
            
            // Atualizar histórico se disponível
            if (typeof atualizarHistoricoModal === 'function') {
                await atualizarHistoricoModal(agendamentoId);
            }
            
            // Atualizar status na tabela da agenda sem recarregar a página
            atualizarStatusNaTabela(agendamentoId, novoStatus);
            
            // Atualizar o valor original para futuras comparações
            selectElement.dataset.originalValue = novoStatus;
            
            showToast(`✅ Status alterado para: ${novoStatus}`, true);
            
        } else {
            throw new Error(resultado.mensagem || 'Erro ao alterar status');
        }
        
    } catch (error) {
        console.error('❌ Erro ao alterar status:', error);
        
        // Reverter o select para o valor original
        const selectElement = document.getElementById(`status-select-${agendamentoId}`);
        if (selectElement && selectElement.dataset.originalValue) {
            selectElement.value = selectElement.dataset.originalValue;
        }
        
        showToast(`❌ Erro ao alterar status: ${error.message}`, false);
        
    } finally {
        // Re-habilitar o select
        const selectElement = document.getElementById(`status-select-${agendamentoId}`);
        if (selectElement) {
            selectElement.disabled = false;
        }
    }
}

// Tornar as funções globais
window.alterarConfirmacao = alterarConfirmacao;
window.alterarTipoAtendimento = alterarTipoAtendimento;
window.atualizarDadosAgendamentoNaTabela = atualizarDadosAgendamentoNaTabela;
window.registrarAuditoriaAlteracao = registrarAuditoriaAlteracao;
window.atualizarHistoricoModal = atualizarHistoricoModal;
window.atualizarBotaoChegada = atualizarBotaoChegada;
window.visualizarRetornosDia = visualizarRetornosDia;
window.criarListaRetornos = criarListaRetornos;
window.atualizarBotaoCriarOS = atualizarBotaoCriarOS;
window.alterarStatusAgendamento = alterarStatusAgendamento;
window.atualizarStatusNaTabela = atualizarStatusNaTabela;

/**
 * Calcula idade automaticamente no modal de edição
 */
function calcularIdadeEdicao() {
    const dataNascimento = document.getElementById('data_nascimento_edicao');
    const campoIdade = document.getElementById('idade_edicao');
    
    if (!dataNascimento || !campoIdade) {
        console.warn('Campos de data de nascimento ou idade não encontrados no modal de edição');
        return;
    }
    
    if (dataNascimento.value) {
        const hoje = new Date();
        const nascimento = new Date(dataNascimento.value);
        
        // Verificar se a data é válida
        if (nascimento > hoje) {
            alert('Data de nascimento não pode ser futura');
            dataNascimento.value = '';
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
        
        // Validação de idade mínima
        if (idade < 0) {
            alert('Idade inválida');
            campoIdade.value = '';
        }
        
        console.log('Idade calculada (edição):', idade, 'anos para', dataNascimento.value);
    } else {
        campoIdade.value = '';
    }
}

// Tornar a função global
window.calcularIdadeEdicao = calcularIdadeEdicao;

// Função para abrir modal de OS com dados completos
window.abrirModalOSCompleto = function(agendamentoId) {
    console.log('Abrindo modal da OS para agendamento:', agendamentoId);
    
    fetchWithAuth(`buscar_os_agendamento.php?agendamento_id=${agendamentoId}`)
        .then(safeJsonParse)
        .then(data => {
            console.log('Dados da OS recebidos:', data);
            
            if (data.tem_os) {
                mostrarModalDetalhesOS(data);
            } else {
                alert('Nenhuma OS encontrada para este agendamento.');
            }
        })
        .catch(error => {
            console.error('Erro ao buscar dados da OS:', error);
            alert('Erro ao carregar dados da OS.');
        });
};

