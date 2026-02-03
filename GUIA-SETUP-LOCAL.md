# Guia Completo - Setup Local e Teste da Aplicação

## 1. MERGE DA BRANCH

### Passo 1.1: Fazer merge da branch de desenvolvimento

```bash
# Navegue até o diretório do projeto
cd /home/user/acao-entre-amigos

# Atualize o repositório local
git fetch origin

# Mude para a branch main/master (a branch principal)
git checkout main
# ou
git checkout master

# Faça merge da branch de desenvolvimento
git merge claude/read-pdf-plan-system-sfYL4

# (Opcional) Se quiser fazer push para a branch principal
git push origin main
```

## 2. ESTRUTURA DO PROJETO

```
acao-entre-amigos/
├── backend-laravel/              # API REST + Admin
│   ├── app/
│   │   ├── Models/              # BD models (Event, Card, Draw, etc)
│   │   ├── Controllers/         # REST controllers
│   │   ├── Services/            # Business logic (DrawService, etc)
│   │   ├── Jobs/                # Queue jobs (GenerateCardsJob)
│   │   ├── Events/              # Broadcast events (NumberDrawn, BingoClaimed)
│   │   └── Http/Middleware/
│   ├── database/
│   │   ├── migrations/          # Schema do BD
│   │   └── seeders/             # Dados iniciais
│   ├── routes/api.php           # Rotas da API
│   ├── config/
│   │   ├── broadcasting.php     # Configuração WebSocket
│   │   └── services.php         # Serviços (Generator Python)
│   ├── .env.example
│   ├── docker-compose.yml       # (raiz)
│   └── Dockerfile
│
├── generator-python/            # Serviço de Geração
│   ├── app/
│   │   ├── main.py             # FastAPI app
│   │   ├── bingo_generator.py  # Lógica de geração
│   │   ├── pdf_generator.py    # Geração de PDFs
│   │   ├── security.py         # Segurança + Hash
│   │   └── models.py           # Modelos Pydantic
│   ├── requirements.txt
│   ├── .env.example
│   └── Dockerfile
│
└── docker-compose.yml           # Orquestração de containers

```

## 3. INSTALAÇÃO E SETUP

### 3.1 Pré-requisitos

**Obrigatório:**
- Docker & Docker Compose
- Git

**Opcional (para não usar Docker):**
- PHP 8.2+
- Laravel 10
- Python 3.11+
- MySQL 8+
- Redis

### 3.2 Setup com Docker (RECOMENDADO)

```bash
# 1. Navegue até o diretório raiz
cd /home/user/acao-entre-amigos

# 2. Copie os arquivos .env.example para .env
cp backend-laravel/.env.example backend-laravel/.env
cp generator-python/.env.example generator-python/.env

# 3. Configure o .env do Laravel (se necessário)
# Editor: backend-laravel/.env
# Verifique/ajuste:
# - APP_KEY (será gerado automaticamente)
# - DB_* (host: mysql, user: root, password: password)
# - GENERATOR_API_KEY=dev-api-key (deve matching com python)

# 4. Configure o .env do Python (se necessário)
# Editor: generator-python/.env
# Verifique:
# - API_KEY=dev-api-key (deve matching com Laravel)
# - SECRET_KEY=dev-secret-key

# 5. Construa e inicie os containers
docker-compose up -d --build

# 6. Aguarde ~30 segundos para tudo iniciar

# 7. Verifique se os containers estão rodando
docker-compose ps
# Output esperado:
# NAME                COMMAND             STATUS
# acao-mysql          "docker-entrypoint" Up
# acao-redis          "redis-server"      Up
# acao-laravel        "php artisan"       Up
# acao-generator      "python -m"         Up

# 8. Execute as migrações do banco
docker-compose exec laravel php artisan migrate

# 9. (Opcional) Popule dados de teste
docker-compose exec laravel php artisan db:seed
```

### 3.3 Verificar se está funcionando

```bash
# Teste o health check do Laravel
curl http://localhost:8000/api/health
# Resposta esperada:
# {
#   "status": "ok",
#   "service": "bingo-admin-api",
#   "version": "1.0.0"
# }

# Teste o health check do Python
curl http://localhost:8001/health
# Resposta esperada:
# {
#   "status": "ok",
#   "service": "bingo-generator",
#   "version": "1.0.0"
# }
```

## 4. CRIAR PRIMEIRO USUÁRIO (Admin)

### 4.1 Acessar o container Laravel

```bash
docker-compose exec laravel bash
```

### 4.2 Criar usuário via CLI (dentro do container)

```bash
# Opção 1: Usar um seeder customizado
php artisan tinker

# Dentro do Tinker:
>>> use App\Models\User;
>>> User::create([
...   'name' => 'Admin User',
...   'email' => 'admin@example.com',
...   'password' => bcrypt('password123'),
...   'role' => 'admin'
... ]);

>>> exit
```

### 4.3 Alternatively - Criar via SQL direto

