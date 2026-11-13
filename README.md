# LogiControl — Versão 2.0 para XAMPP

Sistema de gerenciamento logístico em PHP + MySQL/MariaDB, com ações de cadastro, edição, exclusão, geração de fretes e relatório CSV.

## Funcionalidades
- Dashboard com totais de produtos, endereços, transportadores e fretes.
- Cadastro, listagem, edição e exclusão de produtos.
- Cadastro, listagem, edição e exclusão de endereços.
- Cadastro, listagem, edição e exclusão de transportadores.
- Geração de frete com transportador, origem, destino e vários produtos/quantidades.
- Atualização do status do frete.
- Exportação de fretes de uma data em CSV.
- PDO com prepared statements, escape de saída e proteção CSRF em formulários POST.

## Requisitos
- Windows com XAMPP instalado.
- Apache e MySQL iniciados no painel do XAMPP.
- PHP 8.1 ou superior recomendado (a aplicação usa recursos modernos do PHP).

## Instalação no XAMPP
1. Extraia a pasta `LogiControl_V2_XAMPP`.
2. Copie essa pasta para `C:\xampp\htdocs\`.
3. Renomeie a pasta copiada para `logicontrol` (recomendado).
4. Abra o XAMPP Control Panel e inicie **Apache** e **MySQL**.
5. Acesse `http://localhost/phpmyadmin`.
6. Crie um banco de dados chamado `logicontrol` (collation `utf8mb4_unicode_ci`, se solicitado).
7. Selecione o banco `logicontrol`, clique em **Importar**, escolha `database/logicontrol.sql` e execute.
8. Acesse `http://localhost/logicontrol`.

## Configuração do banco
O arquivo `config.php` já vem com os padrões do XAMPP:
- Host: `127.0.0.1`
- Banco: `logicontrol`
- Usuário: `root`
- Senha: em branco

Se o MySQL do seu XAMPP tiver senha, altere `DB_PASS` em `config.php`.

## Teste inicial recomendado
1. Cadastre dois produtos.
2. Cadastre dois endereços: um de origem e outro de destino.
3. Cadastre um transportador.
4. Gere um frete selecionando os dois produtos e quantidades.
5. Atualize o status do frete.
6. Em Relatórios, escolha a data do frete e baixe o CSV.
7. Teste editar e excluir registros que não estejam vinculados a fretes.

## Observações
- O arquivo `docker-compose.yml` e o `Dockerfile` permanecem no pacote como referência para a versão Docker; para executar no XAMPP, não é necessário usar Docker.
- O CSS e Bootstrap são carregados por CDN; a conexão com a internet é necessária para carregar o visual Bootstrap.
- Se aparecer erro de conexão, confira se o MySQL está iniciado e se os dados em `config.php` correspondem à configuração local.
- Faça backup do banco antes de apagar ou recriar tabelas.
