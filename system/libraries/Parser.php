<?php
/**
 * CodeIgniter
 *
 * An open source application development framework for PHP
 *
 * This file has been modified to combine the original CI_Parser functionality
 * with enhanced features (advanced foreach loops with nested contexts,
 * bracket notation for nested data, conditionals, switch-case blocks, loops,
 * helper functions, and more).
 *
 * The methods from the child class override or extend the original CI_Parser
 * methods, so that the new CI_Parser class can be used as a drop‑in replacement.
 *
 * @package     CodeIgniter
 * @author      EllisLab Dev Team / Modified by Juan Luis Lopez Ruiz and based on https://github.com/gccloud/parser changes
 * @license     https://opensource.org/licenses/MIT MIT License
 * @link        https://codeigniter.com
 * @since       Version 1.0.0
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Parser {

    /**
     * Left delimiter for template variables.
     *
     * @var string
     */
    public $l_delim = '{';

    /**
     * Right delimiter for template variables.
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
     * Class Constructor.
     *
     * Retrieves the global CodeIgniter instance and logs the initialization.
     *
     * @return void
     */
    public function __construct()
    {
        // Get the global CodeIgniter instance.
        $this->CI =& get_instance();
        log_message('info', 'CI_Parser (Enhanced) Class Initialized');
    }

    // --------------------------------------------------------------------

    /**
     * Parse a Template View File.
     *
     * Loads the specified view file, processes the template by replacing
     * pseudo-variables, tag pairs, and advanced foreach loops, and then
     * either outputs or returns the parsed template.
     *
     * @param string $template The view file to load.
     * @param array  $data     The data array for replacements.
     * @param bool   $return   If TRUE, returns the parsed template; if FALSE, appends it to output.
     * @return string          The fully parsed template.
     */
    public function parse($template, $data, $return = FALSE)
    {
        // Load the view file into a string.
        $template = $this->CI->load->view($template, $data, TRUE);

        // Process and return (or output) the template.
        return $this->_parse($template, $data, $return);
    }

    // --------------------------------------------------------------------

    /**
     * Parse a Template String.
     *
     * Processes a template string (without loading a view file) by replacing
     * pseudo-variables, tag pairs, and advanced foreach loops.
     *
     * @param string $template The template string.
     * @param array  $data     The data array for replacements.
     * @param bool   $return   If TRUE, returns the parsed template; otherwise, appends it to output.
     * @return string          The fully parsed template.
     */
    public function parse_string($template, $data, $return = FALSE)
    {
        return $this->_parse($template, $data, $return);
    }

    // --------------------------------------------------------------------

    /**
     * Main Parse Function.
     *
     * This method processes the template in the following order:
     *  1. Merge the passed data with CodeIgniter's global variables.
     *  2. Process traditional "for" loops.
     *  3. Process advanced nested foreach blocks (with dynamic context).
     *  4. Build a replacement array for simple variables and tag pairs.
     *  5. Replace placeholders using strtr.
     *  6. Process helper functions, nested paths, switch-case, and conditionals.
     *
     * @param string $template The template string to be parsed.
     * @param array  $data     The data array used for replacements.
     * @param bool   $return   If TRUE, returns the final parsed template; if FALSE, appends it to output.
     * @return string          The fully parsed template.
     */
    protected function _parse($template, $data, $return = FALSE)
    {
        if ($template === '')
        {
            return FALSE;
        }

        // Merge the passed data with CodeIgniter's global variables.
        $data = array_merge($data, $this->CI->load->get_vars());

        // Process traditional "for" loops (if present).
        $template = $this->_parse_loops($template, TRUE);

        // Process advanced nested foreach blocks.
        $template = $this->_parse_foreach($template, $data);

        // Build a replacement array for simple placeholders and tag pairs.
        $replace = array();
        foreach ($data as $key => $val)
        {
            $replace = array_merge(
                $replace,
                is_object($val)
                    ? $this->_parse_object($key, $val, $template)
                    : (is_array($val)
                        ? $this->_parse_pair($key, $val, $template)
                        : $this->_parse_single($key, (string)$val))
            );
        }

        // Replace simple placeholders using strtr.
        $template = strtr($template, $replace);

        // Process helper functions and nested paths.
        $template = $this->_parse_helpers($template, $data);
        $template = $this->_parse_nested_paths($template, $data);
        $template = $this->_parse_switch($template, TRUE);
        $template = $this->_parse_conditionals($template, TRUE);
        $template = $this->_parse_helpers($template, $data);

        // Optionally, one could remove any remaining unparsed tags.
        $template = $this->_remove_unparsed($template, $data);

        // Either output the template or return it.
        if ($return === FALSE)
        {
            $this->CI->output->append_output($template);
        }
        return $template;
    }

    // --------------------------------------------------------------------

    /**
     * Parse a Single Variable Placeholder.
     *
     * Replaces a simple variable placeholder (e.g., {title}) with its corresponding value.
     *
     * @param string $key    The variable name.
     * @param string $val    The value to substitute.
     * @return array         An associative array mapping the placeholder to its value.
     */
    protected function _parse_single($key, $val)
    {
        return array($this->l_delim.$key.$this->r_delim => (string)$val);
    }

    // --------------------------------------------------------------------

    /**
     * Parse Tag Pairs.
     *
     * Processes tag pairs in the template (e.g., {some_tag} ... {/some_tag})
     * by replacing them with the parsed content based on the data provided.
     *
     * This version supports arrays of data and recursive processing of nested pairs.
     *
     * @param string $variable The tag name.
     * @param array  $data     The data array for this tag.
     * @param string $string   The template string.
     * @return array           An associative array mapping full tag blocks to their replacements.
     */
    protected function _parse_pair($variable, $data, $string)
    {
        $replace = array();
        // If the variable contains bracket notation, extract the nested data.
        if (strpos($variable, '[') !== false)
        {
            $extracted = $this->_get_nested_value_from_brackets($variable, $data);
            if ($extracted !== null)
            {
                $data = $extracted;
            }
        }

        // If the array is not an array of arrays, reformat it.
        if (!empty($data) && !is_array(current($data)))
        {
            $new_data = array();
            foreach ($data as $k => $v)
            {
                $new_data[] = array('key' => $k, 'value' => $v);
            }
            $data = $new_data;
        }

        // Build a regular expression to find tag pairs.
        preg_match_all('#'.preg_quote($this->l_delim.$variable.$this->r_delim).'(.+?)'.preg_quote($this->l_delim.'/'.$variable.$this->r_delim).'#s', $string, $matches, PREG_SET_ORDER);

        // Process each match.
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
                        // Process object properties/methods.
                        $pair = $this->_parse_object($key, $val, $match[1]);
                        if (!empty($pair))
                        {
                            $temp = array_merge($temp, $pair);
                        }
                        continue;
                    }
                    elseif (is_array($val))
                    {
                        // Process nested arrays recursively.
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

    /**
     * Process Conditionals.
     *
     * Parses and processes conditional blocks in the template. Conditional blocks have
     * the syntax:
     *   {if condition} ... {else} ... {/if}
     * and support common comparison operators.
     *
     * @param string $template   The template string containing conditionals.
     * @param bool   $preprocess If TRUE, preprocesses the conditionals to assign unique IDs.
     * @return string            The template with conditionals processed.
     */
    protected function _parse_conditionals($template, $preprocess = FALSE)
    {
        if ($preprocess)
        {
            $if_pattern = $this->l_delim.'if ';
            $else_pattern = $this->l_delim.'else'.$this->r_delim;
            $endif_pattern = $this->l_delim.'\/if'.$this->r_delim;

            preg_match_all('#'.$if_pattern.'|'.$else_pattern.'|'.$endif_pattern.'#sU', $template, $matchesPre, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($matchesPre as $p)
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
                $statement = $conditional[2];

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

    // --------------------------------------------------------------------

    /**
     * Process Switch-Case Blocks.
     *
     * Processes switch-case blocks in the template. The syntax is:
     *   {switch expression}
     *     {case value} ... {break}
     *     {default} ... {break}
     *   {/switch}
     *
     * @param string $template   The template string.
     * @param bool   $preprocess If TRUE, preprocesses switch tags for unique identifiers.
     * @return string            The template with switch blocks processed.
     */
    protected function _parse_switch($template, $preprocess = FALSE)
    {
        if ($preprocess)
        {
            $switch_pattern = $this->l_delim.'switch ';
            $endswitch_pattern = $this->l_delim.'\/switch'.$this->r_delim;

            preg_match_all('#'.$switch_pattern.'|'.$endswitch_pattern.'#sU', $template, $matchesPre, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($matchesPre as $p)
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
                $statement = $conditional[2];
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

    // --------------------------------------------------------------------

    /**
     * Process Traditional For Loops.
     *
     * Parses and processes for loops with the syntax:
     *   {for var from start to end step increment} ... {/for}
     *
     * @param string $template   The template string containing for loops.
     * @param bool   $preprocess If TRUE, preprocesses for loops with unique identifiers.
     * @return string            The template with for loops processed.
     */
    protected function _parse_loops($template, $preprocess = FALSE)
    {
        if ($preprocess)
        {
            $for_pattern = $this->l_delim.'for ';
            $endfor_pattern = $this->l_delim.'\/for'.$this->r_delim;

            preg_match_all('#'.$for_pattern.'|'.$endfor_pattern.'#sU', $template, $matchesPre, PREG_SET_ORDER);

            if (!empty($preprocess))
            {
                $count = 0;
                $last_count = array();
                foreach ($matchesPre as $p)
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

                for ($i = $loop[2]; $i <= $loop[3]; $i = $i + $loop[4])
                {
                    $output .= str_replace($this->l_delim.$loop[1].$this->r_delim, $i, $display);
                }

                $template = str_replace($loop[0], $output, $template);
            }
        }

        return $template;
    }

    // --------------------------------------------------------------------

    /**
     * Process Advanced Nested Foreach Loops.
     *
     * This method processes foreach blocks with the syntax:
     *   {foreach(array as key => value)} ... {/foreach}
     * or
     *   {foreach(array as value)} ... {/foreach}
     *
     * It uses a recursive regular expression (via the (?R) subpattern) to support nested foreach blocks.
     *
     * @param string $template The template string containing foreach blocks.
     * @param array  $data     The context data for variable replacements.
     * @return string          The template with all foreach blocks processed.
     */
    protected function _parse_foreach($template, $data)
    {
        // Recursive regular expression to capture nested foreach blocks.
        $pattern = '/
            \{foreach\(
                (.*?)           # 1: Internal expression (e.g., "array as key => value")
            \)\}               
            (                  # 2: Block content (may include nested blocks)
                (?:
                    (?> [^{]+ )        # Text without "{"
                    |
                    \{(?!\/?foreach)    # A "{" not starting a foreach tag
                    |
                    (?R)               # Recursive call for nested foreach
                )*
            )
            \{\/foreach\}      # Closing foreach tag
        /isx';

        $template = preg_replace_callback($pattern, function ($matches) use ($data) {
            // $matches[1]: The internal expression (e.g., "fruits_by_color as color => fruitList")
            // $matches[2]: The block content.
            $expr = trim($matches[1]);
            $blockContent = $matches[2];

            // Expecting format: "variable as key => value" or "variable as value"
            $parts = preg_split('/\s+as\s+/i', $expr);
            if (count($parts) != 2) {
                return '';
            }
            $varPath = trim($parts[0]);
            $iteratorPart = trim($parts[1]);

            // Retrieve the array from the data using bracket notation if needed.
            $array = $this->_get_nested_value_from_brackets($varPath, $data);
            if (!is_array($array)) {
                return '';
            }

            $result = '';
            $globalContext = $data;
            if (strpos($iteratorPart, '=>') !== false) {
                $pair = preg_split('/\s*=>\s*/', $iteratorPart);
                if (count($pair) != 2) {
                    return '';
                }
                $keyName = trim($pair[0]);
                $valueName = trim($pair[1]);
                foreach ($array as $k => $row) {
                    // Create a context for this iteration.
                    $context = array_merge($globalContext, [
                        $keyName   => $k,
                        $valueName => $row
                    ]);
                    // First, process any nested foreach blocks within the content.
                    $inner = $this->_parse_foreach($blockContent, $context);
                    // Then, process placeholders, helpers, etc.
                    $inner = $this->_parse($inner, $context, true);
                    $result .= $inner;
                }
            } else {
                // Format: "array as value"
                $varName = $iteratorPart;
                foreach ($array as $row) {
                    $context = array_merge($globalContext, [
                        $varName => $row
                    ]);
                    $inner = $this->_parse_foreach($blockContent, $context);
                    $inner = $this->_parse($inner, $context, true);
                    $result .= $inner;
                }
            }
            return $result;
        }, $template);

        return $template;
    }

    // --------------------------------------------------------------------

    /**
     * Retrieve a Nested Value Using Bracket Notation.
     *
     * This method allows access to nested data in the $data array using a variable name
     * that may include bracket notation (e.g., "user[address][city]").
     *
     * @param string $variable The variable name or path.
     * @param array  $data     The context data array.
     * @return mixed           The nested value if found, or null if not.
     */
    protected function _get_nested_value_from_brackets($variable, $data)
    {
        // If there are no brackets, assume a simple key.
        if (strpos($variable, '[') === false) {
            return isset($data[$variable]) ? $data[$variable] : null;
        }
        
        // Use a regular expression to extract all keys.
        preg_match_all('/([a-zA-Z0-9_]+)/', $variable, $matches);
        $parts = $matches[1];
        foreach ($parts as $part) {
            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
            } else {
                return null;
            }
        }
        return $data;
    }

    // --------------------------------------------------------------------

    /**
     * Process Helper Functions in the Template.
     *
     * Finds and processes helper function calls within the template.
     * The syntax is: {functionName(arg1, arg2, ...)}.
     *
     * @param string $template The template string.
     * @param array  $data     The data context.
     * @return string          The template with helper calls processed.
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

                // Process the argument string to allow nested variables.
                $args_string = $this->_parse($args_string, $data, TRUE);

                $args = $this->_parse_helper_args($args_string, $data);

                if (function_exists($func))
                {
                    try
                    {
                        $return = call_user_func_array($func, $args);

                        // If the helper returns a string containing potential template tags, process them.
                        if (is_string($return) && strpos($return, $this->l_delim) !== false)
                        {
                            $return = $this->_parse_helpers($return, $data);
                        }

                        $template = str_replace($code, $return, $template);
                    }
                    catch (Exception $error)
                    {
                        // Optional: handle helper function errors.
                    }
                }
            }
        }

        return $template;
    }

    // --------------------------------------------------------------------

    /**
     * Parse Helper Function Arguments.
     *
     * Processes the argument string passed to a helper function call within the template.
     * Supports both positional arguments and named arguments (associative arrays).
     *
     * @param string $args_string The argument string.
     * @param array  $data        The data context.
     * @return array              An array of arguments to pass to the helper.
     */
    protected function _parse_helper_args($args_string, $data)
    {
        if (!empty($args_string))
        {
            // If the argument string starts and ends with square brackets, treat it as a single array argument.
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
                    $value = trim($sub[1], "\"'");
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

    /**
     * Process Nested Paths Using Bracket Notation.
     *
     * Processes blocks where the variable access uses bracket notation (e.g., {user[address]} ... {/user[address]}).
     * Also processes simple placeholders with bracket notation.
     *
     * @param string $template The template string.
     * @param array  $data     The data context.
     * @return string          The template with nested paths replaced.
     */
    protected function _parse_nested_paths($template, $data)
    {
        // Process foreach-like blocks with bracket notation.
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}(.*?)\{\/\1\}/is', function($matches) use ($data) {
            $tag = $matches[1];
            $inner = $matches[2];
            $array = $this->_get_nested_value_from_brackets($tag, $data);
            $replacement = '';
            if (is_array($array))
            {
                // If the array is simple, convert it into key-value pairs.
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

        // Process simple placeholders with bracket notation.
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}/i', function($matches) use ($data) {
            $tag = $matches[1];
            $value = $this->_get_nested_value_from_brackets($tag, $data);
            return ($value !== null) ? $value : '';
        }, $template);

        return $template;
    }

    // --------------------------------------------------------------------

    /**
     * Process Object Notation.
     *
     * Replaces object notation in the template (e.g., {object.method}) by calling the method
     * or retrieving the property of the object.
     *
     * @param string $key      The object variable name.
     * @param object $val      The object.
     * @param string $template The template string.
     * @return array           An associative array mapping the object notation placeholder to its value.
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

    /**
     * Replace Unparsed Placeholders.
     *
     * This method finds any remaining tag pairs or placeholders that have not been parsed
     * and replaces them with a marker (or optionally empties them).
     *
     * @param string $template The template string.
     * @return string          The template with unparsed placeholders replaced.
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

    // --------------------------------------------------------------------

    /**
     * Remove Unparsed Placeholders.
     *
     * Removes placeholders that are not a single alphanumeric word.
     *
     * @param string $template The template string.
     * @param array  $data     The current data context.
     * @return string          The cleaned template.
     */
    protected function _remove_unparsed($template, $data = array())
    {
        return preg_replace_callback('/\{([^}]+)\}/', function($matches) use ($data) {
            $key = $matches[1];
            if (preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                return $matches[0];
            }
            return '';
        }, $template);
    }
}
