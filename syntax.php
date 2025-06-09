<?php
/**
 * Jirainfo syntax plugin for DokuWiki
 *
 * @author     Vadim Balabin <vadikflint@gmail.com>
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

require_once DOKU_INC.'lib/plugins/jirainfo/utils.php';

/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_jirainfo extends DokuWiki_Syntax_Plugin 
{   
    public function getType() { return 'substition'; }
    public function getSort() { return 361; }
    public function connectTo($mode) {
        $this->Lexer->addEntryPattern('<(?:ji|jirainfo).*?>(?=.*?</(?:ji|jirainfo)>)', $mode, 'plugin_jirainfo');
    }
    public function postConnect() {
        $this->Lexer->addExitPattern('</(?:ji|jirainfo)>','plugin_jirainfo');
    }
    
    public function handle($match, $state, $pos, Doku_Handler $handler){
        switch ($state) {
            case DOKU_LEXER_ENTER:
                libxml_use_internal_errors(true);
                $match = preg_replace('/(?<=key=)([^\s"\'>]+)/', '"$1"', $match);
                $match = preg_replace('/>$/', '/>', $match);

                $attributes = [];
                $xml = simplexml_load_string($match);

                if ($xml !== false) {
                    foreach ($xml->attributes() as $key => $value) {
                        $attributes[$key] = (string) $value;
                    }
                }

                if (!empty($attributes['key'])) {
                    return ['state' => $state, 'key' => $attributes['key']];
                } else {
                    return ['state' => $state, 'error' => 'Ошибка в парметре key.'];
                }

            case DOKU_LEXER_UNMATCHED:
                return ['state' => $state, 'text' => $match];

            case DOKU_LEXER_EXIT:
                return ['state' => $state];
        }
        return array();
    }

    /**
     * check - correct attributes
     *
     * @param  Array $attributes
     *
     * @return boolean
     */
    public function check(array $attributes) {
        return array_key_exists('key', $attributes);
    }

    public function render($mode, Doku_Renderer $renderer, $data) {  
        if ($mode === 'xhtml') {
            static $inError = false;
            static $inLink = false;

            $state = $data['state'];

            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $inError = false;
                    $inLink = false;

                    if (!empty($data['error'])) {
                        $renderer->doc .= '<span class="jirainfo-error-tooltip" title="' . hsc($data['error']) . '" style="color:red; cursor: default;">';
                        $inError = true;
                    } elseif (!empty($data['key'])) {
                        $renderer->doc .= '<a class="jirainfo" href="javascript:void(0);" data-key="' . hsc($data['key']) . '">';
                        $inLink = true;
                    }
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $renderer->doc .= htmlentities($data['text']);
                    break;

                case DOKU_LEXER_EXIT:
                    if ($inError) {
                        $renderer->doc .= '</span>';
                        $inError = false;
                    } elseif ($inLink) {
                        $renderer->doc .= '</a>';
                        $inLink = false;
                    }
                    break;
            }
        } elseif ($mode === 'odt') {
            $this->render_for_odt($renderer, $data);
        }
    }

    public function render_for_odt(Doku_Renderer $renderer, $data) {
        list($state, $match) = $data;
        switch ($state) {
            case DOKU_LEXER_ENTER:
                $renderer->strong_open();
                $renderer->underline_open();
                $renderer->doc = $match;        
                break;            
            
            case DOKU_LEXER_UNMATCHED:
                $url = Utilities::getTaskUrl($renderer->doc, $this->getConf('apiUrl'));
                $renderer->externallink($url, $match);
                break;

            case DOKU_LEXER_EXIT:
                $renderer->underline_close();
                $renderer->strong_close();
                break;
        }
    }
}