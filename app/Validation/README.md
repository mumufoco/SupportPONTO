# Custom Validation Rules

Este documento descreve as regras de validação personalizadas disponíveis no sistema.

## Regras Disponíveis

### Documentos Brasileiros

#### `valid_cpf`
Valida CPF brasileiro com dígitos verificadores.

```php
'cpf' => 'required|valid_cpf'
```

#### `valid_cnpj`
Valida CNPJ brasileiro com dígitos verificadores.

```php
'cnpj' => 'required|valid_cnpj'
```

### Segurança

#### `strong_password`
Valida senha forte com:
- Mínimo 12 caracteres
- Pelo menos uma letra maiúscula
- Pelo menos uma letra minúscula
- Pelo menos um número
- Pelo menos um caractere especial

```php
'password' => 'required|strong_password'
```

### Formatos Brasileiros

#### `valid_phone_br`
Valida telefone brasileiro (10 ou 11 dígitos).

```php
'phone' => 'required|valid_phone_br'
```

#### `valid_cep`
Valida CEP brasileiro (8 dígitos).

```php
'cep' => 'required|valid_cep'
```

#### `valid_date_br`
Valida data no formato brasileiro (dd/mm/yyyy).

```php
'date' => 'required|valid_date_br'
```

### Geolocalização

#### `valid_coordinates`
Valida coordenadas GPS no formato "lat,lng".

```php
'coordinates' => 'required|valid_coordinates'
```

#### `valid_latitude`
Valida latitude (-90 a 90).

```php
'latitude' => 'required|valid_latitude'
```

#### `valid_longitude`
Valida longitude (-180 a 180).

```php
'longitude' => 'required|valid_longitude'
```

### Horários

#### `valid_time`
Valida horário no formato HH:MM ou HH:MM:SS.

```php
'work_start_time' => 'required|valid_time'
```

### Dados Binários

#### `valid_base64_image`
Valida imagem em base64 (JPEG ou PNG).

```php
'photo' => 'required|valid_base64_image'
```

#### `valid_json`
Valida string JSON válida.

```php
'metadata' => 'required|valid_json'
```

### Aplicação Específica

#### `valid_punch_type`
Valida tipo de registro de ponto.

Valores aceitos: `entrada`, `saida`, `intervalo_inicio`, `intervalo_fim`

```php
'punch_type' => 'required|valid_punch_type'
```

#### `valid_employee_role`
Valida função do funcionário.

Valores aceitos: `admin`, `gestor`, `funcionario`

```php
'role' => 'required|valid_employee_role'
```

