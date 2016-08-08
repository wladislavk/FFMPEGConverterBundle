<?php
namespace VKR\FFMPEGConverterBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('vkr_ffmpeg_converter');
        /** @noinspection PhpUndefinedMethodInspection */
        $rootNode
            ->children()
                ->arrayNode('video')
                    ->children()
                        ->scalarNode('extension')->end()
                        ->scalarNode('input')->end()
                        ->scalarNode('output')->end()
                    ->end()
                ->end()
                ->arrayNode('audio')
                    ->children()
                        ->scalarNode('extension')->end()
                        ->scalarNode('input')->end()
                        ->scalarNode('output')->end()
                    ->end()
                ->end()
                ->arrayNode('image')
                    ->children()
                        ->scalarNode('extension')->end()
                        ->scalarNode('input')->end()
                        ->scalarNode('output')->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
