<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Providers;

use App\Helpers\Mail\GmailTransportManager;
use App\Utils\CssInlinerPlugin;
use Coconuts\Mail\PostmarkTransport;
use GuzzleHttp\Client as HttpClient;
use Illuminate\Mail\MailServiceProvider as MailProvider;
use Illuminate\Mail\TransportManager;

class MailServiceProvider extends MailProvider
{

    public function register()
    {
        $this->registerIlluminateMailer();
    }

    public function boot()
    {
        
    }

    protected function registerIlluminateMailer()
    {

        $this->app->singleton('mail.manager', function($app) {
            $manager = new GmailTransportManager($app);
            // $manager->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
            return $manager;
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app['mail.manager']->extend('cocopostmark', function () {

            return new PostmarkTransport(
                $this->guzzle(config('postmark.guzzle', [])),
                config('postmark.secret')
            );

        });
    
        $this->app->extend('mail.manager', function(GmailTransportManager $manager) {

            $manager->extend('cocopostmark', function() {

                return new PostmarkTransport(
                $this->guzzle(config('postmark.guzzle', [])),
                config('postmark.secret')
                );

                // $manager->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
            });

            return $manager;
        
        });

        app('mail.manager')->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));

        // $this->app->afterResolving('mail.manager', function (GmailTransportManager $mailManager) {
        //     $mailManager->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
        //     return $mailManager;
        // });

    }
    
    protected function guzzle(array $config): HttpClient
    {
        return new HttpClient(array_merge($config, [
                'base_uri' => empty($config['base_uri'])
                    ? 'https://api.postmarkapp.com'
                    : $config['base_uri']
            ]));
    }

    public function provides()
    {
        return [
            'mail.manager',
            'mailer'        
        ];
    }
}
