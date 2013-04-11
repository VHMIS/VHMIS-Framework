<?php

namespace Vhmis\Text;

/**
 * Một số phương thức xử lý chuổi an toàn trước khi xuất ra
 * (Dựa trên Zend Framework)
 *
 * @package Vhmis_Text
 */
class Escaper
{

    /**
     * Entity Map mapping Unicode codepoints to any available named HTML
     * entities.
     *
     * While HTML supports far more named entities, the lowest common
     * denominator
     * has become HTML5's XML Serialisation which is restricted to the those
     * named
     * entities that XML supports. Using HTML entities would result in this
     * error:
     * XML Parsing Error: undefined entity
     *
     * @var array
     */
    protected static $_htmlNamedEntityMap = array(
        34 => 'quot', // quotation
                      // mark
        38 => 'amp', // ampersand
        60 => 'lt', // less-than sign
        62 => 'gt'
    ); // greater-than sign
    
    /**
     * Current encoding for escaping.
     * If not UTF-8, we convert strings from this encoding
     * pre-escaping and back to this encoding post-escaping.
     *
     * @var string
     */
    protected $_encoding = 'utf-8';

    /**
     * Holds the value of the special flags passed as second parameter to
     * htmlspecialchars().
     * We modify these for PHP 5.4 to take advantage
     * of the new ENT_SUBSTITUTE flag for correctly dealing with invalid
     * UTF-8 sequences.
     *
     * @var string
     */
    protected $_htmlSpecialCharsFlags =\ENT_QUOTES;

    /**
     * Static Matcher which escapes characters for HTML Attribute contexts
     *
     * @var callable
     */
    protected $_htmlAttrMatcher;

    /**
     * Static Matcher which escapes characters for Javascript contexts
     *
     * @var callable
     */
    protected $_jsMatcher;

    /**
     * Static Matcher which escapes characters for CSS Attribute contexts
     *
     * @var callable
     */
    protected $_cssMatcher;

    /**
     * List of all encoding supported by this class
     *
     * @var array
     */
    protected $_supportedEncodings = array(
        'iso-8859-1',
        'iso8859-1',
        'iso-8859-5',
        'iso8859-5',
        'iso-8859-15',
        'iso8859-15',
        'utf-8',
        'cp866',
        'ibm866',
        '866',
        'cp1251',
        'windows-1251',
        'win-1251',
        '1251',
        'cp1252',
        'windows-1252',
        '1252',
        'koi8-r',
        'koi8-ru',
        'koi8r',
        'big5',
        '950',
        'gb2312',
        '936',
        'big5-hkscs',
        'shift_jis',
        'sjis',
        'sjis-win',
        'cp932',
        '932',
        'euc-jp',
        'eucjp',
        'eucjp-win',
        'macroman'
    );

    /**
     * Khởi tạo, tham số encoding dùng để thiết lập kiểu mã hóa của chuỗi, mặc
     * định là UTF-8.
     *
     * @param string $encoding Kiểu mã hóa của chuỗi
     */
    public function __construct($encoding = null)
    {
        if ($encoding !== null) {
            $encoding = (string) $encoding;
            
            if ($encoding === '') {
                $encoding = 'utf-8';
            }
            
            $encoding = strtolower($encoding);
            
            $this->_encoding = $encoding;
        }
        
        if (defined('ENT_SUBSTITUTE')) {
            $this->_htmlSpecialCharsFlags |= ENT_SUBSTITUTE;
        }
        
        // set matcher callbacks
        $this->_htmlAttrMatcher = array(
            $this,
            '_htmlAttrMatcher'
        );
        $this->_jsMatcher = array(
            $this,
            '_jsMatcher'
        );
        $this->_cssMatcher = array(
            $this,
            '_cssMatcher'
        );
    }

    /**
     * Return the encoding that all output/input is expected to be encoded in.
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->_encoding;
    }

    /**
     * Escape a string for the HTML Body context where there are very few
     * characters
     * of special meaning.
     * Internally this will use htmlspecialchars().
     *
     * @param string $string
     * @return string
     */
    public function escapeHtml($string)
    {
        $result = htmlspecialchars($string, $this->_htmlSpecialCharsFlags, $this->_encoding);
        return $result;
    }

    /**
     * Escape a string for the HTML Attribute context.
     * We use an extended set of characters
     * to escape that are not covered by htmlspecialchars() to cover cases where
     * an attribute
     * might be unquoted or quoted illegally (e.g. backticks are valid quotes
     * for IE).
     *
     * @param string $string
     * @return string
     */
    public function escapeHtmlAttr($string)
    {
        $string = $this->_toUtf8($string);
        if ($string === '' || ctype_digit($string)) {
            return $string;
        }
        
        $result = preg_replace_callback('/[^a-z0-9,\.\-_]/iSu', $this->_htmlAttrMatcher, $string);
        return $this->_fromUtf8($result);
    }

    /**
     * Escape a string for the Javascript context.
     * This does not use json_encode(). An extended
     * set of characters are escaped beyond ECMAScript's rules for Javascript
     * literal string
     * escaping in order to prevent misinterpretation of Javascript as HTML
     * leading to the
     * injection of special characters and entities. The escaping used should be
     * tolerant
     * of cases where HTML escaping was not applied on top of Javascript
     * escaping correctly.
     * Backslash escaping is not used as it still leaves the escaped character
     * as-is and so
     * is not useful in a HTML context.
     *
     * @param string $string
     * @return string
     */
    public function escapeJs($string)
    {
        $string = $this->_toUtf8($string);
        if ($string === '' || ctype_digit($string)) {
            return $string;
        }
        
        $result = preg_replace_callback('/[^a-z0-9,\._]/iSu', $this->_jsMatcher, $string);
        return $this->_fromUtf8($result);
    }

