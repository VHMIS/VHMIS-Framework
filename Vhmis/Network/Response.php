<?php

/**
 * Vhmis Framework
 *
 * @link http://github.com/micti/VHMIS-Framework for git source repository
 * @copyright Le Nhat Anh (http://lenhatanh.com)
 * @license http://opensource.org/licenses/MIT MIT License
 */

namespace Vhmis\Network;

use Vhmis\Config\Configure;

/**
 * Class trả lại kết quả tới client
 *
 * @category Vhmis
 * @package Vhmis_Network
 */
class Response
{

    /**
     * Body content
     * 
     * @var string
     */
    protected $body;

    /**
     * Gửi kết quả xử lý tới client
     */
    public function response()
    {
        // header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
        // header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
        $this->sendContent();
    }

    /**
     * Thông báo lỗi
     */
    public function reponseError($code)
    {
        if ($code === '404') {
            header('HTTP/1.0 404 Not Found');
            echo 'Page not found 404';
            exit();
        }

        if ($code === '403') {
            header('HTTP/1.0 403 Forbidden');
            echo 'Forbidden!';
            exit();
        }
    }

    /**
     * Thiết lập nội dung trả về
     *
     * @param string nội dung trả về
     */
    public function body($content)
    {
        $this->body = $content;

        return $this;
    }

    /**
     * Tải file
     *
     * @param string $filepath
     * @param string $filename
     * @param string $filetype
     */
    public function download($path, $filename, $type = null)
    {
        header('Content-disposition: attachment; filename="' . $filename . '"');

        // Xác định file type
        if (!is_string($type)) {
            if ($finfo = new \finfo(FILEINFO_MIME_TYPE)) {
                $type = $finfo->file($path);
            }
        } else {
            header('Content-type: ' . $type);
        }

        flush();
        readfile($path);

        exit();
    }

    public function redirect($path)
    {
        header('Location: ' . $path);
        exit();
    }

    /**
     * Gửi nội dung trả về
     *
     * @param string Nội dung trả về
     */
    protected function sendContent()
    {
        $benmark = Configure::get('Benchmark');
        $body = str_replace('::::xxxxx-memory-xxxx::::', memory_get_usage(), $this->body);
        echo str_replace('::::xxxxx-time-xxxx::::', $benmark->time('start', 'stop'), $body);
    }
}