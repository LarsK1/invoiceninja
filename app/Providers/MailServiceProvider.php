<?php

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
    }

    public function boot()
    {
        $this->registerIlluminateMailer();

    }

    protected function registerIlluminateMailer()
    {
        $this->app->singleton('mail.manager', function($app) {
            return new GmailTransportManager($app);
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });

        $this->app['mail.manager']->extend('postmark', function () {
            
            return new PostmarkTransport(
                $this->guzzle(config('postmark.guzzle', [])),
                config('postmark.secret')
            );

        });
        
        $this->app->afterResolving('mail.manager', function (GmailTransportManager $mailManager) {
            $mailManager->getSwiftMailer()->registerPlugin($this->app->make(CssInlinerPlugin::class));
            return $mailManager;
        });
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
