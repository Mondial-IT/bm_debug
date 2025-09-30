<?php
// web/modules/custom/bm_debug/src/EventSubscriber/BmExceptionSubscriber.php
declare(strict_types=1);

namespace Drupal\bm_debug\EventSubscriber;

use Drupal\Core\Extension\Exception\UnknownExtensionException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BmExceptionSubscriber implements EventSubscriberInterface {

    public function __construct(private LoggerInterface $logger) {}

    public static function getSubscribedEvents(): array {
        return [
            KernelEvents::EXCEPTION => ['onException', 50],
        ];
    }

    public function onException(ExceptionEvent $event): void {
        $e = $event->getThrowable();

        if ($e instanceof UnknownExtensionException || $e instanceof \UnexpectedValueException) {
            $this->logger->error(
                'Handled exception: {class}: {message}',
                [
                    'class' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            if (!$this->isCli() && !$event->hasResponse()) {
                $response = $this->buildSafeResponse($event->getRequest());
                $event->setResponse($response);
            }
        }
    }

    private function isCli(): bool {
        return \PHP_SAPI === 'cli';
    }

    private function buildSafeResponse($request): Response {
        $accept = (string) $request->headers->get('Accept');
        if (str_contains($accept, 'application/json') || str_contains($accept, 'json')) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'A recoverable application error occurred.',
            ], 500);
        }

        return new Response(
            'A recoverable application error occurred.',
            500,
            ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }

}
