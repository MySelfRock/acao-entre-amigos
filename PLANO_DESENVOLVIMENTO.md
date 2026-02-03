# Plano Completo de Desenvolvimento - Sistema Híbrido de Bingo

## Visão Geral
Sistema de bingo híbrido (digital + presencial) com suporte a 2.000+ cartelas, 5 rodadas, geração determinística de cartelas, sorteio ao vivo e validação automática.

**Stack:** Laravel 10+ (Backend Admin) + Python 3.11+ (Geração) + Frontend (Digital)

---

## Fases de Desenvolvimento

### ✅ FASE 1: DOCUMENTAÇÃO E DESIGN (Concluída)
- [x] Etapa 1 - Diagrama de Banco de Dados
- [x] Etapa 2 - Contrato OpenAPI
- [x] Etapa 3 - Layout da Cartela (PDF e Digital)

---

## ✅ FASE 2: INFRAESTRUTURA E SETUP BASE (Concluída)

### ✅ Etapa 4: Setup do Código Base (Laravel + Python)

#### 4.1 Estrutura de Repositórios
```
bingo-system/
├── backend-laravel/
│   ├── app/
│   │   ├── Models/
│   │   ├── Controllers/
│   │   ├── Services/
│   │   ├── Jobs/
│   │   └── Traits/
│   ├── database/migrations/
│   ├── routes/api.php
│   ├── config/
│   ├── .env.example
│   └── docker/
├── generator-python/
│   ├── app/
│   │   ├── main.py
│   │   ├── bingo_generator.py
│   │   ├── pdf_generator.py
│   │   ├── security.py
│   │   └── models.py
│   ├── requirements.txt
│   ├── .env.example
│   └── Dockerfile
├── docs/
│   ├── ETAPA-1-BANCO-DADOS.md
│   ├── ETAPA-2-OPENAPI.md
│   └── ETAPA-3-LAYOUT.md
└── docker-compose.yml
```

#### 4.2 Tarefas da Etapa 4
- [ ] Inicializar projeto Laravel 10
- [ ] Configurar banco de dados (MySQL/PostgreSQL)
- [ ] Criar estrutura de pastas Django/FastAPI
- [ ] Setup Docker para ambos serviços
- [ ] Configurar variáveis de ambiente
- [ ] Implementar middleware de autenticação (JWT)
- [ ] Criar primeiros Models (User, Event)
- [ ] Criar migrations iniciais

**Saída esperada:** Laravel e Python rodando, ambos comunicando entre si

---

## ✅ FASE 3: MÓDULOS CORE (Concluída)

### ✅ Etapa 5: Autenticação e Perfis de Usuário

#### 5.1 Funcionalidades
- Sistema de login seguro (JWT + Sanctum Laravel)
- 4 perfis: Admin, Operador, Auditor, Jogador Digital
- Controle de permissões via middleware
- Log de ações administrativas

#### 5.2 Tabelas
- `users` (id, name, email, password_hash, role, created_at)
- `system_logs` (para auditoria)

#### 5.3 Endpoints
- `POST /api/auth/register` - Registro (apenas Admin pode criar)
- `POST /api/auth/login` - Login
- `POST /api/auth/logout` - Logout
- `GET /api/auth/me` - Dados do usuário atual

**Saída esperada:** Sistema de autenticação 100% funcional

---

### ✅ Etapa 6: Módulo de Criação de Evento

#### 6.1 Campos do Evento
- Nome do evento
- Data/hora
- Quantidade de cartelas (ex: 2.000)
- Quantidade de rodadas (fixo: 5)
- Tipo de bingo (75 bolas)
- Tipo de participação (Digital/Presencial/Híbrido)
- Descrição
- Logo/imagem (opcional)

#### 6.2 Status do Evento
- `draft` → `generated` → `running` → `finished`

#### 6.3 Endpoints
- `POST /api/events` - Criar evento (Admin)
- `GET /api/events` - Listar eventos
- `GET /api/events/{id}` - Detalhes do evento
- `PUT /api/events/{id}` - Editar evento (draft only)
- `POST /api/events/{id}/start` - Iniciar evento (após geração de cartelas)
- `POST /api/events/{id}/finish` - Finalizar evento

