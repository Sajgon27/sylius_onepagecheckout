<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\OrderInterface;
use Mudrak\OnePageCheckoutPlugin\Handlers\CheckoutSaveFormHandler;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectShippingType;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
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
    public OrderInterface $order;

    public function __construct(
        private CheckoutSaveFormHandler $checkoutFormHandler,
        protected readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(SelectShippingType::class, $this->order);
    }

    #[LiveAction]
    public function updateShippingMethod(): void
    {
        $this->submitForm();
        $this->emit('updateSummary');
        $this->checkoutFormHandler->handle($this->getForm());
    }
}
