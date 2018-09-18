<?php
# MetInfo Enterprise Content Management System
# Copyright (C) MetInfo Co.,Ltd (http://www.metinfo.cn). All rights reserved.
defined('IN_MET') or exit('No permission');
load::sys_class('app');
load::sys_func('file');
load::own_class('admin/class/UI');
class uiset extends app {
    public $UI;
    public $skin_name;
    public $tem_config;
    public $tem_path;
    public $url;
    public function __construct() {
        global $_M;
        parent::__construct();
        $this->skin_name = $_M['form']['skin_name'];
        $this->UI = new UI($this->skin_name);
        $this->UI->skin_name = $this->skin_name;
        $this->tem_path     = PATH_WEB."templates/{$this->skin_name}";
        $this->tem_config     = $this->get_ui_config();
    }
    public function get_ui_config(){
        global $_M;
        $config = json_decode(file_get_contents($this->tem_path.'/ui.json'),true);
        return $config['page'];
    }
    public function dotemplate_import()
    {
        global $_M;
        $status = load::mod_class('appstore/include/inapp', 'new')->get_appstore_status();
        if(!$status['state']){
            echo json_encode(array('status'=>-1,'msg'=>$_M['word']['please_again'],'url'=>$status['loginurl']));die;
        }
        if(!file_exists($this->tem_path)){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_nofile']));die;
        }
        if($this->UI->has_skin_name()){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_fileexist']));die;
        }
        if(!file_exists($this->tem_path.'/ui.json')){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_noconfigfile']));die;
        }
        // 验证权限
        $data = array(
            'user_key'=>$_M['config']['met_secret_key'],
            'skin_name'=>$this->skin_name,
            'referer'=>$_M['url']['site']
        );
        $result = $this->UI->app_get('docheck_template',$data);

        $ui_list = $this->UI->import_ui();
        if($ui_list['status']){
            $cache['total'] = count($ui_list['data']);
            $cache['data'] = $ui_list['data'];
            file_put_contents(PATH_APP_FILE.'admin/ui_list', json_encode($cache));
        }
        echo json_encode($ui_list);die;
    }
    public function get_template_ui($parent_name,$ui_name)
    {
        global $_M;
        return file_exists($this->tem_path.'/ui/'.$parent_name.'/'.$ui_name);
    }
    public function dodel_template(){
        global $_M;
        $query = "DELETE FROM {$_M['table']['skin_table']} WHERE skin_name='{$_M['form']['skin_name']}'";
        DB::query($query);
        $uilist = $this->UI->list_tem_ui();
        foreach ($uilist as $u) {
            $del = $this->UI->delete_sub_ui($u['installid']);
            if(!$del){
                turnover("{$_M[url][own_name]}c=temtool&a=dotemlist","{$_M['word']['met_template_falsedelui']}");
            }
            $del = $this->UI->delete_local_configs($u['installid']);
            if(!$del){
                turnover("{$_M[url][own_name]}c=temtool&a=dotemlist","{$_M['word']['met_template_falsedeluiconfig']}");
            }
        }
        $del = $this->UI->delete_global_configs();
        if(!$del){
            turnover("{$_M[url][own_name]}c=temtool&a=dotemlist","{$_M['word']['met_template_falsedelconfig']}");
        }
        turnover("{$_M[url][own_name]}c=temtool&a=dotemlist");
    }


    public function doupdate()
    {
        global $_M;

        $version = $this->UI->get_tem_version($this->skin_name);

        $tem_res = $this->UI->check_tem_update($this->skin_name,$version);
        $tem = json_decode($tem_res,true);

        if($tem['status']){
            $this->download_tem($version,0);
                $config = $this->get_ui_config();

                foreach ($config as $page) {
                    foreach ($page as $key => $v) {

                        $has = $this->UI->has_ui($v['parent_name'],$v['ui_name'],$v['installid']);
                        if(!$has ){
                           $res =  $this->UI->install_ui($v);
                           if(!$res['status']){
                                echo json_encode($res);die;
                           }
                        }
                    }
                }
           $update = $this->UI->update_tem_version($this->skin_name,$tem['msg']);
        }

        $this->docheck_update();
    }

    public function docheck_update()
    {
        global $_M;
        $version = $this->UI->get_tem_version($this->skin_name);

        $tem_res = $this->UI->check_tem_update($this->skin_name,$version);
        $tem = json_decode($tem_res,true);

        if($tem['status']){
            $tem['skin_name'] = $this->skin_name;
            echo json_encode($tem);die;
        }else{
            $update_list = $this->list_update_ui();
            if($update_list){
                echo json_encode(array('status'=>1,'data'=>$update_list,'skin_name'=>$this->skin_name));die;
            }else{
                echo json_encode(array('status'=>0,'msg'=>$tem['msg']));die;
            }

        }
    }

