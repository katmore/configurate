<?php
/**
 * class \flat\core\config
 *
 * PHP version >=7.3
 *
 * Copyright (c) 2012-2019 Doug Bird.
 *    All Rights Reserved.
 *
 * COPYRIGHT NOTICE:
 * The flat framework. https://github.com/katmore/flat
 * Copyright (c) 2012-2017  Doug Bird.
 * ALL RIGHTS RESERVED. THIS COPYRIGHT APPLIES TO THE ENTIRE CONTENTS OF THE WORKS HEREIN
 * UNLESS A DIFFERENT COPYRIGHT NOTICE IS EXPLICITLY PROVIDED WITH AN EXPLANATION OF WHERE
 * THAT DIFFERENT COPYRIGHT APPLIES. WHERE SUCH A DIFFERENT COPYRIGHT NOTICE IS PROVIDED
 * IT SHALL APPLY EXCLUSIVELY TO THE MATERIAL AS DETAILED WITHIN THE NOTICE.
 *
 * The flat framework is copyrighted free software.
 * You can redistribute it and/or modify it under either the terms and conditions of the
 * "The MIT License (MIT)" (see the file MIT-LICENSE.txt); or the terms and conditions
 * of the "GPL v3 License" (see the file GPL-LICENSE.txt).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @license The MIT License (MIT) http://opensource.org/licenses/MIT
 * @license GNU General Public License, version 3 (GPL-3.0) http://opensource.org/licenses/GPL-3.0
 * @link https://github.com/katmore/flat
 * @author     D. Bird <retran@gmail.com>
 * @copyright  Copyright (c) 2012-2019 Doug Bird. All Rights Reserved.
 */
namespace flat\core;

/**
 * configuration controller ideal for storing deployment details such as
 * filesystem paths, connection parameters, etc.
 *
 * @package    flat\core
 * @author     D. Bird <retran@gmail.com>
 * @copyright  Copyright (c) 2012-2014 Doug Bird. All Rights Reserved.
 * @version    0.1.0-alpha
 */
class config extends \flat\core
{
    
    /**
     * @var string ref identifier token
     */
    const REF_IDENTIFIER_TOKEN = 'CONFIG-REF';
    
    /**
     * @var string ref identifier delimiter
     */
    const REF_IDENTIFIER_DELIM = '::';
    
    /**
     * @var string ref basename delimiter
     */
    const REF_BASENAME_DELIM = '.';
    
    /**
     * @var string inline ref token
     */
    const INLINE_REF_TOKEN = '<%' . self::REF_IDENTIFIER_TOKEN . '-STRING' . self::REF_IDENTIFIER_DELIM;
    
    /**
     * @var string inline ref token delimiter
     */
    const INLINE_REF_DELIM = '%>';
    
    /**
     * @var string base directory for config files
     */
    private static $root_dir;
    
    /**
     * @var bool[] associative array with an element for every config namespace that has been loaded,
     *    element keys are config namespaces, element values are always <i>true</i>
     */
    private static $loaded = [];
    
    /**
     * @var array associative array of config values, element keys are the config key
     */
    private static $value = [];
    
    /**
     * @var bool[] associative array with an element for every config key that has been searched for,
     *    element keys are corresponding config keys, element values are always <i>true</i>
     */
    private static $searched = [];
    
    /**
     * @var mixed[][] associative array (2 dimension) of config ref values,
     *    1st dimension element keys are the corresponding basenames,
     *    2nd dimension element keys are the corresponding ref namespace
     *    2nd dimension element values are the config ref values
     */
    private static $ref_value = [];
    
    /**
     * @var mixed[] associative array of transformed config values, element keys are the corresponding config keys
     */
    private static $transformed_value = [];
    
    /**
     * @var string[] associative array with an element for every config ref file that has been attempted to have been loaded,
     *    but was inaccessible
     */
    private static $invalid_ref_basename = [];
    
    /**
     * @var string[] $invalid_ns filenames that previously failed to load
     */
    private static $invalid_ns = [];
    
    /**
     * tests if given base directory is useable
     *
     * @return void
     *
     * @param string $root_dir base directory path
     *
     * @throws config\exception\bad_base_dir if directory is not useable
     *
     */
    private static function test_base_dir(string $root_dir): void {
        if (empty($root_dir)) {
            throw new config\exception\bad_base_dir\is_empty();
        }
        if (!is_readable($root_dir)) {
            throw new config\exception\bad_base_dir\not_readable();
        }
        
        if (!is_dir($root_dir)) {
            throw new config\exception\bad_base_dir\not_dir();
        }
    }
    
