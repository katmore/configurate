<?php
/*@formatter:off*/ declare(strict_types = 1); /*@formatter:on*/
namespace Katmore\Configurate\Tests\Fun;

use Katmore\Configurate\TestCase;

class ConfigureTest extends TestCase\Fun\Bin
{
   const CONFIGURE_BASENAME = 'configure';
   public static function setUpBeforeClass(): void {
      parent::setUpBeforeClass();
      $cmd = static::basename2cmd(static::CONFIGURE_BASENAME);
      if (0 !== (static::exec("command -v " . $cmd))) {
         static::markTestSkipped(static::CONFIGURE_BASENAME . " must exist: $cmd");
      }
   }

   /**
    * 
    * @return string usageText
    */
   public function testUsageOption(): string {
      $stdout = $stderr = null;
      $this->assertExec(static::basename2cmd(static::CONFIGURE_BASENAME,['--usage']), 0, static::CONFIGURE_BASENAME." usage option", $stdout, $stderr);
      return $stdout;
   }
}