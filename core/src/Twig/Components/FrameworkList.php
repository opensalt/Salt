<?php

namespace App\Twig\Components;

use App\Entity\Framework\LsDoc;
use App\Repository\Framework\LsDocRepository;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
class FrameworkList
{
    public ?string $creator = null;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly LsDocRepository $docRepository,
    ) {
    }

    public function getFrameworks(): array
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        $results = $this->docRepository->findForList();

        $lsDocs = [];
        foreach ($results as $lsDoc) {
            if (null !== $this->creator && $lsDoc->getCreator() !== $this->creator) {
                continue;
            }

            // Optimization: All but "Private Draft" are viewable to everyone (if not mirrored), only auth check "Private Draft"
            if ((null !== $user && $this->authorizationChecker->isGranted(Permission::FRAMEWORK_LIST, $lsDoc))
                || (LsDoc::ADOPTION_STATUS_PRIVATE_DRAFT !== $lsDoc->getAdoptionStatus()
                    && (!$lsDoc->isMirrored() || true === $lsDoc->getMirroredFramework()?->isVisible()))) {
                $lsDocs[$lsDoc->getCreator()][] = $lsDoc;
            }
        }

        ksort($lsDocs);
        foreach ($lsDocs as $creator => $list) {
            uasort($lsDocs[$creator], function ($a, $b) {
                return $a->getTitle() <=> $b->getTitle();
            });
        }

        return $lsDocs;
    }
}
