<?php
declare(strict_types=1);
/*
 * This file is part of the CleverAge/EAVProcessBundle package.
 *
 * Copyright (c) 2015-2019 Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\EAVProcessBundle\Task;

use CleverAge\ProcessBundle\Model\IterableTaskInterface;
use CleverAge\ProcessBundle\Model\ProcessState;
use Doctrine\ORM\EntityManagerInterface;
use Pagerfanta\Exception\LessThan1CurrentPageException;
use Pagerfanta\Exception\NotIntegerCurrentPageException;
use Pagerfanta\Exception\OutOfRangeCurrentPageException;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Sidus\EAVModelBundle\Doctrine\EAVFinder;
use Sidus\EAVModelBundle\Exception\MissingAttributeException;
use Sidus\EAVModelBundle\Exception\MissingFamilyException;
use Sidus\EAVModelBundle\Model\FamilyInterface;
use Sidus\EAVModelBundle\Registry\FamilyRegistry;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Allows to iterate over a paged resultset of EAV data
 */
class EAVReaderTask extends AbstractEAVQueryTask implements IterableTaskInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /** @var \Iterator|\Countable */
    protected $iterator;

    /** @var bool */
    protected $closed = false;

    /**
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $entityManager
     * @param FamilyRegistry         $familyRegistry
     * @param EAVFinder              $eavFinder
     */
    public function __construct(
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        FamilyRegistry $familyRegistry,
        EAVFinder $eavFinder
    ) {
        $this->logger = $logger;
        parent::__construct($entityManager, $familyRegistry, $eavFinder);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \InvalidArgumentException
     * @throws NotIntegerCurrentPageException
     * @throws OutOfRangeCurrentPageException
     * @throws LessThan1CurrentPageException
     * @throws \UnexpectedValueException
     * @throws MissingAttributeException
     * @throws \LogicException
     * @throws ExceptionInterface
     */
    public function execute(ProcessState $state): void
    {
        $options = $this->getOptions($state);
        if ($this->closed) {
            $logContext = $this->getLogContext($state);
            if ($options['allow_reset']) {
                $this->closed = false;
                $this->iterator = null;
                $this->logger->error('Reader was closed previously, restarting it', $logContext);
            } else {
                throw new \RuntimeException('Reader was closed previously, stopping the process');
            }
        }

        $init = false;
        if (null === $this->iterator) {
            $this->initIterator($state);
            $init = true;
        }

        // Handle empty results
        if ($init && !$this->iterator->valid()) {
            $logContext = $this->getLogContext($state);
            $this->logger->log($options['empty_log_level'], 'Empty resultset for query', $logContext);
            $state->setSkipped(true);

            return;
        }

        $state->setOutput($this->iterator->current());
    }

    /**
     * Moves the internal pointer to the next element,
     * return true if the task has a next element
     * return false if the task has terminated it's iteration
     *
     * @param ProcessState $state
     *
     * @throws \LogicException
     *
     * @return bool
     */
    public function next(ProcessState $state): bool
    {
        if (!$this->iterator instanceof \Iterator) {
            throw new \LogicException('No iterator initialized');
        }
        $this->iterator->next();

        $valid = $this->iterator->valid();
        if (!$valid) {
            $this->closed = true;
        }

        return $valid;
    }

    /**
     * {@inheritDoc}
     * @throws MissingFamilyException
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);
        $resolver->setDefaults(
            [
                'allow_reset' => false,   // Allow the reader to reset it's iterator
                'log_count' => false,   // Log in state history the result count
                'empty_log_level' => LogLevel::WARNING,
            ]
        );
        $resolver->setAllowedValues(
            'empty_log_level',
            [
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::DEBUG,
                LogLevel::EMERGENCY,
                LogLevel::ERROR,
                LogLevel::INFO,
                LogLevel::NOTICE,
                LogLevel::WARNING,
            ]
        );
    }

    /**
     * @param ProcessState $state
     *
     * @throws ExceptionInterface
     *
     * @return array
     */
    protected function getLogContext(ProcessState $state): array
    {
        $logContext = $state->getLogContext();
        $options = $this->getOptions($state);
        if (array_key_exists('family', $options)) {
            /** @var FamilyInterface $family */
            $family = $options['family'];
            $options['family'] = $family->getCode();
        }
        if (array_key_exists('repository', $options)) {
            $options['repository'] = \get_class($options['repository']);
        }
        $logContext['options'] = $options;

        return $logContext;
    }

    /**
     * @param ProcessState $state
     */
    protected function initIterator(ProcessState $state): void
    {
        $paginator = $this->getPaginator($state);
        $this->iterator = $paginator->getIterator();

        // Log the data count
        if ($this->getOption($state, 'log_count')) {
            $count = $paginator->count();
            $logContext = $this->getLogContext($state);
            $this->logger->info("{$count} items found with current query", $logContext);
        }
    }
}
