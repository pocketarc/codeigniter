<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2019 - 2022, CodeIgniter Foundation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package	CodeIgniter
 * @author	EllisLab Dev Team
 * @copyright	Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @copyright	Copyright (c) 2014 - 2019, British Columbia Institute of Technology
 * @copyright	Copyright (c) 2019 - 2022, CodeIgniter Foundation
 * @license	https://opensource.org/licenses/MIT	MIT License
 * @link	    https://codeigniter.com
 * @since	    Version 1.0.0
 * @filesource
 */
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * CI_Parser Class
 *
 * Extended version of the CodeIgniter Parser Library with additional features:
 *
 * New Features:
 * - Named parameters for helper functions (grouped into an associative array).
 * - Conversion of parameters in array notation using brackets, e.g. [1,2,3,4].
 * - Access to nested data using exclusively bracket notation.
 * - New {foreach(...)} ... {/foreach} tag for iterating over arrays.
 *
 * Examples in template:
 *   Direct access: {calculations[total][taxes][21.00]}
 *   Loop:
 *     {foreach(calculations[total][taxes] as key => value)}
 *       VAT at {key}%: {value}€<br>
 *     {/foreach}
 *
 *   Helper calls:
 *     {do_something(limit = 10, offset = 5)}
 *     {receive_array_items([1,2,3,4])}
 *
 * @package		CodeIgniter
 * @subpackage	Libraries
 * @category	Parser
 * @link		https://codeigniter.com/userguide3/libraries/parser.html
 */
class CI_Parser {

    /**
     * Left delimiter character for pseudo-variables.
     *
     * @var string
     */
    public $l_delim = '{';

    /**
     * Right delimiter character for pseudo-variables.
     *
     * @var string
     */
    public $r_delim = '}';

    /**
     * Reference to CodeIgniter instance.
     *
     * @var object
     */
    protected $CI;

    // --------------------------------------------------------------------

