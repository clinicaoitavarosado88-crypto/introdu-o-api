// configurar_usuario_renison.js
// Script para integração automática com o sistema principal
// Detecta o usuário logado do cookie "log_usuario"

console.log('🔧 Integrando com o sistema principal...');

// Aguardar o DOM estar carregado
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', integrarComSistemaPrincipal);
} else {
    integrarComSistemaPrincipal();
}

function integrarComSistemaPrincipal() {
    // Aguardar um pouco para garantir que agenda-new.js foi carregado
    setTimeout(() => {
        if (typeof detectarUsuarioLogado === 'function') {
            detectarUsuarioLogado();
            console.log('✅ Sistema integrado com sucesso!');
        } else if (typeof window.configurarUsuarioAtual === 'function') {
            // Fallback: tentar detectar manualmente
            const usuarioCookie = getCookieValue('log_usuario');
            if (usuarioCookie) {
                window.configurarUsuarioAtual(usuarioCookie);
                console.log('✅ Usuário detectado do cookie:', usuarioCookie);
            } else {
                console.warn('⚠️ Nenhum usuário logado encontrado no cookie');
                // Para desenvolvimento, usar RENISON como fallback
                window.configurarUsuarioAtual('RENISON');
                console.log('🔧 Usando RENISON como fallback para desenvolvimento');
            }
        } else {
            console.warn('⚠️ Sistema de usuários não encontrado. Tentando novamente...');
            // Tentar novamente em 1 segundo
            setTimeout(integrarComSistemaPrincipal, 1000);
        }
    }, 500);
}

// Função auxiliar para obter cookie (cópia da função do agenda-new.js)
function getCookieValue(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) {
        const cookieValue = parts.pop().split(';').shift();
        return cookieValue ? decodeURIComponent(cookieValue) : null;
    }
    return null;
}

// Função global para trocar o usuário manualmente se necessário
window.trocarUsuario = function(novoUsuario) {
    if (typeof window.configurarUsuarioAtual === 'function') {
        window.configurarUsuarioAtual(novoUsuario);
        console.log('👤 Usuário trocado para:', novoUsuario);
    } else {
        console.error('❌ Sistema de usuários não inicializado');
    }
};

// Função para verificar o usuário atual
window.verificarUsuarioAtual = function() {
    console.log('👤 Usuário atual:', window.usuarioAtual);
    console.log('🔐 Permissões:', window.usuarioPermissoes);
    return {
        usuario: window.usuarioAtual,
        permissoes: window.usuarioPermissoes
    };
};