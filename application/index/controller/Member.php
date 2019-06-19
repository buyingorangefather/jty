<?php
namespace app\index\controller;

use think\Controller;
use app\index\model\User;
use think\Cookie;
use think\Db;
use \app\index\model\Option;
use \app\index\model\Avatar;
use \PHPGangsta_GoogleAuthenticator;
use \app\index\model\TwoFactor;
use \app\index\common\Commonfun;

class Member extends Controller{

	public $userObj;


	public function index(){
		echo "Pong";
	}

	public function Register(){
		if(input('?post.username-reg') && input('?post.password-reg')){
			$regAction = User::register(input('post.username-reg'),input('post.password-reg'),input('post.captchaCode'),input("post.openid"));
			if ($regAction[0]){
				return json(['code' => '200','message' => $regAction[1]]);
			}else{
				return json(['code' => '1','message' => $regAction[1]]);
			}
		}else{
			return json(['code' => '1','message' => "信息不完整"]);
		}
	}

	public function ForgetPwd(){
		if(input('?post.regEmail')  && !empty(input('post.regEmail'))){
			$findAction = User::findPwd(input('post.regEmail'),input('post.captchaCode'));
			if ($findAction[0]){
				return json(['code' => '200','message' => $findAction[1]]);
			}else{
				return json(['code' => '1','message' => $findAction[1]]);
			}
		}else{
			return json(['code' => '1','message' => "信息不完整"]);
		}
	}


    public function Login(){
        if(input("param.platform_type")=="wechat"){
            $appid = config("wechat.appid");
            $redirect_uri = config('wechat.wechat_redirect_url');
			$wechat_ouath2_url = sprintf(config("wechat.wechat_oauth2_url"),$appid,$redirect_uri);
			return json(['url'=>$wechat_ouath2_url]);
        }
        else{
            if(input('?post.userMail') && input('?post.userPass')){
                $logAction = User::login(input('post.userMail'),input('post.userPass'),input('post.captchaCode'));
                if ($logAction[0]){
                    return json(['code' => '200','message' => '登陆成功']);
                }else{
                    return json(['code' => '1','message' => $logAction[1]]);
                }
            }else{
                return json(['code' => '1','message' => "信息不完整"]);
            }
        }
    }

	public function GetOpenId($code){
        $appid = config("wechat.appid");
        $secret = config('wechat.secret');
		$wechat_code2access_token_url = sprintf(config('wechat.wechat_code2access_token_url'),$appid,$secret,$code);
		$result = Commonfun::https_request($wechat_code2access_token_url);
		return $result;
    }
    /*
    *微信授权后的重定向页面(回调页面)
    */

	public function WcLoginCallBack(){
        $code = input('param.code');
        $openid_data = $this->GetOpenId($code);
        $openid_json = json_decode($openid_data,true);
        $openid = $openid_json["openid"];
        $userData =Db::name('users')->where('openid',$openid)->find();
        if(!$userData){       //如果这个openid没有记录或未绑定邮箱则跳转到注册页注册账号或绑定邮箱
            $this->redirect('SignUp/'.$openid);
        }
        else{
            $userEmail = $userData['user_email'];
            $userPass = $userData['user_pass'];
            $loginKey = md5($userEmail.$userPass.config('salt'));
            cookie('user_id',Db::name('users')->where('user_email',$userEmail)->value('id'));
            cookie('login_status','ok');
			cookie('login_key',$loginKey);
			$this->redirect('Home/');
        }
    }
	
	public function LogOut(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->userObj->clear();
		$this->redirect("/Login",302);

	}

	public function Memory(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->userObj->getMemory();
	}

	public function SignUp(){
		$openid = input('param.openid');
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->isLoginStatusCheck();
		return view('login', [
			'options'  => Option::getValues(['basic'],$this->userObj->userSQLData),
			'RegOptions'  => Option::getValues(['register','login']),
			'loginStatus' => $this->userObj->loginStatus,
			'pageId' => "register",
			'openid'=>$openid,
		]);
	}

