<?php 

include_once 'config.php';

CONST APP_VERSION = '0.1';

class Api {
    /**
     * array
     */
    private $urls = null;
    
    /**
     * arrya
     */
    private $options = array();
    
    public function callAction() {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        switch ($action) {
            case 'add':
                $this->addUrl();
                break;
            case 'delete':
                $this->deleteUrl();
                break;
            case 'speed':
                $this->updateSpeed();
                break;
             case 'hideTabBar':
                $this->hideTabBar();
                break;
        }
    }
    
    public function getOptions() {
        return $this->options;
    }
    
    public function getUrls() {
        if (! is_null($this->urls)) {
            return $urls;
        }
        
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'urls.json';
        if (! file_exists($path)) {
            // read initial url
            if (! file_exists(INIT_URL_PATH)) {
                echo 'Cannot find init url at ' . INIT_URL_PATH;
                exit;
            }
    
            $url = file_get_contents(INIT_URL_PATH);
            $entity = $this->getEntity('Default', $url);
    
            $this->urls = array($entity);
            $this->options['version'] = APP_VERSION;
    
            $this->store();
    
            return $this->urls;
        }
    
        $this->read();
        
        return $this->urls;
    }
    
    private function getEntity($title, $url) {
        return array(
            'title' => $title,
            'url' => $url,
        );
    }
    
    private function read() {
        $json = json_decode(file_get_contents('urls.json'), true);
        $this->options = $json['options'];
        $this->urls = $json['urls'];
    }
    
    private function store() {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'urls.json';
        
        $data = array(
            'options'   => $this->options,
            'urls'      => $this->urls,
        );
        
        file_put_contents($path, json_encode($data));
    }
    
    private function addUrl() {
        $this->read();
        $this->urls[] = $this->getEntity($_POST['title'], $_POST['url']);
        $this->store();
    }   
    
    private function updateSpeed() {
        $this->read();
        $this->options['speed'] = (int) $_POST['value'];
        $this->store();
    }

    private function hideTabBar() {
        $this->read();
        $this->options['hideTabBar'] = $_POST['value'] == 'true' ? 1 : 0;
        $this->store();
    }
    
    private function deleteUrl() {
        $this->read();
        
        $title = $_POST['title'];
        $id = $_POST['id'];
        
        if ($this->urls[$id]['title'] == $title) {
            unset($this->urls[$id]);
        }
        
        $newUrls = array();
        foreach ($this->urls as $url) {
            $newUrls[] = $url;
        }
        $this->urls = $newUrls;
        
        $this->store();
    }
}

$api = new Api();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $api->callAction();
}
