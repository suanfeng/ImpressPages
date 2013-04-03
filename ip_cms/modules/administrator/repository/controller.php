<?php
/**
 * @package ImpressPages
 * @copyright   Copyright (C) 2011 ImpressPages LTD.
 * @license see ip_license.html
 */
namespace Modules\administrator\repository;


class Controller extends \Ip\Controller{

    public function allowAction($action) {
        return \Ip\Backend::loggedIn();
    }

    /**
     * Move files from temporary folder to repository.
     */
    public function storeNewFiles()
    {
        if (!isset($_POST['files']) || !is_array($_POST['files'])) {
            $this->returnJson(array('status' => 'error', 'errorMessage' => 'Missing POST variable'));
        }

        $files = isset($_POST['files']) ? $_POST['files'] : array();


        foreach ($files as $key => $file) {
            if ($file['dir'] != TMP_FILE_DIR) {
                throw new \Exception("File is outside TMP dir.");
            }
        }


        $newFiles = array();

        $destination = BASE_DIR.FILE_REPOSITORY_DIR;
        foreach ($files as $key => $file) {
            $newName = \Library\Php\File\Functions::genUnoccupiedName($file['renameTo'], $destination);
            copy(BASE_DIR.$file['file'], $destination.$newName);
            /*
             * plugin which uses repository had no chance to bind this file yet.
             * But repository requires all files to be bind. So repository automatically
             * binds all new files to itself. Later cron automatically unbinds all files
             * that are bind to repository. If file is not bind to any other module / plugin at that time,
             * file is removed.
             */
            \Modules\administrator\repository\Model::bindFile(FILE_DIR.$newName, 'administrator/repository', 0);

            unlink(BASE_DIR.$file['file']);
            $newFile = array(
                'fileName' => $newName,
                'dir' => $destination,
                'file' => FILE_REPOSITORY_DIR.$newName
            );
            $newFiles[] = $newFile;
        }
        $answer = array(
            'status' => 'success',
            'files' => $newFiles
        );

        $this->returnJson($answer);
    }


    /**
     * Upload file to temporary folder
     */
    public function upload()
    {
        $dispatcher = \Ip\ServiceLocator::getDispatcher();

        if (!isset($_SESSION['backend_session']['user_id'])) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 201, "message": "Try to upload image to temporary directory without permission."}, "id" : "id"}');
        }

        // Settings
        //$targetDir = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "plupload";
        $targetDir = BASE_DIR.TMP_FILE_DIR;

        //$cleanupTargetDir = false; // Remove old files
        //$maxFileAge = 60 * 60; // Temp file age in seconds

        // Get parameters
        $chunk = isset($_REQUEST["chunk"]) ? $_REQUEST["chunk"] : 0;
        $chunks = isset($_REQUEST["chunks"]) ? $_REQUEST["chunks"] : 0;
        $fileName = isset($_REQUEST["name"]) ? $_REQUEST["name"] : '';

        // Clean the fileName for security reasons
        $fileName = \Library\Php\File\Functions::cleanupFileName($fileName);

        // Make sure the fileName is unique but only if chunking is disabled
        if ($chunks < 2 && file_exists($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
            $fileName = \Library\Php\File\Functions::genUnoccupiedName($fileName, $targetDir. DIRECTORY_SEPARATOR);
        }

        //security check
        $fileExtension = strtolower(substr($fileName, strrpos($fileName, '.') + 1));

        $event = new Event\ForbiddenExtensions($this);
        $dispatcher->notify($event);
        $disallow = $event->getForbiddenExtensions();
        if (in_array($fileExtension, $disallow)) {
            //security risk
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Forbidden file extension: '.$fileExtension.'."}, "id" : "id"}');
        }

        //end security check


        // Look for the content type header
        if (isset($_SERVER["HTTP_CONTENT_TYPE"]))
            $contentType = $_SERVER["HTTP_CONTENT_TYPE"];

        if (isset($_SERVER["CONTENT_TYPE"]))
            $contentType = $_SERVER["CONTENT_TYPE"];

        // Handle non multipart uploads older WebKit versions didn't support multipart in HTML5
        if (strpos($contentType, "multipart") !== false) {
            if (isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
                // Open temp file
                $out = fopen($targetDir . $fileName, $chunk == 0 ? "wb" : "ab");
                if ($out) {
                    // Read binary input stream and append it to temp file
                    $in = fopen($_FILES['file']['tmp_name'], "rb");

                    if ($in) {
                        while ($buff = fread($in, 4096))
                            fwrite($out, $buff);
                    } else
                        die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
                    fclose($in);
                    fclose($out);
                    @unlink($_FILES['file']['tmp_name']);
                } else
                    die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
            } else
                die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
        } else {
            // Open temp file
            $out = fopen($targetDir . DIRECTORY_SEPARATOR . $fileName, $chunk == 0 ? "wb" : "ab");
            if ($out) {
                // Read binary input stream and append it to temp file
                $in = fopen("php://input", "rb");

                if ($in) {
                    while ($buff = fread($in, 4096)) {
                        if(function_exists('set_time_limit'))
                        {
                            set_time_limit(30);
                        }
                        fwrite($out, $buff);
                    }
                } else
                    die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');

                fclose($in);
                fclose($out);
            } else
                die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
        }

        // Return JSON-RPC response
        $answerArray = array(
            "jsonrpc" => "2.0",
            "result" => null,
            "id" => "id",
            "fileName" => $fileName,
            "dir" => TMP_FILE_DIR,
            "file" => TMP_FILE_DIR.$fileName
        );

        $this->returnJson($answerArray);

    }

    public function deleteTmpFile()
    {
        if (!isset($_POST['file'])) {
            $answer = array(
                'status' => 'error',
                'error' => 'Missing post variable'
            );
            $this->returnJson($answer);
        }

        $file = realpath($_POST['file']);

        if (strpos($file, BASE_DIR.TMP_FILE_DIR) !== 0) {
            $answer = array(
                'status' => 'error',
                'error' => 'Trying to access file outside temporary dir'
            );
            $this->returnJson($answer);
        }


        if (file_exists($file)) {
            unlink($file);
        }

        $answer = array(
            'status' => 'success'
        );
        $this->returnJson($answer);
    }

    public function getAll()
    {

        if(isset($_POST['seek'])) {
            $seek = (int) $_POST['seek'];
        } else {
            $seek = 0;
        }

        $limit = 100;


        $browserModel = BrowserModel::instance();
        $answer = $browserModel->getAvailableFiles($seek, $limit);


        $this->returnJson($answer);
    }

}
