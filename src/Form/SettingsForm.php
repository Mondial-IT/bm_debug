<?php

declare(strict_types=1);

/*
 * Copyright (c) Mondial-IT BV - Blue Marloc 2024
 * Created on 2024-11-21 at 11:32:11
 */

namespace Drupal\bm_debug\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\bm_debug\Event\DebugInfoEvent;

/**
 * Debug toggles screen for Blue Marloc modules (session-scoped).
 *
 * Notes for Drupal 11:
 * - Uses DI for EventDispatcher and Session.
 * - Uses new Symfony 6 dispatcher signature: dispatch(object $event, ?string $eventName = null).
 * - Avoids direct $_SESSION access (not supported).
 * - Extends FormBase (no config saved).
 */
final class SettingsForm extends FormBase implements ContainerInjectionInterface {

  /**
   * Session key that stores enabled debug destinations per key.
   */
  private const SESSION_KEY = 'bm_debug';

  /**
   * Event name listened to by providers to register their debug keys.
   * Keep in sync with your subscriber.
   */
  private const EVENT_NAME = 'bm_debug.debug_info';

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private EventDispatcherInterface $dispatcher;

  /**
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  private SessionInterface $session;

  public function __construct(EventDispatcherInterface $dispatcher, SessionInterface $session) {
    $this->dispatcher = $dispatcher;
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('event_dispatcher'),
      $container->get('session')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'bm_debug_settings';
  }

  /**
   * Collects debug info from all modules via event subscribers.
   *
   * @return array<int,\Drupal\bm_debug\BMDebug\BMDebugInfo>|array
   *   An array of BMDebugInfo DTOs (module, key, description, default, …).
   */
  private function getDebugInfo(): array {
    $payload = [];
    $event = new DebugInfoEvent($payload);

    // Symfony 6 signature: dispatch(object $event, ?string $eventName = null).
    $this->dispatcher->dispatch($event, self::EVENT_NAME);

    return $event->getData();
  }

  /**
   * Gets the current session map of key => destination.
   *
   * @return array<string,string>
   */
  private function getSessionMap(): array {
    return (array) $this->session->get(self::SESSION_KEY, []);
  }

  /**
   * Persists the session map.
   *
   * @param array<string,string> $map
   */
  private function setSessionMap(array $map): void {
    $this->session->set(self::SESSION_KEY, $map);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $debugInfo = $this->getDebugInfo();

    $form['bm_debug_title'] = [
      '#type' => 'markup',
      '#markup' => new FormattableMarkup(
        '<h1>Debug options for all modules</h1>
         <p>Debug messages are enabled for <strong>this session</strong> when a key is set to a destination.</p>
         <p>Usage in code: <code>bm_d($key, $variable, $title, $destination)</code></p>
         <p><strong>$destination</strong> is one of: <code>dsm</code>, <code>screen</code>, <code>log</code>.</p>
         <h3>Registering keys</h3>
         <p>On first encounter of a new key, <code>bm_d()</code> dispatches the <code>@event</code> event.
            Your subscriber can enrich the passed BMDebugInfo data; it gets cached under <code>bluemarloc.BMDebug</code>.</p>',
        ['@event' => self::EVENT_NAME]
      ),
    ];

    $header = [
      'module' => $this->t('Module'),
      'key' => $this->t('Key'),
      'description' => $this->t('Description'),
      'default' => $this->t('Initial value'),
      'enabled' => $this->t('Enabled (current destination)'),
    ];

    $options = [];
    $sessionMap = $this->getSessionMap();

    foreach ($debugInfo as $info) {
      // Defensive: skip malformed items.
      if (!isset($info->key)) {
        continue;
      }
      $enabled = $sessionMap[$info->key] ?? '';
      $options[$info->key] = [
        'module' => $info->module ?? '',
        'key' => $info->key,
        'description' => $info->description ?? '',
        'default' => $info->default ?? '',
        'enabled' => $enabled,
      ];
    }

    ksort($options, SORT_NATURAL);

    // Tableselect shows a checkbox per row; we use it to choose which rows
    // to apply the chosen destination to (via the dedicated submit buttons).
    $form['bm_debug'] = [
      '#type' => 'tableselect',
      '#multiple' => TRUE,
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No debug keys registered yet. Trigger bm_d() in code to register.'),
      '#default_value' => [],
    ];

    // Action buttons set the destination for selected rows.
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['bm_debug_submit_dsm'] = [
      '#type' => 'submit',
      '#value' => $this->t('DSM'),
      '#submit' => ['::submitDestinationDsm'],
    ];
    $form['actions']['bm_debug_submit_screen'] = [
      '#type' => 'submit',
      '#value' => $this->t('SCREEN'),
      '#submit' => ['::submitDestinationScreen'],
    ];
    $form['actions']['bm_debug_submit_log'] = [
      '#type' => 'submit',
      '#value' => $this->t('LOG'),
      '#submit' => ['::submitDestinationLog'],
    ];
    $form['actions']['bm_debug_submit_clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear ALL'),
      '#submit' => ['::submitClear'],
      '#button_type' => 'danger',
    ];

    return $form;
  }

  /**
   * No-op default submit (FormBase requires the method).
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Intentionally empty; we use custom submit handlers per button above.
  }

  public function submitDestinationDsm(array &$form, FormStateInterface $form_state): void {
    $this->applyDestination($form_state, 'dsm');
  }

  public function submitDestinationScreen(array &$form, FormStateInterface $form_state): void {
    $this->applyDestination($form_state, 'screen');
  }

  public function submitDestinationLog(array &$form, FormStateInterface $form_state): void {
    $this->applyDestination($form_state, 'log');
  }

  public function submitClear(array &$form, FormStateInterface $form_state): void {
    $this->setSessionMap([]);
    $this->messenger()->addStatus($this->t('All debug keys cleared for this session.'));
    $form_state->setRebuild(TRUE);
  }

  /**
   * Applies a destination to the selected table rows.
   */
  private function applyDestination(FormStateInterface $form_state, string $destination): void {
    /** @var array<string,string|int> $selected */
    $selected = (array) $form_state->getValue('bm_debug');
    $map = $this->getSessionMap();

    foreach ($selected as $module_key => $value) {
      // tableselect returns the key when checked, "0" when not.
      if (!empty($value)) {
        $map[$module_key] = $destination;
      }
    }

    $this->setSessionMap($map);
    $this->messenger()->addStatus($this->t('Applied destination "@dest" to selected keys.', ['@dest' => $destination]));
    $form_state->setRebuild(TRUE);
  }

}