#### 6.4 Regras
- Evento só inicia após geração de cartelas
- Gera um seed global único por evento
- Seed NUNCA é exposto ao frontend

**Saída esperada:** CRUD completo de eventos

---

### ✅ Etapa 7: Geração de Cartelas (Python + Laravel)

#### 7.1 Algoritmo Python
```python
# Pseudocódigo
seed = HASH(event_id + rodada + indice_cartela + secret)
for cada_cartela in range(total_cards):
    for cada_rodada in range(5):
        subcartela = gerar_5x5(seed)
        subcartela_hash = HASH(subcartela)
        # Garantir unicidade por rodada
        if subcartela_hash not in banco_hashes[rodada]:
            salvar_subcartela(subcartela)
```

#### 7.2 Fluxo
1. Admin clica "Gerar Cartelas" em um evento
2. Laravel dispara um Job/Queue
3. Job chama API Python: `POST /generator/generate`
4. Python gera 10.000 subcartelas (5 por cartela)
5. Python retorna lista com hashes e números
6. Laravel persiste em BD (cards + subcards + subcard_numbers)
7. Status do evento passa para `generated`

#### 7.3 Endpoints Python
- `POST /generator/generate`
  - Request: `{event_id, total_cards, rounds, seed}`
  - Response: `{status: "ok", generated: 10000}`

- `POST /generator/verify`
  - Request: `{event_id, round, hash}`
  - Response: `{is_valid: true, grid: [[...]]}`

#### 7.4 Garantias
- ✓ Unicidade por rodada
- ✓ Determinístico via seed
- ✓ FREE no centro
- ✓ Colunas B/I/N/G/O respeitadas

**Saída esperada:** 2.000 cartelas geradas em até 5 minutos

---

### ✅ Etapa 8: Geração de PDFs com Layouts Customizados

#### 8.1 Layout PDF (A4)
```
┌─────────────────────────────────┐
│ NOME DO EVENTO | QR CODE        │
│ Data | Local                    │
├─────────────────────────────────┤
│ Cartela Nº: 0001 ID: X7A9F    │
├──────────┬──────────┬──────────┤
│ Rodada 1 │ Rodada 2 │ Rodada 3 │
│ 5x5      │ 5x5      │ 5x5      │
├──────────┼──────────┼──────────┤
│ Rodada 4 │ Rodada 5 │ REGRAS   │
│ 5x5      │ 5x5      │ resumo   │
└──────────┴──────────┴──────────┘
```

#### 8.2 Funcionalidades
- Geração em lote
- Download ZIP
- Impressão A4/A5
- Margens configuráveis
- QR Code único por cartela

#### 8.3 Endpoint Python
- `POST /generator/pdf`
  - Request: `{event_id, card_ids, layout: "default"}`
  - Response: `{pdf_url: "https://...", total_files: 100}`

#### 8.4 Biblioteca
- ReportLab (PDF generation)
- qrcode (QR Code)

**Saída esperada:** 2.000 PDFs prontos para impressão

---

## 🔵 FASE 4: SORTEIO E VALIDAÇÃO

### ✅ Etapa 9: Sorteio ao Vivo com WebSocket

#### 9.1 Interface do Operador
- Botão "Sortear Número"
- Histórico de sorteios em tempo real
- Exibição em telão
- Pausar/Encerrar rodada

#### 9.2 Fluxo
1. Operador clica "Sortear"
2. Laravel gera número (1-75) sem repetição na rodada
3. WebSocket broadcast para todos os jogadores digitais
4. Número armazenado em `draws` com timestamp
5. Frontend atualiza status em tempo real

#### 9.3 Endpoints
- `POST /api/events/{id}/draw` - Sortear número
  - Response: `{number: 42, order: 10, drawn_at: "..."}`

