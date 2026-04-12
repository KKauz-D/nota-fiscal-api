# Guia Completo das Rotas da API

**Base URL:** `http://localhost:8000/api`

Todas as respostas seguem o formato padrão:

```json
{
  "success": true|false,
  "message": "Descrição da operação",
  "data": { ... }
}
```

**Autenticação:** Todas as rotas (exceto `/api/login`) exigem o header:

```
Authorization: Bearer {token}
Accept: application/json
```

---

## 1. Autenticação

### POST `/api/login`

Realiza login e retorna um token Sanctum.

**Auth:** Nenhuma (rota pública)

**Body (JSON):**

| Campo      | Tipo   | Obrigatório | Descrição        |
|------------|--------|:-----------:|------------------|
| `username` | string | Sim         | Nome de usuário  |
| `password` | string | Sim         | Senha            |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}'
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "1|abc123def456...",
    "user": {
      "id": 1,
      "username": "admin",
      "role": "admin"
    }
  }
}
```

**Resposta 401:**

```json
{
  "success": false,
  "message": "Credenciais inválidas.",
  "data": null
}
```

---

### POST `/api/logout`

Revoga o token atual.

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Logout realizado com sucesso.",
  "data": null
}
```

---

### GET `/api/me`

Retorna os dados do usuário autenticado.

**Exemplo:**

```bash
curl http://localhost:8000/api/me \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "id": 1,
    "username": "admin",
    "role": "admin",
    "last_login_at": "2026-04-11T23:14:45.000000Z"
  }
}
```

---

## 2. Dashboard

### GET `/api/dashboard`

Retorna métricas agregadas de lotes e notas fiscais.

**Query Params (opcionais):**

| Param  | Tipo   | Descrição                         |
|--------|--------|-----------------------------------|
| `cnpj` | string | Filtrar dados por CNPJ específico |

**Exemplo:**

```bash
curl "http://localhost:8000/api/dashboard?cnpj=12345678000199" \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "total_lotes": 15,
    "total_notas": 48,
    "notas_emitidas": 45,
    "notas_canceladas": 3,
    "valor_total": "125430.50",
    "ultimos_lotes": [
      {
        "id": 15,
        "numero_lote": "1234567890",
        "status": "Processado com Sucesso",
        "rps_count": 5,
        "protocolo": "ABC123",
        "created_at": "2026-04-11T20:00:00.000000Z"
      }
    ]
  }
}
```

---

## 3. Empresas (Certificados)

### GET `/api/empresas`

Lista todas as empresas com certificado configurado.

**Exemplo:**

```bash
curl http://localhost:8000/api/empresas \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": [
    {
      "cnpj": "12345678000199",
      "cert_file": "cert_12345678000199.pfx",
      "saved_at": "2026-04-11"
    }
  ]
}
```

---

### POST `/api/empresas`

Cadastra uma empresa com seu certificado digital A1 (.pfx).

**Body (multipart/form-data):**

| Campo          | Tipo   | Obrigatório | Descrição                           |
|----------------|--------|:-----------:|-------------------------------------|
| `cnpj`         | string | Sim         | CNPJ (14 dígitos, sem formatação)   |
| `im`           | string | Não         | Inscrição Municipal                 |
| `pfx_password` | string | Sim         | Senha do certificado .pfx           |
| `pfx_file`     | file   | Sim         | Arquivo do certificado (.pfx)       |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/empresas \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "im=123456" \
  -F "pfx_password=minha_senha" \
  -F "pfx_file=@/caminho/certificado.pfx"
