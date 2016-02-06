<?php

namespace PixelPolishers\ResolverBuilder\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Create extends Command
{
    protected function configure()
    {
        $this->setName('create');
        $this->setDescription('Creates a new resolver-builder.json file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $data = [];

        $this->read($helper, $input, $output, 'Name', $data, 'name', true);
        $this->read($helper, $input, $output, 'Description', $data, 'description', false);
        $this->read($helper, $input, $output, 'Homepage', $data, 'homepage', true);
        $this->read($helper, $input, $output, 'Package target', $data, 'package_target', false, 'public/');
        $this->read($helper, $input, $output, 'Web target', $data, 'web_target', false, 'public/');
        $this->read($helper, $input, $output, 'Web template', $data, 'web_template', true, 'satis');

        $data['auth'] = [];
        $data['packages'] = [];

        $path = getcwd() . DIRECTORY_SEPARATOR . 'resolver-builder.json';

        if (!is_file($path) || $this->askForPermission($helper, $input, $output, $path)) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }

    private function askForPermission($helper, $input, $output, $path)
    {
        $question = new ConfirmationQuestion(
            'Are you sure you want to overwrite "' . $path . '"? (Y/n) ',
            false,
            '/^Y/'
        );

        return $helper->ask($input, $output, $question);
    }

    private function read($helper, $input, $output, $label, &$data, $key, $required, $default = null)
    {
        if ($default) {
            $label .= ' (' . $default . '): ';
        } else {
            $label .= ': ';
        }

        $question = new Question($label, $default);

        do {
            $value = $helper->ask($input, $output, $question);
        } while ($required && !$value);

        if ($value) {
            $data[$key] = $value;
        }
    }
}
