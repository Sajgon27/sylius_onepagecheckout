<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Handlers;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Order\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Symfony\Component\Form\FormInterface;

final class CheckoutSaveFormHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrderProcessorInterface $orderProcessor,
    ) {
    }

    public function handle(FormInterface $form): ?OrderInterface
    {
        if (!$form->isSubmitted() || !$form->isValid()) {
            return null;
        }

        /** @var OrderInterface $order */
        $order = $form->getData();

        $this->orderProcessor->process($order);

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
