function carregarConfiguracoes() {
  const conteudoDiv = document.getElementById('conteudo-dinamico');
  const loader = document.getElementById('loader');

  loader.classList.remove('hidden');

  fetch('configuracoes.php')
    .then(res => {
      // Verificar se foi redirecionado para login
      if (res.redirected && res.url.includes('frmindex.php')) {
        window.location.href = res.url;
        return;
      }
      return res.text();
    })
    .then(html => {
      if (html) {
        conteudoDiv.innerHTML = html;
        // Agora que #itensPorPagina e #tabelaAgendas existem, podemos chamar:
        carregarPagina();
      }
    })
    .catch(() => {
      conteudoDiv.innerHTML = '<div class="text-red-600">Erro ao carregar configurações ou sem permissão de acesso.</div>';
    })
    .finally(() => {
      loader.classList.add('hidden');
    });
}

function mostrarFormulario(tipo) {
  // Esconde todos os formulários
  document.querySelectorAll('[id^="form-"]').forEach(div => div.classList.add('hidden'));

  // Esconde o bloco de listagem
  const listagem = document.getElementById('bloco-listagem');
  if (listagem) listagem.classList.add('hidden');

  // Mostra o formulário correto
  const form = document.getElementById('form-' + tipo);
  if (form) {
    form.classList.remove('hidden');

    if (tipo === 'criar') {
      // Limpar cache de dados para evitar interferência com dados de edição
      cachedConvenios = null;
      if (typeof window.currentTipo !== 'undefined') delete window.currentTipo;
      if (typeof window.currentNome !== 'undefined') delete window.currentNome;
      
      // ✅ CORREÇÃO: Limpar TODAS as variáveis globais da edição anterior
      if (typeof window.valoresSalvosVagas !== 'undefined') {
        delete window.valoresSalvosVagas;
        console.log('🧹 Limpou valores salvos de vagas da sessão anterior');
      }
      
      // Limpar outras variáveis globais que podem interferir
      if (typeof window.agendaIdAtual !== 'undefined') {
        window.agendaIdAtual = null;
        console.log('🧹 Limpou agendaIdAtual');
      }
      
      // Limpar preparos globais
      if (typeof window.preparos !== 'undefined') {
        window.preparos = [];
        console.log('🧹 Limpou preparos globais');
      }
      
      if (typeof window.preparoEditandoIndex !== 'undefined') {
        window.preparoEditandoIndex = -1;
        console.log('🧹 Resetou preparoEditandoIndex');
      }
      
      // ✅ CORREÇÃO: Limpar arquivos selecionados
      if (typeof window.arquivosSelecionados !== 'undefined') {
        window.arquivosSelecionados = [];
        console.log('🧹 Limpou arquivos selecionados');
      }
      
      // Adicionar timestamp para forçar cache bust
      const timestamp = new Date().getTime();
      fetch(`form_criar_agenda.php?t=${timestamp}`)
        .then(response => response.text())
        .then(html => {
          form.innerHTML = html;

          // Inicializa Select2 após o HTML ser carregado
          $('.select-horario').select2({
            placeholder: "-- : --",
            allowClear: true,
            width: '100%',
            language: {
              noResults: () => "Nenhum horário encontrado",
              searching: () => "Buscando...",
              inputTooShort: () => "Digite para pesquisar"
            }
          });

          // ✅ CORREÇÃO: Limpar checkboxes dos dias ANTES de desabilitar horários
          document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
            checkbox.checked = false;
            console.log('🧹 CRIAR: Checkbox', checkbox.value, 'desmarcado');
          });

          // ✅ CORREÇÃO: Ocultar todos os campos de vagas e limpar valores
          document.querySelectorAll('[id^="vagas-"]:not(#vagas-por-dia)').forEach(vagasDiv => {
            vagasDiv.classList.add('hidden');
            const input = vagasDiv.querySelector('input[type="number"]');
            if (input) {
              input.value = '';
              input.required = false;
            }
            console.log('🧹 CRIAR: Ocultou campo de vagas', vagasDiv.id);
          });

          // Desabilita todos os horários por padrão
          $('.select-horario').prop('disabled', true);

          // Reage aos checkboxes para habilitar/desabilitar os horários
          document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function () {
              const slug = this.value.toLowerCase().substring(0, 3);
              
              // Controlar horários
              document.querySelectorAll(`select[data-dia="${slug}"]`).forEach(select => {
                $(select).prop('disabled', !this.checked).trigger('change.select2');
              });
              
              // Controlar campos de vagas
              const vagasDiv = document.getElementById(`vagas-${slug}`);
              const vagasInput = document.querySelector(`input[name="vagas_${slug}"]`);
              
              if (vagasDiv && vagasInput) {
                if (this.checked) {
                  vagasDiv.classList.remove('hidden');
                  vagasInput.required = true;
                } else {
                  vagasDiv.classList.add('hidden');
                  vagasInput.required = false;
                  vagasInput.value = '';
                }
              }
            });
          });

          inicializarCriacaoAgenda();
          
          // ✅ CORREÇÃO: Garantir limpeza do DOM de preparos
          setTimeout(() => {
            const listaPreparos = document.getElementById('lista-preparos');
            if (listaPreparos) {
              listaPreparos.innerHTML = '';
            }
            
            const semPreparos = document.getElementById('sem-preparos');
            if (semPreparos) {
              semPreparos.classList.remove('hidden');
            }
            
            console.log('🧹 Limpeza final do DOM de preparos');
          }, 200);

          // Força Select2 apenas no campo tempo estimado, se necessário
          const tempoSelect = document.querySelector('#tempo_estimado');
          if (tempoSelect && !$(tempoSelect).hasClass('select2-hidden-accessible')) {
            $(tempoSelect).select2({
              width: '100%',
              placeholder: tempoSelect.dataset.placeholder || 'Selecione',
              allowClear: true,
              language: {
                noResults: () => "Nenhum resultado encontrado",
                searching: () => "Buscando..."
              }
            });
          }

          // ✅ Adiciona listener de submit apenas uma vez
          setTimeout(() => {
            const novoForm = document.getElementById('form-agenda');
            if (!novoForm) {
              console.warn('⚠️ Formulário não encontrado para adicionar listener.');
              return;
            }

            // Remove listener anterior se existir para evitar duplicação
            const oldListener = novoForm.getAttribute('data-listener-added');
            if (oldListener === 'true') {
              return; // Já tem listener
            }

            novoForm.setAttribute('data-listener-added', 'true');
            novoForm.addEventListener('submit', async function (e) {
              e.preventDefault();
              console.log('✅ Submissão interceptada');

              if (!validarCamposObrigatorios()) {
                console.log('❌ Campos obrigatórios não preenchidos');
                return;
              }

              const horarios = [];
              document.querySelectorAll('input[name="dias[]"]:checked').forEach(cb => {
                const slug = cb.dataset.dia;
                horarios.push({
                  dia: cb.value,
                  manha_ini: document.querySelector(`select[name="${slug}_manha_inicio"]`)?.value || '',
                  manha_fim: document.querySelector(`select[name="${slug}_manha_fim"]`)?.value || '',
                  tarde_ini: document.querySelector(`select[name="${slug}_tarde_inicio"]`)?.value || '',
                  tarde_fim: document.querySelector(`select[name="${slug}_tarde_fim"]`)?.value || ''
                });
              });

              // ✅ CORREÇÃO: Coletar vagas por dia da semana (apenas dias selecionados)
              const vagas = {};
              document.querySelectorAll('input[name="dias[]"]:checked').forEach(cb => {
                const slug = cb.dataset.dia;
                const vagasInput = document.querySelector(`input[name="vagas_${slug}"]`);
                console.log(`🔍 Verificando vagas para ${slug}:`, {
                  checkbox_checked: cb.checked,
                  input_exists: !!vagasInput,
                  input_value: vagasInput?.value,
                  input_visible: vagasInput && !vagasInput.closest('[id^="vagas-"]')?.classList.contains('hidden')
                });
                
                if (vagasInput && vagasInput.value) {
                  vagas[slug] = parseInt(vagasInput.value) || 0;
                  console.log(`📊 ✅ Coletando vagas para ${slug}: ${vagas[slug]}`);
                } else {
                  console.log(`📊 ❌ Vagas não coletadas para ${slug}: input=${!!vagasInput}, value="${vagasInput?.value}"`);
                }
              });

              const convenios = [];
              document.querySelectorAll('.grupo-convenio').forEach(grupo => {
                const id = grupo.querySelector('select[name="convenio_id[]"]')?.value;
                const limite = grupo.querySelector('input[name="limite_atendimentos[]"]')?.value;
                const retornos = grupo.querySelector('input[name="qtd_retornos[]"]')?.value;
                if (id) convenios.push({ id, limite, retornos });
              });

              const formData = new FormData(novoForm);
              formData.append('horarios', JSON.stringify(horarios));
              formData.append('convenios', JSON.stringify(convenios));
              formData.append('vagas', JSON.stringify(vagas)); // ✅ CORREÇÃO: Enviar vagas por dia

              try {
                const res = await fetch('salvar_agenda.php', {
                  method: 'POST',
                  body: formData
                });

                const json = await res.json();

                if (json.status === 'sucesso') {
                  showToast(json.mensagem, true);
                  
                  // ✅ CORREÇÃO: Processar uploads pendentes após agenda criada
                  console.log('🚀 Iniciando processamento de uploads pendentes');
                  console.log('📋 Resposta JSON completa:', json);
                  
                  try {
                    await processarUploadsPendentes(json.agenda_id, json.preparos_mapeados);
                    console.log('✅ Uploads pendentes processados com sucesso');
                  } catch (error) {
                    console.error('❌ Erro no processamento de uploads:', error);
                  }
                  
                  voltarParaListagem();
                  carregarPagina();
                } else {
                  showToast('Erro: ' + json.mensagem, false);
                }

              } catch (err) {
                showToast('Erro ao salvar. Verifique a conexão ou o servidor.', false);
                console.error(err);
              }
            });
          }, 100);

        })
        .catch(() => {
          form.innerHTML = `<div class="text-red-600">Erro ao carregar o formulário.</div>`;
        });
    }
  }
}