```

**Resposta 201:**

```json
{
  "success": true,
  "message": "Empresa/certificado salvo com sucesso.",
  "data": {
    "cnpj": "12345678000199",
    "cert_file": "cert_12345678000199.pfx"
  }
}
```

---

### DELETE `/api/empresas/{cnpj}`

Remove a empresa e o certificado associado.

| Param  | Local | Descrição                    |
|--------|-------|------------------------------|
| `cnpj` | URL   | CNPJ da empresa (14 dígitos) |

**Exemplo:**

```bash
curl -X DELETE http://localhost:8000/api/empresas/12345678000199 \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Empresa/certificado removido com sucesso.",
  "data": null
}
```

---

## 4. Lotes (Batches)

### GET `/api/lotes`

Lista os lotes de RPS com paginação.

**Query Params (opcionais):**

| Param      | Tipo    | Descrição                                                                      |
|------------|---------|--------------------------------------------------------------------------------|
| `cnpj`     | string  | Filtrar por CNPJ                                                               |
| `status`   | string  | Filtrar por status (`Transmitido`, `Processado com Sucesso`, `Erro de Processamento`, `Nao Processado`) |
| `per_page` | integer | Itens por página (padrão: 20)                                                  |
| `page`     | integer | Número da página                                                               |

**Exemplo:**

```bash
curl "http://localhost:8000/api/lotes?cnpj=12345678000199&status=Transmitido&per_page=10" \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "data": [
      {
        "id": 1,
        "cnpj": "12345678000199",
        "im": "123456",
        "numero_lote": "1234567890",
        "rps_count": 3,
        "xml_file": "lote_rps_Lote_20260411201500.xml",
        "protocolo": "PROT123ABC",
        "ambiente": "homolog",
        "status": "Transmitido",
        "situacao_code": null,
        "errors": null,
        "created_at": "2026-04-11T20:15:00+00:00",
        "updated_at": "2026-04-11T20:15:00+00:00",
        "invoices": []
      }
    ],
    "links": {
      "first": "http://localhost:8000/api/lotes?page=1",
      "last": "http://localhost:8000/api/lotes?page=1",
      "prev": null,
      "next": null
    },
    "meta": {
      "current_page": 1,
      "from": 1,
      "last_page": 1,
      "per_page": 20,
      "to": 1,
      "total": 1
    }
  }
}
```

---

### POST `/api/lotes/preview`

Lê um arquivo Excel e retorna os RPS estruturados com numeração temporária, **sem transmitir**.
Útil para o frontend exibir uma tela de edição antes do envio.

**Body (multipart/form-data):**

| Campo        | Tipo   | Obrigatório | Descrição                        |
|--------------|--------|:-----------:|----------------------------------|
| `cnpj`       | string | Sim         | CNPJ da empresa emissora         |
| `excel_file` | file   | Sim         | Planilha de RPS (.xlsx ou .xls)  |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/lotes/preview \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "excel_file=@/caminho/planilha.xlsx"
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Preview gerado com sucesso.",
  "data": [
    {
      "InfRps": {
        "IdentificacaoRps": {
          "Numero": "1",
          "Serie": "A",
          "Tipo": "1"
        },
        "DataEmissao": "2026-04-11T10:00:00",
        "NaturezaOperacao": "1",
        "OptanteSimplesNacional": "2",
        "IncentivadorCultural": "2",
        "Status": "1",
        "Servico": {
          "Valores": {
            "ValorServicos": 1500.00,
            "IssRetido": "2",
            "ValorIss": 75.0,
            "Aliquota": 5.0,
            "ValorLiquidoNfse": 1500.00
          },
          "ItemListaServico": "1.03",
          "CodigoTributacaoMunicipio": "2684",
          "Discriminacao": "Prestação de serviço de TI",
          "CodigoMunicipio": "2304400"
        },
        "Tomador": {
          "IdentificacaoTomador": {
            "CpfCnpj": { "Cnpj": "98765432000111" }
          },
          "RazaoSocial": "EMPRESA CLIENTE LTDA",
          "Endereco": { "..." : "..." },
          "Contato": { "Email": "contato@cliente.com" }
        }
      }
    }
  ]
}
```

---

### POST `/api/lotes/transmitir`

Transmite um lote de RPS ao GINFES. Aceita dados editados (JSON) **ou** um arquivo Excel.

**Body (multipart/form-data ou JSON):**

| Campo        | Tipo   | Obrigatório                | Descrição                                      |
|--------------|--------|:--------------------------:|-------------------------------------------------|
| `cnpj`       | string | Sim                        | CNPJ da empresa emissora                        |
| `im`         | string | Sim                        | Inscrição Municipal                             |
| `ambiente`   | string | Sim                        | `homolog` ou `prod`                             |
| `edited_rps` | array  | Condicional (sem Excel)    | Array de RPS editados (formato do preview)      |
| `excel_file` | file   | Condicional (sem edited_rps)| Planilha RPS (.xlsx ou .xls)                   |

> **Nota:** Envie `edited_rps` OU `excel_file` — um dos dois é obrigatório.

**Exemplo com Excel:**

