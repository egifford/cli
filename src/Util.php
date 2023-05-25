<?php

/**
 * This file is part of SebastianFeldmann\Cli.
 *
 * (c) Sebastian Feldmann <sf@sebastian-feldmann.info>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SebastianFeldmann\Cli;

use RuntimeException;

/**
 * Interface Processor
 *
 * @package SebastianFeldmann\Cli
 * @author  Sebastian Feldmann <sf@sebastian-feldmann.info>
 * @link    https://github.com/sebastianfeldmann/cli
 * @since   Class available since Release 1.0.4
 */
abstract class Util
{
    /**
     * List of console style codes.
     *
     * @var array
     */
    private static $ansiCodes = [
        'bold'       => 1,
        'fg-black'   => 30,
        'fg-red'     => 31,
        'fg-green'   => 32,
        'fg-yellow'  => 33,
        'fg-cyan'    => 36,
        'fg-white'   => 37,
        'bg-red'     => 41,
        'bg-green'   => 42,
        'bg-yellow'  => 43
    ];

    /**
     * Detect a given command's location.
     *
     * @param  string $cmd               The command to locate
     * @param  string $path              Directory where the command should be
     * @param  array  $optionalLocations Some fallback locations where to search for the command
     * @return string                    Absolute path to detected command including command itself
     * @throws \RuntimeException
     */
    public static function detectCmdLocation(string $cmd, string $path = '', array $optionalLocations = []): string
    {
        $detectionSteps = [
            function ($cmd) use ($path) {
                if (!empty($path)) {
                    return self::detectCmdLocationInPath($cmd, $path);
                }
                return '';
            },
            function ($cmd) {
                return self::detectCmdLocationWithWhich($cmd);
            },
            function ($cmd) {
                $paths = explode(PATH_SEPARATOR, self::getEnvPath());
                return self::detectCmdLocationInPaths($cmd, $paths);
            },
            function ($cmd) use ($optionalLocations) {
                return self::detectCmdLocationInPaths($cmd, $optionalLocations);
            }
        ];

        foreach ($detectionSteps as $step) {
            $bin = $step($cmd);
            if (!empty($bin)) {
                return $bin;
            }
        }

        throw new RuntimeException(sprintf('\'%s\' was nowhere to be found please specify the correct path', $cmd));
    }

    /**
     * Detect a command in a given path.
     *
     * @param  string $cmd
     * @param  string $path
     * @return string
     * @throws \RuntimeException
     */
    public static function detectCmdLocationInPath(string $cmd, string $path): string
    {
        $command = $path . DIRECTORY_SEPARATOR . $cmd;
        $bin     = self::getExecutable($command);
        if (empty($bin)) {
            throw new RuntimeException(sprintf('wrong path specified for \'%s\': %s', $cmd, $path));
        }
        return $bin;
    }