    /**
     * Escape a string for the URI or Parameter contexts.
     * This should not be used to escape
     * an entire URI - only a subcomponent being inserted. The function is a
     * simple proxy
     * to rawurlencode() which now implements RFC 3986 since PHP 5.3 completely.
     *
     * @param string $string
     * @return string
     */
    public function escapeUrl($string)
    {
        return rawurlencode($string);
    }

    /**
     * Escape a string for the CSS context.
     * CSS escaping can be applied to any string being
     * inserted into CSS and escapes everything except alphanumerics.
     *
     * @param string $string
     * @return string
     */
    public function escapeCss($string)
    {
        $string = $this->_toUtf8($string);
        if ($string === '' || ctype_digit($string)) {
            return $string;
        }
        
        $result = preg_replace_callback('/[^a-z0-9]/iSu', $this->_cssMatcher, $string);
        return $this->_fromUtf8($result);
    }

    /**
     * Callback function for preg_replace_callback that applies HTML Attribute
     * escaping to all matches.
     *
     * @param array $matches
     * @return string
     */
    protected function _htmlAttrMatcher($matches)
    {
        $chr = $matches[0];
        $ord = ord($chr);
        
        /**
         * The following replaces characters undefined in HTML with the
         * hex entity for the Unicode replacement character.
         */
        if (($ord <= 0x1f && $chr != "\t" && $chr != "\n" && $chr != "\r") || ($ord >= 0x7f && $ord <= 0x9f)) {
            return '&#xFFFD;';
        }
        
        /**
         * Check if the current character to escape has a name entity we should
         * replace it with while grabbing the integer value of the character.
         */
        if (strlen($chr) > 1) {
            $chr = $this->_convertEncoding($chr, 'UTF-16BE', 'UTF-8');
        }
        
        $hex = bin2hex($chr);
        $ord = hexdec($hex);
        if (isset(static::$_htmlNamedEntityMap[$ord])) {
            return '&' . static::$_htmlNamedEntityMap[$ord] . ';';
        }
        
        /**
         * Per OWASP recommendations, we'll use upper hex entities
         * for any other characters where a named entity does not exist.
         */
        if ($ord > 255) {
            return sprintf('&#x%04X;', $ord);
        }
        return sprintf('&#x%02X;', $ord);
    }

    /**
     * Callback function for preg_replace_callback that applies Javascript
     * escaping to all matches.
     *
     * @param array $matches
     * @return string
     */
    protected function _jsMatcher($matches)
    {
        $chr = $matches[0];
        if (strlen($chr) == 1) {
            return sprintf('\\x%02X', ord($chr));
        }
        $chr = $this->_convertEncoding($chr, 'UTF-16BE', 'UTF-8');
        return sprintf('\\u%04s', strtoupper(bin2hex($chr)));
    }

    /**
     * Callback function for preg_replace_callback that applies CSS
     * escaping to all matches.
     *
     * @param array $matches
     * @return string
     */
    protected function _cssMatcher($matches)
    {
        $chr = $matches[0];
        if (strlen($chr) == 1) {
            $ord = ord($chr);
        } else {
            $chr = $this->_convertEncoding($chr, 'UTF-16BE', 'UTF-8');
            $ord = hexdec(bin2hex($chr));
        }
        return sprintf('\\%X ', $ord);
    }

    /**
     * Converts a string to UTF-8 from the base encoding.
     * The base encoding is set via this
     * class' constructor.
     *
     * @param string $string
     * @throws Exception\RuntimeException
     * @return string
     */
    protected function _toUtf8($string)
    {
        if ($this->getEncoding() === 'utf-8') {
            $result = $string;
        } else {
            $result = $this->_convertEncoding($string, 'UTF-8', $this->getEncoding());
        }
        
        if (!$this->_isUtf8($result)) {
            throw new \Exception(
                sprintf('String to be escaped was not valid UTF-8 or could not be converted: %s', $result));
        }
        
        return $result;
    }

    /**
     * Chuyển đổi từ mã UTF-8 sang mã mặc định (được thiết lập khi khởi tạo)
     *
     * @param string $string
     * @return string
     */
    protected function _fromUtf8($string)
    {
        if ($this->getEncoding() === 'utf-8') {
            return $string;
        }
        
        return $this->_convertEncoding($string, $this->getEncoding(), 'UTF-8');
    }

    /**
     * Checks if a given string appears to be valid UTF-8 or not.
     *
     * @param string $string
     * @return bool
     */
    protected function _isUtf8($string)
    {
        return ($string === '' || preg_match('/^./su', $string));
    }

    /**
     * Phương thức hỗ trợ chuyển đổi mã bằng iconv hoặc mbstring
     *
     * @param string $string
     * @param string $to
     * @param array|string $from
     * @return string
     */
    protected function _convertEncoding($string, $to, $from)
    {
        $result = '';
        if (function_exists('iconv')) {
            $result = iconv($from, $to, $string);
        } elseif (function_exists('mb_convert_encoding')) {
            $result = mb_convert_encoding($string, $to, $from);
        } else {
            $result = false;
        }
        
        if ($result === false) {
            return ''; // return non-fatal blank string on encoding errors from
                           // users
        }
        return $result;
    }
}