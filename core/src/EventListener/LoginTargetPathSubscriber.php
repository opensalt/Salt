<?php

namespace App\EventListener;

use App\Security\LoginFormAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginTargetPathSubscriber implements EventSubscriberInterface
{
    use TargetPathTrait;

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->isXmlHttpRequest()
            || LoginFormAuthenticator::LOGIN_ROUTE === $request->attributes->get('_route')
            || true === $request->attributes->get('_stateless')
            || !$request->hasSession()
        ) {
            return;
        }

        $targetPath = $this->getTargetPath($request->getSession(), 'main');
        if (null === $targetPath && !str_ends_with($request->getUri(), '/2fa')) {
            $this->saveTargetPath($request->getSession(), 'main', $request->getUri());
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
        ];
    }
}