function voltarParaListagem() {
  // Oculta formulários
  document.querySelectorAll('[id^="form-"]').forEach(div => div.classList.add('hidden'));

  // ✅ CORREÇÃO: Limpar TODAS as variáveis globais ao voltar
  if (typeof window.valoresSalvosVagas !== 'undefined') {
    delete window.valoresSalvosVagas;
    console.log('🧹 VOLTAR: Limpou valores salvos de vagas');
  }
  
  // Limpar outras variáveis globais
  if (typeof window.agendaIdAtual !== 'undefined') {
    window.agendaIdAtual = null;
    console.log('🧹 VOLTAR: Limpou agendaIdAtual');
  }
  
  if (typeof window.preparos !== 'undefined') {
    window.preparos = [];
    console.log('🧹 VOLTAR: Limpou preparos globais');
  }
  
  if (typeof window.preparoEditandoIndex !== 'undefined') {
    window.preparoEditandoIndex = -1;
    console.log('🧹 VOLTAR: Resetou preparoEditandoIndex');
  }

  // Exibe a listagem
  const listagem = document.getElementById('bloco-listagem');
  if (listagem) listagem.classList.remove('hidden');
}


function inicializarCriacaoAgenda() {
  // Limpar dados de sessões anteriores para evitar cache
  window.preparos = [];
  window.preparoEditandoIndex = -1;
  
  // ✅ CORREÇÃO: Limpar valores salvos de vagas caso ainda existam
  if (typeof window.valoresSalvosVagas !== 'undefined') {
    delete window.valoresSalvosVagas;
    console.log('🧹 CRIAR: Limpou valores salvos de vagas residuais');
  }
  
  // ✅ CORREÇÃO EXTRA: Garantir que checkboxes estejam desmarcados e campos ocultos
  setTimeout(() => {
    document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
      if (checkbox.checked) {
        checkbox.checked = false;
        console.log('🧹 INIT CRIAR: Desmarcou checkbox', checkbox.value);
      }
    });
    
    document.querySelectorAll('[id^="vagas-"]:not(#vagas-por-dia)').forEach(vagasDiv => {
      if (!vagasDiv.classList.contains('hidden')) {
        vagasDiv.classList.add('hidden');
        const input = vagasDiv.querySelector('input[type="number"]');
        if (input) {
          input.value = '';
          input.required = false;
        }
        console.log('🧹 INIT CRIAR: Ocultou campo de vagas', vagasDiv.id);
      }
    });
  }, 100); // Pequeno delay para garantir que o DOM esteja pronto
  
  aplicarSelect2NoFoco();
  ativarValidacao();
  ativarValidacaoTipoAgenda();
  ativarHorariosPorDia();
  inicializarListenersAgenda();
  aplicarListenersCriarAgenda();
  aplicarMascaraTelefone();
  inicializarPreparos();
}

