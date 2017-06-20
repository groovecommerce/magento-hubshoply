<?php

/**
 * Diagnostic program model.
 * 
 * PHP Version 5
 * 
 * @category  Class
 * @package   Groove_Hubshoply
 * @author    Groove Commerce
 * @copyright 2017 Groove Commerce, LLC. All Rights Reserved.
 *
 * LICENSE
 * 
 * The MIT License (MIT)
 * Copyright (c) 2017 Groove Commerce, LLC.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

/**
 * Class declaration
 *
 * @category Class_Type_Model
 * @package  Groove_Hubshoply
 * @author   Groove Commerce
 */

class Groove_Hubshoply_Model_Diagnostic
    extends Varien_Object
{

    private $_dependencyMap = array();

    /**
     * Load diagnostic model type data.
     * 
     * @return array
     */
    private function _loadTypeData(array $types = array())
    {
        $output = array();
        $config = Mage::getSingleton('groove_hubshoply/config')
            ->getDiagnosticTests()
            ->asArray();

        if (empty($types)) {
            return $config;
        }

        foreach ($types as $key) {
            if (!empty($config[$key])) {
                $output[$key] = $config[$key];
            }
        }

        return $output;
    }

    /**
     * Prepare diagnostic tests.
     * 
     * @param array $types The test types.
     * 
     * @return Varien_Data_Collection
     */
    private function _prepareTests(array $types)
    {
        $unsorted = array();
        $tests    = new Varien_Data_Collection();

        foreach ($types as $key => $config) {
            if (empty($config['model'])) {
                Mage::throwException(sprintf('Diagnostic validator model must be specified for type: %s', $key));
            }

            $method = empty($config['method']) ? 'run' : $config['method'];
            $object = Mage::getSingleton($config['model']);

            $test = new Varien_Object(
                array(
                    'id'            => md5($key),
                    'type_id'       => $key,
                    'name'          => empty($config['name']) ? $key : $config['name'],
                    'status'        => null,
                    'details'       => '',
                    'callback'      => array($object, $method),
                    'model'         => $object,
                    'started_at'    => null,
                    'finished_at'   => null,
                )
            );
        
            $unsorted[] = $test;
        }

        usort($unsorted, array($this, '_sortTests'));

        foreach ($unsorted as $test) {
            $tests->addItem($test);
        }

        return $tests;
    }

    /**
     * Sort comparator for test dependencies.
     * 
     * @param Varien_Object $a The left test.
     * @param Varien_Object $b The right test.
     * 
     * @return integer
     */
    private function _sortTests(Varien_Object $a, Varien_Object $b)
    {
        if ( in_array($a->getTypeId(), array_keys($b->getModel()->getDependencies())) ) {
            return -1;
        }

        return 1;
    }

    /**
     * Validate the current test results against the given test's dependencies.
     * 
     * @param Varien_Object          $test  The test object.
     * @param Varien_Data_Collection $tests All available test results.
     * 
     * @return boolean
     */
    private function _validateDependencies(Varien_Object $test, Varien_Data_Collection $tests)
    {
        foreach ($test->getModel()->getDependencies() as $typeId => $requiredStatus) {
            if ( ( $dependentTest = $tests->getItemByColumnValue('type_id', $typeId) ) ) {
                if ( $dependentTest->getStatus() !== $requiredStatus ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Perform one or more diagnostic tests.
     *
     * @param array   $types   The types of tests to run
     * @param integer $storeId The store ID for context.
     * 
     * @return Varien_Data_Collection
     */
    public function run(array $types = array(), $storeId = null)
    {
        $types          = $this->_loadTypeData($types);
        $tests          = $this->_prepareTests($types);
        $environment    = null;

        if ( $storeId && ( $storeId != Mage::app()->getStore()->getId() ) ) {
            $environment = Mage::getSingleton('core/app_emulation')->startEnvironmentEmulation($storeId);
        }

        foreach ($tests as $test) {
            if (!is_callable($test->getCallback())) {
                Mage::throwException(sprintf('Callback for diagnostic test "%s" is not valid', $test->getTypeId()));
            }

            if (
                ( count($tests) > 1 || !$this->getSkipDependencyCheckFlag() ) && 
                !$this->_validateDependencies($test, $tests)
            ) {
                $test->setStatus(Groove_Hubshoply_Model_Diagnostic_Interface::STATUS_SKIP)
                    ->setDetails('Did not pass dependency check.');

                continue;
            }

            $test->setStartedAt(now());

            call_user_func($test->getCallback(), $test);

            $test->setFinishedAt(now());
        }

        if ($environment) {
            Mage::getSingleton('core/app_emulation')->stopEnvironmentEmulation($environment);
        }

        return $tests;
    }

    /**
     * Export test results to JSON.
     * 
     * @return string
     */
    public function exportJson(Varien_Data_Collection $results)
    {
        $user   = Mage::getSingleton('admin/session')->getUser();
        $output = array(
            'exported_at'   => now(),
            'requestor'     => ( $user && $user->getId() ) ? $user->getId() : null,
            'environment'   => Mage::app()->getRequest()->getEnv(),
            'server'        => Mage::app()->getRequest()->getServer(),
            'configuration' => Mage::getStoreConfig('hubshoply'),
            'modules'       => Mage::getConfig()->getNode('modules')->asArray(),
            'test_results'  => array(),
        );

        foreach ($results as $result) {
            $data = $result->getData();

            unset($data['callback']);
            unset($data['model']);

            $output['test_results'][$result->getId()] = $data;
        }

        return Mage::helper('core')->jsonEncode($output);
    }

}