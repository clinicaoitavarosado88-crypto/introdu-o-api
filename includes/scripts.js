/**
 * ================================
 * DROPDOWN DE CLÍNICA (ABRIR POR CLIQUE)
 * ================================
 * 
 * Esse script faz duas coisas:
 * 1. Define o nome da clínica com base no parâmetro da URL (?cidade=)
 * 2. Controla o dropdown de seleção de clínica (abre/fecha ao clicar)
 */

document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('clinicaBtn');               // Botão para abrir dropdown
  const dropdown = document.getElementById('clinicaDropdown');     // Menu dropdown com as clínicas
  const clinicaAtual = document.getElementById('clinicaAtual');    // Onde exibir o nome da clínica atual

  // Mapeia os IDs da URL para o nome da clínica correspondente
  const cidadeMap = {
    1: "Clínica - Mossoró",
    2: "Clínica - Natal",
    3: "Clínica - Parnamirim",
    4: "Clínica - Baraúna",
    5: "Clínica - Assú",
    8: "Clínica - Santo Antônio",
    13: "Clínica - Alto do Rodrigues",
    14: "Clínica - Extremoz",
  };

  // Lê o parâmetro ?cidade= da URL e define o nome da clínica
  const params = new URLSearchParams(window.location.search);
  const cidadeId = params.get('cidade') || '1';

  // Se não houver cidade na URL, adiciona o padrão (Mossoró)
  if (!params.has('cidade')) {
    const url = new URL(window.location);
    url.searchParams.set('cidade', '1');
    window.history.replaceState({}, '', url);
  }

  if (clinicaAtual) {
    clinicaAtual.textContent = cidadeMap[cidadeId] || "Clínica - Mossoró";
  }

  // Toggle do dropdown ao clicar no botão
  if (btn && dropdown) {
    btn.addEventListener('click', function (e) {
      e.stopPropagation(); // Evita que o clique feche imediatamente o menu
      dropdown.classList.toggle('hidden');
    });
  }

  // Fecha o dropdown se clicar fora dele
  document.addEventListener('click', function (e) {
    if (!dropdown.contains(e.target)) {
      dropdown.classList.add('hidden');
    }
  });

  // Sistema de detecção de mudanças não é mais necessário
  // A troca de cidade agora é instantânea via trocarCidade()
});

// Função para trocar cidade sem refresh
function trocarCidade(cidadeId, nomeClinica) {
  // Atualiza a URL sem recarregar a página
  const url = new URL(window.location);
  url.searchParams.set('cidade', cidadeId);
  window.history.pushState({}, '', url);
  
  // Atualiza o texto da clínica atual
  const clinicaAtual = document.getElementById('clinicaAtual');
  if (clinicaAtual) {
    clinicaAtual.textContent = nomeClinica;
  }
  
  // Fecha o dropdown
  const dropdown = document.getElementById('clinicaDropdown');
  if (dropdown) {
    dropdown.classList.add('hidden');
  }
  
  // Exibe toast de confirmação da mudança de cidade
  if (typeof showToast === 'function') {
    showToast(`📍 Cidade alterada para: ${nomeClinica}`, true);
  }
  
  // Recarrega as agendas se estivermos visualizando alguma especialidade/procedimento
  if (window.currentTipo && window.currentNome) {
    carregarListagemAgendas(window.currentTipo, window.currentNome);
  }
  
  // Recarrega o gerenciamento de agendas se estivermos na página de configurações
  if (document.getElementById('tabelaAgendas')) {
    carregarPagina(1);
  }
}


/**
 * ================================
 * SIDEBAR COM SUBMENUS (TOGGLE + AUTOFECHAR)
 * ================================
 * 
 * Esse script:
 * 1. Gerencia a exibição dos submenus do sidebar
 * 2. Fecha automaticamente os submenus ao clicar fora ou sair com o mouse
 */

let sidebar = document.querySelector('aside');                    // Elemento da sidebar principal
let submenus = document.querySelectorAll('[id^="submenu-"]');     // Todos os submenus com ID iniciado em 'submenu-'

let isMouseInside = false; // Flag de controle para saber se o mouse está dentro do sidebar

// Marca quando o mouse entra no sidebar
sidebar.addEventListener('mouseenter', () => isMouseInside = true);

// Quando o mouse sai da sidebar, fecha todos os submenus
sidebar.addEventListener('mouseleave', () => {
  isMouseInside = false;
  closeAllSubmenus();
});

/**
 * Alterna a visibilidade de um submenu específico e fecha os demais
 * @param {string} id - ID do submenu a ser exibido
 */
function toggleSubmenu(id) {
  submenus.forEach(menu => {
    if (menu.id !== id) menu.classList.add('hidden'); // Fecha os outros submenus
  });

  const submenu = document.getElementById(id);
  if (submenu) submenu.classList.toggle('hidden'); // Exibe ou oculta o submenu clicado
}

/**
 * Fecha todos os submenus
 */
function closeAllSubmenus() {
  submenus.forEach(menu => menu.classList.add('hidden'));
}

// Fecha todos os submenus se o clique for fora da sidebar
document.addEventListener('click', function (e) {
  if (!e.target.closest('aside')) {
    closeAllSubmenus();
  }
});


// Carrega o conteúdo inicial da especialidade ou procedimento (nome + tipo)
function carregarConteudo(tipo, nome) {
  const conteudoDiv = document.getElementById('conteudo-dinamico');
  const loader = document.getElementById('loader');

  // Armazena as variáveis globalmente para uso posterior
  window.currentTipo = tipo;
  window.currentNome = nome;

  loader.classList.remove('hidden');

  fetch(`carregar_conteudo.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}`)
    .then(res => res.text())
    .then(html => {
      conteudoDiv.innerHTML = html;

      // ✅ Carrega a listagem completa SEM filtro de dia
      carregarListagemAgendas(tipo, nome);

      // ✅ Carrega os dias disponíveis
      carregarDiasDisponiveis(tipo, nome);
    })
    .catch(() => {
      conteudoDiv.innerHTML = '<div class="text-red-600">Erro ao carregar conteúdo.</div>';
    })
    .finally(() => {
      loader.classList.add('hidden');
    });
}

