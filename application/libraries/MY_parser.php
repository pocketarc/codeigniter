<?php
/**
 * CodeIgniter
 *
 * @package CodeIgniter
 * @author  EllisLab Dev Team
 * @copyright   Copyright (c) 2008 - 2014, EllisLab, Inc.
 * @copyright   Copyright (c) 2014 - 2015, British Columbia Institute of Technology
 * @license http://opensource.org/licenses/MIT  MIT License
 * @link    http://codeigniter.com
 * @since   Version 1.0.0
 */
defined('BASEPATH') OR exit('No direct script access allowed');

// ------------------------------------------------------------------------

/**
 * Class MY_Parser
 * CodeIgniter Parser Library extension
 *
 * Funcionalidades nuevas:
 * - Parámetros nombrados para helpers (agrupados en un array asociativo).
 * - Conversión de parámetros en notación de array usando corchetes, ej. [1,2,3,4].
 * - Acceso a datos anidados usando exclusivamente la notación con corchetes.
 * - Nueva etiqueta {foreach(...)} ... {/foreach} para iterar sobre arrays.
 *
 * Ejemplo de uso en template para un array de frutas:
 *
 * <p>Here are some fruits:</p>
 * <ul>
 *   {foreach(fruits as color => fruitList)}
 *     <li>{color} fruits:
 *       <ul>
 *         {foreach(fruitList as fruit)}
 *           <li>{fruit}</li>
 *         {/foreach}
 *       </ul>
 *     </li>
 *   {/foreach}
 * </ul>
 *
 * @package     CodeIgniter
 * @subpackage  Libraries
 * @category    Library
 * @author      Gregory Carrodano
 * @version     20161121 (modificado)
 */
class MY_Parser extends CI_Parser
{
    protected function _parse($template, $data, $return = FALSE)
    {
        if ($template === '')
        {
            return FALSE;
        }
    
        $data = array_merge($data, $this->CI->load->get_vars());
    
        // Procesa primero los bucles tradicionales (for)
        $template = $this->_parse_loops($template, TRUE);
    
        $replace = array();
        foreach ($data as $key => $val)
        {
            $replace = array_merge(
                $replace,
                is_object($val) ? $this->_parse_object($key, $val, $template) :
                (is_array($val) ? $this->_parse_pair($key, $val, $template) : 
                $this->_parse_single($key, (string)$val, $template))
            );
        }
    
        foreach ($replace as $from => $to)
        {
            $template = str_ireplace($from, (!is_null($to) ? $to : '%EMPTY_VAR%'), $template);
        }
    
        $template = $this->_replace_unparsed($template);
        $template = $this->_parse_helpers($template, $data);
        // Procesa la nueva etiqueta foreach
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
    
                for ($i = $loop[2]; $i <= $loop[3]; $i = $i + $loop[4])
                {
                    $output .= str_replace($this->l_delim.$loop[1].$this->r_delim, $i, $display);
                }
    
                $template = str_replace($loop[0], $output, $template);
            }
        }
    
        return $template;
    }
    
    /**
     * Nueva función para procesar bloques foreach.
     *
     * Soporta la sintaxis:
     *   {foreach(variable as key => value)} ... {/foreach}
     * o, de forma simple:
     *   {foreach(variable as item)} ... {/foreach}
     *
     * Para cada elemento del array obtenido de la ruta indicada se crea un contexto
     * que fusiona (usando array_replace para que las variables de iteración sobrescriban las globales)
     * el contexto global con los datos de la iteración.
     *
     * @param string $template
     * @param array  $data (contexto global)
     * @return string
     */
    protected function _parse_foreach($template, $data)
    {
        return preg_replace_callback(
            '/\{foreach\((.*?)\)\}(.*?)\{\/foreach\}/is',
            function($matches) use ($data) {
                $expr = trim($matches[1]);
                $parts = preg_split('/\s+as\s+/i', $expr);
                if (count($parts) != 2) {
                    return '';
                }
                $varPath = trim($parts[0]);
                $iteratorPart = trim($parts[1]);
    
                // Extrae el array usando la notación con corchetes
                $array = $this->_get_nested_value_from_brackets($varPath, $data);
                $result = '';
                if (is_array($array)) {
                    // Detecta si el array es asociativo (con claves no numéricas secuenciales)
                    $is_assoc = (array_keys($array) !== range(0, count($array) - 1));
    
                    $globalContext = $data;
                    if (strpos($iteratorPart, '=>') !== false) {
                        $pair = preg_split('/\s*=>\s*/', $iteratorPart);
                        if (count($pair) != 2) {
                            return '';
                        }
                        $keyName = trim($pair[0]);
                        $valueName = trim($pair[1]);
                        foreach ($array as $k => $row) {
                            // Para arrays numéricos (no asociativos) los elementos son escalares; para asociativos, se preserva el valor tal cual
                            $context = array_replace($globalContext, array(
                                $keyName => $k,
                                $valueName => $row
                            ));
                            $parsedBlock = $this->_parse($matches[2], $context, true);
                            $parsedBlock = $this->_parse_foreach($parsedBlock, $context);
                            $result .= $parsedBlock;
                        }
                    } else {
                        $varName = $iteratorPart;
                        foreach ($array as $row) {
                            $context = array_replace($globalContext, array(
                                $varName => $row
                            ));
                            $parsedBlock = $this->_parse($matches[2], $context, true);
                            $parsedBlock = $this->_parse_foreach($parsedBlock, $context);
                            $result .= $parsedBlock;
                        }
                    }
                }
                return $result;
            },
            $template
        );
    }
    
    protected function _get_nested_value_from_brackets($variable, $data)
    {
        preg_match_all('/[a-zA-Z0-9_.]+/', $variable, $matches);
        $parts = $matches[0];
        foreach ($parts as $part) {
            if (is_array($data) && array_key_exists($part, $data)) {
                $data = $data[$part];
            } else {
                return null;
            }
        }
        return $data;
    }
    
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
                        // Manejo opcional de errores
                    }
                }
            }
        }
    
        return $template;
    }
    
    protected function _parse_helper_args($args_string, $data)
    {
        if (!empty($args_string))
        {
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
    
    protected function _parse_nested_paths($template, $data)
    {
        // Procesa bloques de bucle con notación de corchetes: {variable} ... {/variable}
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}(.*?)\{\/\1\}/is', function($matches) use ($data) {
            $tag = $matches[1];
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
    
        // Procesa placeholders simples (sin etiqueta de cierre)
        $template = preg_replace_callback('/\{(\w+(?:\[[^\]]+\])+)\}/i', function($matches) use ($data) {
            $tag = $matches[1];
            $value = $this->_get_nested_value_from_brackets($tag, $data);
            return ($value !== null) ? $value : '';
        }, $template);
    
        return $template;
    }
    
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
                // Excluimos {else}, {break}, {default}, {key} y {value} para que no se reemplacen
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
    
    protected function _remove_unparsed($template)
    {
        return str_ireplace('%EMPTY_VAR%', '', $template);
    }
    
}

/* End of file MY_Parser.php */
/* Location: ./application/libraries/MY_Parser.php */
