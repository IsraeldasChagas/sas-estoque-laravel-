# PDV Local — 1 instalável por loja (unidade)

Cada loja instala **seu** PDV local, amarrado à **própria unidade e estoque**.

## O que faz

- Roda na máquina da loja (intranet)
- Caixa vende sem internet
- Atendente lança pedidos pelo celular/tablet na Wi‑Fi da loja
- Quando a internet volta, sincroniza as vendas com o SAS (`POST /pdv/vendas/balcao`)

## Instalação rápida (Windows)

1. Instale [Node.js 22+](https://nodejs.org/) (usa SQLite nativo do Node)
2. Abra PowerShell nesta pasta **ou** dê dois cliques em `start-pdv-local.bat`:

```bat
cd c:\gruposaborparaense\sas-estoque-laravel\pdv-local
npm install
npm run setup
npm run pull
npm start
```

3. No setup (`npm run setup` ou `http://127.0.0.1:8787/setup`) informe:
   - URL da API SAS (ex.: `https://api.gruposaborparaense.com.br/api`)
   - `unidade_id` desta loja
   - `usuario_id` (caixa/operador que sincroniza)
   - token Bearer (se a API exigir)

4. Acesse na loja:
   - Caixa: `http://IP-DO-PC:8787/caixa`
   - Atendente: `http://IP-DO-PC:8787/atendente`
   - Setup: `http://IP-DO-PC:8787/setup`

Descubra o IP do PC com `ipconfig` (ex.: `192.168.0.50`).
Atendentes entram só pela Wi‑Fi da loja nesse IP.

## Comandos

| Comando | Uso |
|---------|-----|
| `npm start` | Sobe o servidor local (porta 8787) |
| `npm run setup` | Configura unidade / API |
| `npm run pull` | Baixa produtos/config da unidade (precisa internet) |
| `npm run sync` | Envia vendas pendentes ao SAS |

## Fluxo

1. Com internet: `npm run pull` (ou botão “Atualizar catálogo” no setup)
2. Sem internet: caixa e atendente continuam no servidor local
3. Internet voltou: sync automático a cada 30s + botão manual

## Dados locais

- Banco: `data/pdv-local.sqlite`
- Config: `data/config.json`

## Observações

- NFC-e **não** emite offline; se marcada na venda, o SAS tenta ao sincronizar
- Este MVP cobre **venda/pedido da unidade**; não substitui todo o PDV web do SAS
- Um instalável = uma unidade. Não misture lojas no mesmo PC