// Função para obter a cidade selecionada da URL
function getCidadeSelecionada() {
  const urlParams = new URLSearchParams(window.location.search);
  return urlParams.get('cidade') || '1'; // Padrão: Mossoró (ID 1)
}


// Carrega os médicos daquele tipo e nome, filtrando por dia (clicado)
function carregarMedicosPorDia(tipo, nome, dia) {
  const destino = document.getElementById('medicos-por-dia');
  destino.innerHTML = 'Carregando...';

  const cidadeId = getCidadeSelecionada();
  let url = `listar_agendas.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}&dia=${encodeURIComponent(dia)}`;
  if (cidadeId) {
    url += `&cidade=${cidadeId}`;
  }

  fetch(url)
    .then(res => {
      if (!res.ok) throw new Error('Erro na requisição');
      return res.text();
    })
    .then(html => destino.innerHTML = html)
    .catch(error => {
      console.error('Erro ao carregar agendas:', error);
      destino.innerHTML = `
        <div class="text-center p-8">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900 mb-4">
            <span class="text-4xl">⚠️</span>
          </div>
          <p class="text-lg text-red-600 dark:text-red-400 mb-2">Erro ao carregar informações</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Tente novamente ou entre em contato com o suporte.</p>
          <button onclick="carregarMedicosPorDia('${tipo}', '${nome}', '${dia}')"
                  class="mt-4 px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">
            🔄 Tentar Novamente
          </button>
        </div>
      `;
    });
}

// Carrega os dias disponíveis da especialidade/procedimento
function carregarDiasDisponiveis(tipo, nome) {
  const div = document.getElementById('dias-disponiveis');
  div.innerHTML = 'Carregando dias disponíveis...';

  fetch(`dias_disponiveis.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}`)
    .then(r => r.text())
    .then(html => div.innerHTML = html)
    .catch(() => div.innerHTML = '<div class="text-red-600">Erro ao carregar dias.</div>');
}

// Carrega todas as agendas da especialidade/procedimento (sem filtro de dia)
function carregarListagemAgendas(tipo, nome) {
  const div = document.getElementById('medicos-por-dia');

  // Verificar se o elemento existe antes de tentar modificá-lo
  if (!div) {
    console.warn('Elemento com ID "medicos-por-dia" não encontrado na página');
    return;
  }

  div.innerHTML = 'Carregando agendas...';

  const cidadeId = getCidadeSelecionada();
  let url = `listar_agendas.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}&dia=`;
  if (cidadeId) {
    url += `&cidade=${cidadeId}`;
  }

  fetch(url)
    .then(res => {
      if (!res.ok) throw new Error('Erro na requisição');
      return res.text();
    })
    .then(html => div.innerHTML = html)
    .catch(error => {
      console.error('Erro ao carregar agendas:', error);
      div.innerHTML = `
        <div class="text-center p-8">
          <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900 mb-4">
            <span class="text-4xl">⚠️</span>
          </div>
          <p class="text-lg text-red-600 dark:text-red-400 mb-2">Erro ao carregar informações</p>
          <p class="text-sm text-gray-500 dark:text-gray-400">Verifique sua conexão ou entre em contato com o suporte.</p>
          <button onclick="carregarListagemAgendas('${tipo}', '${nome}')"
                  class="mt-4 px-4 py-2 bg-teal-600 text-white rounded hover:bg-teal-700">
            🔄 Tentar Novamente
          </button>
        </div>
      `;
    });
}


function selecionarDia(botao, tipo, nome, dia) {
  // Remove destaque de todos os botões
  document.querySelectorAll('#filtros-dias button').forEach(btn => {
    btn.classList.remove('bg-teal-500', 'text-white');
    btn.classList.add('bg-gray-100');
  });

  // Adiciona destaque no botão clicado
  botao.classList.remove('bg-gray-100');
  botao.classList.add('bg-teal-500', 'text-white');

  // Atualiza o texto "Dia selecionado"
  const selecionado = document.getElementById('dia-selecionado');
  selecionado.textContent = dia ? `Dia selecionado: ${dia}` : 'Dia selecionado: Todos os dias';

  // Carrega os médicos do dia
  carregarMedicosPorDia(tipo, nome, dia);
}


function alterarSituacao(botao, id, novaSituacao) {
  if (!confirm('Deseja realmente alterar a situação desta agenda?')) return;

  fetch('atualizar_situacao_agenda.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `agenda_id=${id}&nova_situacao=${novaSituacao}`
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'sucesso') {
      alert(data.mensagem);
      
      // Recarregar a página para atualizar a listagem
      carregarPagina(1);
    } else {
      alert('Erro: ' + data.mensagem);
    }
  })
  .catch(error => {
    console.error('Erro:', error);
    alert('Erro ao alterar situação da agenda');
  });
}

function carregarPagina(pagina = 1) {
  const buscaInput = document.getElementById('campoBusca');
  const busca = buscaInput ? buscaInput.value : '';
  const select = document.getElementById('itensPorPagina');
  const itens = select ? select.value : 10;
  
  // Obter cidade da URL usando o mesmo sistema do header
  const cidadeId = getCidadeSelecionada() || '1';

  fetch(`listar_agendas_ajax.php?cidade=${cidadeId}&pagina=${pagina}&limite=${itens}&busca=${encodeURIComponent(busca)}`)
    .then(res => res.text())
    .then(html => {
      const tabelaElement = document.getElementById('tabelaAgendas');
      if (tabelaElement) {
        tabelaElement.innerHTML = html;
        carregarPaginacao(pagina, busca);
      } else {
        console.error('Elemento tabelaAgendas não encontrado na página');
      }
    });
}

