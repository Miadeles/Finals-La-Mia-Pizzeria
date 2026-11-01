<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminCustomersController extends AbstractController
{
    #[Route('/admin/customers', name: 'admin_customers', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $customers = $userRepository->createQueryBuilder('u')
            ->where('u.roles NOT LIKE :admin')
            ->setParameter('admin', '%ROLE_ADMIN%')
            ->orderBy('u.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin_customers/index.html.twig', [
            'customers' => $customers,
        ]);
    }
}