```bash
curl -X POST http://localhost:8000/api/lotes/transmitir \
  -H "Authorization: Bearer 1|abc123..." \
  -F "cnpj=12345678000199" \
  -F "im=123456" \
  -F "ambiente=homolog" \
  -F "excel_file=@/caminho/planilha.xlsx"
```

**Exemplo com RPS editados (JSON):**

```bash
curl -X POST http://localhost:8000/api/lotes/transmitir \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{
    "cnpj": "12345678000199",
    "im": "123456",
    "ambiente": "homolog",
    "edited_rps": [ { "InfRps": { ... } } ]
  }'
```

**Resposta 201:**

```json
{
  "success": true,
  "message": "Lote transmitido com sucesso.",
  "data": {
    "id": 1,
    "cnpj": "12345678000199",
    "im": "123456",
    "numero_lote": "1234567890",
    "rps_count": 3,
    "xml_file": "lote_rps_Lote_20260411201500.xml",
    "protocolo": "PROT123ABC",
    "ambiente": "homolog",
    "status": "Transmitido",
    "situacao_code": null,
    "errors": null,
    "created_at": "2026-04-11T20:15:00+00:00",
    "updated_at": "2026-04-11T20:15:00+00:00",
    "invoices": []
  }
}
```

---

### POST `/api/lotes/{id}/sincronizar`

Consulta o status do lote no GINFES. Se processado (código 4), busca as notas geradas e salva no banco.

| Param | Local | Descrição   |
|-------|-------|-------------|
| `id`  | URL   | ID do lote  |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/lotes/1/sincronizar \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Processado com Sucesso",
  "data": {
    "status": "Processado com Sucesso",
    "situacao_code": 4,
    "invoices_count": 3,
    "batch": {
      "id": 1,
      "status": "Processado com Sucesso",
      "situacao_code": 4,
      "invoices": [
        {
          "id": 1,
          "numero_nfse": "12345",
          "codigo_verificacao": "ABCD1234",
          "data_emissao": "2026-04-11T00:00:00+00:00",
          "tomador_nome": "EMPRESA CLIENTE LTDA",
          "valor_servicos": "1500.00",
          "status": "Emitida"
        }
      ]
    }
  }
}
```

**Códigos de situação (situacao_code):**

| Código | Status                   | Descrição                              |
|:------:|--------------------------|----------------------------------------|
| 1      | Não Recebido             | Lote não foi recebido pela prefeitura  |
| 2      | Não Processado           | Lote aguardando processamento          |
| 3      | Erro de Processamento    | Ocorreram erros no processamento       |
| 4      | Processado com Sucesso   | NFS-e geradas — notas são recuperadas  |

---

### POST `/api/lotes/{id}/reenviar`

Retransmite um lote usando os dados originais salvos, gerando um novo lote.

| Param | Local | Descrição    |
|-------|-------|--------------|
| `id`  | URL   | ID do lote original |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/lotes/1/reenviar \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 201:** Mesmo formato do `transmitir` (retorna o novo lote).

**Resposta 422:**

```json
{
  "success": false,
  "message": "Lote não possui dados originais para reenvio.",
  "data": null
}
```

---

### DELETE `/api/lotes/{id}`

Exclui um lote e todas as notas associadas.

| Param | Local | Descrição   |
|-------|-------|-------------|
| `id`  | URL   | ID do lote  |

**Exemplo:**

```bash
curl -X DELETE http://localhost:8000/api/lotes/1 \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Lote excluído com sucesso.",
  "data": null
}
```

---

## 5. Notas Fiscais (Invoices)

### GET `/api/notas`

Lista as notas fiscais com paginação.

**Query Params (opcionais):**

| Param      | Tipo    | Descrição                                 |
|------------|---------|-------------------------------------------|
| `cnpj`     | string  | Filtrar por CNPJ do emitente              |
| `status`   | string  | Filtrar: `Emitida`, `Cancelada`, `Erro`   |
| `per_page` | integer | Itens por página (padrão: 20)             |
| `page`     | integer | Número da página                          |

**Exemplo:**

```bash
curl "http://localhost:8000/api/notas?cnpj=12345678000199&status=Emitida" \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "data": [
      {
        "id": 1,
        "batch_id": 1,
        "numero_nfse": "12345",
        "codigo_verificacao": "ABCD1234",
        "data_emissao": "2026-04-11T00:00:00+00:00",
        "tomador_nome": "EMPRESA CLIENTE LTDA",
        "valor_servicos": "1500.00",
        "cnpj": "12345678000199",
        "im": "123456",
        "status": "Emitida",
        "motivo_cancelamento": null,
        "created_at": "2026-04-11T20:15:00+00:00"
      }
    ],
    "links": { "..." : "..." },
    "meta": { "total": 1, "per_page": 20, "..." : "..." }
  }
}
```

---

### POST `/api/notas/{id}/cancelar`

Cancela uma NFS-e junto ao GINFES.

| Param | Local | Descrição    |
|-------|-------|--------------|
| `id`  | URL   | ID da nota   |

**Body (JSON, opcional):**

| Campo    | Tipo   | Obrigatório | Descrição                  |
|----------|--------|:-----------:|----------------------------|
| `motivo` | string | Não         | Motivo do cancelamento (max 500 chars) |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/notas/1/cancelar \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"motivo": "Dados incorretos do tomador"}'
```

