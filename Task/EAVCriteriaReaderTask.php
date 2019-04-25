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
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;

/**
 * Use input as criteria to find EAV Data
 */
class EAVCriteriaReaderTask extends EAVReaderTask
{
    /**
     * @param ProcessState $state
     *
     * @throws ExceptionInterface
     *
     * @return array
     */
    protected function getOptions(ProcessState $state): array
    {
        $options = parent::getOptions($state);
        $options['criteria'] = $state->getInput();
        $options['allow_reset'] = true;

        return $options;
    }
}
