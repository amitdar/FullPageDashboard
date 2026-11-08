<?php 

include_once __DIR__ . '/config.php';

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
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'urls.json';
        $backup = $path . '.bak';
        $json = $this->loadState($path);
        if ($json !== null) {
            // Seed a backup for existing installations, without replacing a good one.
            if ($this->loadState($backup) === null) {
                $this->preserveCorruptState($backup);
                $this->writeState($backup, json_encode($json));
            }
            $this->options = $json['options'];
            $this->urls = $json['urls'];
            return;
        }

        $this->preserveCorruptState($path);
        $json = $this->loadState($backup);
        if ($json !== null) {
            $this->options = $json['options'];
            $this->urls = $json['urls'];
            $this->store();
            error_log('Dashboard state restored from ' . $backup);
            return;
        }

        $url = file_get_contents(INIT_URL_PATH);
        if ($url === false) {
            throw new RuntimeException('Cannot read initial dashboard URL');
        }
        $this->urls = array($this->getEntity('Default', $url));
        $this->options = array('version' => APP_VERSION);
        $this->store();
    }

    private function loadState($path) {
        if (! file_exists($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read dashboard state: ' . $path);
        }
        $json = json_decode($contents, true);
        return $this->validState($json) ? $json : null;
    }

    private function preserveCorruptState($path) {
        if (! file_exists($path)) {
            return;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read corrupt dashboard state');
        }
        $archive = tempnam(__DIR__, basename($path) . '.corrupt-');
        if ($archive === false || dirname($archive) !== __DIR__ ||
            file_put_contents($archive, $contents) !== strlen($contents)) {
            throw new RuntimeException('Cannot preserve corrupt dashboard state');
        }
        error_log('Invalid dashboard state preserved in ' . $archive);
    }

    private function validState($json) {
        if (! is_array($json) || ! isset($json['options'], $json['urls']) ||
            ! is_array($json['options']) || ! is_array($json['urls'])) {
            return false;
        }
        if (array_values($json['urls']) !== $json['urls']) {
            return false;
        }
        foreach ($json['urls'] as $url) {
            if (! is_array($url) || ! isset($url['title'], $url['url']) ||
                ! is_string($url['title']) || ! is_string($url['url'])) {
                return false;
            }
        }
        return true;
    }
    
    private function store() {
        $path = __DIR__ . DIRECTORY_SEPARATOR . 'urls.json';
        
        $data = array(
            'options'   => $this->options,
            'urls'      => $this->urls,
        );
        
        $json = json_encode($data);
        if ($json === false) {
            throw new RuntimeException('Cannot encode dashboard state');
        }

        // Save the previous valid version before replacing the primary.
        $current = $this->loadState($path);
        $backup = $path . '.bak';
        $saved = $this->loadState($backup);
        if ($current !== null || $saved === null) {
            if ($saved === null) {
                $this->preserveCorruptState($backup);
            }
            $this->writeState($backup, json_encode($current !== null ? $current : $data));
        }
        $this->writeState($path, $json);
    }

    private function writeState($path, $json) {
        $temporary = tempnam(__DIR__, '.urls-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create temporary dashboard state');
        }
        $stream = null;
        try {
            if (dirname($temporary) !== __DIR__) {
                throw new RuntimeException('Temporary dashboard state must be on the same filesystem');
            }
            $stream = fopen($temporary, 'wb');
            if ($stream === false || fwrite($stream, $json) !== strlen($json) || ! fflush($stream)) {
                throw new RuntimeException('Cannot write dashboard state');
            }
            if (function_exists('fsync') && ! fsync($stream)) {
                throw new RuntimeException('Cannot sync dashboard state');
            }
            fclose($stream);
            $stream = null;
            $permissions = file_exists($path) ? fileperms($path) & 0777 : 0644;
            if (! chmod($temporary, $permissions) || ! rename($temporary, $path)) {
                throw new RuntimeException('Cannot replace dashboard state');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (file_exists($temporary)) {
                unlink($temporary);
            }
        }
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
