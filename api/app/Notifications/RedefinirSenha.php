<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** RN-126: e-mail com o link para escolher uma nova senha. */
class RedefinirSenha extends Notification
{
    public function __construct(public readonly string $token)
    {
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutos = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Nova senha para o app de escalas')
            ->greeting('Olá!')
            ->line('Recebemos um pedido para escolher uma nova senha no app de escalas.')
            ->action('Escolher nova senha', self::link($this->token, $notifiable->getEmailForPasswordReset()))
            ->line("O link vale por {$minutos} minutos e só pode ser usado uma vez.")
            ->line('Se não foi você, ignore este e-mail: a sua senha continua a mesma.')
            ->salutation('Um produto SEVENFAITHTECH');
    }

    public static function link(string $token, string $email): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/redefinir-senha/'.$token.'?email='.urlencode($email);
    }
}