    public function download_tem($version='1.0',$return = 1)
    {
        global $_M;
        $query = "SELECT * FROM {$_M['table']['otherinfo']} where id=1";
        $th = DB::get_one($query);
        $authkey = $th['authpass'];
        $authcode= $th['authcode'];
        load::sys_class('curl');
        $curl = new curl();
        $curl->set('file', 'index.php?n=platform&c=system&a=docheckpower');
        $post = array('type'=>'tem', 'no'=>$this->skin_name, 'ver'=>$version, 'cmsver'=>$_M['config']['metcms_v'], 'user_key'=>$_M['config']['met_secret_key'], 'authkey'=>$authkey, 'authcode'=>$authcode);
        $getdata = $curl->curl_post($post);

        list($suc, $checknum) = explode('|', $getdata);
        if(!$checknum){
            echo json_encode(array('status'=>0,'msg'=>$getdata));die;
        }
        $dlfile = load::sys_class('dlfile', 'new');
        $re = $dlfile->dlfile('dllist.php', $this->tem_path.'/dllist.php', $checknum);
        if($re){
            require $this->tem_path.'/dllist.php';
            foreach ($dllist as $file) {
                if(strstr($file, 'file/templates/'.$this->skin_name.'/')){
                    $new_file = str_replace('file/templates/'.$this->skin_name.'/', '', $file);

                    $res = $dlfile->dlfile($file, $this->tem_path.'/'.$new_file, $checknum);
                    if(!$res){
                        echo json_encode(array('status'=>0,'msg'=>$file.$_M['word']['met_template_downloadfalse']));die;
                    }
                }
            }
        }
        if($return){
            echo json_encode(array('status'=>1,'msg'=>$_M['word']['met_template_downloadok']));
        }

    }

    public function list_update_ui()
    {
        global $_M;
        $res = $this->UI->list_tem_ui();
        $ui_list = array();
        $update_list = array();
        if($res){
            foreach ($res as $key => $val) {
              $ui_list[$key]['parent_name'] = $val['parent_name'];
              $ui_list[$key]['ui_name']     = $val['ui_name'];
              if(!$this->get_template_ui($val['parent_name'],$val['ui_name'])){
                    $ui_list[$key]['ui_version']  = -1;
              }else{
                    $ui_list[$key]['ui_version']  = $val['ui_version'];
              }
            }
            $res = $this->UI->checkUi($ui_list);
            $res['skin_name'] = $this->skin_name;
            if($res['status']){
                foreach ($res['msg'] as $k => $v) {
                    $ui = explode('/', $v);
                    $update_list[$k]['parent_name'] = $ui[0];
                    $update_list[$k]['ui_name'] = $ui[1];
                    $update_list[$k]['ui_version'] = $ui[2];
                }

                $cache['total'] = count($update_list);
                $cache['data'] = $update_list;
                file_put_contents(PATH_APP_FILE.'admin/ui_list', json_encode($cache));
            }
        }

        return $update_list;
    }

    public function dodownload_ui()
    {
        global $_M;
        $res = $this->UI->download_ui($_M['form']['parent_name'],$_M['form']['ui_name'],$_M['form']['ui_version']);
        $res['progress'] = 1;
        $cache = json_decode(file_get_contents(PATH_APP_FILE.'admin/ui_list'),true);
        if($cache){
            foreach ($cache['data'] as $key => $val) {
                if($_M['form']['parent_name'] == $val['parent_name'] && $_M['form']['ui_name'] == $val['ui_name']){
                    unset($cache['data'][$key]);
                }
            }
            file_put_contents(PATH_APP_FILE.'admin/ui_list', json_encode($cache));
            $res['progress'] = number_format((1-count($cache['data']) / $cache['total']),2)*100;
        }
        echo json_encode($res);die;
    }


    public function doinstall_tem()
    {
        global $_M;
        $this->download_tem();
    }

