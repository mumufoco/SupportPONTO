<?php

declare(strict_types=1);

if (! function_exists('sp_route_path')) {
    /**
     * Gera um caminho interno baseado em alias de rota.
     */
    function sp_route_path(string $routeName, ...$params): string
    {
        try {
            $path = route_to($routeName, ...$params);
            if (is_string($path) && $path !== '') {
                return '/' . ltrim($path, '/');
            }
        } catch (\Throwable $e) {
            // Fallback controlado para evitar quebra em contexto CLI/static.
        }

        return '/';
    }
}

if (! function_exists('sp_route_url')) {
    /**
     * Gera uma URL absoluta baseada em alias de rota.
     */
    function sp_route_url(string $routeName, ...$params): string
    {
        $path = sp_route_path($routeName, ...$params);

        return site_url(ltrim($path, '/'));
    }
}


if (! function_exists('sp_login_path')) {
    function sp_login_path(): string { return sp_route_path('login'); }
}
if (! function_exists('sp_login_url')) {
    function sp_login_url(): string { return sp_route_url('login'); }
}

if (! function_exists('sp_dashboard_path')) {
    function sp_dashboard_path(): string { return sp_route_path('dashboard'); }
}
if (! function_exists('sp_dashboard_url')) {
    function sp_dashboard_url(): string { return sp_route_url('dashboard'); }
}
if (! function_exists('sp_profile_path')) {
    function sp_profile_path(): string { return sp_route_path('profile'); }
}
if (! function_exists('sp_profile_url')) {
    function sp_profile_url(): string { return sp_route_url('profile'); }
}


if (! function_exists('sp_employees_index_path')) {
    function sp_employees_index_path(): string { return sp_route_path('employees'); }
}
if (! function_exists('sp_employees_index_url')) {
    function sp_employees_index_url(): string { return sp_route_url('employees'); }
}
if (! function_exists('sp_employees_create_path')) {
    function sp_employees_create_path(): string { return sp_route_path('employees.create'); }
}
if (! function_exists('sp_employees_create_url')) {
    function sp_employees_create_url(): string { return sp_route_url('employees.create'); }
}

if (! function_exists('sp_shifts_index_path')) {
    function sp_shifts_index_path(): string { return sp_route_path('shifts'); }
}
if (! function_exists('sp_shifts_index_url')) {
    function sp_shifts_index_url(): string { return sp_route_url('shifts'); }
}
if (! function_exists('sp_shifts_create_path')) {
    function sp_shifts_create_path(): string { return sp_route_path('shifts.create'); }
}
if (! function_exists('sp_shifts_create_url')) {
    function sp_shifts_create_url(): string { return sp_route_url('shifts.create'); }
}
if (! function_exists('sp_shifts_show_path')) {
    function sp_shifts_show_path(int|string $id): string { return sp_route_path('shifts.show', $id); }
}
if (! function_exists('sp_shifts_show_url')) {
    function sp_shifts_show_url(int|string $id): string { return sp_route_url('shifts.show', $id); }
}
if (! function_exists('sp_shifts_edit_path')) {
    function sp_shifts_edit_path(int|string $id): string { return sp_route_path('shifts.edit', $id); }
}
if (! function_exists('sp_shifts_edit_url')) {
    function sp_shifts_edit_url(int|string $id): string { return sp_route_url('shifts.edit', $id); }
}
if (! function_exists('sp_shifts_store_path')) {
    function sp_shifts_store_path(): string { return sp_route_path('shifts.store'); }
}
if (! function_exists('sp_shifts_store_url')) {
    function sp_shifts_store_url(): string { return sp_route_url('shifts.store'); }
}
if (! function_exists('sp_shifts_update_path')) {
    function sp_shifts_update_path(int|string $id): string { return sp_route_path('shifts.update', $id); }
}
if (! function_exists('sp_shifts_update_url')) {
    function sp_shifts_update_url(int|string $id): string { return sp_route_url('shifts.update', $id); }
}
if (! function_exists('sp_shifts_delete_path')) {
    function sp_shifts_delete_path(int|string $id): string { return sp_route_path('shifts.delete', $id); }
}
if (! function_exists('sp_shifts_delete_url')) {
    function sp_shifts_delete_url(int|string $id): string { return sp_route_url('shifts.delete', $id); }
}
if (! function_exists('sp_shifts_clone_path')) {
    function sp_shifts_clone_path(int|string $id): string { return sp_route_path('shifts.clone', $id); }
}
if (! function_exists('sp_shifts_clone_url')) {
    function sp_shifts_clone_url(int|string $id): string { return sp_route_url('shifts.clone', $id); }
}
if (! function_exists('sp_shifts_statistics_path')) {
    function sp_shifts_statistics_path(): string { return sp_route_path('shifts.statistics'); }
}
if (! function_exists('sp_shifts_statistics_url')) {
    function sp_shifts_statistics_url(): string { return sp_route_url('shifts.statistics'); }
}

