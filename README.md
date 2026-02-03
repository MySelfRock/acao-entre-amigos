# 🎰 Sistema Híbrido de Bingo

Sistema completo de bingo híbrido (digital + presencial) com suporte a 2.000+ cartelas, sorteio em tempo real e validação automática.

## 📋 Características

- **Geração Determinística:** Cartelas únicas e reproduzíveis via seed
- **5 Rodadas:** 5 subcartelas (5x5) por cartela
- **Sorteio ao Vivo:** WebSocket com broadcast em tempo real
- **Validação Automática:** Bingo digital e presencial (QR Code)
- **Relatórios Completos:** Auditoria imutável de todo o evento
- **Escalável:** Suporta 2.000+ cartelas por evento
- **Seguro:** Seed nunca exposto, hash criptográfico, rate limiting

## 🏗️ Arquitetura

```
┌─────────────────┐
│  Frontend Web   │  (Digital Players)
└────────┬────────┘
         │
┌─────────┴──────────────┐
│   Laravel 10 API       │  (Admin, Operators, Auditors)
│   - Events Management  │
│   - Live Draws         │
│   - Bingo Validation   │
└────────┬──────────────┘
         │
    ┌────┴─────────┐
    │              │
┌───▼────┐   ┌────▼─────┐
│ MySQL  │   │   Redis   │  (Cache, Queue)
└────────┘   └───────────┘
         │
┌─────────┴──────────────┐
│   Python 3.11 API      │  (Generator Service)
│   - Ticket Generation  │
│   - PDF Creation       │
│   - Validation Logic   │
└────────────────────────┘
```

## 🚀 Início Rápido

### Pré-requisitos
- Docker & Docker Compose
- Git

### Instalação

1. **Clone o repositório:**
```bash
git clone https://github.com/MySelfRock/acao-entre-amigos.git
cd acao-entre-amigos
```

2. **Configure variáveis de ambiente:**
```bash
cp .env.example .env
# Edite .env conforme necessário
```

3. **Inicie os serviços:**
```bash
docker-compose up -d
```

4. **Inicialize o banco de dados:**
```bash
docker-compose exec laravel php artisan migrate
docker-compose exec laravel php artisan db:seed
```

5. **Verifique os serviços:**
```bash
# Laravel API
curl http://localhost:8000/api/health

# Python Generator
curl http://localhost:8001/health
```

## 📊 Estrutura de Pastas

```
bingo-system/
├── backend-laravel/          # Laravel 10 Admin API
│   ├── app/
│   │   ├── Models/
│   │   ├── Controllers/
│   │   ├── Services/
│   │   └── Jobs/
│   ├── database/migrations/
│   ├── routes/api.php
│   ├── composer.json
│   └── Dockerfile
│
├── generator-python/         # Python Generator Service
│   ├── app/
│   │   ├── main.py
│   │   ├── bingo_generator.py
│   │   ├── pdf_generator.py
│   │   ├── security.py
│   │   └── models.py
│   ├── requirements.txt
│   └── Dockerfile
│
├── docs/                     # Documentation
│   ├── ETAPA-1-BANCO-DADOS.md
│   ├── ETAPA-2-OPENAPI.md
│   └── ETAPA-3-LAYOUT.md
│
├── docker-compose.yml
└── PLANO_DESENVOLVIMENTO.md
```

## 🔄 Fluxo Principal

### 1. Criar Evento
```bash
curl -X POST http://localhost:8000/api/events \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Bingo Beneficente",
    "description": "Evento híbrido",
    "event_date": "2024-05-10T19:00:00",
    "total_cards": 2000
  }'
```

### 2. Gerar Cartelas
```bash
curl -X POST http://localhost:8000/api/events/{event_id}/generate-cards \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"rounds": 5}'
```

### 3. Gerar PDFs
```bash
curl -X POST http://localhost:8000/api/events/{event_id}/generate-pdfs \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json"
```

### 4. Iniciar Sorteio
```bash
curl -X POST http://localhost:8000/api/events/{event_id}/start \
  -H "Authorization: Bearer TOKEN"
```

### 5. Sortear Número
```bash
curl -X POST http://localhost:8000/api/events/{event_id}/draw \
  -H "Authorization: Bearer TOKEN"
```

## 📚 Documentação

- [Plano Completo de Desenvolvimento](./PLANO_DESENVOLVIMENTO.md)
- [Banco de Dados](./docs/ETAPA-1-BANCO-DADOS.md)
- [Contrato OpenAPI](./docs/ETAPA-2-OPENAPI.md)
- [Layout da Cartela](./docs/ETAPA-3-LAYOUT.md)

## 🧪 Testes

### Teste o serviço Python
```bash
docker-compose exec python pytest -v
```

### Teste o Laravel
```bash
docker-compose exec laravel php artisan test
```

### Teste de carga
```bash
docker-compose exec laravel php artisan tinker
# Gerar 100 eventos de teste
factory(App\Models\Event::class, 100)->create();
```

## 🔐 Segurança

- ✅ Seed nunca exposto ao cliente
- ✅ Hash SHA-256 para verificação
- ✅ HMAC para comunicação inter-serviços
- ✅ Rate limiting em endpoints críticos
- ✅ Validação 100% no backend
- ✅ Logs imutáveis de auditoria
- ✅ CORS e CSRF protection

## 📈 Status de Desenvolvimento

- [x] Etapa 1: Banco de Dados
- [x] Etapa 2: Contrato OpenAPI
- [x] Etapa 3: Layout da Cartela
- [x] Etapa 4: Setup do Código Base
- [ ] Etapa 5: Autenticação
- [ ] Etapa 6: Criação de Evento
- [ ] Etapa 7: Geração de Cartelas
- [ ] Etapa 8: Geração de PDFs
- [ ] Etapa 9: Sorteio ao Vivo
- [ ] Etapa 10: Validação de Bingo
- [ ] Etapa 11: Relatórios
- [ ] Etapa 12: Segurança
- [ ] Etapa 13: Testes
- [ ] Etapa 14: Deploy

## 🤝 Contribuindo

1. Crie uma branch: `git checkout -b feature/sua-feature`
2. Commit suas mudanças: `git commit -m "Adiciona sua feature"`
3. Push para a branch: `git push origin feature/sua-feature`
4. Abra um Pull Request

## 📄 Licença

Este projeto está licenciado sob a MIT License - veja o arquivo [LICENSE](LICENSE) para detalhes.

## 👥 Time

- **Desenvolvedor:** Claude Code
- **Projeto:** Sistema de Bingo Híbrido
- **Organização:** MySelfRock

---

**Status:** 🔧 Em Desenvolvimento (Fase 2 - Infraestrutura)

Siga o [PLANO_DESENVOLVIMENTO.md](./PLANO_DESENVOLVIMENTO.md) para acompanhar o progresso.
