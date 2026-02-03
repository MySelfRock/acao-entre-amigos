# Etapas 5 & 6 - Autenticação, Perfis e Criação de Eventos

## Resumo

Implementadas as funcionalidades core de autenticação e criação de eventos:

### ✅ Etapa 5: Autenticação e Perfis de Usuário
- Sistema de login com JWT (Laravel Sanctum)
- 4 perfis de usuário com controle de permissões
- Registro de usuários
- Gerenciamento de senhas
- Log de ações administrativas

### ✅ Etapa 6: Módulo de Criação de Evento
- CRUD completo de eventos
- Status do evento (draft → generated → running → finished)
- Geração automática de seed global
- Controle de permissões por papel

---

## 📊 Estrutura Implementada

### Models (9 modelos)
```
User ← Created by Event
Event ← Cards ← Subcards ← SubcardNumbers
Event ← Draws
Event ← BingoClaims ← User (claimed_by)
Event ← Winners
SystemLogs ← User
```

### Migrations (5 migrations)
1. `create_users_table` - Usuários com roles
2. `create_events_table` - Eventos e seed global
3. `create_cards_table` - Cartelas e subcartelas
4. `create_draws_table` - Sorteios, bingo_claims, winners
5. `system_logs` - Auditoria completa

### Services (2 serviços)
- **AuthService**: Registro, login, criação de tokens, logs
- **EventService**: CRUD de eventos, gerenciamento de status

### Controllers (2 controllers)
- **AuthController**: Endpoints de autenticação
- **EventController**: Endpoints de eventos

---

## 🔐 Autenticação

### Registro de Usuário
```bash
POST /api/auth/register
Content-Type: application/json

{
  "name": "João Silva",
  "email": "joao@example.com",
  "phone": "11999999999",
  "password": "senha_segura_123",
  "password_confirmation": "senha_segura_123",
  "role": "jogador"  # opcional: jogador, auditor, operador, admin
}

Response 201:
{
  "message": "User registered successfully",
  "user": {
    "id": "uuid",
    "name": "João Silva",
    "email": "joao@example.com",
    "role": "jogador"
  },
  "token": "api_token_here"
}
```

### Login
```bash
POST /api/auth/login
Content-Type: application/json

{
  "email": "joao@example.com",
  "password": "senha_segura_123"
}

Response 200:
{
  "message": "Login successful",
  "user": {
    "id": "uuid",
    "name": "João Silva",
    "email": "joao@example.com",
    "role": "jogador"
  },
  "token": "api_token_here"
}
```

### Perfil do Usuário
```bash
GET /api/auth/me
Authorization: Bearer token

Response 200:
{
  "user": {
    "id": "uuid",
    "name": "João Silva",
    "email": "joao@example.com",
    "phone": "11999999999",
    "role": "jogador",
    "is_active": true,
    "email_verified_at": "2024-02-03T12:00:00",
    "created_at": "2024-02-03T10:00:00"
  }
}
```

### Logout
```bash
POST /api/auth/logout
Authorization: Bearer token

Response 200:
{
  "message": "Logged out successfully"
}
```

### Trocar Senha
```bash
POST /api/auth/update-password
Authorization: Bearer token
Content-Type: application/json

{
  "current_password": "senha_antiga",
  "password": "senha_nova",
  "password_confirmation": "senha_nova"
}

Response 200:
{
  "message": "Password updated successfully"
}
```

---

## 📋 Eventos

### Papéis e Permissões

| Papel | Criar Evento | Gerar Cartelas | Sortear | Auditar | Jogar |
|-------|---------|----------|---------|---------|--------|
| **admin** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **operador** | ✅ | ✅ | ✅ | ✅ | ✅ |
| **auditor** | ❌ | ❌ | ❌ | ✅ | ❌ |
| **jogador** | ❌ | ❌ | ❌ | ❌ | ✅ |

### Criar Evento
```bash
POST /api/events
Authorization: Bearer token (admin/operador only)
Content-Type: application/json

{
  "name": "Bingo Beneficente",
  "description": "Evento híbrido para caridade",
  "event_date": "2024-05-10 19:00:00",
  "location": "São Paulo, SP",
  "total_cards": 2000,
  "participation_type": "hibrido"  # digital, presencial, hibrido
}

Response 201:
{
  "message": "Event created successfully",
  "event": {
    "id": "uuid",
    "name": "Bingo Beneficente",
    "description": "Evento híbrido para caridade",
    "event_date": "2024-05-10T19:00:00",
    "location": "São Paulo, SP",
    "total_cards": 2000,
    "total_rounds": 5,
    "participation_type": "hibrido",
    "status": "draft",
    "cards_generated": 0,
    "draws_count": 0,
    "winners_count": 0,
    "created_by": "Admin User",
    "created_at": "2024-02-03T12:00:00"
  }
}
```

### Listar Eventos
```bash
GET /api/events?status=draft&participation_type=hibrido
Authorization: Bearer token

Response 200:
{
  "data": [
    {
      "id": "uuid",
      "name": "Bingo Beneficente",
      "event_date": "2024-05-10T19:00:00",
      "status": "draft",
      "total_cards": 2000,
      "participation_type": "hibrido",
      "created_by": "Admin User",
      "created_at": "2024-02-03T12:00:00"
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

### Obter Detalhes do Evento
```bash
GET /api/events/{event_id}
Authorization: Bearer token

