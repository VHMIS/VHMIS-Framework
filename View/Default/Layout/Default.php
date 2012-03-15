<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title><?php if(isset($pageTitleBar)) echo $pageTitleBar . ' | VHMIS'; else echo 'VHMIS'; ?></title>

        <!-- Mo phong HTML5 danh cho IE6-8 -->
        <!--[if lt IE 9]>
        <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
        <![endif]-->

        <!-- Style -->
        <link href="<?php echo $config['site']['fullclient']; ?>css/default.css" rel="stylesheet">
<?php
if(isset($cssFiles))
{
    foreach($cssFiles as $file)
    {
        if(strpos('http://', $file) === true) echo '        <link href="' . $file . '" rel="stylesheet">' . "\n";
        else echo '        <link href="' . $config['site']['fullclient'] . $file . '" rel="stylesheet">' . "\n";
    }
}
?>

        <!-- Javascript -->
        <script type="text/javascript" language="javascript">
            var sitePath = '<?php echo $config['site']['path']; ?>';
        </script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
        <script src="<?php echo $config['site']['fullclient']; ?>js/bootstrap.js"></script>
        <script src="<?php echo $config['site']['fullclient']; ?>js/default.js"></script>
<?php
if(isset($jsFiles))
{
    foreach($jsFiles as $file)
    {
        if(strpos('http://', $file) === true) echo '        <script src="' . $file . '"></script>' . "\n";
        else echo '        <script src="' . $config['site']['fullclient'] . $file . '"></script>' . "\n";
    }
}
?>
    </head>
    <body>
        <header>
            <div class="container<?php if(isset($layoutSize)) echo ' container-' . $layoutSize;?> global_bar">
                <ul class="nav pull-left">
<?php
foreach($config['apps']['list']['url'] as $appurl)
{
    echo '                    <li class="' . ($appurl === $appInfo['url'] ? 'active' : '') . '"><a href="' . $config['site']['path'] . $appurl . '">' . $config['apps']['list']['name'][$appurl] . '</a></li>' . "\n";
}

?>
                </ul>
                <ul class="nav pull-right">
                    <li><a href="#"><?php echo $userInfo['name_real']; ?></a></li>
                    <li><a href="<?php echo $config['site']['path'] . $config['apps']['logout-url']; ?>">Thoát</a></li>
                </ul>
            </div>
            <div class="container<?php if(isset($layoutSize)) echo ' container-' . $layoutSize;?> app_header">
                <h1 class="app-title-header app-title-header-<?php echo $appInfo['url']; ?>"><?php echo $appInfo['app']; ?></h1>
<?php
if(isset($_appMenu) && is_array($_appMenu))
{
    echo '                <nav>
                    <ul class="menu">' . "\n";

    foreach($_appMenu as $menu)
    {
        echo '                        <li class="sub_menu';
        echo '"><a href="' . $config['site']['fullpath'] . $menu[1] . '">' . $menu[0] . '</a>';
        if(is_array($menu[2]))
        {
            echo "\n" . '                            <ul class="sub_menu';
            if($menu[3] == 'right') echo ' right';
            echo '">' . "\n";
            foreach($menu[2] as $submenu)
            {
                echo '                                <li><a href="' . $config['site']['fullpath'] . $submenu[1] . '">' . $submenu[0] . '</a></li>' . "\n";
            }
            echo '                            </ul>'. "\n";
        }
        echo '                        </li>' . "\n";
    }

    echo '                    </ul>
                </nav>'. "\n";
}
?>
            </div>
            <div id="page_header">
                <div class="container<?php if(isset($layoutSize)) echo ' container-' . $layoutSize;?> page_header_bar">
                    <ul class="breadcrumb">
                        <li><a href="<?php echo $config['site']['path']; ?>">Trang chủ</a> <span class="divider">/</span></li>
                        <li><a href="<?php echo $config['site']['fullpath']; ?>"><?php echo $appInfo['app']; ?></a> <span class="divider">/</span></li>
<?php
if(isset($_breadcrumb) && is_array($_breadcrumb))
{
    $total = count($_breadcrumb);
    foreach($_breadcrumb as $link)
    {
        $total--;
        echo '                        <li class="';
        if($total == 0) echo 'active';
        echo '"><a href="' . $config['site']['fullpath'] . $link[1] . '">' . $link[0] . '</a>';
        if($total != 0) echo ' <span class="divider">/</span>';
        echo '</li>' . "\n";
    }
}
?>
                    </ul>
<?php
if(isset($pageTitle) && $pageTitle != '') {
    echo '                    <h1>' . $pageTitle;
    if(isset($pageTitleDes) && $pageTitleDes != '')
    {
        echo  ' <small>' . $pageTitleDes . '</small>';
    }
    echo '</h1>' . "\n";
}
?>
                </div>
            </div>
        </header>
        <div id="page_container">
            <div class="container<?php if(isset($layoutSize)) echo ' container-' . $layoutSize;?>">
                <?php echo $content; ?>

            </div>
        </div>
        <footer>
            <div class="container<?php if(isset($layoutSize)) echo ' container-' . $layoutSize;?> footer">
                <p>
                    Giao diện "Default" là giao diện mặc định được sử dụng trong quá trình phát triển hệ thống VHMIS &copy; Trung Tâm CNTT - Trường CĐ CNTT Hữu Nghị Việt Hàn 2011<br />
                    Sử dụng HTML5, CSS3 và Javascript, tương thích với các trình duyệt hiện đại như <a href="#">Chrome, Firefox, Safari</a>
                </p>
            </div>
        </footer>
    </body>
</html>