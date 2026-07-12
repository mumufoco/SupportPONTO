<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Traits\ControllerAuthorizationTrait;
use App\Traits\ControllerResponseSecurityTrait;
use App\Traits\ControllerSessionContextTrait;
use App\Traits\ObservabilityTrait;
use App\Traits\PolicyTrait;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * BaseController leve para inicialização comum e capacidades compartilhadas.
 */
abstract class BaseController extends Controller
{
    use ObservabilityTrait;
    use ControllerSessionContextTrait;
    use ControllerResponseSecurityTrait;
    use ControllerAuthorizationTrait, PolicyTrait {
        ControllerAuthorizationTrait::can insteadof PolicyTrait;
        PolicyTrait::can as protected policyCan;
    } // MELHORIA 9: authorize() e policyCan() para Policy Objects

    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * @var list<string>
     */
    protected $helpers = ['form', 'url', 'text', 'date', 'observability', 'asset', 'session_context', 'navigation_context', 'operational_link', 'csv_download', 'csp_nonce'];

    protected mixed $session = null;

    protected mixed $currentUser = null;

    protected AuthorizationService $authorizationService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->attachResponseContext($response);

        $this->initializeSession();
        $this->authorizationService = new AuthorizationService();
        $this->loadCurrentUser();
    }
}
