<?php

namespace Rafal\ProfilerServiceProvider;

use Silex\SilexEvents;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Rafal\ProfilerServiceProvider\Twig\ProfilerExtension;
use Symfony\Component\HttpFoundation\Cookie;

class ProfilerServiceProvider implements ServiceProviderInterface {

    public function register(Application $app)
    {
        if (!isset($app['profiler.data_url'])) {
            $app['profiler.data_url'] = '/__fetch_profiler_data';
        }
        if (!isset($app['profiler.cookie_name'])) {
            $app['profiler.cookie_name'] = 'webprofiler';
        }

        $app->get($app['profiler.data_url'], function() use($app) {
            $data = unserialize(urldecode($app['request']->cookies->get($app['profiler.cookie_name'])));
            return $app['twig']->render('__profiler-data.twig', array(
                'data' => $data
            ));
        });

        $app['dispatcher']->addListener(SilexEvents::AFTER, function($event) use($app) {
            $data = array();
            $request = $event->getRequest();
            $response = $event->getResponse();
            $data['request'] = array(
                'method'            => $request->getMethod(),
                'uri'               => $request->getRequestUri(),
                'format'            => $request->getRequestFormat(),
                'accept'            => $request->headers->get('accept'),
                'accept-charset'    => $request->headers->get('accept-charset'),
                'accept-encoding'   => $request->headers->get('accept-encoding'),
                'accept-language'   => $request->headers->get('accept-language'),
                'connection'        => $request->headers->get('connection'),
                'host'              => $request->headers->get('host'),
                'user-agent'        => $request->headers->get('user-agent'),
            );
            $data['response'] = array(
                'code'          => $response->getStatusCode(),
                'cache-control' => $response->headers->get('cache-control')
            );
            $event->getResponse()->headers->setCookie(new Cookie($app['profiler.cookie_name'], urlencode(serialize($data))));
        });

        $oldTwig = $app->raw('twig');
        $app['twig'] = $app->share(function($c) use ($oldTwig, $app) {
            $twig = $oldTwig($c);
            $twig->addExtension(new ProfilerExtension($twig));

            return $twig;
        });

        $oldLoader = $app->raw('twig.loader.filesystem');
        $app['twig.loader.filesystem'] = $app->share(function($c) use ($oldLoader, $app) {
            $loader = $oldLoader($c);
            $loader->addPath(__DIR__.'/Resources/views');

            return $loader;
        });
    }
}