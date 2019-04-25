<?php
/*
 * This file is part of the CleverAge/EAVProcessBundle package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVProcessBundle\Transformer;

use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Entity\DataRepository;
use Sidus\EAVModelBundle\Model\AttributeInterface;
use Sidus\EAVModelBundle\Model\FamilyInterface;

use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Find an EAV entity based on a unique attribute that is not an identifier
 *
 * @author Vincent Chalnot <vchalnot@clever-age.com>
 */
class UniqueEAVFinderTransformer extends SingleEAVFinderTransformer
{
    /**
     * @param OptionsResolver $resolver
     *
     * @throws \Exception
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setRequired(
            [
                'attribute',
            ]
        );
        $resolver->setAllowedTypes('attribute', ['string', AttributeInterface::class]);
        $resolver->setNormalizer(
            'attribute',
            static function (Options $options, $value) {
                /** @var FamilyInterface $family */
                $family = $options['family'];
                if ($value instanceof AttributeInterface) {
                    if (!$family->hasAttribute($value->getCode())) {
                        throw new \UnexpectedValueException(
                            "Family {$family->getCode()} has no attribute named {$value->getCode()}"
                        );
                    }

                    return $value;
                }

                return $family->getAttribute($value);
            }
        );
    }

    /**
     * Returns the unique code to identify the transformer
     *
     * @return string
     */
    public function getCode(): string
    {
        return 'unique_eav_finder';
    }

    /**
     * @param string|int $value
     * @param array      $options
     *
     * @throws \Exception
     *
     * @return DataInterface|null
     */
    protected function findData($value, array $options): ?DataInterface
    {
        /** @var FamilyInterface $family */
        $family = $options['family'];
        /** @var AttributeInterface $attribute */
        $attribute = $options['attribute'];
        /** @var DataRepository $repository */
        $repository = $options['repository'];

        $data = $repository->findByUniqueAttribute($family, $attribute, $value);
        if (null === $data && !$options['ignore_missing']) {
            $msg = "Missing entity for family {$family->getCode()} and";
            $msg .= " attribute {$attribute->getCode()} with value '{$value}'";
            throw new \UnexpectedValueException($msg);
        }

        return $data;
    }
}