    /**
     * Cache a config value
     *
     * @return void
     *
     * @param string $key config key
     * @param mixed $value value associated with given config key
     */
    private static function cache_value(string $key, $value): void {
        static::$value[$key] = $value;
    }
    
    /**
     * Get a config-reference value
     *
     * @return mixed Config-refernce value
     *
     * @param string $basename Config-refernce basename
     * @param string $prop Config-refernce property name
     * @param array|null $options associative array of options:<pre>
     * [
     *    'default' =>
     *       <b>mixed</b> (value to return if basename or reference is not found),
     *    'not_found_exception' =>
     *       <b>bool</b> (if value is <i>false</i>, an exception WILL NOT be thrown
     *       if the basename or reference is not found),
     * ]</pre>
     * @throws \flat\core\config\exception\not_ready
     * @throws \flat\core\config\exception\bad_key
     * @throws \flat\core\config\exception\bad_config_file
     * @throws \flat\core\config\exception\key_not_found
     */
    public static function get_ref_value(string $basename, string $prop, array $options = null) {
        
        $ref_value = static::enum_ref($basename);
        
        if (!key_exists($prop, $ref_value)) {
            if ($options!==null) {
                if (key_exists('default', $options)) {
                    return $options['default'];
                }
                if (isset($options['not_found_exception']) && $options['not_found_exception'] === false) {
                    return;
                }
            }
            throw new config\exception\key_not_found("$basename.$ns");
        }
        
        return $ref_value[$prop];
        
    }
    private static function get_transformed_value($rawval, $key) {
        if (is_array($rawval)) {
            foreach ($rawval as $k => $v) {
                $rawval[$k] = static::get_transformed_value($v, $key);
            }
            unset($v);
            unset($k);
            return $rawval;
        } else if (is_string($rawval)) {
            if (
                substr($rawval, 0, strlen(static::REF_IDENTIFIER_TOKEN . static::REF_IDENTIFIER_DELIM)) ==
                static::REF_IDENTIFIER_TOKEN .
                static::REF_IDENTIFIER_DELIM) {
                    $refsub = substr($rawval, strlen(static::REF_IDENTIFIER_TOKEN . static::REF_IDENTIFIER_DELIM));
                    if (false !== ($dotpos = strpos($refsub, static::REF_BASENAME_DELIM))) {
                        $basename = substr($refsub, 0, $dotpos);
                        $ns = substr($refsub, $dotpos + 1);
                        if (!empty($basename) && !empty($ns)) {
                            try {
                                return static::get_ref_value($basename, $ns);
                            } catch (config\exception\key_not_found $e) {
                                throw new config\exception\key_not_found("$key=" . static::REF_IDENTIFIER_TOKEN . static::REF_IDENTIFIER_DELIM . "$basename.$ns");
                            }
                        }
                    }
                } else {
                    $val = $rawval;
                    $offset = 0;
                    $str_replace = [];
                    for(;;) {
                        if (false !== ($pos1 = strpos($val, static::INLINE_REF_TOKEN, $offset))) {
                            
                            if (false !== ($pos2 = strpos($val, static::INLINE_REF_DELIM, $offset))) {
                                $offset = $pos2 + strlen(static::INLINE_REF_DELIM);
                                $refsub = substr($val, $pos1 + strlen(static::INLINE_REF_TOKEN));
                                if (false === ($dotpos = strpos($refsub, static::REF_BASENAME_DELIM))) {
                                    continue;
                                }
                                $basename = substr($refsub, 0, $dotpos);
                                $nslen = $pos2 - $pos1 - strlen($basename) - strlen(static::INLINE_REF_TOKEN) - 1;
                                $ns = substr($refsub, $dotpos + 1, $nslen);
                                $inline_config_ref = static::INLINE_REF_TOKEN . $basename . static::REF_BASENAME_DELIM . $ns . static::INLINE_REF_DELIM;
                                if (isset($str_replace[$inline_config_ref])) {
                                    continue;
                                }
                                $refval = static::get_ref_value($basename, $ns);
                                if (is_scalar($refval)) {
                                    $str_replace[$inline_config_ref] = is_bool($refval) ? $refval ? "true" : "false" : (string) $refval;
                                } else {
                                    if (false === ($str_replace[$inline_config_ref] = json_encode($refval, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_SLASHES))) {
                                        $str_replace[$inline_config_ref] = '';
                                    }
                                }
                            } else {
                                
                                break 1;
                            }
                        } else {
                            
                            break 1;
                        }
                    }
                    unset($inline_config_ref);
                    
                    array_walk($str_replace,
                        function (string $replace_with_string, string $inline_config_ref) use (&$val) {
                            $val = str_replace($inline_config_ref, $replace_with_string, $val);
                        });
                    
                    return $val;
                }
        }
        return $rawval;
    }
    