#### `valid_hex_color`
Valida cor hexadecimal (#RRGGBB ou #RGB).

```php
'color' => 'required|valid_hex_color'
```

### Idade

#### `valid_min_age[anos]`
Valida idade mínima.

```php
'birthdate' => 'required|valid_min_age[18]'
```

### Datas Especiais

#### `is_future_date`
Valida que a data é futura.

```php
'event_date' => 'required|is_future_date'
```

#### `is_past_date`
Valida que a data é passada.

```php
'admission_date' => 'required|is_past_date'
```

#### `is_business_day`
Valida que a data é dia útil (segunda a sexta).

```php
'work_date' => 'required|is_business_day'
```

### Unicidade

#### `unique_employee_code[id]`
Valida que o código do funcionário é único.

```php
'unique_code' => 'required|unique_employee_code'

// Ao atualizar, excluir próprio ID
'unique_code' => 'required|unique_employee_code[' . $employee_id . ']'
```

### Valores Numéricos

#### `valid_hours`
Valida horas entre 0 e 24.

```php
'daily_hours' => 'required|valid_hours'
```

#### `valid_percentage`
Valida percentual entre 0 e 100.

```php
'discount' => 'required|valid_percentage'
```

#### `valid_nsr`
Valida NSR (Número Sequencial de Registro) > 0.

```php
'nsr' => 'required|valid_nsr'
```

### Arquivos

#### `max_file_size[bytes]`
Valida tamanho máximo de arquivo em bytes.

```php
// Máximo 5MB (5 * 1024 * 1024 = 5242880)
'file' => 'required|max_file_size[5242880]'
```

### Rede

#### `valid_ip_address`
Valida endereço IP (IPv4 ou IPv6).

```php
'ip' => 'required|valid_ip_address'
```

#### `valid_url_safe`
Valida URL segura (http ou https).

```php
'website' => 'required|valid_url_safe'
```

### Banco de Dados

#### `exists[tabela.campo]`
Valida que o valor existe no banco.

```php
'employee_id' => 'required|exists[employees.id]'
```

## Uso em Controllers

### Método 1: Validação Inline

```php
public function store()
{
    $rules = [
        'name' => 'required|min_length[3]',
        'cpf' => 'required|valid_cpf',
        'password' => 'required|strong_password',
        'role' => 'required|valid_employee_role',
    ];

    if (!$this->validate($rules)) {
        return redirect()->back()
            ->withInput()
            ->with('errors', $this->validator->getErrors());
    }

    // Processamento...
}
```

### Método 2: Usando Templates Pré-definidos

```php
public function store()
{
    if (!$this->validate('employee')) {
        return redirect()->back()
            ->withInput()
            ->with('errors', $this->validator->getErrors());
    }

    // Processamento...
}
```

### Método 3: Validação Manual

```php
$validation = \Config\Services::validation();

$validation->setRules([
    'cpf' => 'required|valid_cpf',
]);

if (!$validation->run($data)) {
    $errors = $validation->getErrors();
}
```

## Templates Pré-definidos

Os seguintes templates estão disponíveis em `Config/Validation.php`:

- `employee` - Validação de cadastro de funcionário
- `login` - Validação de login
- `timePunch` - Validação de registro de ponto
- `geofence` - Validação de cerca virtual
- `justification` - Validação de justificativa
- `settings` - Validação de configurações

### Exemplo de Uso

```php
if (!$this->validate('login')) {
    return redirect()->back()
        ->withInput()
        ->with('errors', $this->validator->getErrors());
}
```

## Mensagens de Erro Customizadas

Todas as regras possuem mensagens de erro em português configuradas em `Config/Validation.php`.

### Sobrescrever Mensagens

```php
$rules = [
    'cpf' => [
        'label' => 'CPF',
        'rules' => 'required|valid_cpf',
        'errors' => [
            'required' => 'Por favor, informe seu CPF.',
            'valid_cpf' => 'O CPF informado é inválido.',
        ],
    ],
];

$this->validate($rules);
```

## Exemplos Práticos

### Validação de Cadastro de Funcionário

```php
$rules = [
    'name' => 'required|min_length[3]|max_length[255]',
    'email' => 'required|valid_email|is_unique[employees.email]',
    'cpf' => 'required|valid_cpf|cpf_is_unique',
    'password' => 'required|strong_password',
    'password_confirm' => 'required|matches[password]',
    'role' => 'required|valid_employee_role',
    'phone' => 'permit_empty|valid_phone_br',
    'admission_date' => 'required|valid_date|is_past_date',
    'daily_hours' => 'required|valid_hours',
];
```

### Validação de Registro de Ponto

```php
$rules = [
    'punch_type' => 'required|valid_punch_type',
    'latitude' => 'permit_empty|valid_latitude',
    'longitude' => 'permit_empty|valid_longitude',
    'photo' => 'permit_empty|valid_base64_image|max_file_size[5242880]',
];
```

### Validação de Cerca Virtual

```php
$rules = [
    'name' => 'required|min_length[3]|max_length[100]',
    'description' => 'permit_empty|max_length[500]',
    'latitude' => 'required|valid_latitude',
    'longitude' => 'required|valid_longitude',
    'radius_meters' => 'required|numeric|greater_than[0]',
    'color' => 'permit_empty|valid_hex_color',
];
```

### Validação de Configurações

```php
$rules = [
    'company_name' => 'required|min_length[3]',
    'company_cnpj' => 'permit_empty|valid_cnpj',
    'work_start_time' => 'required|valid_time',
    'work_end_time' => 'required|valid_time',
    'daily_hours' => 'required|valid_hours',
    'weekly_hours' => 'required|valid_hours',
    'deepface_threshold' => 'required|valid_percentage',
];
```

## Testando Validações

### Exemplo de Teste Unitário

```php
public function testValidCPF()
{
    $validation = \Config\Services::validation();

    $data = ['cpf' => '123.456.789-09'];
    $rules = ['cpf' => 'required|valid_cpf'];

    $validation->setRules($rules);
    $result = $validation->run($data);

    $this->assertTrue($result);
}
```