function carregarPaginacao(paginaAtual = 1, busca = '') {
  const select = document.getElementById('itensPorPagina');
  const itens = select ? select.value : 10;
  
  // Obter cidade da URL usando o mesmo sistema do header
  const cidadeId = getCidadeSelecionada() || '1';

  fetch(`listar_paginas.php?cidade=${cidadeId}&paginaAtual=${paginaAtual}&limite=${itens}&busca=${encodeURIComponent(busca)}`)
    .then(res => res.text())
    .then(html => {
      const pagRodape = document.getElementById('paginacaoRodape');
      if (pagRodape) pagRodape.innerHTML = html;
    });
}


document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('itensPorPagina')) {
    carregarPagina();
  }
});


  function toggleDropdown(btn) {
    const allMenus = document.querySelectorAll('.dropdown-menu');
    allMenus.forEach(menu => menu.classList.add('hidden'));

    const dropdown = btn.nextElementSibling;
    dropdown.classList.toggle('hidden');
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('td')) {
      document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
    }
  });

function voltarParaTabela() {
  document.getElementById('form-criar')?.classList.add('hidden');
  document.getElementById('campoBusca')?.parentElement.classList.remove('hidden');
  document.getElementById('tabelaAgendas')?.closest('div').classList.remove('hidden');
  document.getElementById('paginacaoRodape')?.classList.remove('hidden');
  document.getElementById('itensPorPagina')?.parentElement?.parentElement?.classList.remove('hidden');
}


function ativarValidacao() {
  const campos = Array.from(document.querySelectorAll('.campo-obrigatorio'))
    .filter(campo => {
      const style = window.getComputedStyle(campo);
      const hiddenBySelf = style.display === 'none' || style.visibility === 'hidden';
      const hiddenByParent = campo.closest('[style*="display: none"]') !== null;

      return !hiddenBySelf && !hiddenByParent;
    });



  campos.forEach(campo => {
    // Ao sair do campo (perder o foco), valida se está vazio
    campo.addEventListener('blur', () => {
      if (campo.value.trim() === '') aplicarErro(campo);
    });

    // Ao digitar, remove erro
    let timer;
    campo.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => limparErro(campo), 300);
    });

  });

  function aplicarErro(campo) {
    if (!campo.classList.contains('border-red-600')) {
      campo.classList.add('border-red-600');

      // Evita duplicar mensagens de erro
      if (!campo.parentElement.querySelector('.msg-erro')) {
        const erro = document.createElement('small');
        erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
        erro.textContent = campo.dataset.erro || 'Campo obrigatório';
        campo.parentElement.appendChild(erro);
      }
    }
  }

  function limparErro(campo) {
    campo.classList.remove('border-red-600');
    const erro = campo.parentElement.querySelector('.msg-erro');
    if (erro) erro.remove();
  }
}


function validarCamposObrigatorios() {
  let valido = true;

const campos = Array.from(document.querySelectorAll('.campo-obrigatorio'))
  .filter(campo => {
    const style = window.getComputedStyle(campo);
    const hiddenBySelf = style.display === 'none' || style.visibility === 'hidden';
    const hiddenByParent = campo.closest('[style*="display: none"]') !== null;

    return !hiddenBySelf && !hiddenByParent;
  });

  //console.log('🕵️ Campos visíveis encontrados:', campos.length);

  campos.forEach(campo => {
    const valor = campo.value?.trim();
    const erroExistente = campo.parentElement.querySelector('.msg-erro');

    //console.log('➡️ Validando campo:', campo.name || campo.id, '→', valor);
    if (!valor) {
      //console.warn('❌ Campo vazio (visível):', campo.name || campo.id, campo);
    }


    if (!valor) {
      campo.classList.add('border-red-600');

      if (!erroExistente) {
        const erro = document.createElement('small');
        erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
        erro.textContent = campo.dataset.erro || 'Campo obrigatório';
        campo.parentElement.appendChild(erro);
      }

      valido = false;
    }
  });

  // Radios (tipo_agenda)
  const radios = document.querySelectorAll('input[name="tipo_agenda"]');
  const wrapper = document.getElementById("tipo-agenda-wrapper");

  if (![...radios].some(r => r.checked)) {
    wrapper.classList.add('border', 'border-red-600', 'rounded', 'p-2');
    if (!wrapper.querySelector('.msg-erro')) {
      const erro = document.createElement('small');
      erro.textContent = 'Selecione o tipo de agenda';
      erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
      wrapper.appendChild(erro);
    }
    //console.log('❌ Falta marcar tipo de agenda');
    valido = false;
  }

  // Checkboxes (dias[])
  const checkboxes = document.querySelectorAll('input[name="dias[]"]');
  const algumDiaMarcado = [...checkboxes].some(cb => cb.checked);
  const erroDias = document.getElementById("erro-dias");

  if (!algumDiaMarcado) {
    if (erroDias) erroDias.classList.remove("hidden");
    //console.log('❌ Nenhum dia selecionado');
    valido = false;
  } else {
    if (erroDias) erroDias.classList.add("hidden");
  }

  return valido;
}




document.addEventListener('change', function (e) {
  if (e.target.id === 'tipo_agendamento') {
    const tipo = e.target.value;
    const campoPrestador = document.getElementById('campo-prestador');
    const campoProcedimento = document.getElementById('campo-procedimento');
    const selectPrestador = document.getElementById('select-prestador');
    const prestadorObrigatorio = document.getElementById('prestador-obrigatorio');

    if (tipo === 'consulta') {
      // CONSULTA: mostra prestador (obrigatório) e esconde procedimento
      campoPrestador.style.display = 'block';
      campoProcedimento.style.display = 'none';
      
      // Tornar prestador obrigatório
      if (selectPrestador) {
        selectPrestador.classList.add('campo-obrigatorio');
        selectPrestador.setAttribute('required', 'required');
      }
      if (prestadorObrigatorio) {
        prestadorObrigatorio.style.display = 'inline';
      }
      
      const blocoConsulta = document.getElementById('bloco-consulta');
      if (blocoConsulta) blocoConsulta.style.display = 'block';
      
    } else if (tipo === 'procedimento') {
      // PROCEDIMENTO: mostra prestador (opcional) e procedimento (obrigatório)
      campoPrestador.style.display = 'block';
      campoProcedimento.style.display = 'block';
      
      // Tornar prestador opcional
      if (selectPrestador) {
        selectPrestador.classList.remove('campo-obrigatorio');
        selectPrestador.removeAttribute('required');
      }
      if (prestadorObrigatorio) {
        prestadorObrigatorio.style.display = 'none';
      }
      
      const blocoConsulta = document.getElementById('bloco-consulta');
      if (blocoConsulta) blocoConsulta.style.display = 'none';
      
    } else {
      // NENHUM TIPO SELECIONADO: esconde ambos
      campoPrestador.style.display = 'none';
      campoProcedimento.style.display = 'none';
      
      const blocoConsulta = document.getElementById('bloco-consulta');
      if (blocoConsulta) blocoConsulta.style.display = 'none';
    }
  }
});