**Resposta 200 (cancelamento bem-sucedido):**

```json
{
  "success": true,
  "message": "Nota fiscal cancelada com sucesso no GINFES.",
  "data": {
    "id": 1,
    "numero_nfse": "12345",
    "status": "Cancelada",
    "motivo_cancelamento": "Dados incorretos do tomador"
  }
}
```

**Resposta 422 (falha no cancelamento):**

```json
{
  "success": false,
  "message": "Nota não pode ser cancelada - prazo expirado",
  "data": { "code": "E44" }
}
```

---

## 6. Tomadores (Customers)

### GET `/api/tomadores`

Lista os tomadores cadastrados com busca.

**Query Params (opcionais):**

| Param      | Tipo    | Descrição                                                    |
|------------|---------|--------------------------------------------------------------|
| `search`   | string  | Busca por razão social ou CPF/CNPJ (parcial)                 |
| `per_page` | integer | Itens por página (padrão: 50)                                |
| `page`     | integer | Número da página                                             |

**Exemplo:**

```bash
curl "http://localhost:8000/api/tomadores?search=empresa" \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "data": [
      {
        "id": 1,
        "cpf_cnpj": "98765432000111",
        "cpf_cnpj_formatado": "98.765.432/0001-11",
        "razao_social": "EMPRESA CLIENTE LTDA",
        "endereco": "Rua das Flores",
        "numero": "100",
        "bairro": "Centro",
        "cod_mun": "2304400",
        "uf": "CE",
        "cep": "60000000",
        "email": "contato@cliente.com",
        "telefone": "8599991234",
        "created_at": "2026-04-11T20:00:00+00:00",
        "updated_at": "2026-04-11T20:00:00+00:00"
      }
    ],
    "links": { "..." : "..." },
    "meta": { "total": 1, "per_page": 50, "..." : "..." }
  }
}
```

---

### PUT `/api/tomadores/{id}`

Atualiza os dados de um tomador.

| Param | Local | Descrição       |
|-------|-------|-----------------|
| `id`  | URL   | ID do tomador   |

**Body (JSON):**

| Campo          | Tipo   | Obrigatório | Descrição                        |
|----------------|--------|:-----------:|----------------------------------|
| `cpf_cnpj`     | string | Não         | CPF (11 chars) ou CNPJ (14 chars)|
| `razao_social` | string | Não         | Razão Social (max 115)           |
| `endereco`     | string | Não         | Logradouro (max 255)             |
| `numero`       | string | Não         | Número (max 20)                  |
| `bairro`       | string | Não         | Bairro (max 100)                 |
| `cod_mun`      | string | Não         | Código IBGE do município (max 10)|
| `uf`           | string | Não         | UF (2 chars, ex: CE)             |
| `cep`          | string | Não         | CEP sem formatação (max 8)       |
| `email`        | string | Não         | E-mail válido (max 255)          |
| `telefone`     | string | Não         | Telefone (max 20)                |

**Exemplo:**

```bash
curl -X PUT http://localhost:8000/api/tomadores/1 \
  -H "Authorization: Bearer 1|abc123..." \
  -H "Content-Type: application/json" \
  -d '{"razao_social": "EMPRESA CLIENTE ATUALIZADA LTDA", "email": "novo@email.com"}'
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Tomador atualizado com sucesso.",
  "data": { "id": 1, "razao_social": "EMPRESA CLIENTE ATUALIZADA LTDA", "..." : "..." }
}
```

---