// ================================
// SISTEMA DE PREPAROS
// ================================

// Variáveis globais para preparos
window.preparos = window.preparos || [];
window.preparoEditandoIndex = -1;

function inicializarPreparos() {
  // Inicializar variáveis globais se não existirem
  if (!window.preparos) {
    window.preparos = [];
  }
  if (typeof window.preparoEditandoIndex === 'undefined') {
    window.preparoEditandoIndex = -1;
  }
  if (!window.arquivosSelecionados) {
    window.arquivosSelecionados = [];
  }
  
  // ✅ CORREÇÃO: Verificar se está realmente no formulário de edição
  const formEditar = document.getElementById('form-editar');
  const formCriar = document.getElementById('form-criar');
  const isFormEditar = formEditar && !formEditar.classList.contains('hidden');
  const isFormCriar = formCriar && !formCriar.classList.contains('hidden');
  
  if (isFormCriar) {
    // Se está no form de criar, sempre inicializar vazio
    console.log('📝 Form CRIAR detectado - inicializando preparos vazios');
    window.preparos = [];
    window.preparoEditandoIndex = -1;
    window.arquivosSelecionados = [];
  } else if (isFormEditar) {
    // Se está no form de editar, carregar preparos existentes
    console.log('📝 Form EDITAR detectado - carregando preparos existentes');
    const preparosScript = document.getElementById('preparos-json');
    if (preparosScript) {
      const conteudo = preparosScript.textContent.trim();
      
      if (conteudo) {
        try {
          const preparosExistentes = JSON.parse(conteudo);
          if (Array.isArray(preparosExistentes)) {
            window.preparos = preparosExistentes;
            console.log('✅ Preparos carregados:', preparosExistentes.length);
          } else {
            console.warn('⚠️ Preparos não é um array:', preparosExistentes);
            window.preparos = [];
          }
        } catch (e) {
          console.error('❌ Erro ao carregar preparos existentes:', e);
          window.preparos = [];
        }
      } else {
        window.preparos = [];
      }
    } else {
      window.preparos = [];
    }
  } else {
    // Se não detectou formulário específico, inicializar vazio por segurança
    console.log('📝 Nenhum formulário específico detectado - inicializando vazio');
    window.preparos = [];
  }
  
  atualizarListaPreparos();
  
  // Adicionar listener de submit para incluir preparos no envio
  const formAgenda = document.getElementById('form-agenda');
  if (formAgenda) {
    // Remove listener anterior se existir
    formAgenda.removeEventListener('submit', adicionarPreparosAoSubmit);
    formAgenda.addEventListener('submit', adicionarPreparosAoSubmit);
  }
}

function adicionarPreparosAoSubmit(e) {
  // Criar campo hidden com os preparos
  let inputPreparos = document.querySelector('input[name="preparos"]');
  if (!inputPreparos) {
    inputPreparos = document.createElement('input');
    inputPreparos.type = 'hidden';
    inputPreparos.name = 'preparos';
    e.target.appendChild(inputPreparos);
  }
  inputPreparos.value = JSON.stringify(window.preparos);
}

// Função para abrir modal de preparo (disponível globalmente)
window.abrirModalPreparo = function(index = -1) {
  // Garantir inicialização das variáveis globais
  if (!window.preparos) window.preparos = [];
  if (!window.arquivosSelecionados) window.arquivosSelecionados = [];
  
  window.preparoEditandoIndex = index;
  const modal = document.getElementById('modal-preparo');
  const titulo = document.getElementById('modal-titulo');
  const inputTitulo = document.getElementById('preparo-titulo');
  const inputInstrucoes = document.getElementById('preparo-instrucoes');
  
  if (!modal || !titulo || !inputTitulo || !inputInstrucoes) {
    console.error('❌ Elementos do modal de preparo não encontrados:', {
      modal: !!modal,
      titulo: !!titulo, 
      inputTitulo: !!inputTitulo,
      inputInstrucoes: !!inputInstrucoes
    });
    return;
  }
  
  // Limpar anexos antes de abrir modal
  if (typeof limparAnexos === 'function') {
    limparAnexos();
  }
  
  if (index >= 0 && window.preparos[index]) {
    // Editando preparo existente
    titulo.textContent = 'Editar Preparo';
    inputTitulo.value = window.preparos[index].titulo || '';
    inputInstrucoes.value = window.preparos[index].instrucoes || '';
    
    // Mostrar anexos existentes (se houver)
    const anexosExistentes = window.preparos[index].anexos || [];
    console.log(`📎 Abrindo modal para preparo "${window.preparos[index].titulo}" com ${anexosExistentes.length} anexos`);
    
    if (anexosExistentes.length > 0) {
      if (typeof mostrarAnexosExistentes === 'function') {
        mostrarAnexosExistentes(anexosExistentes);
      } else {
        console.error('❌ Função mostrarAnexosExistentes não encontrada');
      }
    } else {
      // Limpar lista de anexos se não houver anexos
      const listaAnexos = document.getElementById('lista-anexos-preparo');
      if (listaAnexos) {
        listaAnexos.innerHTML = '';
      }
    }
  } else {
    // Novo preparo
    titulo.textContent = 'Adicionar Preparo';
    inputTitulo.value = '';
    inputInstrucoes.value = '';
  }
  
  modal.classList.remove('hidden');
  inputTitulo.focus();
}

// Função para fechar modal de preparo (disponível globalmente)
window.fecharModalPreparo = function() {
  const modal = document.getElementById('modal-preparo');
  if (modal) {
    modal.classList.add('hidden');
  }
  
  // Reset das variáveis globais
  window.preparoEditandoIndex = -1;
  
  // Limpar campos do modal
  const inputTitulo = document.getElementById('preparo-titulo');
  const inputInstrucoes = document.getElementById('preparo-instrucoes');
  
  if (inputTitulo) inputTitulo.value = '';
  if (inputInstrucoes) inputInstrucoes.value = '';
  
  // Limpar anexos ao fechar modal
  if (typeof limparAnexos === 'function') {
    limparAnexos();
  }
}