- `GET /api/events/{id}/draws` - Listar sorteados
  - Response: `[{number, order, round, drawn_at}, ...]`

#### 9.4 WebSocket Broadcast
```javascript
// Laravel Broadcasting
broadcast(new NumberDrawn($event, $number, $order))
// Recebido por: event.{event_id}.draw
broadcast(new BingoClaimed($event, $subcard_id, $user_id))
// Recebido por: event.{event_id}.bingo
```

#### 9.5 Implementação Completa ✅

**DrawService:**
- `startDraw()` - Inicia sorteio e transiciona evento
- `drawNumber()` - Sorteia número aleatório sem repetição
- `checkForBingoClaims()` - Valida autocarticamente bingos ao sortear
- `checkSubcardForBingo()` - Detecta cartela completa
- `claimBingo()` - Registra reivindicação de bingo digital
- `finishDraw()` - Encerra sorteio
- `getDrawStatus()` - Status atual da rodada
- `getResults()` - Resultados finais

**DrawController Endpoints:**
- `POST /api/events/{id}/draw/start` - Iniciar sorteio
- `POST /api/events/{id}/draw/next` - Sortear próximo número
- `GET /api/events/{id}/draw/status` - Status da rodada
- `GET /api/events/{id}/draw/numbers` - Números sorteados
- `GET /api/events/{id}/draw/winner` - Vencedor da rodada
- `POST /api/events/{id}/draw/claim` - Reivindicar bingo
- `GET /api/events/{id}/draw/claims` - Listar reivindicações
- `POST /api/events/{id}/draw/finish` - Encerrar sorteio
- `GET /api/events/{id}/draw/results` - Resultados finais

**Broadcast Events:**
- `NumberDrawn` - Emitido quando número é sorteado (canal: `event.{event_id}.draw`)
- `BingoClaimed` - Emitido quando bingo é reivindicado (canal: `event.{event_id}.bingo`)

**Suporte WebSocket:**
- Redis broadcaster (padrão produção)
- Pusher (serviço gerenciado)
- Log driver (desenvolvimento)
- Configuração em `config/broadcasting.php`

**Saída esperada:** ✅ Sorteio em tempo real com validação automática de bingos

---

### Etapa 10: Validação de Bingo (Digital + Presencial)

#### 10.1 Fluxo Digital
1. Jogador marca números conforme sorteio
2. Quando preenche padrão, clica "BINGO"
3. Requisição para servidor: `POST /api/bingo/claim`
4. Backend valida automaticamente:
   - Regenera subcartela via Python
   - Compara com números sorteados
   - Se válido: registra em `bingo_claims` e `winners`
   - Broadcast para todos (prêmio concedido)

#### 10.2 Fluxo Presencial
1. Operador escaneia QR Code da cartela
2. Sistema identifica `card_id`
3. Operador seleciona rodada
4. Sistema valida subcartela daquela rodada
5. Se válido: registra ganhador

#### 10.3 Endpoints
- `POST /api/bingo/claim` (Digital)
  - Request: `{subcard_id}`
  - Response: `{is_valid: true, round: 3, prize: "..."}`

- `POST /api/bingo/verify-qr` (Presencial)
  - Request: `{qr_code, round}`
  - Response: `{is_valid: true, card_number: 1}`

#### 10.4 Validação
- Chamar Python para regenerar subcartela
- Comparar grid com `draws` da rodada
- Garantir uma vitória por rodada

**Saída esperada:** Validação 100% confiável

---

## 🔵 FASE 5: RELATÓRIOS E SEGURANÇA

### Etapa 11: Relatórios e Auditoria

#### 11.1 Relatórios
- Cartelas geradas por evento
- Números sorteados por rodada
- Vencedores por rodada
- Tentativas inválidas
- Tempo de cada rodada
- Exportação CSV/PDF

#### 11.2 Endpoints
- `GET /api/events/{id}/reports`
  - Response: `{cards_generated, draws, winners, invalid_claims}`

- `GET /api/events/{id}/audit-log`
  - Response: lista completa de ações (criação evento, sorteios, validações)

