# Proposal

## Why

O rodapé do portal do cliente mostra "Desenvolvido com ITFlow", que fala do software e não da empresa. A empresa quer mostrar ali a própria frase, hoje "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração", e poder trocá-la sem mudar código. No cabeçalho, o menu fica logo depois da logo, encostado nela, e deve ficar centralizado.

## What Changes

- **Configuração nova:** "Texto do rodapé", em Admin > Configurações > Portal do cliente, numa seção "Rodapé". O campo aceita texto puro, até 200 caracteres.
  - Preenchido: o rodapé do portal mostra o nome da empresa e esse texto, exatamente como foi digitado. O texto não é traduzido e aparece com ou sem white-label.
  - Vazio: o rodapé fica como hoje, com "Powered by ITFlow" traduzido e escondido pelo white-label.
  - A mudança entra no audit log como as outras configurações.
- **Banco:** coluna `settings.config_client_portal_footer_text` em `db.sql` e na atualização de banco `3.5.7`. A atualização não preenche nenhum texto; a frase da empresa é configurada depois, pela tela.
- **Cabeçalho:** em telas largas, o menu passa a ficar centralizado no espaço entre a logo e os botões da direita ("Novo chamado" e a conta), e não mais encostado na logo. No celular nada muda.
- **Fora do escopo:** o "Powered by ITFlow" da tela de login, das páginas de convidado e da exportação do cliente em PDF continua igual.

## Capabilities

### New Capabilities
- `client-portal-layout`: o que o cabeçalho, a navegação e o rodapé do portal do cliente mostram e onde ficam, inclusive o texto configurável do rodapé. A área ainda não tinha spec; a spec nasce do comportamento atual, conferido no código, e já com as duas mudanças.

### Modified Capabilities
<!-- Nenhuma. `client-portal-access` trata de quem vê o quê, não do layout. -->

## Impact

- **Código:**
  - portal: `client/includes/footer.php`, `functions/portal.php` (leitura do texto) e `css/itflow_custom.css` (menu);
  - admin: `admin/settings_client_portal.php`, `admin/post/settings_client_portal.php` e `functions/settings.php` (registro do campo para a busca de configurações).
- **Banco:** `db.sql` e `admin/database_updates/3.5.7.php`.
  - Até a atualização rodar, o portal mostra o rodapé de hoje e a tela não oferece o campo.
- **Tradução:** rótulo e ajuda do campo em `lang/pt_BR/settings.php`. O texto do rodapé não passa pela tradução.
- **Produção:** depois de atualizar, o administrador precisa preencher o texto em Admin > Configurações > Portal do cliente.