function ativarValidacaoTipoAgenda() {
  const wrapper = document.getElementById('tipo-agenda-wrapper');
  const radios = document.querySelectorAll('input[name="tipo_agenda"]');

  if (!wrapper || radios.length === 0) return;

  radios.forEach(radio => {
    radio.addEventListener('change', () => {
      wrapper.classList.remove('border', 'border-red-600', 'rounded', 'p-2');
      const erro = wrapper.querySelector('.msg-erro');
      if (erro) erro.remove();
    });
  });

  wrapper.addEventListener('blur', () => {
    const selecionado = Array.from(radios).some(r => r.checked);
    if (!selecionado) {
      wrapper.classList.add('border', 'border-red-600', 'rounded', 'p-2');
      if (!wrapper.querySelector('.msg-erro')) {
        const erro = document.createElement('small');
        erro.textContent = 'Selecione o tipo de agenda';
        erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
        wrapper.appendChild(erro);
      }
    }
  }, true);
}


function ativarHorariosPorDia() {
  const selectsMap = {};

  // Agrupa os selects por dia
  document.querySelectorAll('.select-horario').forEach(sel => {
    const dia = sel.dataset.dia;
    if (!selectsMap[dia]) selectsMap[dia] = [];
    selectsMap[dia].push(sel);
  });

  // Para cada checkbox, aplica estado inicial e adiciona listener
  document.querySelectorAll('.dia-checkbox').forEach(chk => {
    const dia = chk.dataset.dia;
    const relacionados = selectsMap[dia] || [];

    // Estado inicial
    relacionados.forEach(sel => {
      sel.disabled = !chk.checked;
      if (!chk.checked) sel.value = '';
    });

    // Ao alterar
    chk.addEventListener('change', () => {
      relacionados.forEach(sel => {
        sel.disabled = !chk.checked;
        if (!chk.checked) sel.value = '';
      });
    });
  });
}



const grupoDias = document.querySelectorAll('.dia-checkbox');
const containerDias = document.querySelector('#dias-disponiveis');

containerDias?.addEventListener('blur', () => {
  const algumMarcado = Array.from(grupoDias).some(cb => cb.checked);
  if (!algumMarcado) {
    if (!containerDias.querySelector('.msg-erro')) {
      const erro = document.createElement('small');
      erro.textContent = 'Selecione pelo menos um dia de atendimento';
      erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
      containerDias.appendChild(erro);
    }
  }
}, true);


// TEMPORARIAMENTE COMENTADO PARA TESTAR INTERFERÊNCIA COM VAGAS
/*
document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
  checkbox.addEventListener('change', function () {
    const slug = this.value.toLowerCase().substring(0, 3);
    const inputs = document.querySelectorAll(`.horario-input[data-dia="${slug}"]`);
    
    inputs.forEach(input => {
      input.disabled = !this.checked;
      input.classList.toggle('bg-white', this.checked);
      input.classList.toggle('bg-gray-100', !this.checked);
    });
  });
});
*/

function filtrarTabela(valor) {
  const linhas = document.querySelectorAll("#tabelaAgendas tr");
  valor = valor.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");

  if (valor.length < 2) {
    linhas.forEach(linha => linha.style.display = '');
    return;
  }

  linhas.forEach(linha => {
    const texto = linha.innerText.toLowerCase().normalize("NFD").replace(/[̀-ͯ]/g, "");
    linha.style.display = texto.includes(valor) ? '' : 'none';
  });
}


// Aplica ícone na busca + botão "Limpar" fixo fora do scroll
$(document).on('select2:open', () => {
  setTimeout(() => {
    // Ícone de lupa no placeholder
    const searchInput = document.querySelector('.select2-search__field');
    if (searchInput) {
      searchInput.placeholder = "🔍 Pesquise...";
      searchInput.classList.add('text-base', 'px-3', 'py-2');
    }

    // Botão "Limpar" fixo se não existir
    if (!document.querySelector('.select2-clear-wrapper')) {
      const limparBtn = document.createElement('div');
      limparBtn.className = 'select2-clear-wrapper px-4 py-2 border-t border-gray-200 text-center text-red-600 cursor-pointer text-sm bg-white';
      limparBtn.innerText = 'Limpar';

      limparBtn.addEventListener('click', () => {
        const openSelect = $('.select2-container--open').prev('select');
        if (openSelect.length > 0) {
          openSelect.val(null).trigger('change');

          try {
            openSelect.select2('close'); // fecha apenas se for um select2 válido
          } catch (e) {
            console.warn('Elemento não está usando Select2');
          }
        }

        $('.select2-container--open .select2-search__field').val('').trigger('input');
      });


      // Insere fora da rolagem, abaixo do .select2-results
      const dropdown = document.querySelector('.select2-dropdown');
      dropdown?.appendChild(limparBtn);
    }
  }, 0);
});