	public function FindPwd(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->isLoginStatusCheck();
		return view('login', [
			'options'  => Option::getValues(['basic'],$this->userObj->userSQLData),
			'RegOptions'  => Option::getValues(['register','login']),
			'loginStatus' => $this->userObj->loginStatus,
			'pageId' => "resetPwd",
		]);
	}

	public function LoginForm(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->isLoginStatusCheck();
		return view('login', [
			'options'  => Option::getValues(['basic'],$this->userObj->userSQLData),
			'RegOptions'  => Option::getValues(['register','login']),
			'loginStatus' => $this->userObj->loginStatus,
			'pageId' => "login",
		]);
	} 

	public function TwoStepCheck(){
		$checkCode = input("post.code");
		if(empty($checkCode)){
			return json(['code' => '1','message' => "验证码不能为空"]);
		}
		$userId = session("user_id_tmp");
		$userData = Db::name('users')->where('id',$userId)->find();
		$ga = new PHPGangsta_GoogleAuthenticator();
		$checkResult = $ga->verifyCode($userData["two_step"], $checkCode, 2);
		if($checkResult) {
			cookie('user_id',session("user_id_tmp"));
			cookie('login_status',session("login_status_tmp"));
			cookie('login_key',session("login_key_tmp"));
			return json(['code' => '200','message' => '登陆成功']);
		}else{
			return json(['code' => '1','message' => "验证失败"]);
		}
	}

	public function TwoStep(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->isLoginStatusCheck();
		return view('login', [
			'options'  => Option::getValues(['basic'],$this->userObj->userSQLData),
			'RegOptions'  => Option::getValues(['register','login']),
			'loginStatus' => $this->userObj->loginStatus,
			'pageId' => "TwoStep",
		]);
	}

	public function setWebdavPwd(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->loginStatusCheck();
		Db::name("users")->where("id",$this->userObj->uid)
		->update([
			"webdav_key" => md5($this->userObj->userSQLData["user_email"].":CloudreveWebDav:".input("post.pwd")),
			]);
		return json(['error' => '200','msg' => '设置成功']);
	}

	public function emailActivate(){
		$activationKey = input('param.key');
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->isLoginStatusCheck();
		$basicOptions = Option::getValues(['basic','register','login'],$this->userObj->userSQLData);
		$activeAction = User::activicateUser($activationKey);
		if($activeAction[0]){
			return view('login', [
			'options'  => $basicOptions,
			'RegOptions'  => $basicOptions,
			'loginStatus' => $this->userObj->loginStatus,
			'pageId' => "emailActivate",
		]);
		}else{
			$this->error($activeAction[1],403,$basicOptions);
		}
	}

	public function resetPwd(){
		$resetKey = input('param.key');
		$userId = input('get.uid');
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$basicOptions = Option::getValues(['basic','register','login'],$this->userObj->userSQLData);
		$this->isLoginStatusCheck();
		$resetAction = User::resetUser($resetKey,$userId);
		if($resetAction[0]){
			return view('login', [
			'options'  => $basicOptions,
			'RegOptions'  => $basicOptions,
			'loginStatus' => $this->userObj->loginStatus,
			'key' => $resetKey."_".$userId,
			'pageId' => "resetPwdForm",
		]);
		}else{
			$this->error($resetAction[1],403,$basicOptions);
		}
	}

	public function Reset(){
		$newPwd = input('post.pwd');
		$resetKey = input('post.key');
		$resetAction = User::resetPwd($resetKey,$newPwd);
		if($resetAction[0]){
			return json(['code' => '200','message' => '重设成功，请前往登录页登录']);
		}else{
			return json(['code' => '1','message' => $resetAction[1]]);
		}
	}

