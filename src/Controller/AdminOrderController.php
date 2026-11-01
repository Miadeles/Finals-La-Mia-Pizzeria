<?php
namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminOrderController extends AbstractController
{
    #[Route('/admin/orders', name: 'admin_orders', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $orders = $entityManager
            ->getRepository(Order::class)
            ->findBy([], ['id' => 'DESC']);

        return $this->render('admin_orders/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/admin/orders/{id}/status', name: 'admin_order_status', methods: ['POST'])]
    public function updateStatus(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('update_order_status_' . $id, $token)) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $status = (string) $request->request->get('status', '');

        $allowedStatuses = [
            'Pending',
            'Preparing',
            'Out for Delivery',
            'Delivered',
            'Cancelled',
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash('error', 'Invalid order status.');
            return $this->redirectToRoute('admin_orders');
        }

        $order->setStatus($status);
        $entityManager->flush();

        $this->addFlash(
            'success',
            sprintf('Order #%d status updated to %s.', $id, $status)
        );

        return $this->redirectToRoute('admin_orders');
    }
}
