<?php

namespace App\Controller;

use App\Service\FilebotJobQueue;
use App\Service\FilebotJobHistoryStoreInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(
        FilebotJobQueue $queue,
        FilebotJobHistoryStoreInterface $history,
        LoggerInterface $logger,
    ): Response
    {
        return $this->renderDashboard($queue, $history, $logger, 'dashboard/index.html.twig');
    }

    #[Route('/dashboard/live', name: 'app_dashboard_live', methods: ['GET'])]
    public function live(
        FilebotJobQueue $queue,
        FilebotJobHistoryStoreInterface $history,
        LoggerInterface $logger,
    ): Response
    {
        return $this->renderDashboard($queue, $history, $logger, 'dashboard/_live_content.html.twig');
    }

    private function renderDashboard(
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
        $workers = [];

        foreach ($jobs as $job) {
            if (!isset($job['worker'])) {
                continue;
            }

            $workers[$job['worker']][] = [
                'id' => $job['id'],
                'path' => $job['message']['path'],
            ];
        }

        $historyAvailable = true;
        $historyJobs = [];

        try {
            $historyJobs = $historyStore->recentTerminalJobs(50);
        } catch (Throwable $exception) {
            $logger->error('Failed to load FileBot job history.', ['exception' => $exception]);
            $historyAvailable = false;
        }

        return $this->render($template, [
            'jobs' => $jobs,
            'queueAvailable' => $queueAvailable,
            'queuedCount' => $queueAvailable ? count($jobs) - $processingCount : null,
            'processingCount' => $queueAvailable ? $processingCount : null,
            'workerCount' => $queueAvailable ? count($workers) : null,
            'workers' => $workers,
            'historyAvailable' => $historyAvailable,
            'historyJobs' => $historyJobs,
        ], new Response(status: $queueAvailable ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }
}
