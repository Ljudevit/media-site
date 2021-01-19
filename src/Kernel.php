<?php

declare(strict_types=1);

namespace App;

use App\DependencyInjection\AppExtension;
use App\DependencyInjection\CompilerPass;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use function preg_match;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    private const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new CompilerPass\XslRegisterPass());
    }

    public function registerBundles(): iterable
    {
        $contents = require $this->getProjectDir() . '/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if ($envs[$this->environment] ?? $envs['all'] ?? false) {
                yield new $class();
            }
        }
    }

    // can be removed when bumping to Symfony 5.2
    public function getCacheDir()
    {
        if (isset($_SERVER['APP_CACHE_DIR'])) {
            return $_SERVER['APP_CACHE_DIR'] . '/' . $this->environment;
        }

        return parent::getCacheDir();
    }

    // can be removed when bumping to Symfony 5.2
    public function getLogDir()
    {
        return $_SERVER['APP_LOG_DIR'] ?? parent::getLogDir();
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->addResource(new FileResource($this->getProjectDir() . '/config/bundles.php'));
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir() . '/config';

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');

        $loader->load($confDir . '/app/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/app/{services}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/app/{services}' . self::CONFIG_EXTS, 'glob');

        $serverEnvironment = $_SERVER['SERVER_ENVIRONMENT'];
        if (preg_match('/^\w+$/', $serverEnvironment) !== 1) {
            throw new RuntimeException('Server environment contains an invalid format. Valid format contains only alpha-numeric characters and an underscore.');
        }

        $loader->load($confDir . '/app/server/' . $serverEnvironment . '.yaml');

        if ($this->environment === 'dev' && $container->getParameter('profiler_storage') === 'redis') {
            $loader->load($confDir . '/packages/profiler_storage/redis' . self::CONFIG_EXTS, 'glob');
        }

        $container->registerExtension(new AppExtension());
        $container->loadFromExtension('app');
    }

    protected function configureRoutes(RouteCollectionBuilder $routes): void
    {
        $confDir = $this->getProjectDir() . '/config';

        $routes->import($confDir . '/{routes}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}' . self::CONFIG_EXTS, '/', 'glob');

        $routes->import($confDir . '/app/{routes}/*' . self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/app/{routes}' . self::CONFIG_EXTS, '/', 'glob');
    }
}
