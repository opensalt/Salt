<?php

namespace App\Console\User;

use App\Command\User\AddUserByNameCommand;
use App\Console\BaseDoctrineCommand;
use App\Entity\User\Organization;
use App\Entity\User\User;
use App\Event\CommandEvent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

#[AsCommand('salt:user:add', 'Add a local user')]
class UserAddCommand extends BaseDoctrineCommand
{
    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::REQUIRED, 'Email address or username of the new user')
            ->addArgument('org', InputArgument::REQUIRED, 'Organization name for the new user')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Initial password for the new user')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Role to give the new user (editor, admin, super-user)')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $em = $this->em;
        if (empty($input->getArgument('org'))) {
            $orgObjs = $em->getRepository(Organization::class)->findAll();
            $orgs = [];
            foreach ($orgObjs as $org) {
                $orgs[] = $org->getName();
            }

            $question = new Question('Organization name for the new user: ');
            $question->setAutocompleterValues($orgs);
            $question->setValidator(function ($value) use ($em) {
                if ('' === trim($value)) {
                    throw new \Exception('The organization name must exist');
                }

                $org = $em->getRepository(Organization::class)->findOneByName($value);
                if (null === $org) {
                    throw new \Exception('The organization name must exist');
                }

                return $value;
            });
            $org = $helper->ask($input, $output, $question);
            $input->setArgument('org', $org);
        }

        if (empty($input->getArgument('username'))) {
            $question = new Question('Email address or username of new user: ');
            $question->setValidator(function ($value) {
                if ('' === trim($value)) {
                    throw new \Exception('The username can not be empty');
                }

                return $value;
            });
            $username = $helper->ask($input, $output, $question);
            $input->setArgument('username', $username);
        }

        if (empty($input->getOption('password'))) {
            $question = new Question('Initial password for new user: ');
            $question->setValidator(function ($value) {
                if ('' === trim($value)) {
                    throw new \Exception('The password can not be empty');
                }

                return $value;
            });
            $password = $helper->ask($input, $output, $question);
            $input->setOption('password', $password);
        }

        if (empty($input->getOption('role'))) {
            $roleList = [];
            foreach (User::getUserRoles() as $role) {
                $roleList[] = strtolower(preg_replace('/[^A-Z]/', ' ', str_replace('ROLE_', '', $role)));
            }
            $question = new ChoiceQuestion('Role to give the new user: ', $roleList, 0);
            $role = $helper->ask($input, $output, $question);
            $input->setOption('role', $role);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = trim($input->getArgument('username'));
        $org = trim($input->getArgument('org'));
        $password = trim($input->getOption('password'));
        $role = trim($input->getOption('role'));
        if (empty($role)) {
            $role = 'user';
        }
        $role = 'ROLE_'.preg_replace('/[^A-Z]/', '_', strtoupper($role));

        if (!in_array($role, User::USER_ROLES)) {
            $output->writeln(sprintf('<error>Role "%s" is not valid.</error>', $input->getOption('role')));

            return (int) Command::FAILURE;
        }

        $em = $this->em;
        $orgObj = $em->getRepository(Organization::class)->findOneByName($org);
        if (empty($orgObj)) {
            $output->writeln(sprintf('<error>Organization "%s" is not valid.</error>', $org));

            return (int) Command::FAILURE;
        }

        $command = new AddUserByNameCommand($username, $orgObj, $password, $role);
        $this->dispatcher->dispatch(new CommandEvent($command), CommandEvent::class);
        $newPassword = $command->getNewPassword();

        if (empty($password)) {
            $output->writeln(sprintf('The user "%s" has been added with password "%s".', $username, $newPassword));
        } else {
            $output->writeln(sprintf('The user "%s" has been added.', $username));
        }

        return (int) Command::SUCCESS;
    }
}
