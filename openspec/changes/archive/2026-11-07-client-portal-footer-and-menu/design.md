# Design

## Context

Ver proposal.md, "Why". O estado atual:

- **Rodapé** (`client/includes/footer.php:14-21`): `footer.itflow-portal-footer > div.container.d-flex.flex-wrap.justify-content-between`.
  - À esquerda fica o nome da empresa (`$session_company_name`).
  - À direita fica `__('Powered by ITFlow')`, em texto puro e sem link, só quando `$config_whitelabel_enabled` é 0 (linha 17).
  - A tradução pt-BR, "Desenvolvido com ITFlow", está repetida em `lang/pt_BR/access.php:22` e `lang/pt_BR/shared.php:126`.
- **Cabeçalho** (`client/includes/header.php:122-189`): `header.itflow-portal-header > div.container.d-flex.align-items-center.gap-3` com três filhos: a logo (`a.itflow-portal-brand`), o menu (`nav.itflow-portal-nav.d-none.d-lg-flex`, de `portalNav()`) e um `div.ms-auto` com "Novo chamado" (escondido abaixo de 576 px) e a conta. Como só o último bloco tem `ms-auto`, o menu fica logo depois da logo, a `gap-3` (1 rem) dela.
- **Celular** (`header.php:193-232`): barra de abas `nav.itflow-portal-tabbar.d-lg-none` e offcanvas `#portalMenu`. Não muda.
- **CSS:** `css/itflow_custom.css:6890-7030`. Bootstrap 5 com AdminLTE 4 e breakpoint `lg` de 992 px. O portal manda CSP `default-src 'self'`: sem `style=""` nem `<script>` inline.
- **Configurações do portal:**
  - página `admin/settings_client_portal.php`, com o formulário em `:33-80`, seções `settingsSectionStart()` e salvamento por `settingsSaveBar('edit_client_portal_settings')`;
  - handler `admin/post/settings_client_portal.php:13-29`: `validateCSRFToken()`, colunas com `settingsCurrent()` e gravação com `settingsSave('client_portal', ...)`. `settingsSave()` grava via `settingsWrite()`, que passa texto por `escapeSql()`, registra no audit log em inglês e mostra o aviso. A porta de administrador vem de `admin/post.php`;
  - registro dos campos para a busca em `functions/settings.php:263-264`;
  - recusa com erro no campo via `settingsRefuse()` (`functions/settings.php:753`).
- **Colunas novas antes da atualização do banco:** `includes/load_global_settings.php` lista as colunas no `SELECT`. Uma coluna nova ali derruba todas as páginas até a atualização rodar. O padrão do projeto é ler à parte, com checagem da versão do banco: `portalInvitesReady()` (`functions/portal_invite.php:35`, 3.4.6) e o `config_language` com `try/catch` (`functions/i18n.php:66`, 3.5.6). A última atualização no disco é `admin/database_updates/3.5.6.php`.
- **Outros "Powered by ITFlow", fora do escopo:**
  - `login.php:889`: login de técnicos e contatos;
  - `includes/footer.php:34`: páginas de convidado;
  - `functions/client_export.php:1288`: PDF da exportação do cliente.
- **Upstream** (`openspec/changes/archive/2026-11-06-document-system-baseline/design.md`, "Pontos de dependência do upstream"): o white-label depende de chaves validadas pelo itflow.org, que o fork não emite. Com o texto configurado, o rodapé do portal deixa de depender do white-label. Ele nunca teve link para o itflow.org.

## Goals / Non-Goals

**Goals:**
- Um texto do rodapé do portal configurável pelo administrador, que substitui o "Powered by ITFlow" quando preenchido.
- O menu do cabeçalho centralizado entre a logo e os botões da direita, sem mudar nada no celular.
- A spec de base `client-portal-layout`, conferida no código.

**Non-Goals:**
- Tirar o "Powered by ITFlow" do login, das páginas de convidado e da exportação.
- Texto com formatação ou link, ou um texto por idioma.
- Mudar o white-label, as chaves ou o texto de ajuda dele.
- Preencher a frase da empresa pela atualização de banco. O código do fork é genérico, e a frase é configuração de cada instalação.

## Decisions

### D1. O texto do rodapé é uma configuração
- **Coluna:** `settings.config_client_portal_footer_text varchar(200) NOT NULL DEFAULT ''`.
  - Em `db.sql`, fica depois de `config_client_portal_signup`.
  - Em `admin/database_updates/3.5.7.php`, entra com o guarda `FROM_DB_UPDATER`, a checagem de existência da coluna em `information_schema` (para poder rodar de novo) e `AFTER config_client_portal_signup`, no modelo da `3.5.6.php`.
  - O prefixo `config_client_portal_` segue as outras configurações da mesma página. O nome `config_portal_footer_text`, sugerido no pedido, foi ajustado para esse prefixo.
- **Comportamento:**
  - Preenchido: o rodapé mostra o texto com `escapeHtml()`, sem `__()`, com ou sem white-label. O texto é da empresa, então não é traduzido, e o white-label não esconde o que a própria empresa escreveu.
  - Vazio: o rodapé faz o que faz hoje.
