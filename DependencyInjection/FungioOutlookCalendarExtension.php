<?php
namespace Fungio\OutlookCalendarBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class FungioOutlookCalendarExtension
 * @package Fungio\OutlookCalendarBundle\DependencyInjection
 *
 * @author Pierrick AUBIN <pierrick.aubin@siqual.fr>
 */
class FungioOutlookCalendarExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        if ($container->hasDefinition('fungio.outlook_calendar')) {
            $definition = $container->getDefinition('fungio.outlook_calendar');
            if (isset($config['outlook_calendar']['client_id'])) {
                $definition
                    ->addMethodCall('setClientId', [$config['outlook_calendar']['client_id']]);
            }
            if (isset($config['outlook_calendar']['client_secret'])) {
                $definition
                    ->addMethodCall('setClientSecret', [$config['outlook_calendar']['client_secret']]);
            }
        }

    }
}