function inicializarListenersAgenda() {
  const tipoAgendamento = document.getElementById('tipo_agendamento');
  const blocoConsulta = document.getElementById('bloco-consulta');
  const campoQtdRetornos = document.getElementById('qtd-retornos');
  const campoRetorno = document.querySelector("select[name='possui_retorno']");

  if (!tipoAgendamento || !blocoConsulta || !campoQtdRetornos) return;

  if (tipoAgendamento.value === 'consulta') {
    blocoConsulta.style.display = 'block';
    campoQtdRetornos.style.display = campoRetorno?.value === '1' ? 'block' : 'none';
  } else {
    blocoConsulta.style.display = 'none';
    campoQtdRetornos.style.display = 'none';
  }

  tipoAgendamento.addEventListener('change', function () {
    if (this.value === 'consulta') {
      blocoConsulta.style.display = 'block';

      if (campoRetorno) {
        campoQtdRetornos.style.display = campoRetorno.value === '1' ? 'block' : 'none';

        campoRetorno.addEventListener('change', function () {
          campoQtdRetornos.style.display = this.value === '1' ? 'block' : 'none';
        });
      }
    } else {
      blocoConsulta.style.display = 'none';
      campoQtdRetornos.style.display = 'none';
    }
  });
}

function inicializarConsultaRetorno() {
  const tipoAgendamento = document.getElementById('tipo_agendamento');
  const blocoConsulta = document.getElementById('bloco-consulta');

  if (!tipoAgendamento || !blocoConsulta) return;

  // Mostrar ou ocultar baseado no valor inicial
  blocoConsulta.style.display = tipoAgendamento.value === 'consulta' ? 'block' : 'none';

  tipoAgendamento.addEventListener('change', function () {
    blocoConsulta.style.display = this.value === 'consulta' ? 'block' : 'none';
  });
}


function adicionarConvenio(convenio = {}) {
  fetch('get_convenios.php')
    .then(res => res.json())
    .then(convenios => {
      let options = `<option value="">Selecione</option>`;
      convenios.forEach(c => {
        const selected = c.id == convenio.id ? 'selected' : '';
        options += `<option value="${c.id}" ${selected}>${c.nome}</option>`;
      });

      const grupoHTML = `
        <div class="grupo-convenio grid grid-cols-1 md:grid-cols-12 gap-4 items-center mb-4 border-t pt-4">
          <div class="md:col-span-4">
            <label class="block text-sm font-medium text-gray-700">Convênio <span class="text-red-500">*</span></label>
            <select name="convenio_id[]" class="select2-tailwind campo-obrigatorio w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm">
              ${options}
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Limite de atendimentos</label>
            <input type="number" name="limite_atendimentos[]" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm" value="${convenio.limite || ''}">
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Qtd. de retornos</label>
            <input type="number" name="qtd_retornos[]" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm" value="${convenio.retornos || ''}">
          </div>
          <div class="md:col-span-2 flex justify-center items-center h-full">
            <button type="button" class="text-red-500 font-bold hover:underline text-sm w-full text-center" onclick="removerConvenio(this)">Remover</button>
          </div>
        </div>`;

      document.getElementById('container-convenios').insertAdjacentHTML('beforeend', grupoHTML);
      document.getElementById('bloco-aplicar-valores')?.classList.remove('hidden');
      verificarExibicaoBlocoAplicar();

      // Estilo Select2
      $('select.select2-tailwind').each(function () {
        if (!$(this).hasClass("select2-hidden-accessible")) {
          $(this).select2({
            width: '100%',
            placeholder: 'Selecione',
            allowClear: true,
            language: {
              noResults: () => "Nenhum resultado encontrado",
              searching: () => "Buscando..."
            }
          });
        }
      });
    });
}

function removerConvenio(botao) {
  const grupo = botao.closest('.grupo-convenio');
  grupo.remove();
  verificarExibicaoBlocoAplicar();
}


function aplicarListenersCriarAgenda() {
  const selectTipo = document.getElementById('tipo_agendamento');
  const blocoConsulta = document.getElementById('bloco-consulta');

  if (selectTipo && blocoConsulta) {
    selectTipo.addEventListener('change', function () {
      blocoConsulta.style.display = (this.value === 'consulta') ? 'block' : 'none';
    });
  }
}


// Este código foi movido para funcoes.js para evitar duplicação


