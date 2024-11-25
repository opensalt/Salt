<?php

namespace App\EventListener;

use ReCaptcha\ReCaptcha;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class LoginCaptchaListener implements EventSubscriberInterface
{
    private ?string $captchaSecret;

    public function __construct(?string $captchaSecret = null)
    {
        $this->captchaSecret = $captchaSecret;
    }

    public static function getSubscribedEvents(): array
    {
        return [SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin'];
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event): void
    {
        if (empty($this->captchaSecret)) {
            return;
        }

        $request = $event->getRequest();

        $recaptcha = new ReCaptcha($this->captchaSecret);
        $resp = $recaptcha->verify($request->request->get('g-recaptcha-response'), $request->getClientIp());

        if (!$resp->isSuccess()) {
            throw new BadCredentialsException("the reCAPTCHA wasn't entered correctly, please try again");
        }
    }
}
