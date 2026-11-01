<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\OrderRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        UserRepository $userRepository
    ): Response {
        return $this->render('dashboard/index.html.twig', [
            'controller_name' => 'DashboardController',
            'totalProducts' => $productRepository->count([]),
            'totalOrders' => $orderRepository->count([]),
            'totalCustomers' => $userRepository->count([]),
            'recentOrders' => $orderRepository->findBy([], ['id' => 'DESC'], 5),
        ]);
    }
}