<?php

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
    // Run fairly early, before default handlers (higher number = later).
    return [
      KernelEvents::EXCEPTION => ['onException', 50],
    ];
  }

  public function onException(ExceptionEvent $event): void {
    $e = $event->getThrowable();

    // Only care about UnknownExtensionException (you can widen this later).
    if ($e instanceof UnknownExtensionException) {
      // Log it to your channel.
      $this->logger->error(
        'Unknown extension: {message}',
        ['message' => $e->getMessage(), 'exception' => $e]
      );

      // If you want to prevent a hard error page, set a graceful response.
      // Remove this block if you only want to log and let Drupal handle it.
      if (!$this->isCli() && !$event->hasResponse()) {
        $response = $this->buildSafeResponse($e, $event->getRequest());
        $event->setResponse($response);
      }
    }
  }

  private function isCli(): bool {
    return \PHP_SAPI === 'cli';
  }

  private function buildSafeResponse(\Throwable $e, $request): Response {
    // For AJAX/JSON requests, return JSON.
    if (str_starts_with((string) $request->headers->get('Accept'), 'application/json')) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'A required extension was not found.',
      ], 500);
    }

    // For normal pages, show a generic 500 without details.
    return new Response(
      'Unexpected error: required extension not found.',
      500,
      ['Content-Type' => 'text/plain; charset=utf-8']
    );
  }

}