    /**
     * retrieve a config value
     *
     * @return mixed
     *
     * @param string $key config key
     * @param array|null $options associative array of options:<pre>
     * [
     *    'not_found_exception' =>
     *       <b>bool</b> (if value is <i>false</i>, an exception WILL NOT be thrown
     *       if the basename or reference is not found),
     * ]</pre>
     *
     * @throws config\exception\key_not_found thrown when there is no value
     *    associated with given config key if $options['not_found_exception']
     *    is not false
     */
    private static function get_value($key, array $options = null) {
        !isset(static::$searched[$key]) && static::$searched[$key] = true;
        
        if (isset(static::$value[$key])) {
            if (!isset(static::$transformed_value[$key])) {
                static::$transformed_value[$key] = static::get_transformed_value(static::$value[$key], $key);
            }
            return static::$transformed_value[$key];
        }
        
        if ($options !== null) {
            if (key_exists('default', $options)) {
            }
        }
        
        if ($options === null || !isset($options['not_found_exception']) || $options['not_found_exception'] !== false) throw new config\exception\key_not_found(
            $key);
    }
    
    /**
     * Enumerate values of a config namespace
     *
     * @param string $ns config key prefix
     *
     * @return array|null
     */
    public static function enum(string $ns): ?array {
        $ns = static::canonicalize_ns($ns);
        
        $cfg = static::get_config_arr($ns);
        
        if (!is_array($cfg)) {
            return null;
        }
        
        array_walk($cfg, function (&$v, $k) use ($ns) {
            $v = static::get("$ns/$k");
        });
            
            return $cfg;
    }
    
    /**
     * Canonicalize a config namespace
     *
     * @param string $ns namespace
     *
     * @return string canonicalized namespace
     */
    private static function canonicalize_ns(string $ns): string {
        $ns = str_replace("\\", "/", $ns);
        
        $ns = trim(trim($ns), "/");
        
        return $ns;
    }
    /**
     * Get the config root directory
     *
     * @return string config root directory path
     */
    private static function get_root_dir(): string {
        empty(static::$root_dir) && static::enable_auto_root_dir();
        return static::$root_dir;
    }
    
    /**
     * Convert a config namespace to a filename
     *
     * @return string filename
     */
    private static function ns2filename(string $ns): string {
        return str_replace("/", DIRECTORY_SEPARATOR, static::get_root_dir() . "/" . static::canonicalize_ns($ns) . ".php");
    }
    /**
     * Canonicalize a config ref basename
     *
     * @return string canonicalized basename
     */
    private static function canonicalize_basename(string $basename): string {
        $basename = str_replace("\\", "/", $basename);
        
        $basename = trim(trim($basename), "/");
        
        return $basename;
    }
    /**
     * Convert a config ref basename to a filename
     *
     * @return string file
     */
    private static function ref_basename2filename(string $basename): string {
        return str_replace("/", DIRECTORY_SEPARATOR, static::get_root_dir() . "/" . str_replace("\\", "/", $basename) . ".json");
    }
    
    /**
     * retrieve config value
     *
     * @return mixed
     *
     * @param string $key key associated with desired config value
     * @param array $options associative array of options:<pre>
     * [
     *    'default' =>
     *       <b>mixed</b> (value to return if unable to retrieve config value),
     *    'not_found_exception' =>
     *       <b>bool</b> (if value is <i>false</i>, an exception WILL NOT be thrown
     *       if unable to retrieve config value),
     * ]</pre>
     *
     * (optional) assoc array of options.
     *    string $options['default'] default value to return if cannot retrieve config value.
     *    bool $options['not_found_exception'] set to bool false to suppress exception thrown when key not found.
     *    string $options['key_name_base'] value to remove from the beginning of key-name; useful for inherited classes
     *       to get an option from their own namespace.
     *
     *
     * @throws \flat\core\config\exception\not_ready
     * @throws \flat\core\config\exception\bad_key
     * @throws \flat\core\config\exception\bad_config_file
     * @throws \flat\core\config\exception\key_not_found
     */
    public static function get($key, array $options = null) {
        if (empty(static::get_root_dir())) {
            try {
                static::enable_auto_root_dir();
            } catch (config\exception\not_ready $e) {
                if (key_exists('default', $options)) return $options['default'];
                throw $e;
            }
        }
        
        $key = static::canonicalize_path($key);
        
        if ($options !== null && key_exists('default', $options)) {
            
            $default = $options['default'];
            unset($options['default']);
            
            try {
                return static::get($key);
            } catch (config\exception\key_not_found $e) {
                return $default;
            }
        }
        
        if (!empty(static::$searched[$key])) return static::get_value($key, $options);
        
        $resource = explode("/", $key);
        
        var_dump($resource);
        var_dump($key);
        while (array_pop($resource)) {
            if (static::load_config(implode("/", $resource))) // concatonate path from current element list with implode()
                break 1;
        }
        
        static::$searched[$key] = true;
        
        return static::get_value($key, $options);
    }
    