```bash
# Sair do container (se estiver dentro)
exit

# Acessar MySQL
docker-compose exec mysql mysql -uroot -ppassword

# No prompt MySQL:
USE bingo_system;

INSERT INTO users (id, name, email, password, role, created_at, updated_at)
VALUES (
  UUID(),
  'Admin User',
  'admin@example.com',
  '$2y$10$...',  # bcrypt de "password123"
  'admin',
  NOW(),
  NOW()
);

exit;
```

## 5. FLUXO COMPLETO: CRIAR EVENTO + GERAR CARTELAS

### 5.1 Login - Obter Token JWT

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'

# Resposta:
# {
#   "access_token": "YOUR_TOKEN_HERE",
#   "token_type": "Bearer",
#   "user": {
#     "id": "uuid",
#     "name": "Admin User",
#     "email": "admin@example.com",
#     "role": "admin"
#   }
# }

# Salve o access_token em uma variável:
TOKEN="YOUR_TOKEN_HERE"
```

### 5.2 Criar um Evento

```bash
curl -X POST http://localhost:8000/api/events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Bingo Beneficente 2024",
    "description": "Evento de bingo para arrecadação de fundos",
    "event_date": "2024-05-15",
    "location": "São Paulo, SP",
    "total_cards": 100,
    "total_rounds": 5,
    "participation_type": "hibrido",
    "metadata": {
      "organizer": "Ação entre Amigos",
      "expected_participants": 500
    }
  }'

# Resposta (salve o event_id):
# {
#   "message": "Event created successfully",
#   "event": {
#     "id": "EVENT_UUID_HERE",
#     "name": "Bingo Beneficente 2024",
#     "status": "draft",
#     "seed": "SEED_HASH_HERE",
#     "created_by": "admin_uuid",
#     ...
#   }
# }

# Salve o EVENT_UUID_HERE
EVENT_ID="EVENT_UUID_HERE"
```

### 5.3 Verificar Detalhes do Evento

```bash
curl -X GET http://localhost:8000/api/events/$EVENT_ID \
  -H "Authorization: Bearer $TOKEN"

# Resposta:
# {
#   "event": {
#     "id": "EVENT_UUID",
#     "name": "Bingo Beneficente 2024",
#     "status": "draft",
#     "total_cards": 100,
#     "total_rounds": 5,
#     ...
#   }
# }
```

### 5.4 Gerar as Cartelas

```bash
curl -X POST http://localhost:8000/api/events/$EVENT_ID/generate-cards \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json"

# Resposta (status 202 - Accepted):
# {
#   "message": "Card generation started",
#   "event": {
#     "id": "EVENT_UUID",
#     "status": "generating"
#   },
#   "job": {
#     "total_cards": 100,
#     "total_rounds": 5
#   }
# }
```

### 5.5 Acompanhar Progresso da Geração

```bash
# Execute múltiplas vezes para ver o progresso
curl -X GET http://localhost:8000/api/events/$EVENT_ID/generate-status \
  -H "Authorization: Bearer $TOKEN"

# Resposta:
# {
#   "event": {
#     "id": "EVENT_UUID",
#     "status": "generating",  # ou "generated"
#     "cards_generated": 45,   # progresso
#     "total_cards": 100,
#     "progress": 45.00
#   }
# }

# Aguarde até progress == 100 ou status == "generated"
```

### 5.6 Listar as Cartelas Geradas

```bash
curl -X GET http://localhost:8000/api/events/$EVENT_ID/cards?per_page=10 \
  -H "Authorization: Bearer $TOKEN"

# Resposta:
# {
#   "data": [
#     {
#       "id": "card_uuid_1",
#       "card_index": 1,
#       "qr_code": "QR_CODE_DATA",
#       "subcards_count": 5
#     },
#     {
#       "id": "card_uuid_2",
#       "card_index": 2,
#       "qr_code": "QR_CODE_DATA",
#       "subcards_count": 5
#     },
#     ...
#   ],
#   "pagination": {
#     "total": 100,
#     "per_page": 10,
#     "current_page": 1,
#     "last_page": 10
#   }
# }
```

### 5.7 Visualizar Detalhes de Uma Cartela

```bash
CARD_ID="card_uuid_1"  # Do passo anterior

curl -X GET http://localhost:8000/api/events/$EVENT_ID/cards/$CARD_ID \
  -H "Authorization: Bearer $TOKEN"

# Resposta:
# {
#   "card": {
#     "id": "card_uuid",
#     "event_id": "event_uuid",
#     "card_index": 1,
#     "qr_code": "...",
#     "subcards": [
#       {
#         "id": "subcard_uuid_1",
#         "round": 1,
#         "hash": "hash_value",
#         "grid": [
#           ["B1", "I16", "N31", "G46", "O61"],
#           ["B2", "I17", "FREE", "G47", "O62"],
#           ...
#         ]
#       },
#       ... (5 subcards no total)
#     ]
#   }
# }
```

## 6. GERAR PDFs

### 6.1 Gerar PDFs das Cartelas

```bash
# Gerar PDF das primeiras 10 cartelas
curl -X POST http://localhost:8000/api/events/$EVENT_ID/generate-pdfs \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "limit": 10,
    "layout": "default"
  }'

