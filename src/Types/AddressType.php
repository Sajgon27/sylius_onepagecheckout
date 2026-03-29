<?php

declare(strict_types=1);

namespace Mudrak\OnePageCheckoutPlugin\Types;

use Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType as BaseAddressType;
use Sylius\Bundle\ShopBundle\Form\Type\AddressType as SyliusAddressType;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;
use Webmozart\Assert\Assert;

final class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $validate = $options['validate'];

        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($validate): void {
                $form = $event->getForm();

                Assert::isInstanceOf($event->getData(), OrderInterface::class);

                /** @var OrderInterface $order */
                $order = $event->getData();
                $channel = $order->getChannel();

                $form
                    ->add('shippingAddress', SyliusAddressType::class, [
                        'shippable' => true,
                        'constraints' => $validate ? [new Valid()] : [],
                        'validation_groups' => $validate ? ['sylius', 'sylius_shipping_address_update'] : false,
                        'channel' => $channel,
                    ])
                    ->add('billingAddress', SyliusAddressType::class, [
                        'constraints' => $validate ? [new Valid()] : [],
                        'validation_groups' => $validate ? ['sylius'] : false,
                        'channel' => $channel,
                    ])
                ;
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'validate' => true,
        ]);

        $resolver->setAllowedTypes('validate', 'bool');
    }

    public function getParent(): string
    {
        return BaseAddressType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'sylius_shop_checkout_address';
    }
}