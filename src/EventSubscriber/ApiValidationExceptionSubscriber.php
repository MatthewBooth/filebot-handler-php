<?php

namespace App\EventSubscriber;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;

final class ApiValidationExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::EXCEPTION => 'onKernelException'];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        while (null !== $exception) {
            if ($exception instanceof ValidationFailedException) {
                $errors = [];

                foreach ($exception->getViolations() as $violation) {
                    $errors[] = [
                        'field' => $violation->getPropertyPath(),
                        'message' => $violation->getMessage(),
                    ];
                }

                $event->setResponse(new JsonResponse([
                    'status' => 'error',
                    'message' => 'Request validation failed.',
                    'errors' => $errors,
                ], 422));

                return;
            }

            $exception = $exception->getPrevious();
        }
    }
}
