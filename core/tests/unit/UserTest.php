<?php

use App\Entity\User\User;
use App\Entity\User\Organization;

class UserTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    // tests
    public function testAddUser()
    {
        /* @var EntityManagerInterface $em */
        $em = $this->getModule('Doctrine')->em;

        /* @var User $user */
        $user = new User();

        $org = $em->getRepository(Organization::class)->find(1);

        $user->setUsername('usertest');
        $user->setPassword('passwordaB3');
        $user->setOrg($org);

        $em->persist($user);
        $em->flush();

        $this->tester->seeInRepository(User::class, ['username' => 'usertest']);
        $em->clear();

        $user = $em->getRepository(User::class)->findOneBy(['username' => 'usertest']);
        $this->assertEquals($user->isPending(), false);
        $this->assertEquals($user->isSuspended(), false);
        $this->assertEquals($user->isAccountNonLocked(), true);
    }
}
