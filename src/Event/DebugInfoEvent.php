<?php

/*
 Copyright (c) Mondial-IT BV - Blue Marloc 2024
   Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_debug\Event;

use Drupal;
use Drupal\bm_debug\BMDebugInfo\BMDebugInfo;
use stdClass;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Custom event for BM Debug module.
 */
class DebugInfoEvent extends Event {

  const CUSTOM_EVENT = 'bm_debug.DebugInfoEvent';

  protected array $data;

  public function __construct(array $data) {
    $this->data = $data;

  }

  public function getData(): array {
    // these debugs are set in bm_d() function see .module
    $cachedDebugs = Drupal::cache('bluemarloc')->get('BMDebug');
    //dsm($cachedDebugs, 'getData');
    if ($cachedDebugs) {
      $cachedDebugsData = $cachedDebugs->data;
      $existingKeys = array_column($this->data, 'key');
      $existingModules = array_column($this->data, 'module');
      foreach ($cachedDebugsData as $BMDebugInfo) {
        if (!isset($BMDebugInfo->key)) {
          continue;
        }
        $found = FALSE;
        $keyIndex = array_search($BMDebugInfo->key, $existingKeys, TRUE);
        if ($keyIndex !== FALSE && isset($existingModules[$keyIndex])) {
          if ($BMDebugInfo->module === $existingModules[$keyIndex]) {
            $found = TRUE;
          }
        }
        if (!$found) {
          $this->data[] = $BMDebugInfo;
        }
      }

    }
    return $this->data;
  }

  public function setData($data): void {

  }

  public function setDebugInfo($module, $key, $description): void {
    $info = new BMDebugInfo();

    $info->module = $module;
    $info->key = $key;
    $info->description = $description;
    $info->default = 0;
    $info->screen_dsm_log = 'screen';
    $this->data[$module . $key] = $info;
  }

}
