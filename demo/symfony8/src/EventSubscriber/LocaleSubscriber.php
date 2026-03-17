<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

use function in_array;

/**
 * Sets the request locale from the session (_locale) so that language switching persists.
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly array $allowedLocales,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 200]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        // Priority: route (URL) locale > session
        $locale = $request->attributes->get('_locale');
        if ($locale !== null && in_array($locale, $this->allowedLocales, true)) {
            $request->setLocale($locale);
            if ($request->hasSession()) {
                $request->getSession()->set('_locale', $locale);
            }

            return;
        }
        if ($request->hasSession()) {
            $locale = $request->getSession()->get('_locale');
            if ($locale !== null && in_array($locale, $this->allowedLocales, true)) {
                $request->setLocale($locale);
            }
        }
    }
}
