# Glossário pt-BR

Termos e convenções da tradução da interface (`lang/pt_BR.php`). Termos **fixados** foram decididos e não mudam sem nova decisão; termos **propostos** valem até alguém revisar.

## Termos

| Inglês | pt-BR | Situação |
|---|---|---|
| Ticket | Chamado | fixado |
| Agent / Technician | Técnico | fixado |
| Quote | Orçamento | fixado |
| Asset | Ativo | fixado |
| Client | Cliente | proposto |
| Contact | Contato | proposto |
| Location | Local | proposto |
| Vendor | Fornecedor | proposto |
| Invoice | Fatura | proposto |
| Recurring invoice | Fatura recorrente | proposto |
| Payment | Pagamento | proposto |
| Autopay | Cobrança automática | proposto |
| Late fee | Multa por atraso | proposto |
| Due date | Vencimento | proposto |
| Net terms | Prazo de pagamento | proposto |
| Statement | Extrato | proposto |
| Expense | Despesa | proposto |
| Income | Receita | proposto |
| Unbilled time | Horas não faturadas | proposto |
| Billable | Faturável | proposto |
| Contract | Contrato | proposto |
| Prepaid hours | Horas pré-pagas | proposto |
| Credential | Credencial | proposto |
| Domain | Domínio | proposto |
| Certificate | Certificado | proposto |
| License | Licença | proposto |
| Network | Rede | proposto |
| Project | Projeto | proposto |
| Calendar | Agenda | proposto |
| Dashboard | Painel | proposto |
| Canned response | Resposta pronta | proposto |
| Client portal | Portal do cliente | proposto |
| Share link | Link compartilhado | proposto |
| Role | Perfil de acesso | proposto |
| Settings | Configurações | proposto |
| Administration / Admin | Administração | proposto |
| Maintenance | Manutenção | proposto |
| Get started | Primeiros passos | proposto |
| Audit log | Log de auditoria | proposto |
| Archive (verbo) | Arquivar | proposto |
| Delete | Excluir | proposto |

Mantidos em inglês: Backup, Cron, Rack, Software, Tag, SLA, MFA, API, e nomes de produtos e empresas (Stripe, Microsoft 365, Google Workspace).

## Valores com contexto

Status e prioridades gravados no banco **nunca** são traduzidos na gravação; só na exibição, com `_x()` e contexto explícito.

| Contexto | Inglês | pt-BR |
|---|---|---|
| `ticket priority` | Low / Medium / High / Urgent | Baixa / Média / Alta / Urgente |
| `invoice status` | Draft / Sent / Viewed / Partial / Paid / Overdue / Cancelled | Rascunho / Enviada / Visualizada / Parcial / Paga / Vencida / Cancelada |
| `quote status` | Draft / Sent / Viewed / Accepted / Declined / Invoiced | Rascunho / Enviado / Visualizado / Aceito / Recusado / Faturado |

Os nomes de status de chamado (New, Open, On Hold, Resolved, Closed) são **dados** do banco, editáveis em Admin, e não textos do código.

## Estilo

- Caixa de sentença: "Adicionar chamado", não "Adicionar Chamado".
- Botões e ações no infinitivo: "Salvar", "Excluir chamado".
- Tratamento neutro, sem "você" quando der para evitar; quando não der, "você".
- Concordância de gênero com o termo do glossário: chamado (m), fatura (f), orçamento (m), ativo (m).
- Datas pelo `formatDate()`, nunca montadas à mão na tradução.
- Em en_US o `formatDate()` coloca vírgula antes da hora e um espaço fino não separável (U+202F) antes de AM/PM (`Nov 5, 2026, 3:04 PM`), diferente do antigo `date('M j, Y g:i A')`. Por isso ele serve só para exibir, nunca para CSV ou comparação.
