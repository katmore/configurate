<?php
/*@formatter:off*/ declare(strict_types = 1); /*@formatter:on*/
namespace Katmore\Configurate\TestCase\Fun;

use Katmore\Configurate\TestCase;

abstract class Bin extends TestCase\Fun
{
   const BIN_ROOT = self::APP_ROOT . '/bin';

   public static function setUpBeforeClass(): void {
      parent::setUpBeforeClass();
      'cli' === PHP_SAPI || static::markTestSkipped("PHP_SAPI must be cli");
   }
   protected static function basename2cmd(string $basename, array $args = []): string {
      return static::BIN_ROOT . DIRECTORY_SEPARATOR . $basename . rtrim(' ' . implode(" ", $args));
   }
   protected static function assertExec($cmd, int $expectedExitCode = 0, string $message = "", &$stdout = "", &$stderr = ""): void {
      $exitCode = static::exec($cmd, $expectedExitCode, $stdout, $stderr);

      static::assertEquals($expectedExitCode, $exitCode, !empty($message) ? $message : "exit code must be $expectedExitCode");
   }

   /**
    * @return int exit code or -1 if failed
    */
   protected static function exec(string $cmd, int $expectedExitCode = 0, &$stdout = "", &$stderr = ""): int {
      $pipes = null;
      $proc = proc_open($cmd, [
         1 => [
            'pipe',
            'w'
         ],
         2 => [
            'pipe',
            'w'
         ],
      ], $pipes);

      $stdout = stream_get_contents($pipes[1]);
      if (!fclose($pipes[1])) {
         throw new \RuntimeException('fclose() failed for stdout');
      }

      $stderr = stream_get_contents($pipes[2]);
      if (!fclose($pipes[2])) {
         throw new \RuntimeException('fclose() failed for stderr');
      }

      while (false !== ($status = proc_get_status($proc)) && $status["running"]) {
         usleep(10000);
      }

      if (false == $status) {
         throw new \RuntimeException('proc_get_status() failed');
      }

      if (-1 !== ($proc_close_status = proc_close($proc))) {
         $exitCode = $proc_close_status;
      } else if (isset($status['exitcode'])) {
         $exitCode = $status['exitcode'];
      } else {
         $exitCode = -1;
      }

      if (in_array('--debug', $_SERVER['argv'], true) || ($exitCode !== $expectedExitCode && in_array('--verbose', $_SERVER['argv'], true))) {

         $basename = basename(explode(" ", $cmd)[0]);

         fwrite(STDERR, __FUNCTION__ . ": $basename exit code: $exitCode" . PHP_EOL);

         if (empty($stdout) && empty($stderr)) {
            fwrite(STDERR, __FUNCTION__ . ": no output from $basename" . PHP_EOL);
         } else {
            if (!empty($stdout)) {
               fwrite(STDERR, preg_filter('/^/', __FUNCTION__ . ": $basename stdout: ", $stderr) . PHP_EOL);
            }
            if (!empty($stderr)) {
               fwrite(STDERR, preg_filter('/^/', __FUNCTION__ . ": $basename stderr: ", $stderr) . PHP_EOL);
            }
         }
      }

      return $exitCode;
   }
}