if (! function_exists('sp_schedules_index_path')) {
    function sp_schedules_index_path(): string { return sp_route_path('schedules'); }
}
if (! function_exists('sp_schedules_index_url')) {
    function sp_schedules_index_url(): string { return sp_route_url('schedules'); }
}
if (! function_exists('sp_schedules_create_path')) {
    function sp_schedules_create_path(): string { return sp_route_path('schedules.create'); }
}
if (! function_exists('sp_schedules_create_url')) {
    function sp_schedules_create_url(): string { return sp_route_url('schedules.create'); }
}
if (! function_exists('sp_schedules_store_path')) {
    function sp_schedules_store_path(): string { return sp_route_path('schedules.store'); }
}
if (! function_exists('sp_schedules_store_url')) {
    function sp_schedules_store_url(): string { return sp_route_url('schedules.store'); }
}
if (! function_exists('sp_schedules_edit_path')) {
    function sp_schedules_edit_path(int|string $id): string { return sp_route_path('schedules.edit', $id); }
}
if (! function_exists('sp_schedules_edit_url')) {
    function sp_schedules_edit_url(int|string $id): string { return sp_route_url('schedules.edit', $id); }
}
if (! function_exists('sp_schedules_update_path')) {
    function sp_schedules_update_path(int|string $id): string { return sp_route_path('schedules.update', $id); }
}
if (! function_exists('sp_schedules_update_url')) {
    function sp_schedules_update_url(int|string $id): string { return sp_route_url('schedules.update', $id); }
}
if (! function_exists('sp_schedules_delete_path')) {
    function sp_schedules_delete_path(int|string $id): string { return sp_route_path('schedules.delete', $id); }
}
if (! function_exists('sp_schedules_delete_url')) {
    function sp_schedules_delete_url(int|string $id): string { return sp_route_url('schedules.delete', $id); }
}
if (! function_exists('sp_schedules_bulk_assign_path')) {
    function sp_schedules_bulk_assign_path(): string { return sp_route_path('schedules.bulk-assign'); }
}
if (! function_exists('sp_schedules_bulk_assign_url')) {
    function sp_schedules_bulk_assign_url(): string { return sp_route_url('schedules.bulk-assign'); }
}
if (! function_exists('sp_schedules_bulk_assign_store_path')) {
    function sp_schedules_bulk_assign_store_path(): string { return sp_route_path('schedules.bulk-assign.store'); }
}
if (! function_exists('sp_schedules_bulk_assign_store_url')) {
    function sp_schedules_bulk_assign_store_url(): string { return sp_route_url('schedules.bulk-assign.store'); }
}
if (! function_exists('sp_schedules_export_path')) {
    function sp_schedules_export_path(): string { return sp_route_path('schedules.export'); }
}
if (! function_exists('sp_schedules_export_url')) {
    function sp_schedules_export_url(): string { return sp_route_url('schedules.export'); }
}
if (! function_exists('sp_my_schedules_path')) {
    function sp_my_schedules_path(): string { return sp_route_path('my-schedules'); }
}
if (! function_exists('sp_my_schedules_url')) {
    function sp_my_schedules_url(): string { return sp_route_url('my-schedules'); }
}