    /**
     * Read config ref file
     *
     * @param string $filename filename
     * @throws \flat\core\config\exception\bad_config_file if file contents are not a valid JSON object
     *
     * @return array|null associative array of config ref values or <i>null</i> if basename did not exist
     */
    private static function read_ref(string $basename): array {
        
        if (isset(static::$invalid_ref_basename[$filename])) return null;
        
        $filename = static::ref_basename2filename($basename);
        
        if (!is_file($filename) || !is_readable($filename)) {
            static::$invalid_ref_basename[$filename] = true;
            return null;
        }
        
        if (false === ($doc = file_get_contents($filename))) {
            throw new config\exception\bad_config_file($filename, "failed to read config ref file");
        }
        
        if ((null === ($cfg = json_decode($doc))) || !is_object($cfg)) {
            if (JSON_ERROR_NONE === json_last_error()) {
                throw new config\exception\bad_config_file($filename, "config ref file must contain a JSON object");
            }
            throw new config\exception\bad_config_file($filename, "failed to parse as JSON");
        }
        
        return (array) $cfg;
    }
    
    /**
     * Get a namespace's config values
     *
     * @param string $ns config namespace
     *
     * @throws \flat\core\config\exception\bad_config_file if config file exists but does not return an array
     *
     * @return array|null
     */
    private static function get_config_arr(string $ns): ?array {
        if (isset(static::$invalid_ns[$ns])) return null;
        
        $filename = static::ns2filename($ns);
        
        if (!is_file($filename) || !is_readable($filename)) {
            static::$invalid_ns[$filename] = true;
            return null;
        }
        
        $cfg = include ($filename);
        
        if (!is_array($cfg)) {
            throw new config\exception\bad_config_file($filename, "config file must return an array");
        }
        
        return $cfg;
    }
    
    /**
     * Enumerate values of a config-reference basename
     *
     * @return array associative array of config ref values
     * @param string $basename basename
     *
     * @throws \flat\core\config\exception\bad_config_file if file contents are not a valid JSON object
     */
    public static function enum_ref(string $basename): array {
        if (isset(static::$ref_value[$basename])) {
            return static::$ref_value[$basename];
        }
        
        $ref_filename = static::ref_basename2filename($basename);
        
        if (!is_file($ref_filename) || !is_readable($ref_filename)) {
            return static::$ref_value[$basename] = [];
        }
        
        if (false === ($doc = file_get_contents($ref_filename))) {
            
            $error = error_get_last();
            
            throw new config\exception\bad_config_file($ref_filename, "read error: {$error['message']}");
        }
        
        if ((null === ($cfg = json_decode($doc))) || !is_object($cfg)) {
            if (JSON_ERROR_NONE === json_last_error()) {
                throw new config\exception\bad_config_file($filename, "must contain a JSON object");
            }
            throw new config\exception\bad_config_file($filename, "JSON error: ".json_last_error_msg());
        }
        
        return static::$ref_value[$basename] = (array) $cfg;
        
    }
    
    /**
     * Cache all values of a config namespace
     *
     * @param string $ns namespace
     *
     * @return bool <i>true</i> if successful, <i>false</i> otherwise
     */
    private static function load_config($ns): bool {
        if (isset(static::$loaded[$ns])) return static::$loaded[$ns];
        
        if (null === ($cfg = static::get_config_arr($ns))) {
            return static::$loaded[$ns] = false;
        }
        
        array_walk($cfg, function ($val, $ns) use ($ns) {
            static::cache_value("$ns/" . $key, $val);
        });
            
            return static::$loaded[$ns] = true;
    }
    
    /**
     * Attempts to automatically determine and set the root directory for config files
     *
     * @throws \flat\core\config\exception\not_ready
     */
    private static function enable_auto_root_dir() {
    }
    
    /**
     * Set root directory for config files
     *
     * @param string $root_dir system path of config base directory
     * @throws config\exception\bad_base_dir if directory is not useable
     * @return void
     */
    public static function set_base_dir(string $root_dir): void {
        static::test_base_dir($root_dir);
        static::get_root_dir() = $root_dir;
    }
}








