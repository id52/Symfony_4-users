<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{
    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        $roles = ['admin' => 'ROLE_ADMIN', 'moderator' => 'ROLE_MODERATOR', 'user' => 'ROLE_USER'];

        foreach ($roles as $name => $role) {
            for ($i=0; $i<=9; $i++) {
                $username  = $name.$i;
                $email     = $username.'@'.$username.'.'.$username;

                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setRoles([$role]);

                $password = $this->encoder->encodePassword($user, $username);
                $user->setPassword($password);

                $manager->persist($user);
                $manager->flush();
            }
        }
    }
}