- `GET /api/reports/export`
  - Response: arquivo CSV/PDF com relatório

#### 11.3 System Logs
- Toda ação gravada em `system_logs`
- Campos: user_id, action, reference_type, reference_id, metadata, created_at
- Imutável (append-only)

**Saída esperada:** Rastreabilidade total do evento

---

### Etapa 12: Endurecimento de Segurança

#### 12.1 Implementações
- [ ] Seed nunca exposto (server-side only)
- [ ] Hash criptográfico SHA-256 para subcartelas
- [ ] Rate limit em endpoints críticos
- [ ] HMAC para comunicação Laravel ↔ Python
- [ ] Validação 100% no backend
- [ ] CORS configurado
- [ ] CSRF protection (Sanctum)
- [ ] SQL Injection prevention (Eloquent)
- [ ] XSS prevention (Blade escaping)

#### 12.2 Testes de Segurança
- [ ] Tentar forjar cartela → Falha
- [ ] Tentar manipular números sorteados → Falha
- [ ] Tentar acessar seed → Falha (403)
- [ ] Rate limit: >100 req/min → Bloqueado

**Saída esperada:** Sistema preparado para produção

---

## 🔵 FASE 6: TESTES E DEPLOY

### Etapa 13: Testes e QA

#### 13.1 Testes Unitários
- [ ] Generator: Unicidade de subcartelas
- [ ] Generator: Determinismo via seed
- [ ] Validation: Bingo correto é aceito
- [ ] Validation: Bingo falso é rejeitado
- [ ] Draws: Sem repetição de números

#### 13.2 Testes de Integração
- [ ] Fluxo completo: criar evento → gerar cartelas → sortear → validar
- [ ] WebSocket: Broadcast para 1.000 usuários
- [ ] PDF: Gerar 2.000 PDFs em <5 min

#### 13.3 Testes de Carga
- [ ] 1.000 cartelas simultâneas marcando
- [ ] 100 sorteios por minuto
- [ ] 10 validações paralelas

#### 13.4 Coverage
- Mínimo 80% de cobertura de código

**Saída esperada:** Suite de testes robusta

---

### Etapa 14: Deploy e Documentação

#### 14.1 Deploy
- [ ] Docker Compose para prod
- [ ] CI/CD (GitHub Actions / GitLab)
- [ ] Database backup/restore
- [ ] Monitoramento e alertas

#### 14.2 Documentação
- [ ] README com setup
- [ ] API Documentation (Swagger/OpenAPI)
- [ ] Guia de administração
- [ ] Troubleshooting

#### 14.3 Entrega
- [ ] Sistema 100% funcional
- [ ] Pronto para eventos reais
- [ ] Documentação completa
- [ ] Suporte técnico iniciado

**Saída esperada:** Sistema em produção

---

## 📊 Timeline Estimada

| Fase | Etapas | Duração |
|------|--------|---------|
| Design | 1-3 | 🟢 Concluída |
| Setup | 4 | 1-2 semanas |
| Core | 5-8 | 3-4 semanas |
| Sorteio | 9-10 | 2-3 semanas |
| Relatórios | 11-12 | 2-3 semanas |
| Testes/Deploy | 13-14 | 2-3 semanas |
| **Total** | | **~12-18 semanas** |

---

## 🎯 Checklist de Aceitação Final

- [ ] Criar 100 eventos sem erro
- [ ] Gerar 2.000 cartelas em <5 min
- [ ] Gerar 2.000 PDFs em <10 min
- [ ] Sortear 75 números sem repetição
- [ ] Validar 1.000 bingos simultâneos
- [ ] 0 vazamento de seed
- [ ] 0 manipulação de cartelas
- [ ] Relatórios completos e auditáveis
- [ ] 99.9% uptime em teste de carga
- [ ] Documentação 100% completa

---

## Como Proceder

Para começar a Etapa 4, execute:

```bash
"Vamos para a etapa 4"
```

E o desenvolvimento iniciará exatamente de onde paramos.
