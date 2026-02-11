/**
 * ================================
 * DROPDOWN DE CL�NICA (ABRIR POR CLIQUE)
 * ================================
 * 
 * Esse script faz duas coisas:
 * 1. Define o nome da cl�nica com base no par�metro da URL (?cidade=)
 * 2. Controla o dropdown de sele��o de cl�nica (abre/fecha ao clicar)
 */

document.addEventListener('DOMContentLoaded', function () {
  const btn = document.getElementById('clinicaBtn');               // Bot�o para abrir dropdown
  const dropdown = document.getElementById('clinicaDropdown');     // Menu dropdown com as cl�nicas
  const clinicaAtual = document.getElementById('clinicaAtual');    // Onde exibir o nome da cl�nica atual

  // Mapeia os IDs da URL para o nome da cl�nica correspondente
  const cidadeMap = {
    1: "Cl�nica - Mossor�",
    2: "Cl�nica - Natal",
    3: "Cl�nica - Parnamirim",
    4: "Cl�nica - Bara�na",
    5: "Cl�nica - Ass�",
    8: "Cl�nica - Santo Ant�nio",
    13: "Cl�nica - Alto do Rodrigues",
    14: "Cl�nica - Extremoz",
  };

  // L� o par�metro ?cidade= da URL e define o nome da cl�nica
  const params = new URLSearchParams(window.location.search);
  const cidadeId = params.get('cidade') || '1';
  if (clinicaAtual) {
    clinicaAtual.textContent = cidadeMap[cidadeId] || "Cl�nica - Mossor�";
  }

  // Toggle do dropdown ao clicar no bot�o
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
});


/**
 * ================================
 * SIDEBAR COM SUBMENUS (TOGGLE + AUTOFECHAR)
 * ================================
 * 
 * Esse script:
 * 1. Gerencia a exibi��o dos submenus do sidebar
 * 2. Fecha automaticamente os submenus ao clicar fora ou sair com o mouse
 */

let sidebar = document.querySelector('aside');                    // Elemento da sidebar principal
let submenus = document.querySelectorAll('[id^="submenu-"]');     // Todos os submenus com ID iniciado em 'submenu-'

let isMouseInside = false; // Flag de controle para saber se o mouse est� dentro do sidebar

// Marca quando o mouse entra no sidebar
sidebar.addEventListener('mouseenter', () => isMouseInside = true);

// Quando o mouse sai da sidebar, fecha todos os submenus
sidebar.addEventListener('mouseleave', () => {
  isMouseInside = false;
  closeAllSubmenus();
});

