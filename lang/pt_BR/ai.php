<?php

/*
AI: the Reword and Translate menus in the editors, the AI error messages agents see, the client briefing periods and the AI triage line in the ticket history (task 8.3).
 */

return [

    // Reword and Translate in the editors
    'Reword - clearer and more professional' => 'Reescrever - mais claro e profissional',
    'Make it shorter' => 'Deixar mais curto',
    'More formal' => 'Mais formal',
    'Friendlier' => 'Mais amigável',
    'Plain English, for someone non-technical' => 'Linguagem simples, para quem não é técnico',
    'Organize it - headings and lists' => 'Organizar - títulos e listas',
    'Just fix spelling and grammar' => 'Só corrigir ortografia e gramática',
    'Translate...' => 'Traduzir...',

    // Errors
    'No AI model is set up for %s. Add one in Admin > AI Providers.' => 'Nenhum modelo de IA está configurado para %s. Adicione um em Admin > Provedores de IA.',
    'Could not reach the AI provider.' => 'Não foi possível acessar o provedor de IA.',
    'The AI provider turned the request away (HTTP %d) - see Admin > App Logs.' => 'O provedor de IA recusou a solicitação (HTTP %d) - veja Admin > Logs do aplicativo.',
    'The AI provider returned an unexpected response - see Admin > App Logs.' => 'O provedor de IA retornou uma resposta inesperada - veja Admin > Logs do aplicativo.',
    'The AI provider answered with nothing to use - try again.' => 'O provedor de IA não respondeu nada aproveitável - tente de novo.',
    'Say what it is for first.' => 'Diga primeiro para que é.',
    'The AI provider\'s answer couldn\'t be read - try again.' => 'Não foi possível ler a resposta do provedor de IA - tente de novo.',
    'The AI provider answered with no lines to use - say a little more about what it is for.' => 'O provedor de IA não respondeu nenhuma linha aproveitável - diga um pouco mais sobre para que é.',
    'The AI provider answered with nothing - try again.' => 'O provedor de IA não respondeu nada - tente de novo.',
    'Say what the document is for first.' => 'Diga primeiro para que é o documento.',
    'The AI provider answered with nothing to keep - try again.' => 'O provedor de IA não respondeu nada que valha guardar - tente de novo.',
    'There is nothing in it to review yet.' => 'Ainda não há nada nele para revisar.',
    'The database needs updating first - Maintenance > Update.' => 'O banco de dados precisa ser atualizado primeiro - Manutenção > Atualizar.',
    'The AI provider suggested no category or priority it could use - try again.' => 'O provedor de IA não sugeriu nenhuma categoria ou prioridade utilizável - tente de novo.',

    // Client briefing periods
    'The last 30 days' => 'Os últimos 30 dias',
    'The last 90 days - a quarterly review' => 'Os últimos 90 dias - uma revisão trimestral',
    'The last year' => 'O último ano',

    // AI triage, in the ticket history
    'the category from %1$s to %2$s' => 'a categoria de %1$s para %2$s',
    'the priority from %1$s to %2$s' => 'a prioridade de %1$s para %2$s',
    '%1$s set %2$s, as AI triage suggested' => '%1$s mudou %2$s, como a triagem por IA sugeriu',
    'AI triage' => 'Triagem por IA',

    // Use cases (aiUseCases() keys)
    'ai use case::General' => 'Geral',
    'ai use case::Tickets' => 'Chamados',
    'ai use case::Documentation' => 'Documentação',
    'ai use case::Billing' => 'Faturamento',

    // Translate languages (aiLanguages())
    'language::English' => 'Inglês',
    'language::Spanish' => 'Espanhol',
    'language::French' => 'Francês',
    'language::German' => 'Alemão',
    'language::Portuguese' => 'Português',
    'language::Italian' => 'Italiano',
    'language::Dutch' => 'Holandês',
    'language::Polish' => 'Polonês',
    'language::Swedish' => 'Sueco',
    'language::Danish' => 'Dinamarquês',
    'language::Norwegian' => 'Norueguês',
    'language::Finnish' => 'Finlandês',
    'language::Czech' => 'Tcheco',
    'language::Romanian' => 'Romeno',
    'language::Hungarian' => 'Húngaro',
    'language::Greek' => 'Grego',
    'language::Turkish' => 'Turco',
    'language::Russian' => 'Russo',
    'language::Ukrainian' => 'Ucraniano',
    'language::Arabic' => 'Árabe',
    'language::Hebrew' => 'Hebraico',
    'language::Hindi' => 'Hindi',
    'language::Vietnamese' => 'Vietnamita',
    'language::Thai' => 'Tailandês',
    'language::Indonesian' => 'Indonésio',
    'language::Filipino' => 'Filipino',
    'language::Chinese (Simplified)' => 'Chinês (simplificado)',
    'language::Chinese (Traditional)' => 'Chinês (tradicional)',
    'language::Japanese' => 'Japonês',
    'language::Korean' => 'Coreano',
    'category::none' => 'nenhuma',

    // Block 10 - the last screens
    'your deployment name' => 'o nome da sua implantação',
    'the model loaded in LM Studio' => 'o modelo carregado no LM Studio',
    'Other (OpenAI-compatible)' => 'Outro (compatível com OpenAI)',
    'its model name' => 'o nome do modelo',
    'Kind %1$s → %2$s' => 'Tipo %1$s → %2$s',
    'Address %1$s → %2$s' => 'Endereço %1$s → %2$s',
    'API key added' => 'Chave de API adicionada',
    'API key removed' => 'Chave de API removida',
    'API key changed' => 'Chave de API alterada',
    'Passwords and keys taken out of what is sent' => 'Senhas e chaves retiradas do que é enviado',
    'Passwords and keys sent as they are' => 'Senhas e chaves enviadas como estão',
    'Provider %1$s → %2$s' => 'Provedor %1$s → %2$s',
    'Model %1$s → %2$s' => 'Modelo %1$s → %2$s',
    'For %1$s → %2$s' => 'Para %1$s → %2$s',
    'Temperature %1$s → %2$s' => 'Temperatura %1$s → %2$s',
    'Instructions removed' => 'Instruções removidas',
    'Instructions added' => 'Instruções adicionadas',
    'Instructions changed' => 'Instruções alteradas',

];
