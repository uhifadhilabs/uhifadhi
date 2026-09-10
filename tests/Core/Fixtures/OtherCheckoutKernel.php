<?php

declare(strict_types=1);

/*
 * This file is part of the Uhifadhi core.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Uhifadhi\Core\Tests\Core\Fixtures;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Uhifadhi\Core\Tests\Application\CheckoutTempDirTrait;

/**
 * A SECOND CHECKOUT, STOOD UP IN A TEMPORARY DIRECTORY.
 *
 * It answers the throwaway application's own cache and log suffixes on purpose:
 * the only thing that separates its compiled container from that one's is the
 * project directory, which is the whole claim under test.
 */
final class OtherCheckoutKernel extends BaseKernel
{
    use CheckoutTempDirTrait;

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct('test', true);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
    }

    public function getProjectDir(): string
    {
        return $this->projectDir;
    }

    public function getCacheDir(): string
    {
        return $this->checkoutTempDir('application/cache/'.$this->environment);
    }

    public function getLogDir(): string
    {
        return $this->checkoutTempDir('application/log');
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret' => 'test',
                'http_method_override' => false,
                'php_errors' => ['log' => true],
            ]);
        });
    }
}
