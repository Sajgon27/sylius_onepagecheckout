<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Twig\Components;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Component\Order\Context\CartContextInterface;
use Sylius\TwigHooks\LiveComponent\HookableLiveComponentTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Workflow\Registry;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Response;

#[AsLiveComponent('OnePageCheckout:Complete', template: '@MudrakOnePageCheckoutPlugin/components/OnePageCheckout/Complete.html.twig')]
class Complete extends AbstractController
{
    use DefaultActionTrait;
    use HookableLiveComponentTrait;
    use ComponentWithFormTrait;

    #[LiveProp(writable: true)]
    public ?int $orderId = null;

    public function __construct(
        private CartContextInterface $cartContext,
        private Registry $workflowRegistry,
        private EntityManagerInterface $entityManager,
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
        return $this->formFactory->create(CompleteType::class, $this->getOrder());
    }

    #[LiveAction]
    public function submit(): Response
    {
        $this->submitForm();

        if (!$this->getForm()->isValid()) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $order = $this->getOrder();
        $workflow = $this->workflowRegistry->get($order, 'sylius_order_checkout');
        $workflow->apply($order, 'complete');

        $this->entityManager->flush();

        return $this->redirectToRoute('sylius_shop_order_pay', [
            'tokenValue' => $order->getTokenValue(),
        ]);
    }
}