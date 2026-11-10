<?php

namespace App\Controller;

use App\Dto\AdminUserEditData;
use App\Entity\User;
use App\Form\AdminUserCreateType;
use App\Form\AdminUserEditType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_users', methods: ['GET'])]
    public function index(
        EntityManagerInterface $entityManager
    ): Response {
        $users = $entityManager
            ->getRepository(User::class)
            ->findBy([], ['id' => 'DESC']);

        return $this->render('admin_user/index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = new User();

        $form = $this->createForm(AdminUserCreateType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = trim((string) $user->getEmail());

            $existingUser = $entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if ($existingUser !== null) {
                $form->get('email')->addError(
                    new FormError(
                        'An account with this email already exists.'
                    )
                );
            } else {
                $user->setEmail($email);

                $user->setRoles([
                    $form->get('accountRole')->getData(),
                ]);

                $user->setIsActive(true);

                $plainPassword = $form
                    ->get('plainPassword')
                    ->getData();

                $user->setPassword(
                    $passwordHasher->hashPassword(
                        $user,
                        $plainPassword
                    )
                );

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Account created successfully!'
                );

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin_user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Keep form input separate from the database user.
        $editUser = new AdminUserEditData();

        $editUser->firstName = $user->getFirstName();
        $editUser->lastName = $user->getLastName();
        $editUser->email = $user->getEmail();

        // Determine the current account role.
        $currentRole = 'ROLE_USER';

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $currentRole = 'ROLE_ADMIN';
        } elseif (in_array('ROLE_STAFF', $user->getRoles(), true)) {
            $currentRole = 'ROLE_STAFF';
        }

        $form = $this->createForm(AdminUserEditType::class, $editUser, [
            'current_role' => $currentRole,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $selectedRole = $form->get('accountRole')->getData();
            $email = trim((string) $editUser->email);

            // Prevent an Admin from removing their own Admin role.
            if (
                $this->getUser() instanceof User
                && $this->getUser()->getId() === $user->getId()
                && $selectedRole !== 'ROLE_ADMIN'
            ) {
                $form->get('accountRole')->addError(
                    new FormError(
                        'You cannot remove your own Admin role.'
                    )
                );
            }

            // Check for duplicate email addresses.
            if (
                $email !== ''
                && $form->get('email')->isValid()
            ) {
                $existingUser = $entityManager
                    ->getRepository(User::class)
                    ->findOneBy(['email' => $email]);

                if (
                    $existingUser !== null
                    && $existingUser->getId() !== $user->getId()
                ) {
                    $form->get('email')->addError(
                        new FormError(
                            'Another account already uses this email address.'
                        )
                    );
                }
            }

            // Save only if all checks pass.
            if ($form->isValid()) {
                $user->setFirstName(
                    trim((string) $editUser->firstName)
                );

                $user->setLastName(
                    trim((string) $editUser->lastName)
                );

                $user->setEmail($email);
                $user->setRoles([$selectedRole]);

                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Account updated successfully!'
                );

                return $this->redirectToRoute('admin_users');
            }
        }

        return $this->render('admin_user/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    // ENABLE / DISABLE USER ACCOUNT
    #[Route(
        '/{id}/toggle-status',
        name: 'admin_users_toggle_status',
        methods: ['POST']
    )]
    public function toggleStatus(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Protect this action against CSRF attacks.
        if (!$this->isCsrfTokenValid(
            'toggle_status_' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid security token.'
            );
        }

        // Prevent an Admin from disabling their own account.
        if (
            $this->getUser() instanceof User
            && $this->getUser()->getId() === $user->getId()
            && $user->isActive()
        ) {
            $this->addFlash(
                'error',
                'You cannot disable your own Admin account.'
            );

            return $this->redirectToRoute('admin_users');
        }

        // Switch between active and inactive.
        $newStatus = !$user->isActive();

        $user->setIsActive($newStatus);

        $entityManager->flush();

        if ($newStatus) {
            $this->addFlash(
                'success',
                'Account enabled successfully!'
            );
        } else {
            $this->addFlash(
                'success',
                'Account disabled successfully!'
            );
        }

        return $this->redirectToRoute('admin_users');
    }


    // DELETE USER ACCOUNT
    #[Route(
        '/{id}/delete',
        name: 'admin_users_delete',
        methods: ['POST']
    )]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // Validate CSRF token.
        if (!$this->isCsrfTokenValid(
            'delete_user_' . $user->getId(),
            (string) $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException(
                'Invalid security token.'
            );
        }

        // Prevent an Admin from deleting their own account.
        if (
            $this->getUser() instanceof User
            && $this->getUser()->getId() === $user->getId()
        ) {
            $this->addFlash(
                'error',
                'You cannot delete your own Admin account.'
            );

            return $this->redirectToRoute('admin_users');
        }

        // Protect accounts with existing orders.
        $orderCount = $entityManager
            ->getRepository(\App\Entity\Order::class)
            ->count(['user' => $user]);

        if ($orderCount > 0) {
            $this->addFlash(
                'error',
                'This account cannot be deleted because it has order history. You can disable the account instead.'
            );

            return $this->redirectToRoute('admin_users');
        }

        // Delete the account.
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash(
            'success',
            'User account deleted successfully!'
        );

        return $this->redirectToRoute('admin_users');
    }


}