if (! function_exists('sp_justifications_index_path')) {
    function sp_justifications_index_path(): string { return sp_route_path('justifications'); }
}
if (! function_exists('sp_justifications_index_url')) {
    function sp_justifications_index_url(): string { return sp_route_url('justifications'); }
}
if (! function_exists('sp_justifications_create_path')) {
    function sp_justifications_create_path(): string { return sp_route_path('justifications.create'); }
}
if (! function_exists('sp_justifications_create_url')) {
    function sp_justifications_create_url(): string { return sp_route_url('justifications.create'); }
}
if (! function_exists('sp_justifications_show_path')) {
    function sp_justifications_show_path(int|string $id): string { return sp_route_path('justifications.show', $id); }
}
if (! function_exists('sp_justifications_show_url')) {
    function sp_justifications_show_url(int|string $id): string { return sp_route_url('justifications.show', $id); }
}
if (! function_exists('sp_justifications_approve_path')) {
    function sp_justifications_approve_path(int|string $id): string { return sp_route_path('justifications.approve', $id); }
}
if (! function_exists('sp_justifications_approve_url')) {
    function sp_justifications_approve_url(int|string $id): string { return sp_route_url('justifications.approve', $id); }
}
if (! function_exists('sp_justifications_reject_path')) {
    function sp_justifications_reject_path(int|string $id): string { return sp_route_path('justifications.reject', $id); }
}
if (! function_exists('sp_justifications_reject_url')) {
    function sp_justifications_reject_url(int|string $id): string { return sp_route_url('justifications.reject', $id); }
}

if (! function_exists('sp_reports_index_path')) {
    function sp_reports_index_path(): string { return sp_route_path('reports'); }
}
if (! function_exists('sp_reports_index_url')) {
    function sp_reports_index_url(): string { return sp_route_url('reports'); }
}
if (! function_exists('sp_reports_timesheet_path')) {
    function sp_reports_timesheet_path(): string { return sp_route_path('reports.timesheet'); }
}
if (! function_exists('sp_reports_timesheet_url')) {
    function sp_reports_timesheet_url(): string { return sp_route_url('reports.timesheet'); }
}
if (! function_exists('sp_reports_attendance_path')) {
    function sp_reports_attendance_path(): string { return sp_route_path('reports.attendance'); }
}
if (! function_exists('sp_reports_attendance_url')) {
    function sp_reports_attendance_url(): string { return sp_route_url('reports.attendance'); }
}
if (! function_exists('sp_reports_justifications_path')) {
    function sp_reports_justifications_path(): string { return sp_route_path('reports.justifications'); }
}
if (! function_exists('sp_reports_justifications_url')) {
    function sp_reports_justifications_url(): string { return sp_route_url('reports.justifications'); }
}
if (! function_exists('sp_reports_late_arrivals_path')) {
    function sp_reports_late_arrivals_path(): string { return sp_route_path('reports.late_arrivals'); }
}
if (! function_exists('sp_reports_late_arrivals_url')) {
    function sp_reports_late_arrivals_url(): string { return sp_route_url('reports.late_arrivals'); }
}
if (! function_exists('sp_reports_generate_path')) {
    function sp_reports_generate_path(): string { return sp_route_path('reports.generate'); }
}
if (! function_exists('sp_reports_generate_url')) {
    function sp_reports_generate_url(): string { return sp_route_url('reports.generate'); }
}
if (! function_exists('sp_reports_afd_path')) {
    function sp_reports_afd_path(): string { return sp_route_path('reports.afd'); }
}
if (! function_exists('sp_reports_afd_url')) {
    function sp_reports_afd_url(): string { return sp_route_url('reports.afd'); }
}
if (! function_exists('sp_reports_download_path')) {
    function sp_reports_download_path(string $jobId): string { return sp_route_path('reports.download', $jobId); }
}
if (! function_exists('sp_reports_download_url')) {
    function sp_reports_download_url(string $jobId): string { return sp_route_url('reports.download', $jobId); }
}
if (! function_exists('sp_reports_status_path')) {
    function sp_reports_status_path(string $jobId): string { return sp_route_path('reports.status', $jobId); }
}
if (! function_exists('sp_reports_status_url')) {
    function sp_reports_status_url(string $jobId): string { return sp_route_url('reports.status', $jobId); }
}

