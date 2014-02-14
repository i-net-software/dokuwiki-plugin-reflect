<?php 
/**
 * iReflect Plugin
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     i-net software <tools@inetsoftware.de>
 * @author     Gerry Weissbach <gweissbach@inetsoftware.de>
 */

if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/'); 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/'); 
require_once(DOKU_PLUGIN.'syntax.php'); 
  
/** 
 * All DokuWiki plugins to extend the parser/rendering mechanism 
 * need to inherit from this class 
 */ 
class syntax_plugin_reflect extends DokuWiki_Syntax_Plugin { 

  
    function getType(){ return 'substition';}
    function getPType(){ return 'block';}

    // must return a number lower than returned by table (60)
    function getSort(){ return 300; }

	
	function connectTo($mode){  
		$this->Lexer->addSpecialPattern('\{\{reflect>[^}]*\}\}',$mode,'plugin_reflect');
	} 

	function handle($match, $state, $pos, &$handler){

		$match = substr($match, 10, -2); // strip markup
		$array = array();
		
		list($src, $add) = explode('|', $match, 2);
		list($desc, $link) = explode('|', $add, 2);
		list($src, $params) = explode('$', $src, 2);
		$array['ID'] = $src;

		if ( trim($desc) != '' ) {
			$array['desc'] = '&'.trim($desc);
		}

		if ( trim($link) != '' ) {
			$array['link'] = trim($link);
		}

		$array['params'] = str_replace('#', '', trim($params));

		return $array;
	}

	function render($mode, &$renderer, $data){
		global $ID;

		if ($mode == 'xhtml'){
			// chaching abschalten

			$output = '';
			if ( !empty($data['link']) ) { $output .= '[[' . cleanID($data['link']) . '|'; }
			$output .= "{{{$data['ID']}|{$data['desc']}}}";
			if ( !empty($data['link']) ) { $output .= ']]'; }

			$img = p_render('xhtml', p_get_instructions($output),$info);
			$renderer->doc .= preg_replace('%(src="[^"]*?)(")%', "$1&amp;reflect=1&{$data['params']}$2", $img);

			return true;
		}
		
		if ( $mode == 'meta' ) {
			$renderer->meta[] = array(	'src' => $data['ID'],
										'title' => $data['desc'],
										'cache' => 'no-cache'
						);
			return true;
		}

			return false;  
	}
}

//Setup VIM: ex: et ts=4 enc=utf-8 :