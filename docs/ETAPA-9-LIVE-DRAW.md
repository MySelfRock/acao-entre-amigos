# Etapa 9: Sistema de Sorteio ao Vivo com WebSocket

## Visão Geral

O sistema de sorteio ao vivo implementa um mecanismo de sorteio de bingo híbrido, suportando tanto participantes digitais quanto presenciais com atualizações em tempo real via WebSocket.

## Arquitetura

### Componentes Principais

```
┌─────────────────────────────────────────────────────────────┐
│                  DrawService (Lógica de Negócio)            │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. startDraw()          - Inicia sorteio                   │
│  2. drawNumber()         - Sorteia próximo número           │
│  3. checkForBingoClaims()- Verifica bingos automáticos      │
│  4. claimBingo()         - Registra reivindicação de bingo  │
│  5. finishDraw()         - Encerra sorteio                  │
│  6. getDrawStatus()      - Status atual                     │
│  7. getResults()         - Resultados finais                │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│              DrawController (Endpoints REST)                │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  POST   /api/events/{id}/draw/start                         │
│  POST   /api/events/{id}/draw/next                          │
│  GET    /api/events/{id}/draw/status                        │
│  GET    /api/events/{id}/draw/numbers                       │
│  GET    /api/events/{id}/draw/winner                        │
│  POST   /api/events/{id}/draw/claim                         │
│  GET    /api/events/{id}/draw/claims                        │
│  POST   /api/events/{id}/draw/finish                        │
│  GET    /api/events/{id}/draw/results                       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
                           ↓
┌─────────────────────────────────────────────────────────────┐
│           Broadcast Events (WebSocket em Tempo Real)        │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  NumberDrawn     - Número foi sorteado                      │
│  BingoClaimed    - Bingo foi reivindicado                   │
│                                                              │
│  Canais:                                                     │
│  - event.{event_id}.draw    → sorteio de números            │
│  - event.{event_id}.bingo   → reivindicações de bingo       │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

## Fluxo do Sorteio

### 1. Iniciar Sorteio
```
Evento: Status = "generated"
     ↓
POST /api/events/{id}/draw/start
     ↓
Evento: Status = "running"
Sistema: Pronto para sortear números
```

### 2. Sortear Número (Rodada X)
```
POST /api/events/{id}/draw/next
{
  "round": 1
}
     ↓
DrawService::drawNumber()
     ↓
1. Pega números já sorteados na rodada
2. Seleciona aleatoriamente de números disponíveis (1-75)
3. Registra na tabela `draws`
4. Verifica se alguma cartela completou bingo
5. Emite evento WebSocket: NumberDrawn
     ↓
Resposta:
{
  "event_id": "uuid",
  "round": 1,
  "number": 42,
  "draw_order": 15,
  "total_drawn": 15
}
```

### 3. Validação de Bingo Automática

Quando um número é sorteado:

```
DrawService::checkForBingoClaims(event, round, number)
     ↓
1. Encontra todas as cartelas da rodada com esse número
2. Marca o número como "drawn" em cada cartela
3. Verifica se a cartela completou bingo (todos 25 números marcados)
4. Se sim:
   - Cria registro em `winners` table
   - Emite evento WebSocket: BingoWinner
   - Impede novo vencedor na mesma rodada
```

### 4. Reivindicação de Bingo (Participante Digital)

```
POST /api/events/{id}/draw/claim
{
  "subcard_id": "uuid"
}
     ↓
DrawService::claimBingo(event, subcard_id, user_id)
     ↓
1. Verifica se cartela tem bingo completo
2. Valida que não há reivindicação anterior
3. Cria registro em `bingo_claims`
4. Emite evento WebSocket: BingoClaimed
     ↓
Resposta:
{
  "claim_id": "uuid",
  "is_valid": true
}
```

### 5. Finalizar Sorteio

```
POST /api/events/{id}/draw/finish
     ↓
Evento: Status = "finished"
Sistema: Sorteio encerrado, resultados consolidados
```

## Banco de Dados

### Tabelas Principais

#### `draws`
```sql
- id (UUID)
- event_id (UUID FK)
- round_number (1-5)
- number (1-75)
- draw_order (sequência)
- drawn_at (timestamp)
- unique(event_id, number) -- sem repetição
```

#### `bingo_claims`
```sql
- id (UUID)
- event_id (UUID FK)
- subcard_id (UUID FK)
- claimed_by (UUID FK users, nullable para presencial)
- is_valid (boolean)
- validated_at (timestamp)
```

#### `winners`
```sql
- id (UUID)
- event_id (UUID FK)
- subcard_id (UUID FK)
- card_id (UUID FK)
- round_number (1-5)
- awarded_at (timestamp)
- unique(event_id, round_number) -- um vencedor por rodada
```

## WebSocket Setup

### Opção 1: Redis (Recomendado para Produção)

#### Instalação

```bash
# Docker (docker-compose.yml já tem Redis configurado)
services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"

