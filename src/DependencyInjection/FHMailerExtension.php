<?php
declare(strict_types=1);

namespace FH\Bundle\MailerBundle\DependencyInjection;

use FH\Bundle\MailerBundle\Composer\ComposerIdentifiers;
use FH\Bundle\MailerBundle\Composer\TemplatedEmailComposer;
use FH\Bundle\MailerBundle\Email\MessageOptions;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class FHMailerExtension extends ConfigurableExtension
{
    /**
     * @throws \Exception
     */
    public function loadInternal(array $configs, ContainerBuilder $container): void
    {
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config')))
            ->load('message_composer.yaml');

        foreach ($configs[ComposerIdentifiers::TEMPLATED_EMAIL] as $name => $messageOptions) {
            $composerId = $this->createComposerId($name, ComposerIdentifiers::TEMPLATED_EMAIL);

            $this->registerTemplatedEmailComposer($container, $composerId, $messageOptions);
        }
    }

    /**
     * @param ContainerInterface $container
     * @param string $composerId
     * @param string[] $messageOptions
     */
    private function registerTemplatedEmailComposer(
        ContainerInterface $container,
        string $composerId,
        array $messageOptions
    ): void {
        $this->registerComposer($container, $messageOptions, TemplatedEmailComposer::class, $composerId);
    }

    /**
     * @param ContainerInterface $container
     * @param string[] $messageOptions
     * @param string $composerClass
     * @param string $composerId
     * @param string|null $chainedComposerId
     */
    private function registerComposer(
        ContainerInterface $container,
        array $messageOptions,
        string $composerClass,
        string $composerId,
        string $chainedComposerId = null
    ): void {
        $optionsId = $composerId . '._message_options';
        $container->setDefinition($optionsId, $this->createMessageOptionsDefinition($messageOptions));

        $composerDefinition = new ChildDefinition($composerClass);
        $composerDefinition->setArgument('$messageOptions', new Reference($optionsId));

        if (is_string($chainedComposerId)) {
            $composerDefinition->setArgument('$composer', new Reference($chainedComposerId));
        }

        $container->setDefinition($composerId, $composerDefinition);
    }

    private function createMessageOptionsDefinition(array $options): Definition
    {
        $definition = new Definition(MessageOptions::class, [$options]);
        $definition->setFactory([MessageOptions::class, 'fromArray']);

        return $definition;
    }

    private function createComposerId(string $composerId, string $composer): string
    {
        return "fh_mailer.composer.$composer.$composerId";
    }
}