    public function doset_default()
    {
        global $_M;
        $has = $this->UI->get_skin();
        if(!$has){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_temnoexist']));die;
        }

        $query = "UPDATE {$_M['table']['config']} SET value = '{$this->skin_name}' WHERE lang = '{$_M['lang']}' AND name = 'met_skin_user'";
        $row = DB::query($query);
        if($row){
            // 当前语言的模板配置数据有没有存在
            $config = $this->table('ui_config')->where("skin_name = '{$this->skin_name}' AND lang = '{$_M['lang']}'")->find('id');

            if(!$config){
                $source = $this->table('ui_config')->where("lang != '{$_M['lang']}' AND skin_name = '{$this->skin_name}'")->get();

                foreach ($source as $key =>$val) {
                    unset($val['id'],$val['lang']);
                    $desc = $val;

                    $desc['lang'] = $_M['lang'];
                    $this->table('ui_config')->insert($desc);
                }
            }
            echo json_encode(array('status'=>1,'msg'=>$_M['word']['opsuccess']));die;
        }else{
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['opfailed'].DB::error()));die;
        }
    }

    public function doclear_zip()
    {
        global $_M;
        $status = load::mod_class('appstore/include/inapp', 'new')->get_appstore_status();
        if(!$status['state']){
            echo json_encode(array('status'=>-1,'msg'=>$_M['word']['please_again'],'url'=>$status['loginurl']));die;
        }
        deldir(PATH_APP_FILE.'admin/zip/',1);
        echo json_encode(array('status'=>1,'msg'=>$_M['word']['opsuccess']));die;
    }


    public function dodown_data($current=0)
    {
        global $_M;
        $status = load::mod_class('appstore/include/inapp', 'new')->get_appstore_status();
        if(!$status['state']){
            echo json_encode(array('status'=>-1,'msg'=>$_M['word']['please_again'],'url'=>$status['loginurl']));die;
        }
        $has = $this->UI->get_skin();
        if(!$has){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_temnoexist']));die;
        }

        $current = $_M['form']['current'] ? $_M['form']['current'] : $current;

        $response = $this->UI->down_data($current);
        if(!$response){
            self::dodown_data($current);
        }
        $res = json_decode($response,true);
        if(!file_exists(PATH_APP_FILE.'admin/zip')){
            mkdir(PATH_APP_FILE.'admin/zip',0777,true);
        }
        file_put_contents(PATH_APP_FILE.'admin/zip/'.$this->skin_name.'.zip', base64_decode($res['string']),FILE_APPEND);
        echo $response;die;
    }

    public function dounzip_data()
    {
        global $_M;
        $has = $this->UI->get_skin();
        if(!$has){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_temnoexist']));die;
        }

        $zippath = PATH_APP_FILE.'admin/zip/';
        $zipname = $zippath.$this->skin_name.'.zip';
        if(!file_exists($zipname))
        {
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_demonoexist']));die;
        }

        $zip = new ZipArchive;
        if ($zip->open($zipname) === TRUE) {
          $zip->extractTo($zippath);
          $zip->close();
        } else {
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_upzipdemofalse']));die;
        }

        echo json_encode(array('status'=>1,'msg'=>$_M['word']['met_template_upzipok']));die;
    }

    public function doimport_sql()
    {
        global $_M;
        $update_database = load::mod_class('update/update_database','new');

        $version = $_M['config']['metcms_v'];
        $has = $this->UI->get_skin();
        if(!$has){
            echo json_encode(array('status'=>0,'msg'=>$_M['word']['met_template_temnoexist']));die;
        }

        $zippath = PATH_APP_FILE.'admin/zip/';
        $sql = glob($zippath.'*.sql');
        if(!file_exists($sql[0])){
            $res = $this->UI->down_sql();
            $res = json_decode($res,true);
            file_put_contents(PATH_APP_FILE.'admin/zip/'.$this->skin_name.'.sql', base64_decode($res['string']));
        }

        $zippath = PATH_APP_FILE.'admin/zip/';
        $sql = glob($zippath.'*.sql');
        $string = file_get_contents($sql[0]);
        preg_match("/^#[^\r\n]+\//im", $string,$match);
        $site_url = trim($match[0],'#');
        $tablepre = $_M['config']['tablepre'];
        $string = str_replace($site_url,$_M['url']['site'] , $string);
        $string = str_replace(array('DROP TABLE IF EXISTS met_','CREATE TABLE `met_','INSERT INTO met_'), array("DROP TABLE IF EXISTS {$tablepre}","CREATE TABLE `{$tablepre}","INSERT INTO {$tablepre}"), $string);
        $string = preg_split("/;\r?\n/", $string);

        $update_database->temp_data();

        foreach ($string as $key => $query) {
            if($key == 0){
                continue;
            }
            if(trim($query)){
                if(strstr($query, $tablepre.'admin_table')){
                    continue;
                }

                if(strstr($query, $tablepre.'admin_column')){
                    continue;
                }
                DB::query($query);
            }
        }

        $query = "SELECT value FROM {$_M['table']['config']} WHERE name = 'metcms_v'";
        $c = DB::get_one($query);
        $show_version = $c['value'];
        if($show_version == '6.0.0'){
            $update_database->diff_fields($version);

            $update_database->update_language();
            $update_database->recovery_data();

            $update_database->insert_para();
            $update_database->update_plist();

            $update_database->update_shop();
        }

        deldir(PATH_WEB.'upload',1);
        $res = copydir($zippath.'upload/',PATH_WEB.'upload');
        deldir(PATH_WEB.'cache',1);
        echo json_encode(array('status'=>$res,'msg'=>$_M['word']['met_template_installok']));
    }
}
?>