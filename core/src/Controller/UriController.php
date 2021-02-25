<?php

namespace App\Controller;

use App\Service\IdentifiableObjectHelper;
use App\Service\UriGenerator;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UriController extends AbstractController
{
    private SerializerInterface $serializer;

    private IdentifiableObjectHelper $objectHelper;

    private string $assetsVersion;

    public function __construct(SerializerInterface $serializer, IdentifiableObjectHelper $uriHelper, string $assetsVersion)
    {
        $this->serializer = $serializer;
        $this->objectHelper = $uriHelper;
        $this->assetsVersion = $assetsVersion;
    }

    /**
     * @Route("/uri/", methods={"GET"}, defaults={"_format"="html"}, name="uri_lookup_empty")
     */
    public function findEmptyUriAction(Request $request): Response
    {
        // No identifier passed on the URL
        if ('json' === $request->getRequestFormat()) {
            return new JsonResponse([
                'error' => 'Identifier not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->render('uri/no_uri.html.twig', ['uri' => null], new Response('', Response::HTTP_NOT_FOUND));
    }

    /**
     * @Route("/uri/{uri}.{_format}", methods={"GET"}, defaults={"_format"=null}, name="uri_lookup")
     */
    public function findUriAction(Request $request, string $uri, ?string $_format): Response
    {
        if ($request->isXmlHttpRequest()) {
            $_format = 'json';
        }
        $this->determineRequestFormat($request, $_format);

        $isPackage = false;
        if (str_starts_with($uri, UriGenerator::PACKAGE_PREFIX)) {
            $isPackage = true;
            $uri = preg_replace('/^'.UriGenerator::PACKAGE_PREFIX.'/', '', $uri);
        }

        $obj = $this->objectHelper->findObjectByIdentifier($uri);

        if (null === $obj) {
            if ('html' === $request->getRequestFormat()) {
                return $this->render('uri/uri_not_found.html.twig', ['uri' => $uri], new Response('', Response::HTTP_NOT_FOUND));
            }

            return new JsonResponse([
                'error' => sprintf('Object with identifier "%s" was not found', $uri),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($isPackage && 'json' === $request->getRequestFormat()) {
            // Redirect to API for the package
            return $this->redirectToRoute('api_v1p0_cfpackage', ['id' => $uri]);
        }

        $lastModified = $obj->getUpdatedAt();
        $response = $this->generateBaseResponse($lastModified);

        if ($response->isNotModified($request)) {
            return $response;
        }

        // Found -- Display
        $serializationContext = SerializationContext::create();
        $serializationGroups = ['Default', 'LsDoc', 'LsItem', 'LsAssociation'];
        $serializationContext->setGroups($serializationGroups);
        $serialized = $this->serializer->serialize(
            $obj,
            'json',
            $serializationContext
        );
        if ('html' === $request->getRequestFormat()) {
            $className = substr(strrchr(get_class($obj), '\\'), 1);

            return $this->render('uri/found_uri.html.twig', [
                'obj' => $obj,
                'class' => $className,
                'isPackage' => $isPackage,
                'serialized' => json_decode($serialized, true, 512, JSON_THROW_ON_ERROR),
            ], $response);
        }

        $response->setContent($serialized);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    private function determineRequestFormat(Request $request, ?string $_format, array $allowedFormats = ['json', 'html']): void
    {
        if (!in_array($request->getRequestFormat($_format), $allowedFormats, true)) {
            $cTypes = $request->getAcceptableContentTypes();
            $format = null;
            foreach ($cTypes as $cType) {
                if ($request->getFormat($cType)) {
                    $format = $request->getFormat($cType);
                    if ('json' === $format || 'html' === $format) {
                        break;
                    }
                }
            }
            // If there was no match found, default to old request format.
            $format = $format ?: $request->getRequestFormat();

            $request->setRequestFormat($format);
        }
    }

    protected function generateBaseResponse(\DateTimeInterface $lastModified): Response
    {
        $response = new Response();

        $response->setEtag(md5($lastModified->format('U.u').$this->assetsVersion), true);
        $response->setLastModified($lastModified);
        $response->setMaxAge(60);
        $response->setSharedMaxAge(60);
        $response->setPublic();
        $response->setVary(['Accept', 'Accept-Language']);

        return $response;
    }
}