Response 200:
{
  "event": {
    "id": "uuid",
    "name": "Bingo Beneficente",
    "description": "Evento híbrido para caridade",
    "event_date": "2024-05-10T19:00:00",
    "location": "São Paulo, SP",
    "total_cards": 2000,
    "total_rounds": 5,
    "participation_type": "hibrido",
    "status": "draft",
    "cards_generated": 0,
    "draws_count": 0,
    "winners_count": 0,
    "started_at": null,
    "finished_at": null,
    "created_by": "Admin User",
    "created_at": "2024-02-03T12:00:00"
  }
}
```

### Atualizar Evento (draft only)
```bash
PUT /api/events/{event_id}
Authorization: Bearer token (creator only)
Content-Type: application/json

{
  "name": "Bingo Beneficente 2024",
  "total_cards": 2500,
  "location": "São Paulo, SP - Sala VIP"
}

Response 200:
{
  "message": "Event updated successfully",
  "event": { ... }
}
```

### Iniciar Evento
```bash
POST /api/events/{event_id}/start
Authorization: Bearer token (creator/operador only)

# Pré-requisito: status = 'generated' (cartelas foram geradas)

Response 200:
{
  "message": "Event started successfully",
  "event": {
    ...
    "status": "running",
    "started_at": "2024-02-03T15:30:00"
  }
}
```

### Finalizar Evento
```bash
POST /api/events/{event_id}/finish
Authorization: Bearer token (creator/operador only)

Response 200:
{
  "message": "Event finished successfully",
  "event": {
    ...
    "status": "finished",
    "finished_at": "2024-02-03T17:00:00"
  }
}
```

---

## 🗄️ Banco de Dados

### Tabelas Criadas

#### users
```sql
id (UUID, PK)
name (VARCHAR)
email (VARCHAR, UNIQUE)
phone (VARCHAR, nullable)
password (VARCHAR, hash)
role (ENUM: admin, operador, auditor, jogador)
is_active (BOOLEAN, default: true)
email_verified_at (TIMESTAMP, nullable)
created_at, updated_at
```

#### events
```sql
id (UUID, PK)
name (VARCHAR)
description (TEXT, nullable)
event_date (DATETIME)
location (VARCHAR, nullable)
total_cards (INTEGER, default: 2000)
total_rounds (INTEGER, default: 5)
seed (VARCHAR) -- NUNCA exposto ao cliente
participation_type (ENUM: digital, presencial, hibrido)
status (ENUM: draft, generated, running, finished)
created_by (UUID, FK users)
started_at, finished_at (TIMESTAMP, nullable)
metadata (JSON, nullable)
created_at, updated_at
```

#### cards, subcards, subcard_numbers
- Estrutura pronta para armazenar as cartelas geradas
- Índices para performance
- Constraints de unicidade

#### draws
- Registro de números sorteados
- Sequência auditável

#### bingo_claims
- Tentativas de bingo
- Validação (is_valid boolean)

#### winners
- Registro oficial de vencedores por rodada

#### system_logs
- Log imutável de todas as ações
- Rastreabilidade completa

---

## 🌱 Seeders

### Dados de Teste

**Usuários de teste:**
```
Admin: admin@bingo.local / password123
Operador: operador@bingo.local / password123
Auditor: auditor@bingo.local / password123
Player: player@bingo.local / password123
```

**Eventos de teste:**
- 1 evento híbrido (draft)
- 1 evento digital (draft)
- 1 evento presencial (draft)

### Executar Seeders
```bash
php artisan db:seed
# ou específico
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=EventSeeder
```

---

## 🔍 Logs e Auditoria

Todos os eventos importantes são registrados:

```
✓ user_registered
✓ login_success
✓ login_failed
✓ login_blocked
✓ email_verified
✓ password_changed
✓ event_created
✓ event_updated
✓ cards_generated
✓ event_started
✓ event_finished
```

Query logs:
```bash
GET /api/events/{event_id}/audit-log  # Próxima etapa
```

---

## 🚀 Próximos Passos

**Etapa 7**: Geração de Cartelas
- Chamar API Python para gerar cartelas
- Persistir em BD
- Atualizar status de evento

**Etapa 8**: Geração de PDFs
- Implementar ReportLab
- Layout das 5 subcartelas
- QR Code

**Etapa 9**: Sorteio ao Vivo
- WebSocket para broadcast
- Geração aleatória de números

---

## 📝 Notas Implementação

### Segurança
- ✅ Seed gerado e armazenado (nunca exposto)
- ✅ Passwords hasheadas com bcrypt
- ✅ JWT com Sanctum
- ✅ Permissões por papel
- ✅ Logs de auditoria

### Performance
- ✅ Índices no banco de dados
- ✅ Paginação em listagens
- ✅ Relações com lazy loading

### Escalabilidade
- ✅ UUIDs como identificadores
- ✅ Estrutura pronta para milhares de eventos
- ✅ Separação de services/controllers

---

Status: ✅ Etapas 5 e 6 Concluídas
