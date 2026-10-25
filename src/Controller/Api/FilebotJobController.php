<?php

namespace App\Controller\Api;

use App\Dto\CreateFilebotJobRequestDto;
use App\Message\FilebotJobMessage;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

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
        } catch (Exception|ExceptionInterface $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
        return $this->json(['status' => 'queued'], Response::HTTP_ACCEPTED);
    }
}
