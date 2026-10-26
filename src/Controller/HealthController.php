<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractController
{
    #[Route(
        '/api/health',
        name: 'app_api_health',
        methods: ['GET'],
        format: 'json',
    )]
    #[OA\Get(
        summary: 'Check API health',
        tags: ['Health'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'The API is healthy.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'version', type: 'string', example: '1.0'),
                    ],
                ),
            ),
        ],
    )]
    public function index(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'version' => '1.0',
        ]);
    }
}