// Função para salvar preparo (disponível globalmente)
window.salvarPreparo = async function() {
  // Garantir inicialização das variáveis globais
  if (!window.preparos) window.preparos = [];
  if (!window.arquivosSelecionados) window.arquivosSelecionados = [];
  
  const titulo = document.getElementById('preparo-titulo')?.value.trim();
  const instrucoes = document.getElementById('preparo-instrucoes')?.value.trim();
  
  if (!titulo || !instrucoes) {
    alert('Por favor, preencha o título e as instruções do preparo.');
    return;
  }
  
  // Primeiro criar/atualizar o preparo para obter ID
  const preparo = { 
    titulo, 
    instrucoes,
    anexos: []
  };
  
  if (window.preparoEditandoIndex >= 0) {
    // Editando preparo existente - manter ID e anexos existentes
    const preparoExistente = window.preparos[window.preparoEditandoIndex];
    preparo.id = preparoExistente.id; // ✅ SEMPRE usar ID real do banco (sem fallback)
    preparo.anexos = preparoExistente.anexos || [];
    window.preparos[window.preparoEditandoIndex] = preparo;
    console.log('💾 Atualizando preparo existente ID:', preparo.id);
  } else {
    // Novo preparo - atribuir ID único (será substituído quando salvar no banco)
    preparo.id = Date.now(); // ✅ ID temporário único baseado em timestamp
    window.preparos.push(preparo);
    console.log('💾 Criando novo preparo ID temporário:', preparo.id);
  }
  
  // ✅ CORREÇÃO: Detectar se é criação ou edição para decidir quando fazer upload
  const isEdicao = document.querySelector('input[name="id"]')?.value;
  
  if (window.arquivosSelecionados.length > 0) {
    if (isEdicao && window.preparoEditandoIndex >= 0) {
      // EDIÇÃO DE PREPARO EXISTENTE: fazer upload imediatamente
      try {
        const btnSalvar = document.querySelector('[onclick="window.salvarPreparo()"]');
        const textoOriginal = btnSalvar?.innerHTML;
        if (btnSalvar) {
          btnSalvar.innerHTML = 'Enviando arquivos...';
          btnSalvar.disabled = true;
        }
        
        const anexosUpload = await uploadArquivos(window.arquivosSelecionados);
        preparo.anexos = [...preparo.anexos, ...anexosUpload];
        
        // ✅ CORREÇÃO: Atualizar o preparo no array global
        if (window.preparoEditandoIndex >= 0) {
          window.preparos[window.preparoEditandoIndex].anexos = preparo.anexos;
          console.log('📎 Anexos atualizados no preparo existente:', preparo.anexos.length);
        } else {
          // Já foi adicionado ao array no push acima
          console.log('📎 Anexos adicionados ao novo preparo:', preparo.anexos.length);
        }
        
        // Mostrar notificação de sucesso
        if (typeof window.showToast === 'function') {
          window.showToast(`${anexosUpload.length} arquivo(s) enviado(s) com sucesso!`, true);
        }
        
        if (btnSalvar) {
          btnSalvar.innerHTML = textoOriginal || 'Salvar Preparo';
          btnSalvar.disabled = false;
        }
        
        // ✅ CORREÇÃO: Limpar arquivos selecionados após upload bem-sucedido
        window.arquivosSelecionados = [];
        console.log('🧹 Arquivos selecionados limpos após upload');
        
      } catch (error) {
        alert('Erro ao enviar arquivos: ' + error.message);
        const btnSalvar = document.querySelector('[onclick="window.salvarPreparo()"]');
        if (btnSalvar) {
          btnSalvar.innerHTML = 'Salvar Preparo';
          btnSalvar.disabled = false;
        }
        return;
      }
    } else {
      // NOVO PREPARO (em agenda nova ou existente): marcar para upload posterior
      preparo.arquivosPendentes = [...window.arquivosSelecionados];
      console.log('📁 Arquivos marcados para upload posterior:', preparo.arquivosPendentes.length);
      
      if (isEdicao) {
        console.log('📝 Novo preparo em agenda existente - upload será feito após salvar agenda');
      } else {
        console.log('📝 Novo preparo em nova agenda - upload será feito após criar agenda');
      }
    }
  }
  
  
  atualizarListaPreparos();
  window.fecharModalPreparo();
}

window.removerPreparo = function(index) {
  if (confirm('Tem certeza que deseja remover este preparo?')) {
    window.preparos.splice(index, 1);
    atualizarListaPreparos();
  }
}

function atualizarListaPreparos() {
  const listaPreparos = document.getElementById('lista-preparos');
  const semPreparos = document.getElementById('sem-preparos');
  
  if (!listaPreparos || !semPreparos) {
    // Elementos não existem ainda, não há problema
    return;
  }
  
  if (window.preparos.length === 0) {
    listaPreparos.innerHTML = '';
    semPreparos.classList.remove('hidden');
    return;
  }
  
  semPreparos.classList.add('hidden');
  
  listaPreparos.innerHTML = window.preparos.map((preparo, index) => {
    const anexosTexto = preparo.anexos && preparo.anexos.length > 0 
      ? ` (${preparo.anexos.length} anexo${preparo.anexos.length > 1 ? 's' : ''})`
      : '';
    
    // Criar dados seguros para o modal (sem objetos File)
    let anexosSeguro = [];
    if (preparo.anexos && Array.isArray(preparo.anexos)) {
      anexosSeguro = preparo.anexos.map(a => ({
        nome: a.nome || 'Arquivo',
        tipo: a.tipo || 'txt',
        tamanho: a.tamanho || 0
      }));
    }
    
    return `
      <div class="bg-gray-50 dark:bg-gray-900/20 rounded-lg border-l-4 border-gray-400 p-3">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <button 
              type="button" 
              class="text-left w-full font-medium text-gray-800 dark:text-gray-200 hover:text-gray-600 dark:hover:text-gray-100 hover:underline focus:outline-none focus:ring-2 focus:ring-gray-300 rounded p-1 -m-1 transition-colors duration-200"
              onclick="abrirModalPreparoDetalhesComAnexos('${escapeHtml(preparo.titulo)}', '${escapeHtml(preparo.instrucoes)}', ${preparo.id || index + 1})"
              title="Clique para ver as instruções completas"
            >
              <svg class="w-4 h-4 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
              </svg>
              Preparo ${index + 1}: ${preparo.titulo}${anexosTexto}
            </button>
          </div>
          <div class="flex gap-2 ml-4">
            <button type="button" onclick="window.abrirModalPreparo(${index})" 
                    class="text-teal-600 hover:text-teal-800 text-sm px-2 py-1 bg-white rounded border border-teal-200 hover:bg-teal-50" title="Editar">
              <i class="bi bi-pencil"></i> Editar
            </button>
            <button type="button" onclick="window.removerPreparo(${index})" 
                    class="text-red-600 hover:text-red-800 text-sm px-2 py-1 bg-white rounded border border-red-200 hover:bg-red-50" title="Remover">
              <i class="bi bi-trash"></i> Remover
            </button>
          </div>
        </div>
      </div>
    `;
  }).join('');
}

