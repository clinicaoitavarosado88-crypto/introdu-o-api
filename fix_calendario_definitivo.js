/**
 * 🛠️ FIX DEFINITIVO PARA NAVEGAÇÃO DO CALENDÁRIO
 * 
 * Este arquivo contém a correção para o problema de navegação do calendário.
 * O problema: os botões de navegação perdem os event listeners após atualizações.
 * 
 * USO: Incluir este arquivo APÓS o agenda.js ou executar as funções no console do navegador
 */

// ✅ FUNÇÃO PRINCIPAL: Corrigir navegação do calendário
function corrigirNavegacaoCalendario() {
    console.log('🛠️ Aplicando correção definitiva da navegação do calendário...');
    
    // 1. Encontrar todos os botões de navegação
    const navButtons = document.querySelectorAll('.nav-calendario');
    console.log(`📋 Encontrados ${navButtons.length} botões de navegação`);
    
    if (navButtons.length === 0) {
        console.error('❌ Nenhum botão de navegação encontrado! Verifique se os elementos com classe .nav-calendario existem.');
        return false;
    }
    
    // 2. Para cada botão, remover listeners antigos e adicionar novos
    navButtons.forEach((btn, index) => {
        const direcao = btn.dataset.direcao;
        console.log(`🔧 Corrigindo botão ${index + 1}: direção="${direcao}"`);
        
        // Remover event listeners antigos clonando o elemento
        const novoBotao = btn.cloneNode(true);
        btn.parentNode.replaceChild(novoBotao, btn);
        
        // Adicionar novo event listener
        novoBotao.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log(`🔄 Navegação clicada: ${direcao}`);
            
            // Verificar se as variáveis globais existem
            if (typeof window.agendaIdAtual === 'undefined') {
                console.error('❌ window.agendaIdAtual não definido');
                return;
            }
            
            // Chamar a função de navegação
            if (typeof navegarMesCalendario === 'function') {
                navegarMesCalendario(window.agendaIdAtual, direcao);
            } else {
                console.error('❌ Função navegarMesCalendario não encontrada');
                // Fallback: navegação manual simples
                navegacaoManual(direcao);
            }
        });
        
        console.log(`✅ Botão ${index + 1} corrigido com sucesso`);
    });
    
    console.log('🎉 Correção da navegação aplicada com sucesso!');
    return true;
}

// ✅ FUNÇÃO FALLBACK: Navegação manual caso as funções principais não existam
function navegacaoManual(direcao) {
    console.log(`🔄 Executando navegação manual: ${direcao}`);
    
    // Verificar se as variáveis globais existem
    if (typeof mesAtual === 'undefined' || typeof anoAtual === 'undefined') {
        console.error('❌ Variáveis mesAtual/anoAtual não definidas');
        return;
    }
    
    // Navegar
    if (direcao === 'prev') {
        mesAtual--;
        if (mesAtual < 0) {
            mesAtual = 11;
            anoAtual--;
        }
    } else if (direcao === 'next') {
        mesAtual++;
        if (mesAtual > 11) {
            mesAtual = 0;
            anoAtual++;
        }
    }
    
    console.log(`📅 Navegado para: ${mesAtual + 1}/${anoAtual}`);
    
    // Tentar atualizar o calendário
    if (typeof atualizarCalendarioLateral === 'function' && window.agendaIdAtual) {
        atualizarCalendarioLateral(window.agendaIdAtual);
    } else {
        console.warn('⚠️ Função atualizarCalendarioLateral não disponível ou agendaIdAtual não definido');
    }
}

// ✅ FUNÇÃO DE DIAGNÓSTICO: Verificar estado atual do calendário
function diagnosticarCalendario() {
    console.log('🔍 DIAGNÓSTICO DO CALENDÁRIO:');
    console.log('=====================================');
    
    // Verificar botões
    const navButtons = document.querySelectorAll('.nav-calendario');
    console.log(`📋 Botões de navegação encontrados: ${navButtons.length}`);
    
    navButtons.forEach((btn, index) => {
        const direcao = btn.dataset.direcao;
        const visivel = btn.offsetWidth > 0 && btn.offsetHeight > 0;
        const temDataset = !!btn.dataset.direcao;
        console.log(`   - Botão ${index + 1}: direção="${direcao}", visível=${visivel}, tem dataset=${temDataset}`);
    });
    
    // Verificar variáveis globais
    console.log(`📊 Variáveis globais:`);
    console.log(`   - window.agendaIdAtual: ${window.agendaIdAtual}`);
    console.log(`   - window.dataSelecionadaAtual: ${window.dataSelecionadaAtual}`);
    console.log(`   - mesAtual: ${typeof mesAtual !== 'undefined' ? mesAtual : 'não definido'}`);
    console.log(`   - anoAtual: ${typeof anoAtual !== 'undefined' ? anoAtual : 'não definido'}`);
    
    // Verificar funções
    console.log(`🔧 Funções disponíveis:`);
    console.log(`   - navegarMesCalendario: ${typeof navegarMesCalendario}`);
    console.log(`   - atualizarCalendarioLateral: ${typeof atualizarCalendarioLateral}`);
    console.log(`   - configurarCalendario: ${typeof configurarCalendario}`);
    
    // Verificar elementos DOM
    const calendario = document.getElementById('container-calendario');
    console.log(`🌐 Elementos DOM:`);
    console.log(`   - container-calendario existe: ${!!calendario}`);
    
    console.log('=====================================');
}

// ✅ FUNÇÃO AUTO-EXECUTÁVEL: Tentar corrigir automaticamente
function autoCorrecao() {
    console.log('🤖 Iniciando auto-correção da navegação do calendário...');
    
    // Aguardar um pouco para garantir que o DOM está pronto
    setTimeout(() => {
        if (corrigirNavegacaoCalendario()) {
            console.log('✅ Auto-correção bem-sucedida!');
        } else {
            console.warn('⚠️ Auto-correção falhou. Execute manualmente: corrigirNavegacaoCalendario()');
        }
    }, 500);
}

// ✅ FUNÇÃO PARA MONITORAR MUDANÇAS NO DOM (opcional)
function monitorarCalendario() {
    console.log('👁️ Iniciando monitoramento do calendário...');
    
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.target.id === 'container-calendario') {
                console.log('📅 Calendário atualizado, reaplicando correção...');
                setTimeout(corrigirNavegacaoCalendario, 100);
            }
        });
    });
    
    const calendario = document.getElementById('container-calendario');
    if (calendario) {
        observer.observe(calendario, { childList: true, subtree: true });
        console.log('✅ Monitoramento ativo');
    } else {
        console.warn('⚠️ Container do calendário não encontrado');
    }
}

// ✅ EXPOR FUNÇÕES GLOBALMENTE para uso no console
window.corrigirNavegacaoCalendario = corrigirNavegacaoCalendario;
window.diagnosticarCalendario = diagnosticarCalendario;
window.autoCorrecao = autoCorrecao;
window.monitorarCalendario = monitorarCalendario;

// ✅ AUTO-EXECUÇÃO quando o script é carregado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', autoCorrecao);
} else {
    autoCorrecao();
}

console.log('🛠️ Fix do calendário carregado. Funções disponíveis no console:');
console.log('   - corrigirNavegacaoCalendario()');
console.log('   - diagnosticarCalendario()');  
console.log('   - autoCorrecao()');
console.log('   - monitorarCalendario()');