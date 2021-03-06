<?php

declare(strict_types=1);

use EonX\EasyApiToken\Interfaces\ApiTokenDecoderInterface;
use EonX\EasyApiToken\Interfaces\Factories\ApiTokenDecoderFactoryInterface;
use EonX\EasyEventDispatcher\Interfaces\EventDispatcherInterface;
use EonX\EasySecurity\Authorization\AuthorizationMatrixFactory;
use EonX\EasySecurity\Authorization\CachedAuthorizationMatrixFactory;
use EonX\EasySecurity\Bridge\BridgeConstantsInterface;
use EonX\EasySecurity\Bridge\Symfony\DataCollector\SecurityContextDataCollector;
use EonX\EasySecurity\Bridge\Symfony\Factories\AuthenticationFailureResponseFactory;
use EonX\EasySecurity\Bridge\Symfony\Interfaces\AuthenticationFailureResponseFactoryInterface;
use EonX\EasySecurity\Bridge\Symfony\Listeners\ConfigureSecurityContextListener;
use EonX\EasySecurity\Bridge\Symfony\Security\ContextAuthenticator;
use EonX\EasySecurity\DeferredSecurityContextProvider;
use EonX\EasySecurity\Interfaces\Authorization\AuthorizationMatrixFactoryInterface;
use EonX\EasySecurity\Interfaces\Authorization\AuthorizationMatrixInterface;
use EonX\EasySecurity\Interfaces\DeferredSecurityContextProviderInterface;
use EonX\EasySecurity\Interfaces\SecurityContextFactoryInterface;
use EonX\EasySecurity\SecurityContextFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\ref;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure();

    // ApiTokenDecoder
    $services
        ->set(BridgeConstantsInterface::SERVICE_API_TOKEN_DECODER, ApiTokenDecoderInterface::class)
        ->factory([ref(ApiTokenDecoderFactoryInterface::class), 'build'])
        ->args(['%' . BridgeConstantsInterface::PARAM_TOKEN_DECODER . '%']);

    // Authorization
    $services->set(BridgeConstantsInterface::SERVICE_AUTHORIZATION_MATRIX_CACHE, ArrayAdapter::class);

    $services
        ->set('easy_security.core_authorization_matrix_factory', AuthorizationMatrixFactory::class)
        ->arg('$rolesProviders', tagged_iterator(BridgeConstantsInterface::TAG_ROLES_PROVIDER))
        ->arg('$permissionsProviders', tagged_iterator(BridgeConstantsInterface::TAG_PERMISSIONS_PROVIDER));

    $services
        ->set(AuthorizationMatrixFactoryInterface::class, CachedAuthorizationMatrixFactory::class)
        ->arg('$cache', ref(BridgeConstantsInterface::SERVICE_AUTHORIZATION_MATRIX_CACHE))
        ->arg('$decorated', ref('easy_security.core_authorization_matrix_factory'));

    $services
        ->set(AuthorizationMatrixInterface::class)
        ->factory([ref(AuthorizationMatrixFactoryInterface::class), 'create']);

    // DataCollector
    $services
        ->set(SecurityContextDataCollector::class)
        ->arg('$configurators', tagged_iterator(BridgeConstantsInterface::TAG_CONTEXT_CONFIGURATOR))
        ->tag('data_collector', [
            'template' => '@EasySecurity/Collector/security_context_collector.html.twig',
            'id' => SecurityContextDataCollector::NAME,
        ]);

    // Deferred Security Provider
    $services
        ->set(DeferredSecurityContextProviderInterface::class, DeferredSecurityContextProvider::class)
        ->arg('$contextServiceId', '%' . BridgeConstantsInterface::PARAM_CONTEXT_SERVICE_ID . '%');

    // Listeners
    $services
        ->set(ConfigureSecurityContextListener::class)
        ->arg('$configurators', tagged_iterator(BridgeConstantsInterface::TAG_CONTEXT_CONFIGURATOR))
        ->tag('kernel.event_listener');

    // SecurityContextFactory
    $services
        ->set(SecurityContextFactoryInterface::class, SecurityContextFactory::class)
        ->arg('$eventDispatcher', ref(EventDispatcherInterface::class));

    // Symfony Security
    $services->set(AuthenticationFailureResponseFactoryInterface::class, AuthenticationFailureResponseFactory::class);
    $services->set(ContextAuthenticator::class);
};
