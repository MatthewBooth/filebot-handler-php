<?php

namespace App\Controller;

use App\Dto\CreateFilebotJobMessageDto;
use App\Service\FilebotJobQueue;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class FilebotJobController extends AbstractController
{
    #[Route('/api/jobs', name: 'app_api_filebotjob_list', methods: ['GET'], format: 'json')]
    #[OA\Get(
        summary: 'List queued FileBot jobs',
        tags: ['Jobs'],
        parameters: [
            new OA\Parameter(
                name: 'limit',
                description: 'Maximum number of jobs to return (1–500).',
                in: 'query',
                schema: new OA\Schema(type: 'integer', default: 100, maximum: 500, minimum: 1),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Queued jobs and their message details.'),
            new OA\Response(response: 400, description: 'The limit is outside the supported range.'),
            new OA\Response(response: 503, description: 'The queue could not be inspected.'),
        ],
    )]
    public function list(Request $request, FilebotJobQueue $queue, LoggerInterface $logger): JsonResponse
    {
        $limit = filter_var($request->query->get('limit', '100'), FILTER_VALIDATE_INT);
        if (false === $limit || $limit < 1 || $limit > 500) {
            return $this->json([
                'status' => 'error',
                'message' => 'The limit must be between 1 and 500.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            return $this->json(['jobs' => $queue->list($limit)]);
        } catch (Throwable $exception) {
            $logger->error('Failed to list queued FileBot jobs.', ['exception' => $exception]);

            return $this->json([
                'status' => 'error',
                'message' => 'Unable to inspect the FileBot job queue.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }

    #[Route('/api/jobs/{id}', name: 'app_api_filebotjob_show', methods: ['GET'], format: 'json')]
    #[OA\Get(
        summary: 'View a queued FileBot job',
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'The queued job and its message details.'),
            new OA\Response(response: 404, description: 'No queued FileBot job has this ID.'),
            new OA\Response(response: 503, description: 'The queue could not be inspected.'),
        ],
    )]
    public function show(string $id, FilebotJobQueue $queue, LoggerInterface $logger): JsonResponse
    {
        try {
            $job = $queue->find($id);
        } catch (Throwable $exception) {
            $logger->error('Failed to inspect queued FileBot job.', ['job_id' => $id, 'exception' => $exception]);

            return $this->json([
                'status' => 'error',
                'message' => 'Unable to inspect the FileBot job queue.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (null === $job) {
            return $this->json([
                'status' => 'error',
                'message' => 'Queued FileBot job not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($job);
    }

    #[Route('/api/jobs/{id}', name: 'app_api_filebotjob_delete', methods: ['DELETE'], format: 'json')]
    #[OA\Delete(
        summary: 'Delete a queued FileBot job',
        tags: ['Jobs'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'The queued job was deleted.'),
            new OA\Response(response: 404, description: 'No queued FileBot job has this ID.'),
            new OA\Response(response: 503, description: 'The job could not be removed from the queue.'),
        ],
    )]
    public function delete(string $id, FilebotJobQueue $queue, LoggerInterface $logger): JsonResponse
    {
        try {
            $deleted = $queue->delete($id);
        } catch (Throwable $exception) {
            $logger->error('Failed to delete queued FileBot job.', ['job_id' => $id, 'exception' => $exception]);

            return $this->json([
                'status' => 'error',
                'message' => 'Unable to delete the FileBot job from the queue.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$deleted) {
            return $this->json([
                'status' => 'error',
                'message' => 'Queued FileBot job not found.',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['status' => 'deleted', 'id' => $id]);
    }

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
