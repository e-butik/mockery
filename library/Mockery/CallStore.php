<?php
/**
 * Mockery
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://github.com/padraic/mockery/blob/master/LICENSE
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to padraic@php.net so we can send you a copy immediately.
 *
 * @category   Mockery
 * @package    Mockery
 * @copyright  Copyright (c) 2010 Pádraic Brady (http://blog.astrumfutura.com)
 * @license    http://github.com/padraic/mockery/blob/master/LICENSE New BSD License
 */
 
namespace Mockery;

class CallStore
{

    /**
     * Test Spy object to which this CallStore belongs
     *
     * @var object
     */
    protected $_spy = null;
    
    /**
     * Method name 
     *
     * @var string
     */
    protected $_name = null;
    
    /**
     * Arguments expected by this expectation
     *
     * @var array
     */
    protected $_expectedArgs = array();
    
    /**
     * Count validator store
     *
     * @var array
     */
    protected $_countValidators = array();
    
    /**
     * The count validator class to use
     *
     * @var string
     */
    protected $_countValidatorClass = 'Mockery\CountValidator\Exact';
    
    /**
     * Actual count of calls to this expectation
     *
     * @var int
     */
    protected $_actualCount = 0;
    
    /**
     * Value to return from this expectation
     *
     * @var mixed
     */
    protected $_returnValue = null;
    
    /**
     * Array of return values as a queue for multiple return sequence
     *
     * @var array
     */
    protected $_returnQueue = array();
    
    /**
     * Array of closures executed with given arguments to generate a result
     * to be returned
     *
     * @var array
     */
    protected $_closureQueue = array();
    
    /**
     * Integer representing the call order of this expectation
     *
     * @var int
     */
    protected $_orderNumber = null;
    
    /**
     * Integer representing the call order of this expectation on a global basis
     *
     * @var int
     */
    protected $_globalOrderNumber = null;
    
    /**
     * Flag indicating that an exception is expected to be throw (not returned)
     *
     * @var bool
     */
    protected $_throw = false;
    
    /**
     * Flag indicating whether the order of calling is determined locally or
     * globally
     *
     * @var bool
     */
    protected $_globally = false;
    
    /**
     * Flag indicating we expect no arguments
     *
     * @var bool
     */
    protected $_noArgsExpectation = false;
    
    /**
     * Constructor
     *
     * @param \Mockery\SpyInterface $spy
     * @param string $name
     */
    public function __construct(\Mockery\SpyInterface $spy, $name)
    {
        $this->_spy = $spy;
        $this->_name = $name;
    }
    
    /**
     * Return a string with the method name and arguments formatted
     *
     * @param string $name Name of the expected method
     * @param array $args List of arguments to the method
     * @return string
     */
    public function __toString()
    {
        return \Mockery::formatArgs($this->_name, $this->_expectedArgs);
    }
    
    /**
     * Verify the current call, i.e. that the given arguments match those
     * of this expectation
     *
     * @param array $args
     * @return mixed
     */
    public function verifyCall(array $args)
    {
        //$this->validateOrder();
        $this->_actualCount++;
        $return = $this->_getReturnValue($args);
        if ($return instanceof \Exception && $this->_throw === true) {
            throw $return;
        }
        return $return; 
    }
    
    /**
     * Fetch the return value for the matching args
     *
     * @param array $args
     * @return mixed
     */
    protected function _getReturnValue(array $args)
    {
        if (count($this->_closureQueue) > 1) {
            return call_user_func_array(array_shift($this->_closureQueue), $args);
        } elseif (count($this->_closureQueue) > 0) {
            return call_user_func_array(current($this->_closureQueue), $args);
        } elseif (count($this->_returnQueue) > 1) {
            return array_shift($this->_returnQueue);
        } elseif (count($this->_returnQueue) > 0) {
            return current($this->_returnQueue);
        }
    }

