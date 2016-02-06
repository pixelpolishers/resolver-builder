<?php

namespace PixelPolishers\ResolverBuilder\Builder;

use PixelPolishers\ResolverBuilder\Utils\FileSystem;

class Package implements BuilderInterface
{
    private $packages;
    private $repositories;

    public function __construct(array $packages)
    {
        $this->packages = $packages;
    }

    public function build($outputPath)
    {
        $outputPath = rtrim($outputPath) . '/resolver-packages.json';

        $directory = dirname($outputPath);
        FileSystem::ensureDirectory($directory);

        $packages = [];

        foreach ($this->packages as $package) {
            $name = $package['name'];
            $packages[$name] = [];

            foreach ($package['releases'] as $release) {
                $packages[$name][$release['name']] = [
                    'time' => $release['committer']['date'],
                    'source' => $release['source'],
                ];
            }
        }

        $data = [
            'packages' => $packages,
        ];

        file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
