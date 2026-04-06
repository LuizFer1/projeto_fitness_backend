# Plataforma Fitness — Documento de Requisitos de Sistema

> **Versão:** 1.0  
> **Backend:** Laravel | **Frontend:** React

---

## 1. Visão Geral do Sistema

Hub central para pessoas que desejam melhorar saúde e qualidade de vida. A IA atua como treinador físico e nutricionista pessoal, recomendando treinos, excluindo exercícios que possam agravar lesões, e planejando alimentações balanceadas com base em alergias e preferências do usuário. A plataforma conta com gamificação (leaderboard semanal, mensal e geral) e rede social integrada para fortalecer a comunidade.

### Módulos do sistema

- Autenticação e gerenciamento de conta
- Cadastro e acompanhamento de metas
- Geração e ajuste de planos de treino via IA
- Geração e ajuste de planos alimentares via IA
- Gamificação: XP, níveis, leaderboard e conquistas
- Rede social: amigos, feed e compartilhamento de conquistas

---

## 2. Requisitos Funcionais

### 2.1 Módulo de Autenticação

---

#### `RF-01` — Cadastro de usuário · **Prioridade: Alta**

O sistema deve permitir criar conta com e-mail, senha e dados completos de perfil.

- E-mail deve ser único e validado por link de verificação
- Senha mínima de 8 caracteres com letras e números
- Coletar: nome, data de nascimento, peso, altura e objetivo principal
- Coletar alergias alimentares e preferências/restrições alimentares
- Coletar histórico de lesões para filtragem de treinos pela IA

---

#### `RF-02` — Login · **Prioridade: Alta**

Autenticar usuário por e-mail/senha ou OAuth (Google).

- Retornar token JWT via Laravel Sanctum
- Bloquear após 5 tentativas falhas por 15 minutos
- Suporte a "lembrar dispositivo" por 30 dias

---

#### `RF-03` — Logout · **Prioridade: Alta**

Invalidar o token do dispositivo atual ou de todos os dispositivos.

- `POST /auth/logout` — revoga o token atual
- `POST /auth/logout-all` — revoga todos os tokens do usuário

---

#### `RF-04` — Alteração de senha (logado) · **Prioridade: Média**

Usuário autenticado pode alterar a senha fornecendo a senha atual.

- Validar senha atual antes de aceitar a nova
- Notificar por e-mail após alteração bem-sucedida

---

#### `RF-05` — Recuperação de senha (esqueceu) · **Prioridade: Alta**

Fluxo de reset por e-mail para usuários não autenticados.

- Enviar link com token de vida útil de 1 hora
- Token de uso único — invalidar após uso
- Não revelar se o e-mail existe ou não (prevenção de enumeração)

---

### 2.2 Módulo de Metas

---

#### `RF-06` — Cadastro de metas · **Prioridade: Alta**

Usuário pode criar metas pessoais de saúde e fitness.

- Tipos: perda de peso, ganho de massa, distância percorrida, calorias, frequência de treino
- Campos obrigatórios: título, tipo, valor alvo, data limite, unidade de medida
- Meta pode ser pública (visível a amigos) ou privada

---

#### `RF-07` — Acompanhamento de metas · **Prioridade: Alta**

Usuário pode registrar progresso e visualizar evolução.

- Registrar checkins de progresso com data e valor atual
- Calcular e exibir percentual concluído
- Notificar quando meta for atingida (push e e-mail)
- Histórico de progresso com gráfico de linha

---

#### `RF-08` — Edição e arquivamento de metas · **Prioridade: Média**

Usuário pode editar ou arquivar metas existentes sem perder histórico.

- Editar título, valor alvo e data limite
- Arquivar meta sem excluir histórico de progresso
- Metas concluídas ou expiradas geram conquista automaticamente

---

### 2.3 Módulo de IA e Planos

> **Atenção:** Todos os endpoints de geração de planos devem ser assíncronos. O fluxo correto é: requisição cria um job na fila (Laravel Queue) → retorna `202 Accepted` com ID do job → worker processa → notifica o usuário via push/WebSocket ao concluir. Falhas na IA não devem impactar o restante da aplicação.

