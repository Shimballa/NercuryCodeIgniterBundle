<?php

/*
 * Copyright 2012 Nerijus Arlauskas <nercury@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Nercury\CodeIgniterBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;

/**
 * Listens to kernel request event and gets response from CI in case
 * a controller matches URL.
 *
 * @author nercury
 */
class CiRequestListenerService
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var CiControllerChecker
     */
    private $controllerChecker;

    /**
     * @var bool
     */
    private $detectControllers;

    public function __construct(ContainerInterface $container, CiControllerChecker $controllerChecker, $detectControllers)
    {
        $this->container = $container;
        $this->controllerChecker = $controllerChecker;
        $this->detectControllers = $detectControllers;
    }

    /**
     * This method listens to symfony request, and if it's url matches some controller
     * defined in CI path, it redirects request handling to CI.
     *
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $resolverEvent = new CiActionResolveEvent($event->getRequest());
        if ($this->detectControllers !== false) {
            $this->container->get('event_dispatcher')->dispatch('nercury.ci_action_resolve', $resolverEvent);
        }
        $actions = $resolverEvent->getResolvedActions();

        foreach ($actions as $action) {
            if ($this->controllerChecker->isControllerExist($action['controller'])) {
                // handle everything over CI
                $event->getRequest()->setLocale($action['locale']);
                // add debug information
                $event->getRequest()->attributes->set(
                    '_route',
                    sprintf('CI[%s::%s]', $action['controller'], $action['method'])
                );
                $event->setResponse($this->container->get('ci')->getResponse($event->getRequest()));
                $event->stopPropagation();
                break;
            }
        }
    }
}
