<?php

/**
 * 
 */
class PhpStringTemplateEngine {

	private $_string;
	private $_vars;
	private $_prefix;
	private $_extractType;
	private $_heredocTag;
	static private $_isClosure = null;

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

	public function expand() {
		if (empty($this->_string)) {
			return '';
		}

		extract($this->_controlStatement());

		if (!empty($this->_vars)) {
			extract($this->_vars, $this->_extractType, $this->_prefix);
		}


		return eval("return <<<{$this->_heredocTag}
{$this->_string}
{$this->_heredocTag};
");
	}

	static private function _controlStatementForStatic() {
		static $val = null;
		if (null === $val) {
			$arr = array(
				'set'	 => create_function('&$var,$val', '$var=$val;'),
				'echo'	 => create_function('$val', 'return $val;'),
				'if'	 => create_function('$bool,$f1,$f2', '$f = $bool ? "f1" : "f2";if ($$f){	if (is_callable($$f)){	return call_user_func($$f);	}else{	return $$f;	}	}	return "";'),
				'while'	 => create_function('$terms,$body', '
					$ret = "";
					if ($terms && is_callable($terms)){
						while ($bool = call_user_func($terms, $bool)){
							if (is_callable($body)){	$ret .= call_user_func($body);	}else{	$ret .= $body;	}
						}
					}
					return $ret;'),
				'time'	 => create_function('$time,$body', '
					if (!is_array($time)){
						$time = intval($time);
						$time = 0 < $time ? array_fill(1,intval($time),"") : array();
					}
					$ret = "";
					foreach($time as $key => $val){
						if (is_callable($body)){	$ret .= call_user_func($body, $key, $val);	}else{	$ret .= $body;	}
					}
					return $ret;'),
			);
			$val	 = $arr;
		}
		return $val;
	}

	private function &_controlStatement() {
		$vars			 = $this->_vars;
		$extract_type	 = $this->_extractType;
		$prefix			 = $this->_prefix;
		$val			 = self::_controlStatementForStatic();

		if (self::$_isClosure) {
			$val['if'] = function($bool, $t_body, $f_body) use ($vars, $extract_type, $prefix) {
						$body = $bool ? "t_body" : "f_body";
						if ($$body) {
							if (is_callable($$body)) {
								return call_user_func($$body);
							}
							else {
								$pste			 = new PhpStringTemplateEngine($$body, $vars, $extract_type, $prefix);
								return $pste->expand();
							}
						}
					};
			$val['while']	 = function ($terms, $body) use ($vars, $extract_type, $prefix) {
						$ret = "";
						if ($terms && is_callable($terms)) {
							while ($bool = call_user_func($terms, $bool)) {
								if (is_callable($body)) {
									$ret .= call_user_func($body);
								}
								else {
									$pste		 = new PhpStringTemplateEngine($body, $vars, $extract_type, $prefix);
									$ret .= $pste->expand();
								}
							}
						}
						return $ret;
					};
			$val['time'] = function($time, $body) use ($vars, $extract_type, $prefix) {
						if (!is_array($time)) {
							$time	 = intval($time);
							$time	 = 0 < $time ? array_fill(1, intval($time), "") : array();
						}
						$ret = "";
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
		return $val;
	}

}