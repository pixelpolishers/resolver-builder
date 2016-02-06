<?php

namespace PixelPolishers\ResolverBuilder\Builder;

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
        $directory = dirname($outputPath);
        FileSystem::ensureDirectory($directory);

        if (!is_dir($this->config['web_template'])) {
            $this->config['web_template'] = __DIR__ . '/../../resources/templates/' . $this->config['web_template'];
        }

        $twig = new Twig_Environment(new Twig_Loader_Filesystem($this->config['web_template']));

        $content = $twig->render('index.html.twig', [
            'name' => $this->config['name'],
            'description' => $this->config['description'],
            'url' => $this->config['homepage'],
            'packages' => $this->packages,
        ]);

        file_put_contents($outputPath . '/index.html', $content);
    }
}
