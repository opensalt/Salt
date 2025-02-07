<?php

namespace App\Service;

use App\Entity\Framework\IdentifiableInterface;
use App\Entity\Framework\LsAssociation;
use App\Entity\Framework\Package;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

readonly class Api1Uris
{
    public function __construct(
        private RouterInterface $router,
        private UriGenerator $uriGenerator,
    ) {
    }

    public function getUri(?IdentifiableInterface $obj, ?string $route = null): ?string
    {
        return $this->generateUri($obj, $route);
    }

    public function getApiUrl(?IdentifiableInterface $obj, ?string $route = null): ?string
    {
        return $this->generateUri($obj, $route, true);
    }

    public function getApiUriForIdentifier(string $id, ?string $route): ?string
    {
        if (null === $route) {
            return null;
        }

        return $this->router->generate($route, ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    protected function generateUri(?IdentifiableInterface $obj, ?string $route = null, bool $isApiLink = false): ?string
    {
        if (null === $obj) {
            return null;
        }

        $uri = $obj->getUri();

        if (!str_starts_with($uri, 'local:')) {
            return $this->generateRemoteUri($uri, $route);
        }

        if (!$isApiLink) {
            return $this->uriGenerator->getUri($obj, $route);
        }

        if (null === $route) {
            $route = Api1RouteMap::getForObject($obj);
        }

        return $this->getApiUriForIdentifier($obj->getIdentifier(), $route);
    }

    protected function generateRemoteUri(string $uri, ?string $route = null): string
    {
        if (Api1RouteMap::getForClass(Package::class) === $route) {
            // Since we don't store the CF Package URI patch it
            $uri = str_replace('CFDocuments', 'CFPackages', $uri);
        }

        return $uri;
    }

    public function splitByComma(?string $csv): ?array
    {
        if (null === $csv) {
            return null;
        }

        $values = preg_split('/ *, */', $csv, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($values)) {
            return null;
        }

        return $values;
    }

    public function getLinkUriList(iterable $objs): ?array
    {
        $list = [];
        foreach ($objs as $obj) {
            $list[] = $this->getLinkUri($obj);
        }

        return $list;
    }

    public function getLinkUri(?IdentifiableInterface $obj, ?string $route = null): ?array
    {
        if (null === $obj) {
            return null;
        }

        $descriptors = [
            'getHumanCodingScheme',
            'getShortStatement',
            'getTitle',
            'getName',
        ];

        $title = null;
        foreach ($descriptors as $descriptor) {
            if (method_exists($obj, $descriptor) && !empty($title = $obj->{$descriptor}())) {
                break;
            }
        }

        return [
            'title' => !empty($title) ? $title : 'Linked Reference',
            'identifier' => $obj->getIdentifier(),
            'uri' => $this->getUri($obj, $route),
        ];
    }

    public function getNodeLinkUri(string $selector, LsAssociation $obj): ?array
    {
        $selector = strtolower($selector);

        $uri = match ($selector) {
            'origin' => $obj->getOrigin(),
            'destination' => $obj->getDestination(),
            default => throw new \InvalidArgumentException('Selector may only be "origin" or "destination"'),
        };

        if (is_object($uri)) {
            return $this->getLinkUri($uri);
        }

        $identifier = $obj->{'get'.$selector.'NodeIdentifier'}();

        if (str_starts_with($uri, 'local:')) {
            $uri = $this->uriGenerator->getPublicUriForIdentifier($identifier);
        }

        return [
            'title' => $selector.' node',
            'identifier' => $identifier,
            'uri' => $uri,
        ];
    }

    public function formatAssociationType(string $type): string
    {
        return lcfirst(str_replace(' ', '', $type));
    }
}