    /**
     * Checks if this expectation is eligible for additional calls
     *
     * @return bool
     */
    public function isEligible()
    {
        foreach ($this->_countValidators as $validator) {
            if (!$validator->isEligible($this->_actualCount)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if there is a constraint on call count
     *
     * @return bool
     */
    public function isCallCountConstrained()
    {
        return (count($this->_countValidators) > 0);
    }
    
    /**
     * Verify call order
     *
     * @return void
     */
    public function validateOrder()
    {
        if ($this->_orderNumber) {
            $this->_spy->mockery_validateOrder((string) $this, $this->_orderNumber);
        }
        if ($this->_globalOrderNumber) {
            $this->_spy->mockery_getContainer()
                ->mockery_validateOrder((string) $this, $this->_globalOrderNumber);
        }
    }
    
    /**
     * Verify this expectation
     *
     * @return bool
     */
    public function verify()
    {
        foreach ($this->_countValidators as $validator) {
            $validator->validate($this->_actualCount);
        }
    }
    
    /**
     * Check if passed arguments match an argument expectation
     *
     * @param array $args
     * @return bool
     */
    public function matchArgs(array $args)
    {
        if(empty($this->_expectedArgs) && !$this->_noArgsExpectation) {
            return true;
        }
        if(count($args) !== count($this->_expectedArgs)) {
            return false;
        }
        foreach ($args as $i=>$v) {
            if (!$this->_matchArg($this->_expectedArgs[$i], $v)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Check if passed argument matches an argument expectation
     *
     * @param array $args
     * @return bool
     */
    protected function _matchArg($expected, $actual)
    {
        if ($expected === $actual) {
            return true;
        }
        if (!is_object($expected) && !is_object($actual) && $expected == $actual) {
            return true;
        }
        if (is_string($expected) && !is_array($actual) && !is_object($actual)) {
            $result = @preg_match($expected, (string) $actual);
            if($result) {
                return true;
            }
        }
        if (is_string($expected) && is_object($actual)) {
            $result = $actual instanceof $expected;
            if($result) {
                return true;
            }
        }
        if ($expected instanceof \Mockery\Matcher\MatcherAbstract) {
            return $expected->match($actual);
        }
        return false;
    }
    
    /**
     * Expected argument setter for the expectation
     *
     * @param mixed
     * @return self
     */
    public function with()
    {
        $this->_expectedArgs = func_get_args();
        return $this;
    }
    
    /**
     * Set with() as no arguments expected
     *
     * @return self
     */
    public function withNoArgs()
    {
        $this->_noArgsExpectation = true;
        return $this->with();
    }
    
    /**
     * Set expectation that any arguments are acceptable
     *
     * @return self
     */
    public function withAnyArgs()
    {
        $this->_expectedArgs = array();
        return $this;
    }
    
    /**
     * Set a return value, or sequential queue of return values
     *
     * @return self
     */
    public function andReturn()
    {
        $this->_returnQueue = func_get_args();
        return $this;
    }
    
    /**
     * Set a return value, or sequential queue of return values
     *
     * @return self
     */
    public function thenReturn()
    {
        return call_user_func_array(array($this, 'andReturn'), func_get_args());
    }
    
    /**
     * Set a closure or sequence of closures with which to generate return
     * values. The arguments passed to the expected method are passed to the
     * closures as parameters.
     *
     * @return self
     */
    public function andReturnUsing()
    {
        $this->_closureQueue = func_get_args();
        return $this;
    }
    
    /**
     * Set a closure or sequence of closures with which to generate return
     * values. The arguments passed to the expected method are passed to the
     * closures as parameters.
     *
     * @return self
     */
    public function thenReturnUsing()
    {
        return call_user_func_array(array($this, 'andReturnUsing'), func_get_args());
    }
    
    /**
     * Return a self-returning black hole object.
     *
     * @return self
     */
    public function andReturnUndefined()
    {
        $this->andReturn(new \Mockery\Undefined);
        return $this;
    }
    
    /**
     * Return a self-returning black hole object.
     *
     * @return self
     */
    public function thenReturnUndefined()
    {
        return call_user_func_array(array($this, 'andReturnUndefined'), func_get_args());
    }
    
    /**
     * Set Exception class and arguments to that class to be thrown
     *
     * @param string $exception
     * @param string $message
     * @param int $code
     * @param Exception $previous
     * @return self
     */
    public function thenThrow($exception, $message = '', $code = 0, \Exception $previous = null)
    {
        $this->_throw = true;
        if (is_object($exception)) {
            $this->andReturn($exception);
        } else {
            $this->andReturn(new $exception($message, $code, $previous));
        }
        return $this;
    }
    
    /**
     * Indicates this expectation should occur zero or more times
     *
     * @return self
     */
    public function zeroOrMoreTimes()
    {
        $this->atLeast()->never();
    }
    
    /**
     * Indicates the number of times this expectation should occur
     *
     * @param int $limit
     */
    public function times($limit = null)
    {
        if (is_null($limit)) return $this;
        $this->_countValidators[] = new $this->_countValidatorClass($this, $limit);
        $this->_countValidatorClass = 'Mockery\CountValidator\Exact';
        $this->verify();
        return $this;
    }
    
    /**
     * Indicates that this expectation is never expected to be called
     *
     * @return self
     */
    public function never()
    {
        return $this->times(0);
    }
    
    /**
     * Indicates that this expectation is expected exactly once
     *
     * @return self
     */
    public function once()
    {
        return $this->times(1);
    }
    
    /**
     * Indicates that this expectation is expected exactly twice
     *
     * @return self
     */
    public function twice()
    {
        return $this->times(2);
    }
    
    /**
     * Sets next count validator to the AtLeast instance
     *
     * @return self
     */
    public function atLeast()
    {
        $this->_countValidatorClass = 'Mockery\CountValidator\AtLeast';
        return $this;
    }
    
    /**
     * Sets next count validator to the AtMost instance
     *
     * @return self
     */
    public function atMost()
    {
        $this->_countValidatorClass = 'Mockery\CountValidator\AtMost';
        return $this;
    }
    
    /**
     * Shorthand for setting minimum and maximum constraints on call counts
     *
     * @param int $minimum
     * @param int $maximum
     */
    public function between($minimum, $maximum)
    {
        return $this->atLeast()->times($minimum)->atMost()->times($maximum);
    }
    
    /**
     * Mark this expectation as being a default
     *
     * @return self
     */
    public function byDefault()
    {
        $director = $this->_spy->mockery_getExpectationsFor($this->_name);
        if(!empty($director)) {
            $director->makeExpectationDefault($this);
        }
        return $this;
    }
    
    /**
     * Return the parent mock of the expectation
     *
     * @return \Mockery\MockInterface
     */
    public function getMock()
    {
        return $this->_spy;
    }

}