if (! function_exists('sp_async_job_status_path')) {
    function sp_async_job_status_path(string $jobId): string { return sp_route_path('jobs.status', $jobId); }
}
if (! function_exists('sp_async_job_status_url')) {
    function sp_async_job_status_url(string $jobId): string { return sp_route_url('jobs.status', $jobId); }
}
if (! function_exists('sp_async_job_download_path')) {
    function sp_async_job_download_path(string $jobId): string { return sp_route_path('jobs.download', $jobId); }
}
if (! function_exists('sp_async_job_download_url')) {
    function sp_async_job_download_url(string $jobId): string { return sp_route_url('jobs.download', $jobId); }
}
if (! function_exists('sp_api_async_job_status_path')) {
    function sp_api_async_job_status_path(string $jobId): string { return sp_route_path('api.jobs.status', $jobId); }
}
if (! function_exists('sp_api_async_job_status_url')) {
    function sp_api_async_job_status_url(string $jobId): string { return sp_route_url('api.jobs.status', $jobId); }
}
if (! function_exists('sp_api_async_job_download_path')) {
    function sp_api_async_job_download_path(string $jobId): string { return sp_route_path('api.jobs.download', $jobId); }
}
if (! function_exists('sp_api_async_job_download_url')) {
    function sp_api_async_job_download_url(string $jobId): string { return sp_route_url('api.jobs.download', $jobId); }
}


if (! function_exists('sp_api_reports_status_path')) {
    function sp_api_reports_status_path(string $jobId): string { return sp_route_path('api.reports.status', $jobId); }
}
if (! function_exists('sp_api_reports_status_url')) {
    function sp_api_reports_status_url(string $jobId): string { return sp_route_url('api.reports.status', $jobId); }
}
if (! function_exists('sp_api_reports_download_path')) {
    function sp_api_reports_download_path(string $jobId): string { return sp_route_path('api.reports.download', $jobId); }
}
if (! function_exists('sp_api_reports_download_url')) {
    function sp_api_reports_download_url(string $jobId): string { return sp_route_url('api.reports.download', $jobId); }
}

if (! function_exists('sp_warning_index_path')) {
    function sp_warning_index_path(): string { return sp_route_path('warnings'); }
}
if (! function_exists('sp_warning_index_url')) {
    function sp_warning_index_url(): string { return sp_route_url('warnings'); }
}
if (! function_exists('sp_warning_create_path')) {
    function sp_warning_create_path(): string { return sp_route_path('warnings.create'); }
}
if (! function_exists('sp_warning_create_url')) {
    function sp_warning_create_url(): string { return sp_route_url('warnings.create'); }
}
if (! function_exists('sp_warning_store_path')) {
    function sp_warning_store_path(): string { return sp_route_path('warnings.store'); }
}
if (! function_exists('sp_warning_store_url')) {
    function sp_warning_store_url(): string { return sp_route_url('warnings.store'); }
}
if (! function_exists('sp_warning_sign_path')) {
    function sp_warning_sign_path(int $warningId): string { return sp_route_path('warnings.sign.form', $warningId); }
}
if (! function_exists('sp_warning_sign_url')) {
    function sp_warning_sign_url(int $warningId): string { return sp_route_url('warnings.sign.form', $warningId); }
}
if (! function_exists('sp_warning_sign_submit_path')) {
    function sp_warning_sign_submit_path(int $warningId): string { return sp_route_path('warnings.sign', $warningId); }
}
if (! function_exists('sp_warning_sign_submit_url')) {
    function sp_warning_sign_submit_url(int $warningId): string { return sp_route_url('warnings.sign', $warningId); }
}
if (! function_exists('sp_warning_sign_sms_path')) {
    function sp_warning_sign_sms_path(int $warningId): string { return sp_route_path('warnings.sign.sms', $warningId); }
}
if (! function_exists('sp_warning_sign_sms_url')) {
    function sp_warning_sign_sms_url(int $warningId): string { return sp_route_url('warnings.sign.sms', $warningId); }
}
if (! function_exists('sp_warning_show_path')) {
    function sp_warning_show_path(int $warningId): string { return sp_route_path('warnings.show', $warningId); }
}
if (! function_exists('sp_warning_show_url')) {
    function sp_warning_show_url(int $warningId): string { return sp_route_url('warnings.show', $warningId); }
}
if (! function_exists('sp_warning_download_path')) {
    function sp_warning_download_path(int $warningId): string { return sp_route_path('warnings.download', $warningId); }
}
if (! function_exists('sp_warning_download_url')) {
    function sp_warning_download_url(int $warningId): string { return sp_route_url('warnings.download', $warningId); }
}
if (! function_exists('sp_warning_dashboard_path')) {
    function sp_warning_dashboard_path(int $employeeId): string { return sp_route_path('warnings.dashboard', $employeeId); }
}
if (! function_exists('sp_warning_dashboard_url')) {
    function sp_warning_dashboard_url(int $employeeId): string { return sp_route_url('warnings.dashboard', $employeeId); }
}

