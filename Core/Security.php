<?php

class Vhmis_Core_Security
{
    /**
	 * Random Hash for protecting URLs
	 *
	 * @var string
	 */
	protected $_xssHash =	'';

    /**
	 * List of never allowed strings
	 *
	 * @var array
	 */
	protected $_neverAllowedStr =	array(
		'document.cookie'	=> '',
		'document.write'	=> '',
		'.parentNode'		=> '',
		'.innerHTML'		=> '',
		'window.location'	=> '',
		'-moz-binding'		=> '',
		'<!--'				=> '&lt;!--',
		'-->'				=> '--&gt;',
		'<![CDATA['			=> '&lt;![CDATA[',
		'<comment>'			=> '&lt;comment&gt;'
	);

	/**
	 * List of never allowed regex replacement
	 *
	 * @var array
	 */
	protected $_neverAllowedRegex =	array(
		'javascript\s*:',
		'expression\s*(\(|&\#40;)', // CSS and IE
		'vbscript\s*:', // IE, surprise!
		'Redirect\s+302'
	);

    /**
	 * XSS Clean, based on CI framework
	 *
	 * Note: This function should only be used to deal with data
	 * upon submission. It's not something that should
	 * be used for general runtime processing.
	 *
	 * This function was based in part on some code and ideas I
	 * got from Bitflux: http://channel.bitflux.ch/wiki/XSS_Prevention
	 *
	 * To help develop this script I used this great list of
	 * vulnerabilities along with a few other hacks I've
	 * harvested from examining vulnerabilities in other programs:
	 * http://ha.ckers.org/xss.html
	 *
	 * @param	mixed	string or array
	 * @param 	bool
	 * @return	string
	 */
	public function xssClean($str, $is_image = FALSE)
	{
		// Is the string an array?
		if (is_array($str))
		{
			while (list($key) = each($str))
			{
				$str[$key] = $this->xssClean($str[$key]);
			}

			return $str;
		}

		// Remove Invisible Characters and validate entities in URLs
		$str = $this->_validateEntities(___removeInvisibleCharacters($str));

		/*
		 * URL Decode
		 *
		 * Just in case stuff like this is submitted:
		 *
		 * <a href="http://%77%77%77%2E%67%6F%6F%67%6C%65%2E%63%6F%6D">Google</a>
		 *
		 * Note: Use rawurldecode() so it does not remove plus signs
		 */
		$str = rawurldecode($str);

		/*
		 * Convert character entities to ASCII
		 *
		 * This permits our tests below to work reliably.
		 * We only convert entities that are within tags since
		 * these are the ones that will pose security problems.
		 */
		$str = preg_replace_callback("/[a-z]+=([\'\"]).*?\\1/si", array($this, '_convertAttribute'), $str);
		$str = preg_replace_callback('/<\w+.*?(?=>|<|$)/si', array($this, '_decodeEntity'), $str);

		// Remove Invisible Characters Again!
		$str = ___removeInvisibleCharacters($str);

		/*
		 * Convert all tabs to spaces
		 *
		 * This prevents strings like this: ja	vascript
		 * NOTE: we deal with spaces between characters later.
		 * NOTE: preg_replace was found to be amazingly slow here on
		 * large blocks of data, so we use str_replace.
		 */
		$str = str_replace("\t", ' ', $str);

		// Capture converted string for later comparison
		$converted_string = $str;

		// Remove Strings that are never allowed
		$str = $this->_doNeverAllowed($str);

		/*
		 * Makes PHP tags safe
		 *
		 * Note: XML tags are inadvertently replaced too:
		 *
		 * <?xml
		 *
		 * But it doesn't seem to pose a problem.
		 */
		if ($is_image === TRUE)
		{
			// Images have a tendency to have the PHP short opening and
			// closing tags every so often so we skip those and only
			// do the long opening tags.
			$str = preg_replace('/<\?(php)/i', '&lt;?\\1', $str);
		}
		else
		{
			$str = str_replace(array('<?', '?'.'>'),  array('&lt;?', '?&gt;'), $str);
		}

		/*
		 * Compact any exploded words
		 *
		 * This corrects words like:  j a v a s c r i p t
		 * These words are compacted back to their correct state.
		 */
		$words = array(
			'javascript', 'expression', 'vbscript', 'script',
			'applet', 'alert', 'document', 'write', 'cookie', 'window'
		);

		foreach ($words as $word)
		{
			$word = implode('\s*', str_split($word)).'\s*';

			// We only want to do this when it is followed by a non-word character
			// That way valid stuff like "dealer to" does not become "dealerto"
			$str = preg_replace_callback('#('.substr($word, 0, -3).')(\W)#is', array($this, '_compactExplodedWords'), $str);
		}

		/*
		 * Remove disallowed Javascript in links or img tags
		 * We used to do some version comparisons and use of stripos for PHP5,
		 * but it is dog slow compared to these simplified non-capturing
		 * preg_match(), especially if the pattern exists in the string
		 */
		do
		{
			$original = $str;

			if (preg_match('/<a/i', $str))
			{
				$str = preg_replace_callback('#<a\s+([^>]*?)(>|$)#si', array($this, '_jsLinkRemoval'), $str);
			}

			if (preg_match('/<img/i', $str))
			{
				$str = preg_replace_callback('#<img\s+([^>]*?)(\s?/?>|$)#si', array($this, '_jsImgRemoval'), $str);
			}

			if (preg_match('/(script|xss)/i', $str))
			{
				$str = preg_replace('#<(/*)(script|xss)(.*?)\>#si', '', $str);
			}
		} while($original != $str);

		unset($original);

		// Remove evil attributes such as style, onclick and xmlns
		$str = $this->_removeEvilAttributes($str, $is_image);

		/*
		 * Sanitize naughty HTML elements
		 *
		 * If a tag containing any of the words in the list
		 * below is found, the tag gets converted to entities.
		 *
		 * So this: <blink>
		 * Becomes: &lt;blink&gt;
		 */
		$naughty = 'alert|applet|audio|basefont|base|behavior|bgsound|blink|body|embed|expression|form|frameset|frame|head|html|ilayer|iframe|input|isindex|layer|link|meta|object|plaintext|style|script|textarea|title|video|xml|xss';
		$str = preg_replace_callback('#<(/*\s*)('.$naughty.')([^><]*)([><]*)#is', array($this, '_sanitizeNaughtyHtml'), $str);

		/*
		 * Sanitize naughty scripting elements
		 *
		 * Similar to above, only instead of looking for
		 * tags it looks for PHP and JavaScript commands
		 * that are disallowed. Rather than removing the
		 * code, it simply converts the parenthesis to entities
		 * rendering the code un-executable.
		 *
		 * For example:	eval('some code')
		 * Becomes:	eval&#40;'some code'&#41;
		 */
		$str = preg_replace('#(alert|cmd|passthru|eval|exec|expression|system|fopen|fsockopen|file|file_get_contents|readfile|unlink)(\s*)\((.*?)\)#si',
					'\\1\\2&#40;\\3&#41;',
					$str);

		// Final clean up
		// This adds a bit of extra precaution in case
		// something got through the above filters
		$str = $this->_doNeverAllowed($str);

		/*
		 * Images are Handled in a Special Way
		 * - Essentially, we want to know that after all of the character
		 * conversion is done whether any unwanted, likely XSS, code was found.
		 * If not, we return TRUE, as the image is clean.
		 * However, if the string post-conversion does not matched the
		 * string post-removal of XSS, then it fails, as there was unwanted XSS
		 * code found and removed/changed during processing.
		 */
		if ($is_image === TRUE)
		{
			return ($str === $converted_string);
		}

		return $str;
	}

