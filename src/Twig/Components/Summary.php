<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('OnePageCheckout:Summary', template: '@MudrakOnePageCheckoutPlugin/components/OnePageCheckout/Summary.html.twig')]
class Summary
{
    use DefaultActionTrait;
    use HookableLiveComponentTrait;

    #[LiveProp(writable: true, updateFromParent: true)]
    public ?int $orderId = null;

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
    ) {}

    public function getOrder(): ?OrderInterface
    {
        return $this->orderId ? $this->orderRepository->find($this->orderId) : null;
    }

    #[LiveListener('updateSummary')]
    public function updateSummary()
    { }
}
