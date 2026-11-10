<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CheckoutController extends AbstractController
{
    #[Route('/checkout', name: 'app_checkout', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->isMethod('POST')) {
            $name = trim((string) $request->request->get('name', ''));
            $phone = trim((string) $request->request->get('phone', ''));
            $address = trim((string) $request->request->get('address', ''));
            $paymentMethod = trim((string) $request->request->get('paymentMethod', ''));

            // Only Admin and Staff may create assisted or walk-in orders.
            $canCreateAssistedOrder = $this->isGranted('ROLE_ADMIN')
                || $this->isGranted('ROLE_STAFF');

            $requestedOrderType = (string) $request->request->get('orderType', 'personal');

            $orderType = in_array(
                $requestedOrderType,
                ['personal', 'staff_assisted', 'walk_in'],
                true
            ) ? $requestedOrderType : 'personal';

            if ($orderType !== 'personal' && !$canCreateAssistedOrder) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You are not allowed to create this type of order.',
                ], 403);
            }

            $isWalkIn = $orderType === 'walk_in';

            $itemsJson = $request->request->get('items', '');
            $items = json_decode($itemsJson, true);

            $accountName = trim((string) $request->request->get('paymentAccountName', ''));
            $referenceNumber = trim((string) $request->request->get('paymentReferenceNumber', ''));

            $receipt = $request->files->get('paymentReceipt');

            $allowedPaymentMethods = [
                'COD',
                'GoTyme',
                'Bank Transfer',
            ];

            if (!in_array($paymentMethod, $allowedPaymentMethods, true)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Please select a valid payment method.',
                ], 400);
            }

            if (
                $name === '' ||
                $phone === '' ||
                (!$isWalkIn && $address === '') ||
                !is_array($items) ||
                count($items) === 0
            ) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Please provide your details and at least one pizza.',
                ], 400);
            }

            $isOnlinePayment = in_array(
                $paymentMethod,
                ['GoTyme', 'Bank Transfer'],
                true
            );

            if (
                $isOnlinePayment &&
                ($accountName === '' || $referenceNumber === '')
            ) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Please provide your account name and payment reference number.',
                ], 400);
            }

            if ($isOnlinePayment && !$receipt instanceof UploadedFile) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Please upload your payment receipt.',
                ], 400);
            }

            if ($receipt instanceof UploadedFile) {
                if (!$receipt->isValid()) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'The uploaded receipt could not be received. Please try again.',
                    ], 400);
                }

                if ($receipt->getSize() > 5 * 1024 * 1024) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'The receipt must be 5 MB or smaller.',
                    ], 400);
                }

                $mimeType = $receipt->getMimeType();

                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                ];

                if (!in_array($mimeType, $allowedMimeTypes, true)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Please upload a JPG, PNG, WebP, or PDF receipt.',
                    ], 400);
                }
            }

            // Existing pizza prices remain unchanged.
            $prices = [
                'Pepperoni' => ['Small' => 399, 'Medium' => 449, 'Large' => 499],
                'Margherita' => ['Small' => 349, 'Medium' => 399, 'Large' => 449],
                'Creamy Spinach' => ['Small' => 429, 'Medium' => 479, 'Large' => 529],
                'New York Style' => ['Small' => 449, 'Medium' => 499, 'Large' => 549],
                'Hawaiian' => ['Small' => 429, 'Medium' => 479, 'Large' => 529],
                'Spinach & Glazed Bacon' => ['Small' => 459, 'Medium' => 509, 'Large' => 559],
                'Extravaganzza' => ['Small' => 499, 'Medium' => 549, 'Large' => 599],
                'Cheese Mania' => ['Small' => 449, 'Medium' => 499, 'Large' => 549],
                'American Bacon & Cheeseburger' => ['Small' => 479, 'Medium' => 529, 'Large' => 579],
            ];

            // Validate all order items before saving anything.
            $validatedItems = [];
            $total = 0;

            foreach ($items as $item) {
                if (!is_array($item)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Invalid pizza item.',
                    ], 400);
                }

                $pizzaName = trim((string) ($item['name'] ?? ''));
                $pizzaSize = trim((string) ($item['size'] ?? ''));

                if (str_ends_with($pizzaName, ' Pizza')) {
                    $pizzaName = substr($pizzaName, 0, -6);
                }

                $quantity = filter_var(
                    $item['quantity'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (
                    $pizzaName === '' ||
                    $pizzaSize === '' ||
                    $quantity === false ||
                    $quantity < 1 ||
                    $quantity > 99
                ) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'One of the pizza items is invalid.',
                    ], 400);
                }

                if (!isset($prices[$pizzaName][$pizzaSize])) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'A pizza name or size is not recognized.',
                    ], 400);
                }

                $price = $prices[$pizzaName][$pizzaSize];
                $total += $price * $quantity;

                $validatedItems[] = [
                    'name' => $pizzaName,
                    'size' => $pizzaSize,
                    'quantity' => $quantity,
                    'price' => $price,
                ];
            }

            $order = new Order();
            $order->setCustomerName($name);
            $order->setCustomerPhone($phone);
            $order->setCustomerAddress($isWalkIn ? null : $address);
            $order->setOrderType($orderType);

            // Only personal orders belong to the logged-in user's order history.
            if ($orderType === 'personal' && $this->getUser() instanceof User) {
                $order->setUser($this->getUser());
            }

            $order->setStatus('Pending');
            $order->setPaymentMethod($paymentMethod);
            $order->setPaymentStatus('Pending');

            if ($isOnlinePayment) {
                $order->setPaymentAccountName($accountName);
                $order->setPaymentReferenceNumber($referenceNumber);
            }

            $savedReceiptName = null;

            try {
                // Save the receipt using a generated filename.
                if ($isOnlinePayment && $receipt instanceof UploadedFile) {
                    $extension = match ($receipt->getMimeType()) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'application/pdf' => 'pdf',
                        default => null,
                    };

                    if ($extension === null) {
                        return new JsonResponse([
                            'success' => false,
                            'message' => 'Unsupported receipt file.',
                        ], 400);
                    }

                    $uploadDirectory = $this->getParameter('kernel.project_dir')
                        . '/public/uploads/payment_receipts';

                    if (!is_dir($uploadDirectory)) {
                        mkdir($uploadDirectory, 0755, true);
                    }

                    $savedReceiptName = bin2hex(random_bytes(16))
                        . '.' . $extension;

                    $receipt->move($uploadDirectory, $savedReceiptName);

                    $order->setPaymentReceipt($savedReceiptName);
                }

                foreach ($validatedItems as $item) {
                    $orderItem = (new OrderItem())
                        ->setPizzaName($item['name'])
                        ->setPizzaSize($item['size'])
                        ->setQuantity($item['quantity'])
                        ->setPrice($item['price']);

                    $order->addOrderItem($orderItem);
                    $entityManager->persist($orderItem);
                }

                $order->setTotal($total);

                $entityManager->persist($order);
                $entityManager->flush();

            } catch (\Throwable $exception) {
                // Remove an uploaded file if saving the order fails.
                if ($savedReceiptName !== null) {
                    $savedPath = $this->getParameter('kernel.project_dir')
                        . '/public/uploads/payment_receipts/'
                        . $savedReceiptName;

                    if (is_file($savedPath)) {
                        unlink($savedPath);
                    }
                }

                throw $exception;
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Your order has been placed successfully! Payment status: Pending.',
                'orderId' => $order->getId(),
            ]);
        }

        $user = $this->getUser();

        return $this->render('checkout/index.html.twig', [
            'user' => $user instanceof User ? $user : null,
        ]);
    }
}
