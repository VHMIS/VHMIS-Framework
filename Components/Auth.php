<?php

/**
 * Auth
 *
 * Thực hiện quá trình kiểm tra, thiết lập và lấy thông tin đăng nhập, đăng xuất
 *
 * PHP 5
 *
 * VHMIS(tm) : Viethan IT Management Information System
 * Copyright 2011, IT Center, Viethan IT College (http://viethanit.edu.vn)
 *
 * All rights reversed, giữ toàn bộ bản quyền, các thư viện bên ngoài xin xem file thông tin đi kèm
 *
 * @copyright     Copyright 2011, IT Center, Viethan IT College (http://viethanit.edu.vn)
 * @link          https://github.com/VHIT/VHMIS VHMIS(tm) Project
 * @category      VHMIS
 * @package       Components
 * @subpackage    Auth
 * @since         1.0.0
 * @license       All rights reversed
 */

class Vhmis_Component_Auth
{
    protected $_adapterDb;
    protected $_adapterWebmail;
    protected $_dbUser;
    protected $_dbUserGroup;
    protected $_user;
    protected $_group;
    protected $_session;

    public function __construct()
    {
        $db = Vhmis_Configure::get('DbSystem');
        $this->_dbUser = new Vhmis_Model_System_User(array('db' => $db));
        //$this->_dbUserGroup = new Vhmis_Model_System_User_Group(array('db' => $db));

        // Session
        Zend_Session::start();
        $this->_session = new Zend_Session_Namespace('Auth');

        // Thông tin người dùng
        $this->_user = $this->_findUserInfo();
        //$this->_group = $this->_findGroupInfo();
    }

    /**
     * Kiểm tra người dùng đã đăng nhập hay chưa, xem thêm phương thức getUser
     *
     * @return boolean
     */
    public function isLoggedIn()
    {
        if($this->_user === null) return false;
        return true;
    }

    /**
     * Lấy thông tin của người dùng
     *
     * @return array Mảng chứa thông tin người dùng
     */
    public function getUser()
    {
        if($this->_user === null) return null;

        if($this->_group !== null)
        {
            $groups = array();
            foreach($this->_group as $group)
            {
                $groups[] = $group->id;
            }
        }
        else
        {
            $groups = null;
        }

        return array(
            'username' => $this->_user->username,
            'realname' => $this->_user->realname,
            'id' => $this->_user->id,
            'department' => $this->_user->hrm_department_id,
            'hrm_id' => $this->_user->hrm_id,
            'groups' => $groups
        );
    }

    /**
     * Thực hiện đăng nhập
     *
     * @param string $username Tên người dùng
     * @param string $password Mật khẩu người dùng
     * @return int 0 : Đăng nhập ko thành công, 1 : Đăng nhập thành công lần đầu qua webmail (có khởi tạo tài khoản), 2 Đăng nhập thành công
     */
    public function login($username, $password)
    {
        $user = $this->_dbUser->getUserByUsername($username);

        //Kiểm tra nội bộ
        $ok = false;
        if($user != null)
        {
            $passwordHash = Vhmis_Utility_String::hash($password, $user->password_salt);
            if($passwordHash == $user->password)
            {
                $this->_session->username = $username;
                $this->_session->password = $passwordHash;
                return 2;
            }
        }

        // Kiểm tra qua webmail
        if($ok == false)
        {
            if($this->_webmailLogin($username, $password) != false)
            {
                $passwordSalt = Vhmis_Utility_String::random('alnum', 20);
                $password = Vhmis_Utility_String::hash($password, $passwordSalt);

                $this->_session->username = $username;
                $this->_session->password = $password;

                if($user != null)
                {
                    // Update trong hệ thống
                    $user->password_salt = $passwordSalt;
                    $user->password = $password;
                    $user->save();
                    return 2;
                }
                else
                {
                    // Tạo mới người dùng trong hệ thống
                    $user = $this->_dbUser->fetchNew();
                    $user->username = $username;
                    $user->password_salt = $passwordSalt;
                    $user->password = $password;
                    $user->active = 1;
                    $user->save();
                    return 1;
                }
            }
        }

        return 0;
    }

    /**
     * Thực hiện đăng xuất
     */
    public function logout()
    {
        $this->_session->username = null;
        $this->_session->password = null;
    }

    /**
     * Lấy thông tin của người dùng
     *
     * @return mixed Nếu không có thì null, nếu có thì thông tin người dùng nằm trong đối tượng Row của Vhmis_Model_System_User
     */
    public function _findUserInfo()
    {
        if(!$this->_session->username || $this->_session->username === null) return null;
        if(!$this->_session->password || $this->_session->password === null) return null;

        return $this->_dbUser->getUserByLogin($this->_session->username, $this->_session->password);
    }

    public function _findGroupInfo()
    {
        if($this->_user === null) return null;

        $this->_dbUserGroup->getGroupOfUser($this->_user->id);
    }

    /**
     * Login qua Webmail
     *
     * @param string $user Username
     * @param string $pass Password, không mã hóa
     * @return boolean Kết quả
     */
    protected function _webmailLogin($user, $pass)
    {
        $request = new Vhmis_Network_Http_Curl();
        $request->setRequestInfo(
            'http://mail.viethanit.edu.vn:4040/zmail/jsp/Login.jsp',
            'POST',
            'http://mail.viethanit.edu.vn:4040/zmail/jsp/LoginF.jsp?language=en',
            'language_code=en&domain_idx=0&member_id=' . $user . '&password=' . $pass
        );
        $requestResult = $request->sendSimpleRequest();

        if(strpos($requestResult, 'Login Check Error') === false)
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}