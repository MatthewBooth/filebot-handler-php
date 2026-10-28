<?php

namespace App\Controller;

use App\Service\FilebotJobQueue;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard', methods: ['GET'])]
    public function index(FilebotJobQueue $queue, LoggerInterface $logger): Response
    {
        try {
            $jobs = $queue->list(500);
        } catch (Throwable $exception) {
            $logger->error('Failed to load the FileBot job dashboard.', [
                'exception' => $exception,
            ]);

            return $this->render('dashboard/index.html.twig', [
                'jobs' => [],
                'queueAvailable' => false,
                'queuedCount' => null,
                'processingCount' => null,
                'workerCount' => null,
                'workers' => [],
            ], new Response(status: Response::HTTP_SERVICE_UNAVAILABLE));
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

        return $this->render('dashboard/index.html.twig', [
            'jobs' => $jobs,
            'queueAvailable' => true,
            'queuedCount' => count($jobs) - $processingCount,
            'processingCount' => $processingCount,
            'workerCount' => count($workers),
            'workers' => $workers,
        ]);
    }
}
