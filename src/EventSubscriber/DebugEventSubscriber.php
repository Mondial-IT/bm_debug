<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_debug\EventSubscriber;

use Drupal\bm_debug\Event\DebugInfoEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event subscriber for BM Listings module.
*/
class DebugEventSubscriber implements EventSubscriberInterface {

  public function onDebugInfoEvent(DebugInfoEvent $event): void {
    // Access the data from the event and perform desired actions.
    $data = $event->getData();
    if (function_exists('dsm')) {
      dsm($data);
    }
    // Perform any necessary debugging operations.
    // Drupal::moduleHandler()->invokeAll('bm_debug', [$data]);
  }

  public static function getSubscribedEvents(): array {
    return [
      'bm_debug.DebugInfoEvent' => 'onDebugInfoEvent',
    ];
  }

}