if (! function_exists('sp_warning_witness_form_path')) {
    function sp_warning_witness_form_path(int $warningId): string { return sp_route_path('warnings.witness.form', $warningId); }
}
if (! function_exists('sp_warning_witness_form_url')) {
    function sp_warning_witness_form_url(int $warningId): string { return sp_route_url('warnings.witness.form', $warningId); }
}
if (! function_exists('sp_warning_witness_store_path')) {
    function sp_warning_witness_store_path(int $warningId): string { return sp_route_path('warnings.witness.store', $warningId); }
}
if (! function_exists('sp_warning_witness_store_url')) {
    function sp_warning_witness_store_url(int $warningId): string { return sp_route_url('warnings.witness.store', $warningId); }
}
if (! function_exists('sp_warning_refuse_signature_path')) {
    function sp_warning_refuse_signature_path(int $warningId): string { return sp_route_path('warnings.refuse.signature', $warningId); }
}
if (! function_exists('sp_warning_refuse_signature_url')) {
    function sp_warning_refuse_signature_url(int $warningId): string { return sp_route_url('warnings.refuse.signature', $warningId); }
}

if (! function_exists('sp_timesheet_index_path')) {
    function sp_timesheet_index_path(): string { return sp_route_path('timesheet.index'); }
}
if (! function_exists('sp_timesheet_index_url')) {
    function sp_timesheet_index_url(): string { return sp_route_url('timesheet.index'); }
}
if (! function_exists('sp_timesheet_punch_path')) {
    function sp_timesheet_punch_path(): string { return sp_route_path('timesheet.punch'); }
}
if (! function_exists('sp_timesheet_punch_url')) {
    function sp_timesheet_punch_url(): string { return sp_route_url('timesheet.punch'); }
}
if (! function_exists('sp_timesheet_punch_kiosk_path')) {
    function sp_timesheet_punch_kiosk_path(): string { return sp_route_path('timesheet.punch.kiosk'); }
}
if (! function_exists('sp_timesheet_punch_kiosk_url')) {
    function sp_timesheet_punch_kiosk_url(): string { return sp_route_url('timesheet.punch.kiosk'); }
}

