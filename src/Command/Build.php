<?php

namespace PixelPolishers\ResolverBuilder\Command;

use Composer\Semver\VersionParser;
use Enrise\Uri;
use Exception;
use PixelPolishers\ResolverBuilder\Builder\Html as HtmlBuilder;
use PixelPolishers\ResolverBuilder\Builder\Package as PackageBuilder;
use PixelPolishers\ResolverBuilder\Driver\DriverInterface;
use PixelPolishers\ResolverBuilder\Driver\Github;
use PixelPolishers\ResolverBuilder\Package\Release;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Build extends Command
{
    /**
     * @var VersionParser
     */
    private $versionParser;

    protected function configure()
    {
        $this->setName('build');
        $this->setDescription('Builds the packages.json and a web inferface.');
        $this->addOption('no-web', null, InputOption::VALUE_NONE, 'Disabled the building of the web interface.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->readConfiguration($input, $output);
        if (!$config) {
            return 1;
        }

        $this->versionParser = new VersionParser();
        $packages = [];

        $output->writeln('Scanning packages');
        foreach ($config['packages'] as $package) {
            $driver = $this->getDriver($package, $config);

            $package = $this->scanPackage($output, $driver);
            if ($package) {
                $packages[] = $package;
            }
        }

        $output->writeln('Building resolver-packages.json');

        $packageBuilder = new PackageBuilder($packages);
        $packageBuilder->build($config['output']['target']);

        if ($input->getOption('no-web')) {
            return;
        }

        $output->writeln('Building html output');

        $webBuilder = new HtmlBuilder($packages, $config);
        $webBuilder->build($config['output']['target']);
    }

    private function readConfiguration(InputInterface $input, OutputInterface $output)
    {
        $path = getcwd() . DIRECTORY_SEPARATOR . 'resolver-builder.json';
        if (!is_file($path) || !is_readable($path)) {
            $output->writeln(sprintf('<error>Missing configuration file "%s".</error>', $path));
            return null;
        }

        $data = file_get_contents($path);
        $config = json_decode($data, true);

        if (!$config) {
            $output->writeln(sprintf('<error>The file "%s" contains errors.</error>', $path));
            return null;
        }

        if (!array_key_exists('output', $config)) {
            $output->writeln('<error>Missing "output" configuration option.</error>');
            return null;
        }

        if (!array_key_exists('target', $config['output'])) {
            $output->writeln('<error>Missing "target" configuration option in output configuration.</error>');
            return null;
        }

        $homepage = new Uri($config['homepage']);
        if (!$homepage->isAbsolute() || $homepage->isSchemeless()) {
            $output->writeln(sprintf('<error>The homepage "%s" is invalid.</error>', $config['homepage']));
            return null;
        }

        $outputTarget = new Uri($config['output']['target']);
        if ($outputTarget->isRelative()) {
            $config['output']['target'] = getcwd() . '/' . ltrim($config['output']['target'], '/');
        }

        return $config;
    }

    private function getDriver($package, $config)
    {
        switch ($package['type']) {
            case 'github':
                $oauthToken = isset($config['sync_auth']['github']) ? $config['sync_auth']['github'] : null;
                $driver = new Github($package['url'], $oauthToken);
                break;

            default:
                throw new RuntimeException(sprintf('The package type "%s" is not supported.', $package['type']));
        }

        return $driver;
    }

    private function scanPackage(OutputInterface $output, DriverInterface $driver)
    {
        $releases = [];
        $releases = array_merge($releases, $this->scanPackageTags($output, $driver));
        $releases = array_merge($releases, $this->scanPackageBranches($output, $driver));

        if (!$releases) {
            return null;
        }

        return [
            'name' => $driver->getPackageName(),
            'url' => $driver->getUrl(),
            'ssh_url' => $driver->getSshUrl(),
            'releases' => $releases,
        ];
    }

    private function scanPackageTags(OutputInterface $output, DriverInterface $driver)
    {
        $releases = [];

        foreach ($driver->getTags() as $tag) {
            $output->writeln(sprintf(
                'Reading resolver.json of <info>%s</info> (<comment>%s</comment>)',
                $driver->getPackageName() ?: $driver->getUrl(),
                $tag['name']
            ));

            // Strip off the release- prefix from tags if present:
            $tag['name'] = str_replace('release-', '', $tag['name']);

            $normalizedVersion = $this->validateTag($tag['name']);
            if (!$normalizedVersion) {
                $output->writeln('<warning>Skipped tag ' . $tag . ', invalid tag name</warning>');
                continue;
            }

            try {
                $information = $driver->getResolverInformation($tag['name']);
                if (!$information) {
                    $output->writeln('<warning>Skipped tag ' . $tag['name'] . ', no resolver.json</warning>');
                    continue;
                }
            } catch (Exception $e) {
                $output->writeln('<warning>Skipped tag ' . $tag['name'] . ', no resolver.json</warning>');
                continue;
            }

            $commit = $driver->getCommit($tag['commit']['sha']);

            $releases[] = [
                'name' => $tag['name'],
                'commit_url' => $commit['html_url'],
                'author' => $commit['author'],
                'committer' => $commit['committer'],
                'message' => $commit['message'],
                'dist' => $driver->getDistInformation($tag['commit']['sha']),
                'source' => $driver->getSourceInformation($tag['commit']['sha']),
            ];
        }

        return $releases;
    }

    private function scanPackageBranches(OutputInterface $output, DriverInterface $driver)
    {
        $releases = [];

        foreach ($driver->getBranches() as $branch) {
            $output->writeln(sprintf(
                'Reading resolver.json of <info>%s</info> (<comment>%s</comment>)',
                $driver->getPackageName() ?: $driver->getUrl(),
                $branch['name']
            ));

            $normalizedVersion = $this->validateBranch($branch['name']);
            if (!$normalizedVersion) {
                $output->writeln('<warning>Skipped branch ' . $branch['name'] . ', invalid branch name</warning>');
                continue;
            }

            try {
                $information = $driver->getResolverInformation($branch['name']);
                if (!$information) {
                    $output->writeln('<warning>Skipped branch ' . $branch['name'] . ', no resolver.json</warning>');
                    continue;
                }
            } catch (Exception $e) {
                $output->writeln('<warning>Skipped branch ' . $branch['name'] . ', no resolver.json</warning>');
                continue;
            }

            $commit = $driver->getCommit($branch['commit']['sha']);

            $releases[] = [
                'name' => 'dev-' . $branch['name'],
                'reference' => $branch['commit']['sha'],
                'commit_url' => $commit['html_url'],
                'author' => $commit['author'],
                'committer' => $commit['committer'],
                'message' => $commit['message'],
                'dist' => $driver->getDistInformation($branch['commit']['sha']),
                'source' => $driver->getSourceInformation($branch['commit']['sha']),
            ];
        }

        return $releases;
    }

    private function validateBranch($branch)
    {
        try {
            return $this->versionParser->normalizeBranch($branch);
        } catch (Exception $e) {
        }
        return false;
    }

    private function validateTag($version)
    {
        try {
            return $this->versionParser->normalize($version);
        } catch (Exception $e) {
        }
        return false;
    }
}