	/**
	 * Random Hash for protecting URLs
	 *
	 * @return	string
	 */
	public function xssHash()
	{
		if ($this->_xssHash == '')
		{
			mt_srand();
			$this->_xssHash = md5(time() + mt_rand(0, 1999999999));
		}

		return $this->_xssHash;
	}

	/**
	 * HTML Entities Decode
	 *
	 * This function is a replacement for html_entity_decode()
	 *
	 * The reason we are not using html_entity_decode() by itself is because
	 * while it is not technically correct to leave out the semicolon
	 * at the end of an entity most browsers will still interpret the entity
	 * correctly. html_entity_decode() does not convert entities without
	 * semicolons, so we are left with our own little solution here. Bummer.
	 *
	 * @param	string
	 * @param	string
	 * @return	string
	 */
	public function entityDecode($str, $charset = NULL)
	{
		if (strpos($str, '&') === FALSE)
		{
			return $str;
		}

		if (empty($charset))
		{
			$charset = config_item('charset');
		}

		$str = html_entity_decode($str, ENT_COMPAT, $charset);
		$str = preg_replace('~&#x(0*[0-9a-f]{2,5})~ei', 'chr(hexdec("\\1"))', $str);
		return preg_replace('~&#([0-9]{2,4})~e', 'chr(\\1)', $str);
	}

