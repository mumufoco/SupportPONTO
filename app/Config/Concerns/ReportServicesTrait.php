<?php

namespace Config\Concerns;

use App\Services\CSVService;
use App\Services\ExcelService;
use App\Services\PDFService;
use App\Services\Reports\ReportExecutionService;
use App\Services\ReportService;
use App\Services\TXTService;
use App\Services\TimesheetService;
use App\Services\XMLService;

trait ReportServicesTrait
{
    public static function timesheetService(bool $getShared = true): TimesheetService
    {
        if ($getShared) {
            return static::getSharedInstance('timesheetService');
        }

        return new TimesheetService();
    }
    public static function pdfService(bool $getShared = true): PDFService
    {
        if ($getShared) {
            return static::getSharedInstance('pdfService');
        }

        return new PDFService();
    }
    public static function excelService(bool $getShared = true): ExcelService
    {
        if ($getShared) {
            return static::getSharedInstance('excelService');
        }

        return new ExcelService();
    }
    public static function csvService(bool $getShared = true): CSVService
    {
        if ($getShared) {
            return static::getSharedInstance('csvService');
        }

        return new CSVService();
    }
    public static function xmlService(bool $getShared = true): XMLService
    {
        if ($getShared) {
            return static::getSharedInstance('xmlService');
        }

        return new XMLService();
    }
    public static function txtService(bool $getShared = true): TXTService
    {
        if ($getShared) {
            return static::getSharedInstance('txtService');
        }

        return new TXTService();
    }
    public static function reportService(bool $getShared = true): ReportService
    {
        if ($getShared) {
            return static::getSharedInstance('reportService');
        }

        return new ReportService(static::reportExecutionService(false));
    }

    public static function reportExecutionService(bool $getShared = true): ReportExecutionService
    {
        if ($getShared) {
            return static::getSharedInstance('reportExecutionService');
        }

        return new ReportExecutionService(
            static::employeeModel(),
            static::timePunchModel(),
            static::justificationModel(),
            static::timesheetConsolidatedModel(),
            static::timesheetService(),
            static::pdfService(),
            static::excelService(),
            static::csvService(),
            static::xmlService(),
            static::txtService()
        );
    }

}
