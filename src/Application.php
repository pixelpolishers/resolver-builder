<?php

namespace PixelPolishers\ResolverBuilder;

use PixelPolishers\ResolverBuilder\Command\Build;
use PixelPolishers\ResolverBuilder\Command\Create;
use PixelPolishers\ResolverBuilder\Command\SelfUpdate;
use PixelPolishers\ResolverBuilder\Command\Validate;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Application extends BaseApplication
{
    const VERSION = '@package_version@';

    public function __construct()
    {
        parent::__construct('ResolverBuilder', self::VERSION);
    }

    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow', null));

        if (extension_loaded('xdebug') && !getenv('RESOLVER_DISABLE_XDEBUG_WARN')) {
            $output->writeln(sprintf(
                '<question>%s</question>',
                'You are running resolver with xdebug enabled. This has a major impact on runtime performance.'
            ));
            $output->writeln('');
        }

        if ($this->checkNewerVersion()) {
            $output->writeln(sprintf(
                '<error>%s</error>',
                'A new version of Resolver is available, run `self-update` to update to the latest version.'
            ));
            $output->writeln('');
        }

        $oldWorkingDir = getcwd();
        $newWorkingDir = $this->getNewWorkingDir($input);

        if ($newWorkingDir) {
            chdir($newWorkingDir);
        }

        $result = parent::doRun($input, $output);

        chdir($oldWorkingDir);

        return $result;
    }

    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new Build();
        $commands[] = new Create();
        if (PHP_VERSION_ID >= 50600 && self::VERSION !== '@' . 'package_version' . '@') {
            $commands[] = new SelfUpdate();
        }
        $commands[] = new Validate();

        return $commands;
    }

    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();
        $definition->addOption(new InputOption(
            '--working-dir',
            '-w',
            InputOption::VALUE_REQUIRED,
            'If specified, use the given directory as working directory.'
        ));

        return $definition;
    }

    private function getNewWorkingDir(InputInterface $input)
    {
        $workingDir = $input->getParameterOption(array('--working-dir', '-w'));

        if (false !== $workingDir && !is_dir($workingDir)) {
            throw new \RuntimeException('Invalid working directory specified, ' . $workingDir . ' does not exist.');
        }

        return $workingDir;
    }

    private function checkNewerVersion()
    {
        // Splitted up the version string because we don't want it to be parsed when building :)
        if (PHP_VERSION_ID < 50600 || self::VERSION === '@' . 'package_version' . '@') {
            return false;
        }

        $content = humbug_get_contents('https://pixelpolishers.github.com/resolver/resolver-builder.phar.version');

        return substr($content, 0, 40) !== self::VERSION;
    }
}