// Função para escapar HTML (usada nos preparos)
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
}

// Variável global para armazenar arquivos selecionados
window.arquivosSelecionados = [];

// Função para gerenciar seleção de arquivos
function handleFileSelect(input) {
  const arquivos = Array.from(input.files);
  const listaAnexos = document.getElementById('lista-anexos-preparo');
  
  // Validar arquivos
  const arquivosValidos = [];
  const tiposPermitidos = ['.pdf', '.doc', '.docx', '.txt', '.jpg', '.jpeg', '.png'];
  const tamanhoMaximo = 5 * 1024 * 1024; // 5MB
  
  arquivos.forEach(arquivo => {
    // Verificar extensão
    const extensao = '.' + arquivo.name.split('.').pop().toLowerCase();
    if (!tiposPermitidos.includes(extensao)) {
      alert(`Arquivo "${arquivo.name}" não é permitido. Tipos aceitos: ${tiposPermitidos.join(', ')}`);
      return;
    }
    
    // Verificar tamanho
    if (arquivo.size > tamanhoMaximo) {
      alert(`Arquivo "${arquivo.name}" é muito grande. Máximo permitido: 5MB`);
      return;
    }
    
    arquivosValidos.push(arquivo);
  });
  
  // Adicionar arquivos válidos à lista global
  window.arquivosSelecionados = [...window.arquivosSelecionados, ...arquivosValidos];
  
  // Atualizar exibição
  atualizarListaAnexos();
  
  // Limpar input para permitir re-seleção do mesmo arquivo
  input.value = '';
}

// Função para mostrar anexos existentes
function mostrarAnexosExistentes(anexos) {
  const listaAnexos = document.getElementById('lista-anexos-preparo');
  if (!listaAnexos) return;
  
  const htmlAnexosExistentes = anexos.map(anexo => {
    const tamanhoMB = anexo.tamanho ? (anexo.tamanho / (1024 * 1024)).toFixed(2) : '0.00';
    return `
      <div class="flex items-center justify-between p-3 bg-blue-50 rounded border border-blue-200">
        <div class="flex items-center space-x-3">
          ${getIconeArquivo(anexo.nome || 'arquivo.txt')}
          <div>
            <p class="text-sm font-medium text-gray-900">${anexo.nome || 'Arquivo'}</p>
            <p class="text-xs text-gray-500">${tamanhoMB} MB</p>
            <p class="text-xs text-blue-600">Anexo salvo</p>
          </div>
        </div>
        <button type="button" onclick="baixarAnexo(${anexo.id})" 
                class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs flex items-center gap-1"
                title="Baixar arquivo">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
          </svg>
          Baixar
        </button>
      </div>
    `;
  }).join('');
  
  listaAnexos.innerHTML = htmlAnexosExistentes;
}

// Função para atualizar lista de anexos (novos arquivos selecionados)
function atualizarListaAnexos() {
  const listaAnexos = document.getElementById('lista-anexos-preparo');
  if (!listaAnexos) return;
  
  // Obter HTML de anexos existentes se houver
  let htmlExistente = '';
  if (window.preparoEditandoIndex >= 0) {
    const preparo = window.preparos[window.preparoEditandoIndex];
    if (preparo && preparo.anexos && preparo.anexos.length > 0) {
      htmlExistente = preparo.anexos.map(anexo => {
        const tamanhoMB = anexo.tamanho ? (anexo.tamanho / (1024 * 1024)).toFixed(2) : '0.00';
        return `
          <div class="flex items-center justify-between p-3 bg-blue-50 rounded border border-blue-200">
            <div class="flex items-center space-x-3">
              ${getIconeArquivo(anexo.nome || 'arquivo.txt')}
              <div>
                <p class="text-sm font-medium text-gray-900">${anexo.nome || 'Arquivo'}</p>
                <p class="text-xs text-gray-500">${tamanhoMB} MB</p>
                <p class="text-xs text-blue-600">Anexo salvo</p>
              </div>
            </div>
            <button type="button" onclick="window.baixarAnexo(${anexo.id})" 
                    class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs flex items-center gap-1"
                    title="Baixar arquivo">
              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
              </svg>
              Baixar
            </button>
          </div>
        `;
      }).join('');
    }
  }
  
  // HTML dos novos arquivos selecionados (se houver)
  let htmlNovos = '';
  if (window.arquivosSelecionados && window.arquivosSelecionados.length > 0) {
    htmlNovos = window.arquivosSelecionados.map((arquivo, index) => {
      const tamanhoMB = (arquivo.size / (1024 * 1024)).toFixed(2);
      const icone = getIconeArquivo(arquivo.name);
      
      return `
        <div class="flex items-center justify-between p-3 bg-gray-50 rounded border">
          <div class="flex items-center space-x-3">
            ${icone}
            <div>
              <p class="text-sm font-medium text-gray-900">${arquivo.name}</p>
              <p class="text-xs text-gray-500">${tamanhoMB} MB</p>
              <p class="text-xs text-orange-600">Aguardando envio</p>
            </div>
          </div>
          <button type="button" onclick="removerAnexo(${index})" 
                  class="text-red-600 hover:text-red-800 text-sm" title="Remover">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>
      `;
    }).join('');
  }
  
  listaAnexos.innerHTML = htmlExistente + htmlNovos;
}

// Função para obter ícone baseado no tipo de arquivo
function getIconeArquivo(nomeArquivo) {
  const extensao = nomeArquivo.split('.').pop().toLowerCase();
  
  switch (extensao) {
    case 'pdf':
      return `<svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8.267 14.68c-.184 0-.308.018-.372.036v1.178c.076.018.171.023.302.023.479 0 .774-.242.774-.651 0-.366-.254-.586-.704-.586zm3.487.012c-.2 0-.33.018-.407.036v2.61c.077.018.201.018.313.018.817.006 1.349-.444 1.349-1.396.006-.83-.479-1.268-1.255-1.268z"/>
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                <path d="M14 2v6h6"/>
              </svg>`;
    case 'doc':
    case 'docx':
      return `<svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                <path d="M14 2v6h6"/>
              </svg>`;
    case 'jpg':
    case 'jpeg':
    case 'png':
      return `<svg class="w-6 h-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                <path d="M14 2v6h6"/>
                <circle cx="10" cy="13" r="2"/>
                <path d="m20 17-1.09-1.09a2 2 0 0 0-2.82 0L10 22"/>
              </svg>`;
    default:
      return `<svg class="w-6 h-6 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6z"/>
                <path d="M14 2v6h6"/>
              </svg>`;
  }
}

