<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\Order;
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
    public Order $order;

    public function __construct() {}

    #[LiveListener('updateSummary')]
    public function updateSummary()
    { }
}
