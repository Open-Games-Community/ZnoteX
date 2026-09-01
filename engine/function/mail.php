<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    protected array $_config;

    public function __construct(array $config) {
        $this->_config = $config;
    }

    public function sendMail(string $to, string $title, string $html, string $accname = ''): bool
    {
        // Charger PHPMailer UNE FOIS (idéalement via autoload)
        require_once __DIR__.'/../../PHPMailer/src/Exception.php';
        require_once __DIR__.'/../../PHPMailer/src/PHPMailer.php';
        require_once __DIR__.'/../../PHPMailer/src/SMTP.php';

        try {
            $mail = new PHPMailer(true);

            // SMTP
            $mail->isSMTP();
            $mail->SMTPDebug  = !empty($this->_config['debug']) ? 2 : 0;
            $mail->Host       = $this->_config['host'];
            $mail->Port       = (int)$this->_config['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->_config['username'];
            $mail->Password   = $this->_config['password'];
            $mail->CharSet    = 'UTF-8';

            // Security
            $secure = $this->_config['securityType'] ?? '';
            if (in_array($secure, ['ssl', 'tls'], true)) {
                $mail->SMTPSecure = $secure;
            }

            // Sender / receiver
            $mail->setFrom($this->_config['email'], $this->_config['fromName']);
            $mail->addAddress($to, $accname);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $title;
            $mail->Body    = $html;

            // AltBody
            $plain = str_replace(['<br>', '<br/>', '<br />'], "\n", $html);
            $plain = strip_tags($plain);
            $mail->AltBody = $plain;

            return $mail->send();

        } catch (Exception $e) {
            // Log plutôt qu'echo
            error_log('Mail error: ' . $e->getMessage());
            return false;
        }
    }
}