# Roteiro de testes manuais — LogiControl v2

1. Subir containers e abrir `http://localhost:8080`.
2. Cadastrar dois produtos (ex.: Teclado e Monitor).
3. Cadastrar dois endereços (um como origem e outro como destino).
4. Cadastrar um transportador.
5. Gerar um frete selecionando transportador, origem, destino e os dois produtos, com quantidades diferentes.
6. Confirmar que o frete aparece na listagem com código automático.
7. Alterar status do frete para “Em trânsito” e depois “Entregue”.
8. Abrir Relatórios, escolher a data do frete e baixar o CSV.
9. Testar edição e exclusão de produtos, endereços e transportadores sem vínculos.
10. Reiniciar containers com `docker compose restart` e confirmar que os dados continuam salvos.

Registre capturas de tela e resultados. Não considere o projeto aprovado até concluir esses testes no ambiente Docker.
