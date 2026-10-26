<?php

namespace App\Controller;

use App\Dto\CreateFilebotJobMessageDto;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class FilebotJobController extends AbstractController
{
    #[Route(
        '/api/v1/jobs',
        name: 'app_api_filebotjob_create',
        methods: ['POST'],
        format: 'json'
    )]
    public function create(
        #[MapRequestPayload] CreateFilebotJobMessageDto $createFilebotJobRequest,
        MessageBusInterface                             $messageBus,
        LoggerInterface                                 $logger,
    ): JsonResponse {
        $job = $createFilebotJobRequest->toFilebotJobMessage();

        try {
            $messageBus->dispatch($job);
        } catch (Throwable $exception) {
            $logger->error('Failed to queue FileBot job.', ['exception' => $exception]);

            return $this->json([
                'status' => 'error',
                'message' => 'Unable to queue FileBot job.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
