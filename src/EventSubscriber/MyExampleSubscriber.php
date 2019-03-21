<?php

namespace Drupal\oeaw\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use EasyRdf\RdfNamespace;

class MyExampleSubscriber implements EventSubscriberInterface
{
    /**
     * @param GetResponseEvent $event
     */

    public function checkForRedirection(GetResponseEvent $event)
    {
        if ($event->getRequest()->getPathInfo() == '/oeaw_newresource_one') {
            error_log("oeaw_newresource_one");
            \EasyRdf\RdfNamespace::set("dct", "http://purl.org/dc/terms/");
        }
        if ($event->getRequest()->getPathInfo() == '/oeaw_multi_new_resource') {
            error_log("oeaw_multi_new_resource");
            \EasyRdf\RdfNamespace::set("dct", "http://purl.org/dc/terms/");
        }
    }

    /**
    * {@inheritdoc}
    */

    public static function getSubscribedEvents()
    {
        //$events[KernelEvents::REQUEST][] = array('checkForRedirection');
        //return $events;
    }
}
