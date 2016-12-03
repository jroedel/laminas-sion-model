<?php
namespace SionModel\Stdlib\Dom;
/**
* This class overcomes a few common annoyances with the DOMDocument class,
* such as saving partial HTML without automatically adding extra tags
* and properly recognizing various encodings, specifically UTF-8.
*
* @author Artem Russakovskii
* @version 0.4
* @link http://beerpla.net
* @link http://www.php.net/manual/en/class.domdocument.php
*/
  class SmartDOMDocument extends \DOMDocument {

	  /**
	  * Adds an ability to use the SmartDOMDocument object as a string in a string context.
	  * For example, echo "Here is the HTML: $dom";
	  */
	  public function __toString() {
		  return $this->saveHTMLExact();
	  }

	  /**
	  * Load HTML with a proper encoding fix/hack.
	  * Borrowed from the link below.
	  *
	  * @link http://www.php.net/manual/en/domdocument.loadhtml.php
	  *
	  * @param string $html
	  * @param string $encoding
	  */
	  public function loadHTML($html, $encoding = "UTF-8") {
		  $html = mb_convert_encoding($html, 'HTML-ENTITIES', $encoding);
		  @parent::loadHTML($html); // suppress warnings
	  }

	  /**
	  * Return HTML while stripping the annoying auto-added <html>, <body>, and doctype.
	  *
	  * @link http://php.net/manual/en/migration52.methods.php
	  *
	  * @return string Html encoded
	  */
	  public function saveHTMLExact() {
      $content = preg_replace(array("/^(\<\!DOCTYPE.*?)?<html><body>/si",
                                    "!</body></html>$!si"),
                              "",
                              $this->saveHTML());

		  return $content;
	  }

    /**
    * This test functions shows an example of SmartDOMDocument in action.
    * A sample HTML fragment is loaded.
    * Then, the first image in the document is cut out and saved separately.
    * It also shows that Russian characters are parsed correctly.
    *
    */
    public static function testHTML() {
      $content = <<<CONTENT
<div class='class1'>
  <img src='http://www.google.com/favicon.ico' />
  Some Text
  <p>Ñ€ÑƒÑÑÐºÐ¸Ð¹</p>
</div>
CONTENT;

      print "Before removing the image, the content is: " . htmlspecialchars($content) . "<br>";

      $content_doc = new SmartDOMDocument();
      $content_doc->loadHTML($content);

      try {
        $first_image = $content_doc->getElementsByTagName("img")->item(0);

        if ($first_image) {
          $first_image->parentNode->removeChild($first_image);

          $content = $content_doc->saveHTMLExact();

          $image_doc = new SmartDOMDocument();
          $image_doc->appendChild($image_doc->importNode($first_image, true));
          $image = $image_doc->saveHTMLExact();
        }
      } catch(Exception $e) { }

      print "After removing the image, the content is: " . htmlspecialchars($content) . "<br>";
      print "The image is: " . htmlspecialchars($image);
    }
    
    /**
     * Removes HTML elements of tag in $nodeNames recursively
     * @param array|string|null $nodeNames
     * @param DOMNode $domNode
     */
    public function removeElements($nodeNames, $domNode = null)
    {
        if ($domNode == null) $domNode = $this;

        if ($nodeNames != null && is_string($nodeNames))
            $nodeNames = array($nodeNames);
        
        foreach ($domNode->childNodes as $node)
        {
            //if ($node->hasChildNodes()) {
            //    $this->removeElements($nodeNames, $node);
            //}
            $node->parentNode->removeChild($node);
        }
        
        if ($nodeNames == null || in_array($domNode->nodeName, $nodeNames)) {
            if ($domNode === $this)
                throw \Exception('Can\'t delete root node');

            $domNode->parentNode->removeChild($domNode);
        } 
    }
    
    public function removeElementsAndMerge($nodeNames, $domNode = null)
    {
        if ($domNode == null) $domNode = $this;
        
        if (in_array($domNode->nodeName, $nodeNames)) {
            
            //remove all attributes
            //foreach ($domNode->attributes() as $attr)
            //    $domNode->removeAttribute($attr);
        }
        $removed=0;
        foreach ($domNode->childNodes as $node)
        {
            //print $node->nodeName.':'.$node->nodeValue.'<br>';
            if (in_array($node->nodeName, $nodeNames)) {
                //remove the node
                $fragment = $domNode->ownerDocument->createDocumentFragment();
                while($node->childNodes->length > 0)
                    $fragment->appendChild($node->childNodes->item(0));
        
                $node->parentNode->replaceChild($fragment, $node);
        
                $removed=1;
                //echo "REMOVED============";
            } else {
                if($node->hasChildNodes()) {
                    //print '<tr>';
                    $this->removeElementsAndMerge($nodeNames, $node);
                }
            }
        }
        if ($removed)
            $this->removeElementsAndMerge($nodeNames, $domNode);
    }
    
    /**
     * 
     * @param string|array $attributes
     * @param string|array $nodeNames
     * @param DOMNode $domNode
     */
    public function removeAttributes($attributes, $nodeNames = array('*'), $domNode = null)
    {
        if ($domNode == null) $domNode = $this;
        
        if ($nodeNames == null)
            $nodeNames = array('*');
        
        if (is_string($nodeNames))
            $nodeNames = array($nodeNames);
        
        if (is_string($attributes))
            $attributes = array($attributes);
        foreach ($nodeNames as $tag)
        {
            $elements = $domNode->getElementsByTagName($tag);
            foreach ($elements as $element)
            {
                foreach ($attributes as $attr) {
                    $element->removeAttribute($attr);
                }
            }
        
        }
        
        
        
        /*
        if (array_key_exists($domNode->nodeName, $nodeNames)) {
            foreach ($domNode->attributes as $attribute) {
                if (array_key_exists($attribute, $attributes)) {
                    $domNode->removeAttribute($nodeNames, $attribute, $domNode);
                }
            }
        }
        
        //and check my kids too
        foreach ($domNode->childNodes as $node) {
            $this->removeAttributes($attributes, $nodeNames, $node);
        }
        */
    }
  }
?>