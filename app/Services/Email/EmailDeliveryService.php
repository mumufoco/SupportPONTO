<?php

namespace App\Services\Email;

use CodeIgniter\Email\Email;
use Throwable;

class EmailDeliveryService
{
    public function __construct(private readonly Email $email)
    {
    }

    /**
     * @param string|array $to
     */
    public function send(string|array $to, string $subject, string $message, array $options, string $fromEmail, string $fromName): array
    {
        try {
            $this->email->setFrom($fromEmail, $fromName);
            $this->email->setTo($to);
            $this->email->setSubject($subject);
            $this->email->setMessage($message);

            if (isset($options['cc'])) {
                $this->email->setCC($options['cc']);
            }
            if (isset($options['bcc'])) {
                $this->email->setBCC($options['bcc']);
            }
            if (isset($options['reply_to'])) {
                $this->email->setReplyTo($options['reply_to']);
            }
            if (isset($options['attachments']) && is_array($options['attachments'])) {
                foreach ($options['attachments'] as $attachment) {
                    $this->email->attach($attachment);
                }
            }

            $sent = $this->email->send();
            $details = $this->email->printDebugger(['headers']);
            $this->email->clear();

            // CI4\Email::send() as vezes retorna false via SMTP mesmo quando o
            // servidor ja confirmou a entrega -- o transcript do handshake
            // mostra o Postfix respondendo "250 ... Ok: queued as <id>" (sucesso
            // real, mensagem enfileirada), mas o parser interno da lib nao
            // reconhece essa resposta especifica e marca como falha. Sem esta
            // checagem, e-mails efetivamente entregues ficavam registrados como
            // falha (auditoria enganosa) e geravam ERROR constante nos logs a
            // cada envio, mesmo com a entrega funcionando.
            if (! $sent && preg_match('/\bqueued as\b/i', $details) === 1) {
                $sent = true;
            }

            return [
                'sent' => $sent,
                'details' => $details,
                'debug' => ENVIRONMENT === 'production' ? null : $this->email->printDebugger(),
            ];
        } catch (Throwable $exception) {
            $this->email->clear();

            return [
                'sent' => false,
                'details' => $exception->getMessage(),
                'debug' => ENVIRONMENT === 'production' ? null : $exception->getTraceAsString(),
            ];
        }
    }
}