/**
 * Alterna a visibilidade de um submenu espec�fico e fecha os demais
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


// Carrega o conte�do inicial da especialidade ou procedimento (nome + tipo)
function carregarConteudo(tipo, nome) {
  const conteudoDiv = document.getElementById('conteudo-dinamico');
  const loader = document.getElementById('loader');

  loader.classList.remove('hidden');

  fetch(`carregar_conteudo.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}`)
    .then(res => res.text())
    .then(html => {
      conteudoDiv.innerHTML = html;

      // ? Carrega a listagem completa SEM filtro de dia
      carregarListagemAgendas(tipo, nome);

      // ? Carrega os dias dispon�veis
      carregarDiasDisponiveis(tipo, nome);
    })
    .catch(() => {
      conteudoDiv.innerHTML = '<div class="text-red-600">Erro ao carregar conte�do.</div>';
    })
    .finally(() => {
      loader.classList.add('hidden');
    });
}


// Carrega os m�dicos daquele tipo e nome, filtrando por dia (clicado)
function carregarMedicosPorDia(tipo, nome, dia) {
  const destino = document.getElementById('medicos-por-dia');
  destino.innerHTML = 'Carregando...';

  fetch(`listar_agendas.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}&dia=${encodeURIComponent(dia)}`)
    .then(res => res.text())
    .then(html => destino.innerHTML = html)
    .catch(() => destino.innerHTML = '<div class="text-red-600">Erro ao carregar.');
}

// Carrega os dias dispon�veis da especialidade/procedimento
function carregarDiasDisponiveis(tipo, nome) {
  const div = document.getElementById('dias-disponiveis');
  div.innerHTML = 'Carregando dias dispon�veis...';

  fetch(`dias_disponiveis.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}`)
    .then(r => r.text())
    .then(html => div.innerHTML = html)
    .catch(() => div.innerHTML = '<div class="text-red-600">Erro ao carregar dias.</div>');
}

// Carrega todas as agendas da especialidade/procedimento (sem filtro de dia)
function carregarListagemAgendas(tipo, nome) {
  const div = document.getElementById('medicos-por-dia');
  div.innerHTML = 'Carregando agendas...';

  fetch(`listar_agendas.php?tipo=${tipo}&nome=${encodeURIComponent(nome)}&dia=`)
    .then(res => res.text())
    .then(html => div.innerHTML = html)
    .catch(() => div.innerHTML = '<div class="text-red-600">Erro ao carregar agendas.</div>');
}


function selecionarDia(botao, tipo, nome, dia) {
  // Remove destaque de todos os bot�es
  document.querySelectorAll('#filtros-dias button').forEach(btn => {
    btn.classList.remove('bg-teal-500', 'text-white');
    btn.classList.add('bg-gray-100');
  });

  // Adiciona destaque no bot�o clicado
  botao.classList.remove('bg-gray-100');
  botao.classList.add('bg-teal-500', 'text-white');

  // Atualiza o texto "Dia selecionado"
  const selecionado = document.getElementById('dia-selecionado');
  selecionado.textContent = dia ? `Dia selecionado: ${dia}` : 'Dia selecionado: Todos os dias';

  // Carrega os m�dicos do dia
  carregarMedicosPorDia(tipo, nome, dia);
}


function alterarSituacao(botao, id, novaSituacao) {
  if (!confirm('Deseja realmente alterar a situa��o desta agenda?')) return;

  fetch('atualizar_situacao_agenda.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `id=${id}&situacao=${novaSituacao}`
  })
  .then(res => res.text())
  .then(msg => {
    alert(msg);

    // Atualiza visual do bot�o
    botao.innerText = novaSituacao === 1 ? 'Desativar' : 'Ativar';
    botao.classList.toggle('bg-green-200');
    botao.classList.toggle('text-green-800');
    botao.classList.toggle('bg-gray-200');
    botao.classList.toggle('text-gray-700');

    // Atualiza fun��o do bot�o (pr�xima troca)
    botao.setAttribute('onclick', `alterarSituacao(this, ${id}, ${novaSituacao === 1 ? 0 : 1})`);
  })
  .catch(() => alert('Erro ao alterar situa��o.'));
}

const idcidade = new URLSearchParams(window.location.search).get('idcidade') || 1;

function carregarPagina(pagina = 1) {
  const buscaInput = document.getElementById('campoBusca');
  const busca = buscaInput ? buscaInput.value : '';
  const select = document.getElementById('itensPorPagina');
  const itens = select ? select.value : 10;

  fetch(`listar_agendas_ajax.php?idcidade=${idcidade}&pagina=${pagina}&limite=${itens}&busca=${encodeURIComponent(busca)}`)
    .then(res => res.text())
    .then(html => {
      document.getElementById('tabelaAgendas').innerHTML = html;
      carregarPaginacao(pagina, busca);
    });
}

function carregarPaginacao(paginaAtual = 1, busca = '') {
  const select = document.getElementById('itensPorPagina');
  const itens = select ? select.value : 10;

  fetch(`listar_paginas.php?idcidade=${idcidade}&paginaAtual=${paginaAtual}&limite=${itens}&busca=${encodeURIComponent(busca)}`)
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
    // Ao sair do campo (perder o foco), valida se est� vazio
    campo.addEventListener('blur', () => {
      if (campo.value.trim() === '') aplicarErro(campo);
    });

    // Ao digitar, remove erro
    campo.addEventListener('input', () => limparErro(campo));
  });

  function aplicarErro(campo) {
    if (!campo.classList.contains('border-red-600')) {
      campo.classList.add('border-red-600');

      // Evita duplicar mensagens de erro
      if (!campo.parentElement.querySelector('.msg-erro')) {
        const erro = document.createElement('small');
        erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
        erro.textContent = campo.dataset.erro || 'Campo obrigat�rio';
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



  //console.log('??? Campos vis�veis encontrados:', campos.length);

  campos.forEach(campo => {
    const valor = campo.value?.trim();
    const erroExistente = campo.parentElement.querySelector('.msg-erro');

    //console.log('?? Validando campo:', campo.name || campo.id, '?', valor);
    if (!valor) {
      //console.warn('? Campo vazio (vis�vel):', campo.name || campo.id, campo);
    }


    if (!valor) {
      campo.classList.add('border-red-600');

      if (!erroExistente) {
        const erro = document.createElement('small');
        erro.className = 'msg-erro text-red-600 text-sm mt-1 block';
        erro.textContent = campo.dataset.erro || 'Campo obrigat�rio';
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
    //console.log('? Falta marcar tipo de agenda');
    valido = false;
  }

  // Checkboxes (dias[])
  const checkboxes = document.querySelectorAll('input[name="dias[]"]');
  const algumDiaMarcado = [...checkboxes].some(cb => cb.checked);
  const erroDias = document.getElementById("erro-dias");

  if (!algumDiaMarcado) {
    if (erroDias) erroDias.classList.remove("hidden");
    //console.log('? Nenhum dia selecionado');
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

    if (tipo === 'consulta') {
      campoPrestador.style.display = 'block';
      campoProcedimento.style.display = 'none';
      const blocoConsulta = document.getElementById('bloco-consulta');
       if (blocoConsulta) blocoConsulta.style.display = 'block';
    } else if (tipo === 'procedimento') {
      campoPrestador.style.display = 'block';
      campoProcedimento.style.display = 'block';
    } else {
      campoPrestador.style.display = 'none';
      campoProcedimento.style.display = 'none';
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

function filtrarTabela(valor) {
  const linhas = document.querySelectorAll("#tabelaAgendas tr");
  valor = valor.toLowerCase().normalize("NFD").replace(/[?-?]/g, "");

  if (valor.length < 2) {
    linhas.forEach(linha => linha.style.display = '');
    return;
  }

  linhas.forEach(linha => {
    const texto = linha.innerText.toLowerCase().normalize("NFD").replace(/[?-?]/g, "");
    linha.style.display = texto.includes(valor) ? '' : 'none';
  });
}

$(document).ready(function () {
  $('.select-horario').select2({
    placeholder: "-- : --",
    allowClear: true,
    width: '100%',
    language: {
      noResults: function () { return "Nenhum hor�rio encontrado"; },
      searching: function () { return "Buscando..."; }
    }
  });
});


const observer = new MutationObserver(() => {
  document.querySelectorAll('.select2-tailwind').forEach(select => {
    if (!$(select).hasClass("select2-hidden-accessible")) {
      $(select).select2({
        placeholder: $(select).data("placeholder") || "Selecione uma op��o",
        allowClear: false,
        width: '100%',
        dropdownAutoWidth: true,
        language: {
          noResults: () => "Nenhum resultado encontrado",
          searching: () => "Buscando..."
        },
        templateResult: function (data) {
          if (!data.id) return data.text;
          const isSelected = $(select).val() == data.id;
          return $('<span class="ml-4 text-base">' + (isSelected ? '?? ' : '') + data.text + '</span>');
        }
      });

      setTimeout(() => {
        const $container = $(select).next('.select2-container');

        $container.find('.select2-selection--single').css({
          height: '2.5rem',                // igual ao input padr�o (h-10)
          border: '1px solid #d1d5db',
          'border-radius': '0.375rem',
          padding: '0.375rem 0.75rem',
          'font-size': '0.875rem',         // text-sm
          display: 'flex',
          'align-items': 'center',
          'background-color': '#fff',
          'box-shadow': '0 1px 2px rgba(0, 0, 0, 0.05)'
        });

        $('.select2-dropdown').addClass('rounded-md border border-gray-200 text-base shadow-lg')
          .css({ 'max-height': '350px' }); // aumenta a altura do dropdown

        $('.select2-results__options').addClass('!max-h-[320px] overflow-auto');
        $('.select2-results__option').addClass('pl-6 py-2');
      }, 100);
    }
  });
});

observer.observe(document.body, { childList: true, subtree: true });

// Aplica �cone na busca + bot�o "Limpar" fixo fora do scroll
$(document).on('select2:open', () => {
  setTimeout(() => {
    // �cone de lupa no placeholder
    const searchInput = document.querySelector('.select2-search__field');
    if (searchInput) {
      searchInput.placeholder = "?? Pesquise...";
      searchInput.classList.add('text-base', 'px-3', 'py-2');
    }

    // Bot�o "Limpar" fixo se n�o existir
    if (!document.querySelector('.select2-clear-wrapper')) {
      const limparBtn = document.createElement('div');
      limparBtn.className = 'select2-clear-wrapper px-4 py-2 border-t border-gray-200 text-center text-red-600 cursor-pointer text-sm bg-white';
      limparBtn.innerText = 'Limpar';

      limparBtn.addEventListener('click', () => {
        const openSelect = $('.select2-container--open').prev('select');
        if (openSelect.length > 0) {
          openSelect.val(null).trigger('change');

          try {
            openSelect.select2('close'); // fecha apenas se for um select2 v�lido
          } catch (e) {
            console.warn('Elemento n�o est� usando Select2');
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
            <label class="block text-sm font-medium text-gray-700">Conv�nio <span class="text-red-500">*</span></label>
            <select name="convenio_id[]" class="select2-tailwind campo-obrigatorio w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm">
              ${options}
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">Limite de atendimentos *</label>
            <input type="number" name="limite_atendimentos[]" min="0" class="campo-obrigatorio w-full border border-gray-300 rounded-md px-3 py-2 text-sm shadow-sm" value="${convenio.limite || ''}">
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


document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('#form-criar form');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validarCamposObrigatorios()) return;

    // Coleta os hor�rios
    const diasSelecionados = [...document.querySelectorAll('input[name="dias[]"]:checked')];
    const horarios = diasSelecionados.map(cb => {
      const slug = cb.dataset.dia;
      return {
        dia: cb.value,
        manha_ini: document.querySelector(`select[name="${slug}_manha_inicio"]`)?.value || '',
        manha_fim: document.querySelector(`select[name="${slug}_manha_fim"]`)?.value || '',
        tarde_ini: document.querySelector(`select[name="${slug}_tarde_inicio"]`)?.value || '',
        tarde_fim: document.querySelector(`select[name="${slug}_tarde_fim"]`)?.value || ''
      };
    });

    // Coleta os conv�nios
    const grupos = document.querySelectorAll('.grupo-convenio');
    const convenios = [...grupos].map(grupo => ({
      id: grupo.querySelector('select[name="convenio_id[]"]')?.value || '',
      limite: grupo.querySelector('input[name="limite_atendimentos[]"]')?.value || 0,
      retornos: grupo.querySelector('input[name="qtd_retornos[]"]')?.value || 0
    }));

    // Cria FormData
    const formData = new FormData(form);
    formData.append('horarios', JSON.stringify(horarios));
    formData.append('convenios', JSON.stringify(convenios));

    fetch('salvar_agenda.php', {
      method: 'POST',
      body: formData
    })
    .then(res => res.json())
    .then(json => {
    if (json.status === 'sucesso') {
      showToast(json.mensagem, true);
      voltarParaListagem();
      carregarPagina();
    } else {
      showToast('Erro: ' + json.mensagem, false);
    }
    })
      .catch(() => showToast('Erro ao salvar a agenda.', false));
  });
});


function aplicarMascaraTelefone() {
  const tel = document.getElementById('telefone');
  if (!tel) return;

  tel.addEventListener('input', function () {
    let v = tel.value.replace(/\D/g, ''); // remove tudo que n�o � n�mero

    // Limita a 11 d�gitos (celular com DDD)
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


function editarAgenda(id) {
  // Oculta todos os formul�rios
  document.querySelectorAll('[id^="form-"]').forEach(div => div.classList.add('hidden'));

  // Oculta o bloco de listagem
  const listagem = document.getElementById('bloco-listagem');
  if (listagem) listagem.classList.add('hidden');

  const form = document.getElementById('form-editar');
  if (!form) return;

  form.classList.remove('hidden');
  form.innerHTML = 'Carregando...';

  fetch(`form_editar_agenda.php?id=${id}`)
    .then(response => response.text())
    .then(html => {
      form.innerHTML = html;

      // Inicializa comportamentos ap�s o HTML ser carregado
      $('.select-horario').select2({
        placeholder: "-- : --",
        allowClear: true,
        width: '100%',
        language: {
          noResults: () => "Nenhum hor�rio encontrado",
          searching: () => "Buscando...",
          inputTooShort: () => "Digite para pesquisar"
        }
      });

      ativarValidacao();
      ativarValidacaoTipoAgenda();
      ativarHorariosPorDia();
      inicializarListenersAgenda();
      aplicarMascaraTelefone();

      document.querySelectorAll('input[name="dias[]"]').forEach(checkbox => {
        const slug = checkbox.value.toLowerCase().substring(0, 3);
        const inputs = document.querySelectorAll(`.horario-input[data-dia="${slug}"]`);

        // Estado inicial ao carregar
        const ativo = checkbox.checked;
        inputs.forEach(input => {
          input.disabled = !ativo;
          input.classList.toggle('bg-white', ativo);
          input.classList.toggle('bg-gray-100', !ativo);
        });

        // Atualiza��o ao interagir
        checkbox.addEventListener('change', () => {
          const ativo = checkbox.checked;
          inputs.forEach(input => {
            input.disabled = !ativo;
            input.classList.toggle('bg-white', ativo);
            input.classList.toggle('bg-gray-100', !ativo);
          });
        });
      });


      // Preenche os conv�nios j� existentes
      if (typeof conveniosCarregados !== 'undefined') {
        conveniosCarregados.forEach(c => adicionarConvenio(c));
      }

      // Recupera os conv�nios via PHP embutido no HTML
      const scriptConvenios = form.querySelector('#convenios-json');
      if (scriptConvenios) {
        const dados = scriptConvenios.textContent.trim();
        if (dados) {
          const listaConvenios = JSON.parse(dados);
          listaConvenios.forEach(c => adicionarConvenio(c));
        }
      }


      // ?? For�a exibi��o correta dos campos tipo_agendamento
      const tipoAgendamento = document.getElementById('tipo_agendamento');
      const campoPrestador = document.getElementById('campo-prestador');
      const campoProcedimento = document.getElementById('campo-procedimento');

      if (tipoAgendamento && campoPrestador && campoProcedimento) {
        const tipo = tipoAgendamento.value;
        const blocoConsulta = document.getElementById('bloco-consulta');

        if (tipo === 'consulta') {
          campoPrestador.style.display = 'block';
          campoProcedimento.style.display = 'none';
          if (blocoConsulta) blocoConsulta.style.display = 'block';
        } else if (tipo === 'procedimento') {
          campoPrestador.style.display = 'block';
          campoProcedimento.style.display = 'block';
          if (blocoConsulta) blocoConsulta.style.display = 'none';
        } else {
          campoPrestador.style.display = 'none';
          campoProcedimento.style.display = 'none';
          if (blocoConsulta) blocoConsulta.style.display = 'none';
        }
      }

      // Submiss�o do form de edi��o
      const formEditar = document.getElementById('form-agenda');
      if (formEditar) {
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

          const formData = new FormData(formEditar);
          formData.append('horarios', JSON.stringify(horarios));
          formData.append('convenios', JSON.stringify(convenios));

          try {
            const res = await fetch('salvar_agenda.php', {
              method: 'POST',
              body: formData
            });

            const json = await res.json();
            if (json.status === 'sucesso') {
              showToast(json.mensagem, true);
              setTimeout(() => {
                voltarParaListagem();
                carregarPagina();
              }, 1500); // espera 1,5s antes de sair da tela
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
      form.innerHTML = '<div class="text-red-600">Erro ao carregar o formul�rio de edi��o.</div>';
    });
}

function showToast(msg, success = true) {
  const toast = document.getElementById('toast');
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


