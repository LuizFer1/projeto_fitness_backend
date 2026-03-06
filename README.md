<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

# Fitness App Backend API

Este é o repositório que contém toda a API e lógica de negócios do Aplicativo Fitness. É construído sob o ecossistema robusto do **Laravel 11**, fornecendo segurança estrutural com **Sanctum** e um alto nível de coesão nos bancos de dados graças à integração de relacionamentos por UUIDs.

## 🛠 Tecnologias

- **PHP 8.2+**
- **Laravel 11.x**
- **MySQL 8**
- **Laravel Sail** (Dockerização nativa)
- **Laravel Sanctum** (Autenticação Token/SPA)

## 🏛 Arquitetura de Banco de Dados: Padrão ID + UUID

Para garantir segurança anti-enumeração nas APIs e ao mesmo tempo preservar o desempenho a nível de disco e indexação B-tree do MySQL, foi tomada a decisão avançada de utilizar um padrão híbrido no mapeamento objeto-relacional (ORM):

- **Chaves Primárias Interiores (PK)**: Todas as tabelas (`users`, `workouts`, `meals`, etc.) contêm a coluna padrão `$table->id()` auto-incremento para acelerar e enxugar os índices estruturais de armazenamento.
- **Identificadores Criptográficos Externos**: Simultaneamente, cada tabela possui uma coluna `$table->uuid('uuid')->unique()`.
- **Relacionamentos (Foreign Keys) Guiados por UUID**: As chaves estrangeiras entre as tabelas usam os UUIDs das tabelas vizinhas via `foreignUuid('alguma_tabela_uuid')`. Isso requer que toda injeção via Eloquent aponte os campos explícitos.
- **Implementação no Eloquent**: As Models do sistema utilizam customizações do `HasUuids`:

    ```php
    public function uniqueIds(): array { return ['uuid']; }

    // Relacionamento Eloquent:
    public function user() {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
    ```

## 🧩 Principais Domínios do Sistema

O banco de dados é robusto e possui cerca de 27 tabelas organizadas em features modulares:

1.  **Usuários & Gestão de Acesso** (`users`, `sessions`, `personal_access_tokens`)
    - Gerencia o acesso ao app e a gestão dos tokens gerados para API.
2.  **Onboarding** (`user_onboarding`)
    - Coleta dados de início, altura, peso, gordura corporal estimada para gerar o perfil inicial.
3.  **Metas e Registros Rápidos** (`goals`, `goal_progress_snapshots`, `weight_logs`)
    - Histórico e acompanhamento de oscilação de peso e métricas flexíveis (litros d'água, horas de sono, etc).
4.  **Assinaturas e Monetização** (`plans`, `plan_prices`, `subscriptions`)
    - Planos como (Free, Plus, Pro), permitindo cobranças mensais, anuais e fases de `trialing`.
5.  **Treinamentos Completos** (`workouts`, `workout_exercises`, `exercise_records`, `exercise_weekly`)
    - Rotinas diárias sugeridas pelo sistema, bibliotecas de exercícios musculares, planilhas semanais por objetivo de sessões.
6.  **Nutrição Diária** (`nutrition_daily`, `nutrition_daily_macros`, `meal_groups`, `meals`, `meal_suggestions`)
    - Estrutura contábil calórica com divisão de Macronutrientes. Controle de sugestões de alimentos atrelados às refeições do dia (ex: Café da manhã, Almoço).
7.  **Gamificação e Retenção Social** (`user_gamification`, `xp_ledger`, `missions`, `badges`, `leaderboards`)
    - Sistema central de retenção de XP.
    - Conquista contínua de Badges de progresso.
    - Leaderboards (Rankings) gerados por snapshots baseados no acúmulo temporizado de XP.
    - Missões semanais dinâmicas.

## 🚀 Como Executar o Projeto Localmente

O aplicativo utiliza o [Laravel Sail](https://laravel.com/docs/sail), uma interface de linha de comando voltada à interação dockerizada com o ecossistema Laravel.

### 1. Requisitos

- [Docker](https://www.docker.com/products/docker-desktop) instalado e rodando.
- [Docker Compose](https://docs.docker.com/compose/install/)

### 2. Passo a Passo

Clone o repositório, em seguida navegue até o diretório:

```bash
# 1. Copie o arquivo de variáveis de ambiente
cp .env.example .env

# Se estiver testando localmente, garanta de apontar o .env ao banco de dados interno
# DB_HOST=mysql
# DB_USERNAME=sail
# DB_PASSWORD=password
# DB_DATABASE=projeto_fitness

# 2. Suba o ambiente via Laravel Sail (instalará as dependências PHP via container automático)
./vendor/bin/sail up -d

# 3. Rode os comandos essenciais para a instalação das packages, key generation e cache
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate

# 4. Construa o esquema de banco de dados e instancie os seeders iniciais de Teste OBRIGATORIOS
./vendor/bin/sail artisan migrate:fresh --seed
```

O servidor local ficará ativo na sua porta HTTP `localhost` e port de banco relacional equivalente (padrão porta `3306`).

## 📁 Estrutura de Rotas e Endpoints (Breve API)

O arquivo `routes/api.php` orquestra a camada Rest. As rotas são separadas entre **públicas** (como login e registro de auth) e rotas embutidas com o middleware `auth:sanctum`. Sempre exija o cabeçalho:
`Accept: application/json` e `Authorization: Bearer <seu_token>` para comunicação segura.

---

_Projeto Desenvolvido visando excelência técnica em ecossistemas de saúde e bem-estar (Fitness)._
