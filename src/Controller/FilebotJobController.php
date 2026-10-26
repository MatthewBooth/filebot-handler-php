<?php

namespace App\Controller;

use App\Dto\CreateFilebotJobMessageDto;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
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
        '/api/jobs',
        name: 'app_api_filebotjob_create',
        methods: ['POST'],
        format: 'json'
    )]
    #[OA\Post(
        summary: 'Queue a FileBot job',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: new Model(type: CreateFilebotJobMessageDto::class),
                example: [
                    'path' => '/torrents/Example.Movie.2024/Example.Movie.2024.mkv',
                    'label' => 'movie',
                    'order' => 'Airdate',
                    'conflict' => 'skip',
                    'action' => 'test',
                    'mediaId' => '',
                    'strict' => false,
                    'artwork' => true,
                ],
            ),
        ),
        tags: ['Jobs'],
        responses: [
            new OA\Response(
                response: 202,
                description: 'The job was queued.',
                content: new OA\JsonContent(
                    properties: [new OA\Property(property: 'status', type: 'string', example: 'queued')],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'The request failed validation.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Request validation failed.'),
                        new OA\Property(
                            property: 'errors',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'field', type: 'string', example: 'path'),
                                    new OA\Property(property: 'message', type: 'string', example: 'The path does not exist.'),
                                ],
                            ),
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 500,
                description: 'The job could not be queued.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'error'),
                        new OA\Property(property: 'message', type: 'string', example: 'Unable to queue FileBot job.'),
                    ],
                ),
            ),
        ],
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
