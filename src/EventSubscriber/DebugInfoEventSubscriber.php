<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_debug\EventSubscriber;

use Drupal\bm_debug\Event\DebugInfoEvent;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Event subscriber for BM Debug module.
 */
class DebugInfoEventSubscriber implements EventSubscriberInterface {

  public function onDebugInfoEvent(DebugInfoEvent $event): void {
    // $event->setDebugInfo('bm_debug','bmd',"use as bm_d('bmd',variable,title,destination); ");
  }

  //  public static function getSubscribedEvents(): array {
  //
  //    return [
  //      'bm_debug.DebugInfoEvent' => 'onDebugInfoEvent',
  //    ];
  //  }

  public static function getSubscribedEvents(): array {
    // The getSubscribedEvents() method returns an array of event names this subscriber wants to listen to.
    $events[KernelEvents::EXCEPTION][] = ['onKernelException', -100];
    return $events;
  }

  public function onKernelException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    $message = $exception->getMessage();
    $trace = $exception->getTraceAsString(); // Get the stack trace as a string

    // Log the full exception with trace
    \Drupal::logger('bm_debug')->error('Uncaught exception: @message. Stack trace: @trace', [
      '@message' => $message,
      '@trace' => $trace,
    ]);

    // Display a message to the user
    \Drupal::messenger()->addMessage('bm_debug: Caught Uncaught exception: ' . $message . ' see log.', MessengerInterface::TYPE_ERROR);
    $user = \Drupal::currentUser()->id();
    if ($user === 1) {
      dsm($trace, $message);
    }

    // Check for specific conditions like missing routes for images
    if (str_contains($message, 'No route found for "GET')) {
      $request = $event->getRequest();
      $path = $request->getPathInfo();

      // Check if the path is an image path
      if (preg_match('/\/styles\/[^\/]+\/public\/(.+)$/', $path, $matches)) {
        $relative_path = $matches[1];

        // Generate the alternative URI
        $alternative_uri = 'source://' . $relative_path;
        $this->updateManagedFileUriIfMissing($alternative_uri);
      }
      if (str_contains($path, 'min.js.map')) {
        // ignore the missing map file warnings
        return;
      }
    }
    $nicerTrace = str_replace('#', '<br />#', $trace);
    $nicerTrace = str_replace('/home/docker_volume/git_root/drupal_root/', '', $nicerTrace);
    $nicerTrace = str_replace('web/modules/custom/', '_____ web/modules/custom/', $nicerTrace);

    $pattern = '/\/custom\/[^\/]+\//';
    $replacement = '<span style="color:#5A5CAD">($0)</span>';
    $nicerTrace = preg_replace($pattern, $replacement, $nicerTrace);

    // stand out the line numbers

    // Regular expression to match numbers in parentheses
    $pattern = '/\((\d+)\)/';
    $replacement = '<span style="background-color:palegoldenrod">($1)</span>';
    $nicerTrace = preg_replace($pattern, $replacement, $nicerTrace);

    // Set a response (optional) with the error message
    $response = new Response(Markup::create(
      '<div style="margin:200px auto;font-size:20px;font-weight:bold;color:white;font-family:roboto, sans-serif;
                    background:#98c5f3;border:1px solid lavenderblush;
                    padding:20px;
                    border-radius:15px;
                    box-shadow:1px 1px #eee;width:800px;text-align: center">
            <p>Oeps an error has occurred.</p>
            <p>Not to worry, admin has been notified.</p>
            <p>Blue Marloc bm_debug</p>
            <p><a href="/dashboard">Continue to dashboard</a></p>'
      . '</div>'

      . '<details style="color: black;font-size: 14px;font-family: system-ui;line-height: 1.2em;}"><summary>Error details !</summary>
            <span style="color:violet">' . $message . '</span> ' . $nicerTrace . '</details>'
    ));

    // Set HTTP status code
    $response->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);

    // Set the response on the event
    $event->setResponse($response);
  }



  /**
   * Updates the file URI of a managed file if the file does not exist at the real path.
   *
   * @param string $uri
   *   The URI of the file to check and update.
   */
  private function updateManagedFileUriIfMissing(string $uri): void {
    $file_system = \Drupal::service('file_system');
    $realpath = $file_system->realpath($uri);

    // Log the URI for debugging purposes.
    \Drupal::logger('bm_media')->debug('Checking file URI: ' . $uri);

    // If the file exists at the real path, no need to update.
    if (file_exists($realpath)) {
      \Drupal::logger('bm_media')->debug('File exists at real path: ' . $realpath);
      return;
    }

    // Load the file entity by its URI.
    $files = \Drupal::entityTypeManager()->getStorage('file')->loadByProperties(['uri' => $uri]);

    // Update the URI if the file entity is found.
    foreach ($files as $file) {
      // Replace 'public:' with 'source:' in the URI.
      $new_uri = str_replace('public://', 'source://', $file->getFileUri());

      // Check if the new URI file exists.
      $new_realpath = $file_system->realpath($new_uri);
      if (file_exists($new_realpath)) {
        // Update the file entity with the new URI.
        $file->setFileUri($new_uri);
        $file->save();

        \Drupal::logger('bm_media')->info('Updated file URI from %old_uri to %new_uri', [
          '%old_uri' => $uri,
          '%new_uri' => $new_uri,
        ]);
      } else {
        \Drupal::logger('bm_media')->warning('File not found at new URI: ' . $new_uri);
      }
    }
  }


}
