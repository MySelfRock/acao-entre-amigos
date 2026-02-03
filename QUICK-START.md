# ⚡ Quick Start - Teste em 5 Minutos

## Pré-requisitos
- Docker & Docker Compose instalados
- ~4GB de RAM disponível
- 2GB de espaço em disco

## 1️⃣ Fazer Merge da Branch

```bash
cd /path/to/acao-entre-amigos

# Mude para branch principal
git checkout main    # ou master

# Faça merge
git merge claude-read-pdf-plan-system-sfYL4

# (Opcional) Push para remoto
git push origin main
```

## 2️⃣ Setup Automático (RECOMENDADO)

```bash
# Torne o script executável
chmod +x setup-local.sh

# Execute o script
./setup-local.sh

# Aguarde ~5 minutos enquanto containers iniciam...
```

**O que o script faz:**
- ✓ Verifica Docker instalado
- ✓ Copia .env files
- ✓ Constrói e inicia containers
- ✓ Executa migrations de BD
- ✓ Cria usuário admin

## 3️⃣ Verificar Setup

```bash
# Testar API Laravel
curl http://localhost:8000/api/health

# Testar API Python
curl http://localhost:8001/health

# Ver containers rodando
docker-compose ps
```

## 4️⃣ Fazer Login

Substitua `$TOKEN` pelos primeiros 20 caracteres do token retornado:

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@example.com",
    "password": "password123"
  }' | jq .

# Salve o token
TOKEN="seu_token_aqui"
```

## 5️⃣ Criar Evento

```bash
curl -X POST http://localhost:8000/api/events \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "name": "Bingo Teste",
    "description": "Evento de teste",
    "event_date": "2024-12-25",
    "location": "São Paulo, SP",
    "total_cards": 50,
    "total_rounds": 5,
    "participation_type": "hibrido"
  }' | jq .

# Salve o event_id do retorno
EVENT_ID="uuid_do_evento"
```

## 6️⃣ Gerar Cartelas

```bash
# Inicia geração (retorna 202 - Accepted)
curl -X POST http://localhost:8000/api/events/$EVENT_ID/generate-cards \
  -H "Authorization: Bearer $TOKEN" | jq .

# Verificar progresso (execute várias vezes)
curl -X GET http://localhost:8000/api/events/$EVENT_ID/generate-status \
  -H "Authorization: Bearer $TOKEN" | jq .
```

**Aguarde até `progress` = 100**

## 7️⃣ Listar Cartelas Geradas

```bash
curl -X GET "http://localhost:8000/api/events/$EVENT_ID/cards?per_page=5" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## 8️⃣ Ver Detalhes de Uma Cartela

```bash
# Use um ID do passo anterior
CARD_ID="card_uuid_aqui"

curl -X GET http://localhost:8000/api/events/$EVENT_ID/cards/$CARD_ID \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## 🎲 Testar Sorteio (Bônus)

```bash
# Iniciar sorteio
curl -X POST http://localhost:8000/api/events/$EVENT_ID/draw/start \
  -H "Authorization: Bearer $TOKEN" | jq .

# Sortear primeiro número
curl -X POST http://localhost:8000/api/events/$EVENT_ID/draw/next \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"round": 1}' | jq .

# Ver status do sorteio
curl -X GET "http://localhost:8000/api/events/$EVENT_ID/draw/status?round=1" \
  -H "Authorization: Bearer $TOKEN" | jq .
```

## 🛑 Parar Tudo

```bash
docker-compose down

# Com limpeza de dados (CUIDADO!)
docker-compose down -v
```

## 📊 Ver Logs

```bash
# Logs do Laravel (API)
docker-compose logs -f laravel

# Logs do Python (Gerador)
docker-compose logs -f python

# Logs do MySQL
docker-compose logs -f mysql

# Sair: Ctrl+C
```

## 🐛 Troubleshooting Rápido

| Problema | Solução |
|----------|---------|
| "Connection refused" | Aguarde 30 segundos, depois `docker-compose ps` |
| "No such container" | Execute `docker-compose up -d` novamente |
| Cartelas não geram | Ver logs: `docker-compose logs -f laravel` |
| MySQL falha ao iniciar | `docker-compose down -v` e `up` novamente |

## 📖 Documentação Completa

- **GUIA-SETUP-LOCAL.md** - Guia detalhado com todos os steps e troubleshooting
- **PLANO-DESENVOLVIMENTO.md** - Visão geral do projeto
- **docs/ETAPA-9-LIVE-DRAW.md** - Documentação do sistema de sorteio

## 📝 Credentials Padrão

```
Email: admin@example.com
Senha: password123
Role: admin
```

## 🌐 URLs Principais

| Serviço | URL | Descrição |
|---------|-----|-----------|
| Laravel API | http://localhost:8000 | REST API principal |
| Python API | http://localhost:8001 | Serviço de geração |
| MySQL | localhost:3306 | Banco de dados |
| Redis | localhost:6379 | Cache/Queue |

---

**Dúvidas?** Consulte o **GUIA-SETUP-LOCAL.md** para instruções detalhadas! 🚀
