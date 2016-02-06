<?php

namespace PixelPolishers\ResolverBuilder\Builder;

use Enrise\Uri;
use PixelPolishers\ResolverBuilder\Utils\FileSystem;
use Twig_Environment;
use Twig_Loader_Filesystem;

class Html implements BuilderInterface
{
    private $packages;
    private $config;

    public function __construct(array $packages, $config)
    {
        $this->packages = $packages;
        $this->config = $config;
    }

    public function build($outputPath)
    {
        FileSystem::ensureDirectory($outputPath);

        $templateUri = new Uri($this->config['output']['template']);
        if ($templateUri->isRelative()) {
            $possibleAbsoluteTemplate = getcwd() . '/' . ltrim($this->config['output']['template'], '/');

            if (is_dir($possibleAbsoluteTemplate)) {
                $this->config['output']['template'] = $possibleAbsoluteTemplate;
            } else {
                $this->config['output']['template'] = sprintf(
                    '%s/../../resources/templates/%s',
                    __DIR__,
                    $this->config['output']['template']
                );
            }
        }

        $twig = new Twig_Environment(new Twig_Loader_Filesystem($this->config['output']['template']));

        $content = $twig->render('index.html.twig', [
            'name' => $this->config['name'],
            'description' => $this->config['description'],
            'url' => $this->config['homepage'],
            'packages' => $this->packages,
        ]);

        file_put_contents($outputPath . '/index.html', $content);
    }
}
