<?php

namespace PixelPolishers\ResolverBuilder\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
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
        $this->read($helper, $input, $output, 'Output directory', $data['output'], 'target', false, 'public/');
        $this->read($helper, $input, $output, 'Output template', $data['output'], 'template', false, 'satis');

        $data['sync_auth'] = [];
        $data['packages'] = [];

        $hasGithubKey = $this->askQuestion($helper, $input, $output, 'Do you have a GitHub OAuth key?');
        if ($hasGithubKey) {
            $this->read($helper, $input, $output, 'Please enter your GitHub key', $data['sync_auth'], 'github', true);
        }

        $path = getcwd() . DIRECTORY_SEPARATOR . 'resolver-builder.json';

        if (!is_file($path) || $this->askForPermission($helper, $input, $output, $path)) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        return 0;
    }

    private function askQuestion($helper, $input, $output, $label)
    {
        $question = new ConfirmationQuestion(
            $label . ' (y/n) ',
            false,
            '/^y/i'
        );

        return $helper->ask($input, $output, $question);
    }

    private function askForPermission($helper, $input, $output, $path)
    {
        return $this->askQuestion($helper, $input, $output, 'Are you sure you want to overwrite "' . $path . '"?');
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
