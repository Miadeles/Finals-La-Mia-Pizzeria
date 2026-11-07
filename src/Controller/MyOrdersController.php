<?php

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MyOrdersController extends AbstractController
{
    #[Route('/my/orders', name: 'app_my_orders')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $orders = $entityManager->getRepository(Order::class)->findBy(
            ['user' => $this->getUser()],
            ['id' => 'DESC']
        );

        return $this->render('my_orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }
}