	public function Setting(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$userInfo = $this->userObj->getInfo();
		$this->loginStatusCheck();
		$policyList=[];
		foreach (explode(",",$this->userObj->groupData["policy_list"]) as $key => $value) {
			$policyList[$key] = $value;
		}
		$avaliablePolicy = Db::name("policy")->where("id","in",$policyList)->select();
		$basicOptions = Option::getValues(['basic'],$this->userObj->userSQLData);
		return view('setting', [
			'options'  => $basicOptions,
			'userInfo' => $userInfo,
			'userSQL' => $this->userObj->userSQLData,
			'groupData' => $this->userObj->groupData,
			'loginStatus' => $this->userObj->loginStatus,
			'avaliablePolicy' => $avaliablePolicy,
		]);
	}

	public function SaveAvatar(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$file = request()->file("avatar");
		$avatarObj = new Avatar(true,$file);
		if(!$avatarObj->SaveAvatar()){
			return json_encode($avatarObj->errorMsg);
		}else{
			$avatarObj->bindUser($this->userObj->uid);
			return json_encode(["result" => "success"]);
		}
	}

	public function Avatar(){
		if(!input("get.cache")=="no"){
			header("Cache-Control: max-age=10800");
		}
		$userId = input("param.uid");
		$avatarObj = new Avatar(false,$userId);
		$avatarImg = $avatarObj->Out(input("param.size"));
		$this->redirect($avatarImg,302);
	}

	public function SetGravatar(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$avatarObj = new Avatar(false,$this->userObj->uid);
		$avatarObj->setGravatar();
	}

	public function Nick(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$userInfo = $this->userObj->getInfo();
		$this->loginStatusCheck();
		$saveAction = $this->userObj->changeNick(input("post.nick"));
		if($saveAction[0]){
			return json(['error' => '200','msg' => '设置成功']);
		}else{
			return json(['error' => '1','msg' => $saveAction[1]]);
		}
	}

	public function ChangeThemeColor(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$this->loginStatusCheck();
		$saveAction = $this->userObj->changeOption("preferTheme",input("post.theme"));
		if($saveAction[0]){
			return json(['error' => '200','msg' => '设置成功']);
		}else{
			return json(['error' => '1','msg' => $saveAction[1]]);
		}
	}

	public function HomePage(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$userInfo = $this->userObj->getInfo();
		$this->loginStatusCheck();
		$saveAction = $this->userObj->homePageToggle(input("post.status"));
		if($saveAction[0]){
			return json(['error' => '200','msg' => '设置成功']);
		}else{
			return json(['error' => '1','msg' => $saveAction[1]]);
		}
	}

	public function EnableTwoFactor(){
		$twoFactor = new TwoFactor();
		$twoFactor->qrcodeRender();
	}

	public function TwoFactorConfirm(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$userInfo = $this->userObj->getInfo();
		$this->loginStatusCheck();
		$twoFactor = new TwoFactor();
		$confirmResult = $twoFactor->confirmCode(session("two_factor_enable"),input("post.code"));
		if($confirmResult[0]){
			$twoFactor->bindUser($this->userObj->uid);
			return json(['error' => '200','msg' => '设置成功']);
		}else{
			return json(['error' => '1','msg' => $confirmResult[1]]);
		}
	}

	public function ChangePwd(){
		$this->userObj = new User(cookie('user_id'),cookie('login_key'));
		$userInfo = $this->userObj->getInfo();
		$this->loginStatusCheck();
		$changeAction = $this->userObj->changePwd(input("post.origin"),input("post.new"));
		if($changeAction[0]){
			return json(['error' => '200','msg' => '设置成功']);
		}else{
			return json(['error' => '1','msg' => $changeAction[1]]);
		}
	}

	private function loginStatusCheck($login=true){
		if(!$this->userObj->loginStatus){
			if($login){
				$this->redirect(url('/Login','',''));
			}else{
				$this->redirect(url('/Home','',''));
			}
			exit();
		}
	}

	private function isLoginStatusCheck(){
		if($this->userObj->loginStatus){
			$this->redirect(url('/Home','',''));
			exit();
		}
	}

}
