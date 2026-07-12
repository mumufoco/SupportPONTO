<?php

namespace App\Models;

/**
 * Compatibilidade para serviços LGPD legados que ainda referenciam
 * BiometricRecordModel. O modelo canônico do domínio permanece sendo
 * BiometricTemplateModel.
 */
class BiometricRecordModel extends BiometricTemplateModel
{
}
