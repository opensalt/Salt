<?php

namespace App\Security;

use App\Entity\User\User;
use App\Repository\User\UserRepository;
use Novaway\Bundle\FeatureFlagBundle\Manager\FeatureManager;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator implements EventSubscriberInterface
{
    use TargetPathTrait;

    final public const string LOGIN_ROUTE = 'login';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RouterInterface $router,
        private readonly FeatureManager $featureManager,
    ) {
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate(self::LOGIN_ROUTE);
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && self::LOGIN_ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->request->get('_username');
        $password = $request->request->get('_password');
        $csrfToken = $request->request->get('_csrf_token');
        $targetPath = $request->request->get('_target_path');

        if (null !== $targetPath) {
            $this->saveTargetPath($request->getSession(), 'main', $targetPath);
        }

        return new Passport(
            new UserBadge($username, fn ($userIdentifier) => $this->userRepository->loadUserByIdentifier($userIdentifier)),
            new PasswordCredentials($password),
            [new CsrfTokenBadge('authenticate', $csrfToken)]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($this->featureManager->isEnabled('mfa')) {
            $user = $token->getUser();
            if ($user instanceof User && !$user->isTotpAuthenticationEnabled()) {
                return new RedirectResponse($this->router->generate('app_2fa_enable'));
            }
        }

        if (true !== $request->attributes->get('_stateless')
            && ($targetPath = $this->getTargetPath($request->getSession(), $firewallName))
            && $targetPath !== $this->getLoginUrl($request)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->router->generate('salt_index'));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $token = parent::createToken($passport, $firewallName);

        if (!$this->featureManager->isEnabled('mfa')) {
            $token->setAttribute(TwoFactorAuthenticator::FLAG_2FA_COMPLETE, true);
        }

        return $token;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || $request->isXmlHttpRequest()
            || self::LOGIN_ROUTE === $request->attributes->get('_route')
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

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Return JSON-formatted error if request is an ajax call
        if ($request->isXmlHttpRequest() || 'json' === $request->getRequestFormat()) {
            return new JsonResponse(
                [
                    'error' => [
                        'message' => 'Authentication Required',
                        'code' => 'AUTH-REQ',
                    ],
                ],
                Response::HTTP_UNAUTHORIZED
            );
        }

        return parent::start($request, $authException);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest'],
        ];
    }
}
