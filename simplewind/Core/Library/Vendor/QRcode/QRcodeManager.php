<?php
/**
 * Created by Eva
 * User: Administrator
 * Date: 17-5-17
 * Time: 下午8:04
 */
require_once(dirname(__FILE__).'/phpqrcode.php');
class QRcodeManager {
    public function png($text, $outfile=false, $level=QR_ECLEVEL_L, $size=3, $margin=4, $saveandprint=false)
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encodePNG($text, $outfile, $saveandprint=false);
    }
} 