# Ou localmente
brew install redis
redis-server
```

#### Configuração Laravel

```bash
# .env
BROADCAST_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Inicie o Broadcast Server

```bash
# Terminal 1: Laravel Broadcasting Server
php artisan queue:work redis --queue=default

# Terminal 2: WebSocket Server (Laravel Echo)
npm install -g laravel-echo-server

echo-server init
# Responda às perguntas:
# - Hostname: localhost
# - Port: 6001
# - Client Host: localhost
# - Client Port: 6001

echo-server start
```

### Opção 2: Pusher (Serviço Externo)

```bash
# .env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=xxxxx
PUSHER_APP_KEY=xxxxx
PUSHER_APP_SECRET=xxxxx
PUSHER_APP_CLUSTER=mt
```

### Opção 3: Development (Log Driver)

```bash
# .env
BROADCAST_DRIVER=log
```

## Cliente WebSocket (Frontend)

### Exemplo com JavaScript

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Se usando Redis com Echo Server
window.Echo = new Echo({
  broadcaster: 'pusher',
  key: 'your-key', // ou qualquer string em dev
  cluster: 'mt',
  wsHost: 'localhost',
  wsPort: 6001,
  wssPort: 6001,
  encrypted: false,
  disableStats: true,
});

// Escutar número sorteado
Echo.channel(`event.${eventId}.draw`)
  .listen('NumberDrawn', (data) => {
    console.log(`Número sorteado: ${data.number}`);
    updateUI(data);
  });

// Escutar reivindicação de bingo
Echo.channel(`event.${eventId}.bingo`)
  .listen('BingoClaimed', (data) => {
    console.log(`Bingo reivindicado! Cartela: ${data.subcard_id}`);
    notifyBingo(data);
  });
```

### Exemplo com Python (para app mobile/desktop)

```python
import websocket
import json

def on_message(ws, message):
    data = json.loads(message)
    if data.get('event') == 'number.drawn':
        print(f"Número sorteado: {data['number']}")

def on_error(ws, error):
    print(f"Erro: {error}")

ws = websocket.WebSocketApp(
    f"ws://localhost:6001/app/your-key?channel=event.{event_id}.draw",
    on_message=on_message,
    on_error=on_error
)

ws.run_forever()
```

## API Endpoints

### 1. Iniciar Sorteio
```http
POST /api/events/{event}/draw/start
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "message": "Draw started successfully",
  "data": {
    "event_id": "uuid",
    "status": "running",
    "started_at": "2024-02-03T15:30:00Z"
  }
}
```

### 2. Sortear Próximo Número
```http
POST /api/events/{event}/draw/next
Authorization: Bearer {token}

{
  "round": 1
}
```

**Resposta:**
```json
{
  "message": "Number drawn successfully",
  "data": {
    "event_id": "uuid",
    "round": 1,
    "number": 42,
    "draw_order": 15,
    "total_drawn": 15,
    "drawn_at": "2024-02-03T15:31:00Z"
  }
}
```

**Broadcast Event:**
```json
{
  "event": "number.drawn",
  "data": {
    "event_id": "uuid",
    "round": 1,
    "number": 42,
    "drawn_at": "2024-02-03T15:31:00Z"
  }
}
```

### 3. Status do Sorteio
```http
GET /api/events/{event}/draw/status?round=1
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "data": {
    "event_id": "uuid",
    "round": 1,
    "total_drawn": 15,
    "drawn_numbers": [1, 5, 10, 15, 20, ...],
    "available_numbers": [2, 3, 4, 6, 7, ...],
    "has_winner": true,
    "winner": {
      "round": 1,
      "card_id": "uuid",
      "subcard_id": "uuid"
    }
  }
}
```

### 4. Números Sorteados
```http
GET /api/events/{event}/draw/numbers?round=1
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "round": 1,
  "total_drawn": 15,
  "numbers": [
    { "number": 42, "draw_order": 1, "drawn_at": "2024-02-03T15:31:00Z" },
    { "number": 15, "draw_order": 2, "drawn_at": "2024-02-03T15:31:05Z" },
    ...
  ]
}
```

### 5. Vencedor da Rodada
```http
GET /api/events/{event}/draw/winner?round=1
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "data": {
    "round": 1,
    "card_id": "uuid",
    "card_index": 42,
    "subcard_id": "uuid",
    "awarded_at": "2024-02-03T15:35:00Z"
  }
}
```

### 6. Reivindicar Bingo
```http
POST /api/events/{event}/draw/claim
Authorization: Bearer {token}