---

#### `RF-09` — Geração de plano de treino · **Prioridade: Alta**

A IA gera plano semanal de treino personalizado com base no perfil do usuário.

- Considerar: objetivo, nível de condicionamento e equipamentos disponíveis
- Excluir exercícios que agravem lesões cadastradas no perfil
- Plano estruturado por dias: exercícios, séries, repetições e descanso
- Geração assíncrona via fila — notificar ao concluir

---

#### `RF-10` — Geração de plano alimentar · **Prioridade: Alta**

A IA gera plano alimentar semanal considerando saúde, alergias e preferências.

- Considerar: alergias, intolerâncias, alimentos preferidos e rejeitados
- Calcular macros (proteína, carboidrato, gordura) e calorias por refeição
- Estrutura: café da manhã, almoço, lanche, jantar e ceia
- Sugestões de substituição para cada item da refeição

---

#### `RF-11` — Comentários e ajuste do plano via IA · **Prioridade: Alta**

Usuário comenta em itens específicos ou no plano completo para refinar via IA.

- Comentário pode ser em: item individual, dia inteiro ou plano completo
- IA lê os comentários e o plano atual para gerar versão revisada
- Histórico de versões do plano com comparativo para o usuário
- Limite configurável de refinamentos por plano por dia (ex.: 3)

---

#### `RF-12` — Visualização dos planos · **Prioridade: Alta**

Usuário pode navegar e consultar seu plano ativo a qualquer momento.

- Navegação por dia da semana e por refeição/treino
- Marcar itens como concluídos (checkin de execução)
- Planos anteriores disponíveis para consulta em histórico

---

### 2.4 Módulo Social

---

#### `RF-13` — Adicionar amigos · **Prioridade: Média**

Usuário pode encontrar e adicionar outros usuários como amigos.

- Busca por nome de usuário ou e-mail
- Sistema de solicitação: enviar, aceitar, recusar e cancelar
- Feed de atividades filtrado por amigos
- Opção de bloquear usuário

---

#### `RF-14` — Compartilhar conquistas · **Prioridade: Média**

Usuário pode compartilhar conquistas na plataforma e em redes sociais externas.

- Compartilhar na timeline interna da plataforma (post automático)
- Gerar imagem/card para compartilhamento externo (Instagram, WhatsApp)
- Conquistas compartilháveis: metas batidas, sequências de treino, XP milestone, posição no leaderboard
- Controle de privacidade por conquista: público ou somente amigos

---

#### `RF-15` — Feed / timeline da plataforma · **Prioridade: Média**

Feed social interno exibindo atividades de amigos e conquistas.

- Curtir e comentar em posts de conquistas
- Suporte a posts textuais curtos pelos usuários
- Moderação básica de conteúdo impróprio

---

### 2.5 Módulo de Gamificação

---

#### `RF-16` — Sistema de XP e níveis · **Prioridade: Alta**

Usuário acumula pontos de experiência por ações na plataforma.

- Ações que geram XP: concluir treino, seguir plano alimentar, atingir meta, fazer checkin diário
- Níveis com nomes temáticos (ex.: Iniciante, Atleta, Elite)
- Barra de progresso de XP visível no perfil do usuário

---

#### `RF-17` — Leaderboard · **Prioridade: Alta**

Ranking dos usuários por XP acumulado em diferentes períodos.

- Rankings disponíveis: semanal, mensal e geral (all-time)
- Exibir posição do usuário mesmo fora do top N
- Recalculado periodicamente via job agendado — não em tempo real
- Leaderboard entre amigos com escopo reduzido
- Resultado servido do cache Redis para evitar queries ao banco

---

#### `RF-18` — Conquistas (badges) · **Prioridade: Média**

Medalhas desbloqueadas automaticamente por marcos atingidos.

- Categorias: metas, sequência de dias, social e leaderboard
- Notificação push ao desbloquear conquista
- Conquistas visíveis no perfil público do usuário

---

## 3. Requisitos Não Funcionais

### 3.1 Desempenho — `RNF-01`

