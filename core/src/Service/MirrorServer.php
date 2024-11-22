<?php

namespace App\Service;

use App\DTO\CaseJson\CFDocument;
use App\Entity\Framework\LsDoc;
use App\Entity\Framework\Mirror\Framework;
use App\Entity\Framework\Mirror\Log;
use App\Entity\Framework\Mirror\OAuthCredential;
use App\Entity\Framework\Mirror\Server;
use App\Form\DTO\MirroredFrameworkDTO;
use App\Form\DTO\MirroredServerDTO;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use kamermans\OAuth2\Exception\AccessTokenRequestException;
use kamermans\OAuth2\GrantType\ClientCredentials;
use kamermans\OAuth2\OAuth2Middleware;
use kamermans\OAuth2\Persistence\FileTokenPersistence;
use League\Uri\UriString;
use Symfony\Component\Serializer\SerializerInterface;

class MirrorServer
{
    use LoggerTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {
    }

    public function fetchDocumentList(Server $server): array
    {
        $body = $this->fetchDocumentListJson($server);

        try {
            $docList = json5_decode($body, true);
        } catch (\Exception $e) {
            $this->warning('Error: CFDocuments list is not valid JSON', ['exception' => $e->getMessage()]);

            throw new \RuntimeException(sprintf('Error getting CFDocuments list: Response was not valid JSON.'));
        }

        if (!array_key_exists('CFDocuments', $docList)) {
            if (count($docList) > 0) {
                // Texas's API does not have a CFDocuments JSON key and just returns an array from /CDocuments, add an exception to handle
                try {
                    $cfDocument = $this->serializer->deserialize(json_encode($docList[0], JSON_THROW_ON_ERROR), CFDocument::class, 'json');
                    if (null !== $cfDocument->cfPackageURI->uri) {
                        return $docList;
                    }
                } catch (\Throwable) {
                    // Fall through with error
                }
            }

            $this->warning('Error: CFDocuments list did not contain a CFDocuments JSON key', ['response' => $docList]);

            throw new \RuntimeException(sprintf('Error getting CFDocuments list: Response JSON did not contain a CFDocuments key.'));
        }

        return $docList['CFDocuments'];
    }

    public function addServer(MirroredServerDTO $dto): Server
    {
        $server = $this->getServerByUrl($dto->url);

        if (null !== $server) {
            $this->warning('Trying to add an already known server', ['url' => $dto->url]);

            throw new \RuntimeException(sprintf('The server %s is already known.', $dto->url));
        }

        $server = $this->addNewServerWithDocuments($dto);

        return $server;
    }

    public function addFramework(array $doc, Server $server, ?bool $include = null, ?string $url = null): Framework
    {
        $mirroredDoc = $this->em->getRepository(Server::class)->findFrameworkOnServer($doc['identifier'], $server);
        if (null === $mirroredDoc) {
            $mirroredDoc = new Framework($server, $doc['identifier']);
            $mirroredDoc->setInclude($include ?? $server->isAddFoundFrameworks());
        }

        if (null === $url) {
            $uri = UriString::parse($server->getUrl());
            $uri['path'] = rtrim($uri['path'], '/').Server::URL_CASE_1_0_PACKAGE.'/'.($doc['CFPackageURI']['identifier'] ?? $doc['identifier']);
            $uri['query'] = null;
            $uri['fragment'] = null;
            $url = UriString::build($uri);
        }
        if ($mirroredDoc->getUrl() !== $url) {
            $mirroredDoc->setUrl($url);
        }

        $this->em->persist($mirroredDoc);

        $mirroredDoc->setCreator($doc['creator'] ?? 'Unknown');
        $mirroredDoc->setTitle($doc['title'] ?? 'Unknown');

        if (Framework::STATUS_NEW !== $mirroredDoc->getStatus()) {
            // This framework is already being mirrored
            return $mirroredDoc;
        }

        $localDoc = $this->em->getRepository(LsDoc::class)->findOneByIdentifier($doc['identifier']);
        if (null !== $localDoc) {
            $mirroredDoc->setInclude(false);
            $mirroredDoc->setStatus(Framework::STATUS_ERROR);
            $mirroredDoc->setErrorType(Framework::ERROR_ID_CONFLICT);
            $mirroredDoc->addLog(Log::STATUS_FAILURE, 'A framework already exists on the server with the same identifier');
        }

        if (null === $localDoc && $mirroredDoc->isInclude()) {
            $mirroredDoc->markToRefresh();
        }

        return $mirroredDoc;
    }

    public function addSingleFramework(MirroredFrameworkDTO $dto): Framework
    {
        // Add framework to list of frameworks, using URL to fetch by
        // ?Determine if framework already is in the system (fail case)
        // Create framework mirror obj (if doesn't exist)

        // Get just the server portion of the URL
        $uri = UriString::parse($dto->url);
        $uri['path'] = '/';
        $url = UriString::build($uri);

        $server = $this->getServerByUrl($url);
        if (null === $server) {
            $serverDto = new MirroredServerDTO();
            $serverDto->url = $url;
            $serverDto->autoAddFoundFrameworks = false;
            $serverDto->credentials = $dto->credentials;

            $server = $this->addNewServer($serverDto);
            $server->setCheckServer(false);
            $server->setServerType(Server::TYPE_CASE_1_0);
        }

        $stringDoc = $this->fetchUrlWithCredentials($dto->url, $server->getCredentials());
        $jsonDoc = json5_decode($stringDoc, true);

        // If the URL was for a document instead of package then try again
        if (!isset($jsonDoc['CFDocument']) && isset($jsonDoc['CFPackageURI'])) {
            $dto->url = $jsonDoc['CFPackageURI']['uri'];

            $stringDoc = $this->fetchUrlWithCredentials($dto->url, $server->getCredentials());
            $jsonDoc = json5_decode($stringDoc, true);
        }

        if (!isset($jsonDoc['CFDocument'])) {
            $this->warning('CFDocument key not found in response', ['url' => $dto->url]);

            throw new \RuntimeException(sprintf('Error: Cannot find a CFPackage using the URL `%s`', $dto->url));
        }

        $jsonDoc = $jsonDoc['CFDocument'];

        $mirroredDoc = $this->addFramework($jsonDoc, $server, true, $dto->url);

        $this->em->flush();

        return $mirroredDoc;
    }

