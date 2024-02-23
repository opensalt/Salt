<?php

namespace App\Controller\User;

use App\Command\CommandDispatcherTrait;
use App\Command\User\UpdateUserCommand;
use App\Entity\User\User;
use Doctrine\Persistence\ManagerRegistry;
use League\OAuth2\Client\Provider\Github;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route(path: '/login')]
class OAuthServiceController extends AbstractController
{
    use CommandDispatcherTrait;

    public function __construct(
        private readonly ?string $githubClientId = null,
        private readonly ?string $githubClientSecret = null,
        private readonly ?string $githubRedirectUri = null,
    ) {
    }

    /**
     * Save the Github Access Token.
     *
     * @return RedirectResponse
     *
     * @throws \UnexpectedValueException
     */
    #[Route(path: '/check-github', name: 'github_login', methods: ['GET'])]
    public function github(Request $request, SessionInterface $session, ManagerRegistry $managerRegistry): Response
    {
        if (!empty($this->githubRedirectUri)) {
            $redirectUri = $this->githubRedirectUri;
        }
        if (empty($redirectUri)) {
            $redirectUri = $this->generateUrl(
                'github_login',
                [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        }

        $provider = new Github([
            'clientId' => $this->githubClientId,
            'clientSecret' => $this->githubClientSecret,
            'redirectUri' => $redirectUri,
        ]);

        $code = $request->query->get('code');
        $state = $request->query->get('state');

        // User logged in
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            throw new \UnexpectedValueException('Invalid user.');
        }

        if (!isset($code)) {
            $options = [
                'scope' => ['user', 'user:email', 'repo'],
            ];
            // If we don't have an authorization code then get one
            $authUrl = $provider->getAuthorizationUrl($options);
            $session->set('oauth2state', $provider->getState());

            return $this->redirect($authUrl);
            // Check given state against previously stored one to mitigate CSRF attack
        }

        if (empty($state) || ($state !== $session->get('oauth2state'))) {
            $session->remove('oauth2state');

            throw new \UnexpectedValueException('Invalid state.');
        }

        // Try to get an access token (using the authorization code grant)
        $token = $provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        $em = $managerRegistry->getManager();
        $user = $em->getRepository(User::class)->find($currentUser->getId());
        if (null === $user) {
            throw new \UnexpectedValueException('Invalid user.');
        }

        // Set an access token per each user for fetch info.
        $user->setGithubToken($token->getToken());

        $command = new UpdateUserCommand($user);
        $this->sendCommand($command);

        return $this->redirectToRoute('lsdoc_index');
    }
}
