<?php
namespace Fungio\OutlookCalendarBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * Class FungioPutlookCalendarExtension
 * @package Fungio\OutlookCalendarBundle\DependencyInjection
 *
 * @author Pierrick AUBIN <pierrick.aubin@siqual.fr>
 */
class FungioPutlookCalendarExtension extends Extension
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
            if (isset($config['outlook_calendar']['application_name'])) {
                $definition
                    ->addMethodCall('setApplicationName', [$config['outlook_calendar']['application_name']]);
            }
            if (isset($config['outlook_calendar']['credentials_path'])) {
                $definition
                    ->addMethodCall('setCredentialsPath', [$config['outlook_calendar']['credentials_path']]);
            }
            if (isset($config['outlook_calendar']['client_secret_path'])) {
                $definition
                    ->addMethodCall('setClientSecretPath', [$config['outlook_calendar']['client_secret_path']]);
            }
        }

    }
}