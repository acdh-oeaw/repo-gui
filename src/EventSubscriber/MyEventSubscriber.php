<?php

namespace Drupal\oeaw\EventSubscriber;

use Drupal\User\Entity\User;
use Drupal\Core\DrupalKernel;
// This is the interface we are going to implement.
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
// This class contains the event we want to subscribe to.
use Symfony\Component\HttpKernel\KernelEvents;
// Our event listener method will receive one of these.
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
// We'll use this to perform a redirect if necessary.
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Routing\TrustedRedirectResponse;

class MyEventSubscriber implements EventSubscriberInterface
{
    
    /**
     * Check the shibboleth user logins
     *
     * @global type $user
     * @param GetResponseEvent $event
     * @return TrustedRedirectResponse
     */
    public function checkForShibboleth(GetResponseEvent $event)
    {
        if (($event->getRequest()->getPathInfo() == '/user/logout') /*&&  (\Drupal::currentUser()->getUsername() == "shibboleth") */) {
            unset($_SERVER['HTTP_AUTHORIZATION']);
            unset($_SERVER['HTTP_EPPN']);
            $_SERVER['HTTP_AUTHORIZATION'] = "";
            $_SERVER['HTTP_EPPN'] = "";
            foreach (headers_list() as $header) {
                header_remove($header);
            }
            $host = \Drupal::request()->getSchemeAndHttpHost();
            $userid = \Drupal::currentUser()->id();
            \Drupal::service('session_manager')->delete($userid);            
            //return new TrustedRedirectResponse($host."/Shibboleth.sso/Logout?return=".$host."/browser/discover/root");
            $event->setResponse(new TrustedRedirectResponse($host."/Shibboleth.sso/Logout?return=".$host."/browser/"));
        }
       
        if ($event->getRequest()->getPathInfo() == '/federated_login') {
            global $user;
            //the actual user id, if the user is logged in
            $userid = \Drupal::currentUser()->id();
            //if it is a shibboleth login and there is no user logged in
            if (isset($_SERVER['HTTP_EPPN']) 
                    && $_SERVER['HTTP_EPPN'] != "(null)"
                    && $userid == 0 
                    && \Drupal::currentUser()->isAnonymous()) {
                
                 //the global drupal shibboleth username
                $shib = user_load_by_name('shibboleth');
                //if we dont have it then we will create it
                if ($shib === false) {
                    $sh = $this->createShibbolethUser();
                    $shib = user_load_by_name('shibboleth');
                }
                if ($shib->id() != 0) {
                    $user = \Drupal\User\Entity\User::load($shib->id());
                    $user->activate();
                    user_login_finalize($user);
                    $host = \Drupal::request()->getSchemeAndHttpHost();
                    return new TrustedRedirectResponse($host."/browser/");
                }
            }
        }
    }


    /**
     * This is the event handler main method
     *
     * @return string
     */
    public static function getSubscribedEvents()
    {
        $events = [];
        $events[KernelEvents::REQUEST][] = array('checkForShibboleth', 300);
        return $events;
    }
    
    /**
     * create the shibb. user inside the drupal DB
     *
     * @return type
     */
    private function createShibbolethUser()
    {
        $user = \Drupal\user\Entity\User::create();
        // Mandatory.
        $user->setPassword('ShiBBoLeth');
        $user->enforceIsNew();
        $user->setEmail('sh_guest@acdh.oeaw.ac.at');
        $user->setUsername('shibboleth');
        $user->activate();
        $result = $user->save();
        return $result;
    }
}
