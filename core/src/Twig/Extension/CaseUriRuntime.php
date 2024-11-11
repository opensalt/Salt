<?php

namespace App\Twig\Extension;

use App\Entity\Framework\IdentifiableInterface;
use App\Service\IdentifiableObjectHelper;
use App\Service\UriGenerator;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\RuntimeExtensionInterface;

class CaseUriRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private UriGenerator $uriGenerator,
        private RouterInterface $router,
        private IdentifiableObjectHelper $uriHelper,
    ) {
    }

    public function getObjectUri(?IdentifiableInterface $obj, ?string $route = null): ?string
    {
        if (null === $obj) {
            return null;
        }

        return $this->uriGenerator->getUri($obj, $route);
    }

    public function getUriForIdentifier(?string $identifier): ?string
    {
        if (null === $identifier || '' === $identifier) {
            return null;
        }

        if (str_starts_with($identifier, 'data:text')) {
            return $identifier;
        }

        return $this->uriGenerator->getPublicUriForIdentifier($identifier);
    }

    public function getLocalUri(?string $uri): ?string
    {
        if (null === $uri) {
            return null;
        }

        if (str_starts_with($uri, 'local:')) {
            $uri = preg_replace('/^local:/', '', $uri);

            return $this->getUriForIdentifier($uri);
        }

        $obj = $this->uriHelper->findObjectByUri($uri);

        if (null === $obj) {
            return null;
        }

        return $this->router->generate('uri_lookup', ['uri' => $obj->getIdentifier()], RouterInterface::ABSOLUTE_URL);
    }

    public function getLocalOrRemoteUri(?string $uri): ?string
    {
        if (null === $uri) {
            return null;
        }

        if (str_starts_with($uri, 'local:')) {
            return $this->getLocalUri($uri);
        }

        return $uri;
    }
}