    /**
     * Class constructor.
     *
     * Retrieves the CodeIgniter instance and logs a message.
     *
     * @return void
     */
    public function __construct()
    {
        $this->CI =& get_instance();
        log_message('info', 'Parser Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Parse a template.
     *
     * Loads the view and processes pseudo-variables (including loops, foreach blocks,
     * nested data, switch-case blocks, conditionals, and helper calls) using the given data.
     *
     * @param string  $template The template view name.
     * @param array   $data     Data array.
     * @param boolean $return   Whether to return the parsed template or output it.
     * @return string
     */
    public function parse($template, $data, $return = FALSE)
    {
        // Load the view as a string.
        $template = $this->CI->load->view($template, $data, TRUE);

        return $this->_parse($template, $data, $return);
    }

    // --------------------------------------------------------------------

    /**
     * Parse a string.
     *
     * Processes pseudo-variables contained in the specified string using the data array.
     *
     * @param string  $template Template string.
     * @param array   $data     Data array.
     * @param boolean $return   Whether to return the parsed string or output it.
     * @return string
     */
    public function parse_string($template, $data, $return = FALSE)
    {
        return $this->_parse($template, $data, $return);
    }

    // --------------------------------------------------------------------

    /**
     * Set the left/right variable delimiters.
     *
     * @param string $l Left delimiter.
     * @param string $r Right delimiter.
     * @return void
     */
    public function set_delimiters($l = '{', $r = '}')
    {
        $this->l_delim = $l;
        $this->r_delim = $r;
    }

    // --------------------------------------------------------------------
    // Main Parsing Function
    // --------------------------------------------------------------------

    /**
     * Parses the template with the given data.
     *
     * This is the main entry point for parsing templates. It first processes traditional loops,
     * then replaces object, array, and simple placeholders. It further processes helper functions,
     * foreach blocks, nested array paths, switch-case blocks, and conditionals.
     *
     * @param string  $template The template content.
     * @param array   $data     Data array.
     * @param boolean $return   Whether to return the parsed template or output it.
     * @return mixed
     */
    protected function _parse($template, $data, $return = FALSE)
    {
        if ($template === '')
        {
            return FALSE;
        }

        // Merge passed data with variables loaded by CodeIgniter.
        $data = array_merge($data, $this->CI->load->get_vars());

        // Process traditional loops (for)
        $template = $this->_parse_loops($template, TRUE);

        // Process data variables: objects, arrays, and single placeholders.
        $replace = array();
        foreach ($data as $key => $val)
        {
            if (is_object($val))
            {
                $replace = array_merge($replace, $this->_parse_object($key, $val, $template));
            }
            elseif (is_array($val))
            {
                $replace = array_merge($replace, $this->_parse_pair($key, $val, $template));
            }
            else
            {
                $replace = array_merge($replace, $this->_parse_single($key, (string)$val, $template));
            }
        }

        // Replace found pseudo-variables.
        foreach ($replace as $from => $to)
        {
            $template = str_ireplace($from, (!is_null($to) ? $to : '%EMPTY_VAR%'), $template);
        }

        // Cleanup and process extended structures.
        $template = $this->_replace_unparsed($template);
        $template = $this->_parse_helpers($template, $data);
        // Process the new foreach tag before nested paths.
        $template = $this->_parse_foreach($template, $data);
        $template = $this->_parse_nested_paths($template, $data);
        $template = $this->_parse_switch($template, TRUE);
        $template = $this->_parse_conditionals($template, TRUE);
        $template = $this->_parse_helpers($template, $data);
        $template = $this->_remove_unparsed($template);

        if ($return === FALSE)
        {
            $this->CI->output->append_output($template);
        }
        else
        {
            return $template;
        }

        return $template;
    }

    // --------------------------------------------------------------------
    // Basic Placeholder Parsing
    // --------------------------------------------------------------------

    /**
     * Parses a single key/value pair.
     *
     * @param string $key    The key.
     * @param string $val    The value.
     * @param string $string Template content.
     * @return array Replacement array.
     */
    protected function _parse_single($key, $val, $string)
    {
        return array($this->l_delim.$key.$this->r_delim => (string) $val);
    }

    /**
     * Parses paired array blocks.
     *
     * Processes blocks with an opening and closing tag for array data. If the array is not
     * multi-dimensional, it is first converted into an array of arrays containing keys 'key' and 'value'.
     *
     * @param string $variable The variable name.
     * @param array  $data     Data array.
     * @param string $string   Template content.
     * @return array Replacement array.
     */
    protected function _parse_pair($variable, $data, $string)
    {
        $replace = array();
        if (strpos($variable, '[') !== false)
        {
            $extracted = $this->_get_nested_value_from_brackets($variable, $data);
            if ($extracted !== null)
            {
                $data = $extracted;
            }
        }

        if (!empty($data) && !is_array(current($data)))
        {
            $new_data = array();
            foreach ($data as $k => $v)
            {
                $new_data[] = array('key' => $k, 'value' => $v);
            }
            $data = $new_data;
        }

        preg_match_all('#'.preg_quote($this->l_delim.$variable.$this->r_delim).'(.+?)'.preg_quote($this->l_delim.'/'.$variable.$this->r_delim).'#s', $string, $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            $str = '';
            foreach ($data as $pos => $row)
            {
                $temp = array();
                foreach ($row as $key => $val)
                {
                    if (is_object($val))
                    {
                        $pair = $this->_parse_object($key, $val, $match[1]);
                        if (!empty($pair))
                        {
                            $temp = array_merge($temp, $pair);
                        }
                        continue;
                    }
                    elseif (is_array($val))
                    {
                        $pair = $this->_parse_pair($key, $val, $match[1]);
                        if (!empty($pair))
                        {
                            $temp = array_merge($temp, $pair);
                        }
                        continue;
                    }

                    $temp[$this->l_delim.$key.$this->r_delim] = (!empty($val) ? $val : '%EMPTY_VAR%');
                }

                $str .= strtr($match[1], $temp);
                $str = preg_replace('#'.$this->l_delim.'index in '.$variable.$this->r_delim.'#', $pos, $str);
            }

            $replace[$match[0]] = $str;
        }

        return $replace;
    }

    // --------------------------------------------------------------------
    // Data Access Functions
    // --------------------------------------------------------------------

    /**
     * Retrieve a nested value from an array using bracket notation.
     *
     * For example, given "calculations[total][taxes]", this function extracts the nested data.
     *
     * @param string $variable Variable name with nested keys.
     * @param array  $data     Data array.
     * @return mixed
     */
    protected function _get_nested_value_from_brackets($variable, $data)
    {
        preg_match_all('/[a-zA-Z0-9_.]+/', $variable, $matches);
        $parts = $matches[0];
        foreach ($parts as $part)
        {
            if (is_array($data) && array_key_exists($part, $data))
            {
                $data = $data[$part];
            }
            else
            {
                return null;
            }
        }
        return $data;
    }

    /**
     * Parses object properties or methods used as placeholders in the template.
     *
     * For example: {object.method} or {object.property}.
     *
     * @param string $key      The object key.
     * @param object $val      The object instance.
     * @param string $template The template content.
     * @return array Replacement array.
     */
    protected function _parse_object($key, $val, $template)
    {
        $replace = array();
        preg_match_all('#'.preg_quote($this->l_delim).$key.'\.'.'(.+?)'.preg_quote($this->r_delim).'#', $template, $matches, PREG_SET_ORDER);

        foreach ($matches as $match)
        {
            $class = $val;
            $explode = explode('.', $match[1]);
            $count = count($explode);
            if ($count > 1)
            {
                $attr = $explode[$count - 1];
                array_pop($explode);
                foreach ($explode as $e)
                {
                    $class = $class->$e;
                }
            }
            else
            {
                $attr = $match[1];
            }

            if (is_object($class))
            {
                if (method_exists($class, $attr))
                {
                    $replace[$match[0]] = $class->$attr();
                }
                elseif (property_exists($class, $attr))
                {
                    $replace[$match[0]] = $class->$attr;
                }
                else
                {
                    $replace[$match[0]] = '%EMPTY_VAR%';
                }
            }
            else
            {
                $replace[$match[0]] = '%EMPTY_VAR%';
            }
        }

        return $replace;
    }

    // --------------------------------------------------------------------
    // Cleanup Functions
    // --------------------------------------------------------------------

    /**
     * Replaces unparsed template blocks with a placeholder.
     *
     * Searches for unparsed tags and replaces them with '%EMPTY_VAR%' so they can be removed later.
     *
     * @param string $template Template content.
     * @return string
     */
    protected function _replace_unparsed($template)
    {
        preg_match_all('#('.$this->l_delim.'(\w+)'.$this->r_delim.'(.+?)'.$this->l_delim.'\/(\2)'.$this->r_delim.')#sU', $template, $unparsed, PREG_SET_ORDER);
        if (!empty($unparsed))
        {
            foreach ($unparsed as $u)
            {
                $template = str_ireplace($u[0], '%EMPTY_VAR%', $template);
            }
        }

        preg_match_all('#'.$this->l_delim.'\w+'.$this->r_delim.'#sU', $template, $unparsed, PREG_SET_ORDER);
        if (!empty($unparsed))
        {
            foreach ($unparsed as $u)
            {
                // Exclude tags {else}, {break}, {default}, {key}, and {value}
                if (!in_array($u[0], array(
                    $this->l_delim.'else'.$this->r_delim,
                    $this->l_delim.'break'.$this->r_delim,
                    $this->l_delim.'default'.$this->r_delim,
                    '{key}',
                    '{value}'
                )))
                {
                    $template = str_ireplace($u[0], '%EMPTY_VAR%', $template);
                }
            }
        }

        return $template;
    }

    /**
     * Removes the placeholders for empty variables.
     *
     * @param string $template Template content.
     * @return string
     */
    protected function _remove_unparsed($template)
    {
        return str_ireplace('%EMPTY_VAR%', '', $template);
    }

    // --------------------------------------------------------------------
    // Helper Function Parsing
    // --------------------------------------------------------------------

    /**
     * Processes helper function calls in the template.
     *
     * Syntax example: {helperFunction(arg1, arg2)}
     *
     * Supports:
     * - Named parameters (grouped into an associative array)
     * - Array notation parameters, e.g. [1,2,3,4]
     *
     * @param string $template Template content.
     * @param array  $data     Data array.
     * @return string
     */
    protected function _parse_helpers($template, $data)
    {
        preg_match_all('#'.$this->l_delim.'(\w+)\(([^{}]*)\)'.$this->r_delim.'#s', $template, $helpers, PREG_SET_ORDER);

        if (!empty($helpers))
        {
            foreach ($helpers as $helper)
            {
                $code = $helper[0];
                $func = $helper[1];
                $args_string = $helper[2];

                // Process any nested tags inside the arguments.
                $args_string = $this->_parse($args_string, $data, TRUE);

                $args = $this->_parse_helper_args($args_string, $data);

                if (function_exists($func))
                {
                    try
                    {
                        $return = call_user_func_array($func, $args);

                        if (is_string($return) && strpos($return, $this->l_delim) !== false)
                        {
                            $return = $this->_parse_helpers($return, $data);
                        }

                        $template = str_replace($code, $return, $template);
                    }
                    catch (Exception $error)
                    {
                        // Optional error handling.
                    }
                }
            }
        }

        return $template;
    }

    /**
     * Parses helper function arguments from a string.
     *
     * Supports:
     * - A single array notation wrapped in square brackets, e.g. [1,2,3,4]
     * - Comma-separated named parameters, e.g. limit = 10, offset = 5
     * - Comma-separated positional parameters.
     *
     * @param string $args_string The arguments string.
     * @param array  $data        Data array.
     * @return array
     */
    protected function _parse_helper_args($args_string, $data)
    {
        if (!empty($args_string))
        {
            // If the string starts with '[' and ends with ']', treat it as an array.
            if (substr($args_string, 0, 1) === '[' && substr($args_string, -1) === ']')
            {
                $inner = substr($args_string, 1, -1);
                $result = array_map('trim', explode(',', $inner));
                return array($result);
            }

            $parts = explode(',', $args_string);
            $all_named = true;
            foreach ($parts as $part)
            {
                if (strpos($part, '=') === false)
                {
                    $all_named = false;
                    break;
                }
            }
            if ($all_named)
            {
                $assoc = array();
                foreach ($parts as $part)
                {
                    $sub = explode('=', $part, 2);
                    $key = trim($sub[0]);
                    $value = trim($sub[1]);
                    $value = trim($value, "\"'");
                    if (substr($value, 0, 1) === '[' && substr($value, -1) === ']')
                    {
                        $inner = substr($value, 1, -1);
                        $arr = array_map('trim', explode(',', $inner));
                        $assoc[$key] = $arr;
                    }
                    else
                    {
                        $assoc[$key] = $value;
                    }
                }
                return array($assoc);
            }
            else
            {
                $result = array();
                foreach ($parts as $part)
                {
                    $value = trim($part);
                    if (substr($value, 0, 1) === '[' && substr($value, -1) === ']')
                    {
                        $inner = substr($value, 1, -1);
                        $result[] = array_map('trim', explode(',', $inner));
                    }
                    else
                    {
                        $result[] = $value;
                    }
                }
                return $result;
            }
        }
        return array();
    }

    // --------------------------------------------------------------------
    // Control Structures Parsing
    // --------------------------------------------------------------------

    /**
     * Processes traditional for loops in the template.
     *
     * Syntax: {for(variable from start to end step increment)} ... {/for}
     *
     * @param string  $template   Template content.
     * @param boolean $preprocess Flag for preprocessing.
     * @return string
     */
    protected function _parse_loops($template, $preprocess = FALSE)
    {
        if ($preprocess)
        {
            $for_pattern = $this->l_delim.'for ';
            $endfor_pattern = $this->l_delim.'\/for'.$this->r_delim;

            preg_match_all('#'.$for_pattern.'|'.$endfor_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($preprocess as $p)
                {
                    if ($p[0] === $for_pattern)
                    {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$for_pattern.'#', $this->l_delim.'for'.$count.' ', $template, 1);
                    }
                    else
                    {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endfor_pattern.'#', $this->l_delim.'/for'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        preg_match_all('#'.$this->l_delim.'for(\d+) (\w+) from (\d+) to (\d+) step (\d+)'.$this->r_delim.'(.+?)'.$this->l_delim.'/for(\1)'.$this->r_delim.'#s', $template, $loops, PREG_SET_ORDER);
        if (!empty($loops))
        {
            foreach ($loops as $loop)
            {
                $output = '';
                $display = $loop[5];

                for ($i = $loop[2]; $i <= $loop[3]; $i += $loop[4])
                {
                    $output .= str_replace($this->l_delim.$loop[1].$this->r_delim, $i, $display);
                }

                $template = str_replace($loop[0], $output, $template);
            }
        }

        return $template;
    }

    /**
     * Processes new foreach blocks.
     *
     * Syntax: {foreach(calculations[total][taxes] as key => value)} ... {/foreach}
     *
     * Iterates over the array obtained from the specified path and replaces {key} and {value}
     * in the block.
     *
     * @param string $template Template content.
     * @param array  $data     Data array.
     * @return string
     */
    protected function _parse_foreach($template, $data)
    {
        $template = preg_replace_callback(
            '/\{foreach\((.*?)\)\}(.*?)\{\/foreach\}/is',
            function($matches) use ($data) {
                // $matches[1]: expression, e.g. "calculations[total][taxes] as key => value"
                // $matches[2]: inner block content.
                $expr = trim($matches[1]);
                $parts = preg_split('/\s+as\s+/i', $expr);
                if (count($parts) != 2) return '';
                $varPath = trim($parts[0]); // e.g. "calculations[total][taxes]"
                // Always use {key} and {value} regardless of the names provided.
                $array = $this->_get_nested_value_from_brackets($varPath, $data);
                $result = '';
                if (is_array($array)) {
                    if (!empty($array) && !is_array(current($array))) {
                        $temp = array();
                        foreach ($array as $k => $v) {
                            $temp[] = array('key' => $k, 'value' => $v);
                        }
                        $array = $temp;
                    }
                    foreach ($array as $row) {
                        $temp = $matches[2];
                        // Replace {key} and {value}
                        $temp = str_replace('{key}', isset($row['key']) ? $row['key'] : '', $temp);
                        $temp = str_replace('{value}', isset($row['value']) ? $row['value'] : '', $temp);
                        $result .= $temp;
                    }
                }
                return $result;
            },
            $template
        );
        return $template;
    }

    /**
     * Processes nested array paths.
     *
     * Handles blocks with bracket notation such as:
     *   {calculations[total][taxes]} ... {/calculations[total][taxes]}
     * and also processes simple placeholders without a closing tag.
     *
     * @param string $template Template content.
     * @param array  $data     Data array.
     * @return string
     */
    protected function _parse_nested_paths($template, $data)
    {
        // Process loop blocks with bracket notation.
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}(.*?)\{\/\1\}/is', function($matches) use ($data) {
            $tag = $matches[1];   // e.g. "calculations[total][taxes]"
            $inner = $matches[2];
            $array = $this->_get_nested_value_from_brackets($tag, $data);
            $replacement = '';
            if (is_array($array))
            {
                if (!empty($array) && !is_array(current($array)))
                {
                    $temp = array();
                    foreach ($array as $k => $v)
                    {
                        $temp[] = array('key' => $k, 'value' => $v);
                    }
                    $array = $temp;
                }
                foreach ($array as $row)
                {
                    $temp = $inner;
                    // Replace {key} and {value}.
                    $temp = str_replace('{key}', isset($row['key']) ? $row['key'] : '', $temp);
                    $temp = str_replace('{value}', isset($row['value']) ? $row['value'] : '', $temp);
                    $replacement .= $temp;
                }
            }
            else
            {
                $replacement = ($array !== null) ? $array : '';
            }
            return $replacement;
        }, $template);

        // Process simple placeholders (without a closing tag).
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}/i', function($matches) use ($data) {
            $tag = $matches[1];
            $value = $this->_get_nested_value_from_brackets($tag, $data);
            return ($value !== null) ? $value : '';
        }, $template);