function aplicarMascaraTelefone() {
  const tel = document.getElementById('telefone');
  if (!tel) return;

  tel.addEventListener('input', function () {
    let v = tel.value.replace(/\D/g, ''); // remove tudo que não é número

    // Limita a 11 dígitos (celular com DDD)
    v = v.substring(0, 11);

    if (v.length <= 10) {
      // Formato fixo (ex: (84) 3456-7890)
      v = v.replace(/^(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
    } else {
      // Formato celular (ex: (84) 98765-4321)
      v = v.replace(/^(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
    }

    tel.value = v;
  });
}


let cachedConvenios = null;

function editarAgenda(id) {
  // Oculta todos os formulários
  document.querySelectorAll('[id^="form-"]').forEach(div => div.classList.add('hidden'));

  // Oculta o bloco de listagem
  const listagem = document.getElementById('bloco-listagem');
  if (listagem) listagem.classList.add('hidden');

  const form = document.getElementById('form-editar');
  if (!form) return;

  form.classList.remove('hidden');
  form.innerHTML = 'Carregando...';

  // 🔁 Carrega convênios apenas uma vez
  const conveniosPromise = cachedConvenios
    ? Promise.resolve(cachedConvenios)
    : fetch('get_convenios.php')
        .then(res => res.json())
        .then(json => {
          cachedConvenios = json;
          return cachedConvenios;
        });

  // Carrega o formulário + convênios ao mesmo tempo
  Promise.all([
    fetch(`form_editar_agenda.php?id=${id}`).then(r => r.text()),
    conveniosPromise
  ])
  .then(([html, conveniosDisponiveis]) => {
    form.innerHTML = html;

    inicializarEdicaoAgenda();

    // Preenche convenios já vinculados
    const scriptConvenios = form.querySelector('#convenios-json');
    if (scriptConvenios) {
      const listaConvenios = JSON.parse(scriptConvenios.textContent.trim());

      const container = document.getElementById('container-convenios');
      if (container) {
        const fragment = document.createDocumentFragment();

        listaConvenios.forEach(c => {
          const grupo = document.createElement('div');
          grupo.className = "grupo-convenio grid grid-cols-1 md:grid-cols-12 gap-4 items-center mb-4 border-t pt-4";
          grupo.innerHTML = `
            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700">Convênio <span class="text-red-500">*</span></label>
              <select name="convenio_id[]" class="select2-tailwind campo-obrigatorio w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm">
                <option value="">Selecione</option>
                ${conveniosDisponiveis.map(opt =>
                  `<option value="${opt.id}" ${opt.id == c.id ? 'selected' : ''}>${opt.nome}</option>`).join('')}
              </select>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Limite de atendimentos *</label>
              <input type="number" name="limite_atendimentos[]" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm" value="${c.limite || ''}">
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Qtd. de retornos</label>
              <input type="number" name="qtd_retornos[]" min="0" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm" value="${c.retornos || ''}">
            </div>
            <div class="md:col-span-2 flex justify-center items-center h-full">
              <button type="button" class="text-red-500 font-bold hover:underline text-sm w-full text-center" onclick="removerConvenio(this)">Remover</button>
            </div>
          `;
          fragment.appendChild(grupo);
        });

        container.appendChild(fragment);
        verificarExibicaoBlocoAplicar();
        $('select.select2-tailwind').select2({ width: '100%' });
      }
    }

    // Submissão - garante apenas um listener
    const formEditar = document.getElementById('form-agenda');
    if (formEditar && !formEditar.getAttribute('data-edit-listener-added')) {
      formEditar.setAttribute('data-edit-listener-added', 'true');
      formEditar.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!validarCamposObrigatorios()) return;

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

        const convenios = [];
        document.querySelectorAll('.grupo-convenio').forEach(grupo => {
          const id = grupo.querySelector('select[name="convenio_id[]"]')?.value;
          const limite = grupo.querySelector('input[name="limite_atendimentos[]"]')?.value;
          const retornos = grupo.querySelector('input[name="qtd_retornos[]"]')?.value;
          if (id) convenios.push({ id, limite, retornos });
        });

        // ✅ CORREÇÃO: Coletar vagas por dia da semana (igual ao form criar)
        const vagas = {};
        document.querySelectorAll('input[name="dias[]"]:checked').forEach(cb => {
          const slug = cb.dataset.dia;
          const vagasInput = document.querySelector(`input[name="vagas_${slug}"]`);
          if (vagasInput && vagasInput.value) {
            vagas[slug] = parseInt(vagasInput.value) || 0;
            console.log(`📊 EDITAR: Coletando vagas para ${slug}: ${vagas[slug]}`);
          }
        });

        const formData = new FormData(formEditar);
        formData.append('horarios', JSON.stringify(horarios));
        formData.append('convenios', JSON.stringify(convenios));
        formData.append('vagas', JSON.stringify(vagas)); // ✅ CORREÇÃO: Enviar vagas

        try {
          const res = await fetch('salvar_agenda.php', {
            method: 'POST',
            body: formData
          });

          const json = await res.json();
          if (json.status === 'sucesso') {
            showToast(json.mensagem, true);
            
            // ✅ CORREÇÃO: Processar uploads pendentes de preparos
            if (typeof processarUploadsPendentes === 'function' && json.agenda_id) {
              const preparosMapeados = json.preparos_mapeados || [];
              console.log('📎 Processando uploads pendentes para agenda editada:', json.agenda_id);
              
              try {
                await processarUploadsPendentes(json.agenda_id, preparosMapeados);
                showToast('Anexos processados com sucesso!', true);
              } catch (error) {
                console.error('Erro ao processar uploads pendentes:', error);
                showToast('Anexos salvos, mas houve erro no processamento', false);
              }
            }
            
            setTimeout(() => {
              voltarParaListagem();
              carregarPagina();
            }, 1500);
          } else {
            showToast('Erro: ' + json.mensagem, false);
          }
        } catch (err) {
          showToast('Erro ao salvar a agenda.', false);
        }
      });
    }
  })
  .catch(() => {
    form.innerHTML = '<div class="text-red-600">Erro ao carregar o formulário de edição.</div>';
  });
}

// Alias para carregarFormEdicao - mantém compatibilidade com onclick antigos
function carregarFormEdicao(id) {
  editarAgenda(id);
}


function showToast(msg, success = true) {
  let toast = document.getElementById('toast');
  
  // Criar elemento toast se não existir
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'toast';
    toast.className = 'hidden';
    document.body.appendChild(toast);
  }
  
  toast.textContent = msg;
  toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded shadow z-50 ${
    success ? 'bg-green-600' : 'bg-red-600'
  } text-white`;
  toast.classList.remove('hidden');
  setTimeout(() => toast.classList.add('hidden'), 4000);
}

function aplicarValoresConvenios() {
  const limite = document.getElementById('aplicar-limite')?.value;
  const retornos = document.getElementById('aplicar-retornos')?.value;

  document.querySelectorAll('.grupo-convenio').forEach(grupo => {
    if (limite !== '') {
      grupo.querySelector('input[name="limite_atendimentos[]"]').value = limite;
    }
    if (retornos !== '') {
      grupo.querySelector('input[name="qtd_retornos[]"]').value = retornos;
    }
  });
}

function verificarExibicaoBlocoAplicar() {
  const container = document.querySelectorAll('.grupo-convenio');
  const blocoAplicar = document.getElementById('bloco-aplicar-valores');
  if (!blocoAplicar) return;

  if (container.length > 0) {
    blocoAplicar.classList.remove('hidden');
  } else {
    blocoAplicar.classList.add('hidden');
  }
}


// ✅ Aplica Select2 apenas quando focar no campo
function aplicarSelect2NoFoco() {
  document.querySelectorAll('select.select2-tailwind').forEach(select => {
    select.addEventListener('focus', () => {
      if (!$(select).hasClass('select2-hidden-accessible')) {
        $(select).select2({
          width: '100%',
          placeholder: 'Selecione',
          allowClear: true,
          language: {
            noResults: () => "Nenhum resultado encontrado",
            searching: () => "Buscando..."
          }
        });
      }
    }, { once: true });
  });
}

// ✅ Inicializa validações e otimizações específicas ao editar
function inicializarEdicaoAgenda() {
  console.log('🚀 INICIALIZANDO EDIÇÃO AGENDA - Função chamada!');
  
  // 🧹 LIMPEZA RADICAL: Remover formulários duplicados ANTES de tudo
  console.log('🧹 LIMPANDO FORMULÁRIOS DUPLICADOS...');
  
  // ✅ PRIMEIRO: Salvar valores existentes ANTES de remover elementos
  const valoresVagas = {};
  const elementosVagasAntigos = document.querySelectorAll('[id^="vagas-"]:not(#vagas-por-dia)');
  console.log(`📥 Salvando valores de ${elementosVagasAntigos.length} elementos vagas antes de remover`);
  
  elementosVagasAntigos.forEach(el => {
    const input = el.querySelector('input[type="number"]');
    if (input && input.value) {
      const nome = input.name; // ex: vagas_seg
      valoresVagas[nome] = input.value;
      console.log(`💾 Salvou valor: ${nome} = ${input.value}`);
    }
  });
  
  // Armazenar valores globalmente para acesso posterior
  window.valoresSalvosVagas = valoresVagas;
  
  console.log(`🗑️ Removendo ${elementosVagasAntigos.length} elementos vagas antigos`);
  elementosVagasAntigos.forEach(el => el.remove());
  
  // Remover formulários duplicados (manter apenas o último)
  const formularios = document.querySelectorAll('#form-agenda');
  if (formularios.length > 1) {
    console.log(`🗑️ Encontrados ${formularios.length} formulários duplicados, mantendo apenas o último`);
    for (let i = 0; i < formularios.length - 1; i++) {
      formularios[i].remove();
    }
  }
  
  // Remover containers vagas-por-dia duplicados (manter apenas o último)
  const containers = document.querySelectorAll('#vagas-por-dia');
  if (containers.length > 1) {
    console.log(`🗑️ Encontrados ${containers.length} containers duplicados, mantendo apenas o último`);
    for (let i = 0; i < containers.length - 1; i++) {
      containers[i].remove();
    }
  }
  
  ativarValidacao();
  ativarValidacaoTipoAgenda();
  ativarHorariosPorDia();
  inicializarListenersAgenda();
  aplicarMascaraTelefone();
  aplicarSelect2NoFoco();
  
  // ✅ CÓDIGO COPIADO DO form_criar_agenda.php - CONTROLE DE VAGAS POR DIA
  // Reage aos checkboxes para habilitar/desabilitar os horários E controlar vagas
  const checkboxes = document.querySelectorAll('input[name="dias[]"]');
  console.log('🔧 EDITAR AGENDA: Encontrados', checkboxes.length, 'checkboxes para controle de vagas');
  
  // ⚠️ IMPORTANTE: Remover listeners anteriores para evitar conflito com form criar
  checkboxes.forEach(checkbox => {
    // Clona o elemento para remover todos os event listeners
    const novoCheckbox = checkbox.cloneNode(true);
    checkbox.parentNode.replaceChild(novoCheckbox, checkbox);
  });
  
  // Buscar novamente os checkboxes após substituição
  const checkboxesLimpos = document.querySelectorAll('input[name="dias[]"]');
  console.log('🧹 EDITAR: Checkboxes limpos, total:', checkboxesLimpos.length);
  
  checkboxesLimpos.forEach((checkbox, index) => {
    console.log(`🔧 EDITAR: Checkbox ${index}:`, checkbox.value, 'data-dia:', checkbox.getAttribute('data-dia'));
    
    // Função específica para edição (evita conflito com criar)
    function handleVagasEdicao() {
      const slug = this.value.toLowerCase().substring(0, 3);
      console.log('🎯 EDITAR: Evento change!', this.value, 'slug:', slug, 'checked:', this.checked);
      
      // Controlar horários (código existente)
      document.querySelectorAll(`select[data-dia="${slug}"]`).forEach(select => {
        $(select).prop('disabled', !this.checked).trigger('change.select2');
      });
      
      // ✅ NOVO: Controlar campos de vagas - IGUAL AO FORM CRIAR
      let vagasDiv = document.getElementById(`vagas-${slug}`);
      let vagasInput = document.querySelector(`input[name="vagas_${slug}"]`);
      
      console.log('🎯 EDITAR: Procurando vagas:', {
        slug: slug,
        vagasDivId: `vagas-${slug}`,
        vagasDiv: !!vagasDiv,
        vagasInput: !!vagasInput
      });
      
      // Se os elementos não existem, vamos criá-los do zero
      if (!vagasDiv) {
        console.log('🔧 EDITAR: Elemento vagas não existe, criando do zero para', slug);
        const containerPai = document.getElementById('vagas-por-dia');
        if (containerPai) {
          // ✅ BUSCAR VALOR SALVO ANTES DA LIMPEZA (apenas se for edição real)
          let valorExistente = '';
          const nomeInput = `vagas_${slug}`;
          
          // ✅ VERIFICAR SE É EDIÇÃO: Só usar valores salvos se existir ID da agenda
          const isEdicaoReal = document.querySelector('input[name="id"]');
          
          if (isEdicaoReal) {
            // Primeiro: tentar pegar dos valores salvos
            if (window.valoresSalvosVagas && window.valoresSalvosVagas[nomeInput]) {
              valorExistente = window.valoresSalvosVagas[nomeInput];
              console.log('💰 EDITAR: Valor recuperado dos salvos para', slug, ':', valorExistente);
            } else {
              // Segundo: tentar pegar de input existente (caso ainda exista)
              const inputExistente = document.querySelector(`input[name="${nomeInput}"]`);
              if (inputExistente && inputExistente.value) {
                valorExistente = inputExistente.value;
                console.log('🔍 EDITAR: Valor encontrado em input existente para', slug, ':', valorExistente);
              } else {
                console.log('ℹ️ EDITAR: Nenhum valor encontrado para', slug);
              }
            }
          } else {
            // Se não é edição real, começar com campo limpo
            console.log('🆕 CRIAR: Não é edição real, campo será criado vazio para', slug);
          }
          
          // Criar elemento diretamente
          const novoElementoVagas = document.createElement('div');
          novoElementoVagas.id = `vagas-${slug}`;
          novoElementoVagas.className = 'hidden'; // Começar oculto como o original
          novoElementoVagas.innerHTML = `
            <label class="block text-sm font-medium text-gray-700">${this.value}</label>
            <input type="number" name="vagas_${slug}" min="1" max="100" value="${valorExistente}"
                   class="w-full border border-gray-300 rounded px-3 py-2 text-sm" 
                   placeholder="Ex: 20">
          `;
          
          // ✅ INSERIR NA POSIÇÃO CORRETA (ordenação por dia da semana)
          const ordemDias = ['seg', 'ter', 'qua', 'qui', 'sex', 'sáb', 'dom'];
          const posicaoAtual = ordemDias.indexOf(slug);
          
          console.log(`🔍 EDITAR: Verificando posição para ${slug} - posição: ${posicaoAtual}`);
          
          // Encontrar onde inserir baseado na ordem dos dias
          let elementoAnterior = null;
          for (let i = posicaoAtual - 1; i >= 0; i--) {
            const elementoAnteriorCandidato = containerPai.querySelector(`#vagas-${ordemDias[i]}`);
            if (elementoAnteriorCandidato) {
              elementoAnterior = elementoAnteriorCandidato;
              break;
            }
          }
          
          if (elementoAnterior) {
            // Inserir após o elemento anterior encontrado
            elementoAnterior.insertAdjacentElement('afterend', novoElementoVagas);
            console.log(`📍 EDITAR: Inserindo ${slug} após ${elementoAnterior.id}`);
          } else {
            // Se não há elemento anterior, inserir no início
            containerPai.insertBefore(novoElementoVagas, containerPai.firstChild);
            console.log(`📍 EDITAR: Inserindo ${slug} no início`);
          }
          
          // Atualizar referências
          vagasDiv = novoElementoVagas;
          vagasInput = novoElementoVagas.querySelector('input[type="number"]');
          console.log('✅ EDITAR: Elemento vagas criado para', slug);
        }
      }
      
      if (vagasDiv && vagasInput) {
        // ⚠️ Usar setTimeout para garantir que nossa mudança seja a última
        setTimeout(() => {
          if (this.checked) {
            console.log('✅ EDITAR: Mostrando vagas para', slug);
            vagasDiv.classList.remove('hidden');
            vagasInput.required = true;
          } else {
            console.log('❌ EDITAR: Ocultando vagas para', slug);
            vagasDiv.classList.add('hidden');
            vagasInput.required = false;
            vagasInput.value = '';
          }
        }, 10); // Pequeno delay para garantir que execute por último
      } else {
        console.error('❌ EDITAR: Elementos não encontrados para', slug);
      }
    }
    
    checkbox.addEventListener('change', handleVagasEdicao);
    
    // ✅ CONFIGURAR ESTADO INICIAL (mostrar elementos já selecionados)
    if (checkbox.checked) {
      console.log('🔄 EDITAR: Configurando estado inicial para checkbox já marcado:', checkbox.value);
      handleVagasEdicao.call(checkbox);
    }
  });
  
  // Inicializar preparos
  if (typeof inicializarPreparos === 'function') {
    inicializarPreparos();
  }
}

