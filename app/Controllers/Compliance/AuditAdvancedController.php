<?php
namespace App\Controllers\Compliance;
use App\Controllers\BaseController;

class AuditAdvancedController extends BaseController
{
    public function index()
    {
        return redirect()->to(site_url('audit'));
    }
}
