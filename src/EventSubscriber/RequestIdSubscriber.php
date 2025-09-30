<?php
// web/modules/custom/bm_debug/src/EventSubscriber/RequestIdSubscriber.php
declare(strict_types=1);

namespace Drupal\bm_debug\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class RequestIdSubscriber implements EventSubscriberInterface {

    public static function getSubscribedEvents(): array {
        // Generate early; attach to every response.
        return [
            KernelEvents::REQUEST => ['onRequest', 300],
            KernelEvents::RESPONSE => ['onResponse', -300],
        ];
    }

    public function onRequest(RequestEvent $event): void {
        $request = $event->getRequest();
        $rid = $request->headers->get('X-Request-Id');
        if (!$rid) {
            $rid = self::uuidV4();
            $request->headers->set('X-Request-Id', $rid);
        }
        $request->attributes->set('bm_request_id', $rid);
    }

    public function onResponse(ResponseEvent $event): void {
        $request = $event->getRequest();
        $rid = $request->attributes->get('bm_request_id');
        if ($rid) {
            $event->getResponse()->headers->set('X-Request-Id', $rid);
        }
    }

    private static function uuidV4(): string {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($h, 4));
    }

}
