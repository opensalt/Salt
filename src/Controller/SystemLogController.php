<?php

namespace App\Controller;

use App\Repository\ChangeEntryRepository;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use SimpleThings\EntityAudit\AuditReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemLogController extends AbstractController
{
    /**
     * @var AuditReader
     */
    protected $auditReader;

    /**
     * @var ChangeEntryRepository
     */
    private $entryRepository;

    public function __construct(AuditReader $auditReader, ChangeEntryRepository $entryRepository)
    {
        $this->auditReader = $auditReader;
        $this->entryRepository = $entryRepository;
    }

    /**
     * @Route("/system_log", methods={"GET"}, name="system_logs_show")
     * @Security("is_granted('ROLE_SUPER_USER')")
     * @Template()
     */
    public function showSystemLogs(): array
    {
        return [];
    }

    /**
     * @Route("/system_log/revisions/{offset}/{limit}", methods={"GET"}, requirements={"offset" = "\d+", "limit" = "\d+"}, defaults={"offset" = 0, "limit" = 0}, name="system_logs_json")
     * @Security("is_granted('ROLE_SUPER_USER')")
     */
    public function listDocRevisionsAction(int $offset = 0, int $limit = 0): Response
    {
        $response = new StreamedResponse();
        $response->headers->set('Content-type', 'application/json');

        $response->setCallback(function () use ($limit, $offset) {
            $fd = fopen('php://output', 'wb+');
            fwrite($fd, '{"data": [');

            $history = $this->entryRepository->getChangeEntriesForSystem($limit, $offset);

            $first = true;
            foreach ($history as $line) {
                if (!$first) {
                    fwrite($fd, ',');
                } else {
                    $first = false;
                }
                fwrite($fd, json_encode($line));
            }

            fwrite($fd, ']}');
            fclose($fd);
        });

        return $response;
    }

    /**
     * @Route("/system_log/revisions/count", methods={"GET"}, name="system_logs_count")
     * @Security("is_granted('ROLE_SUPER_USER')")
     */
    public function changeLogCount(): Response
    {
        $count = $this->entryRepository->getChangeEntryCountForSystem();

        return new JsonResponse($count);
    }

    /**
     * @Route("/system_log/export", methods={"GET"}, name="system_logs_csv")
     * @Security("is_granted('ROLE_SUPER_USER')")
     *
     * @return Response
     */
    public function exportSystemLogs(): Response
    {
        $response = new StreamedResponse();
        $response->headers->set('Content-type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="system_log.csv"');

        $response->setCallback(function () {
            $fd = fopen('php://output', 'wb+');

            fputcsv($fd, ['Date/Time (UTC timezone)', 'Description', 'Username']);

            $history = $this->entryRepository->getChangeEntriesForSystem(0, 0);
            foreach ($history as $line) {
                fputcsv($fd, [
                    preg_replace('/\..*$/', '', $line['changed_at']),
                    $line['description'],
                    $line['username']
                ]);
            }

            fclose($fd);
        });

        return $response;
    }
}
