# 🤖 Bot WhatsApp - Teste Local

Bot WhatsApp para Clínica Oitava Rosado - Versão para testar localmente no seu computador.

---

## ⚡ INSTALAÇÃO RÁPIDA (5 minutos)

### 1. Instalar Node.js
🔗 **Download:** https://nodejs.org/ (clique no botão verde "LTS")

### 2. Baixar este projeto
Copie os arquivos:
- `package.json`
- `bot.js`
- `INSTRUCOES.md`

Para uma pasta no seu computador (ex: `Desktop/whatsapp-bot`)

### 3. Instalar bibliotecas
Abra terminal na pasta e rode:
```bash
npm install
```

### 4. Rodar o bot
```bash
npm start
```

### 5. Escanear QR Code
O QR Code vai aparecer no terminal. Escaneie com WhatsApp!

---

## 📋 Comandos Úteis

```bash
# Instalar dependências
npm install

# Rodar o bot
npm start

# Parar o bot
Ctrl + C

# Ver versão do Node
node --version

# Limpar cache (se der erro)
npm cache clean --force
```

---

## 🎯 Por Que Testar Localmente?

1. ✅ Descobrir se o problema é no servidor ou no WhatsApp
2. ✅ Validar que o código funciona
3. ✅ Testar sem custos extras
4. ✅ Ver logs em tempo real

---

## ✅ Se Funcionar

O problema estava nos servidores Digital Ocean (IPs bloqueados).

**Solução:** Usar outro provedor (AWS, Contabo, Hetzner)

---

## ❌ Se NÃO Funcionar

O WhatsApp está bloqueando sua conta para uso com APIs.

**Solução:** Usar API oficial paga (Twilio, 360Dialog, Gupshup)

---

## 🆘 Precisa de Ajuda?

Leia o arquivo **INSTRUCOES.md** com passo a passo detalhado.

---

## 🔧 Tecnologias

- **Node.js** - Ambiente JavaScript
- **whatsapp-web.js** - Biblioteca WhatsApp Web
- **Puppeteer** - Automação Chrome
- **Express** - Servidor web
- **Axios** - Requisições HTTP

---

## 📞 Suporte

Se tiver problemas, envie:
- Print do erro
- Sistema operacional
- Versão do Node (`node --version`)

**Boa sorte!** 🚀