// Função para remover anexo
function removerAnexo(index) {
  window.arquivosSelecionados.splice(index, 1);
  atualizarListaAnexos();
}

// Função para limpar anexos (usada ao fechar modal)
function limparAnexos() {
  window.arquivosSelecionados = [];
  const listaAnexos = document.getElementById('lista-anexos-preparo');
  if (listaAnexos) {
    listaAnexos.innerHTML = '';
  }
}

// ✅ NOVA FUNÇÃO: Processar uploads pendentes após agenda criada
async function processarUploadsPendentes(agendaId, preparosMapeados) {
  console.log('🔍 DEBUG processarUploadsPendentes - Entrada:', {
    agendaId,
    preparosMapeados,
    preparos: window.preparos?.length || 0
  });
  
  if (!window.preparos || window.preparos.length === 0) {
    console.log('📁 Nenhum preparo encontrado');
    return;
  }
  
  // Verificar se há preparos com arquivos pendentes
  const preparosComArquivos = window.preparos.filter(p => p.arquivosPendentes && p.arquivosPendentes.length > 0);
  console.log('📁 Preparos com arquivos pendentes:', preparosComArquivos.length);
  
  if (preparosComArquivos.length === 0) {
    console.log('📁 Nenhum preparo com uploads pendentes');
    return;
  }
  
  console.log('📁 Processando uploads pendentes para agenda ID:', agendaId);
  console.log('📁 Mapeamento de preparos recebido:', preparosMapeados);
  
  // Criar mapa de ID temporário para ID real
  const mapaIds = {};
  if (preparosMapeados) {
    preparosMapeados.forEach(mapping => {
      mapaIds[mapping.id_temporario] = mapping.id_real;
    });
  }
  
  for (let i = 0; i < window.preparos.length; i++) {
    const preparo = window.preparos[i];
    
    if (preparo.arquivosPendentes && preparo.arquivosPendentes.length > 0) {
      console.log(`📁 Upload pendente para preparo "${preparo.titulo}":`, preparo.arquivosPendentes.length, 'arquivos');
      
      // ✅ CORREÇÃO: Usar ID real mapeado
      const idReal = mapaIds[preparo.id] || preparo.id;
      console.log(`📝 Mapeamento ID: ${preparo.id} -> ${idReal}`);
      
      try {
        // Configurar contexto para upload
        const agendaIdOriginal = window.agendaIdAtual;
        const indexOriginal = window.preparoEditandoIndex;
        
        window.agendaIdAtual = agendaId;
        window.preparoEditandoIndex = i;
        
        // ✅ CORREÇÃO: Atualizar ID do preparo para o real antes do upload
        preparo.id = idReal;
        
        const anexosUpload = await uploadArquivos(preparo.arquivosPendentes);
        
        // Restaurar contexto
        window.agendaIdAtual = agendaIdOriginal;
        window.preparoEditandoIndex = indexOriginal;
        
        // Adicionar anexos ao preparo e limpar pendentes
        preparo.anexos = [...(preparo.anexos || []), ...anexosUpload];
        delete preparo.arquivosPendentes;
        
        console.log(`✅ Upload concluído para preparo "${preparo.titulo}":`, anexosUpload.length, 'arquivos');
        
      } catch (error) {
        console.error(`❌ Erro no upload do preparo "${preparo.titulo}":`, error);
        showToast(`Erro no upload do preparo "${preparo.titulo}": ${error.message}`, false);
        // Continue processando outros preparos mesmo se um falhar
      }
    }
  }
  
  console.log('📁 Processamento de uploads pendentes concluído');
}

