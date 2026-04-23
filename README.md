# NotaFiscal Fortal API

API backend para emissão, consulta e cancelamento de Notas Fiscais de Serviço Eletrônicas (NFS-e) junto à prefeitura de Fortaleza (GINFES).

## Requisitos

- PHP 8.2+
- Extensões PHP: `curl`, `mbstring`, `openssl`, `pdo_sqlite` (ou `pdo_mysql`), `soap`, `zip`, `gd`, `intl`
- Composer 2.x
- Certificado digital A1 (.pfx) da empresa emissora

## Instalação

```bash
# Clonar e instalar dependências
composer install

# Copiar e configurar variáveis de ambiente
cp .env.example .env
php artisan key:generate

# Criar banco de dados (SQLite por padrão)
touch database/database.sqlite
php artisan migrate

# Criar usuário admin padrão (admin / admin123)
php artisan db:seed
```

## Configuração

Edite o `.env` com os dados do seu ambiente:

```env
# Banco de dados (SQLite padrão, ou MySQL/PostgreSQL)
DB_CONNECTION=sqlite

# GINFES endpoints
GINFES_HOMOLOG_URL=https://isshomo.sefin.fortaleza.ce.gov.br/grpfor-iss/ServiceGinfesImplService?wsdl
GINFES_PROD_URL=https://iss.fortaleza.ce.gov.br/grpfor-iss/ServiceGinfesImplService?wsdl
GINFES_COD_MUNICIPIO=2304400
```

## Estrutura do projeto

```
app/
├── Enums/                    # BatchStatus, InvoiceStatus, Environment, UserRole
├── Http/
│   ├── Controllers/Api/      # 8 controllers (Auth, Dashboard, Batch, Invoice, ...)
│   ├── Middleware/            # EnsureUserIsAdmin
│   ├── Requests/             # Form requests com validação
│   ├── Resources/            # API Resources (JSON transform)
│   └── Traits/               # ApiResponse
├── Models/                   # User, Batch, Invoice, Customer, RpsControl, AuditLog
├── Providers/                # GinfesServiceProvider
└── Services/
    ├── Certificate/          # CertificateService (XMLDSig), CertificateStorageService
    ├── External/             # BrasilApiService, ViaCepService
    ├── Ginfes/               # SoapService, XmlService, CancelService, BatchSyncService
    └── Import/               # ExcelImportService, CustomerImportService, CnaeService
```

## Endpoints da API

Todas as rotas usam o prefixo `/api`. Rotas autenticadas exigem header `Authorization: Bearer {token}`.

### Autenticação
| Método | Rota            | Descrição                    |
|--------|-----------------|------------------------------|
| POST   | `/api/login`    | Login (retorna token Sanctum)|
| POST   | `/api/logout`   | Logout (revoga token)        |
| GET    | `/api/me`       | Dados do usuário autenticado |

### Dashboard
| Método | Rota              | Descrição                       |
|--------|-------------------|---------------------------------|
| GET    | `/api/dashboard`  | Totais, valores, últimos lotes  |

### Empresas (Certificados)
| Método | Rota                    | Descrição                        |
|--------|-------------------------|----------------------------------|
| GET    | `/api/empresas`         | Listar empresas configuradas     |
| POST   | `/api/empresas`         | Cadastrar empresa + certificado  |
| DELETE | `/api/empresas/{cnpj}`  | Remover empresa                  |

### Lotes (Batches)
| Método | Rota                              | Descrição                          |
|--------|-----------------------------------|------------------------------------|
| GET    | `/api/lotes`                      | Listar lotes (com filtros)         |
| POST   | `/api/lotes/preview`              | Preview: lê Excel sem transmitir   |
| POST   | `/api/lotes/transmitir`           | Transmitir lote ao GINFES          |
| POST   | `/api/lotes/{id}/sincronizar`     | Sincronizar status do lote         |
| POST   | `/api/lotes/{id}/reenviar`        | Reenviar lote existente            |
| DELETE | `/api/lotes/{id}`                 | Excluir lote                       |

### Notas Fiscais (Invoices)
| Método | Rota                            | Descrição                    |
|--------|---------------------------------|------------------------------|
| GET    | `/api/notas`                    | Listar notas fiscais         |
| POST   | `/api/notas/{id}/cancelar`      | Cancelar NFS-e no GINFES     |

### Tomadores (Customers)
| Método | Rota                        | Descrição                           |
|--------|-----------------------------|-------------------------------------|
| GET    | `/api/tomadores`            | Listar tomadores (busca por nome/doc)|
| PUT    | `/api/tomadores/{id}`       | Atualizar tomador                   |
| DELETE | `/api/tomadores/{id}`       | Excluir tomador                     |
| POST   | `/api/tomadores/importar`   | Importar tomadores via Excel        |

### Downloads e Auditoria
| Método | Rota                          | Descrição                      |
|--------|-------------------------------|--------------------------------|
| GET    | `/api/download/xml/{id}`      | Baixar XML do lote             |
| GET    | `/api/auditoria`              | Logs de auditoria (admin only) |

## Testando a API

### 1. Iniciar o servidor

```bash
php artisan serve
```

### 2. Login

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

Resposta:
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "1|abc123...",
    "user": { "id": 1, "username": "admin", "role": "admin" }
  }
}
```

### 3. Usar o token nas requisições

```bash
# Dashboard
curl http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer 1|abc123..."

# Listar empresas
curl http://localhost:8000/api/empresas \
  -H "Authorization: Bearer 1|abc123..."

# Listar lotes
curl http://localhost:8000/api/lotes \
  -H "Authorization: Bearer 1|abc123..."
```

### 4. Cadastrar empresa com certificado

```bash
curl -X POST http://localhost:8000/api/empresas \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "im=123456" \
  -F "pfx_password=senha_do_certificado" \
  -F "pfx_file=@/caminho/para/certificado.pfx"
```

### 5. Transmitir lote de RPS

```bash
# Preview (lê Excel sem enviar)
curl -X POST http://localhost:8000/api/lotes/preview \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "excel_file=@/caminho/para/planilha.xlsx"

# Transmitir
curl -X POST http://localhost:8000/api/lotes/transmitir \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "im=123456" \
  -F "ambiente=homolog" \
  -F "excel_file=@/caminho/para/planilha.xlsx"
```

## Credenciais padrão

| Usuário | Senha      | Role  |
|---------|------------|-------|
| admin   | admin123   | admin |