	/**
	 * JS Link Removal
	 *
	 * Callback function for xss_clean() to sanitize links
	 * This limits the PCRE backtracks, making it more performance friendly
	 * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
	 * PHP 5.2+ on link-heavy strings
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _jsLinkRemoval($match)
	{
		return str_replace($match[1],
					preg_replace('#href=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
							'',
							$this->_filterAttributes(str_replace(array('<', '>'), '', $match[1]))
					),
					$match[0]);
	}

	/**
	 * JS Image Removal
	 *
	 * Callback function for xss_clean() to sanitize image tags
	 * This limits the PCRE backtracks, making it more performance friendly
	 * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
	 * PHP 5.2+ on image tag heavy strings
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _jsImgRemoval($match)
	{
		return str_replace($match[1],
					preg_replace('#src=.*?(alert\(|alert&\#40;|javascript\:|livescript\:|mocha\:|charset\=|window\.|document\.|\.cookie|<script|<xss|base64\s*,)#si',
							'',
							$this->_filterAttributes(str_replace(array('<', '>'), '', $match[1]))
					),
					$match[0]);
	}

	/**
	 * Compact Exploded Words
	 *
	 * Callback function for xss_clean() to remove whitespace from
	 * things like j a v a s c r i p t
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _compactExplodedWords($matches)
	{
		return preg_replace('/\s+/s', '', $matches[1]).$matches[2];
	}

	/**
	 * Remove Evil HTML Attributes (like event handlers and style)
	 *
	 * It removes the evil attribute and either:
	 * 	- Everything up until a space
	 *		For example, everything between the pipes:
	 *		<a |style=document.write('hello');alert('world');| class=link>
	 * 	- Everything inside the quotes
	 *		For example, everything between the pipes:
	 *		<a |style="document.write('hello'); alert('world');"| class="link">
	 *
	 * @param string $str The string to check
	 * @param boolean $is_image TRUE if this is an image
	 * @return string The string with the evil attributes removed
	 */
	protected function _removeEvilAttributes($str, $is_image)
	{
		// All javascript event handlers (e.g. onload, onclick, onmouseover), style, and xmlns
		$evil_attributes = array('on\w*', 'style', 'xmlns', 'formaction');

		if ($is_image === TRUE)
		{
			/*
			 * Adobe Photoshop puts XML metadata into JFIF images,
			 * including namespacing, so we have to allow this for images.
			 */
			unset($evil_attributes[array_search('xmlns', $evil_attributes)]);
		}

		do
		{
			$count = 0;
			$attribs = array();

			// find occurrences of illegal attribute strings without quotes
			preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*([^\s]*)/is', $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{
				$attribs[] = preg_quote($attr[0], '/');
			}

			// find occurrences of illegal attribute strings with quotes (042 and 047 are octal quotes)
			preg_match_all('/('.implode('|', $evil_attributes).')\s*=\s*(\042|\047)([^\\2]*?)(\\2)/is', $str, $matches, PREG_SET_ORDER);

			foreach ($matches as $attr)
			{
				$attribs[] = preg_quote($attr[0], '/');
			}

			// replace illegal attribute strings that are inside an html tag
			if (count($attribs) > 0)
			{
				$str = preg_replace('/<(\/?[^><]+?)([^A-Za-z\-])('.implode('|', $attribs).')([\s><])([><]*)/i', '<$1$2$4$5', $str, -1, $count);
			}

		} while ($count);

