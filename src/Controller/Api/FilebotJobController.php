<?php

namespace App\Controller\Api;

use App\Dto\CreateFilebotJobRequestDto;
use App\Message\FilebotJobMessage;
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
        #[MapRequestPayload] CreateFilebotJobRequestDto $createFilebotJobRequest,
        MessageBusInterface                             $messageBus
    ): JsonResponse
    {
        try {
            $messageBus->dispatch(new FilebotJobMessage($createFilebotJobRequest->toFilebotJob()));
        } catch (Throwable $e) {
            return $this->json([
                'status' => 'error',
                'type' => $e::class,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $this->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
