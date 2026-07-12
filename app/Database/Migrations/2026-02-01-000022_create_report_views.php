<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Create Views for Frequent Reports
 *
 * PostgreSQL is the official production database for this project. SQLite is skipped in
 * automated tests because the report views rely on PostgreSQL functions.
 */
class CreateReportViews extends Migration
{
    public function up()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            log_message('warning', 'Skipping report views creation for SQLite (database-specific syntax)');
            return;
        }

        if ($this->db->DBDriver !== 'Postgre') {
            throw new \RuntimeException('This migration supports PostgreSQL only.');
        }

        $this->createPostgreSQLViews();
        log_message('info', 'Report views created successfully (PostgreSQL)');
    }

    private function createPostgreSQLViews(): void
    {
        $this->db->query("
            CREATE OR REPLACE VIEW v_monthly_timesheet AS
            SELECT
                e.id AS employee_id,
                e.name AS employee_name,
                e.department,
                e.position,
                TO_CHAR(tp.punch_time, 'YYYY-MM') AS month,
                COUNT(DISTINCT DATE(tp.punch_time)) AS days_worked,
                SUM(CASE WHEN tp.punch_type = 'entrada' THEN 1 ELSE 0 END) AS entrance_count,
                MIN(CASE WHEN tp.punch_type = 'entrada' THEN tp.punch_time ELSE NULL END) AS first_entrance,
                MAX(CASE WHEN tp.punch_type = 'saida' THEN tp.punch_time ELSE NULL END) AS last_exit,
                AVG(tp.location_accuracy) AS avg_location_accuracy,
                SUM(CASE WHEN tp.within_geofence = false THEN 1 ELSE 0 END) AS punches_outside_geofence
            FROM employees e
            LEFT JOIN time_punches tp ON e.id = tp.employee_id
            WHERE e.active = true
            GROUP BY e.id, e.name, e.department, e.position, TO_CHAR(tp.punch_time, 'YYYY-MM')
        ");

        $this->db->query("
            CREATE OR REPLACE VIEW v_daily_attendance AS
            SELECT
                e.id AS employee_id,
                e.name AS employee_name,
                e.department,
                e.work_schedule_start AS expected_start,
                CURRENT_DATE AS attendance_date,
                MIN(CASE WHEN tp.punch_type = 'entrada' AND DATE(tp.punch_time) = CURRENT_DATE THEN tp.punch_time ELSE NULL END) AS actual_entrance,
                MAX(CASE WHEN tp.punch_type = 'saida' AND DATE(tp.punch_time) = CURRENT_DATE THEN tp.punch_time ELSE NULL END) AS actual_exit,
                CASE
                    WHEN MIN(CASE WHEN tp.punch_type = 'entrada' AND DATE(tp.punch_time) = CURRENT_DATE THEN tp.punch_time ELSE NULL END) IS NULL THEN 'absent'
                    WHEN (MIN(CASE WHEN tp.punch_type = 'entrada' AND DATE(tp.punch_time) = CURRENT_DATE THEN tp.punch_time ELSE NULL END)::TIME > (e.work_schedule_start::TIME + INTERVAL '10 minutes')) THEN 'late'
                    ELSE 'on_time'
                END AS status
            FROM employees e
            LEFT JOIN time_punches tp ON e.id = tp.employee_id
            WHERE e.active = true
            GROUP BY e.id, e.name, e.department, e.work_schedule_start
        ");

        $this->db->query("
            CREATE OR REPLACE VIEW v_employee_performance AS
            SELECT
                e.id AS employee_id,
                e.name AS employee_name,
                e.department,
                e.role,
                e.created_at AS hire_date,
                COUNT(DISTINCT DATE(tp.punch_time)) AS total_days_worked,
                COUNT(tp.id) AS total_punches,
                SUM(CASE WHEN tp.within_geofence = false THEN 1 ELSE 0 END) AS out_of_geofence_count,
                COUNT(DISTINCT w.id) AS warning_count,
                COUNT(DISTINCT j.id) AS justification_count,
                SUM(CASE WHEN j.status = 'approved' THEN 1 ELSE 0 END) AS approved_justifications,
                e.extra_hours_balance,
                e.owed_hours_balance
            FROM employees e
            LEFT JOIN time_punches tp ON e.id = tp.employee_id
            LEFT JOIN warnings w ON e.id = w.employee_id
            LEFT JOIN justifications j ON e.id = j.employee_id
            WHERE e.active = true
            GROUP BY e.id, e.name, e.department, e.role, e.created_at, e.extra_hours_balance, e.owed_hours_balance
        ");

        $this->db->query("
            CREATE OR REPLACE VIEW v_biometric_status AS
            SELECT
                e.id AS employee_id,
                e.name AS employee_name,
                e.department,
                MAX(CASE WHEN bt.biometric_type = 'face' AND bt.active = true THEN 1 ELSE 0 END) AS has_facial,
                MAX(CASE WHEN bt.biometric_type = 'fingerprint' AND bt.active = true THEN 1 ELSE 0 END) AS has_fingerprint,
                COUNT(bt.id) AS total_templates,
                MAX(bt.created_at) AS last_enrollment_date
            FROM employees e
            LEFT JOIN biometric_templates bt ON e.id = bt.employee_id
            WHERE e.active = true
            GROUP BY e.id, e.name, e.department
        ");
    }

    public function down()
    {
        if ($this->db->DBDriver === 'SQLite3') {
            return;
        }

        foreach ([
            'v_monthly_timesheet',
            'v_daily_attendance',
            'v_employee_performance',
            'v_biometric_status',
        ] as $view) {
            $this->db->query("DROP VIEW IF EXISTS {$view}");
        }
    }
}
