@echo off
echo ════════════════════════════════════════════════
echo   Bot WhatsApp - Clinica Oitava Rosado
echo   Instalacao Automatica
echo ════════════════════════════════════════════════
echo.

echo Verificando Node.js...
node --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERRO] Node.js nao esta instalado!
    echo.
    echo Por favor, instale Node.js primeiro:
    echo https://nodejs.org/
    echo.
    echo Baixe a versao LTS e instale.
    echo Depois rode este script novamente.
    echo.
    pause
    exit
)

echo ✓ Node.js instalado!
node --version
npm --version
echo.

echo Instalando dependencias...
echo (Isso pode demorar 1-2 minutos)
echo.
npm install

if errorlevel 1 (
    echo.
    echo [ERRO] Falha ao instalar dependencias!
    echo.
    echo Tente rodar manualmente:
    echo   npm cache clean --force
    echo   npm install
    echo.
    pause
    exit
)

echo.
echo ════════════════════════════════════════════════
echo   ✓ Instalacao concluida com sucesso!
echo ════════════════════════════════════════════════
echo.
echo Para rodar o bot, execute: RODAR.bat
echo Ou digite: npm start
echo.
pause
