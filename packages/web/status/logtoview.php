<?php
require_once('../commons/base.inc.php');
header('Content-Type: text/event-stream');
header('Connection: close');
$vals = function($reverse,$HookManager) {
    ini_set("auto_detect_line_endings", true);
    $folder = sprintf('/%s/',trim(trim(dirname($_REQUEST['file'])),'/'));
    $pattern = sprintf('#^%s$#',$folder);
    $folders = array('/var/log/fog/','/opt/fog/log/','/var/log/httpd/','/var/log/apache2/','/var/log/nginx/','/var/log/php-fpm/','/var/log/php5.6-fpm/','/var/log/php5-fpm/','/var/log/php7.0-fpm/');
    $HookManager->processEvent('LOG_FOLDERS',array('folders'=>&$folders));
    if (!preg_grep($pattern,$folders)) return _('Invalid Folder');
    $file = trim(basename($_REQUEST['file']));
    $path = sprintf('%s%s',$folder,$file);
    if (($fh = fopen($path,'rb')) === false) return _('Unable to open file for reading');
    $lines = (int)$_REQUEST['lines'];
    $buffer = 4096;
    fseek($fh, -1, SEEK_END);
    if (fread($fh, 1) != "\n") $lines -= 1;
    $output = '';
    $chunk = '';
    while (ftell($fh) > 0 && $lines >= 0) {
        $seek = min(ftell($fh),$buffer);
        fseek($fh,-$seek,SEEK_CUR);
        $output = ($chunk = fread($fh,$seek)).$output;
        fseek($fh,-mb_strlen($chunk,'8bit'),SEEK_CUR);
        $lines -= substr_count($chunk,"\n");
    }
    while ($lines++ < 0) $output = substr($output,strpos($output,"\n")+1);
    fclose($fh);
    if ($reverse) $output = implode("\n",array_reverse(explode("\n",$output)));
    return trim($output);
};
$url = trim($FOGCore->aesdecrypt($_REQUEST['ip']));
$ip = $FOGCore->resolveHostname($url);
if (filter_var($ip,FILTER_VALIDATE_IP) === false) {
    echo json_encode(_('IP Passed is incorrect'));
} else {
    if ($url != $ip) $ip = $url;
    $pat = sprintf('#%s#',$ip);
    if (preg_match($pat,$_SERVER['HTTP_HOST'])) echo json_encode($vals((int) $_REQUEST['reverse'],$HookManager));
    else {
        $url = sprintf('http://%s/fog/status/logtoview.php',$ip);
        $url = filter_var($url,FILTER_SANITIZE_URL);
        $response = $FOGURLRequests->process($url,'POST',array(
            'ip'=>$FOGCore->aesencrypt($ip),
            'file'=>$_REQUEST['file'],
            'lines'=>$_REQUEST['lines'],
            'reverse'=>(int) $_REQUEST['reverse'])
        );
        echo array_shift($response);
    }
}
exit;
