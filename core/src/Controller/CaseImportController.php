<?php

namespace App\Controller;

use App\Command\CommandDispatcherTrait;
use App\Command\Import\ImportCaseJsonCommand;
use App\Entity\User\User;
use App\Security\Permission;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\NotBlank;

class CaseImportController extends AbstractController
{
    use CommandDispatcherTrait;

    #[Route(path: '/salt/case/import', name: 'import_case_file')]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function import(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $content = base64_decode($request->request->get('fileContent'));

        $command = new ImportCaseJsonCommand($content, $user->getOrg(), $user);
        $this->sendCommand($command);

        return new JsonResponse([
            'message' => 'Success',
        ]);
    }

    #[Route(path: '/salt/case/importRemote', name: 'import_case_file_remote')]
    #[IsGranted(Permission::FRAMEWORK_CREATE)]
    public function importRemote(Request $request, #[CurrentUser] User $user): Response
    {
        $defaultData = [];
        $form = $this->createFormBuilder($defaultData)
            ->add('url', UrlType::class, [
                'constraints' => new NotBlank(),
                'default_protocol' => 'http',
            ])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            ini_set('memory_limit', '4096M');
            set_time_limit(900);

            $data = $form->getData();

            $jsonClient = new Client();
            try {
                $response = $jsonClient->request(
                    'GET',
                    $data['url'],
                    [
                        RequestOptions::AUTH => null,
                        RequestOptions::ALLOW_REDIRECTS => true,
                        RequestOptions::TIMEOUT => 300,
                        RequestOptions::HEADERS => [
                            'Accept' => 'application/vnd.opensalt+json, application/json;q=0.8, text/plain;q=0.2, */*;q=0.1',
                        ],
                        RequestOptions::HTTP_ERRORS => false,
                    ]
                );
            } catch (\Exception $e) {
                return new JsonResponse(['error' => ['url' => $data['url'], 'exception' => $e->getMessage()]]);
            }

            if (200 !== $response->getStatusCode()) {
                return new JsonResponse(['error' => ['url' => $data['url'], 'response_code' => $response->getStatusCode(), 'response_reason' => $response->getReasonPhrase()]]);
            }

            $content = $response->getBody()->getContents();

            $command = new ImportCaseJsonCommand($content, $user->getOrg(), $user);
            $this->sendCommand($command);

            return new JsonResponse(['message' => 'Success']);
        }

        return $this->render('framework/import/new.html.twig', ['form' => $form->createView()]);
    }
}