        return $template;
    }

    /**
     * Processes switch-case blocks.
     *
     * Syntax:
     *   {switch condition}
     *     {case value} ... {break}
     *     {default} ... {break}
     *   {/switch}
     *
     * @param string  $template   Template content.
     * @param boolean $preprocess Flag for preprocessing.
     * @return string
     */
    protected function _parse_switch($template, $preprocess = FALSE)
    {
        $currency = '&pound;';

        if ($preprocess)
        {
            $switch_pattern = $this->l_delim.'switch ';
            $endswitch_pattern = $this->l_delim.'\/switch'.$this->r_delim;

            preg_match_all('#'.$switch_pattern.'|'.$endswitch_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($preprocess as $p)
                {
                    if ($p[0] === $switch_pattern)
                    {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$switch_pattern.'#', $this->l_delim.'switch'.$count.' ', $template, 1);
                    }
                    else
                    {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endswitch_pattern.'#', $this->l_delim.'/switch'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        preg_match_all('#'.$this->l_delim.'switch(\d+) (.+)'.$this->r_delim.'(.+)'.$this->l_delim.'/switch(\1)'.$this->r_delim.'#sU', $template, $conditionals, PREG_SET_ORDER);
        if (!empty($conditionals))
        {
            foreach ($conditionals as $conditional)
            {
                $code = $conditional[0];
                $statement = str_replace($currency, '', $conditional[2]);
                $output = '';
                $sub = $conditional[3];
                preg_match_all('#'.$this->l_delim.'case (.+)'.$this->r_delim.'(.+)'.$this->l_delim.'break'.$this->r_delim.'#sU', $sub, $cases, PREG_SET_ORDER);
                if (!empty($cases))
                {
                    foreach ($cases as $case)
                    {
                        if ($statement == $case[1])
                        {
                            $output = $case[2];
                            break;
                        }
                    }
                }
                if ($output == '')
                {
                    preg_match('#'.$this->l_delim.'default'.$this->r_delim.'(.+)'.$this->l_delim.'break'.$this->r_delim.'#sU', $sub, $default);
                    if (!empty($default))
                    {
                        $output = $default[1];
                    }
                }

                $template = str_replace($code, $output, $template);
            }
        }

        return $template;
    }

    /**
     * Processes if-else conditionals.
     *
     * Syntax:
     *   {if condition} ... {else} ... {/if}
     *
     * Evaluates the condition (supports basic comparisons) and outputs the appropriate block.
     *
     * @param string  $template   Template content.
     * @param boolean $preprocess Flag for preprocessing.
     * @return string
     */
    protected function _parse_conditionals($template, $preprocess = FALSE)
    {
        $currency = '&pound;';

        if ($preprocess)
        {
            $if_pattern = $this->l_delim.'if ';
            $else_pattern = $this->l_delim.'else'.$this->r_delim;
            $endif_pattern = $this->l_delim.'\/if'.$this->r_delim;

            preg_match_all('#'.$if_pattern.'|'.$else_pattern.'|'.$endif_pattern.'#sU', $template, $preprocess, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($preprocess as $p)
                {
                    if ($p[0] === $if_pattern)
                    {
                        ++$count;
                        $last_count[] = $count;
                        $template = preg_replace('#'.$if_pattern.'#', $this->l_delim.'if'.$count.' ', $template, 1);
                    }
                    elseif ($p[0] === $else_pattern)
                    {
                        $last = array_pop($last_count);
                        $last_count[] = $last;
                        $template = preg_replace('#'.$else_pattern.'#', $this->l_delim.'else'.$last.$this->r_delim, $template, 1);
                    }
                    else
                    {
                        $last = array_pop($last_count);
                        $template = preg_replace('#'.$endif_pattern.'#', $this->l_delim.'/if'.$last.$this->r_delim, $template, 1);
                    }
                }
            }
        }

        preg_match_all('#'.$this->l_delim.'if(\d+) (.+)'.$this->r_delim.'(.+)'.$this->l_delim.'\/if(\1)'.$this->r_delim.'#sU', $template, $conditionals, PREG_SET_ORDER);

        if (!empty($conditionals))
        {
            foreach ($conditionals as $conditional)
            {
                $output = $conditional[3];
                $statement = str_replace($currency, '', $conditional[2]);

                preg_match('#(.+\s?)(>|>=|<>|!=|==|<=|<)(.+\s?)#', $statement, $comparison);

                if (!empty($comparison))
                {
                    $a = (trim($comparison[1]) != '') ? str_replace('"', '', trim($comparison[1])) : FALSE;
                    $b = (trim($comparison[3]) != '') ? str_replace('"', '', trim($comparison[3])) : FALSE;
                    $operator = trim($comparison[2]);

                    if ($a == 'true' or $a == 'TRUE')
                    {
                        $a = 1;
                    }
                    elseif ($a == 'false' or $a == 'FALSE')
                    {
                        $a = 0;
                    }
                    if ($b == 'true' or $b == 'TRUE')
                    {
                        $b = 1;
                    }
                    elseif ($b == 'false' or $b == 'FALSE')
                    {
                        $b = 0;
                    }

                    switch ($operator)
                    {
                        case '>' :
                            $output = ($a > $b) ? $output : '';
                            break;
                        case '>=' :
                            $output = ($a >= $b) ? $output : '';
                            break;
                        case '<>' :
                            $output = ($a <> $b) ? $output : '';
                            break;
                        case '!=' :
                            $output = ($a != $b) ? $output : '';
                            break;
                        case '==' :
                            $output = ($a == $b) ? $output : '';
                            break;
                        case '<=' :
                            $output = ($a <= $b) ? $output : '';
                            break;
                        case '<' :
                            $output = ($a < $b) ? $output : '';
                            break;
                    }
                }
                else
                {
                    $output = (!empty($statement) && $statement != '0' && $statement != '%EMPTY_VAR%') ? $output : '';
                }

                $else = preg_split('#'.$this->l_delim.'else'.$conditional[1].$this->r_delim.'#', $conditional[3]);
                if (count($else) > 1)
                {
                    $output = ($output == '') ? $else[1] : $else[0];
                }

                $template = str_replace($conditional[0], $output, $template);
            }
            $template = $this->_parse_conditionals($template);
        }

        return $template;
    }
}
