<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 20)]
class LocaleListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Get locale from session if available
        $locale = $request->getSession()->get('_locale');

        if ($locale && in_array($locale, ['en', 'ru'])) {
            $request->setLocale($locale);
        }
    }
}
