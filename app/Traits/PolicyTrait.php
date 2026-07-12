<?php

namespace App\Traits;

use App\Exceptions\ForbiddenException;

/**
 * MELHORIA 9: Trait para uso de Policy Objects nos controllers.
 *
 * Fornece o método policy() para instanciar e usar policies de forma
 * fluente, similar ao Laravel Gate.
 *
 * Uso no controller:
 *
 *   // Verificar permissão (lança ForbiddenException automaticamente)
 *   $this->authorize(new JustificationPolicy(), 'approve', $justification);
 *
 *   // Verificar sem lançar exceção
 *   $canApprove = $this->can(new JustificationPolicy(), 'approve', $justification);
 */
trait PolicyTrait
{
    /**
     * Verifica permissão e lança ForbiddenException se negado.
     *
     * @param  object $policy     Instância da policy
     * @param  string $ability    Método da policy ('view', 'approve' etc.)
     * @param  mixed  ...$args    Argumentos adicionais (ex: o recurso alvo)
     * @throws ForbiddenException
     */
    protected function authorize(object $policy, string $ability, mixed ...$args): void
    {
        $actor = $this->currentUser ?? null;

        if ($actor === null) {
            throw new \App\Exceptions\UnauthorizedException();
        }

        if (!method_exists($policy, $ability)) {
            throw new \LogicException("Policy " . get_class($policy) . " não tem método {$ability}().");
        }

        $allowed = $policy->$ability($actor, ...$args);

        if (!$allowed) {
            throw new ForbiddenException($ability);
        }
    }

    /**
     * Verifica permissão sem lançar exceção.
     * Retorna true se permitido, false se negado.
     */
    protected function can(object $policy, string $ability, mixed ...$args): bool
    {
        $actor = $this->currentUser ?? null;

        if ($actor === null || !method_exists($policy, $ability)) {
            return false;
        }

        return (bool) $policy->$ability($actor, ...$args);
    }
}