if (! function_exists('sp_punch_terminal_code_path')) {
    function sp_punch_terminal_code_path(): string { return sp_route_path('punch.terminal.code'); }
}
if (! function_exists('sp_punch_terminal_code_url')) {
    function sp_punch_terminal_code_url(): string { return sp_route_url('punch.terminal.code'); }
}
if (! function_exists('sp_punch_terminal_cpf_path')) {
    function sp_punch_terminal_cpf_path(): string { return sp_route_path('punch.terminal.cpf'); }
}
if (! function_exists('sp_punch_terminal_cpf_url')) {
    function sp_punch_terminal_cpf_url(): string { return sp_route_url('punch.terminal.cpf'); }
}
if (! function_exists('sp_timesheet_punch_qr_path')) {
    function sp_timesheet_punch_qr_path(): string { return sp_route_path('timesheet.punch.qr'); }
}
if (! function_exists('sp_timesheet_punch_qr_url')) {
    function sp_timesheet_punch_qr_url(): string { return sp_route_url('timesheet.punch.qr'); }
}
if (! function_exists('sp_punch_terminal_face_path')) {
    function sp_punch_terminal_face_path(): string { return sp_route_path('timesheet.punch.face.kiosk'); }
}
if (! function_exists('sp_punch_terminal_face_url')) {
    function sp_punch_terminal_face_url(): string { return sp_route_url('timesheet.punch.face.kiosk'); }
}
if (! function_exists('sp_punch_terminal_fingerprint_path')) {
    function sp_punch_terminal_fingerprint_path(): string { return sp_route_path('punch.terminal.fingerprint'); }
}
if (! function_exists('sp_punch_terminal_fingerprint_url')) {
    function sp_punch_terminal_fingerprint_url(): string { return sp_route_url('punch.terminal.fingerprint'); }
}

if (! function_exists('sp_timesheet_history_path')) {
    function sp_timesheet_history_path(): string { return sp_route_path('timesheet.history'); }
}
if (! function_exists('sp_timesheet_history_url')) {
    function sp_timesheet_history_url(): string { return sp_route_url('timesheet.history'); }
}
if (! function_exists('sp_timesheet_balance_path')) {
    function sp_timesheet_balance_path(): string { return sp_route_path('timesheet.balance'); }
}
if (! function_exists('sp_timesheet_balance_url')) {
    function sp_timesheet_balance_url(): string { return sp_route_url('timesheet.balance'); }
}
if (! function_exists('sp_timesheet_receipt_path')) {
    function sp_timesheet_receipt_path(int $punchId): string { return sp_route_path('timesheet.receipt', $punchId); }
}
if (! function_exists('sp_timesheet_receipt_url')) {
    function sp_timesheet_receipt_url(int $punchId): string { return sp_route_url('timesheet.receipt', $punchId); }
}
if (! function_exists('sp_timesheet_employee_path')) {
    function sp_timesheet_employee_path(int $employeeId): string { return sp_route_path('timesheet.employee', $employeeId); }
}
if (! function_exists('sp_timesheet_employee_url')) {
    function sp_timesheet_employee_url(int $employeeId): string { return sp_route_url('timesheet.employee', $employeeId); }
}
if (! function_exists('sp_timesheet_day_path')) {
    function sp_timesheet_day_path(string $day): string { return '/timesheet/day/' . rawurlencode($day); }
}
if (! function_exists('sp_timesheet_day_url')) {
    function sp_timesheet_day_url(string $day): string { return site_url(ltrim(sp_timesheet_day_path($day), '/')); }
}
if (! function_exists('sp_timesheet_export_excel_url')) {
    function sp_timesheet_export_excel_url(?string $month = null): string {
        $path = sp_route_path('timesheet.export.excel');
        return site_url(ltrim($path, '/')) . ($month ? ('?month=' . rawurlencode($month)) : '');
    }
}
if (! function_exists('sp_timesheet_export_pdf_url')) {
    function sp_timesheet_export_pdf_url(?string $month = null): string {
        $path = sp_route_path('timesheet.export.pdf');
        return site_url(ltrim($path, '/')) . ($month ? ('?month=' . rawurlencode($month)) : '');
    }
}
if (! function_exists('sp_timesheet_justify_path')) {
    function sp_timesheet_justify_path(): string { return sp_route_path('timesheet.punch.justify'); }
}
if (! function_exists('sp_timesheet_justify_url')) {
    function sp_timesheet_justify_url(): string { return sp_route_url('timesheet.punch.justify'); }
}
if (! function_exists('sp_timesheet_justify_submit_path')) {
    function sp_timesheet_justify_submit_path(): string { return sp_route_path('timesheet.punch.justify.submit'); }
}
if (! function_exists('sp_timesheet_justify_submit_url')) {
    function sp_timesheet_justify_submit_url(): string { return sp_route_url('timesheet.punch.justify.submit'); }
}
if (! function_exists('sp_profile_security_path')) {
    function sp_profile_security_path(): string { return sp_route_path('profile.security'); }
}
if (! function_exists('sp_profile_security_url')) {
    function sp_profile_security_url(): string { return sp_route_url('profile.security'); }
}
if (! function_exists('sp_profile_biometric_path')) {
    function sp_profile_biometric_path(): string { return sp_route_path('profile.biometric'); }
}
if (! function_exists('sp_profile_biometric_url')) {
    function sp_profile_biometric_url(): string { return sp_route_url('profile.biometric'); }
}

