<?php

declare(strict_types=1);

namespace EonX\EasySecurity\Bridge\Symfony\DataCollector;

use EonX\EasyApiToken\Interfaces\EasyApiTokenInterface;
use EonX\EasySecurity\Authorization\AuthorizationMatrixFactory;
use EonX\EasySecurity\Authorization\SymfonyCacheAuthorizationMatrixFactory;
use EonX\EasySecurity\Interfaces\Authorization\AuthorizationMatrixFactoryInterface;
use EonX\EasySecurity\Interfaces\Authorization\AuthorizationMatrixInterface;
use EonX\EasySecurity\Interfaces\ProviderInterface;
use EonX\EasySecurity\Interfaces\SecurityContextInterface;
use EonX\EasySecurity\Interfaces\SecurityContextResolverInterface;
use EonX\EasySecurity\Interfaces\UserInterface;
use EonX\EasySecurity\SecurityContextResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class SecurityContextDataCollector extends DataCollector
{
    /**
     * @var string
     */
    public const NAME = 'easy_security.security_context_collector';

    /**
     * @var \EonX\EasySecurity\Interfaces\Authorization\AuthorizationMatrixFactoryInterface
     */
    private $authorizationMatrixFactory;

    /**
     * @var \EonX\EasySecurity\Interfaces\SecurityContextInterface
     */
    private $securityContext;

    /**
     * @var \EonX\EasySecurity\Interfaces\SecurityContextResolverInterface
     */
    private $securityContextResolver;

    public function __construct(
        AuthorizationMatrixFactoryInterface $authorizationMatrixFactory,
        SecurityContextResolverInterface $securityContextResolver,
        SecurityContextInterface $securityContext
    ) {
        $this->authorizationMatrixFactory = $authorizationMatrixFactory;
        $this->securityContextResolver = $securityContextResolver;
        $this->securityContext = $securityContext;
    }

    public function collect(Request $request, Response $response, ?\Throwable $throwable = null): void
    {
        $this->data['authorization_matrix'] = $this->securityContext->getAuthorizationMatrix();
        $this->data['permissions'] = $this->securityContext->getPermissions();
        $this->data['roles'] = $this->securityContext->getRoles();
        $this->data['provider'] = $this->securityContext->getProvider();
        $this->data['user'] = $this->securityContext->getUser();
        $this->data['token'] = $this->securityContext->getToken();

        $this->setContextConfigurators();
        $this->setRolesPermissionsProviders();
    }

    public function getAuthorizationMatrix(): AuthorizationMatrixInterface
    {
        return $this->data['authorization_matrix'];
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getPermissions(): array
    {
        return $this->data['permissions'] ?? [];
    }

    public function getPermissionsProviders(): array
    {
        return $this->data['permissions_providers'];
    }

    public function getProvider(): ?ProviderInterface
    {
        return $this->data['provider'] ?? null;
    }

    public function getRoles(): array
    {
        return $this->data['roles'] ?? [];
    }

    public function getRolesProviders(): array
    {
        return $this->data['roles_providers'];
    }

    /**
     * @return mixed[]
     */
    public function getSecurityContextConfigurators(): array
    {
        return $this->data['context_configurators'] ?? [];
    }

    public function getToken(): ?EasyApiTokenInterface
    {
        return $this->data['token'] ?? null;
    }

    public function getUser(): ?UserInterface
    {
        return $this->data['user'] ?? null;
    }

    public function reset(): void
    {
        $this->data = [];
    }

    private function setContextConfigurators(): void
    {
        $this->data['context_configurators'] = [];

        if ($this->securityContextResolver instanceof SecurityContextResolver === false) {
            return;
        }

        foreach ($this->securityContextResolver->getContextConfigurators() as $contextConfigurator) {
            $reflection = new \ReflectionClass($contextConfigurator);

            $this->data['context_configurators'][] = [
                'class' => $reflection->getName(),
                'filename' => $reflection->getFileName(),
                'priority' => $contextConfigurator->getPriority(),
            ];
        }
    }

    private function setRolesPermissionsProviders(): void
    {
        $this->data['roles_providers'] = [];
        $this->data['permissions_providers'] = [];

        $factory = $this->authorizationMatrixFactory instanceof SymfonyCacheAuthorizationMatrixFactory
            ? $this->authorizationMatrixFactory->getDecorated()
            : $this->authorizationMatrixFactory;

        if ($factory instanceof AuthorizationMatrixFactory === false) {
            return;
        }

        foreach ($factory->getRolesProviders() as $rolesProvider) {
            $reflection = new \ReflectionClass($rolesProvider);

            $this->data['roles_providers'][] = [
                'class' => $reflection->getName(),
                'filename' => $reflection->getFileName(),
                'roles' => $rolesProvider->getRoles(),
            ];
        }

        foreach ($factory->getPermissionsProviders() as $permissionsProvider) {
            $reflection = new \ReflectionClass($permissionsProvider);

            $this->data['permissions_providers'][] = [
                'class' => $reflection->getName(),
                'filename' => $reflection->getFileName(),
                'permissions' => $permissionsProvider->getPermissions(),
            ];
        }
    }
}