# Resposta:
# {
#   "message": "PDFs generated successfully",
#   "event": {
#     "id": "event_uuid",
#     "name": "Bingo Beneficente 2024"
#   },
#   "pdfs": {
#     "total": 10,
#     "urls": [
#       "/output/00001_uuid.pdf",
#       "/output/00002_uuid.pdf",
#       ...
#     ]
#   }
# }
```

### 6.2 Acessar os PDFs Gerados

Os PDFs estão salvos no diretório do container:
```bash
# Listar PDFs gerados
docker-compose exec generator ls -lh /app/output/

# Copiar PDFs para a máquina local
docker cp acao-generator:/app/output ./pdfs-gerados
```

## 7. VERIFICAÇÃO E DEBUG

### 7.1 Ver logs do Laravel

```bash
docker-compose logs -f laravel

# Para logs só dos últimas 50 linhas:
docker-compose logs --tail=50 laravel

# Para logs de um serviço específico:
docker-compose logs -f mysql
docker-compose logs -f generator
```

### 7.2 Ver logs do Python

```bash
docker-compose logs -f generator

# Ver logs detalhados de requisição:
docker-compose logs -f generator | grep -i "generating\|pdf\|error"
```

### 7.3 Acessar banco de dados MySQL

```bash
docker-compose exec mysql mysql -uroot -ppassword bingo_system

# Ver tabelas criadas
SHOW TABLES;

# Ver registros de eventos
SELECT id, name, status, total_cards FROM events;

# Ver cartelas geradas
SELECT id, event_id, card_index FROM cards LIMIT 5;

# Ver subcartelas
SELECT id, card_id, round_number FROM subcards LIMIT 5;

# Ver números de sorteio (será vazio no início)
SELECT * FROM draws;

exit;
```

### 7.4 Acessar Redis (para cache/queue)

```bash
docker-compose exec redis redis-cli

# Ver todos as chaves
KEYS *

# Ver estado da queue (jobs)
LLEN "queues:default"

exit;
```

## 8. PARAR E LIMPAR

```bash
# Parar containers (mantém dados)
docker-compose down

# Parar e remover dados (CUIDADO!)
docker-compose down -v

# Reiniciar tudo
docker-compose restart

# Reconstruir containers
docker-compose up -d --build --force-recreate
```

## 9. TROUBLESHOOTING

### Problema: "Connection refused" ao acessar API

```bash
# Verifique se containers estão rodando
docker-compose ps

# Se não estão, inicie
docker-compose up -d

# Aguarde ~30 segundos para inicialização completa
sleep 30

# Tente novamente
curl http://localhost:8000/api/health
```

### Problema: "SQLSTATE[HY000]: General error: 1030"

```bash
# Container MySQL pode estar com pouca memória
# Aumentar limite no docker-compose.yml

# Ou reiniciar e recriar
docker-compose down -v
docker-compose up -d --build
docker-compose exec laravel php artisan migrate
```

### Problema: "Token invalid or expired"

```bash
# Gere um novo token
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }'

# Copie o novo token
```

### Problema: Geração de cartelas travada

```bash
# Verifique se há jobs na fila
docker-compose exec redis redis-cli
LLEN "queues:default"
exit

# Se houver muitos, limpe (CUIDADO!)
docker-compose exec redis redis-cli FLUSHALL

# Reinicie o queue worker
docker-compose restart laravel
```

## 10. PRÓXIMOS PASSOS APÓS TESTE

Uma vez que as cartelas foram geradas com sucesso, você pode:

1. **Testar Sorteio** (Etapa 9):
   ```bash
   # Iniciar sorteio
   curl -X POST http://localhost:8000/api/events/$EVENT_ID/draw/start \
     -H "Authorization: Bearer $TOKEN"

   # Sortear número
   curl -X POST http://localhost:8000/api/events/$EVENT_ID/draw/next \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"round": 1}'
   ```

2. **Verificar Logs do Sistema**:
   ```bash
   docker-compose exec mysql mysql -uroot -ppassword bingo_system
   SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20;
   exit;
   ```

3. **Monitorar WebSocket** (após implementar cliente):
   - Redis Publisher escuta em `event.{event_id}.draw`
   - Cliente JavaScript conecta via Laravel Echo

---

**Resumo dos Ports:**
- Laravel API: http://localhost:8000
- Python Generator: http://localhost:8001
- MySQL: localhost:3306 (usuario: root, senha: password)
- Redis: localhost:6379
- Mailhog (email): http://localhost:1025

**Credenciais Padrão:**
- Email: admin@example.com
- Senha: password123
- Role: admin
