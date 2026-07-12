# API REST Documentation

Documentação completa da API REST para integração mobile e aplicações externas.

## Base URL

```
https://seu-dominio.com.br/api
```

## Autenticação

A API utiliza autenticação via Bearer Token.

### Obter Token (Login)

**Endpoint:** `POST /api/auth/login`

**Request:**
```json
{
  "email": "funcionario@empresa.com.br",
  "password": "senha123"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Login realizado com sucesso.",
  "data": {
    "token": "eyJlbXBsb3llZV9pZCI6MSwidGltZXN0YW1wIjoxNjk...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "employee": {
      "id": 1,
      "name": "João Silva",
      "email": "funcionario@empresa.com.br",
      "role": "funcionario",
      "department": "TI",
      "position": "Desenvolvedor",
      "unique_code": "ABC123",
      "has_face_biometric": true,
      "has_fingerprint_biometric": false
    }
  }
}
```

### Usar Token

Todas as requisições subsequentes devem incluir o token no header:

```
Authorization: Bearer {token}
```

### Renovar Token

**Endpoint:** `POST /api/auth/refresh`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Token renovado com sucesso.",
  "data": {
    "token": "novo_token_aqui",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### Logout

**Endpoint:** `POST /api/auth/logout`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Logout realizado com sucesso."
}
```

## Endpoints

### 1. Autenticação (Auth)

#### Obter Dados do Usuário Atual

**Endpoint:** `GET /api/auth/me`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "João Silva",
    "email": "funcionario@empresa.com.br",
    "cpf": "123.456.789-00",
    "role": "funcionario",
    "department": "TI",
    "position": "Desenvolvedor",
    "unique_code": "ABC123",
    "phone": "(11) 98765-4321",
    "admission_date": "2023-01-15",
    "daily_hours": 8.0,
    "weekly_hours": 44.0,
    "work_start_time": "08:00:00",
    "work_end_time": "18:00:00",
    "hours_balance": 5.5,
    "has_face_biometric": true,
    "has_fingerprint_biometric": false,
    "active": true
  }
}
```

#### Alterar Senha

**Endpoint:** `POST /api/auth/change-password`

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "current_password": "senha_atual",
  "new_password": "Nova@Senha123",
  "new_password_confirm": "Nova@Senha123"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Senha alterada com sucesso."
}
```

---

### 2. Registro de Ponto (Time Punch)

#### Registrar Ponto

**Endpoint:** `POST /api/punch`

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "punch_type": "entrada",
  "method": "facial",
  "latitude": -23.550520,
  "longitude": -46.633308,
  "photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA..."
}
```

**Campos:**
- `punch_type`: `entrada`, `saida`, `intervalo_inicio`, `intervalo_fim`
- `method`: `codigo`, `qrcode`, `facial`, `biometria`
- `latitude`: opcional, coordenada GPS
- `longitude`: opcional, coordenada GPS
- `photo`: opcional, necessário para method=facial

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Ponto registrado com sucesso!",
  "data": {
    "id": 12345,
    "nsr": 98765,
    "punch_time": "15/01/2024 09:00",
    "punch_type": "entrada",
    "method": "facial",
    "hash": "a3b2c1d4e5f6..."
  }
}
```

#### Registros de Hoje

**Endpoint:** `GET /api/punch/today`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "date": "15/01/2024",
    "punches": [
      {
        "id": 1,
        "nsr": 98765,
        "time": "09:00",
        "punch_type": "entrada",
        "method": "facial",
        "latitude": -23.550520,
        "longitude": -46.633308
      },
      {
        "id": 2,
        "nsr": 98766,
        "time": "12:00",
        "punch_type": "intervalo_inicio",
        "method": "codigo",
        "latitude": null,
        "longitude": null
      }
    ],
    "summary": {
      "total_hours": 3.0,
      "work_hours": 3.0,
      "break_hours": 0.0,
      "total_punches": 2
    }
  }
}
```

#### Histórico de Registros

**Endpoint:** `GET /api/punch/history?month=2024-01&page=1`

**Headers:** `Authorization: Bearer {token}`

