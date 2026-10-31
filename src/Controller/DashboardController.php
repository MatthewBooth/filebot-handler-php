<?php

namespace App\Controller;

use App\Service\FilebotJobQueue;
use App\Service\FilebotJobHistoryStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        Request $request,
        FilebotJobQueue $queue,
        FilebotJobHistoryStoreInterface $history,
        LoggerInterface $logger,
    ): Response
    {
        return $this->renderDashboard($request, $queue, $history, $logger, 'dashboard/index.html.twig');
    }

    #[Route('/dashboard/live', name: 'app_dashboard_live', methods: ['GET'])]
    public function live(
        Request $request,
        FilebotJobQueue $queue,
        FilebotJobHistoryStoreInterface $history,
        LoggerInterface $logger,
    ): Response
    {
        return $this->renderDashboard($request, $queue, $history, $logger, 'dashboard/_live_content.html.twig');
    }

    private function renderDashboard(
        Request $request,
        FilebotJobQueue $queue,
        FilebotJobHistoryStoreInterface $historyStore,
        LoggerInterface $logger,
        string $template,
    ): Response
    {
        $queueAvailable = true;
        $jobs = [];

        try {
            $jobs = $queue->list(500);
        } catch (Throwable $exception) {
            $logger->error('Failed to load the FileBot job dashboard.', [
                'exception' => $exception,
            ]);
            $queueAvailable = false;
        }

        $processingCount = count(array_filter(
            $jobs,
            static fn (array $job): bool => $job['status'] === 'processing',
        ));
        $historyAvailable = true;
        $historyJobs = [];
        $historyPageSize = 10;
        $historyCount = 0;
        $historyPage = max(1, $request->query->getInt('page', 1));

        try {
            $historyCount = $historyStore->countRecentTerminalJobs();
            $historyPageCount = max(1, (int) ceil($historyCount / $historyPageSize));
            $historyPage = min($historyPage, $historyPageCount);
            $historyJobs = $historyStore->recentTerminalJobs($historyPageSize, ($historyPage - 1) * $historyPageSize);
        } catch (Throwable $exception) {
            $logger->error('Failed to load FileBot job history.', ['exception' => $exception]);
            $historyAvailable = false;
        }

        return $this->render($template, [
            'jobs' => $jobs,
            'queueAvailable' => $queueAvailable,
            'queuedCount' => $queueAvailable ? count($jobs) - $processingCount : null,
            'processingCount' => $queueAvailable ? $processingCount : null,
            'historyAvailable' => $historyAvailable,
            'historyJobs' => $historyJobs,
            'historyCount' => $historyCount,
            'historyPage' => $historyPage,
            'historyPageCount' => max(1, (int) ceil($historyCount / $historyPageSize)),
        ], new Response(status: $queueAvailable ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
