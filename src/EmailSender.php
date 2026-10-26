<?php
declare(strict_types=1);

namespace Lab;

use PHPMailer\PHPMailer\PHPMailer;

final class EmailSender
{
    public function __construct(
        private array $smtpSettings
    ) {}

    public function send(array $customer, array $job): string
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = $this->smtpSettings['host'];
        $mail->Port = (int) $this->smtpSettings['port'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->smtpSettings['username'];
        $mail->Password = $this->smtpSettings['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->setFrom(
            $this->smtpSettings['from_address'],
            'Marketing Laboratory'
        );

        // TODO E1: Recipient from the local database
        $mail->addAddress(
            $customer['email'],
            $customer['full_name']
        );

        // TODO E2: Campaign subject and personalized body
        $mail->Subject = $job['subject'];
        $mail->isHTML(false);

        $mail->Body =
            "Hello {$customer['full_name']},\n\n" .
            $job['body'] . "\n\n" .
            "Your promotional code is: {$job['promoCode']}\n\n" .
            "Thank you!";

        // Let SMTP exceptions propagate to the processor.
        $mail->send();

        return $mail->getLastMessageID();
    }
}