- **Alternativa descartada:** um texto fixo e traduzível no código, a chave em inglês com a frase pt-BR. Funcionaria para esta instalação, mas exigiria mudar código a cada troca de frase e levaria uma frase de uma empresa para o código do fork. Também pedia uma exceção à caixa de sentença do `lang/GLOSSARY.md`, que deixa de ser necessária.
- **Alternativa descartada:** uma configuração por idioma. O portal tem um idioma só por instalação (`config_language`), então um campo basta.

### D2. Leitura protegida pela versão do banco
Uma função em `functions/portal.php`, `portalFooterText()`, devolve o texto configurado, ou `''` se o banco ainda é anterior à 3.5.7. A checagem segue `portalInvitesReady()`: `CURRENT_DATABASE_VERSION` quando definido, senão `settings.config_current_database_version`, com `version_compare(..., '3.5.7', '>=')`, num cache estático. A mesma checagem, numa função `portalFooterTextReady()`, controla:
- se a página de configurações mostra a seção "Rodapé";
- se o handler inclui a coluna no salvamento.

A coluna não entra em `load_global_settings.php`.
- *Por quê:* o código chega antes da atualização do banco. Um `SELECT` com a coluna nova quebraria todas as páginas até alguém rodar a atualização. É o mesmo cuidado já tomado na 3.4.6 e na 3.5.6.

### D3. Edição em Admin > Configurações > Portal do cliente
A página ganha uma seção "Rodapé" (`settingsSectionStart('footer', __('Footer'), ...)`) com `settingsInput('config_client_portal_footer_text', ..., ['icon' => 'fa-shoe-prints', 'maxlength' => 200, 'label' => __('Footer text'), 'help' => __('...')])`.
- **Ajuda:** "Shown on every portal page, next to your company name, exactly as typed. Left empty, the footer shows Powered by ITFlow, unless white-label is on."
- **Handler:** quando `portalFooterTextReady()`, inclui a coluna em `$columns`, com `$after[...] = trim((string) $_POST[...])`. Acima de 200 caracteres (`mb_strlen`), chama `settingsRefuse('client_portal', ['config_client_portal_footer_text' => __('...')])` e não salva nada.
- **Registro:** `functions/settings.php` ganha a entrada do campo para a busca de configurações (seção `__('Footer')`, rótulo `__('Footer text')`).
- **Traduções:** os textos novos vão para `lang/pt_BR/settings.php`. "Footer" já existe ("Rodapé").
- **Segurança e auditoria:** o CSRF, a porta de administrador, o audit log e o `escapeSql()` vêm do que a página já usa (`validateCSRFToken()`, `admin/post.php`, `settingsSave()`). O `escapeSql()` tira marcação; como o campo é texto puro, isso é aceitável.

### D4. Centralizar o menu com flex no espaço livre
Em `css/itflow_custom.css`, dentro de `@media (min-width: 992px)`, o `.itflow-portal-nav` ganha `flex: 1 1 auto; justify-content: center; min-width: 0`. O menu ocupa o espaço entre a logo e o bloco da direita, com os itens no meio. Abaixo de 992 px continua com `d-none`.
- *Alternativa descartada:* centralizar no meio exato da página (`grid-template-columns: 1fr auto 1fr`). Com uma logo larga (até 11 rem), o menu ficaria apertado e poderia sobrepor a logo.
- *Alternativa descartada:* só aumentar a margem entre a logo e o menu. Afasta, mas não centraliza.

### D5. A spec nasce com o comportamento atual e as mudanças
`client-portal-layout` não existia nem estava no mapa de capabilities. Pela regra "documentar antes de mudar", ela descreve o comportamento atual, conferido no código e registrado em "Context" (cabeçalho e navegação no celular). As partes que mudam (menu, rodapé e edição do texto) já entram na forma nova.
- *Por quê:* uma delta não pode modificar uma spec que ainda não existe. Arquivar uma change só de documentação antes desta seria um ciclo inteiro para pouco comportamento.

## Risks / Trade-offs

- **[Risco] Código atualizado e banco ainda não.** → D2: o portal mostra o rodapé de hoje e o campo some da tela até a atualização.
- **[Risco] Menu com muitas seções entre 992 e ~1200 px.** O menu não quebra (`white-space: nowrap`) e pode encostar nos dois lados. → Conferir a 992 px com um contato principal. Só agir se o teste mostrar o problema.
- **[Risco] Um texto de 200 caracteres no celular.** O rodapé usa `flex-wrap`, então o texto desce para a linha de baixo. → Conferir a 390 px.
- **[Trade-off] Um idioma só.** Se a instalação trocar o idioma da interface, o texto continua o mesmo. É a empresa que escreve, e há um idioma por instalação.

## Migration Plan

- A atualização `3.5.7` acrescenta a coluna vazia. O comportamento só muda quando o administrador preencher o texto.
- Em produção, depois de atualizar, preencher Admin > Configurações > Portal do cliente > Rodapé com "Presença Digital, Automação e Inteligência Artificial, Produtividade e Integração".
- Para desfazer: reverter o código. A coluna pode ficar, porque nada mais a lê.
