<?php

namespace App\Controllers\Settings;

use App\Models\WorkShiftModel;
use App\Models\HolidayModel;
use App\Models\EmployeeModel;

class WorkforceSettingsController extends BaseSettingsController
{
    private const DEFAULT_PER_PAGE = 25;
    private const MAX_PER_PAGE = 100;

    protected WorkShiftModel $workShiftModelInstance;
    protected HolidayModel   $holidayModelInstance;
    protected EmployeeModel  $employeeModelInstance;

    public function __construct()
    {
        parent::__construct();
        $this->workShiftModelInstance = new WorkShiftModel();
        $this->holidayModelInstance   = new HolidayModel();
        $this->employeeModelInstance  = new EmployeeModel();
    }

    private function workShiftModel(): WorkShiftModel
    {
        return $this->workShiftModelInstance;
    }

    private function employeeModel(): EmployeeModel
    {
        return $this->employeeModelInstance;
    }

    private function holidayModel(): HolidayModel
    {
        return $this->holidayModelInstance;
    }



    private function paginationParams(): array
    {
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        return [$page, $perPage];
    }

    private function paginationMeta($pager, int $page, int $perPage, int $count): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'count' => $count,
            'total' => (int) ($pager?->getTotal() ?? $count),
            'page_count' => (int) ($pager?->getPageCount() ?? 1),
        ];
    }

    public function workShifts()
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();
        [$page, $perPage] = $this->paginationParams();

        $records = $shiftModel
            ->orderBy('active', 'DESC')
            ->orderBy('type', 'ASC')
            ->orderBy('start_time', 'ASC')
            ->paginate($perPage, 'default', $page);

        return $this->response->setJSON([
            'success' => true,
            'data' => $records,
            'meta' => $this->paginationMeta($shiftModel->pager, $page, $perPage, count($records)),
        ]);
    }

    public function storeWorkShift()
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'start_time' => 'required',
            'end_time' => 'required',
            'type' => 'required|in_list[morning,afternoon,night,custom]',
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON(['success' => false, 'message' => implode(', ', $this->validator->getErrors())]);
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time' => $this->request->getPost('end_time'),
            'type' => $this->request->getPost('type'),
            'break_duration' => (int) $this->request->getPost('break_duration'),
            'color' => $this->request->getPost('color') ?: '#9DB89D',
            'active' => 1,
        ];

        if ($shiftModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada criada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao criar jornada']);
    }

    public function updateWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'start_time' => $this->request->getPost('start_time'),
            'end_time' => $this->request->getPost('end_time'),
            'type' => $this->request->getPost('type'),
            'break_duration' => (int) $this->request->getPost('break_duration'),
            'color' => $this->request->getPost('color') ?: '#9DB89D',
        ];

        if ($shiftModel->update($id, $data)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada atualizada com sucesso']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao atualizar jornada']);
    }

    public function toggleWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        $active = is_array($shift) ? ($shift['active'] ?? 0) : ($shift->active ?? 0);
        $shiftModel->update($id, ['active' => $active ? 0 : 1]);

        return $this->response->setJSON(['success' => true, 'message' => 'Status alterado']);
    }

    public function deleteWorkShift($id)
    {
        $this->requireAdminAccess();
        $shiftModel = $this->workShiftModel();

        $shift = $shiftModel->find($id);
        if (!$shift) {
            return $this->response->setJSON(['success' => false, 'message' => 'Jornada não encontrada']);
        }

        if ($shiftModel->delete($id)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Jornada excluída']);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Erro ao excluir']);
    }

    public function vacations()
    {
        $this->requireAdminAccess();

        $employeeModel = $this->employeeModel();
        $employees = $employeeModel->where('active', true)->orderBy('name', 'ASC')->findAll();

        return view('settings/vacations/index', [
            'employees' => $employees,
        ]);
    }

    public function holidays()
    {
        $this->requireAdminAccess();
        [$page, $perPage] = $this->paginationParams();

        $search     = (string) ($this->request->getGet('search') ?? '');
        $filterType = (string) ($this->request->getGet('type') ?? '');

        // Builder novo a cada chamada (via $this->db->table(), nao $this->where()
        // direto no Model) -- evita que o count abaixo herde o WHERE ja aplicado
        // na query paginada, problema ja visto em outros pontos do sistema quando
        // o mesmo Model e reaproveitado para mais de uma consulta na mesma request.
        $db = \Config\Database::connect();
        $scoped = static function () use ($db, $search, $filterType) {
            $builder = $db->table('holidays');
            if ($search !== '') {
                $builder->like('name', $search);
            }
            if ($filterType !== '') {
                $builder->where('type', $filterType);
            }
            return $builder;
        };

        $totalFiltered = (int) $scoped()->countAllResults();

        $listModel = $this->holidayModel();
        if ($search !== '') {
            $listModel = $listModel->like('name', $search);
        }
        if ($filterType !== '') {
            $listModel = $listModel->where('type', $filterType);
        }
        $records = $listModel
            ->orderBy('active', 'DESC')
            ->orderBy('date', 'ASC')
            ->paginate($perPage, 'default', $page);

        $currentYear = (int) date('Y');
        $nextYear    = $currentYear + 1;

        return view('settings/holidays/index', [
            'holidays'             => $records,
            'pager'                => $this->holidayModel()->pager,
            'filters'              => ['search' => $search, 'type' => $filterType],
            'meta'                 => ['total' => $totalFiltered, 'count' => count($records), 'page' => $page, 'per_page' => $perPage],
            'nationalCatalog'      => [
                $currentYear => $this->nationalHolidayCatalog($currentYear),
                $nextYear    => $this->nationalHolidayCatalog($nextYear),
            ],
            'localCatalog'         => $this->localHolidayCatalog(),
            'existingHolidayKeys'  => $this->existingHolidayKeys(),
            'currentYear'          => $currentYear,
            'nextYear'             => $nextYear,
        ]);
    }

    /**
     * Chaves "data|nome" ja cadastradas -- usado para desabilitar/marcar como
     * "ja cadastrado" os itens dos catalogos de feriados nacionais/locais nos
     * modais de adicao em lote, evitando duplicatas silenciosas.
     *
     * @return array<string,true>
     */
    private function existingHolidayKeys(): array
    {
        $rows = $this->holidayModel()->select('date, name')->findAll();
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row->date . '|' . $row->name] = true;
        }
        return $keys;
    }

    /**
     * Feriados nacionais fixos + moveis (calculados a partir da Pascoa) para
     * o ano informado. Carnaval e Corpus Christi sao ponto facultativo, nao
     * feriado estatutario -- marcados como 'non_working'.
     *
     * @return array<string,array{name:string,date:string,type:string,recurring:bool}>
     */
    private function nationalHolidayCatalog(int $year): array
    {
        $easter = $this->computeEaster($year);

        return [
            'confraternizacao' => ['name' => 'Confraternização Universal', 'date' => sprintf('%04d-01-01', $year), 'type' => 'national', 'recurring' => true],
            'carnaval_segunda' => ['name' => 'Carnaval (segunda-feira)', 'date' => $easter->modify('-48 days')->format('Y-m-d'), 'type' => 'non_working', 'recurring' => false],
            'carnaval_terca'   => ['name' => 'Carnaval (terça-feira)', 'date' => $easter->modify('-47 days')->format('Y-m-d'), 'type' => 'non_working', 'recurring' => false],
            'sexta_santa'      => ['name' => 'Sexta-feira Santa', 'date' => $easter->modify('-2 days')->format('Y-m-d'), 'type' => 'national', 'recurring' => false],
            'tiradentes'       => ['name' => 'Tiradentes', 'date' => sprintf('%04d-04-21', $year), 'type' => 'national', 'recurring' => true],
            'trabalho'         => ['name' => 'Dia do Trabalho', 'date' => sprintf('%04d-05-01', $year), 'type' => 'national', 'recurring' => true],
            'corpus_christi'   => ['name' => 'Corpus Christi', 'date' => $easter->modify('+60 days')->format('Y-m-d'), 'type' => 'non_working', 'recurring' => false],
            'independencia'    => ['name' => 'Independência do Brasil', 'date' => sprintf('%04d-09-07', $year), 'type' => 'national', 'recurring' => true],
            'aparecida'        => ['name' => 'Nossa Senhora Aparecida', 'date' => sprintf('%04d-10-12', $year), 'type' => 'national', 'recurring' => true],
            'finados'          => ['name' => 'Finados', 'date' => sprintf('%04d-11-02', $year), 'type' => 'national', 'recurring' => true],
            'consciencia_negra' => ['name' => 'Dia Nacional de Zumbi e da Consciência Negra', 'date' => sprintf('%04d-11-20', $year), 'type' => 'national', 'recurring' => true],
            'republica'        => ['name' => 'Proclamação da República', 'date' => sprintf('%04d-11-15', $year), 'type' => 'national', 'recurring' => true],
            'natal'            => ['name' => 'Natal', 'date' => sprintf('%04d-12-25', $year), 'type' => 'national', 'recurring' => true],
        ];
    }

    /**
     * Feriados locais (Goias/Goiania) de data fixa -- ano de referencia
     * irrelevante para os recorrentes (so mes/dia importam), usa o ano atual.
     * Fontes: Lei Municipal de Goiania n. 6968/1991 (24/10); 26/07 e 24/05
     * amplamente listados nos calendarios oficiais de feriados de GO/Goiania.
     *
     * @return array<string,array{name:string,date:string,type:string,recurring:bool}>
     */
    private function localHolidayCatalog(): array
    {
        $year = (int) date('Y');

        return [
            'fundacao_goias'      => ['name' => 'Fundação da Cidade de Goiás', 'date' => sprintf('%04d-07-26', $year), 'type' => 'state', 'recurring' => true],
            'aniversario_goiania' => ['name' => 'Aniversário de Goiânia', 'date' => sprintf('%04d-10-24', $year), 'type' => 'municipal', 'recurring' => true],
            'nsra_auxiliadora'    => ['name' => 'Nossa Senhora Auxiliadora (padroeira de Goiânia)', 'date' => sprintf('%04d-05-24', $year), 'type' => 'municipal', 'recurring' => true],
        ];
    }

    /**
     * Domingo de Pascoa pelo algoritmo de Meeus/Jones/Butcher (calendario
     * gregoriano) -- implementado sem depender da extensao calendar/
     * easter_date(), que pode nao estar habilitada no ambiente.
     */
    private function computeEaster(int $year): \DateTimeImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Adiciona em lote feriados de um dos catalogos pre-definidos (nacional
     * ou local GO/Goiania) -- usado pelos botoes "Feriados Nacionais" e
     * "Feriados Locais" da tela de feriados.
     */
    public function storeBulkHolidays()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $group = (string) ($this->request->getPost('group') ?? '');
        $keys  = (array) ($this->request->getPost('keys') ?? []);
        $year  = (int) ($this->request->getPost('year') ?? date('Y'));
        if ($year < 2020 || $year > 2100) {
            $year = (int) date('Y');
        }

        $catalog = match ($group) {
            'national' => $this->nationalHolidayCatalog($year),
            'local_go' => $this->localHolidayCatalog(),
            default    => [],
        };

        if (empty($catalog) || empty($keys)) {
            $this->setError('Selecione ao menos um feriado para adicionar.');
            return redirect()->to(sp_route_url('settings.holidays'));
        }

        $db = \Config\Database::connect();

        $added = 0;
        $skipped = 0;
        foreach ($keys as $key) {
            if (! is_string($key) || ! isset($catalog[$key])) {
                continue;
            }
            $item = $catalog[$key];

            // $db->table() gera um builder novo a cada chamada -- diferente de
            // encadear where()/first() direto no Model dentro de um loop, o que
            // arriscaria herdar condicoes de iteracoes anteriores.
            $exists = $db->table('holidays')->where('date', $item['date'])->where('name', $item['name'])->countAllResults() > 0;
            if ($exists) {
                $skipped++;
                continue;
            }

            $holidayModel->insert([
                'name'         => $item['name'],
                'description'  => null,
                'date'         => $item['date'],
                'type'         => $item['type'],
                'recurring'    => $item['recurring'] ? 1 : 0,
                'blocks_punch' => 1,
                'active'       => 1,
            ]);
            $added++;
        }

        if ($added > 0) {
            $msg = "{$added} feriado(s) adicionado(s) com sucesso.";
            if ($skipped > 0) {
                $msg .= " {$skipped} já cadastrado(s) foram ignorado(s).";
            }
            $this->setSuccess($msg);
        } else {
            $this->setError('Nenhum feriado novo adicionado — todos os selecionados já estavam cadastrados.');
        }

        return redirect()->to(sp_route_url('settings.holidays'));
    }

    public function holidaysJson()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();
        [$page, $perPage] = $this->paginationParams();

        $records = $holidayModel
            ->orderBy('active', 'DESC')
            ->orderBy('date', 'ASC')
            ->paginate($perPage, 'default', $page);

        return $this->response->setJSON([
            'success' => true,
            'data'    => $records,
            'meta'    => $this->paginationMeta($holidayModel->pager, $page, $perPage, count($records)),
        ]);
    }

    public function storeHoliday()
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'date' => 'required|valid_date',
            'type' => 'required|in_list[national,state,municipal,company,non_working]',
        ];

        if (!$this->validate($rules)) {
            $this->setError(implode('<br>', $this->validator->getErrors()));
            return redirect()->back()->withInput();
        }

        $data = [
            'name'         => $this->request->getPost('name'),
            'description'  => $this->request->getPost('description') ?: null,
            'date'         => $this->request->getPost('date'),
            'type'         => $this->request->getPost('type'),
            'recurring'    => $this->request->getPost('recurring') ? 1 : 0,
            'blocks_punch' => $this->request->getPost('blocks_punch') ? 1 : 0,
            'active'       => 1,
        ];

        if ($holidayModel->insert($data)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado criado com sucesso']);
            }
            $this->setSuccess('Feriado/dia não trabalhado criado com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao criar feriado']);
        }
        $this->setError('Erro ao criar feriado.');
        return redirect()->back()->withInput();
    }

    public function editHoliday($id)
    {
        $this->requireAdminAccess();
        $holiday = $this->holidayModel()->find($id);
        if (!$holiday) {
            $this->setError('Feriado não encontrado.');
            return redirect()->to(sp_route_url('settings.holidays'));
        }

        return view('settings/holidays/edit', ['holiday' => $holiday]);
    }

    public function updateHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[255]',
            'date' => 'required|valid_date',
            'type' => 'required|in_list[national,state,municipal,company,non_working]',
        ];

        if (!$this->validate($rules)) {
            $this->setError(implode('<br>', $this->validator->getErrors()));
            return redirect()->back()->withInput();
        }

        $data = [
            'name'         => $this->request->getPost('name'),
            'description'  => $this->request->getPost('description') ?: null,
            'date'         => $this->request->getPost('date'),
            'type'         => $this->request->getPost('type'),
            'recurring'    => $this->request->getPost('recurring') ? 1 : 0,
            'blocks_punch' => $this->request->getPost('blocks_punch') ? 1 : 0,
        ];

        if ($holidayModel->update($id, $data)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado atualizado com sucesso']);
            }
            $this->setSuccess('Feriado atualizado com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao atualizar feriado']);
        }
        $this->setError('Erro ao atualizar feriado.');
        return redirect()->back()->withInput();
    }

    public function toggleHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        $active = is_array($holiday) ? ($holiday['active'] ?? 0) : ($holiday->active ?? 0);
        $holidayModel->skipValidation(true)->update($id, ['active' => $active ? 0 : 1]);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Status alterado']);
        }
        $this->setSuccess($active ? 'Feriado desativado.' : 'Feriado ativado.');
        return redirect()->to(sp_route_url('settings.holidays'));
    }

    public function deleteHoliday($id)
    {
        $this->requireAdminAccess();
        $holidayModel = $this->holidayModel();

        $holiday = $holidayModel->find($id);
        if (!$holiday) {
            return $this->response->setJSON(['success' => false, 'message' => 'Feriado não encontrado']);
        }

        if ($holidayModel->delete($id)) {
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => true, 'message' => 'Feriado excluído']);
            }
            $this->setSuccess('Feriado excluído com sucesso!');
            return redirect()->to(sp_route_url('settings.holidays'));
        }
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Erro ao excluir']);
        }
        $this->setError('Erro ao excluir feriado.');
        return redirect()->to(sp_route_url('settings.holidays'));
    }
}
