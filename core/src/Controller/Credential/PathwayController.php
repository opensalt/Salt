<?php

namespace App\Controller\Credential;

use App\Repository\Framework\LsAssociationRepository;
use App\Repository\Framework\LsItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class PathwayController extends AbstractController
{
    public function __construct(
        private readonly LsItemRepository $itemRepository,
        private readonly LsAssociationRepository $associationRepository,
    ) {
    }

    #[Route('/pathway/{id}', name: 'pathway_view', methods: ['GET'])]
    public function pathway(string $id, Request $request): Response
    {
        $credential = $this->itemRepository->findOneByIdentifier($id);
        $checked = $request->query->all('has');

        if (!str_starts_with($credential?->getItemType()?->getTitle() ?? '', 'Credential - ')) {
            throw $this->createNotFoundException('No pathway found');
        }

        /*
        $criteria = [];
        $associations = $credential->getInverseAssociations();
        foreach ($associations as $association) {
            $origin = $association->getOrigin();
            if ($origin instanceof LsDoc) {
                continue;
            }

            switch ($association->getType()) {
                case LsAssociation::PRECEDES:
                    $criteria[$origin->getIdentifier()] = $origin;
                    break;

                default:
                    break;
            }
        }
        */
        return $this->render('pathway/pathway_view.html.twig', [
            'id' => $id,
            'credential' => $credential,
            'checked' => $checked,
            'associationRepo' => $this->associationRepository,
            'itemRepo' => $this->itemRepository,
        ]);
    }
}
