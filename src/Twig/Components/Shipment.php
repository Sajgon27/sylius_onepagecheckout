<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Mudrak\OnePageCheckoutPlugin\Handlers\CheckoutSaveFormHandler;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectShippingType;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;


#[AsLiveComponent('OnePageCheckout:Shipment', template: '@MudrakOnePageCheckoutPlugin/components/OnePageCheckout/Shipment.html.twig')]
class Shipment
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use HookableLiveComponentTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true, updateFromParent: true)]
    public ?int $orderId = null;

    public function __construct(
        private CheckoutSaveFormHandler $checkoutFormHandler,
        protected readonly FormFactoryInterface $formFactory,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->orderId ? $this->orderRepository->find($this->orderId) : null;
    }

    public function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(SelectShippingType::class, $this->getOrder());
    }

    #[LiveAction]
    public function updateShippingMethod(): void
    {
        $this->submitForm();
        $this->emit('updateSummary');
        $this->emit('updatePayment');
        $this->checkoutFormHandler->handle($this->getForm());
    }

    #[LiveListener('updateShipment')]
    public function updateShipment()
    { }
}