if (! function_exists('sp_settings_center_path')) {
    function sp_settings_center_path(): string { return sp_route_path('settings'); }
}
if (! function_exists('sp_settings_center_url')) {
    function sp_settings_center_url(): string { return sp_route_url('admin.settings'); }
}
if (! function_exists('sp_admin_settings_index_path')) {
    function sp_admin_settings_index_path(): string { return sp_route_path('admin.settings'); }
}
if (! function_exists('sp_admin_settings_index_url')) {
    function sp_admin_settings_index_url(): string { return sp_route_url('admin.settings'); }
}
if (! function_exists('sp_admin_settings_appearance_path')) {
    function sp_admin_settings_appearance_path(): string { return sp_route_path('admin.settings.appearance'); }
}
if (! function_exists('sp_admin_settings_appearance_url')) {
    function sp_admin_settings_appearance_url(): string { return sp_route_url('admin.settings.appearance'); }
}
if (! function_exists('sp_admin_settings_authentication_path')) {
    function sp_admin_settings_authentication_path(): string { return sp_route_path('admin.settings.authentication'); }
}
if (! function_exists('sp_admin_settings_authentication_url')) {
    function sp_admin_settings_authentication_url(): string { return sp_route_url('admin.settings.authentication'); }
}
if (! function_exists('sp_admin_settings_system_path')) {
    function sp_admin_settings_system_path(): string { return sp_route_path('admin.settings.system'); }
}
if (! function_exists('sp_admin_settings_system_url')) {
    function sp_admin_settings_system_url(): string { return sp_route_url('admin.settings.system'); }
}
if (! function_exists('sp_admin_settings_security_path')) {
    function sp_admin_settings_security_path(): string { return sp_route_path('admin.settings.security'); }
}
if (! function_exists('sp_admin_settings_security_url')) {
    function sp_admin_settings_security_url(): string { return sp_route_url('admin.settings.security'); }
}
if (! function_exists('sp_settings_geofences_path')) {
    function sp_settings_geofences_path(): string { return sp_route_path('settings.geofences'); }
}
if (! function_exists('sp_settings_geofences_url')) {
    function sp_settings_geofences_url(): string { return sp_route_url('settings.geofences'); }
}
if (! function_exists('sp_geofences_index_path')) {
    function sp_geofences_index_path(): string { return sp_route_path('geofences'); }
}
if (! function_exists('sp_geofences_index_url')) {
    function sp_geofences_index_url(): string { return sp_route_url('geofences'); }
}
if (! function_exists('sp_geofences_create_path')) {
    function sp_geofences_create_path(): string { return sp_route_path('geofences.create'); }
}
if (! function_exists('sp_geofences_create_url')) {
    function sp_geofences_create_url(): string { return sp_route_url('geofences.create'); }
}
if (! function_exists('sp_geofences_map_path')) {
    function sp_geofences_map_path(): string { return sp_route_path('geofences.map'); }
}
if (! function_exists('sp_geofences_map_url')) {
    function sp_geofences_map_url(): string { return sp_route_url('geofences.map'); }
}
if (! function_exists('sp_geofences_show_path')) {
    function sp_geofences_show_path(int $id): string { return sp_route_path('geofences.show', $id); }
}
if (! function_exists('sp_geofences_show_url')) {
    function sp_geofences_show_url(int $id): string { return sp_route_url('geofences.show', $id); }
}
if (! function_exists('sp_geofences_edit_path')) {
    function sp_geofences_edit_path(int $id): string { return sp_route_path('geofences.edit', $id); }
}
if (! function_exists('sp_geofences_edit_url')) {
    function sp_geofences_edit_url(int $id): string { return sp_route_url('geofences.edit', $id); }
}
if (! function_exists('sp_geofences_store_path')) {
    function sp_geofences_store_path(): string { return sp_route_path('geofences.store'); }
}
if (! function_exists('sp_geofences_store_url')) {
    function sp_geofences_store_url(): string { return sp_route_url('geofences.store'); }
}
if (! function_exists('sp_geofences_update_path')) {
    function sp_geofences_update_path(int $id): string { return sp_route_path('geofences.update', $id); }
}
if (! function_exists('sp_geofences_update_url')) {
    function sp_geofences_update_url(int $id): string { return sp_route_url('geofences.update', $id); }
}
if (! function_exists('sp_geofences_toggle_path')) {
    function sp_geofences_toggle_path(int $id): string { return sp_route_path('geofences.toggle', $id); }
}
if (! function_exists('sp_geofences_toggle_url')) {
    function sp_geofences_toggle_url(int $id): string { return sp_route_url('geofences.toggle', $id); }
}
if (! function_exists('sp_geofences_delete_path')) {
    function sp_geofences_delete_path(int $id): string { return sp_route_path('geofences.delete', $id); }
}
if (! function_exists('sp_geofences_delete_url')) {
    function sp_geofences_delete_url(int $id): string { return sp_route_url('geofences.delete', $id); }
}
if (! function_exists('sp_geofences_json_path')) {
    function sp_geofences_json_path(): string { return sp_route_path('geofences.json'); }
}
if (! function_exists('sp_geofences_json_url')) {
    function sp_geofences_json_url(): string { return sp_route_url('geofences.json'); }
}
if (! function_exists('sp_geofences_test_path')) {
    function sp_geofences_test_path(): string { return sp_route_path('geofences.test'); }
}
if (! function_exists('sp_geofences_test_url')) {
    function sp_geofences_test_url(): string { return sp_route_url('geofences.test'); }
}


