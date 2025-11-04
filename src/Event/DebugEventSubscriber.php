<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_debug\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Custom event for BM Listings module.
 */
class DebugEventSubscriber extends Event {

  const CUSTOM_EVENT = 'bm_debug.DebugInfoEvent';

  protected array $data;

  public function __construct(array $data) {
    $this->data = $data;
    dsm('construct DebugEventSubscriber');
  }

  public function getData(): array {
    return $this->data;
  }

}
