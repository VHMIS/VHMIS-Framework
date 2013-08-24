<?php
/**
 * Vhmis Framework (http://vhmis.viethanit.edu.vn/developer/vhmis)
 *
 * @link http://vhmis.viethanit.edu.vn/developer/vhmis Vhmis Framework
 * @copyright Copyright (c) IT Center - ViethanIt College (http://www.viethanit.edu.vn)
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace Vhmis\Controller;

use \Vhmis\Network;
use \Vhmis\Di\ServiceManager;
use \Vhmis\View\View;

/**
 * Controller
 *
 * @category Vhmis
 * @package Vhmis_Controller
 */
class Controller implements \Vhmis\Di\ServiceManagerAwareInterface
{
    /**
     * Thông tin Apps và Request (chủ yếu dùng khi chuyển qua đối tượng khác).
     */
    public $appInfo;

    /**
     * Tên App
     */
    public $app;

    /**
     * Tên url cua app (dung de lam dia chi, dat ten bien).
     */
    public $appUrl;

    /**
     * Tên controller
     */
    public $controller;

    /**
     * Tên Action
     */
    public $action;

    /**
     * Các thông số đi kèm
     */
    public $params;

    /**
     * Kiểu xuất ra
     */
    public $output;

    /**
     * Container
     *
     * @var \Vhmis\Di\ServiceManager
     */
    public $sm;

    /**
     * View
     *
     * @var \Vhmis\View\View
     */
    public $view;

    /**
     * @var \Vhmis\Network\Response
     */
    public $response;

    /**
     * @var \Vhmis\Network\Request
     */
    public $request;

    /**
     * Khởi tạo
     *
     * @param \Vhmis\Network\Request $request
     * @param \Vhmis\Network\Response $response
     */
    public function __construct(Network\Request $request = null, Network\Response $response = null)
    {
        $this->request = $request != null ? $request : new Network\Request();
        $this->response = $response != null ? $response : new Network\Response();
        $this->view = new View;

        $this->appInfo = $request->app;
        $this->app = $this->appInfo['app'];
        $this->appUrl = $this->appInfo['appUrl'];

        $this->action = $this->appInfo['action'];
        $this->params = $this->appInfo['params'];
        $this->output = $this->findOutputType($this->appInfo['output']);
        $this->controller = $this->appInfo['controller'];
    }

    /**
     * Thiết lập Service Manager
     *
     * @param \Vhmis\Di\ServiceManager $sm
     */
    public function setServiceManager(ServiceManager $sm)
    {
        $this->sm = $sm;
    }

    /**
     * Thực thi request
     */
    public function init()
    {
        $this->beforeInit();

        $action = 'action' . $this->action;

        $this->view->setTemplate('Default')->setLayout('Default')->setAppUrl($this->appUrl)->setOutput($this->output);
        $this->view->setApp($this->app)->setController($this->controller)->setMethod($this->action);

        if (method_exists($this, $action)) {
            $this->$action();
        } else {
            // throw new \Exception('Not found ' . $this->action . ' action. Create new method : ' . $action);
            echo 'Not found ' . $this->action . ' action. Create new method : ' . $action;
            exit();
        }

        $content = $this->view->render();

        $this->response->body($content)->response();

        $this->afterInit();
    }

    public function beforeInit()
    {

    }

    public function afterInit()
    {
        exit();
    }

    /**
     * Tìm kiểu trả về
     *
     * html|json|xml|text
     *
     * @param string $output
     * @return string
     */
    protected function findOutputType($output)
    {
        if ($output === 'auto') {
            if ($this->request->isAjaxRequest()) {
                $output = $this->request->findAjaxReponseContentType();
            } else {
                $output = 'html';
            }
        }

        return $output;
    }

    /**
     * Lấy model, sử dụng tên class (bắt đầu từ tên App)
     *
     * Ví dụ \YourSystem\Apps\App1\Model\Model1 thì tên model là App1\Model\Model1
     *
     * @param string $model Tên Model
     * @return \Vhmis\Db\ModelInterface
     * @throws \Exception
     */
    protected function getModel($model)
    {
        $model = $this->sm->getModel($model);

        if ($model === null) {
            throw new \Exception('Model ' . $model . 'not found');
        }

        return $model;
    }

    /**
     * Lấy model của app hiện hành
     *
     * @param string $model
     * @return \Vhmis\Db\ModelInterface
     * @throws \Exception
     */
    protected function model($model)
    {
        $fullname = $this->appInfo['app'] . '\Model\\' . $model;

        $model = $this->sm->getModel($fullname);

        if ($model === null) {
            throw new \Exception('Model ' . $model . 'not found');
        }

        return $model;
    }

    /**
     * Thiết lập dữ liệu cho view
     *
     * @param type $key
     * @param type $data
     * @return \Vhmis\Controller\Controller
     */
    public function set($key, $data)
    {
        $this->view->setData($key, $data);

        return $this;
    }

    /**
     * Gọi view
     *
     * @param mixed $info
     * @param string $view
     * @param string $layout
     * @param string $template
     */
    public function end($data, $view = '', $layout = '', $template = '')
    {
        if ($view !== '') {
            $this->view->setMethod($view);
        }

        if ($layout !== '') {
            $this->view->setLayout($layout);
        }

        if ($template !== '') {
            $this->view->setTemplate($template);
        }

        $content = $this->view->render($data);

        $this->response->body($content)->response();

        $this->afterInit();
    }

    /**
     * Xuất thông báo lỗi
     *
     * @param mixed $info
     * @param string $layout
     */
    public function error($data, $view = '', $layout = '')
    {
        if ($layout == '') {
            $layout = 'Error';
        }

        $this->view->setNoView();

        $content = $this->view->setLayout($layout)->render($data);

        $this->response->body($content)->response();

        $this->afterInit();
    }
}
