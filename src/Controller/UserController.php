<?php
namespace App\Controller;

use App\Entity\User;
use Symfony\Component\Form\FormError;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class UserController extends Controller
{
    /**
     * @Route("/", name="default")
     * @Route("/users/", name="user_list")
     */
    public function listAction(Request $request)
    {
        /** @var $em \Doctrine\ORM\EntityManager */
        $em = $this->container->get('doctrine')->getManager();
        $qb = $em->getRepository('App:User')->createQueryBuilder('u');

        $pagerfanta = new Pagerfanta(new DoctrineORMAdapter($qb));
        $pagerfanta->setMaxPerPage(10);
        $pagerfanta->setCurrentPage($request->get('page', 1));

        $roles = [
            'ROLE_ADMIN'     => 'Admin',
            'ROLE_MODERATOR' => 'Moderator',
            'ROLE_USER'      => 'User',
        ];

        return $this->render('admin/list.html.twig', [
            'pagerfanta' => $pagerfanta,
            'roles'      => $roles,
        ]);
    }

    /**
     * @Route("/users/create/", name="user_create")
     */
    public function createAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $id = $request->get('id' );
        $em = $this->container->get('doctrine')->getManager(); /** @var $em \Doctrine\ORM\EntityManager */

        if ($id) {
            $user = $em->getRepository('App:User')->find($id);
            if (!$user) {
                throw $this->createNotFoundException('Not found');
            }
        }

        $roleChoices = [
            'User'      => 'ROLE_USER',
            'Moderator' => 'ROLE_MODERATOR',
            'Admin'     => 'ROLE_ADMIN',
        ];

        $fb = $this->createFormBuilder();

        $fb->add('username', TextType::class, [
            'label'       => 'Username',
            'constraints' => new Assert\NotBlank(),
            'attr'  => ['class' => 'form-group form-control'],
        ]);
        $fb->add('email', EmailType::class, [
            'label'       => 'Email',
            'constraints'     => new Assert\NotBlank(),
            'attr'  => ['class' => 'form-group form-control'],
        ]);

        $fb->add('password', RepeatedType::class, [
            'type'            => PasswordType::class,
            'invalid_message' => 'The password fields must match.',
            'label'           => 'Password again',
            'options'         => ['attr' => ['class' => 'password-field']],
            'required'        => true,
            'first_options'   => [
                'label' => 'Password',
                'attr'  => ['class' => 'form-group form-control', 'autocomplete' => 'off'],
            ],
            'second_options'  => [
                'label' => 'Repeat password',
                'attr'  => ['class' => 'form-group form-control', 'autocomplete' => 'off'],
            ],
            'constraints'     => new Assert\NotBlank(),
        ]);
        $fb->add('role', ChoiceType::class, [
            'label'             => 'Role',
            'placeholder'       => ' - Select - ',
            'choices'           => $roleChoices,
            'constraints'       => new Assert\NotBlank(),
            'attr'  => ['class' => 'form-group form-control'],
        ]);
        $fb->add('add', SubmitType::class, [
            'label' => 'Add',
            'attr'  => ['class' => 'btn btn-success'],
        ]);

        $form = $fb->getForm();
        $form->handleRequest($request);
        if ($request->isMethod('post') and $form->isSubmitted()) {
            $email     = $form->get('email')->getData();
            $username  = $form->get('username')->getData();
            $passowrd  = $form->get('password')->getData();
            $role      = $form->get('role')->getData();

            $user = $em->getRepository('App:User')->findBy(['email' => $email]);
            if ($user) {
                $form->get('email')->addError(new FormError('This email is already in use'));
            }

            $user = $em->getRepository('App:User')->findBy(['username' => $username]);
            if ($user) {
                $form->get('username')->addError(new FormError('This username is already in use'));
            }

            if ($form->isValid()) {
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setRoles([$role]);

                $encoded_password = $encoder->encodePassword($user, $passowrd);
                $user->setPassword($encoded_password);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'The user has been added successfully');
                return $this->redirectToRoute('user_list');
            }
        }

        return $this->render('admin/item.html.twig', [
            'form'   => $form->createView(),
            'action' => 'add',
        ]);

    }

    /**
     * @Route("/users/edit-{id}/", name="user_edit")
     */
    public function editAction(Request $request, UserPasswordEncoderInterface $encoder)
    {
        $id = $request->get('id' );
        $em = $this->container->get('doctrine')->getManager(); /** @var $em \Doctrine\ORM\EntityManager */

        $user = $em->getRepository('App:User')->find($id); /** @var $user \App\Entity\User */
        if (!$user) {
            throw $this->createNotFoundException('Not found');
        }

        $main_role = $this->getUser()->getRoles()[0];
        $sub_role  = $user->getRoles()[0];

        $data = [
            'username' => $user->getUsername(),
            'email'    => $user->getEmail(),
            'role'     => $sub_role,
        ];

        $roleChoices = [
            'User'      => 'ROLE_USER',
            'Moderator' => 'ROLE_MODERATOR',
        ];

        if ($main_role == 'ROLE_ADMIN') {
            $roleChoices['Admin'] = 'ROLE_ADMIN';
        }


        $fb = $this->createFormBuilder($data);

        $fb->add('username', TextType::class, [
            'label'       => 'Username',
            'constraints' => new Assert\NotBlank(),
            'attr'  => ['class' => 'form-group form-control'],
        ]);
        $fb->add('email', EmailType::class, [
            'label'       => 'Email',
            'constraints'     => new Assert\NotBlank(),
            'attr'  => ['class' => 'form-group form-control'],
        ]);



        if ( ! ($sub_role == 'ROLE_ADMIN' and $main_role == 'ROLE_MODERATOR')) {
            $fb->add('password', RepeatedType::class, [
                'type'            => PasswordType::class,
                'invalid_message' => 'The password fields must match.',
                'label'           => 'Password again',
                'options'         => ['attr' => ['class' => 'password-field']],
                'first_options'   => ['label' => 'Password', 'attr'  => ['class' => 'form-group form-control']],
                'second_options'  => ['label' => 'Repeat password', 'attr'  => ['class' => 'form-group form-control']],
                'required' => false,
            ]);

            $fb->add('role', ChoiceType::class, [
                'label'             => 'Role',
                'placeholder'       => ' - Select - ',
                'choices'           => $roleChoices,
                'constraints'       => new Assert\NotBlank(),
                'attr'  => ['class' => 'form-group form-control'],
            ]);
        }

        $fb->add('edit', SubmitType::class, [
            'label' => 'Edit',
            'attr'  => ['class' => 'btn btn-success'],
        ]);

        $form = $fb->getForm();
        $form->handleRequest($request);
        if ($request->isMethod('post') and $form->isSubmitted()) {
            $email     = $form->get('email')->getData();
            $username  = $form->get('username')->getData();
            if ( ! ($sub_role == 'ROLE_ADMIN' and $main_role == 'ROLE_MODERATOR')) {
                $passowrd  = $form->get('password')->getData();
                $role      = $form->get('role')->getData();
            }

            $existed_user = $em->getRepository('App:User')->createQueryBuilder('u')
                ->andWhere('u.email = :email')->setParameter('email', $email)
                ->andWhere('u.id != :id')->setParameter('id', $id)
                ->getQuery()->getOneOrNullResult();

            if ($existed_user) {
                $form->get('email')->addError(new FormError('This email is already in use'));
            }

            $existed_user = $em->getRepository('App:User')->createQueryBuilder('u')
                ->andWhere('u.username = :username')->setParameter('username', $username)
                ->andWhere('u.id != :id')->setParameter('id', $id)
                ->getQuery()->getOneOrNullResult();

            if ($existed_user) {
                $form->get('username')->addError(new FormError('This username is already in use'));
            }

            if ($form->isValid()) { /** @var $user \App\Entity\User */
                $user->setUsername($username);
                $user->setEmail($email);

                if ( ! ($sub_role == 'ROLE_ADMIN' and $main_role == 'ROLE_MODERATOR')) {
                    $user->setRoles([$role]);
                    if ($passowrd) {
                        $encoded_password = $encoder->encodePassword($user, $passowrd);
                        $user->setPassword($encoded_password);
                    }
                }


                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'The user has been edited successfully');
                return $this->redirectToRoute('user_list');
            }
        }

        return $this->render('admin/item.html.twig', [
            'form'   => $form->createView(),
            'action' => 'edit',
        ]);
    }

    /**
     * @Route("/users/delete-{id}/", name="user_delete")
     */
    public function deleteAction(Request $request)
    {
        $id = $request->get('id' );
        $em = $this->container->get('doctrine')->getManager(); /** @var $em \Doctrine\ORM\EntityManager */

        if ($id) {
            $user = $em->getRepository('App:User')->find($id);
            if (!$user) {
                throw $this->createNotFoundException('Not found');
            }

            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'The user has been deleted successfully');
        }
        return $this->redirect($request->headers->get('referer'));
    }

    /**
     * @Route("/users/get_excel/", name="user_get_excel")
     */
    public function getExcelAction()
    {
        $filepath = "/tmp/users.xlsx";
        $filename = "users.xlsx";

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $em    = $this->container->get('doctrine')->getManager(); /** @var $em \Doctrine\ORM\EntityManager */
        $users = $em->getRepository('App:User')->findAll();
        $col   = [];
        $row   = 0;

        foreach ($users as $user) { /** @var $user \App\Entity\User */
            $row++;
            $col['A'] = $user->getUsername();
            $col['B'] = $user->getEmail();
            $roles    =  $user->getRoles();
            $col['C'] = implode(',', $roles);

            foreach ($col as $c => $value) {
                $coordinate = $c.$row;
                $sheet->setCellValue($coordinate, $value);
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);

        $response = new Response();
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, $filename);
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('Content-Type', 'text/vnd.ms-excel; charset=utf-8');
        $response->headers->set('Pragma', 'public');
        $response->headers->set('Cache-Control', 'maxage=1');
        $response->setContent(file_get_contents($filepath));

        return $response;
    }
}
