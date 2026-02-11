#!/bin/bash
# Script para converter arquivos da API para retornar JSON puro

echo "=== Conversão de API para JSON ==="
echo ""
echo "Backup já criado em: backups/api_json_20251031_142559"
echo ""

# Lista de arquivos a converter (que retornam HTML)
arquivos_html=(
    "buscar_agendas.php"
    "listar_agendados.php"
    "listar_agendas.php"
    "buscar_bloqueios.php"
    "buscar_convenio_ajax.php"
    "buscar_vagas_agenda.php"
    "listar_agendas_ajax.php"
    "listar_bloqueios.php"
    "listar_paginas.php"
    "verificar_estrutura_tabelas.php"
)

echo "Arquivos a converter:"
for arquivo in "${arquivos_html[@]}"; do
    if [ -f "$arquivo" ]; then
        echo "  ✓ $arquivo"
    else
        echo "  ✗ $arquivo (não encontrado)"
    fi
done

echo ""
echo "Conversão será feita manualmente para cada arquivo"
echo "Total de arquivos: ${#arquivos_html[@]}"