**Parâmetros:**
- `month`: Mês no formato YYYY-MM (default: mês atual)
- `page`: Número da página (default: 1)

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "nsr": 98765,
      "date": "15/01/2024",
      "time": "09:00",
      "punch_type": "entrada",
      "method": "facial",
      "latitude": -23.550520,
      "longitude": -46.633308
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 50,
    "total": 120,
    "last_page": 3
  }
}
```

#### Resumo Mensal

**Endpoint:** `GET /api/punch/summary?month=2024-01`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "month": "Janeiro/2024",
    "summary": {
      "total_hours": 176.5,
      "expected_hours": 176.0,
      "balance": 0.5,
      "days_worked": 22,
      "total_punches": 88
    },
    "daily_records": [
      {
        "date": "01/01/2024",
        "day_of_week": "Seg",
        "hours_worked": 8.0,
        "expected_hours": 8.0,
        "balance": 0.0,
        "punch_count": 4
      }
    ]
  }
}
```

#### Verificar Hash de Registro

**Endpoint:** `GET /api/punch/{id}/verify`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "punch_id": 12345,
    "nsr": 98765,
    "hash": "a3b2c1d4e5f6...",
    "is_valid": true,
    "punch_time": "15/01/2024 09:00:00"
  }
}
```

#### Obter Cercas Virtuais

**Endpoint:** `GET /api/punch/geofences?latitude=-23.5505&longitude=-46.6333`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Sede Principal",
      "description": "Escritório central",
      "latitude": -23.550520,
      "longitude": -46.633308,
      "radius_meters": 100,
      "distance_meters": 25.5,
      "distance_readable": "25 metros",
      "within": true
    }
  ]
}
```

---

### 3. Funcionário (Employee)

#### Perfil do Funcionário

**Endpoint:** `GET /api/employee/profile`

**Headers:** `Authorization: Bearer {token}`

**Response:** Ver `/api/auth/me`

#### Saldo de Horas

**Endpoint:** `GET /api/employee/balance`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "hours_balance": 5.5,
    "hours_balance_formatted": "+05:30",
    "extra_hours_balance": 5.5,
    "owed_hours_balance": 0.0
  }
}
```

#### Estatísticas do Funcionário

**Endpoint:** `GET /api/employee/statistics?month=2024-01`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "period": {
      "month": "Janeiro/2024",
      "start": "01/01/2024",
      "end": "31/01/2024"
    },
    "hours": {
      "worked": 176.5,
      "expected": 176.0,
      "balance": 0.5,
      "average_per_day": 8.02
    },
    "attendance": {
      "days_worked": 22,
      "late_arrivals": 2,
      "missing_days": 0
    },
    "justifications": {
      "total": 1,
      "pending": 0,
      "approved": 1,
      "rejected": 0
    }
  }
}
```

#### Atualizar Perfil

**Endpoint:** `PUT /api/employee/profile`

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "phone": "(11) 98765-4321"
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Perfil atualizado com sucesso."
}
```

#### Listar Equipe (Gestores)

**Endpoint:** `GET /api/employee/team`

**Headers:** `Authorization: Bearer {token}`

**Requer:** role = `gestor`, `rh` ou `admin`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "name": "Maria Santos",
      "email": "maria@empresa.com.br",
      "role": "funcionario",
      "department": "TI",
      "position": "Analista",
      "unique_code": "DEF456"
    }
  ]
}
```

#### Buscar por Código

**Endpoint:** `GET /api/employee/by-code/{code}`

**Headers:** `Authorization: Bearer {token}`

**Requer:** role = `gestor`, `rh` ou `admin`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Maria Santos",
    "email": "maria@empresa.com.br",
    "role": "funcionario",
    "department": "TI",
    "position": "Analista",
    "unique_code": "DEF456",
    "active": true
  }
}
```

---

### 4. Biometria (Biometric)

#### Cadastrar Biometria Facial

**Endpoint:** `POST /api/biometric/enroll/face`

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA..."
}
```

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Biometria facial cadastrada com sucesso!",
  "data": {
    "template_id": 10,
    "quality": 0.95,
    "facial_area": {
      "x": 100,
      "y": 150,
      "w": 200,
      "h": 250
    }
  }
}
```

#### Testar Reconhecimento Facial

**Endpoint:** `POST /api/biometric/test/face`

**Headers:** `Authorization: Bearer {token}`

**Request:**
```json
{
  "photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAA..."
}
```

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "recognized": true,
    "is_current_user": true,
    "similarity": 0.85,
    "distance": 0.15,
    "message": "Reconhecimento bem-sucedido!"
  }
}
```

#### Listar Templates Biométricos

**Endpoint:** `GET /api/biometric/templates`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 10,
      "biometric_type": "face",
      "enrollment_quality": 0.95,
      "model_used": "VGG-Face",
      "active": true,
      "created_at": "2024-01-15 10:00:00"
    }
  ]
}
```

#### Excluir Template Biométrico

**Endpoint:** `DELETE /api/biometric/face/{id}`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Template biométrico excluído com sucesso."
}
```

