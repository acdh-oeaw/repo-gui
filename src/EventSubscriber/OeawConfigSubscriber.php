<?php

namespace Drupal\oeaw\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\Core\Url;

class OeawConfigSubscriber implements EventSubscriberInterface
{
    public function initArcheCfg(GetResponseEvent $event)
    {
        global $archeCfg;
        $archeCfg = \acdhOeaw\util\RepoConfig::init($_SERVER["DOCUMENT_ROOT"].'/modules/custom/oeaw/config.ini');
    }
    
    /**
    * Listen to kernel.request events and call customRedirection.
    * {@inheritdoc}
    * @return array Event names to listen to (key) and methods to call (value)
    */
    public static function getSubscribedEvents()
    {
        $events[KernelEvents::REQUEST][] = array('initArcheCfg');
        return $events;
    }
}
