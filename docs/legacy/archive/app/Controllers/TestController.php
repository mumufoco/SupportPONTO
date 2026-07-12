<?php

namespace App\Controllers;

class TestController extends \CodeIgniter\Controller
{
    /**
     * Simple test - no dependencies, no session, no auth
     */
    public function index()
    {
        return "✅ SUCESSO! Controller funcionando!<br><br>" .
               "Se você está vendo isso, o CodeIgniter está funcionando corretamente.<br>" .
               "O problema está em outro controller ou configuração.";
    }

    /**
     * Test with view
     */
    public function view()
    {
        echo "<!DOCTYPE html><html><head><title>Test</title></head><body>";
        echo "<h1>✅ Test Controller</h1>";
        echo "<p>CodeIgniter está funcionando!</p>";
        echo "<p>Hora atual: " . date('Y-m-d H:i:s') . "</p>";
        echo "</body></html>";
    }
}