if (! function_exists('sp_admin_settings_information_path')) {
    function sp_admin_settings_information_path(): string { return sp_route_path('admin.settings.information'); }
}
if (! function_exists('sp_admin_settings_information_url')) {
    function sp_admin_settings_information_url(): string { return sp_route_url('admin.settings.information'); }
}
if (! function_exists('sp_admin_settings_personalization_path')) {
    function sp_admin_settings_personalization_path(): string { return sp_route_path('admin.settings.personalization'); }
}
if (! function_exists('sp_admin_settings_personalization_url')) {
    function sp_admin_settings_personalization_url(): string { return sp_route_url('admin.settings.personalization'); }
}
if (! function_exists('sp_admin_settings_email_path')) {
    function sp_admin_settings_email_path(): string { return sp_route_path('admin.settings.email'); }
}
if (! function_exists('sp_admin_settings_email_url')) {
    function sp_admin_settings_email_url(): string { return sp_route_url('admin.settings.email'); }
}
if (! function_exists('sp_admin_settings_integrations_path')) {
    function sp_admin_settings_integrations_path(): string { return sp_route_path('admin.settings.integrations'); }
}
if (! function_exists('sp_admin_settings_integrations_url')) {
    function sp_admin_settings_integrations_url(): string { return sp_route_url('admin.settings.integrations'); }
}
if (! function_exists('sp_admin_settings_backup_path')) {
    function sp_admin_settings_backup_path(): string { return sp_route_path('admin.settings.backup'); }
}
if (! function_exists('sp_admin_settings_backup_url')) {
    function sp_admin_settings_backup_url(): string { return sp_route_url('admin.settings.backup'); }
}
if (! function_exists('sp_admin_settings_pwa_path')) {
    function sp_admin_settings_pwa_path(): string { return sp_route_path('admin.settings.pwa'); }
}
if (! function_exists('sp_admin_settings_pwa_url')) {
    function sp_admin_settings_pwa_url(): string { return sp_route_url('admin.settings.pwa'); }
}
