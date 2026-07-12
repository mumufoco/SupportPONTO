<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var string[]
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class,
        \App\Validation\CustomRules::class, // Custom rules
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single',
    ];

    // --------------------------------------------------------------------
    // Custom Error Messages
    // --------------------------------------------------------------------

    /**
     * Custom error messages in Portuguese
     *
     * @var array<string, string>
     */
    public array $customMessages = [
        // CPF/CNPJ
        'valid_cpf' => 'O campo {field} deve conter um CPF válido.',
        'valid_cnpj' => 'O campo {field} deve conter um CNPJ válido.',

        // Password
        'strong_password' => 'O campo {field} deve conter pelo menos 12 caracteres, incluindo maiúsculas, minúsculas, números e caracteres especiais.',

        // Brazilian formats
        'valid_phone_br' => 'O campo {field} deve conter um telefone válido.',
        'valid_cep' => 'O campo {field} deve conter um CEP válido.',
        'valid_date_br' => 'O campo {field} deve conter uma data válida no formato dd/mm/yyyy.',

        // Coordinates
        'valid_coordinates' => 'O campo {field} deve conter coordenadas válidas (latitude,longitude).',
        'valid_latitude' => 'O campo {field} deve conter uma latitude válida (-90 a 90).',
        'valid_longitude' => 'O campo {field} deve conter uma longitude válida (-180 a 180).',

        // Time
        'valid_time' => 'O campo {field} deve conter um horário válido (HH:MM).',

        // Data formats
        'valid_base64_image' => 'O campo {field} deve conter uma imagem válida em base64.',
        'valid_json' => 'O campo {field} deve conter um JSON válido.',

        // Application specific
        'valid_punch_type' => 'O campo {field} deve ser entrada, saida, intervalo_inicio ou intervalo_fim. Aliases legados só são aceitos por compatibilidade transitória.',
        'valid_employee_role' => 'O campo {field} deve ser admin, gestor, funcionario, rh ou dpo.',
        'valid_hex_color' => 'O campo {field} deve conter uma cor hexadecimal válida (#RRGGBB).',

        // Age validation
        'valid_min_age' => 'Você deve ter pelo menos {param} anos.',

        // Date validations
        'is_future_date' => 'O campo {field} deve ser uma data futura.',
        'is_past_date' => 'O campo {field} deve ser uma data passada.',
        'is_business_day' => 'O campo {field} deve ser um dia útil (segunda a sexta).',

        // Uniqueness
        'unique_employee_code' => 'O código do funcionário já está em uso.',

        // Ranges
        'valid_hours' => 'O campo {field} deve estar entre 0 e 24 horas.',
        'valid_percentage' => 'O campo {field} deve estar entre 0 e 100.',

        // File size
        'max_file_size' => 'O arquivo {field} excede o tamanho máximo permitido.',

        // Network
        'valid_ip_address' => 'O campo {field} deve conter um endereço IP válido.',
        'valid_url_safe' => 'O campo {field} deve conter uma URL válida (http ou https).',

        // Database
        'exists' => 'O valor do campo {field} não existe.',

        // NSR
        'valid_nsr' => 'O campo {field} deve conter um NSR válido.',
    ];

    // --------------------------------------------------------------------
    // Validation Rules Templates
    // --------------------------------------------------------------------

    /**
     * Employee validation rules
     */
    public array $employee = [
        'name' => [
            'label' => 'Nome',
            'rules' => 'required|min_length[3]|max_length[255]',
            'errors' => [
                'required' => 'O nome é obrigatório.',
                'min_length' => 'O nome deve ter pelo menos 3 caracteres.',
            ],
        ],
        'email' => [
            'label' => 'E-mail',
            'rules' => 'required|valid_email|max_length[255]',
            'errors' => [
                'required' => 'O e-mail é obrigatório.',
                'valid_email' => 'O e-mail deve ser válido.',
            ],
        ],
        'cpf' => [
            'label' => 'CPF',
            'rules' => 'required|valid_cpf',
            'errors' => [
                'required' => 'O CPF é obrigatório.',
            ],
        ],
        'password' => [
            'label' => 'Senha',
            'rules' => 'required|strong_password',
            'errors' => [
                'required' => 'A senha é obrigatória.',
            ],
        ],
        'role' => [
            'label' => 'Função',
            'rules' => 'required|valid_employee_role',
            'errors' => [
                'required' => 'A função é obrigatória.',
            ],
        ],
    ];

    /**
     * Login validation rules
     */
    public array $login = [
        'email' => [
            'label' => 'E-mail',
            'rules' => 'required|valid_email',
            'errors' => [
                'required' => 'O e-mail é obrigatório.',
                'valid_email' => 'O e-mail deve ser válido.',
            ],
        ],
        'password' => [
            'label' => 'Senha',
            'rules' => 'required|min_length[6]',
            'errors' => [
                'required' => 'A senha é obrigatória.',
                'min_length' => 'A senha deve ter pelo menos 6 caracteres.',
            ],
        ],
    ];

    /**
     * Time punch validation rules
     */
    public array $timePunch = [
        'punch_type' => [
            'label' => 'Tipo de registro',
            'rules' => 'required|valid_punch_type',
            'errors' => [
                'required' => 'O tipo de registro é obrigatório.',
            ],
        ],
        'latitude' => [
            'label' => 'Latitude',
            'rules' => 'permit_empty|valid_latitude',
        ],
        'longitude' => [
            'label' => 'Longitude',
            'rules' => 'permit_empty|valid_longitude',
        ],
    ];

    /**
     * Geofence validation rules
     */
    public array $geofence = [
        'name' => [
            'label' => 'Nome',
            'rules' => 'required|min_length[3]|max_length[100]',
            'errors' => [
                'required' => 'O nome é obrigatório.',
            ],
        ],
        'latitude' => [
            'label' => 'Latitude',
            'rules' => 'required|valid_latitude',
            'errors' => [
                'required' => 'A latitude é obrigatória.',
            ],
        ],
        'longitude' => [
            'label' => 'Longitude',
            'rules' => 'required|valid_longitude',
            'errors' => [
                'required' => 'A longitude é obrigatória.',
            ],
        ],
        'radius_meters' => [
            'label' => 'Raio (metros)',
            'rules' => 'required|numeric|greater_than[0]',
            'errors' => [
                'required' => 'O raio é obrigatório.',
                'greater_than' => 'O raio deve ser maior que zero.',
            ],
        ],
    ];

    /**
     * Justification validation rules
     */
    public array $justification = [
        'date' => [
            'label' => 'Data',
            'rules' => 'required|valid_date',
            'errors' => [
                'required' => 'A data é obrigatória.',
                'valid_date' => 'A data deve ser válida.',
            ],
        ],
        'reason' => [
            'label' => 'Motivo',
            'rules' => 'required|min_length[10]|max_length[1000]',
            'errors' => [
                'required' => 'O motivo é obrigatório.',
                'min_length' => 'O motivo deve ter pelo menos 10 caracteres.',
            ],
        ],
    ];

    /**
     * Settings validation rules
     */
    public array $settings = [
        'company_name' => [
            'label' => 'Nome da Empresa',
            'rules' => 'required|min_length[3]|max_length[255]',
            'errors' => [
                'required' => 'O nome da empresa é obrigatório.',
            ],
        ],
        'company_cnpj' => [
            'label' => 'CNPJ',
            'rules' => 'permit_empty|valid_cnpj',
        ],
        'work_start_time' => [
            'label' => 'Horário de início',
            'rules' => 'required|valid_time',
            'errors' => [
                'required' => 'O horário de início é obrigatório.',
            ],
        ],
        'work_end_time' => [
            'label' => 'Horário de término',
            'rules' => 'required|valid_time',
            'errors' => [
                'required' => 'O horário de término é obrigatório.',
            ],
        ],
        'daily_hours' => [
            'label' => 'Horas diárias',
            'rules' => 'required|valid_hours',
            'errors' => [
                'required' => 'As horas diárias são obrigatórias.',
            ],
        ],
    ];
}