		return $str;
	}

	/**
	 * Sanitize Naughty HTML
	 *
	 * Callback function for xss_clean() to remove naughty HTML elements
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _sanitizeNaughtyHtml($matches)
	{
		return '&lt;'.$matches[1].$matches[2].$matches[3] // encode opening brace
			// encode captured opening or closing brace to prevent recursive vectors:
			.str_replace(array('>', '<'), array('&gt;', '&lt;'), $matches[4]);
	}

	/**
	 * Attribute Conversion
	 *
	 * Used as a callback for XSS Clean
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _convertAttribute($match)
	{
		return str_replace(array('>', '<', '\\'), array('&gt;', '&lt;', '\\\\'), $match[0]);
	}

	/**
	 * Filter Attributes
	 *
	 * Filters tag attributes for consistency and safety
	 *
	 * @param	string
	 * @return	string
	 */
	protected function _filterAttributes($str)
	{
		$out = '';
		if (preg_match_all('#\s*[a-z\-]+\s*=\s*(\042|\047)([^\\1]*?)\\1#is', $str, $matches))
		{
			foreach ($matches[0] as $match)
			{
				$out .= preg_replace('#/\*.*?\*/#s', '', $match);
			}
		}

		return $out;
	}

	/**
	 * HTML Entity Decode Callback
	 *
	 * Used as a callback for XSS Clean
	 *
	 * @param	array
	 * @return	string
	 */
	protected function _decodeEntity($match)
	{
		return $this->entityDecode($match[0], strtoupper('utf-8'));
	}

	/**
	 * Validate URL entities
	 *
	 * Called by xss_clean()
	 *
	 * @param 	string
	 * @return 	string
	 */
	protected function _validateEntities($str)
	{
		/*
		 * Protect GET variables in URLs
		 */

		// 901119URL5918AMP18930PROTECT8198
		$str = preg_replace('|\&([a-z\_0-9\-]+)\=([a-z\_0-9\-]+)|i', $this->xssHash().'\\1=\\2', $str);

		/*
		 * Validate standard character entities
		 *
		 * Add a semicolon if missing.  We do this to enable
		 * the conversion of entities to ASCII later.
		 */
		$str = preg_replace('#(&\#?[0-9a-z]{2,})([\x00-\x20])*;?#i', '\\1;\\2', $str);

		/*
		 * Validate UTF16 two byte encoding (x00)
		 *
		 * Just as above, adds a semicolon if missing.
		 */
		$str = preg_replace('#(&\#x?)([0-9A-F]+);?#i', '\\1\\2;', $str);

		/*
		 * Un-Protect GET variables in URLs
		 */
		return str_replace($this->xssHash(), '&', $str);
	}

	/**
	 * Do Never Allowed
	 *
	 * A utility function for xss_clean()
	 *
	 * @param 	string
	 * @return 	string
	 */
	protected function _doNeverAllowed($str)
	{
		$str = str_replace(array_keys($this->_neverAllowedStr), $this->_neverAllowedStr, $str);

		foreach ($this->_neverAllowedRegex as $regex)
		{
			$str = preg_replace('#'.$regex.'#i', '', $str);
		}

		return $str;
	}
}