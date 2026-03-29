<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\Order;
use Mudrak\OnePageCheckoutPlugin\Handlers\CheckoutSaveFormHandler;
use Sylius\Bundle\ShopBundle\Form\Type\Checkout\SelectPaymentType;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('OnePageCheckout:Payment', template: '@MudrakOnePageCheckoutPlugin/components/OnePageCheckout/Payment.html.twig')]
class Payment
{
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use HookableLiveComponentTrait;

    #[LiveProp(writable: true)]
    public Order $order;

    public function __construct(
        private CheckoutSaveFormHandler $checkoutFormHandler,
        protected readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function instantiateForm(): FormInterface
    {
        return $this->formFactory->create(SelectPaymentType::class, $this->order);
        
    }

    #[LiveAction]
    public function updatePaymentMethod(): void
    {
        $this->submitForm();

        $this->checkoutFormHandler->handle($this->getForm());
    }
}
