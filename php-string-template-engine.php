<?php

/**
 * PhpStringTemplateEngine
 * 
 * @author nulil
 * @copyright	Copyright &copy; 2012 nulil
 * @link		http://nulil.github.com
 * @license	MIT License (http://www.opensource.org/licenses/mit-license.php)
 * 
 * @version 0.1.2
 */
class PhpStringTemplateEngine {

	private $_string;
	private $_vars;
	private $_prefix;
	private $_extractType;
	private $_heredocTag;
	static private $_isClosure = null;

	/**
	 * 
	 * @param string $string
	 * @param array $vars
	 * @param int $extract_type
	 * @param string $var_prefix
	 */
	public function __construct($string, array $vars = null, $extract_type = EXTR_PREFIX_SAME, $var_prefix = 'var_') {
		$this->_string		 = $string;
		$this->_vars		 = $vars;
		$this->_prefix		 = $extract_type;
		$this->_extractType	 = $var_prefix;

		$this->_heredocTag = 'ENDOFTEMPLATE';
//		while (0 < preg_match('/^' . $this->_heredocTag . ';/mu', $string)) {
//			$this->_heredocTag .= $this->_heredocTag;
//		}
		while (false != strpos($this->_heredocTag, $string)) {
			$this->_heredocTag .= $this->_heredocTag;
		}

		if (self::$_isClosure === null) {
			self::$_isClosure = version_compare(PHP_VERSION, '5.3', '>=');
		}
	}

	/**
	 * expand
	 * 
	 * 変数を展開した文字列を返す
	 * 
	 * @method expand
	 * @return string
	 */
	public function expand() {
		if (empty($this->_string)) {
			return '';
		}

		extract($this->_createCcontrolStatements());

		if (!empty($this->_vars)) {
			extract($this->_vars, $this->_extractType, $this->_prefix);
		}


		return eval("return <<<{$this->_heredocTag}
{$this->_string}
{$this->_heredocTag};
");
	}

	/**
	 * cons
	 * 
	 * コンストラクタの代替呼び出し
	 * 
	 * @method cons
	 * @param string $string
	 * @param array $vars
	 * @param int $extract_type
	 * @param string $var_prefix
	 * @return \self
	 */
	static public function cons($string, array $vars = null, $extract_type = EXTR_PREFIX_SAME, $var_prefix = 'var_') {
		return new self($string, $vars, $extract_type, $var_prefix);
	}

	/**
	 * 制御関数
	 * 
	 * @method _controlStatement
	 * @staticvar null $static_control_statements
	 * @return array
	 */
	static private function _controlStatements() {
		static $static_control_statements = null;
		if (null === $static_control_statements) {
			$control_statements = array(
				'set'						 => create_function('&$var,$val', '$var=$val;'),
				'echo'						 => create_function('', 'return implode(\'\',func_get_args());'),
				'if'						 => create_function('$bool,$t_body,$f_body', '$ret = \'\';$body = $bool ? $t_body : $f_body;if ($body){ if (is_callable($body)){ $ret = call_user_func($body); }else{ $ret = $body; } } return $ret;'),
				'while'						 => create_function('$terms,$body', '$ret = \'\'; if ($terms && is_callable($terms)){ while ($bool = call_user_func($terms, $bool)){ if (is_callable($body)){ $ret .= call_user_func($body); }else{ $ret .= $body; } } } return $ret;'),
				'time'						 => create_function('$time,$body', 'if (!is_array($time)){ $time = intval($time); $time = 0 < $time ? array_fill(1,intval($time),\'\') : array(); } $ret = \'\'; foreach($time as $key => $val){ if (is_callable($body)){ $ret .= call_user_func($body, $key, $val); }else{ $ret .= $body; } } return $ret;'),
			);
			$static_control_statements	 = $control_statements;
		}
		return $static_control_statements;
	}

	/**
	 * _createCcontrolStatement
	 * 
	 * 制御関数作成
	 * 
	 * @method _createCcontrolStatement
	 * @return array
	 */
	private function &_createCcontrolStatements() {
		$vars				 = $this->_vars;
		$extract_type		 = $this->_extractType;
		$prefix				 = $this->_prefix;
		$control_statements	 = self::_controlStatements();

		if (self::$_isClosure) {
			$control_statements['if'] = function($bool, $t_body, $f_body) use ($vars, $extract_type, $prefix) {
						$ret	 = '';
						$body	 = $bool ? $t_body : $f_body;
						if ($body) {
							if (is_callable($body)) {
								$ret = call_user_func($body);
							}
							else {
								$pste						 = new PhpStringTemplateEngine($body, $vars, $extract_type, $prefix);
								$ret						 = $pste->expand();
							}
						}
						return $ret;
					};
			$control_statements['while'] = function ($terms, $body) use ($vars, $extract_type, $prefix) {
						$ret = '';
						if ($terms && is_callable($terms)) {
							while ($bool = call_user_func($terms, $bool)) {
								if (is_callable($body)) {
									$ret .= call_user_func($body);
								}
								else {
									$pste						 = new PhpStringTemplateEngine($body, $vars, $extract_type, $prefix);
									$ret .= $pste->expand();
								}
							}
						}
						return $ret;
					};
			$control_statements['time']	 = function($time, $body) use ($vars, $extract_type, $prefix) {
						if (!is_array($time)) {
							$time	 = intval($time);
							$time	 = 0 < $time ? array_fill(1, $time, '') : array();
						}
						$ret = '';
						foreach ($time as $key => $val) {
							if (is_callable($body)) {
								$ret .= call_user_func($body, $key, $val);
							}
							else {
								$pste = new PhpStringTemplateEngine($body, $vars, $extract_type, $prefix);
								$ret .= $pste->expand();
							}
						}
						return $ret;
					};
		}
		return $control_statements;
	}

}