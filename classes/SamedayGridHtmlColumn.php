<?php

use PrestaShop\PrestaShop\Core\Grid\Column\AbstractColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Html grid column for PS 1.7 (core HtmlColumn exists from PS 8).
 */
final class SamedayGridHtmlColumn extends AbstractColumn
{
    /**
     * {@inheritdoc}
     */
    public function getType()
    {
        return 'html';
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired([
                'field',
            ])
            ->setDefaults([
                'clickable' => true,
            ])
            ->setAllowedTypes('field', 'string')
            ->setAllowedTypes('clickable', 'bool');
    }
}
