<?php

namespace App\Controller;

use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminOrderController extends AbstractController
{
    /**
     * Allow Admin and Staff to manage orders.
     */
    private function denyUnlessOrderManager(): void
    {
        if (
            !$this->isGranted('ROLE_ADMIN')
            && !$this->isGranted('ROLE_STAFF')
        ) {
            throw $this->createAccessDeniedException(
                'You do not have permission to manage orders.'
            );
        }
    }

    #[Route('/admin/orders', name: 'admin_orders', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessOrderManager();

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
        $this->denyUnlessOrderManager();

        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('update_order_status_' . $id, $token)) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $status = (string) $request->request->get('status', '');

        // Walk-in orders have an in-store pickup workflow.
        if ($order->getOrderType() === 'walk_in') {
            $allowedStatuses = [
                'Pending',
                'Preparing',
                'Ready for Pickup',
                'Picked Up',
                'Cancelled',
            ];
        } else {
            // Preserve the existing delivery workflow.
            $allowedStatuses = [
                'Pending',
                'Preparing',
                'Out for Delivery',
                'Delivered',
                'Cancelled',
            ];
        }

        if (!in_array($status, $allowedStatuses, true)) {
            $this->addFlash(
                'error',
                'Invalid status for this order type.'
            );

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

    #[Route('/admin/orders/{id}/payment-status', name: 'admin_order_payment_status', methods: ['POST'])]
    public function updatePaymentStatus(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyUnlessOrderManager();

        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('update_payment_status_' . $id, $token)) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        $paymentStatus = (string) $request->request->get('paymentStatus', '');

        if (!in_array($paymentStatus, ['Paid', 'Rejected'], true)) {
            $this->addFlash('error', 'Invalid payment status.');

            return $this->redirectToRoute('admin_orders');
        }

        if ($order->getPaymentMethod() === 'COD' && $paymentStatus !== 'Paid') {
            $this->addFlash(
                'error',
                'Cash orders can only be marked as Paid.'
            );

            return $this->redirectToRoute('admin_orders');
        }

        $order->setPaymentStatus($paymentStatus);
        $entityManager->flush();

        $this->addFlash(
            'success',
            sprintf('Payment for Order #%d marked as %s.', $id, $paymentStatus)
        );

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/admin/orders/{id}/undo-payment', name: 'admin_order_undo_payment', methods: ['POST'])]
    public function undoPayment(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyUnlessOrderManager();

        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        $token = (string) $request->request->get('_token', '');

        if (!$this->isCsrfTokenValid('undo_payment_' . $id, $token)) {
            throw $this->createAccessDeniedException('Invalid security token.');
        }

        if ($order->getPaymentStatus() !== 'Paid') {
            $this->addFlash(
                'error',
                'Only paid orders can be returned to Pending.'
            );

            return $this->redirectToRoute('admin_orders');
        }

        $order->setPaymentStatus('Pending');
        $entityManager->flush();

        $this->addFlash(
            'success',
            sprintf(
                'Payment for Order #%d has been returned to Pending.',
                $id
            )
        );

        return $this->redirectToRoute('admin_orders');
    }

    #[Route('/admin/orders/{id}/payment-receipt', name: 'admin_order_payment_receipt', methods: ['GET'])]
    public function viewPaymentReceipt(
        int $id,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyUnlessOrderManager();

        $order = $entityManager->getRepository(Order::class)->find($id);

        if (!$order || !$order->getPaymentReceipt()) {
            throw $this->createNotFoundException(
                'Payment receipt not found.'
            );
        }

        $filename = basename($order->getPaymentReceipt());

        $receiptPath = $this->getParameter('kernel.project_dir')
            . '/public/uploads/payment_receipts/'
            . $filename;

        if (!is_file($receiptPath)) {
            throw $this->createNotFoundException(
                'Receipt file does not exist.'
            );
        }

        return new BinaryFileResponse($receiptPath);
    }
}