// Função para fazer upload dos arquivos
async function uploadArquivos(arquivos) {
  if (!arquivos || arquivos.length === 0) {
    return [];
  }
  
  const formData = new FormData();
  
  // Adicionar arquivos ao FormData
  arquivos.forEach((arquivo, index) => {
    formData.append('anexos[]', arquivo);
  });
  
  // Adicionar dados adicionais - IDs obrigatórios
  // ✅ CORREÇÃO: Detectar se é criação ou edição
  const inputId = document.querySelector('input[name="id"]');
  let agendaId;
  
  if (inputId && inputId.value) {
    // Formulário de edição
    agendaId = inputId.value;
    console.log('📝 Upload: Detectado formulário de EDIÇÃO, agenda ID:', agendaId);
  } else if (window.agendaIdAtual) {
    // Usando ID global (se disponível)
    agendaId = window.agendaIdAtual;
    console.log('📝 Upload: Usando agendaIdAtual global:', agendaId);
  } else {
    // Formulário de criação - usar ID temporário
    agendaId = 'temp_' + Date.now();
    console.log('📝 Upload: Formulário de CRIAÇÃO, usando ID temporário:', agendaId);
  }
  
  // ✅ CORREÇÃO: Melhor lógica para determinar ID do preparo
  let preparoId;
  if (window.preparoEditandoIndex >= 0 && window.preparos && window.preparos[window.preparoEditandoIndex]) {
    // Editando preparo existente - usar ID real do banco
    const preparoExistente = window.preparos[window.preparoEditandoIndex];
    preparoId = preparoExistente.id; // ID real do banco
    console.log('📝 Upload para preparo existente ID:', preparoId);
  } else {
    // Novo preparo numa agenda de edição - o upload deve aguardar o preparo ser salvo primeiro
    if (inputId && inputId.value) {
      // Este é um novo preparo sendo criado numa agenda existente
      // O upload deveria acontecer APÓS o preparo ser salvo no banco
      throw new Error('Upload de anexos para novos preparos deve aguardar salvamento do preparo no banco');
    } else {
      // Formulário de criação de agenda - usar ID sequencial temporário
      preparoId = (window.preparos ? window.preparos.length : 0) + 1;
      console.log('📝 Upload para novo preparo em nova agenda - ID temporário:', preparoId);
    }
  }
  
  formData.append('preparo_id', preparoId);
  formData.append('agenda_id', agendaId);
  
  try {
    const response = await fetch('upload_anexo_preparo.php', {
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      throw new Error(`Erro HTTP: ${response.status}`);
    }
    
    const resultado = await response.json();
    
    if (!resultado.success) {
      throw new Error(resultado.error || 'Erro desconhecido no upload');
    }
    
    // Converter resposta para formato compatível
    return resultado.arquivos.map(arquivo => ({
      id: arquivo.id, // ID do banco de dados
      nome: arquivo.nome_original,
      tipo: arquivo.tipo,
      tamanho: arquivo.tamanho,
      arquivo_servidor: arquivo.nome_arquivo
    }));
    
  } catch (error) {
    console.error('Erro no upload:', error);
    throw error;
  }
}

// Função para buscar anexos do banco e abrir modal
// Função para baixar anexo (se não existir)
window.baixarAnexo = function(anexoId) {
    window.open('download_anexo.php?id=' + anexoId, '_blank');
}

window.abrirModalPreparoDetalhesComAnexos = async function(titulo, instrucoes, preparoId) {
  try {
    // Buscar anexos do banco
    const agendaId = window.agendaIdAtual || 
                    document.querySelector('input[name="id"]')?.value || 
                    1;
    const response = await fetch(`buscar_anexos_preparo.php?agenda_id=${agendaId}&preparo_id=${preparoId}`);
    
    if (!response.ok) {
      throw new Error(`Erro HTTP: ${response.status}`);
    }
    
    const resultado = await response.json();
    
    if (!resultado.success) {
      console.error('Erro ao buscar anexos:', resultado.error);
      // Abrir modal sem anexos se houver erro
      if (typeof window.abrirModalPreparoDetalhes === 'function') {
        window.abrirModalPreparoDetalhes(titulo, instrucoes, []);
      }
      return;
    }
    
    // Abrir modal com anexos do banco
    if (typeof window.abrirModalPreparoDetalhes === 'function') {
      window.abrirModalPreparoDetalhes(titulo, instrucoes, resultado.anexos || []);
    }
    
  } catch (error) {
    console.error('Erro ao buscar anexos:', error);
    // Abrir modal sem anexos se houver erro
    if (typeof window.abrirModalPreparoDetalhes === 'function') {
      window.abrirModalPreparoDetalhes(titulo, instrucoes, []);
    }
  }
}

// Função para mostrar formulário de bloqueio com agenda pré-selecionada
function mostrarFormularioBloqueio(tipo, agendaId, nomeAgenda) {
  console.log('🔧 Abrindo formulário:', tipo, 'Agenda ID:', agendaId, 'Nome:', nomeAgenda);
  
  mostrarFormulario(tipo);
  
  // Definir agenda selecionada no formulário
  setTimeout(() => {
    const agendaDisplay = document.getElementById(`agenda-selecionada-${tipo}`);
    const agendaInput = document.getElementById(`agenda-id-${tipo}`);
    
    if (agendaDisplay && agendaInput) {
      agendaDisplay.textContent = nomeAgenda;
      agendaInput.value = agendaId;
      console.log('✅ Agenda definida:', nomeAgenda, 'ID:', agendaId);
      
      // Carregar bloqueios existentes para esta agenda
      carregarBloqueiosAgenda(agendaId, tipo);
    }
  }, 100);
}

// Função para carregar bloqueios de uma agenda específica
function carregarBloqueiosAgenda(agendaId, tipo) {
  const tipoBloqueio = tipo === 'bloquearDia' ? 'DIA' : 
                      tipo === 'bloquearAgenda' ? 'AGENDA' : 'HORARIO';
  
  fetch(`buscar_bloqueios.php?agenda_id=${agendaId}&tipo=${tipoBloqueio}`)
    .then(response => response.text())
    .then(html => {
      const container = document.getElementById(`tabela-bloqueios-${tipo.replace('bloquear', '').toLowerCase()}`);
      if (container) {
        container.innerHTML = html;
      }
    })
    .catch(error => {
      console.error('Erro ao carregar bloqueios:', error);
      const container = document.getElementById(`tabela-bloqueios-${tipo.replace('bloquear', '').toLowerCase()}`);
      if (container) {
        container.innerHTML = '<div class="text-center text-red-500 py-4">Erro ao carregar bloqueios</div>';
      }
    });
}

// Função para carregar opções de agenda no select
function carregarOpcoesAgenda(selectElement, agendaIdSelecionada = null) {
  fetch('buscar_agendas_select.php')
    .then(response => response.json())
    .then(agendas => {
      selectElement.innerHTML = '<option value="">Selecione uma agenda</option>';
      agendas.forEach(agenda => {
        const option = new Option(agenda.nome, agenda.id);
        if (agenda.id == agendaIdSelecionada) {
          option.selected = true;
        }
        selectElement.appendChild(option);
      });
    })
    .catch(error => {
      console.error('Erro ao carregar agendas:', error);
    });
}

// Função para esconder formulários
function esconderFormulario(tipo) {
  document.getElementById('form-' + tipo)?.classList.add('hidden');
  document.getElementById('bloco-listagem')?.classList.remove('hidden');
}

// Função para bloquear dia
function bloquearDia(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  fetch('processar_bloqueio.php?acao=bloquear_dia', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'sucesso') {
      showToast('Dia bloqueado com sucesso!', true);
      form.reset();
      // Recarregar tabela de bloqueios
      const agendaId = document.getElementById('agenda-id-bloquearDia').value;
      carregarBloqueiosAgenda(agendaId, 'bloquearDia');
    } else {
      showToast(data.mensagem || 'Erro ao bloquear dia', false);
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    showToast('Erro ao processar bloqueio', false);
  });
}

// Função para bloquear agenda
function bloquearAgenda(event) {
  event.preventDefault();
  const form = event.target;
  const formData = new FormData(form);
  
  fetch('processar_bloqueio.php?acao=bloquear_agenda', {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'sucesso') {
      showToast('Agenda bloqueada com sucesso!', true);
      form.reset();
      // Recarregar tabela de bloqueios
      const agendaId = document.getElementById('agenda-id-bloquearAgenda').value;
      carregarBloqueiosAgenda(agendaId, 'bloquearAgenda');
    } else {
      showToast(data.mensagem || 'Erro ao bloquear agenda', false);
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    showToast('Erro ao processar bloqueio', false);
  });
}

