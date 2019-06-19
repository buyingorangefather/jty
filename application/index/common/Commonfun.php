<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/14
 * Time: 16:12
 */

namespace app\index\common;


use think\Exception;

class Commonfun
{
    static public function https_request($url,$data=null)
    {
        $curl = curl_init();
        curl_setopt($curl,CURLOPT_URL,$url);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);
        if(!empty($data)){
            curl_setopt($curl,CURLOPT_POST,1);
            curl_setopt($curl,CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
        //Content-Type: application/json 修改  zsh
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($data)
        ));
        $output = curl_exec($curl);
        curl_close($curl);
        return $output;
    }

    /**
     * @param $str  文件名
     * @return string 文件类型
     */
    static public function file_format($str)
    {
        try{
            // 取文件后缀名
            $str = strtolower(pathinfo($str, PATHINFO_EXTENSION));
            // 图片格式
            $image = array('webp', 'jpg', 'png', 'ico', 'bmp', 'gif', 'tif', 'pcx', 'tga', 'bmp', 'pxc', 'tiff', 'jpeg', 'exif', 'fpx', 'svg', 'psd', 'cdr', 'pcd', 'dxf', 'ufo', 'eps', 'ai', 'hdri');
            // 视频格式
            $video = array('mp4', 'avi', '3gp', 'rmvb', 'gif', 'wmv', 'mkv', 'mpg', 'vob', 'mov', 'flv', 'swf', 'mp3', 'ape', 'wma', 'aac', 'mmf', 'amr', 'm4a', 'm4r', 'ogg', 'wav', 'wavpack');
            // 压缩格式
            $zip = array('rar', 'zip', 'tar', 'cab', 'uue', 'jar', 'iso', 'z', '7-zip', 'ace', 'lzh', 'arj', 'gzip', 'bz2', 'tz');
            // 文档格式
            $text = array('exe', 'doc', 'ppt', 'xls', 'wps', 'txt', 'lrc', 'wfs', 'torrent', 'html', 'htm', 'java', 'js', 'css', 'less', 'php', 'pdf', 'pps', 'host', 'box', 'docx', 'word', 'perfect', 'dot', 'dsf', 'efe', 'ini', 'json', 'lnk', 'log', 'msi', 'ost', 'pcs', 'tmp', 'xlsb');
            // 匹配不同的结果
            switch ($str) {
                case in_array($str, $image):
                    return 'image';
                    break;
                case in_array($str, $video):
                    return 'video';
                    break;
                case in_array($str, $zip):
                    return 'zip';
                    break;
                case in_array($str, $text):
                    return 'text';
                    break;
                default:
                    return 'other';
                    break;
            }
        }catch (Exception $e){
            return 'other';
        }

    }
}