<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * Base test case for the very simple libphutil test framework.
 *
 * @task assert   Making Test Assertions
 * @task hook     Hooks for Setup and Teardown
 * @task internal Internals
 *
 * @group unitrun
 */
abstract class ArcanistPhutilTestCase {

  private $runningTest;
  private $testStartTime;
  private $results = array();


/* -(  Making Test Assertions  )--------------------------------------------- */


  /**
   * Assert that two values are equal. The test fails if they are not.
   *
   * NOTE: This method uses PHP's strict equality test operator ("===") to
   * compare values. This means values and types must be equal, key order must
   * be identical in arrays, and objects must be referentially identical.
   *
   * @param wild    The theoretically expected value, generated by careful
   *                reasoning about the properties of the system.
   * @param wild    The empirically derived value, generated by executing the
   *                test.
   * @param string  A human-readable description of what these values represent,
   *                and particularly of what a discrepancy means.
   *
   * @return void
   * @task assert
   */
  final protected function assertEqual($expect, $result, $message = null) {
    if ($expect === $result) {
      return;
    }

    $expect = PhutilReadableSerializer::printableValue($expect);
    $result = PhutilReadableSerializer::printableValue($result);

    $where = debug_backtrace();
    $where = array_shift($where);

    $line = idx($where, 'line');
    $file = basename(idx($where, 'file'));

    $output = "Assertion failed at line {$line} in {$file}";

    if ($message) {
      $output .= ": {$message}";
    }

    $output .= "\n";

    if (strpos($expect, "\n") !== false) {
      $expect = "\n{$expect}";
    }

    if (strpos($result, "\n") !== false) {
      $result = "\n{$result}";
    }

    $output .= "Expected: {$expect}\n";
    $output .= "Actual: {$result}";

    $this->failTest($output);
    throw new ArcanistPhutilTestTerminatedException($output);
  }


  /**
   * Assert an unconditional failure. This is just a convenience method that
   * better indicates intent than using dummy values with assertEqual(). This
   * causes test failure.
   *
   * @param   string  Human-readable description of the reason for test failure.
   * @return  void
   * @task    assert
   */
  final protected function assertFailure($message) {
    $this->failTest($message);
    throw new ArcanistPhutilTestTerminatedException($message);
  }


/* -(  Hooks for Setup and Teardown  )--------------------------------------- */


  /**
   * This hook is invoked once, before any tests in this class are run. It
   * gives you an opportunity to perform setup steps for the entire class.
   *
   * @return void
   * @task hook
   */
  protected function willRunTests() {
    return;
  }


  /**
   * This hook is invoked once, after any tests in this class are run. It gives
   * you an opportunity to perform teardown steps for the entire class.
   *
   * @return void
   * @task hook
   */
  protected function didRunTests() {
    return;
  }


  /**
   * This hook is invoked once per test, before the test method is invoked.
   *
   * @param string Method name of the test which will be invoked.
   * @return void
   * @task hook
   */
  protected function willRunOneTest($test_method_name) {
    return;
  }


  /**
   * This hook is invoked once per test, after the test method is invoked.
   *
   * @param string Method name of the test which was invoked.
   * @return void
   * @task hook
   */
  protected function didRunOneTest($test_method_name) {
    return;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   * Construct a new test case. This method is ##final##, use willRunTests() to
   * provide test-wide setup logic.
   *
   * @task internal
   */
  final public function __construct() {

  }


  /**
   * Mark the currently-running test as a failure.
   *
   * @param string  Human-readable description of problems.
   * @return void
   *
   * @task internal
   */
  final private function failTest($reason) {
    $result = new ArcanistUnitTestResult();
    $result->setName($this->runningTest);
    $result->setResult(ArcanistUnitTestResult::RESULT_FAIL);
    $result->setDuration(microtime(true) - $this->testStartTime);
    $result->setUserData($reason);
    $this->results[] = $result;
  }


  /**
   * This was a triumph. I'm making a note here: HUGE SUCCESS.
   *
   * @param string  Human-readable overstatement of satisfaction.
   * @return void
   *
   * @task internal
   */
  final private function passTest($reason) {
    $result = new ArcanistUnitTestResult();
    $result->setName($this->runningTest);
    $result->setResult(ArcanistUnitTestResult::RESULT_PASS);
    $result->setDuration(microtime(true) - $this->testStartTime);
    $result->setUserData($reason);
    $this->results[] = $result;
  }


  /**
   * Execute the tests in this test case. You should not call this directly;
   * use @{class:PhutilUnitTestEngine} to orchestrate test execution.
   *
   * @return void
   * @task internal
   */
  final public function run() {
    $this->results = array();

    $reflection = new ReflectionClass($this);
    $methods = $reflection->getMethods();

    // Try to ensure that poorly-written tests which depend on execution order
    // (and are thus not properly isolated) will fail.
    shuffle($methods);

    $this->willRunTests();
    foreach ($methods as $method) {
      $name = $method->getName();
      if (preg_match('/^test/', $name)) {
        $this->runningTest = $name;
        $this->testStartTime = microtime(true);

        try {
          $this->willRunOneTest($name);

          $test_exception = null;
          try {
            call_user_func_array(
              array($this, $name),
              array());
            $this->passTest("All assertions passed.");
          } catch (Exception $ex) {
            $test_exception = $ex;
          }

          $this->didRunOneTest($name);
          if ($test_exception) {
            throw $test_exception;
          }
        } catch (ArcanistPhutilTestTerminatedException $ex) {
          // Continue with the next test.
        } catch (Exception $ex) {
          $ex_class = get_class($ex);
          $ex_message = $ex->getMessage();
          $ex_trace = $ex->getTraceAsString();
          $message = "EXCEPTION ({$ex_class}): {$ex_message}\n{$ex_trace}";
          $this->failTest($message);
        }
      }
    }
    $this->didRunTests();

    return $this->results;
  }

}
