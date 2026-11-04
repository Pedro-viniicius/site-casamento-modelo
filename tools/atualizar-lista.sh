#!/usr/bin/env bash
# Atualiza os preços da lista de presentes: recoleta na Amazon SÓ os termos que alimentam a lista
# (os 18 de presentes + os termos das ofertas escolhidas hoje) e reenvia as ofertas ao site.
#
# Uso (no SEU computador, com o Etiqueta ligado):
#   bash tools/atualizar-lista.sh                      # usa o endereço salvo em config/site.txt
#   bash tools/atualizar-lista.sh https://seudominio.com.br
#   bash tools/atualizar-lista.sh http://localhost:8000 /caminho/import_key.txt   # teste local
#
# - Usa um arquivo de progresso TEMPORÁRIO (ACHADINHOS_FEITOS): o progresso normal do Etiqueta não muda.
# - Itens que já existem no Etiqueta são atualizados (preço, nota), não duplicados.
# - Ofertas do Mercado Livre e da Shopee mantêm o preço da última coleta delas (a coleta do ML é pelo console do Chrome).
# - O token do Etiqueta e a IMPORT_KEY são lidos de arquivo pelo importador e nunca aparecem na tela.
set -euo pipefail

ETQ="${ETIQUETA_DIR:-$HOME/etiqueta}"
RAIZ="$(cd "$(dirname "$0")/.." && pwd)"
SITE="${1:-$(cat "$RAIZ/config/site.txt" 2>/dev/null || true)}"
[ -n "$SITE" ] || { echo "Informe o site: bash tools/atualizar-lista.sh https://seudominio.com.br (ou salve o endereço em config/site.txt)"; exit 1; }
CHAVE="${2:-$RAIZ/config/import_key.txt}"

curl -s -o /dev/null --max-time 3 http://127.0.0.1:8765/ || { echo "O Etiqueta não está no ar. Rode: cd $ETQ && python3 servidor.py"; exit 1; }

FEITOS="$(mktemp)"
ABAS="$(mktemp)"
trap 'rm -f "$FEITOS" "$ABAS"' EXIT

# Monta o progresso temporário com TODOS os termos marcados, menos os da lista; e descobre em quais abas eles estão.
python3 -I - "$ETQ/buscas.json" "$RAIZ/data/ofertas-etiqueta.json" "$FEITOS" "$ABAS" <<'PY'
import json, sys, urllib.request
buscas_arq, ofertas_arq, feitos_arq, abas_arq = sys.argv[1:5]
buscas = json.load(open(buscas_arq, encoding='utf-8'))
PRESENTES = {'Torradeira elétrica', 'Batedeira planetária', 'Umidificador de ar', 'Lavadora de alta pressão', 'Climatizador de ar',
             'Balança de cozinha digital', 'Manta para sofá', 'Assadeira antiaderente', 'Jogo de taças de vinho', 'Forma de bolo antiaderente',
             'Aparelho de fondue', 'Taças de sobremesa', 'Boleira com tampa', 'Frigideira antiaderente', 'Conjunto de bowls de cerâmica',
             'Jogo de xícaras de café com pires', 'Panela de pressão 4,5 litros', 'Abajur de chão'}
# Termos que trouxeram as ofertas escolhidas hoje (API local de itens do Etiqueta, só leitura, sem token).
chaves = {o['chave_origem'] for p in json.load(open(ofertas_arq, encoding='utf-8'))['presentes'] for o in p['ofertas'] if o['loja'] == 'amazon'}
try:
    itens = json.load(urllib.request.urlopen('http://127.0.0.1:8765/api/items', timeout=20))
    PRESENTES |= {i['busca'] for i in itens if f"{i['loja']}:{i['item_id']}" in chaves and i.get('busca')}
except Exception as e:
    print(f'(aviso: não li os termos das ofertas atuais: {type(e).__name__}; sigo só com os 18 de presentes)')
alvo = {t for ts in buscas.values() for t in ts if t in PRESENTES}
with open(feitos_arq, 'w', encoding='utf-8') as f:
    for ts in buscas.values():
        for t in ts:
            if t not in alvo:
                f.write(f'busca|{t}\n')
abas = [aba for aba, ts in buscas.items() if any(t in alvo for t in ts)]
open(abas_arq, 'w', encoding='utf-8').write('\n'.join(abas) + '\n')
print(f'{len(alvo)} termos para recoletar em {len(abas)} aba(s): {", ".join(abas)}')
PY

cd "$ETQ"
while IFS= read -r aba; do
  [ -n "$aba" ] && ACHADINHOS_FEITOS="$FEITOS" python3 coletar_amazon.py buscas --cat "$aba"
done < "$ABAS"

cd "$RAIZ"
node tools/importar-etiqueta.mjs --enviar "$SITE" --chave-arquivo "$CHAVE"
echo "Pronto. Confira OFERTAS.md (mudanças de oferta) e o site: $SITE/#presentes"
