<?php
/*
Plugin Name: Cachestatic
Plugin URI:
Description: 
Version: 0.1
Author: Yoan De Macedo
Author URI: http://yoandemacedo.com
License: GPL3
*/

/*  
    Copyright (C) 2024  Yoan De Macedo  <mail@yoandm.com>                       
    web : http://yoandm.com

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

    if ( ! defined( 'ABSPATH' ) ) {
            exit;
    }

    class Cachestatic {

        private $currentCachePath;
        private $iScache;

        public function __construct(){

            global $wp;
            register_activation_hook( __FILE__, array($this, 'install'));
            register_deactivation_hook( __FILE__, array($this, 'disable'));

            add_action('template_redirect', array($this, 'start_buffer'), 0);
            add_action('shutdown', array($this, 'shutdown'), 1000);
            add_action('admin_menu', array($this, 'plugin_setup_admin_menu'));
            add_filter('mod_rewrite_rules', array($this, 'htaccess_update'), 10, 1);
        }

        public function install(){

            update_option('cachestatic_enable', 0);

            if(! file_exists(ABSPATH . 'wp-content/cache-static/')){
                mkdir(ABSPATH . 'wp-content/cache-static/');

                $htaccess = file_get_contents(ABSPATH . '.htaccess');

                if(! strstr($htaccess, 'cachestatic')){
 
                    $newhtaccess = $this->getNewHtaccess($htaccess);
                    file_put_contents(ABSPATH . '.htaccess', $newhtaccess);

                }                
            }

        }

        public function disable(){
            $htaccess = file_get_contents(ABSPATH . '.htaccess');
            $newhtaccess = '';

            if(! strstr($htaccess, 'cachestatic')){
                $htaccess = str_replace("\r\n", "\n", $htaccess);
                $cut = explode("\n", $htaccess);

                foreach($cut as $line){
                    if(! strstr($line, 'cache-static'))
                        $newhtaccess .= $line . "\n";
                }

                file_put_contents(ABSPATH . '.htaccess', $newhtaccess);
            }

            $this->resetCache(ABSPATH . 'wp-content/cache-static/');
        }

        public function getNewHtaccess($oldHtaccess){
           $oldHtaccess = str_replace("\r\n", "\n", $oldHtaccess);
            $cut = explode("\n", $oldHtaccess);

            $newhtaccess = '';
            foreach($cut as $line){
                $newhtaccess .= $line . "\n";
                if(preg_match('/RewriteBase (.*)/', $line, $res)){
                    $newhtaccess .= 'RewriteCond %{DOCUMENT_ROOT}' . $res[1] . 'wp-content/cache-static/$1 -d' . "\n";
                    $newhtaccess .= 'RewriteRule (.*) ' . $res[1] . 'wp-content/cache-static/$1index.html [L]' . "\n";

                }
            }

            return $newhtaccess;
        }


        public function htaccess_update($rules){
            $rules = $this->getNewHtaccess($rules);
            $this->resetCache(ABSPATH . 'wp-content/cache-static/');
            mkdir(ABSPATH . 'wp-content/cache-static/');

            return $rules;
        }

        public function plugin_setup_admin_menu(){
                add_menu_page( 'Cachestatic', 'Cachestatic', 'manage_options', 'cachestatic', array($this,'admin'));
        }

        public function admin(){

            if(isset($_POST['cachestatic_action'])){
                 if($_POST['cachestatic_action'] === 'cachestatic_reset_cache'){

                    $this->resetCache(ABSPATH . 'wp-content/cache-static/');
                    mkdir(ABSPATH . 'wp-content/cache-static/');

                    $this->generateCache(0);

                    echo 'Reset cache ... ok. <br /><br />';

                } else if($_POST['cachestatic_action'] === 'cachestatic_config'){
                    if((int) $_POST['cachestatic_enable']){
                        update_option('cachestatic_enable', 1);
                        $this->generateCache(0);
                    } else {
                        $this->resetCache(ABSPATH . 'wp-content/cache-static/');
                        mkdir(ABSPATH . 'wp-content/cache-static/');       
                        update_option('cachestatic_enable', 0);                
                    }

                    if(! empty($_POST['cachestatic_restriction'])){
                        $cut = preg_split('/\r\n|[\r\n]/',trim($_POST['cachestatic_restriction']));
                       
                        if(is_array($cut)){
                            $urls = array();
                            foreach($cut as $c){
                                $urls[] = sanitize_text_field($c);
                            }    

                        }

                        update_option('cachestatic_restriction', $urls);

                    }
                }

            }



            $enable = (int) get_option('cachestatic_enable');
            $check = $enable ? 'checked="checked"' : '';

            echo '<form action="admin.php?page=cachestatic" method="post">
                    <input type="hidden" name="cachestatic_action" value="cachestatic_reset_cache" />
                    <input type="submit" value="Reset cache" />
                  </form>';

            echo '<br /><br />';

            echo '<form action="admin.php?page=cachestatic" method="post">

                    Active : <input type="checkbox" name="cachestatic_enable" ' . $check . ' value="1" /><br /><br />
                    <input type="hidden" name="cachestatic_action" value="cachestatic_config" />
                    Cache URLs restriction (one by line) : <br />
                    <textarea name="cachestatic_restriction" cols="50" rows="10">';
                    $restrictions = get_option('cachestatic_restriction');
                    if( $restrictions && is_array($restrictions) && count($restrictions)){
                     foreach($restrictions as $url){
                        echo trim($url) . "\r\n";
                     }
                    }
            echo '</textarea><br />
                    <input type="submit" value="Save" />
                  </form>';
        }

        public function generateCache($all = 1){

            $urls = array();
            $urls[] = get_site_url();

            $ch = curl_init();
            $user_agent='Mozilla/5.0 (Windows NT 6.1; rv:8.0) Gecko/20100101 Firefox/8.0';
            
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt ($ch, CURLOPT_HEADER, 0);
            curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt ($ch,CURLOPT_MAXREDIRS, 10);
            curl_setopt ($ch,CURLOPT_CONNECTTIMEOUT, 120);

            if($all){
                $pages = get_pages('post_status=publish');

                foreach($pages as $page) {
                  $urls[] = get_permalink($page->ID);
                }

            }

            foreach($urls as $url){
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_exec($ch);
            }

            curl_close($ch);
        }

        public function resetCache($dir) { 

            if(is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) { 
                    if ($object != "." && $object != "..") { 
                        if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir.DIRECTORY_SEPARATOR.$object))
                            $this->resetCache($dir. DIRECTORY_SEPARATOR .$object);
                        else
                            unlink($dir. DIRECTORY_SEPARATOR .$object); 
                    } 
                }   

                rmdir($dir);
            } 
         }

        public function start_buffer(){

            global $wp;

            $restrictions = get_option('cachestatic_restriction');

            $disallow = 0;

            if(is_array($restrictions) && count($restrictions)){
                 foreach($restrictions as $restriction){
                    if(strstr('/'. $wp->request, $restriction)){
                        $disallow = 1;
                        break;
                    }
                 }
            }
           
            if(! is_user_logged_in() && ! $disallow && substr($_SERVER['REQUEST_URI'], -1) === '/' && (int) get_option('cachestatic_enable') === 1){
                $cut = explode('/', $wp->request);

                $this->currentCachePath = ABSPATH . 'wp-content/cache-static/';

                foreach($cut as $dir){
                    $this->currentCachePath .= $dir . '/';
                    if(! file_exists($this->currentCachePath)){
                        mkdir($this->currentCachePath);
                    }
                }

                $this->isCache = 1;
            } else {
                $this->isCache = 0;
            }

 
            ob_start(array($this, 'foo_buffer_callback'));
        }


       public function foo_buffer_callback($buffer){
            if($this->isCache){
                file_put_contents($this->currentCachePath . 'index.html', $buffer);
            }
 
            return $buffer;
         }   

         public function shutdown(){
             ob_end_flush();
         }
    }

    $cachestatic = new Cachestatic();

?>