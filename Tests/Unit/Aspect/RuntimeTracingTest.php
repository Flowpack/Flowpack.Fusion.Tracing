<?php
namespace Flowpack\Fusion\Tracing;

use Flowpack\Fusion\Tracing\Aspect\RuntimeTracing;
use Neos\Flow\Tests\UnitTestCase;

class RuntimeTracingTest extends UnitTestCase
{

  /**
   * @test
   */
  public function getStackFrame_with_same_paths()
  {
    $aspect = new RuntimeTracing();

    $sfs[] = $aspect->getStackFrame('root');
    $sfs[] = $aspect->getStackFrame('root');
    $sfs[] = $aspect->getStackFrame('root');

    foreach ($sfs as $sf) {
      $this->assertEquals(1, $sf);
    }

    $this->assertEquals([
      1 => ['name' => 'root'],
    ], $aspect->getStackFrames());
  }

  /**
   * @test
   */
  public function getStackFrame_with_multiple_paths()
  {
    $aspect = new RuntimeTracing();

    $sfs[] = $aspect->getStackFrame('root');
    $sfs[] = $aspect->getStackFrame('root/default');
    $sfs[] = $aspect->getStackFrame('root/default/body');
    $sfs[] = $aspect->getStackFrame('root/test/__meta/cache/entryIdentifier');
    $sfs[] = $aspect->getStackFrame('root/test2');

    foreach ($sfs as $sf) {
      $this->assertIsInt($sf);
    }

    $this->assertEquals([
      1 => [
        'name' => 'root'
      ],
      2 => [
        'name' => 'default',
        'parent' => '1'
      ],
      3 => [
        'name' => 'body',
        'parent' => '2'
      ],
      4 => [
        'name' => 'test',
        'parent' => '1'
      ],
      5 => [
        'name' => '__meta',
        'parent' => '4'
      ],
      6 => [
        'name' => 'cache',
        'parent' => '5'
      ],
      7 => [
        'name' => 'entryIdentifier',
        'parent' => '6'
      ],
      8 => [
        'name' => 'test2',
        'parent' => '1'
      ],
    ], $aspect->getStackFrames());
  }
}