    public function updateNext(): ?Server
    {
        $server = $this->em->getRepository(Server::class)->findNext();
        if (null !== $server) {
            $this->updateFrameworkList($server);
        }

        return $server;
    }

    private function fetchDocumentListJson(Server $server): string
    {
        $uri = UriString::parse($server->getUrl());
        $uri['path'] = rtrim($uri['path'], '/').Server::URL_CASE_1_0_LIST;
        $url = UriString::build($uri);

        return $this->fetchUrlWithCredentials($url, $server->getCredentials());
    }

    private function getServerByUrl(string $url): ?Server
    {
        return $this->em->getRepository(Server::class)->findOneByUrl($url);
    }

    private function addNewServer(MirroredServerDTO $dto): Server
    {
        $server = new Server($dto->url, $dto->autoAddFoundFrameworks, $dto->credentials);
        $server->setStatus($dto->status);
        if (Server::STATUS_ACTIVE === $dto->status) {
            $server->scheduleNextCheck();
        }
        $this->em->persist($server);

        return $server;
    }

    private function addNewServerWithDocuments(MirroredServerDTO $dto): Server
    {
        $server = $this->addNewServer($dto);

        $this->updateFrameworkList($server);

        return $server;
    }

    public function fetchUrlWithCredentials(string $url, ?OAuthCredential $credentials = null): string
    {
        $stack = HandlerStack::create();

        if (null !== $credentials) {
            $oauth = $this->createOAuthHandler($credentials);
            $stack->push($oauth);
        }

        $jsonClient = new Client([
            'handler' => $stack,
        ]);

        try {
            /** @noinspection PhpUnhandledExceptionInspection */
            $response = $jsonClient->request(
                'GET',
                $url,
                [
                    RequestOptions::AUTH => (null !== $credentials) ? 'oauth' : null,
                    RequestOptions::TIMEOUT => 300,
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/json;q=0.8',
                    ],
                    RequestOptions::HTTP_ERRORS => false,
                ]
            );
        } catch (AccessTokenRequestException $e) {
            /**
             * @var TransferException
             *
             * @psalm-suppress UndefinedDocblockClass
             */
            $guzzleException = $e->getGuzzleException();
            $this->warning('Error authenticating to server', ['message' => $e->getMessage(), 'exception' => $guzzleException->getMessage()]);

            throw new \RuntimeException('Error authenticating to server: '.$guzzleException->getMessage(), 0, $e);
        } catch (RequestException|\Exception $e) {
            $this->warning('Error requesting URL from server', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Error requesting URL from server.', 0, $e);
        }

        if (200 !== $response->getStatusCode()) {
            $this->warning('Error requesting URL from server', ['url' => $url, 'response_code' => $response->getStatusCode(), 'response_reason' => $response->getReasonPhrase()]);

            throw new \RuntimeException(sprintf('Error: Request to `%s` returned `%s %s`.', $url, $response->getStatusCode(), $response->getReasonPhrase()), 0);
        }

        return $response->getBody()->getContents();
    }

    private function createOAuthHandler(OAuthCredential $credentials): OAuth2Middleware
    {
        $authClient = new Client([
            'base_uri' => $credentials->getAuthenticationEndpoint(),
        ]);

        $authConfig = [
            'client_id' => $credentials->getKey(),
            'client_secret' => $credentials->getSecret(),
            'scope' => implode(',', $credentials->getScopes()),
        ];

        $grantType = new ClientCredentials($authClient, $authConfig);
        $oauth = new OAuth2Middleware($grantType);

        $tokenFile = '/tmp/access_token_'.$credentials->getId().'.json';
        $tokenPersistence = new FileTokenPersistence($tokenFile);
        $oauth->setTokenPersistence($tokenPersistence);

        return $oauth;
    }

    public function updateFrameworkList(Server $server): void
    {
        try {
            $docList = $this->fetchDocumentList($server);
        } catch (\Throwable $e) {
            $this->warning('Error: Could not update framework list', ['exception' => $e->getMessage(), 'previousException' => $e->getPrevious()?->getMessage() ?? '']);
            if (Server::STATUS_SUSPENDED !== $server->getStatus()) {
                $server->scheduleNextCheck();
            }
            $this->em->flush();

            return;
        }

        foreach ($docList as $doc) {
            $this->addFramework($doc, $server);
        }

        $server->setLastCheck(new \DateTimeImmutable());
        $server->setPriority(0);
        if (Server::STATUS_SUSPENDED !== $server->getStatus()) {
            $server->scheduleNextCheck();
        }

        $this->em->flush();
    }
}