### DELETE `/api/tomadores/{id}`

Remove um tomador.

**Exemplo:**

```bash
curl -X DELETE http://localhost:8000/api/tomadores/1 \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "Tomador excluído com sucesso.",
  "data": null
}
```

---

### POST `/api/tomadores/importar`

Importa tomadores em lote a partir de uma planilha Excel.

**Colunas esperadas na planilha (linha 1 = cabeçalho):**

| Coluna | Campo          |
|:------:|----------------|
| A      | cpf_cnpj       |
| B      | razao_social   |
| C      | endereco       |
| D      | numero         |
| E      | bairro         |
| F      | uf             |
| G      | cep            |
| H      | cod_mun        |
| I      | email          |
| J      | telefone       |

> Se `cod_mun` estiver vazio e o CEP for válido, a API consulta o ViaCEP automaticamente.

**Body (multipart/form-data):**

| Campo        | Tipo | Obrigatório | Descrição                   |
|--------------|------|:-----------:|-----------------------------|
| `excel_file` | file | Sim         | Planilha (.xlsx, .xls)      |

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/tomadores/importar \
  -H "Authorization: Bearer 1|abc123..." \
  -F "excel_file=@/caminho/tomadores.xlsx"
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "25 tomador(es) importado(s) com sucesso!",
  "data": { "count": 25 }
}
```

---

## 7. Downloads

### GET `/api/download/xml/{id}`

Baixa o arquivo XML assinado de um lote.

| Param | Local | Descrição   |
|-------|-------|-------------|
| `id`  | URL   | ID do lote  |

**Exemplo:**

```bash
curl -O http://localhost:8000/api/download/xml/1 \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:** Arquivo XML binário (`Content-Type: application/xml`)

**Resposta 404:**

```json
{
  "success": false,
  "message": "Arquivo XML não encontrado.",
  "data": null
}
```

---

## 8. Auditoria

### GET `/api/auditoria`

Lista os logs de auditoria. **Somente administradores** (role = `admin`).

**Query Params (opcionais):**

| Param      | Tipo    | Descrição                                                                                  |
|------------|---------|--------------------------------------------------------------------------------------------|
| `action`   | string  | Filtrar por ação: `transmitir_lote`, `sincronizar_lote`, `reenviar_lote`, `excluir_lote`, `cancelar_nota`, `salvar_certificado`, `excluir_certificado` |
| `per_page` | integer | Itens por página (padrão: 50)                                                              |
| `page`     | integer | Número da página                                                                           |

**Exemplo:**

```bash
curl "http://localhost:8000/api/auditoria?action=cancelar_nota" \
  -H "Authorization: Bearer 1|abc123..."
```

**Resposta 200:**

```json
{
  "success": true,
  "message": "",
  "data": {
    "data": [
      {
        "id": 1,
        "user_id": 1,
        "user_name": "admin",
        "action": "cancelar_nota",
        "details": "NFS-e 12345 cancelada: Nota fiscal cancelada com sucesso no GINFES.",
        "ip_address": "127.0.0.1",
        "result": null,
        "created_at": "2026-04-11T21:00:00+00:00"
      }
    ],
    "links": { "..." : "..." },
    "meta": { "total": 1, "per_page": 50, "..." : "..." }
  }
}
```

**Resposta 403 (não é admin):**

```json
{
  "success": false,
  "message": "Acesso restrito a administradores."
}
```

---

## Códigos de Erro HTTP

| Código | Significado                                          |
|:------:|------------------------------------------------------|
| 200    | Sucesso                                              |
| 201    | Criado (lote transmitido, empresa cadastrada)        |
| 401    | Não autenticado ou credenciais inválidas             |
| 403    | Sem permissão (rota admin)                           |
| 404    | Recurso não encontrado                               |
| 422    | Validação falhou ou operação não processável         |
| 500    | Erro interno do servidor                             |

---

## Fluxo Típico de Uso

```
1. POST /api/login                    → obter token
2. POST /api/empresas                 → cadastrar certificado
3. POST /api/lotes/preview            → fazer upload do Excel, revisar RPS
4. POST /api/lotes/transmitir         → enviar lote ao GINFES
5. POST /api/lotes/{id}/sincronizar   → consultar resultado (repetir até código 4)
6. GET  /api/notas                    → ver notas geradas
7. GET  /api/download/xml/{id}        → baixar XML
```
