<?php
/*
 * This file is part of the CleverAge/EAVProcessBundle package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVProcessBundle\Task;

use CleverAge\ProcessBundle\Model\ProcessState;
use CleverAge\DoctrineProcessBundle\Task\EntityManager\AbstractDoctrineTask;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\ORMInvalidArgumentException;
use Sidus\EAVModelBundle\Entity\DataInterface;
use Sidus\EAVModelBundle\Exception\ContextException;
use Sidus\EAVModelBundle\Exception\InvalidValueDataException;
use Sidus\EAVModelBundle\Exception\MissingAttributeException;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Update an flush a single value from an EAV data
 */
class EAVUpdateSingleValueTask extends AbstractDoctrineTask
{
    /**
     * @param ProcessState $state
     *
     * @throws ExceptionInterface
     * @throws \InvalidArgumentException
     * @throws MissingAttributeException
     * @throws OptimisticLockException
     * @throws ORMInvalidArgumentException
     * @throws InvalidValueDataException
     * @throws ContextException
     * @throws ORMException
     */
    public function execute(ProcessState $state): void
    {
        $entity = $state->getInput();
        if (!$entity instanceof DataInterface) {
            throw new \UnexpectedValueException('Expecting a DataInterface as input');
        }
        $family = $entity->getFamily();
        $options = $this->getOptions($state);
        if (!$family->hasAttribute($options['attribute'])) {
            throw new MissingAttributeException($options['attribute']);
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManager();

        $attribute = $family->getAttribute($options['attribute']);
        $entity->set($attribute->getCode(), $options['value']); // Set actual value

        $valueEntity = $entity->getValue($attribute); // Get value "entity" with updated value
        $em->persist($valueEntity); // Persist if new
        $em->flush($valueEntity); // Flush value ONLY
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @throws AccessException
     * @throws UndefinedOptionsException
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setRequired(
            [
                'attribute',
                'value',
            ]
        );
        $resolver->setAllowedTypes('attribute', ['string']);
    }
}