function excluirAgenda(id) {
  if (!confirm("Deseja realmente excluir esta agenda?")) return;

  fetch('excluir_agenda.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: `id=${id}`
  })
  .then(res => res.text())
  .then(msg => {
    alert(msg);
    carregarPagina(); // recarrega a lista
  })
  .catch(() => alert('Erro ao excluir agenda.'));
}

document.addEventListener('DOMContentLoaded', function() {
    const inputEncaixes = document.querySelector('input[name="limite_encaixes_dia"]');
    const inputVagas = document.querySelector('input[name="limite_vagas_dia"]');
    
    if (inputEncaixes) {
        inputEncaixes.addEventListener('change', function() {
            const valor = parseInt(this.value) || 0;
            const vagas = parseInt(inputVagas?.value) || 20;
            
            // Validação: encaixes não devem exceder 50% das vagas normais
            const maxEncaixes = Math.floor(vagas * 0.5);
            
            if (valor > maxEncaixes && valor > 0) {
                alert(`Atenção: Recomenda-se no máximo ${maxEncaixes} encaixes para ${vagas} vagas normais.`);
            }
            
            // Mostrar/ocultar aviso baseado no valor
            let aviso = document.getElementById('aviso-encaixes');
            if (valor > 0 && !aviso) {
                aviso = document.createElement('div');
                aviso.id = 'aviso-encaixes';
                aviso.className = 'mt-2 p-2 bg-yellow-50 border border-yellow-200 rounded text-xs text-yellow-800';
                aviso.innerHTML = `
                    <i class="bi bi-exclamation-triangle mr-1"></i>
                    <strong>Encaixes habilitados:</strong> ${valor} encaixe(s) por dia permitido(s)
                `;
                this.parentElement.appendChild(aviso);
            } else if (valor === 0 && aviso) {
                aviso.remove();
            } else if (aviso) {
                aviso.innerHTML = `
                    <i class="bi bi-exclamation-triangle mr-1"></i>
                    <strong>Encaixes habilitados:</strong> ${valor} encaixe(s) por dia permitido(s)
                `;
            }
        });
    }
});
