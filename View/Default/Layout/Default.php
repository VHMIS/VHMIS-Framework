<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title><? if(isset($viewPageTitle)) echo $viewPageTitle . ' | VHMIS'; else echo 'VHMIS'; ?></title>

        <!-- Mo phong HTML5 danh cho IE6-8 -->
        <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <!-- Style -->
        <link href="/VHMIS_WWW/client/default/css/default.css" rel="stylesheet">
        <?php $this->block('CssFiles', $viewJsFiles); ?>

        <!-- Javascript -->
        <script type="text/javascript" language="javascript">
            var sitePath = '/VHMIS_WWW/';
        </script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
        <script src="/VHMIS_WWW/client/default/js/default.js"></script>
        <?php $this->block('JsFiles', $viewJsFiles); ?>
    </head>
    <body>
        <header>
            <div class="container global_bar">
                <ul class="nav pull-left">
                    <li class="active"><a href="#">Giao diện</a></li>
                </ul>
                <ul class="nav pull-right">
                    <li><a href="#"><?php echo $userInfo['realname']; ?></a></li>
                    <li><a href="<?php echo $config['site']['path'] . $config['apps']['logout-url']; ?>">Thoát</a></li>
                </ul>
            </div>
            <div class="container app_header">
                <h1>Default</h1>
                <nav>
                    <ul class="menu">
                        <li class="sub_menu active"><a href="/giaodien/default/docs/index.html">Giới thiệu</a></li>
                        <li class="sub_menu"><a href="/giaodien/default/docs/scaffoding.html">Dàn trang</a></li>
                        <li class="sub_menu"><a href="/giaodien/default/docs/base_css.html">CSS Cơ bản</a></li>
                        <li class="sub_menu"><a href="/giaodien/default/docs/components.html">Thành phần trang</a></li>
                        <li class="sub_menu"><a href="/giaodien/default/docs/javascript.html">Javascript</a></li>
                        <li class="sub_menu"><a href="/giaodien/default/example.html">Ví dụ</a></li>
                    </ul>
                </nav>
            </div>
            <div id="page_header">
                <div class="container page_header_bar">
                    <ul class="breadcrumb">
                        <li><a href="/giaodien/default/">Default</a> <span class="divider">/</span></li>
                        <li class="active">Giới thiệu</li>
                    </ul>
                    <h1>Giới thiệu <small>Bla Bla</small></h1>
                </div>
            </div>
        </header>
        <div id="page_container">
            <div class="container">
                <?php echo $content; ?>
            </div>
        </div>
        <footer>
            <div class="container footer">
                <p>
                    Giao diện "Default" là giao diện mặc định được sử dụng trong quá trình phát triển hệ thống VHMIS &copy; Trung Tâm CNTT - Trường CĐ CNTT Hữu Nghị Việt Hàn 2011<br />
                    Sử dụng HTML5, CSS3 và Javascript, tương thích với các trình duyệt hiện đại như <a href="#">Chrome, Firefox, Safari</a>
                </p>
            </div>
        </footer>
    </body>
</html>