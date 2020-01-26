<?php

/**
 * XML Response
 */

namespace App\ApiModule\Responses;

use Nette,
	SimpleXMLElement;


/**
 * XML response used for data export.
 *
 * @property-read array|\stdClass $payload
 * @property-read string $contentType
 */
class XmlResponse extends Nette\SmartObject implements Nette\Application\IResponse
{
    /** @var array|\stdClass */
    private $payload;

    /** @var string */
    private $contentType;


    /**
     * @param  array|\stdClass  payload
     * @param  string    MIME content type
     */
    public function __construct($payload, $contentType = NULL)
    {
        if (!is_array($payload) && !is_object($payload)) {
            throw new Nette\InvalidArgumentException(sprintf('Payload must be array or object class, %s given.', gettype($payload)));
        }
        $this->payload = $payload;
        $this->contentType = $contentType ? $contentType : 'application/xml';
    }


    /**
     * @return array|\stdClass
     */
    public function getPayload()
    {
        return $this->payload;
    }


    /**
     * Returns the MIME content type of a downloaded file.
     * @return string
     */
    public function getContentType()
    {
        return $this->contentType;
    }



    private function arrayToXml($arr, \SimpleXMLElement $xml)
	{
	    foreach ($arr as $k => $v) {
	        is_array($v)
	            ? $this->arrayToXml($v, $xml->addChild($k))
	            : $xml->addChild($k, $v);
	    }
	    return $xml;
	}


    /**
     * Sends response to output.
     * @return void
     */
    public function send(Nette\Http\IRequest $httpRequest, Nette\Http\IResponse $httpResponse)
    {
        $httpResponse->setContentType($this->contentType);
        $httpResponse->setExpiration(FALSE);

        $xml = XMLParser::encode( $this->payload , 'VybRiz' );
		echo $xml->asXML();

        //$xml = $this->arrayToXml($this->payload, new \SimpleXMLElement('<VybRiz/>'));
		//echo $xml->asXML();
        //echo Nette\Utils\Json::encode($this->payload);
    }

}

class XMLParser
{

	private static $_defaultRootNode = 'root';
	private static $_defaultListNode = 'l';
	private static $_defaultItemNode = 'i';
	private static $_defaultEncoding = 'UTF-8';
	private static $_defaultVersion  = '1.0';
	private static $_defaultAttrTag  = 'attr:';

	public static function encode ($data, $root = null)
	{
		if ($data instanceof SimpleXMLElement) {
			return $data;
		}
		try {
			$data = self::_validateEncodeData($data);
			$version = self::$_defaultVersion;
			$encoding  = self::$_defaultEncoding;
			$xml_string = "<?xml version=\"{$version}\" encoding=\"{$encoding}\" ?>";
			$node = self::_formatName( is_null($root) ?
				self::$_defaultRootNode :
				$root);
			$value = ($is_array = is_array($data)) ? NULL : self::_formatValue($data);
			$xml_string .= "<$node>$value</$node>";
			$xml = new SimpleXMLElement($xml_string);
			if ($is_array) {
				$xml = self::_addChildren($xml,$data);
			}
		}
		catch (Exception $e) {
			trigger_error($e->getMessage(), E_USER_ERROR);
		}
    	return isset($xml) ? $xml : null; // isset() essentially to make editor happy.
	}

	public static function objectToArray($std) {
		if (is_object($std)) {
			$std = get_object_vars($std);
		}
		if (is_array($std)) {
			return array_map(['self','objectToArray'], $std);
		}
		else {
			return $std;
		}
	}

	private static function _validateEncodeData ($data)
	{
	    if(is_object($data)){ // Try conversion
	    	$data = self::objectToArray($data);
	    }
	    if (is_object($data)) { // If it's still an object throw exception
	    throw new \InvalidArgumentException(
	    	"Invalid data type supplied for XMLParser::encode"
	    	);
	}
	return $data;
	}

	private static function _addChildren (SimpleXMLElement $element, $data)
	{
		foreach ($data as $key => $value) {
			$regex = '/^'.self::$_defaultAttrTag.'([a-z0-9\._-]*)/';
			$is_attr = preg_match($regex, $key, $attr);
			if ($is_attr) {
				if (is_array($value)) {
					foreach( $value as $k=>$v ) {
						$element->addAttribute(
							self::_formatName($k),
							self::_formatValue($v));
					}
				} else {
					$element->addAttribute(
						self::_formatName($attr[1]),
						self::_formatValue($value)
						);
				}
				continue;
			}
			$node = self::_formatName(
				is_numeric($key) ?
				(is_array($value) ?
					self::$_defaultListNode.'_'.$key :
					self::$_defaultItemNode.'_'.$key) : $key
				);
			if (is_array($value)) {
				$child = $element->addChild($node);
				self::_addChildren($child,$value);
				continue;
			}
			$element->addChild($node, $value);
		}
		return $element;
	}

	private static function _formatName ($string)
	{
		$p = [
			'/[^a-z0-9\._ -]/i' => '',
			'/(?=^[[0-9]\.\-\:^xml])/i' => self::$_defaultItemNode.'_',
			'/ /' => '_'
		];
		$string = preg_replace(array_keys($p), array_values($p), $string);
		return strtolower($string);
	}

	private static function _formatValue ($string)
	{
		$string = is_null($string) ? 'NULL' : $string;
		return is_bool($string) ? self::_bool($string) : $string;
	}

	private static function _bool ($bool)
	{
		return $bool ? 'TRUE' : 'FALSE';
	}

	public static function decode ($xml)
	{
		if (!$xml instanceof SimpleXMLElement)
		{
			$xml = new SimpleXMLElement($xml);
		}
		return json_decode(json_encode($xml),false);
	}
}