# SAS Estoque — Grupo Sabor Paraense

Sistema privado de estoque e operação das lojas do Grupo Sabor Paraense. A API é Laravel 12. O painel é web. Cada loja pode ter um PDV local que vende sem internet e sincroniza depois.

Repositório privado. O endereço da API em produção é [api.gruposaborparaense.com.br](https://api.gruposaborparaense.com.br).

## O que o sistema cobre

- Dashboard, produtos, unidades, locais e lotes
- Entradas, saídas e movimentações
- Compras e relatórios
- Usuários e permissões
- PDV local por loja, em `pdv-local`

## Como subir na máquina

1. No `backend`, copie `.env.example` para `.env`, preencha o banco e rode `composer install` e `php artisan key:generate`.
2. Inicie a API e o painel com `iniciar-tudo.bat` ou com `iniciar-servidores.ps1`.
3. Abra o painel no endereço local indicado pelo script. Não abra o HTML direto no disco.

O `.env` fica só na máquina. Ele não entra no Git.