{
  "subcard_id": "uuid"
}
```

**Resposta:**
```json
{
  "message": "Bingo claimed successfully",
  "data": {
    "claim_id": "uuid",
    "subcard_id": "uuid",
    "is_valid": true,
    "claimed_at": "2024-02-03T15:35:00Z"
  }
}
```

**Broadcast Event:**
```json
{
  "event": "bingo.claimed",
  "data": {
    "event_id": "uuid",
    "subcard_id": "uuid",
    "claimed_by": "user-uuid",
    "claimed_at": "2024-02-03T15:35:00Z"
  }
}
```

### 7. Reivindicações de Bingo
```http
GET /api/events/{event}/draw/claims?per_page=20
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "data": [
    {
      "id": "uuid",
      "subcard_id": "uuid",
      "claimed_by": "João Silva",
      "is_valid": true,
      "claimed_at": "2024-02-03T15:35:00Z"
    },
    ...
  ],
  "pagination": {
    "total": 45,
    "per_page": 20,
    "current_page": 1,
    "last_page": 3
  }
}
```

### 8. Finalizar Sorteio
```http
POST /api/events/{event}/draw/finish
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "message": "Draw finished successfully",
  "data": {
    "event_id": "uuid",
    "status": "finished",
    "finished_at": "2024-02-03T16:00:00Z",
    "total_winners": 5
  }
}
```

### 9. Resultados Finais
```http
GET /api/events/{event}/draw/results
Authorization: Bearer {token}
```

**Resposta:**
```json
{
  "data": {
    "event_id": "uuid",
    "event_name": "Bingo Beneficente 2024",
    "total_rounds": 5,
    "total_draws": 75,
    "total_winners": 5,
    "winners": [
      {
        "round": 1,
        "card_id": "uuid",
        "card_index": 42,
        "subcard_id": "uuid",
        "awarded_at": "2024-02-03T15:35:00Z"
      },
      ...
    ]
  }
}
```

## Segurança

### Validações

1. **Autorização**: Apenas o criador do evento pode iniciar/finalizar sorteio
2. **Status do Evento**: Verificação rigorosa de transição de status
3. **Integridade**: Constraints de banco de dados (unique, foreign keys)
4. **Auditoria**: Todos os eventos registrados em `system_logs`

### Proteções

```php
// DrawController - Exemplo
if (!$request->user()->canManageEvents() ||
    $event->created_by !== $request->user()->id) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

## Tratamento de Erros

### Cenários Comuns

```javascript
// Erro: Evento não está em "running"
{
  "message": "Failed to draw number",
  "error": "Event is not in running status"
}

// Erro: Todos os 75 números já foram sorteados
{
  "message": "Failed to draw number",
  "error": "All 75 numbers have been drawn in this round"
}

// Erro: Cartela não tem bingo completo
{
  "message": "Failed to claim bingo",
  "error": "This subcard does not have a complete bingo yet"
}

// Erro: Cartela sem reivindicação anterior
{
  "message": "Failed to claim bingo",
  "error": "Bingo claim already exists for this subcard"
}
```

## Logging e Monitoramento

Todos os eventos são registrados em `system_logs`:

```sql
SELECT * FROM system_logs
WHERE reference_type = 'draw'
ORDER BY created_at DESC;
```

Ações registradas:
- `draw_started` - Sorteio iniciado
- `number_drawn` - Número sorteado
- `bingo_winner` - Vencedor de rodada
- `bingo_claimed` - Bingo reivindicado
- `draw_finished` - Sorteio finalizado

## Próximos Passos

- **Etapa 10**: Validação de Bingo (Digital + Presencial)
- **Etapa 11**: Relatórios e Auditoria
- **Etapa 12**: Endurecimento de Segurança
- **Etapa 13**: Testes e QA
- **Etapa 14**: Deploy e Documentação Final