    /**
     * Detect command location using which cli command.
     *
     * @param  string $cmd
     * @return string
     */
    public static function detectCmdLocationWithWhich($cmd): string
    {
        $bin = '';
        // on nx systems use 'which' command.
        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command = trim(`which $cmd`);
            $bin     = self::getExecutable($command);
        }
        return $bin;
    }

    /**
     * Check path list for executable command.
     *
     * @param  string $cmd
     * @param  array  $paths
     * @return string
     */
    public static function detectCmdLocationInPaths($cmd, array $paths): string
    {
        foreach ($paths as $path) {
            $command = $path . DIRECTORY_SEPARATOR . $cmd;
            $bin     = self::getExecutable($command);
            if (null !== $bin) {
                return $bin;
            }
        }
        return '';
    }

    /**
     * Return local $PATH variable.
     *
     * @return string
     * @throws \RuntimeException
     */
    public static function getEnvPath(): string
    {
        // check for unix and windows case $_SERVER index
        foreach (['PATH', 'Path', 'path'] as $index) {
            if (isset($_SERVER[$index])) {
                return $_SERVER[$index];
            }
        }
        throw new RuntimeException('cant find local PATH variable');
    }

    /**
     * Returns the executable command if the command is executable, empty string otherwise.
     * Search for $command.exe on Windows systems.
     *
     * @param  string $command
     * @return string
     */
    public static function getExecutable($command): string
    {
        if (is_executable($command)) {
            return $command;
        }
        // on windows check the .exe suffix
        if (defined('PHP_WINDOWS_VERSION_BUILD')) {
            $command .= '.exe';
            if (is_executable($command)) {
                return $command;
            }
        }
        return '';
    }

    /**
     * Is given path absolute.
     *
     * @param  string $path
     * @return bool
     */
    public static function isAbsolutePath($path): bool
    {
        // path already absolute?
        if ($path[0] === '/') {
            return true;
        }

        // Matches the following on Windows:
        //  - \\NetworkComputer\Path
        //  - \\.\D:
        //  - \\.\c:
        //  - C:\Windows
        //  - C:\windows
        //  - C:/windows
        //  - c:/windows
        if (defined('PHP_WINDOWS_VERSION_BUILD') && self::isAbsoluteWindowsPath($path)) {
            return true;
        }

        // Stream
        if (strpos($path, '://') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Is given path an absolute windows path.
     *
     * @param  string $path
     * @return bool
     */
    public static function isAbsoluteWindowsPath($path): bool
    {
        return ($path[0] === '\\' || (strlen($path) >= 3 && preg_match('#^[A-Z]\:[/\\\]#i', substr($path, 0, 3))));
    }

    /**
     * Converts a path to an absolute one if necessary relative to a given base path.
     *
     * @param  string $path
     * @param  string $base
     * @param  bool   $useIncludePath
     * @return string
     */
    public static function toAbsolutePath(string $path, string $base, bool $useIncludePath = false): string
    {
        if (self::isAbsolutePath($path)) {
            return $path;
        }

        $file = $base . DIRECTORY_SEPARATOR . $path;

        if ($useIncludePath && !file_exists($file)) {
            $includePathFile = stream_resolve_include_path($path);
            if ($includePathFile) {
                $file = $includePathFile;
            }
        }
        return $file;
    }

    /**
     * Formats a buffer with a specified ANSI color sequence if colors are enabled.
     *
     * @author Sebastian Bergmann <sebastian@phpunit.de>
     * @param  string $color
     * @param  string $buffer
     * @return string
     */
    public static function formatWithColor(string $color, string $buffer): string
    {
        $codes   = array_map('trim', explode(',', $color));
        $lines   = explode("\n", $buffer);
        $padding = max(array_map('strlen', $lines));

        $styles = [];
        foreach ($codes as $code) {
            $styles[] = self::$ansiCodes[$code];
        }
        $style = sprintf("\x1b[%sm", implode(';', $styles));

        $styledLines = [];
        foreach ($lines as $line) {
            $styledLines[] = strlen($line) ? $style . str_pad($line, $padding) . "\x1b[0m" : '';
        }

        return implode(PHP_EOL, $styledLines);
    }

    /**
     * Fills up a text buffer with '*' to consume by default 72 chars.
     *
     * @param  string $buffer
     * @param  int    $length
     * @return string
     */
    public static function formatWithAsterisk(string $buffer, int $length = 72): string
    {
        return $buffer . str_repeat('*', $length - strlen($buffer)) . PHP_EOL;
    }

    /**
     * Can command pipe operator be used.
     *
     * @return bool
     */
    public static function canPipe(): bool
    {
        return !defined('PHP_WINDOWS_VERSION_BUILD');
    }

    /**
     * Removes a directory that is not empty.
     *
     * @param string $dir
     */
    public static function removeDir(string $dir)
    {
        foreach (scandir($dir) as $file) {
            if ('.' === $file || '..' === $file) {
                continue;
            }
            if (is_dir($dir . '/' . $file)) {
                self::removeDir($dir . '/' . $file);
            } else {
                unlink($dir . '/' . $file);
            }
        }
        rmdir($dir);
    }

    /**
     * Wraps windows command with a double quote to escape spaces
     * i.e: `E:/Program Files/tar.exe -zcf ...` escaped to `"E:/Program Files/tar.exe -zcf ..."`
     * @param string $cmd
     * @return string
     */
    public static function escapeSpacesIfOnWindows(string $cmd): string
    {
        $escapeSpacesIfOnWindows = !defined('SKIP_ESCAPE_SPACES_IF_ON_WINDOWS') && defined('PHP_WINDOWS_VERSION_BUILD');

        return $escapeSpacesIfOnWindows ? sprintf('"%s"', $cmd) : $cmd;
    }
}
