<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
 */

namespace Drupal\bm_debug;

use Drupal\Core\Render\RendererInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FinalExceptionHandler implements EventSubscriberInterface {

  protected LoggerInterface $logger;

  protected RendererInterface $renderer;

  public function __construct(LoggerInterface $logger, RendererInterface $renderer) {
    $this->logger = $logger;
    $this->renderer = $renderer;
    $logger->debug('construct debug finalExceptionHandler');
  }

  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    $this->logger->debug('bm_debug onException: ' . $exception->getMessage());
    // Log the exception
    $this->logger->error('bm_debug:' . $exception->getMessage(), ['exception' => $exception]);

    // Check if the exception is an HTTP exception
    if ($exception instanceof HttpExceptionInterface) {
      $statusCode = $exception->getStatusCode();
    }
    else {
      $statusCode = 500; // Default to 500 for internal server errors
    }

    // Set a custom response for the exception
    $response = new Response();
    $response->setContent('<h1>bm_debug: An error occurred!</h1>');
    $response->setStatusCode($statusCode);
    $event->setResponse($response);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'kernel.exception' => 'onException',
    ];
  }

}


