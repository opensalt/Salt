<?php

namespace App\Console\User;

use App\Command\User\SetUserPasswordCommand;
use App\Console\BaseDispatchingCommand;
use App\Event\CommandEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand('salt:user:set-password', 'Set the password for a local user')]
class UserSetPasswordCommand extends BaseDispatchingCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Email address or username of the user to change')
            ->addArgument('password', InputArgument::OPTIONAL, 'New password for the user')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (empty($input->getArgument('username'))) {
            $question = new Question('Email address or username of new user: ');
            $question->setValidator(function (string $value): string {
                if ('' === trim($value)) {
                    throw new \Exception('The username can not be empty');
                }

                return $value;
            });
            $username = $helper->ask($input, $output, $question);
            $input->setArgument('username', $username);
        }

        if (empty($input->getArgument('password'))) {
            $question = new Question('New password for the user (leave empty to generate one): ');
            $password = $helper->ask($input, $output, $question);
            $input->setArgument('password', $password);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim($input->getArgument('username'));
        $password = $input->getArgument('password');
        if (null !== $password) {
            $password = trim($password);
        }

        $command = new SetUserPasswordCommand($username, $password);
        $this->dispatcher->dispatch(new CommandEvent($command), CommandEvent::class);
        $newPassword = $command->getPlainPassword();

        if (empty($password)) {
            $output->writeln(sprintf('The password for "%s" has been set to "%s".', $input->getArgument('username'), $newPassword));
        } else {
            $output->writeln(sprintf('The password for "%s" has been set.', $input->getArgument('username')));
        }

        return (int) Command::SUCCESS;
    }
}
