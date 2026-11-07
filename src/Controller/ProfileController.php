<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($request->isMethod('POST')) {
            $formType = (string) $request->request->get('formType', 'profile');

            // CHANGE PASSWORD
            if ($formType === 'change_password') {
                $token = (string) $request->request->get('_token', '');

                if (!$this->isCsrfTokenValid('change_password', $token)) {
                    throw $this->createAccessDeniedException('Invalid security token.');
                }

                $currentPassword = (string) $request->request->get('currentPassword', '');
                $newPassword = (string) $request->request->get('newPassword', '');
                $confirmPassword = (string) $request->request->get('confirmPassword', '');

                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('password_error', 'Your current password is incorrect.');

                    return $this->redirect(
                        $this->generateUrl('app_profile') . '#change-password',
                        Response::HTTP_SEE_OTHER
                    );
                }

                if (strlen($newPassword) < 8) {
                    $this->addFlash('password_error', 'Your new password must be at least 8 characters long.');

                    return $this->redirect(
                        $this->generateUrl('app_profile') . '#change-password',
                        Response::HTTP_SEE_OTHER
                    );
                }

                if ($newPassword !== $confirmPassword) {
                    $this->addFlash('password_error', 'The new passwords do not match.');

                    return $this->redirect(
                        $this->generateUrl('app_profile') . '#change-password',
                        Response::HTTP_SEE_OTHER
                    );
                }

                if ($passwordHasher->isPasswordValid($user, $newPassword)) {
                    $this->addFlash('password_error', 'Your new password must be different from your current password.');

                    return $this->redirect(
                        $this->generateUrl('app_profile') . '#change-password',
                        Response::HTTP_SEE_OTHER
                    );
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);

                $user->setPassword($hashedPassword);
                $entityManager->flush();

                $this->addFlash('password_success', 'Your password has been changed successfully.');

                return $this->redirect(
                    $this->generateUrl('app_profile') . '#change-password',
                    Response::HTTP_SEE_OTHER
                );
            }

            // UPDATE PROFILE
            else {
                $token = (string) $request->request->get('_token', '');

                if (!$this->isCsrfTokenValid('edit_profile', $token)) {
                    throw $this->createAccessDeniedException('Invalid security token.');
                }

                $firstName = trim((string) $request->request->get('firstName', ''));
                $lastName = trim((string) $request->request->get('lastName', ''));
                $email = trim((string) $request->request->get('email', ''));
                $phoneNumber = trim((string) $request->request->get('phoneNumber', ''));
                $deliveryAddress = trim((string) $request->request->get('deliveryAddress', ''));

                if (
                    $firstName === ''
                    || $lastName === ''
                    || !filter_var($email, FILTER_VALIDATE_EMAIL)
                ) {
                    $this->addFlash('error', 'Please enter a valid name and email address.');
                } elseif (strlen($phoneNumber) > 30) {
                    $this->addFlash('error', 'Phone number must not exceed 30 characters.');
                } else {
                    $existingUser = $entityManager->getRepository(User::class)
                        ->findOneBy(['email' => $email]);

                    if ($existingUser !== null && $existingUser->getId() !== $user->getId()) {
                        $this->addFlash('error', 'That email address is already in use.');
                    } else {
                        $user->setFirstName($firstName);
                        $user->setLastName($lastName);
                        $user->setEmail($email);
                        $user->setPhoneNumber($phoneNumber !== '' ? $phoneNumber : null);
                        $user->setDeliveryAddress($deliveryAddress !== '' ? $deliveryAddress : null);

                        $entityManager->flush();

                        $this->addFlash('success', 'Your profile has been updated.');

                        return $this->redirectToRoute('app_profile');
                    }
                }
            }
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }
}
