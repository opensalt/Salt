<?php

declare(strict_types=1);

namespace App\Domain\FrontMatter\Controller;

use App\Domain\FrontMatter\Entity\FrontMatterRepository;
use App\Entity\Framework\LsDoc;
use App\Repository\Framework\LsDocRepository;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontMatterViewController extends AbstractController
{
    public function __construct(
        private readonly FrontMatterRepository $twigTemplateRepository,
        private readonly LsDocRepository $docRepository,
    ) {
    }

    #[Route('/front/{path}', name: 'front_matter')]
    public function index(string $path = 'index'): Response
    {
        $template = $this->twigTemplateRepository->findOneBy(['filename' => 'front:'.$path.'.html.twig']);

        if (!$template) {
            throw $this->createNotFoundException('Page not found.');
        }

        $results = $this->docRepository->findForList();

        $lsDocs = [];
        foreach ($results as $lsDoc) {
            // Optimization: All but "Private Draft" are viewable to everyone (if not mirrored), only auth check "Private Draft"
            if ((null !== $this->getUser() && $this->isGranted(Permission::FRAMEWORK_LIST, $lsDoc))
                || (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $lsDoc->getAdoptionStatus()
                    && (!$lsDoc->isMirrored() || true === $lsDoc->getMirroredFramework()?->isVisible()))) {
                $lsDocs[] = $lsDoc;
            }
        }

        return $this->render('front_matter/wrapper.html.twig', [
            'template' => $template->getFilename(),
            'docs' => $lsDocs,
        ]);
    }
}