#### Conceder Consentimento LGPD

**Endpoint:** `POST /api/biometric/consent`

**Headers:** `Authorization: Bearer {token}`

**Response:** `201 Created`
```json
{
  "success": true,
  "message": "Consentimento registrado com sucesso!"
}
```

#### Revogar Consentimento LGPD

**Endpoint:** `POST /api/biometric/revoke-consent`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Consentimento revogado. Seus dados biométricos foram desativados."
}
```

#### Status do Consentimento

**Endpoint:** `GET /api/biometric/consent/status`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "has_consent": true
  }
}
```

---

### 5. Notificações (Notifications)

#### Listar Notificações

**Endpoint:** `GET /api/notifications?page=1&per_page=20`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Espelho de ponto disponível",
      "message": "O espelho de ponto do mês 01/2024 está disponível.",
      "type": "info",
      "link": "/reports/timesheet/2024-01",
      "read": false,
      "read_at": null,
      "created_at": "15/01/2024 10:00",
      "time_ago": "há 2 horas"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

#### Notificações Não Lidas

**Endpoint:** `GET /api/notifications/unread?limit=10`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": [...],
  "count": 5
}
```

#### Contagem de Não Lidas

**Endpoint:** `GET /api/notifications/unread/count`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "unread_count": 5
  }
}
```

#### Marcar como Lida

**Endpoint:** `PUT /api/notifications/{id}/read`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Notificação marcada como lida."
}
```

#### Marcar Todas como Lidas

**Endpoint:** `PUT /api/notifications/read-all`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "5 notificação(ões) marcada(s) como lida(s).",
  "count": 5
}
```

#### Excluir Notificação

**Endpoint:** `DELETE /api/notifications/{id}`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "Notificação excluída."
}
```

#### Excluir Todas Lidas

**Endpoint:** `DELETE /api/notifications/read-all`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "message": "10 notificação(ões) excluída(s).",
  "count": 10
}
```

#### Obter Notificação por ID

**Endpoint:** `GET /api/notifications/{id}`

**Headers:** `Authorization: Bearer {token}`

**Response:** `200 OK`
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Espelho de ponto disponível",
    "message": "O espelho de ponto do mês 01/2024 está disponível.",
    "type": "info",
    "link": "/reports/timesheet/2024-01",
    "read": true,
    "read_at": "15/01/2024 12:30",
    "created_at": "15/01/2024 10:00",
    "time_ago": "há 2 horas"
  }
}
```

---

## Códigos de Status HTTP

- `200 OK` - Requisição bem-sucedida
- `201 Created` - Recurso criado com sucesso
- `400 Bad Request` - Dados inválidos
- `401 Unauthorized` - Não autenticado
- `403 Forbidden` - Sem permissão
- `404 Not Found` - Recurso não encontrado
- `429 Too Many Requests` - Limite de requisições excedido
- `500 Internal Server Error` - Erro no servidor

## Formato de Erro

```json
{
  "success": false,
  "message": "Mensagem de erro",
  "data": {
    "details": "Detalhes adicionais se disponíveis"
  }
}
```

## Rate Limiting

- **Auth endpoints**: 5 requisições por 5 minutos
- **Punch endpoints**: 10 requisições por minuto
- **API geral**: 60 requisições por minuto

Headers de resposta:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1705324800
```

## Exemplo de Integração (JavaScript)

```javascript
// Login
const response = await fetch('https://seu-dominio.com.br/api/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'funcionario@empresa.com.br',
    password: 'senha123'
  })
});

const data = await response.json();
const token = data.data.token;

// Registrar ponto
const punchResponse = await fetch('https://seu-dominio.com.br/api/punch', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    punch_type: 'entrada',
    method: 'codigo',
    latitude: -23.550520,
    longitude: -46.633308
  })
});

const punchData = await punchResponse.json();
console.log(punchData);
```


## Escopo operacional por perfil

- `admin`: acesso global aos jobs e relatórios operacionais.
- `gestor`: acesso gerencial com restrição por departamento quando o fluxo usar escopo de equipe.
- `rh`: acesso gerencial global nos fluxos operacionais já alinhados ao modelo canônico.
- `dpo`: foco em auditoria, compliance e dashboard dedicado; não participa por padrão dos jobs operacionais assíncronos.
