<?php

declare(strict_types=1);

namespace EonX\EasyErrorHandler\Bridge\Laravel\Provider;

use Bugsnag\Client;
use EonX\EasyBugsnag\Bridge\BridgeConstantsInterface as EasyBugsnagConstantsInterface;
use EonX\EasyErrorHandler\Bridge\BridgeConstantsInterface;
use EonX\EasyErrorHandler\Bridge\Bugsnag\BugsnagReporterProvider;
use EonX\EasyErrorHandler\Bridge\Bugsnag\ErrorDetailsClientConfigurator;
use EonX\EasyErrorHandler\Bridge\Bugsnag\SeverityClientConfigurator;
use EonX\EasyErrorHandler\Bridge\Laravel\ExceptionHandler;
use EonX\EasyErrorHandler\Bridge\Laravel\Translator;
use EonX\EasyErrorHandler\Builders\DefaultBuilderProvider;
use EonX\EasyErrorHandler\ErrorDetailsResolver;
use EonX\EasyErrorHandler\ErrorHandler;
use EonX\EasyErrorHandler\Interfaces\ErrorDetailsResolverInterface;
use EonX\EasyErrorHandler\Interfaces\ErrorHandlerInterface;
use EonX\EasyErrorHandler\Interfaces\ErrorResponseFactoryInterface;
use EonX\EasyErrorHandler\Interfaces\TranslatorInterface;
use EonX\EasyErrorHandler\Reporters\DefaultReporterProvider;
use EonX\EasyErrorHandler\Response\ErrorResponseFactory;
use Illuminate\Contracts\Debug\ExceptionHandler as IlluminateExceptionHandlerInterface;
use Illuminate\Support\ServiceProvider;

final class EasyErrorHandlerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../translations', BridgeConstantsInterface::TRANSLATION_NAMESPACE);

        $this->publishes([
            __DIR__ . '/../config/easy-error-handler.php' => \base_path('config/easy-error-handler.php'),
        ]);
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/easy-error-handler.php', 'easy-error-handler');

        $this->app->singleton(ErrorDetailsResolverInterface::class, ErrorDetailsResolver::class);

        $this->app->singleton(ErrorResponseFactoryInterface::class, ErrorResponseFactory::class);

        $this->app->singleton(ErrorHandlerInterface::class, function (): ErrorHandlerInterface {
            return new ErrorHandler(
                $this->app->make(ErrorResponseFactoryInterface::class),
                $this->app->tagged(BridgeConstantsInterface::TAG_ERROR_RESPONSE_BUILDER_PROVIDER),
                $this->app->tagged(BridgeConstantsInterface::TAG_ERROR_REPORTER_PROVIDER),
                (bool)\config('easy-error-handler.use_extended_response', false)
            );
        });

        $this->app->singleton(
            IlluminateExceptionHandlerInterface::class,
            function (): IlluminateExceptionHandlerInterface {
                return new ExceptionHandler(
                    $this->app->make(ErrorHandlerInterface::class),
                    $this->app->make(TranslatorInterface::class)
                );
            }
        );

        $this->app->singleton(TranslatorInterface::class, function (): TranslatorInterface {
            return new Translator($this->app->make('translator'));
        });

        if ((bool)\config('easy-error-handler.use_default_builders', true)) {
            $this->app->singleton(DefaultBuilderProvider::class, function (): DefaultBuilderProvider {
                return new DefaultBuilderProvider(
                    $this->app->make(ErrorDetailsResolverInterface::class),
                    $this->app->make(TranslatorInterface::class),
                    \config('easy-error-handler.response')
                );
            });
            $this->app->tag(
                DefaultBuilderProvider::class,
                [BridgeConstantsInterface::TAG_ERROR_RESPONSE_BUILDER_PROVIDER]
            );
        }

        if ((bool)\config('easy-error-handler.use_default_reporters', true)) {
            $this->app->singleton(DefaultReporterProvider::class);
            $this->app->tag(DefaultReporterProvider::class, [BridgeConstantsInterface::TAG_ERROR_REPORTER_PROVIDER]);
        }

        if ((bool)\config('easy-error-handler.bugsnag_enabled', true) && \class_exists(Client::class)) {
            $this->app->singleton(BugsnagReporterProvider::class, function (): BugsnagReporterProvider {
                return new BugsnagReporterProvider(
                    $this->app->make(Client::class),
                    \config('easy-error-handler.bugsnag_threshold'),
                    \config('easy-error-handler.bugsnag_ignored_exceptions')
                );
            });
            $this->app->tag(BugsnagReporterProvider::class, [BridgeConstantsInterface::TAG_ERROR_REPORTER_PROVIDER]);

            $this->app->singleton(ErrorDetailsClientConfigurator::class);
            $this->app->singleton(SeverityClientConfigurator::class);
            $this->app->tag(
                [ErrorDetailsClientConfigurator::class, SeverityClientConfigurator::class],
                [EasyBugsnagConstantsInterface::TAG_CLIENT_CONFIGURATOR]
            );
        }
    }
}