- APIs CRUD: resposta em até 300ms (percentil 95)
- Geração de plano pela IA: até 30 segundos (processamento assíncrono)
- Leaderboard: servido do cache Redis, sem consulta direta ao banco
- Suportar 500 usuários simultâneos sem degradação perceptível

### 3.2 Segurança — `RNF-02`

- Autenticação stateless via JWT (Laravel Sanctum)
- Senhas com bcrypt, custo mínimo 12
- Rate limiting em todos os endpoints públicos
- Proteção contra OWASP Top 10
- Dados de saúde criptografados em repouso
- HTTPS obrigatório em todos os ambientes

### 3.3 Escalabilidade — `RNF-03`

- Arquitetura stateless — escalável horizontalmente com múltiplas instâncias
- Geração de IA isolada em workers independentes
- Banco com réplica de leitura para relatórios e leaderboard
- CDN para assets estáticos e imagens de conquistas

### 3.4 Disponibilidade — `RNF-04`

- Uptime alvo: 99,5% mensal
- Filas garantem que falha da IA não derruba a API principal
- Circuit breaker no serviço de IA externo
- Health check endpoint para monitoramento automatizado

### 3.5 Manutenibilidade — `RNF-05`

- Cobertura de testes unitários ≥ 80% nos Services e Actions
- API versionada desde o início (`/api/v1/`)
- Logs estruturados em JSON com request ID rastreável
- Documentação OpenAPI gerada automaticamente

### 3.6 Privacidade / LGPD — `RNF-06`

- Usuário controla visibilidade de cada dado de saúde individualmente
- Direito ao esquecimento: exclusão completa e permanente de dados
- Consentimento explícito antes de usar dados pessoais para geração de planos pela IA
- Conformidade com LGPD — base legal registrada para cada tipo de dado processado

### 3.7 Padrão de API — `RNF-07`

- Respostas padronizadas com API Resources do Laravel
- Erros com: código HTTP, mensagem legível e campo inválido
- Paginação cursor-based para listas grandes
- Suporte a internacionalização: pt-BR e en

### 3.8 Observabilidade — `RNF-08`

- Rastreamento de erros via Sentry
- Métricas de filas via Laravel Horizon Dashboard
- Alertas automáticos para latência acima do SLA
- Logs de auditoria para ações sensíveis (login, exclusão de dados, redefinição de senha)

---

## 4. Resumo de Requisitos

| ID | Requisito | Módulo | Prioridade |
|----|-----------|--------|------------|
| RF-01 | Cadastro de usuário | Autenticação | Alta |
| RF-02 | Login | Autenticação | Alta |
| RF-03 | Logout | Autenticação | Alta |
| RF-04 | Alteração de senha (logado) | Autenticação | Média |
| RF-05 | Recuperação de senha (esqueceu) | Autenticação | Alta |
| RF-06 | Cadastro de metas | Metas | Alta |
| RF-07 | Acompanhamento de metas | Metas | Alta |
| RF-08 | Edição e arquivamento de metas | Metas | Média |
| RF-09 | Geração de plano de treino | IA / Planos | Alta |
| RF-10 | Geração de plano alimentar | IA / Planos | Alta |
| RF-11 | Comentários e ajuste de plano | IA / Planos | Alta |
| RF-12 | Visualização dos planos | IA / Planos | Alta |
| RF-13 | Adicionar amigos | Social | Média |
| RF-14 | Compartilhar conquistas | Social | Média |
| RF-15 | Feed / timeline | Social | Média |
| RF-16 | Sistema de XP e níveis | Gamificação | Alta |
| RF-17 | Leaderboard | Gamificação | Alta |
| RF-18 | Conquistas (badges) | Gamificação | Média |
| RNF-01 | Desempenho | Não funcional | — |
| RNF-02 | Segurança | Não funcional | — |
| RNF-03 | Escalabilidade | Não funcional | — |
| RNF-04 | Disponibilidade | Não funcional | — |
| RNF-05 | Manutenibilidade | Não funcional | — |
| RNF-06 | Privacidade / LGPD | Não funcional | — |
| RNF-07 | Padrão de API | Não funcional | — |
| RNF-08 | Observabilidade | Não funcional | — |