// Função para bloquear horário
function bloquearHorario(event, agendaId, data, horario) {
  // Se foi chamado com event (forma antiga)
  if (event && event.preventDefault) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    fetch('processar_bloqueio.php?acao=bloquear_horario', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.status === 'sucesso') {
        showToast('Horário bloqueado com sucesso!', true);
        form.reset();
      } else {
        showToast('Erro: ' + data.mensagem, false);
      }
    })
    .catch(error => {
      console.error('Erro:', error);
      showToast('Erro ao processar bloqueio', false);
    });
    return;
  }
  
  // Se foi chamado com parâmetros (forma nova): bloquearHorario(agendaId, data, horario)
  if (typeof event === 'string' || typeof event === 'number') {
    // Reorganizar parâmetros
    const realAgendaId = event;
    const realData = agendaId; 
    const realHorario = data;
    
    const dataFormatada = formatarDataBR ? formatarDataBR(realData) : realData;
    
    if (confirm(`Deseja bloquear o horário ${realHorario} do dia ${dataFormatada}?`)) {
      console.log('🔒 Bloqueando horário:', { agendaId: realAgendaId, data: realData, horario: realHorario });
      
      if (typeof showToast === 'function') {
        showToast('Bloqueando horário...', true, 2000);
      }
      
      const formData = new FormData();
      formData.append('agenda_id', realAgendaId);
      formData.append('data_agendamento', realData);
      formData.append('horario_agendamento', realHorario.includes && realHorario.includes(':') && realHorario.length === 5 ? realHorario + ':00' : realHorario);
      formData.append('acao', 'bloquear');
      
      fetch('bloquear_horario.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.status === 'sucesso') {
          if (typeof showToast === 'function') {
            showToast('Horário bloqueado com sucesso!', true);
          }
          // Recarregar visualização se disponível
          if (typeof carregarVisualizacaoDia === 'function' && window.agendaIdAtual && window.dataSelecionadaAtual) {
            carregarVisualizacaoDia(window.agendaIdAtual, window.dataSelecionadaAtual);
          }
        } else {
          if (typeof showToast === 'function') {
            showToast('Erro: ' + (data.mensagem || 'Erro ao bloquear horário'), false);
          }
        }
      })
      .catch(error => {
        console.error('Erro ao bloquear horário:', error);
        if (typeof showToast === 'function') {
          showToast('Erro ao processar bloqueio', false);
        }
      });
    }
    return;
  }
}

// Função para desbloquear item específico
function desbloquearItem(bloqueioId, tipoBloqueio, agendaId) {
  if (!confirm(`Tem certeza que deseja excluir este bloqueio (${tipoBloqueio})?`)) {
    return;
  }
  
  fetch('processar_bloqueio.php?acao=desbloquear', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `bloqueio_id=${bloqueioId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.status === 'sucesso') {
      showToast('Bloqueio removido com sucesso!', true);
      // Recarregar apenas a tabela de bloqueios
      const tipo = tipoBloqueio === 'DIA' ? 'bloquearDia' : 
                   tipoBloqueio === 'HORARIO' ? 'bloquearHorario' : 'bloquearAgenda';
      carregarBloqueiosAgenda(agendaId, tipo);
    } else {
      showToast(data.mensagem || 'Erro ao remover bloqueio', false);
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    showToast('Erro ao processar remoção', false);
  });
}

// Função para mostrar/esconder campos de data baseado no período selecionado
function toggleCamposTempo(select) {
  const periodoDiv = document.getElementById('periodo-temporario');
  const dataInicio = document.getElementById('data_inicio_agenda');
  const dataFim = document.getElementById('data_fim_agenda');
  
  if (select.value === 'temporario') {
    periodoDiv.classList.remove('hidden');
    dataInicio.required = true;
    dataFim.required = true;
  } else {
    periodoDiv.classList.add('hidden');
    dataInicio.required = false;
    dataFim.required = false;
    dataInicio.value = '';
    dataFim.value = '';
  }
}

// ================================
// INICIALIZAÇÃO AUTOMÁTICA
// ================================

// Inicializar automaticamente quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', function() {
  // Aguardar um pouco para garantir que todos os scripts carreguem
  setTimeout(() => {
    // Verificar se estamos em um formulário que usa preparos
    const modalPreparo = document.getElementById('modal-preparo');
    const listaPreparos = document.getElementById('lista-preparos');
    
    if (modalPreparo || listaPreparos) {
      console.log('🔧 Inicializando sistema de preparos automaticamente');
      
      // Garantir que as variáveis globais existam
      if (!window.preparos) window.preparos = [];
      if (typeof window.preparoEditandoIndex === 'undefined') window.preparoEditandoIndex = -1;
      if (!window.arquivosSelecionados) window.arquivosSelecionados = [];
      
      // Inicializar preparos se a função existir
      if (typeof inicializarPreparos === 'function') {
        inicializarPreparos();
      }
    }
  }, 100);
});

// ================================
// SISTEMA DE DOWNLOAD DE ANEXOS
// ================================

// Função para baixar anexo salvo
window.baixarAnexo = function(anexoId) {
  if (!anexoId) {
    alert('ID do anexo não encontrado');
    return;
  }
  
  // Criar link temporário para download
  const link = document.createElement('a');
  link.href = `download_anexo_preparo.php?id=${anexoId}`;
  link.download = ''; // Deixar o servidor definir o nome do arquivo
  link.target = '_blank';
  
  // Simular clique para iniciar download
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Função para verificar status do anexo
window.verificarStatusAnexo = function(anexoId) {
  fetch(`verificar_anexo_preparo.php?id=${anexoId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        console.log('Anexo encontrado:', data.anexo);
      } else {
        console.error('Anexo não encontrado:', data.error);
      }
    })
    .catch(error => {
      console.error('Erro ao verificar anexo:', error);
    });
}

// ================================
// SISTEMA DE NOTIFICAÇÕES
// ================================

// Função para mostrar notificação (toast)
window.showToast = function(message, isSuccess = true) {
  // Remover toasts existentes
  const existingToasts = document.querySelectorAll('.toast-notification');
  existingToasts.forEach(toast => toast.remove());
  
  // Criar elemento toast
  const toast = document.createElement('div');
  toast.className = `toast-notification fixed bottom-4 right-4 z-50 px-4 py-3 rounded-lg shadow-lg text-white max-w-sm ${
    isSuccess ? 'bg-green-500' : 'bg-red-500'
  }`;
  
  toast.innerHTML = `
    <div class="flex items-center gap-2">
      <div class="flex-shrink-0">
        ${isSuccess 
          ? '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
          : '<svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>'
        }
      </div>
      <div class="flex-1 text-sm font-medium">${message}</div>
      <button onclick="this.parentElement.parentElement.remove()" class="flex-shrink-0 text-white hover:text-gray-200">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
        </svg>
      </button>
    </div>
  `;
  
  // Adicionar ao body
  document.body.appendChild(toast);
  
  // Auto remover após 5 segundos
  setTimeout(() => {
    if (toast && toast.parentElement) {
      toast.remove();
    }
  }, 5000);
  
  // Animação de entrada
  setTimeout(() => {
    toast.style.transform = 'translateX(0)';
    toast.style.opacity = '1';
  }, 10);
}

