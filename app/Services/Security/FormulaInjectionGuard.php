<?php

namespace App\Services\Security;

/**
 * Neutraliza injeção de fórmula (CSV/Excel Formula Injection — ALTO-07 na
 * auditoria) em exportações CSV/Excel.
 *
 * Qualquer célula cujo conteúdo comece com =, +, -, @ ou tab/CR é avaliada como
 * fórmula pelo Excel/LibreOffice ao abrir o arquivo. Um campo de texto livre
 * preenchido por qualquer funcionário (ex.: motivo de justificativa, observações de
 * advertência) podia carregar algo como =HYPERLINK("http://atacante.com","clique") e
 * ser executado quando RH/Admin exportasse o relatório. Prefixar com um apóstrofo é a
 * mitigação padrão recomendada pela OWASP — o Excel exibe o conteúdo como texto
 * literal em vez de avaliá-lo como fórmula.
 */
class FormulaInjectionGuard
{
    private const TRIGGER_CHARS = ['=', '+', '-', '@', "\t", "\r"];

    public static function neutralize(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::TRIGGER_CHARS, true) ? "'" . $value : $value;
    }

    /**
     * Aplica neutralize() em cada elemento de uma linha/registro, preservando as
     * chaves (útil para arrays associativos vindos direto do banco).
     */
    public static function neutralizeRow(array $row): array
    {
        return array_map(self::neutralize(...), $row);
    }
}
