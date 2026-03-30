<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Mudrak\OnePageCheckoutPlugin\Handlers\CheckoutSaveFormHandler;
use Doctrine\ORM\EntityManagerInterface;
use Sylius\Bundle\ShopBundle\Form\Type\Checkout\AddressType;
use Sylius\Bundle\UiBundle\Twig\Component\TemplatePropTrait;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('MudrakOnePageCheckoutPlugin:OnePageCheckoutComponent', template: '@MudrakOnePageCheckoutPlugin/components/OnePageCheckout/OnePageCheckoutComponent.html.twig')]
class OnePageCheckoutComponent
{
    use TemplatePropTrait;
    use DefaultActionTrait;
    use ComponentWithFormTrait;
    use ComponentToolsTrait;
    use HookableLiveComponentTrait;
    #[LiveProp] 
    public ?int $orderId = null;

    #[LiveProp(writable: true)]
    public bool $showShippingAddress = false;

    public function __construct(
        private EntityManagerInterface $em,
        private CheckoutSaveFormHandler $checkoutFormHandler,
        protected readonly FormFactoryInterface $formFactory,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->orderId ? $this->orderRepository->find($this->orderId) : null;
    }

    protected function instantiateForm(): FormInterface
    {
        $order = $this->getOrder();

        return $this->formFactory->create(AddressType::class, $order, [
            'customer' => $order->getCustomer(),
        ]);
    }
    
    #[LiveAction]
    public function saveAddress(): void
    {
        $this->submitForm(); 
        $this->checkoutFormHandler->handle($this->getForm());
        $this->emit('updateShipment');
        $this->emit('updatePayment');
    }
}
