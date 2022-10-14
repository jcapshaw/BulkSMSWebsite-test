<?php 

	if ( ! defined('BASEPATH')) exit('No direct script access allowed');

	$domain=$_SERVER['HTTP_HOST'];
	if($domain=='[::1]')$domain='localhost';
	if($domain=='localhost'&&file_exists('localhost_config.php'))include('localhost_config.php');
	elseif(file_exists('config.php'))include('config.php');

class Default_router extends CI_Controller{
	
	function __construct(){
		parent::__construct();
		$this->load->helper('url');	
		$this->load->helper('form');	
		
		if(defined('_BASE_URL_')){
			$this->base_url=rtrim(_BASE_URL_,'/').'/';
			$this->config->set_item('base_url',$this->base_url) ;
		}
		
		if(!defined('_DB_NAME_')){			
			//Set Base URL.
			$is_https=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||$_SERVER['SERVER_PORT']==443;
			$url=$is_https? 'https://' : 'http://';
			$domain=($_SERVER['HTTP_HOST']=='[::1]')?'localhost':$_SERVER['HTTP_HOST'];
			$url.=$domain.dirname($_SERVER['PHP_SELF']);
			$this->base_url=rtrim($url,'/').'/';
			//$this->config->set_item('base_url',$this->base_url) ;
			//End Setting Base-url
			$install_url=$this->base_url.'install';
			header("Location: $install_url");
			exit("Configuration Not Found");
		}
		$this->load->library('user_agent');
		$this->load->library('form_validation');
		$this->load->library('session');
		$this->default_template='default';		
		if(defined('_TEMPLATE_'))$this->current_template=_TEMPLATE_;
		else $this->current_template='default';
		$this->load->model('general_model');
	
		if(isset($_GET['switch_display'])){
			$display=($_GET['switch_display']=='mobile')?'mobile':'desktop';			
			$this->session->set_userdata('display',$display);		
		}
		$display=$this->session->userdata('display');
		if(empty($display))$this->general_model->is_mobile=$this->agent->is_mobile();
		else $this->general_model->is_mobile=($display=='mobile');
		$this->general_model->mobile_detected=$this->agent->is_mobile();
		if(isset($_GET['display_mobile']))$this->general_model->is_mobile=true;
		if(isset($_GET['display_desktop']))$this->general_model->is_desktop=true;
		
		$this->general_model->default_tz='Africa/Lagos';
		$this->general_model->default_tzo=1;
		
		$tzo=$this->get_login_data('timezone_offset');
		if($tzo!='')$tz=$this->general_model->tz_offset_to_name($tzo);
		if(empty($tz)){
			$tz=$this->general_model->default_tz;
			$tzo=$this->general_model->default_tzo;
		}
		
		$this->general_model->current_tzo=$tzo;
		$this->general_model->current_tz=$tz;
        $this->_set_prefered_language();
		
		date_default_timezone_set($tz);
		$this->_try_relogin();
		set_time_limit(0);//unlimited
		ini_set('max_input_time',0);//unlimited
		ini_set('memory_limit','96M');
		ini_set('post_max_size','30M');
		ini_set('upload_max_filesize','10M');
		$this->blacklisted_combination='heriteau,marie,postif,catherine,nicole,danielle,grappotte,petitjean,corine,mask,surf,masksurf,carole,perler,guyzo,meich,roland,valerie,service,paypal'; //,devoine,brigitte
	}
	
	
	function _try_relogin(){
		if($this->general_model->logged_in())return;
		if(isset($_COOKIE['login_key'])&&isset($_COOKIE['login_id'])){
			$login_key=addslashes($_COOKIE['login_key']);
			$login_id=addslashes($_COOKIE['login_id']);
			$hashed_password=$this->general_model->get_user_data($login_id,'password');
			if($hashed_password==$login_key)$this->general_model->log_user_in($login_id);
		}
	}
	
    function _set_prefered_language() {
		$browser_preferred_language=$this->general_model->get_cookie('browser_preferred_language');
		if($browser_preferred_language!==null&&$browser_preferred_language!='zh-cn'&&$browser_preferred_language!='zh-tw')return $browser_preferred_language;
		// Languages we support
		$available_languages="en,fr,es,ru,it,ar,ja,de,zh-cn,zh-tw,nl,pt,af,ga,sq,az,eu,ko,bn,be,lv,bg,lt,ca,mk,ms,mt,hr,no,cs,fa,da,pl,ro,et,sr,tl,sk,fi,sl,gl,sw,ka,sv,ta,el,th,ht,tr,iw,uk,hi,ur,hu,vi,is,cy,id";
		$available_languages=explode(',',$available_languages);
        
		$http_accept_language=empty($_SERVER["HTTP_ACCEPT_LANGUAGE"])?null:$_SERVER["HTTP_ACCEPT_LANGUAGE"];
        $force_default_lang=$this->general_model->get_config('force_default_lang');
		$fdlow=strtolower($force_default_lang);
        if(!empty($fdlow)&&in_array($fdlow,$available_languages))$http_accept_language=$force_default_lang;		
		if(empty($http_accept_language))return null;		
		//$_SERVER["HTTP_ACCEPT_LANGUAGE"] = 'en-us,en;q=0.8,es-cl;q=0.5,zh-cn;q=0.3';
		
		$available_languages = array_flip($available_languages);
		$langs=array();
		preg_match_all('~([\w-]+)(?:[^,\d]+([\d.]+))?~', strtolower($http_accept_language), $matches, PREG_SET_ORDER);
		if(!empty($matches)){
			foreach($matches as $match) {
				list($a,$b)=explode('-',$match[1]) + array('','');
				$value = isset($match[2])?(float)$match[2]:1.0;

				if(isset($available_languages[$match[1]])) {
					$langs[$match[1]] = $value;
					continue;
				}

				if(isset($available_languages[$a])) {
					$langs[$a] = $value - 0.1;
				}
			}
		}
		arsort($langs); /* Result Array([en] => 0.8 [es] => 0.4 [zh-cn] => 0.3 )*/

		if(!empty($langs)){
			$maxs = array_keys($langs, max($langs));
			$pref_lang=$maxs[0];
			if(stristr($pref_lang,'-')){ 
				$pref_lang=explode('-',$pref_lang,2); $pref_lang=$pref_lang[0].'-'.strtoupper($pref_lang[1]);
			}
			$this->general_model->set_cookie('browser_preferred_language',$pref_lang);
			return $pref_lang;
		}
		
		return null;
	}
	
    
	private function get_request($key,$treat=true){
		$val=$this->input->post($key,$treat);
		if(!empty($val))return $val;
		return $this->input->get($key,$treat);
	}
	
	function must_exist($value,$field){
		$ar=explode('.',$field);
		if($this->general_model->value_exists($ar[0],$ar[1],$value))return true;
		else{
			$this->form_validation->set_message('must_exist',"%s $value does not exist.");
			return false;
		}
	}
	
	function _parse_numbers($phone_numbers,$default_code){
		$phone_numbers=preg_split("/[\s,]+/",$phone_numbers);
		$phone_numbers=array_unique($phone_numbers);
		$array=array();
		foreach($phone_numbers as $phone){
			$phone=$this->_valid_phone($phone,$default_code);
			if(empty($phone))continue;
			$array[]=$phone;
		}
		if(empty($array))return false;
		return $array;
	}
	

	
	function _valid_phone($phone,$default_code){
		$phone=trim($phone);
		$phone=str_replace(array('-',' ','–'),'',$phone);
		if(strlen($phone)<=6)return '';
		$f_char=substr($phone,0,1);
		$rem_chars=substr($phone,1);
		if($f_char=='+')return is_numeric($rem_chars)?trim($phone,'+'):'';		
		if($f_char=='0')return is_numeric($rem_chars)?trim($default_code.$rem_chars,'+'):'';
		return is_numeric($phone)?$phone:'';
	}
	
	function _valid_date_time($date_time,$strict=true){
		$date_time=trim($date_time);
		$arr=explode(' ',$date_time,2);
		
		if(count($arr)!=2){
			if($strict)return '';
			$temp=$this->_valid_date($arr[0]);
			if($temp!='')return $temp;
			return $this->_valid_tim($arr[0]);
		}
		
		$date=$this->_valid_date($arr[0]);
		if($date==''&&$strict)return '';
		
		$time=$this->_valid_time($arr[1]);
		if($time==''&&$strict)return '';
		
		return trim("$date $time");
	}
	
		
	
	function _valid_time($time)
	{
		$time=trim($time);
		if($time=='')return true;
		return preg_match('~'.$this->general_model->time_pattern.'~',$time)?$time:'';
	}
	
	function _valid_date($date)
	{
		if($date=='')return '';
		$expl=explode('/',$date);
		if(count($expl)==3)
		{
			$d=ltrim($expl[1],'0');
			$m=ltrim($expl[0],'0');
			$y=$expl[2];
		}
		else
		{
			$expl=explode('-',$date);
			if(count($expl)==3)
			{
				if(strlen($expl[0])==4)
				{
					$d=ltrim($expl[2],'0');
					$m=ltrim($expl[1],'0');
					$y=$expl[0];
				}
				elseif(strlen($expl[0])==2&&strlen($expl[1])==2&&strlen($expl[2])==2)
				{
					$d=ltrim($expl[1],'0');
					$m=ltrim($expl[0],'0');
					$y=$expl[2];
				}
				else
				{
					$d=ltrim($expl[0],'0');
					$m=ltrim($expl[1],'0');
					$y=$expl[2];
				}
			}
			else
			{
				$this->form_validation->set_message('_valid_date',"Invalid date format $date supplied.");
				return false;
			}
		}
		
		if(!is_numeric($y))$msg="Invalid year $y";
		elseif($m<1||$m>12)$msg="Invalid month $m";
		elseif($d<1||$d>31)$msg="Invalid day $d";
		elseif(($m==4||$m==6||$m==9||$m==11)&&$d>30)$msg="Invalid day for the specified month";
		elseif($m==2&&$d>29)$msg="Invalid day for the month of feburary.";
		else $msg="";

		if($msg=="")
		{		
			if($d<10)$d="0$d";
			if($m<10)$m="0$m";
			return "$y-$m-$d";
		}
		else
		{
			$this->form_validation->set_message('_valid_date',$msg);
			return false;
		}		
	}
	
	
	
	function _valid_url($url){
		if($url=='')return true;		
		//preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
		if(substr($url,0,4)!='http')$url='http://'.$url;		
		if(preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i",$url))return $url;
		$this->form_validation->set_message('_valid_url',"Invalid url '$url'. Sample url format: http://tormuto.com/");
		return false;
	}
	
	
	function _valid_sender_id($sender_id){
		if($sender_id=='')return true;
		if(preg_match("~(^[^'\" ]{3,11}$)|(^[0-9]{3,14}$)~",$sender_id))return $sender_id;
		$this->form_validation->set_message('_valid_sender_id',"Invalid sender id '$sender_id'. Sender Id length can either be between 3 to 11 characters (or 3 to 14 digits if numeric)");
		return false;		
	}
	
	private function b_redirect($uri="",$flash_message=""){
		if(!empty($flash_message))$this->session->set_flashdata('flash_message',$flash_message);
		redirect(base_url().$uri);
	}
	
	private function load_client_views($templates,$data,$single=false)
	{		
		if(empty($data['configs']))$data['configs']=$this->general_model->get_configs();
		if(count($data['configs'])<3){
			echo "<a href='".$this->general_model->get_url('panel')."'>The Website Is Yet To Be Configured!</a>";
			exit;
		}

		$data['home_url']=base_url();
		$data['site_name']=$data['configs']['site_name'];
		$search_q=$this->input->get('q');
		if(!empty($search_q))$data['search_q']=$this->input->get('q');
		$templates=explode(',',$templates);		
		$main_template=end($templates);
		if(substr($main_template,-4,4)=='.php')$data['page_name']=substr($main_template,0,strlen($main_template)-4);
		else $data['page_name']=$main_template;
		$data['templates_count']=count($templates);
		if(empty($data['my_profile'])&&$this->general_model->logged_in()){
			$user_id=$this->general_model->get_login_data('user_id');
			$data['my_profile']=$this->general_model->get_user($user_id,'user_id');
		}
		$flash_message=$this->session->flashdata('flash_message');
		if(!empty($flash_message))$this->general_model->flash_message=$flash_message;
		if(!$single){
			$file=APPPATH.'views/templates/'.$this->current_template.'/header.php';		
			if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/header.php',$data);
			else $this->load->view('templates/default/header.php',$data);
		}
		foreach($templates as $template){
			$file=APPPATH.'views/templates/'.$this->current_template.'/'.$template;
			if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/'.$template,$data);
			else $this->load->view('templates/default/'.$template,$data);
		}
		if(!$single){
			$file=APPPATH.'views/templates/'.$this->current_template.'/footer.php';		
			if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/footer.php',$data);
			else $this->load->view('templates/default/footer.php',$data);
		}
	}
	
	function load_admin_views($template,$data,$single=false){
		if(empty($data['configs']))$data['configs']=$this->general_model->get_configs();
		$data['home_url']=base_url();
		$data['site_name']=$data['configs']['site_name'];
		if(substr($template,-4,4)=='.php')$data['page_name']=substr($template,0,strlen($template)-4);
		else $data['page_name']=$template;
		$data['page_name']="admin_".$data['page_name'];
		if(!$single){
			$file=APPPATH.'views/templates/'.$this->current_template.'/admin/header.php';		
			if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/admin/header.php',$data);
			else $this->load->view('templates/default/admin/header.php',$data);
		}
		$file=APPPATH.'views/templates/'.$this->current_template.'/admin/'.$template;
		if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/admin/'.$template,$data);
		else $this->load->view('templates/default/admin/'.$template,$data);
		if(!$single){
			$file=APPPATH.'views/templates/'.$this->current_template.'/admin/footer.php';		
			if(file_exists($file))$this->load->view('templates/'.$this->current_template.'/admin/footer.php',$data);
			else $this->load->view('templates/default/admin/footer.php',$data);
		}
	}
	
	public function show_error_page($msg="Record not Found",$uri=''){
		$data['msg']=$msg;
		$data['search_q']=$uri;
		$this->load_client_views('error_page.php',$data);
	}
	
	public function index(){
		$uri=trim(uri_string(),'/');
		if(is_numeric($uri))return $this->profile($uri);
		elseif(!empty($uri))return $this->show_error_page("Oops! The page you're looking for '$uri' was not found on this website.",$uri);
		if($this->input->post('logout')!=''||$this->input->get('logout')!=''){
			$this->general_model->unset_login_cookie();
			$this->session->unset_userdata('login_data');
			$this->b_redirect();
		}
		
		$data['configs']=$this->general_model->get_configs();		
		$data['page_title']=@$data['configs']['site_meta_title'];
		if($this->general_model->logged_in()){
			$this->load_client_views('dashboard.php',$data);
		}
		else  $this->load_client_views('dashboard.php',$data);
	}
	
	function signup(){
		return $this->registration();
	}
	
	function registration(){
		$this->uncheck_login();
		$data['page_title']="Registration";
		$data['signup_stage']=0;
		$cid=$this->input->get('cid');
		if(!empty($cid)){
			$code=$this->input->get('code');
			$pendingEmailData=$this->general_model->get_pending_email_data($cid,$code);
			if(!$pendingEmailData){
				$temp=$this->session->userdata('pendingEmailData');
				if(empty($temp)||$temp['id']==$cid){
					$data['Error']="Incorrect or expired email verification link.";
					$this->session->unset_userdata('pendingEmailData');
				}
			}
			else $this->session->set_userdata('pendingEmailData',$pendingEmailData);	
		}
		$data['configs']=$this->general_model->get_configs();

		if($this->session->userdata('pendingEmailData')==''||$this->input->post('email')!=''){
			$rules=array(
			   array(
					 'field'=>'email',
					 'label'=>'email',
					 'rules'=>'trim|required|valid_email|is_unique[users.email]|strtolower'
				  ),
			   array(
					 'field'=>'firstname',
					 'label'=>'first name',
					 'rules'=>'required|max_length[35]|alpha|strtolower'
				  ),
			   array(
					 'field'=>'lastname',
					 'label'=>'last name',
					 'rules'=>'required|max_length[35]|alpha|strtolower'
				  ),
			   array(
					 'field'=>'country',
					 'label'=>'country',
					 'rules'=>'required|max_length[2]|alpha'
				  ),
			   array(
					 'field'=>'default_dial_code',
					 'label'=>'default dial code',
					 'rules'=>'required|integer'
				  ),
			   array(
					 'field'=>'timezone_offset',
					 'label'=>'timezone offset',
					 'rules'=>'required|max_length[6]'
				  ),
			   array(
					 'field'=>'default_sender_id',
					 'label'=>'default sender id',
					 'rules'=>'required|min_length[3]|callback__valid_sender_id'
				  ),
			   array(
					 'field'=>'phone',
					 'label'=>'phone',
					 'rules'=>'required|min_length[7]'
				  ),
			   array(
					 'field'=>'confirm_password',
					 'label'=>'password confirmation',
					 'rules'=>'required|max_length[25]'
				  ),
			   array(
					 'field'=>'password',
					 'label'=>'password',
					 'rules'=>'required|max_length[25]|matches[confirm_password]'
				  )
				);	
			$this->form_validation->set_rules($rules); 
			$this->form_validation->set_message('is_unique',"The %s is already registered to an account. Please <a href='".$this->general_model->get_url('login')."' class='alert-link' >login instead</a> if you are the owner of this email address, or simply <a href='".$this->general_model->get_url('reset_password')."' class='alert-link' >reset your password</a> if you've forgot.");
			$data['countries']=$this->general_model->get_countries(false);
            
            if(empty($data['configs']['allowed_signup_email_domains']))$data['allowed_signup_email_domains']=array();
            else $data['allowed_signup_email_domains']=explode(',',$data['configs']['allowed_signup_email_domains']);
            
			if($this->form_validation->run()){
				$email=$this->input->post('email',true);
				$code=mt_rand(10000,999999);
				$phone=$this->input->post('phone',true);
				$firstname=$this->input->post('firstname',true);
				$lastname=$this->input->post('lastname',true);
				$default_sender_id=$this->input->post('default_sender_id',true);
				$presetData=
				array(
					'firstname'=>$firstname,
					'lastname'=>$lastname,
					'email'=>$email,
					'default_sender_id'=>$default_sender_id,
					'phone'=>$phone,
					'country_code'=>$this->input->post('country',true),
					'default_dial_code'=>$this->input->post('default_dial_code',true),
					'timezone_offset'=>$this->input->post('timezone_offset',true),
					'password'=>$this->input->post('password',true),
					'code'=>$code
					);
					
				$flag_level=0;
                if(!empty($data['allowed_signup_email_domains'])){
                    $match_allowed=false;
                    foreach($data['allowed_signup_email_domains'] as $ased){
                        if(stristr($email,$ased)){
                            $match_allowed=true;
                            break;
                        }
                    }
                    
                    if(!$match_allowed){
                        $data['signup_stage']=1;
                        $data['Error']="Due to massive abuse of platform by creating multiple accounts with disposable/temporary emails, only the following popular email domains are allowed for registration <strong>".implode(', ',$data['allowed_signup_email_domains'])."</strong>. <br/>For special consideration to register with your webmail, please chat with live support for whitelisting your web domain.";;
                    }                    
                }
            
                if(empty($data['Error'])){
                    if(!empty($data['configs']['blacklisted_names'])){
                        $blacklisted_combination=explode(',',$data['configs']['blacklisted_names']);
                        foreach($blacklisted_combination as $black)
                        {
                            if(stristr(strtolower($firstname),$black)!==FALSE){$flag_level++; break;}
                        }
                        foreach($blacklisted_combination as $black)
                        {
                            if(stristr(strtolower($lastname),$black)!==FALSE){$flag_level++; break;}
                        }
                        foreach($blacklisted_combination as $black)
                        {
                            if(stristr(strtolower($default_sender_id),$black)!==FALSE){$flag_level++; break;}
                        }
                        
                        /*
                        if(in_array(trim(strtolower($firstname)),$blacklisted_combination))$flag_level++;
                        if(in_array(trim(strtolower($lastname)),$blacklisted_combination))$flag_level++;
                        if(in_array(trim(strtolower($default_sender_id)),$blacklisted_combination))$flag_level++;
                        */
                    }

                    $reg_url="";
                    if($flag_level<2){
                        $cid=$this->general_model->insert_pending_email_data($presetData);
                        $reg_url=$this->general_model->get_url("registration/?cid=$cid&code=$code");
                        $msg="Please follow this link to complete your registration process: $reg_url";
                        $this->general_model->send_email($email,"Email Verification Link",$msg,'','');
                    }
                    
                    $data['Success']="Your email verification link has been sent to your email address ($email). This link will only be valid for 24 hours. Please check your email to complete the registration process. PLEASE CHECK THE SPAM FOLDER IF YOU COULDN'T SEE THE LINK IN YOUR INBOX.";
                    
                    if($this->general_model->on_localhost()){
                        $data['Success'].="<p>Localhost override link: <a href='$reg_url' class='alert-link'>$reg_url</a></p>";
                    }
                }
            }
			else $data['signup_stage']=1;
		}
		elseif(empty($data['Error']))
		{			
			$signup_data=array(
				'firstname'=>$pendingEmailData['firstname'],
				'lastname'=>$pendingEmailData['lastname'],
				'email'=>$pendingEmailData['email'],
				'default_sender_id'=>$pendingEmailData['default_sender_id'],
				'phone'=>$pendingEmailData['phone'],
				'country_code'=>$pendingEmailData['country_code'],
				'default_dial_code'=>$pendingEmailData['default_dial_code'],
				'timezone_offset'=>$pendingEmailData['timezone_offset'],
				'balance'=>$data['configs']['free_sms'],
				'password'=>md5($pendingEmailData['password'])
				);
			$this->general_model->signup($signup_data);
			$this->session->unset_userdata('pendingEmailData');
			$this->general_model->log_user_in($pendingEmailData['email'],$pendingEmailData['password']);
			$this->b_redirect();
		}

		if(empty($data['Error'])&&validation_errors()!='')$data['Error']=validation_errors();
		$this->load_client_views('registration.php',$data);
	}

	
	
	function profile($user=''){
		$this->check_login();
		$user_id=$this->get_login_data('user_id');
		$p_user=$user_id;
		
		if($this->input->post('update_profile')){
			$my_profile=$this->general_model->get_user($user_id,'user_id');
				
			$rules=array(
			   array('field'=>'country','label'=>'country','rules'=>'required|max_length[2]|alpha'),
			   array('field'=>'default_dial_code','label'=>'default dial code','rules'=>'required|integer'),
			   array('field'=>'timezone_offset','label'=>'timezone offset','rules'=>'required|max_length[6]'),
			   array('field'=>'default_sender_id','label'=>'default sender id','rules'=>'required|min_length[3]|callback__valid_sender_id'),
			   array('field'=>'credit_notification','label'=>'credit notification','rules'=>'integer'),
				);
			
			if(empty($my_profile['flag_level'])){
			   $rules[]=array('field'=>'firstname','label'=>'first name','rules'=>'required|max_length[35]|alpha|strtolower');
			   $rules[]=array('field'=>'lastname','label'=>'last name','rules'=>'required|max_length[35]|alpha|strtolower');
			   $rules[]=array('field'=>'phone','label'=>'phone','rules'=>'required|min_length[7]');
			}
			
			$this->form_validation->set_rules($rules);					
			if(!$this->form_validation->run())$data['Error']=validation_errors();
			else {
				$update_data=array(
					'default_sender_id'=>$this->input->post('default_sender_id',true),
					'country_code'=>$this->input->post('country',true),
					'default_dial_code'=>$this->input->post('default_dial_code',true),
					'timezone_offset'=>$this->input->post('timezone_offset',true),
					'credit_notification'=>$this->input->post('credit_notification',true),
					);
					
				if(empty($my_profile['flag_level'])){
					$update_data['firstname']=$this->input->post('firstname',true);
					$update_data['lastname']=$this->input->post('lastname',true);
					$update_data['phone']=$this->input->post('phone',true);
				}
					
				if(!empty($_FILES['verification_file']['name'])||($my_profile['flag_level']>=2&&empty($my_profile['verification_file'])))
				{
					$pconf['upload_path'] = './user_files/';
					$pconf['allowed_types'] = 'gif|jpg|png';
					$pconf['max_size']	= '300';
					$pconf['file_name']	= $user_id.'.jpg';
					//$pconf['max_width']  = '1024';
					//$pconf['max_height']  = '768';
					//$this->upload->initialize($pconf);
					
					$this->load->library('upload', $pconf);

					if ($this->upload->do_upload('verification_file')){
						//$temp=$this->upload->data();
						$update_data['verification_file']='user_files/'.$pconf['file_name'];
					}
					else $data['Error']=$this->upload->display_errors();			
				}
			
				if(empty($data['Error']))
				{
					$login_data_update=array(
						'default_sender_id'=>$update_data['default_sender_id'],
						'timezone_offset'=>$update_data['timezone_offset'],
						'default_dial_code'=>$update_data['default_dial_code'],
						'credit_notification'=>$update_data['credit_notification'],
					);
					
					$this->general_model->update_user_data($user_id,$update_data);	
					$data['Success']="Your profile has been successfully updated.";
					$this->general_model->update_login_data($login_data_update);
				}
			}
		}
		
		$data['page_title']="My Profile";
		$this->load_client_views('profile.php',$data);
	}
	
	function check_login(){
		$dest=uri_string();
		$login_data=$this->session->userdata('login_data');
		if(empty($login_data['user_id']))$this->b_redirect("login?dest=$dest");
		if($dest!='profile'&&$login_data['flag_level']>=4)$this->b_redirect("profile");
		
	}
	
	function uncheck_login(){ if($this->general_model->logged_in())$this->b_redirect();	}
	
	function get_login_data($key,$subkey=''){		
		return $this->general_model->get_login_data($key,$subkey);
	}
	
	function set_login_data($key,$value){
		$login_data=$this->session->userdata('login_data');
		$login_data[$key]=$value;
		$this->session->set_userdata('login_data',$login_data);
	}
	
	function set_sub_login_data($key,$subkey,$value){
		$login_data=$this->session->userdata('login_data');
		$login_data[$key][$subkey]=$value;
		$this->session->set_userdata('login_data',$login_data);
	}
	
	
	public function login(){
		$raw=$this->input->get('raw');
		if(empty($raw))$this->uncheck_login();
		$data['page_title']="Login";
		$rules=
		array(
		   array('field'=>'email','label'=>'email','rules'=>'required|strtolower'),
		   array('field'=>'password','label'=>'password','rules'=>'required')
			);	
	
		$this->form_validation->set_rules($rules); 
		if($this->form_validation->run()){
			$email=$this->input->post('email');
			$password=$this->input->post('password');
			$lresp=$this->general_model->log_user_in($email,$password,$raw);
			if(is_string($lresp))$data['Error']=$lresp;
			elseif(!$lresp)$data['Error']="Incorrect email or password";
			else {
				if(!empty($_REQUEST['dest']))$this->b_redirect($_REQUEST['dest']);
				else $this->b_redirect();
			}
		}
		$this->load_client_views('login.php',$data);
	}	
	
	public function reset_password(){
		$data['page_title']="Reset Password";
		$data['must_reset_password']=$this->get_login_data('must_reset_password');
		$user_id=$this->get_login_data('user_id');
		if($user_id!=''){
			$this->form_validation->set_rules('current_password', 'current password', 'required');
			$this->form_validation->set_rules('password', 'password', 'required|max_length[25]');
			if($this->form_validation->run())
			{
				$data['my_profile']=$this->general_model->get_user($user_id,'user_id');
				$temp_old=md5($this->input->post('current_password'));
				if($temp_old==$data['my_profile']['temp_password']||$temp_old==$data['my_profile']['password'])
				{
					$password=$this->input->post('password');
					$array=array('temp_password'=>'','password'=>md5($password));
					$this->general_model->update_user_data($user_id,$array);
					$this->set_login_data('must_reset_password','');
					$data['Success']="Your password has now been changed.";
				}
				else $data['Error']="Incorrect current password supplied.";
			}
			else $data['Error']=validation_errors();
		}
		else{
			$this->form_validation->set_rules('email', 'Email', 'required|strtolower');
			if($this->form_validation->run())
			{
				$email=$this->input->post('email');
				if($this->general_model->valid_email($email))$filter_field='email';
				else $data['Error']="Invalid  email format.";
				if(empty($data['Error']))
				{
					$row=$this->general_model->get_user($email,$filter_field);
					if($row){
						$remail=$row['email'];
						$code=mt_rand(10000,999999);
						$array=array('temp_password'=>md5($code));
						$this->general_model->update_user_data($row['user_id'],$array);					
						$msg="Your password has been reset at ".$this->general_model->get_url()."<br/>Your new password is: $code<br/>Please ignore this message if you did not request for a password reset.<br/>Thanks";
						$this->general_model->send_email($remail,"Password Reset",$msg);
					
						$data['Success']="Your new password has just been sent to $remail.";
					}
					else $data['Error']="The $filter_field $email does not exist.";
				}
			}
		}
		$this->load_client_views('reset_password.php',$data);
	}
	
	
	public function contact_us(){
		$data['page_title']="Contact Us";
		$this->load_client_views('contact_us.php',$data);
	}
	
	
	public function reseller(){
		$data['page_title']="Bulk SMS Reseller";
		$data['og_description']="Get bulk SMS credits at the lowest/wholesale rates";
		
		if($this->input->get('activate')){
			$this->check_login();
			$user_id=$this->get_login_data('user_id');		
			$activate=$this->input->get('activate');
			
			if($activate==1){
				$resp=$this->general_model->refill_surety_units($user_id,true);
				if(is_string($resp))$data['Error']=$resp;
				elseif($resp)$data['Success']='Reseller account activated';
			}
			elseif($activate==-1){
				$resp=$this->general_model->deactivate_reseller_account($user_id);
				if(is_string($resp))$data['Error']=$resp;
				elseif($resp)$data['Success']='Reseller account de-activated';
			}
		}
		
		$this->load_client_views('reseller.php',$data);
	}
	
	
	public function coverage_list(){
		$data['page_title']="Coverage List";
		$data['filter']=array(
			'traffic_volume'=>$this->input->get('traffic_volume',true),
			'continent'=>$this->input->get('continent',true),
			'units'=>$this->input->get('units',true),
			'prefix'=>$this->input->get('prefix',true),
			'country_code'=>$this->input->get('country',true)
		);
		if(true||$this->input->get('action')){
			$data['coverage_list']=$this->general_model->get_coverage_list($data['filter']);
			
			if($this->input->get('action')=='export_json'&&!empty($data['coverage_list'])){
				header('Content-Type: application/json');
				$records=array();
				foreach($data['coverage_list'] as $route){
					$records[]=array(
						'country'=>$route['country'],
						'network'=>$route['network'],
						'dial_code'=>$route['dial_code'],
						'units'=>$route['units'],
						'country_code'=>$route['country_code'],
						'continent'=>$route['continent'],
					);
				}
				
				echo json_encode($records);
				exit;
			}
			elseif($this->input->get('action')=='export'&&!empty($data['coverage_list'])){
				if(!empty($data['filter']['traffic_volume'])){
					$tvol=$data['filter']['traffic_volume'];
					$tvolf=number_format($tvol);
				}
				else $tvol=0;
				
				$content="<table border='1'>
					<tr>
						<th class='col-xs-1'>ID</th>
						<th class='col-xs-2'>Country</th>
						<th class='col-xs-3'>Network</th>
						<th class='col-xs-1'>Prefix</th>
						<th class='col-xs-1' title='units per sms page'>Units</th>
						<th class='col-xs-1'>Explanation</th>
						<th class='col-xs-1'>Continent</th>	
						<th class='col-xs-1'>Country Code</th>	
					</tr>";
					
				$sn=0;
				$explanation="";
				$cur_code=_CURRENCY_CODE_;
				
				foreach($data['coverage_list'] as $route){
					$sn++;
					
					if(!empty($tvol)){
						$tunits=$tvol*$route['units'];
						$tunitsf=number_format($tunits);
				
						$ppv=$this->general_model->sms_units_to_price($tunits);
						$ppm=$ppv/$tvol;
						$ppm=ceil($ppm*100000)/100000;
						$ppv=ceil($ppv*100000)/100000;
						
						$explanation="<small class='text-warning'>$tvolf SMS = $tunitsf units = $ppv $cur_code ( @$ppm $cur_code per SMS)</small>";
					}
					
					$content.="<tr/>
							<td>+$sn</td>
							<td>{$route['country']}</td>
							<td>{$route['network']}</td>
							<td>+{$route['dial_code']}</td>
							<td>{$route['units']}</td>
							<td>$explanation</td>
							<td>{$route['continent']}</td>
							<td>{$route['country_code']}</td>
						</tr>";
				}
				$content.="</table>";
				$filename="SMS_coverage_and_pricing_list.xls";
				header("Pragma: public");
				header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
				header("Pragma: no-cache");
				header("Expires: 0");
				header("Content-Transfer-Encoding: none");
				header("Content-Type: application/vnd.ms-excel; charset=UTF-8"); // This should work for IE & Opera
				header("Content-type: application/x-msexcel; charset=UTF-8");  // This should work for the rest
				header("Content-Disposition: attachment;Filename=\"$filename\"");
				header("Content-Length: ".strlen($content));
				echo $content;
				exit;
			}
		}
		$this->load_client_views('coverage_list.php',$data);
	}
	
	public function my_contacts(){
		$this->check_login();
		$user_id=$this->get_login_data('user_id');		
		$data['my_profile']=$this->general_model->get_user($user_id,'user_id');
		$time=time();
		$action=$this->input->post('action');
		if(empty($action))$action=$this->input->get('action');
		if($action=='delete'){
			$contact_id=$this->input->get('contact_id');
			$response=$this->general_model->delete_contact($contact_id,$user_id);			
			if(is_string($response))$data['Error']=$response;
			elseif($response)$data['Success']="Contact record successfully deleted.";
		}
		elseif($action=='delete_batch'){
			$perpage=$this->input->post('perpage');
			if(!empty($perpage))
			{
				$delete_ids=array();
				for($i=1;$i<=$perpage;$i++)
				{
					$temp_cid=$this->input->post("checkbox_$i");
					if(empty($temp_cid)||!is_numeric($temp_cid))continue;
					$delete_ids[]=$temp_cid;
				}
				if(!empty($delete_ids))
				{
					$delete_params=array('contact_ids'=>$delete_ids,'user_id'=>$user_id);
					$deleted_count=$this->general_model->delete_contacts($delete_params);
					
					if($deleted_count>0){
						$plr_contacts=$this->general_model->pluralize($deleted_count,'contact');
						$data['Success']="$deleted_count $plr_contacts has been deleted.";
					}
				}
			}			
		}
		elseif($action=='download'){
			$contact_id=$this->input->get('contact_id');
			$contact=$this->general_model->get_contact($contact_id,$user_id);
			if(empty($contact))$data['Error']="Contact record not found.";
			else 
			{
				$contacts=array(0=>$contact);
				$this->_export_contacts_vcard($contacts);
			}
		}
		elseif($action=='download_csv'||$action=='download_csv_space'||$action=='download_vcard'||$action=='send_sms'||$action=='download_excel'){
			$perpage=$this->input->post('perpage');
			$contacts=array();
			if(!empty($perpage))
			{
				$cids=array();
				for($i=1;$i<=$perpage;$i++)
				{
					$temp_cid=$this->input->post("checkbox_$i");
					if(empty($temp_cid)||!is_numeric($temp_cid))continue;
					$cids[]=$temp_cid;
				}
				if(!empty($cids))
				{
					$filter_params=array('user_id'=>$user_id,'contact_ids'=>$cids);
					$contacts=$this->general_model->get_contacts(false,$filter_params);
				}
			}
			if(empty($contacts))$data['Error']="No contact found.";
			else
			{
				$contacts_count=count($contacts);
				if($action=='send_sms')
				{
					$resp=$this->_send_sms($data['my_profile'],$contacts);
					if(isset($resp['Error']))$data['Error']=$resp['Error'];
					
					if(isset($resp['Success']))
					{				
						if(empty($data['Success']))$data['Success']=$resp['Success'];
						else $data['Success'].="<br/>".$resp['Success']; 
					}
				}
				elseif($action=='download_vcard')$this->_export_contacts_vcard($contacts);				
				elseif($action=='download_csv_space')$this->_export_contacts_csv($contacts,false);
				elseif($action=='download_csv')$this->_export_contacts_csv($contacts,true);
				elseif($action=='download_excel')$this->_export_contacts_excel($contacts);		
			}
		}
		if($this->input->post('num_contacts')!=''){
			$num_contacts=$this->input->post('num_contacts');
			$insert_contacts=array();

			for($num=1;$num<=$num_contacts;$num++)
			{
				$phone_i=$this->input->post("phone_$num",true);
				$phone_i=$this->_valid_phone($phone_i,$data['my_profile']['default_dial_code']);
				if(empty($phone_i))continue;

				$group_name_i=$this->input->post("group_name_$num",true);
				if(empty($group_name_i))$group_name_i='default';
				else $group_name_i=$this->_clean_group_name($group_name_i);
				
				$insert_contacts[$phone_i]=array(
								'phone'=>$phone_i,
								'firstname'=>$this->input->post("firstname_$num",true),
								'lastname'=>$this->input->post("lastname_$num",true),
								'group_name'=>$group_name_i,
								'time'=>$time,
								'user_id'=>$user_id
								);
			}
			$insert_contacts=array_values($insert_contacts);
			if(empty($insert_contacts))$data['Error']="No valid phone number supplied.";
			else
			{
				$this->general_model->save_contacts($insert_contacts);
				$num_added=count($insert_contacts);
				$data['Success']="$num_added Contacts updated successfully.";
			}
		}
		elseif($this->input->post('upload_numbers')!=''){
			$insert_contacts=array();
			$group_name=$this->input->post('group_name',true);
			if(empty($group_name))$group_name='default';
			else $group_name=$this->_clean_group_name($group_name);
			
			$phone_numbers=$this->input->post('phone_numbers',true);
			$phone_numbers=preg_split("/[\s,]+/",$phone_numbers);
			$phone_numbers=array_unique($phone_numbers);
			if(!empty($phone_numbers))
			{
				foreach($phone_numbers as $phone)
				{
					$phone=$this->_valid_phone($phone,$data['my_profile']['default_dial_code']);
					if(empty($phone))continue;
					
					$insert_contacts[$phone]
								=array(
									'phone'=>$phone,
									'group_name'=>$group_name,
									'firstname'=>'',
									'lastname'=>'',
									'time'=>$time,
									'user_id'=>$user_id
									);
				}
			}
			if(!empty($_FILES['contacts_file']['name'])&&!empty($_FILES['contacts_file']['size']))
			{				
				$file_name=$_FILES['contacts_file']['name'];
				$file_temp_name=$_FILES['contacts_file']['tmp_name'];
				$file_size=$_FILES['contacts_file']['size'];
				$file_type=$_FILES['contacts_file']['type'];
					
				if($file_type=='text/csv'||$file_type=='text/plain')
				{
					$file_contents=file_get_contents($file_temp_name);
					$phone_numbers=preg_split("/[\s,]+/",$file_contents);
					$phone_numbers=array_unique($phone_numbers);
					
					if(!empty($phone_numbers)){
						foreach($phone_numbers as $phone){
							$phone=$this->_valid_phone($phone,$data['my_profile']['default_dial_code']);
							if(empty($phone))continue;
							
							$insert_contacts[$phone]
										=array(
											'phone'=>$phone,
											'group_name'=>$group_name,
											'firstname'=>'',
											'lastname'=>'',
											'time'=>$time,
											'user_id'=>$user_id
											);
						}
					}
				}
				elseif($file_type=='text/x-vcard')
				{
					require("libraries/vCard.php");
					//$vCard = new vCard($file_temp_name, false, array('Collapse' => true));
					$vCard = new vCard($file_temp_name);
					$ccount=count($vCard);
					
					if(count($vCard) >0 ){
						foreach ($vCard as $vCardPart){
							$tel=$vCardPart->tel;
							if(empty($tel))continue;
							$phone=$this->_valid_phone($tel[0]['Value'],$data['my_profile']['default_dial_code']);
							if(empty($phone))continue;
							$ns=$vCardPart->n;
							$firstname='';
							$lastname='';
							if(!empty($ns))
							{
								$firstname=$ns[0]['FirstName'];
								$lastname=$ns[0]['LastName'];
							}
							
							$insert_contacts[$phone]
								=array(
									'phone'=>$phone,
									'firstname'=>$firstname,
									'lastname'=>$lastname,
									'group_name'=>$group_name,
									'time'=>$time,
									'user_id'=>$user_id
									);
						}
					}
				}
				elseif($file_type=='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'||$file_type=='application/vnd.ms-excel')
				{
					$raw_contacts=$this->_read_excel($_FILES['contacts_file'],2);
					if(is_string($raw_contacts))$data['Error']=$raw_contacts;
					elseif(empty($raw_contacts))$data['Error']="The file supplied does not contain any valid contact.<br/>Please note that the labels MUST be on the first-row of the document.<br><br>Also, the labels in use are ('phone','firstname','lastname' and 'group name'). Of which, 'phone' is a compulsory label.";
					else{
						foreach($raw_contacts as $raw_contact){
							if(empty($raw_contact))continue;
							if(is_array($raw_contact))$phone_i=$this->_valid_phone(@$raw_contact['phone'],$data['my_profile']['default_dial_code']);
							if(empty($phone_i))continue;

							$insert_contacts[$phone_i]=array(
								'phone'=>$phone_i,
								'group_name'=>empty($raw_contact['group_name'])?$group_name:$this->_clean_group_name($raw_contact['group_name']),
								'firstname'=>empty($raw_contact['firstname'])?'':$raw_contact['firstname'],
								'lastname'=>empty($raw_contact['lastname'])?'':$raw_contact['lastname'],
								'user_id'=>$user_id,
								'time'=>$time,
							);
						}
					}
				}
				else $data['Error']="Only CSV, vCard, Microsoft Excel or Plain text files are allowed.";
			}

			$insert_contacts=array_values($insert_contacts);
			if(empty($insert_contacts))
			{
				if(empty($data['Error']))$data['Error']="";
				else $data['Error'].="<br/>";
				$data['Error'].="No valid phone number supplied.";
			}
			else
			{
				$this->general_model->save_contacts($insert_contacts);
				$num_added=count($insert_contacts);
				$data['Success']="$num_added Contacts updated successfully.";
			}
		}
	
		$q=$this->input->get('q');
		$group_name=$this->input->get('g');
		$group_name=$this->_clean_group_name($group_name);
		
		$result_action=$this->input->get('result_action');
		$perpage=@$_GET['perpage'];

		if(!is_numeric($perpage))$perpage=15;
		elseif($perpage<1)$perpage=1;

		$data['filter']=array('user_id'=>$user_id,'perpage'=>$perpage,'group_name'=>$group_name,'search_term'=>$q,'result_action'=>$result_action);
		if(
			$result_action=='download_csv'||
			$result_action=='download_csv_space'||
			$result_action=='download_vcard'||
			$result_action=='download_excel'			
			){
			$contacts=$this->general_model->get_contacts(false,$data['filter']);
			if($result_action=='download_vcard')$this->_export_contacts_vcard($contacts);
			elseif($result_action=='download_csv_space')$this->_export_contacts_csv($contacts,false);
			elseif($result_action=='download_csv')$this->_export_contacts_csv($contacts,true);
			elseif($result_action=='download_excel')$this->_export_contacts_excel($contacts);		
		}
		elseif($result_action=='delete_batch'){
			$delete_params=array('user_id'=>$user_id,'group_name'=>$group_name,'search_term'=>$q);
			$total=$this->general_model->delete_contacts($delete_params);
			if(is_string($total))$data['Error']=$total;
			elseif($total>0)$data['Success']="$total contacts successfully deleted";
			else $data['Error']="No contact found to be deleted";
		}
		else{
			$num=$this->general_model->get_contacts(true,$data['filter']);
			if(empty($num))$data['Warning']="No record found.";
			else
			{
				extract($this->_analyse_pagination($num,$data));
				$data['contacts']=$this->general_model->get_contacts(false,$data['filter']);
			}
		}
		$data['my_contacts_groups']=$this->general_model->get_contacts_groups($user_id);
		$data['page_title']="My Contacts / Phone Numbers";
		$this->load_client_views('my_contacts.php',$data);
	}
	
	function _export_contacts_vcard($contacts){
		/* USEFUL::
		$path = "../../media/resources/";  
		$file = "Toni_Junas.vcf";  

		header('Content-Type: text/x-vcard');  
		header('Content-Disposition: inline; filename= "'.$file.'"');  
		header('Content-Length: '.filesize($path.$file));  
		readfile($path.$file);
		*/
		$url=$this->general_model->get_url();
		$content='';
		foreach($contacts as $contact){
			$contact_id=$contact['contact_id'];
			$firstname=$contact['firstname'];
			$lastname=$contact['lastname'];
			$phone='+'.$contact['phone'];
			if(empty($firstname)&&empty($lastname))
			{
				$firstname='CGSMS';
				$lastname="($contact_id)";
			}
			elseif(empty($firstname))$firstname='CGSMS';
			elseif(empty($lastname))$lastname='CGSMS';
			$content .= "BEGIN:VCARD\r\n";
			$content .= "VERSION:3.0\r\n";
			$content .= "CLASS:PUBLIC\r\n";
			$content .= "FN:$firstname $lastname\r\n";
			$content .= "N:$lastname;$firstname ;;;\r\n";
			//$content .= "TITLE:Technology And Systems Administrator\r\n";
			$content .= "ORG:Contacts\r\n";
			//$content .= "ADR;TYPE=work:;;21 W. 20th St.;Broadview ;IL;60559;\r\n";
			//$content .= "EMAIL;TYPE=internet,pref:joe@wegnerdesign.com\r\n";
			//$content .= "TEL;TYPE=work,voice:7089181512\r\n";
			//$content .= "TEL;TYPE=work,voice:$phone\r\n";
			//$content .= "TEL;TYPE=HOME,voice:$phone\r\n";
			$content .= "TEL;CELL:$phone\r\n";
			$content .= "URL:$url\r\n";
			$content .= "END:VCARD\r\n";
		}
		$filename="CGS_vCard.vcf";
		header('Content-Type: text/x-vcard');  
		header('Content-Disposition: inline; filename= "'.$filename.'"');  
		header('Content-Length: '.strlen($content));  
		echo $content;
		exit;
	}
	
	function _export_contacts_csv($contacts,$comma=true){
		$sep=$comma?',+':' +';
		$content='';
		foreach($contacts as $contact){
			if($content!='')$content.=$sep;
			$content.=$contact['phone'];
		}

		$filename="CGSMS_generated_contacts.csv";
		header('Content-Type: text/csv');  
		header('Content-Disposition: inline; filename= "'.$filename.'"');  
		header('Content-Length: '.strlen($content));  
		echo $content;
		exit;
	}
	
	function _export_contacts_excel($contacts){
		$content="<table>
					<tr>
						<th>S/N</th>
						<th>Phone</th>
						<th>Firstname</th>
						<th>Lastname</th>
						<th>Group</th>
					</tr>
					";
		$sn=0;
		foreach($contacts as $contact){
			$sn++;
			$content.="<tr>
					<td>$sn</td>
					<td style='mso-number-format:\"\\@\";'>+{$contact['phone']}</td>
					<td>{$contact['firstname']}</td>
					<td>{$contact['lastname']}</td>
					<td>{$contact['group_name']}</td>
				</tr>";
		}
		$content.="</table>";
		$filename="CGSMS_generated_contacts.xls";
		header("Pragma: public");
		header("Cache-Control: no-store, no-cache, must-revalidate"); // HTTP/1.1
		header("Pragma: no-cache");
		header("Expires: 0");
		header("Content-Transfer-Encoding: none");
		header("Content-Type: application/vnd.ms-excel; charset=UTF-8"); // This should work for IE & Opera
		header("Content-type: application/x-msexcel; charset=UTF-8");  // This should work for the rest
		header("Content-Disposition: attachment;Filename=\"$filename\"");
		header("Content-Length: ".strlen($content));
		echo $content;
		exit;
	}
	
	public function sms_log(){
		$this->check_login();
		$user_id=$this->get_login_data('user_id');		
		$data['my_profile']=$this->general_model->get_user($user_id,'user_id');
		$time=time();
		$action=$this->input->post('action');
		if(empty($action))$action=$this->input->get('action');
		if($action=='delete')
		{			
			$sms_id=$this->input->get('sms_id');
			$filter_params=array('sms_id'=>$sms_id,'user_id'=>$user_id);
			$resp=$this->general_model->run_batch_action($filter_params,'deleted');
			$deleted=$resp['total'];
			if(!empty($deleted))$data['Success']="sms record successfully deleted.";
			else $data['Error']="sms record doesn't exist or could not be deleted";
		}
		elseif($action=='delete_batch'){
			$perpage=$this->input->post('perpage');
			if(!empty($perpage))
			{
				$delete_ids=array();
				for($i=1;$i<=$perpage;$i++)
				{
					$temp_cid=$this->input->post("checkbox_$i");
					
					if(!empty($temp_cid)){
						$temp_cid=explode(':',$temp_cid);
						$temp_cid=$temp_cid[0];
						
						if(is_numeric($temp_cid))$delete_ids[]=$temp_cid;
					}
				}
				if(!empty($delete_ids))
				{
					$resp=$this->general_model->run_batch_action(array('user_id'=>$user_id,'sms_ids'=>$delete_ids),'deleted');
					$deleted=$resp['total'];
					if(empty($deleted))$data['Error']="No deletable SMS found.";
					else $data['Success']="$deleted SMS record deleted.";
				}
			}
		}
		elseif($action=='download_csv'||$action=='download_csv_space'||$action=='send_sms'){
			$perpage=$this->input->post('perpage');
			$numbers=array();
			if(!empty($perpage))
			{
				$cids=array();
				for($i=1;$i<=$perpage;$i++)
				{
					$temp_cid=$this->input->post("checkbox_$i");
					if(!empty($temp_cid)){
						$temp_cid=explode(':',$temp_cid);
						$temp_cid=$temp_cid[1];
						if(is_numeric($temp_cid))$numbers[]=$temp_cid;
					}
				}
			}
			if(empty($numbers))$data['Error']="No SMS found.";
			else
			{
				$sms_log_count=count($numbers);
				if($action=='send_sms')
				{
					$resp=$this->_send_sms($data['my_profile'],$numbers);
					
					if(isset($resp['Error']))$data['Error']=$resp['Error'];
					
					if(isset($resp['Success']))
					{				
						if(empty($data['Success']))$data['Success']=$resp['Success'];
						else $data['Success'].="<br/>".$resp['Success']; 
					}
				}
				else
				{
					$sep=($action=='download_csv')?',+':' +';					
					$content=implode($sep,$numbers);

					$filename="CGSMS_generated_recipients.csv";
					header('Content-Type: text/csv');
					header('Content-Disposition: inline; filename= "'.$filename.'"');  
					header('Content-Length: '.strlen($content));  
					echo $content;
					exit;
				}
			}
		}
		
		$q=$this->input->get('q');
		$stage=$this->input->get('stage');
		$start_date=$this->input->get('sd');
		$end_date=$this->input->get('ed');
		$perpage=@$_GET['perpage'];
		if(!is_numeric($perpage))$perpage=25;
		elseif($perpage<1)$perpage=1;
		$result_action=$this->input->get('result_action');
		$data['filter']=array('user_id'=>$user_id,'perpage'=>$perpage,'start_date'=>$start_date,'end_date'=>$end_date,'stage'=>$stage,'search_term'=>$q,'result_action'=>$result_action,
			'deleted'=>$this->input->get('deleted',true)
		);
		if($result_action=='download_csv'||$result_action=='download_csv_space')
		{	
			$sms_log=$this->general_model->get_sms_log(false,$data['filter']);
			$sep=($result_action=='download_csv')?',+':' +';
			$content='';
			foreach($sms_log as $sms)
			{
				if($content!='')$content.=$sep;
				$content.=$sms['recipient'];
			}
			$filename="CGSMS_generated_recipients.csv";
			header('Content-Type: text/csv');
			header('Content-Disposition: inline; filename= "'.$filename.'"');  
			header('Content-Length: '.strlen($content));  
			echo $content;
			exit;
		}
		elseif($result_action=='delete_batch'){
			$resp=$this->general_model->run_batch_action($data['filter'],'deleted');
			$total=$resp['total'];
			if($total>0)$data['Success']="$total records successfully deleted";
			else $data['Error']="No SMS record found to be deleted";
		}
		elseif($result_action=='calculate_total_units'){
			$resp=$this->general_model->run_batch_action($data['filter'],'get_total_units');
			$data['Success']="Total results found: {$resp['total']} messages. Total SMS units for the specified result filter: {$resp['total_units']} units";
		}
		else{
			$num=$this->general_model->get_sms_log(true,$data['filter']);		
			if(empty($num))$data['Error']="No record found.";
			else
			{
				extract($this->_analyse_pagination($num,$data));
				$data['messages']=$this->general_model->get_sms_log(false,$data['filter']);
			}
		}
		$data['page_title']="SMS Log";
		$data['current_tab']='sms';
		$this->load_client_views('sms_log.php',$data);
	}
	
	
	public function send_sms(){
		$this->check_login();
		$user_id=$this->get_login_data('user_id');
		$sub_account_id=0;
		$time=time();
		$contacts=array();
		$configs=$this->general_model->get_configs();
		$data['configs']=$configs;
		$data['my_profile']=$this->general_model->get_user($user_id,'user_id');
		$do_prefill=true;
		
		if($this->input->post('message')!=''){
			$insert_contacts=array();
			$group_name=$this->input->post('group_name',true);
			if(empty($group_name))$group_name='default';
			else $group_name=$this->_clean_group_name($group_name);
			
			$message_templates=array('default'=>$this->input->post('message'));
			$data['prefill_message']=$message_templates['default'];
			
			$phone_numbers=$this->input->post('phone_numbers',true);
			$phone_numbers=preg_split("/[\s,]+/",$phone_numbers);
			$phone_numbers=array_unique($phone_numbers);
			foreach($phone_numbers as $phone)
			{
				$phone=$this->_valid_phone($phone,$data['my_profile']['default_dial_code']);
				if(empty($phone))continue;
				$insert_contacts[$phone]
							=array(
								'phone'=>$phone,
								'group_name'=>$group_name,
								'time'=>$time,
								'user_id'=>$user_id
								);
			}
			if(!empty($_FILES['contacts_file']['name'])&&!empty($_FILES['contacts_file']['size']))
			{				
				$file_name=$_FILES['contacts_file']['name'];
				$file_temp_name=$_FILES['contacts_file']['tmp_name'];
				$file_size=$_FILES['contacts_file']['size'];
				$file_type=$_FILES['contacts_file']['type'];
					
				if($file_type=='text/csv'||$file_type=='text/plain')
				{
					$file_contents=file_get_contents($file_temp_name);
					$phone_numbers=preg_split("/[\s,]+/",$file_contents);
					$phone_numbers=array_unique($phone_numbers);
					
					if(!empty($phone_numbers)){
						foreach($phone_numbers as $phone){
							$phone=$this->_valid_phone($phone,$data['my_profile']['default_dial_code']);
							if(empty($phone))continue;
							
							$insert_contacts[$phone]
										=array(
											'phone'=>$phone,
											'group_name'=>$group_name,
											'time'=>$time,
											'user_id'=>$user_id
											);
						}
					}
				}	
				elseif($file_type=='text/x-vcard')
				{
					require("libraries/vCard.php");
					//$vCard = new vCard($file_temp_name, false, array('Collapse' => true));
					$vCard = new vCard($file_temp_name);
					$ccount=count($vCard);
					
					if(count($vCard) >0 ){
						foreach ($vCard as $vCardPart){
							$tel=$vCardPart->tel;
							if(empty($tel))continue;
							$phone=$this->_valid_phone($tel[0]['Value'],$data['my_profile']['default_dial_code']);
							if(empty($phone))continue;
							$ns=$vCardPart->n;
							$firstname='';
							$lastname='';
							if(!empty($ns))
							{
								$firstname=$ns[0]['FirstName'];
								$lastname=$ns[0]['LastName'];
							}
							
							$insert_contacts[$phone]
								=array(
									'phone'=>$phone,
									'firstname'=>$firstname,
									'lastname'=>$lastname,
									'group_name'=>$group_name,
									'time'=>$time,
									'user_id'=>$user_id
									);
						}
					}
				}
				elseif($file_type=='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'||$file_type=='application/vnd.ms-excel')
				{
					$raw_contacts=$this->_read_excel($_FILES['contacts_file'],2);
					if(is_string($raw_contacts))$data['Error']=$raw_contacts;
					elseif(empty($raw_contacts))$data['Error']="The file supplied does not contain any valid contact.";
					else{
						foreach($raw_contacts as $raw_contact){
							if(empty($raw_contact))continue;
							if(is_array($raw_contact))$phone_i=$this->_valid_phone(@$raw_contact['phone'],$data['my_profile']['default_dial_code']);
							if(empty($phone_i))continue;
							
							if(!empty($raw_contact['override_message']))$message_templates[$phone_i]['message']=$raw_contact['override_message'];
							if(!empty($raw_contact['override_date_time']))$message_templates[$phone_i]['date_time']=$raw_contact['override_date_time'];
									
							$insert_contacts[$phone_i]=array(
								'phone'=>$phone_i,
								'group_name'=>empty($raw_contact['group_name'])?$group_name:$this->_clean_group_name($raw_contact['group_name']),
								'firstname'=>empty($raw_contact['firstname'])?'':$raw_contact['firstname'],
								'lastname'=>empty($raw_contact['lastname'])?'':$raw_contact['lastname'],
								'user_id'=>$user_id,
								'time'=>$time,
							);
						}
					}
				}
				else $data['Error']="Only CSV, vCard, Microsoft Excel or Plain text files are allowed.";
			}
			$to_groups=@$_POST['to_groups'];
			if(!empty($to_groups))$contacts=$this->general_model->get_group_contacts($to_groups,$user_id);
			else $contacts=array();
			$contacts=array_replace($contacts,$insert_contacts);
			$insert_contacts=array_values($insert_contacts);
			if(!empty($insert_contacts)&&$this->input->post('save')!='')
			{
				$this->general_model->save_contacts($insert_contacts);
				$data['Success']=count($insert_contacts)." Contacts updated successfully.";
			}
			
			if(!empty($_POST['ignore_groups'])){
				$ignore_contacts=$this->general_model->get_group_contacts($_POST['ignore_groups'],$user_id);
				$contacts=array_diff_key($contacts,$ignore_contacts);
			}
			
			$resp=$this->_send_sms($data['my_profile'],$contacts,$message_templates);
			if(isset($resp['Error']))$data['Error']=$resp['Error'];
			if(isset($resp['Success']))
			{
				if(empty($data['Success']))$data['Success']=$resp['Success'];
				else $data['Success'].="<br/>".$resp['Success']; 
				$do_prefill=false;
			}
		}
		
		if($do_prefill){
			if($this->input->get('sms_template_id'))$data['prefill_message']=$this->general_model->get_sms_template_message($this->input->get('sms_template_id'));
			if($this->input->get('recp'))$data['prefill_recp']=$this->input->get('recp');
		}
		
		$data['my_contacts_groups']=$this->general_model->get_contacts_groups($user_id);		
		$data['page_title']="Send SMS";
		$data['current_tab']='sms';
		$this->load_client_views('send_sms.php',$data);
	}
	
	function _send_sms($user,$contacts,$message=false,$sender_id=false,$date_time=false,$type=false,$unicode=false,$route=false){
		if(empty($contacts))return array('Error'=>"No recipient contact found.");
		if($message===false)$message=$this->input->post('message');

		if(empty($message))return array('Error'=>"Message is empty");
		$contacts_count=count($contacts);
		if($contacts_count>5000)return array('Error'=>"You can not dispatch SMS to more than 5,000 recipients at once. (You are sending to $contacts_count recipients)");
		
		if(is_array($user))$user_id=$user['user_id'];
		else{
			$exp=explode(':',$user,2);
			$user=$exp[0];
			
			if(!empty($exp[1])){
				$user=$this->general_model->get_sub_account($exp[1]);
				$user_id=$user['user_id'];
			} else {
				$user_id=$user;
				$user=$this->general_model->get_user($user_id,'user_id');
			}
		}
		
		if(isset($user['sub_account_id'])){
			$sub_account_id=$user['sub_account_id'];
			$email=$user['notification_email'];
		}
		else{
			$sub_account_id=0;
			$email=$user['email'];
		}
		
		
		
		if(!empty($user['owing_surety']))return array('Error'=>'The surety units for this reseller account is empty');
		
		if(!empty($email)&&$user['balance']<100&&$user['last_notified']!=date('Y-m-d')){
			if(!empty($user['sub_account']))$formatted_sub=$this->general_model->format_sub_account($user['sub_account'],$user['user_id']);
			$mail_message="Hello!<br/><br/>You have {$user['balance']} SMS credits left on your ";
			if(empty($formatted_sub))$mail_message.=" main account.";
			else $mail_message.=" sub-account:  $formatted_sub";
			$mail_message.="<br/><br/>It's very important that you top up your SMS credits now, to avoid service interruption.<br/><br/>Kind Regards!";
			$this->general_model->send_email($email,"NOTICE: Low SMS Credits Reminder",$mail_message);
			$last_notified_today=date('Y-m-d');
			if(empty($user['sub_account']))$this->general_model->update_user_data($user['user_id'],'last_notified',$last_notified_today);
			else $this->general_model->update_sub_account_data($user['sub_account_id'],array('last_notified'=>$last_notified_today));
		}
		$time=time();
		$batch_id=$user_id.'_'.$sub_account_id.'_'.$time;
		if($type===false)$type=(int)$this->input->post('type');
		if($type!=0)$type=1;
		if($unicode===false)$unicode=(int)$this->input->post('unicode');
		if($unicode!=0)$unicode=1;
		if($route===false)$route=(int)$this->input->post('route');
		if($route!=0)$route=1;
		if($sender_id===false)$sender_id=$this->input->post('sender_id');
		if(!empty($sendder_id)&&!$this->_valid_sender_id($sender_id))return array('Error'=>"Invalid sender id $sender_id. It can only be between 3 to 11 characters (or 3 to 14 digits if numeric)");
		if(empty($sender_id))$sender_id=$user['default_sender_id'];
		if (empty($sender_id))$sender_id=''; //if boolean, false to zero
		
		if($date_time!==false)$ds=$date_time;
		else{
			$date_time=$this->input->post('schedule_date_time');
			$date_time=trim($date_time);
			$ds=$this->general_model->valid_date_time($date_time);
		}
		
		if(!empty($date_time)&&empty($ds))return array('Error'=>'Invalid schedule date-time format');
		
		$diff=0;

		if(empty($ds))$time_scheduled=$time;
		else{
			$time_scheduled=strtotime($ds);
			$diff=$time_scheduled-$time;
			if($diff<=0){
				//if(!empty($date_time))return array('Error'=>"The supplied schedule date-time ($ds) has already passed.  For safety, only future dates are allowed as schedule date-time");
				$time_scheduled=$time;
			}
		}
		
		$data['configs']=$this->general_model->get_configs();
		$cct=count($contacts);
		$send_later=($diff>0);
		$must_send_later=($send_later||$cct>1000);		
		$sms_batch=array();
		$locked=$must_send_later?0:1;
		$sms_total_units=0;
		
		if(!is_array($message)){
			$message_templates=array('default'=>$message);
		}
		else $message_templates=$message;
		
		if(empty($message_templates['default']))$message_templates['default']=current($message_templates);
		
		
		$flag_level=$account_flag_level=@$user['flag_level']*1;
		$restrict=false;
		$much_links=false;
		$flagged_words=empty($data['configs']['flagged_words'])?'':trim($data['configs']['flagged_words']);
		$restricted_sender_ids=empty($data['configs']['restricted_sender_ids'])?'':trim(@$data['configs']['restricted_sender_ids']);
		
		$FRAUD_SUSPECTED=false; 
		$sanitized_sender_id=preg_replace('~[^a-zA-Z0-9]~','',$sender_id);
		$BAD_SENDER_ID=(!empty($restricted_sender_ids)&&preg_match_all("~$restricted_sender_ids~i",$sanitized_sender_id)>=1);

		if(!empty($this->no_restrict)||$flag_level<-1)$restrict=false; 
		else {
			if($flag_level>=3)$restrict=true; 
			
			if($BAD_SENDER_ID){ $restrict=true; $FRAUD_SUSPECTED=true; }
			elseif(!empty($flagged_words)&&(
				preg_match_all("~\b($flagged_words)~i",$message_templates['default'])+
				preg_match_all("~$flagged_words~i",$sender_id)
			)>=2){
				$restrict=true; $FRAUD_SUSPECTED=true;
			}
			elseif(!$restrict){
				$has_link=stripos($message_templates['default'],'http')!==false||
					stripos($message_templates['default'],'www.')!==false;
				
				if(!$has_link)$has_link=preg_match('~\.([a-z]{2,4})/([a-z0-9_]+)~i',$message_templates['default']); //check non arbitary link: .com/xyz
				if(!$has_link)$has_link=preg_match('~\b([a-zA-Z0-9\-]+)\.([a-z]{2,4})\b~',$message_templates['default']); //check non arbitary link: .com/xyz
				
				if(!$has_link)$has_link=preg_match('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b/si',$message_templates['default']); //check email too
				if(!$has_link)$has_link=preg_match('/\b[0-9\-\(\) ]{10,}\b/si',$message_templates['default']); //check phones too
				
				
				
				if($flag_level>=2){ //level two can not send link at all
					if($has_link)$restrict=true;
				}
				else {
					$much_links=($has_link&&$cct>$data['configs']['max_linked_sms']);
					
					if($flag_level>=1){ //level 1 can't send up to 2 links
						if($has_link&&$cct>1)$restrict=true;
					}
					elseif($much_links&&$flag_level>=0)$restrict=true; //nobody can send multiple links except trusted (flag_level<0)
				}

				if($restrict){
					$restrict=!$this->general_model->is_sms_whitelisted($message_templates['default'],$sender_id,$user_id,$sub_account_id);
				}
			}
		}
		
		if($restrict)$locked=1;
		
		foreach($contacts as $contact){
			$extra_data='';

			if(!is_array($contact)){
				$pn=$contact;
				$temp_msg=empty($message_templates[$pn]['message'])?$message_templates['default']:$message_templates[$pn]['message'];
			}
			else {
				$pn=isset($contact['phone'])?$contact['phone']:$contact['recipient'];
				$temp_msg=empty($message_templates[$pn]['message'])?$message_templates['default']:$message_templates[$pn]['message'];
				$temp_msg=$this->_replace_placeholders($temp_msg,$contact);
				if(!empty($contact['extra_data']))$extra_data=$contact['extra_data'];
			}
			
			$pn=$this->_valid_phone($pn,$user['default_dial_code']);
			$pages=$this->general_model->count_message_pages($temp_msg,$unicode);
			
			$sms_units= $this->general_model->get_cgsms_coverage_cost($pn) * $pages; 
			$sms_total_units+=$sms_units;
			$sms_batch[]=array
				(
				'user_id'=>$user_id,
				'sub_account_id'=>$sub_account_id,
				'time_submitted'=>$time,
				'time_scheduled'=>empty($message_templates[$pn]['date_time'])?$time_scheduled:strtotime($message_templates[$pn]['date_time']),
				'recipient'=>$pn,
				'batch_id'=>$batch_id,
				'message'=>$temp_msg,
				'pages'=>$pages,
				'sender'=>$sender_id,
				'type'=>$type,
				'unicode'=>$unicode,
				'route'=>$route,
				'units'=>$sms_units,
				'locked'=>$locked,
				'extra_data'=>$extra_data
				);
		}

		if($user['balance']<$sms_total_units){
			if($sub_account_id||$this->input->is_cli_request())return array('Error'=>"Insufficient SMS credit");
			return array('Error'=>"Oops! You do not have sufficient balance for sending this message.<br/>Your balance is {$user['balance']} units, but $sms_total_units units is needed. Simply <a href='".$this->general_model->get_url('pricing')."' class='alert-link'>get more sms credits here</a>",'balance_error'=>true);
		}
		$num=count($sms_batch);
		$this->general_model->charge_balance($sms_total_units,$user_id,$sub_account_id);
		
		if($FRAUD_SUSPECTED&&$account_flag_level>=0&&$account_flag_level<3){ //3=Restricted,4=locked
			$this->db->where('user_id',$user_id)->limit(1)->update('users',array('flag_level'=>$account_flag_level+1));
		}
		
		$this->general_model->schedule_sms($sms_batch);
		$resp_str=($num==1)?$sms_batch[0]['recipient']:"$num recipients";
		
		if($restrict&&empty($PRE_PENALIZE)){
			if($sub_account_id||$this->input->is_cli_request())return array('Error'=>"Message currently on hold, and will be dispatched for delivery, once your account has been verified.");
			$temp_url=$this->general_model->get_url('profile');
			$resp="Message currently on hold, and will be dispatched for delivery, once your <a href='$temp_url' class='alert-link'>account has been verified</a>";
			
			$temp_url=$this->general_model->get_url("admin_error_log?dispatch_suspended_batch=$batch_id");
			$temp_urlb=$this->general_model->get_url("admin_error_log?dispatch_suspended_batch=$user_id&batch_action=1");
			$temp_url0=$this->general_model->get_url("admin_error_log?cancel_suspended_batch=$batch_id");
			$temp_urlb0=$this->general_model->get_url("admin_error_log?cancel_suspended_batch=$user_id&batch_action=1");
			$temp_url00=$this->general_model->get_url("admin_error_log?penalize_suspended_batch=$batch_id");
			$temp_urlb00=$this->general_model->get_url("admin_error_log?penalize_suspended_batch=$user_id&batch_action=1");
			
			$log_msg="Recipients: $num<br/>Batch Id: $batch_id<br/><br/>SENDER: $sender_id<br/>{$message_templates['default']}<br/><br/>
			<a href='$temp_url' onclick=\"return confirm('Do you really want to allow this?')\" class='btn btn-xs btn-success' >DISPATCH</a>
			|<a href='$temp_urlb' onclick=\"return confirm('Do you really want to allow this?')\" class='btn btn-xs btn-success' >DISPATCH ALL</a>
			| <a href='$temp_url0' onclick=\"return confirm('Do you really want to reject this?')\" class='btn btn-xs btn-warning' >REJECT</a> |
			| <a href='$temp_urlb0' onclick=\"return confirm('Do you really want to reject this?')\" class='btn btn-xs btn-warning' >REJECT ALL</a> |
			| <a href='$temp_url00' onclick=\"return confirm('Do you really want to penalize this?')\" class='btn btn-xs btn-danger' >PENALIZE</a>
			<a href='$temp_urlb00' onclick=\"return confirm('Do you really want to penalize this?')\" class='btn btn-xs btn-danger' >PENALIZE ALL</a>";
			
			$json_data=array(
				'user_id'=>$user_id,
				'sub_account_id'=>$sub_account_id,
				'sender_id'=>$sender_id,
				'message'=>trim($message_templates['default'],' ,.;?')
			);
			
			$this->general_model->log_error("Suspended SMS Batch",$log_msg,$user_id,'suspended_sms',$batch_id,$json_data);
			
			$temp_url2=$this->general_model->get_url("admin_manage_users?f_user_id=$user_id");
			$log_msg="USER ID: <a href='$temp_url2'>$user_id</a><br/>$log_msg";
			$this->general_model->send_email(_ADMIN_EMAIL_,"Suspended SMS Batch",$log_msg);
		}
		else {
			$log_msg="Recipients: $num<br/>Batch Id: $batch_id <br/><br/>{$message_templates['default']}";
			if($much_links)$this->general_model->log_error("Too many linked SMS",$log_msg,$user_id,'technical');
			
			if($send_later){
				$time_scheduled_s=date('D, jS M. Y g:i a',$time_scheduled);
				$resp="Message submitted for delivery to $resp_str by $time_scheduled_s.";
			}
			else{
				if(!$must_send_later){
					$sms_batch=$this->general_model->get_sms_batch($batch_id);
					$message_sent=$this->general_model->send_sms_batch($sms_batch,$data['configs']);
					if($message_sent===0)return array('Error'=>'Message could not be sent to any recipient');
				}
				$resp="Message submitted for immediate delivery to $resp_str";
			}
		}
		
		return array('Success'=>$resp,'num'=>$num,'batch_id'=>$batch_id);
	}
	
	function sub_accounts(){
		$this->check_login();
		$data['page_title']="Sub Accounts";
		$login_data=$this->session->userdata('login_data');
		$user_id=$login_data['user_id'];
		$data['my_profile']=$this->general_model->get_user($user_id,'user_id');

		if($this->input->get('delete_sub_account')){
			$sub_account_id=$this->input->get('delete_sub_account');
			$sub_account_data=$this->general_model->get_sub_account($sub_account_id,$user_id);

			if(empty($sub_account_data))$data['Error']="sub_account record not found.";
			elseif(!empty($sub_account_data['balance']))$data['Error']="This sub account still has a balance of {$sub_account_data['balance']} credits. It can not be deleted.";
			else
			{
				$this->general_model->delete_sub_account($sub_account_id);
				$data['Success']="Record deleted successfully";
			}	
		}
		
		if($this->input->post('add_credits')||$this->input->post('remove_credits')){
			$adding=$this->input->post('add_credits');
			$sub_account_id=$this->input->post('sub_account_id');
			$amount=floatval($this->input->post('amount',true));
            if($amount<=0)$amount=0;
			$pricing_page=$this->general_model->get_url('pricing');
			if($adding&&$amount>$data['my_profile']['balance'])$data['Error']="Sorry, you don't have up to $amount SMS credits in your main balance, <a class='alert-link' href='$pricing_page'> get more SMS credits</a>.";
			elseif($amount>0){
				$sub_account_data=$this->general_model->get_sub_account($sub_account_id,$user_id);
				if(empty($sub_account_data))$data['Error']="sub-account record not found.";
				else
				{
					$sub_balance=$sub_account_data['balance'];
					
					if($adding){
						$new_sub_balance=$sub_balance+$amount;
						$data['my_profile']['balance']-=$amount;
						
						$this->general_model->update_sub_account(array('balance'=>$new_sub_balance),$sub_account_id);
						$this->general_model->update_user_data($user_id,'balance',$data['my_profile']['balance']);
						
						$data['Success']="You have successfully added $amount SMS credits from your main account to '{$sub_account_data['sub_account']}' sub-account.";
					}
					else{
						if($amount>$sub_balance)$data['Error']="Sorry, you don't have up to $amount SMS credits on this sub-account.";
						else{
							$new_sub_balance=$sub_balance-$amount;
							$data['my_profile']['balance']+=$amount;
							
							$this->general_model->update_sub_account(array('balance'=>$new_sub_balance),$sub_account_id);
							$this->general_model->update_user_data($user_id,'balance',$data['my_profile']['balance']);
							
							$data['Success']="You have successfully returned $amount SMS credits back to your main account from '{$sub_account_data['sub_account']}' sub-account.";
						}
					}
				}
			}
		}
		$rules=
			array(
				 array( 'field'=>'sub_account','label'=>'sub account','rules'=>'trim|max_length[25]|alpha_numeric|strtolower'),
				 array( 'field'=>'sub_account_password','label'=>'sub account password','rules'=>'trim|max_length[60]'),
				 array( 'field'=>'notification_email','label'=>'notification email','rules'=>'trim|required|valid_email'),
				 array( 'field'=>'default_dial_code','label'=>'default dial code','rules'=>'trim|integer|max_length[8]'),
				 array( 'field'=>'timezone_offset','label'=>'timezone offset','rules'=>'trim|max_length[6]'),
				 array( 'field'=>'enabled','label'=>'enabled','rules'=>'trim|integer|max_length[1]'),
				 array( 'field'=>'default_sender_id','label'=>'default dial code','rules'=>'trim|min_length[3]|callback__valid_sender_id'),
			);	


		if($this->input->get('edit_sub_account')){
			$this->form_validation->set_rules($rules);
			
			$sub_account_id=$this->input->get('edit_sub_account');
			$sub_account_data=$this->general_model->get_sub_account($sub_account_id);
			if(empty($sub_account_data))$data['Error']="sub_account record not found.";
			else
			{
				if($this->input->post('update_account')!=''){
					$sa=$this->input->post('sub_account',true);
					$sa=trim(strtolower($sa));
					
					$sub_account_data2=array
					(
						'sub_account'=>$sa,
						'sub_account_password'=>$this->input->post('sub_account_password',true),
						'enabled'=>$this->input->post('enabled',true),
						'notification_email'=>$this->input->post('notification_email',true),
						'default_dial_code'=>$this->input->post('default_dial_code',true),
						'timezone_offset'=>$this->input->post('timezone_offset',true),
						'default_sender_id'=>$this->input->post('default_sender_id',true),
					);
					
					$existing=$this->general_model->get_sub_account($sa,$user_id,'sub_account');
					
					if(!$this->form_validation->run())$data['Error']=validation_errors();		
					elseif(!empty($existing)&&$existing['sub_account_id']!=$sub_account_id)$data['Error']='You have already used this name for another sub account';
					else {
						$resp=$this->general_model->update_sub_account($sub_account_data2,$sub_account_id);
						
						if(is_string($resp))$data['Error']=$resp;
						else {
							$data['Success']="Account updated successfully.";
							$just_submitted=true;
						}
					}
				}
				
				if(empty($just_submitted))$data['edit_sub_account_data']=$sub_account_data;
			}
		}
		
		if($this->input->post('add_account')!=''){
			$this->form_validation->set_rules($rules);
			if(!$this->form_validation->run())$data['Error']=validation_errors();
			else
			{
				$sa=$this->input->post('sub_account',true);
				$sa=trim(strtolower($sa));

				$existing=$this->general_model->get_sub_account($sa,$user_id,'sub_account');
				if(!empty($existing))$data['Error']='You have already used this name fore another sub account';
				else
				{
					$sub_account_data=array
					(
						'sub_account'=>$sa,
						'sub_account_password'=>$this->input->post('sub_account_password',true),
						'notification_email'=>$this->input->post('notification_email',true),
						'enabled'=>$this->input->post('enabled',true),
						'default_dial_code'=>$this->input->post('default_dial_code',true),
						'timezone_offset'=>$this->input->post('timezone_offset',true),
						'default_sender_id'=>$this->input->post('default_sender_id',true),
						'user_id'=>$user_id
					);

					$resp=$this->general_model->add_sub_account($sub_account_data);
					$data['Success']="Account successfully added.";
				}
			}
		}


		$data['filter']=array('user_id'=>$user_id);
		$num=$this->general_model->get_sub_accounts(true,$data['filter']);
		if(empty($num))$data['Warning']="No record found.";
		else{
			extract($this->_analyse_pagination($num,$data));
			$data['sub_accounts']=$this->general_model->get_sub_accounts(false,$data['filter']);
		}
		$this->load_client_views('sub_accounts.php',$data);
	}

	
	function run_updates(){	
        $pref=_DB_PREFIX_;
		@$this->db->query("ALTER TABLE {$pref}sms_log CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
		@$this->db->query("ALTER TABLE {$pref}contacts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci");
		@$this->db->query("ALTER TABLE {$pref}sms_log ADD route TINYINT(1) NOT NULL DEFAULT 0 AFTER gateway");
		@$this->db->query("ALTER TABLE {$pref}transactions ADD `checksum` VARCHAR(128) NOT NULL DEFAULT ''");
		echo '--DONE--';
		
	}
	
	function faqs(){
		$data['page_title']="Frequently Asked Questions";
		$this->load_client_views('faqs.php',$data);
	}
	
	function gateway_api(){
		$data['page_title']="SMS Gateway API for developers";
		$this->load_client_views('gateway_api.php',$data);
	}
	
	function _input_request($key,$filter=false){
		return isset($_POST[$key])?$this->input->post($key,$filter):$this->input->get($key,$filter);
	}
	
	function api_v1(){
		$response=new stdClass();
		if(empty($_REQUEST['sub_account']))$response->error_code=1;
		elseif(empty($_REQUEST['sub_account_pass']))$response->error_code=2;
		elseif(empty($_REQUEST['action']))$response->error_code=3;
		else{
			$sub_account=$this->general_model->api_get_sub_account($_REQUEST['sub_account'],$_REQUEST['sub_account_pass']);
			if(empty($sub_account)){ $response->error_code=4; $response->auth_error=1; }
			elseif(empty($sub_account['enabled'])){$response->error_code=5; $response->auth_error=1; }
			else
			{
				$sub_account_id=$sub_account['sub_account_id'];		
				if($_REQUEST['action']=='account_info')
				{
					unset($sub_account['enabled'],$sub_account['sub_account_password']);
					$response=(object)$sub_account;
				}
				elseif($_REQUEST['action']=='stop_sms'||$_REQUEST['action']=='delete_sms'||$_REQUEST['action']=='get_total_units')
				{
					if($_REQUEST['action']=='get_total_units')$subaction='get_total_units';
					else $subaction=($_REQUEST['action']=='delete_sms')?'deleted':'stopped';

					$filters=array('sub_account_id'=>$sub_account_id);
					$filts=array('batch_id','sms_id','sms_ids','type','recipient','sender_id','stage','start_date','end_date');
					
					$sub_filt_found=false;
					
					foreach($filts as $filt)
					{
						if(empty($_REQUEST[$filt]))$filters[$filt]='';
						else
						{
							$filters[$filt]=$_REQUEST[$filt];
							$sub_filt_found=true;
						}
					}
					
					if($_REQUEST['action']=='delete_sms'&&!$sub_filt_found){
						$filts_str=implode(',',$filts);
						$response->error="To prevent mistake of deleting your entire sms log, you must supply at least a filter from ($filts_str)";
						$response->error_code=7;
					}
					else {
						$resp=$this->general_model->run_batch_action($filters,$subaction);
						$response->total=$resp['total'];
						if(empty($response->total))$response->error_code=10;
						elseif($subaction=='get_total_units')$response->total_units=$resp['total_units'];
					}
				}
				elseif($_REQUEST['action']=='fetch_sms')
				{
					$filters=array('sub_account_id'=>$sub_account_id);
					$filts=array('batch_id','sms_id','search_term','sms_ids','type','recipient','sender_id','stage','start_date','end_date','p');
					foreach($filts as $filt)$filters[$filt]=empty($_REQUEST[$filt])?'':$_REQUEST[$filt];
					
					$perpage=@$_REQUEST['perpage'];
					
					if(!is_numeric($perpage))$perpage=100;
					elseif($perpage<1)$perpage=1;
					elseif($perpage>300)$perpage=300;
					$filters['perpage']=$perpage;
					
					$data['filter']=$filters;					
					$num=$this->general_model->get_sms_log(true,$data['filter']);
					
					if(empty($num)){
						$data['totalpages']=$data['p']=0;
						$sms_log=array();
					}
					else
					{
						extract($this->_analyse_pagination($num,$data));

						$sms_log=$this->general_model->get_sms_log(false,$data['filter']);
						
						$tz=$this->general_model->tz_offset_to_name($sub_account['timezone_offset']);
						if(empty($tz)){
							$tz='Africa/Lagos';
							$sub_account['timezone_offset']=1;
						}
						
						date_default_timezone_set($tz);

						foreach($sms_log as $temp_sms_log_k=>$temp_sms){
							unset($sms_log[$temp_sms_log_k]['deleted'],$sms_log[$temp_sms_log_k]['gateway']);
							$sms_log[$temp_sms_log_k]['status_msg']=strtoupper(@$this->general_model->sms_status[$temp_sms['status']]['title']);
							
							$sms_log[$temp_sms_log_k]['submitted_at']=date('Y-m-d H:i',$temp_sms['time_submitted']);
							$sms_log[$temp_sms_log_k]['scheduled_to']=date('Y-m-d H:i',$temp_sms['time_scheduled']);
							if($temp_sms['time_sent']>0)$sms_log[$temp_sms_log_k]['sent_at']=date('Y-m-d H:i',$temp_sms['time_sent']);
						}
					}
				
					$response->total=$num;
					$response->totalpages=$data['totalpages'];
					$response->p=$data['p'];
					$response->perpage=$data['filter']['perpage'];
					$response->timezone_offset=$sub_account['timezone_offset'];
					$response->records=array_values($sms_log);
					$response->filter=$data['filter'];
				}
				elseif($_REQUEST['action']=='send_sms')
				{
					if(empty($_REQUEST['recipients'])&&empty($_REQUEST['contact_groups'])&&empty($_REQUEST['contacts'])){
						$response->error='recipients not supplied';
						$response->error_code=7;
					}
					elseif(empty($_REQUEST['message'])){
						$response->error='message not supplied';
						$response->error_code=7;
					}
					elseif(isset($_REQUEST['default_dial_code'])&&!is_numeric(trim($_REQUEST['default_dial_code'],'+'))){
						$response->error='Invalid default dial-code, use the format: +000';
						$response->error_code=8;
					}
					elseif(isset($_REQUEST['timezone_offset'])&&!preg_match("~^[-+]?[0-9]{1,2}(:[0-9]{1,2})?$~",$_REQUEST['timezone_offset'])){
						$response->error='Invalid time zone offset format.';
						$response->error_code=8;
					}
					elseif(!empty($_REQUEST['send_at'])&&!$this->general_model->valid_date_time($_REQUEST['send_at']))
					{
						$response->error='Invalid scheduled date_time format.';
						$response->error_code=8;
					}
					else{
					
						if(!empty($_REQUEST['contact_groups'])){
							$group_contacts=$this->general_model->get_group_contacts($_REQUEST['contact_groups'],$sub_account['user_id'],$sub_account['sub_account_id']);
						}
						
						if(!empty($_REQUEST['recipients']))$recipients=$this->_parse_numbers($_REQUEST['recipients'],$sub_account['default_dial_code']);
						if(empty($recipients))$recipients=array();
						
						$time=time();
						$group_name=empty($_REQUEST['save_as'])?'default':$this->_clean_group_name($_REQUEST['save_as']);
						$contacts=array();
						$message_templates=array('default'=>$_REQUEST['message']);
						
						if(isset($_REQUEST['contacts']))
						{
							if(is_array($_REQUEST['contacts']))$raw_contacts=$_REQUEST['contacts'];
							else $raw_contacts=@json_decode($_REQUEST['contacts'],true);
							
							if($raw_contacts===false)
							{
								$response->error='contacts supplied contains Invalid JSON string';
								$response->error_code=8;
							}
							else 
							{
								foreach($raw_contacts as $raw_contact)
								{
									if(is_array($raw_contact))$phone_i=$this->_valid_phone(@$raw_contact['phone'],$sub_account['default_dial_code']);
									if(empty($phone_i))continue;
									
									if(!empty($raw_contact['override_message']))$message_templates[$phone_i]['message']=$raw_contact['override_message'];
									if(!empty($raw_contact['override_date_time']))$message_templates[$phone_i]['date_time']=$raw_contact['override_date_time'];
									
									$contacts[$phone_i]=array(
										'phone'=>$phone_i,
										'group_name'=>empty($raw_contact['group_name'])?$group_name:$this->_clean_group_name($raw_contact['group_name']),
										'firstname'=>empty($raw_contact['firstname'])?'':$raw_contact['firstname'],
										'lastname'=>empty($raw_contact['lastname'])?'':$raw_contact['lastname'],
										'user_id'=>$sub_account['user_id'],
										'sub_account_id'=>$sub_account['sub_account_id'],
										'time'=>$time,
									);
								}
							}							
						}
						
						if(empty($recipients)&&empty($group_contacts)&&empty($contacts)&&empty($response->error))$response->error='No valid recipient found';
						elseif(empty($response->error)){
							if(isset($_REQUEST['default_dial_code']))$sub_account['default_dial_code']=$_REQUEST['default_dial_code'];
							if(isset($_REQUEST['timezone_offset']))$sub_account['timezone_offset']=$_REQUEST['timezone_offset'];
							
							$tz=$this->general_model->tz_offset_to_name($sub_account['timezone_offset']);
							if(!empty($tz))date_default_timezone_set($tz);
							
							if(!empty($_REQUEST['sender_id']))$sub_account['default_sender_id']=$_REQUEST['sender_id'];
							$date_time=isset($_REQUEST['send_at'])?trim($_REQUEST['send_at']):false;
							
							
							if(!empty($recipients))
							{
								foreach($recipients as $phone_i)
								{
									$contacts[$phone_i]=array(
										'phone'=>$phone_i,
										'group_name'=>$group_name,
										'user_id'=>$sub_account['user_id'],
										'sub_account_id'=>$sub_account['sub_account_id'],
										'time'=>$time,
									);
								}
								
								if(!empty($_REQUEST['save_as'])&&!empty($contacts))$this->general_model->save_contacts(array_values($contacts));
							}
							
							if(!empty($group_contacts)){
								$contacts=array_replace($group_contacts,$contacts);
							}
							
							if(!empty($_REQUEST['ignore_groups'])){
								$ignore_contacts=$this->general_model->get_group_contacts($_REQUEST['ignore_groups'],$sub_account['user_id'],$sub_account['sub_account_id']);
								$contacts=array_diff_key($contacts,$ignore_contacts);
							}
							
							$type=empty($_REQUEST['type'])?0:1;
							$unicode=empty($_REQUEST['unicode'])?0:1;
							$route=empty($_REQUEST['route'])?0:1;
							
							$resp=$this->_send_sms($sub_account,$contacts,$message_templates,false,$date_time,$type,$unicode,$route);
							
							if(isset($resp['batch_id']))
							{
								$response->batch_id=$resp['batch_id'];
								$response->total=$resp['num'];
							}
							else $response->error=$resp['Error'];
						}
					}
				}
				elseif($_REQUEST['action']=='save_contacts')
				{
					if(empty($_REQUEST['contacts'])&&empty($_REQUEST['phone_numbers'])){
						$response->error='no contacts or phone_numbers supplied';
						$response->error_code=7;
					}
					else{
						if(isset($_REQUEST['contacts'])){
							if(is_array($_REQUEST['contacts']))$raw_contacts=$_REQUEST['contacts'];
							else {
								$raw_contacts=@json_decode($_REQUEST['contacts'],true);
								if($raw_contacts===false)
								{
									$response->error='contacts supplied contains Invalid JSON string';
									$response->error_code=8;
								}
							}
						}
						
						if(!isset($response->error)&&isset($_REQUEST['phone_numbers']))$raw_phones=preg_split("/[\s,]+/",$_REQUEST['phone_numbers']);
					}

					if(!isset($response->error)){
						$contacts=array();
						
						$tempi=0;
						$time=time();
						$group_name=@$_REQUEST['group_name'];
						if(empty($group_name))$group_name='default';
						else $group_name=$this->_clean_group_name($group_name);
						
						if(!empty($raw_contacts)){
							foreach($raw_contacts as $raw_contact)
							{
								if(is_array($raw_contact))$phone_i=$this->_valid_phone(@$raw_contact['phone'],$sub_account['default_dial_code']);
								if(empty($phone_i))continue;

								$contacts[$phone_i]=array(
									'phone'=>$phone_i,
									'group_name'=>empty($raw_contact['group_name'])?$group_name:$this->_clean_group_name($raw_contact['group_name']),
									'firstname'=>empty($raw_contact['firstname'])?'':$raw_contact['firstname'],
									'lastname'=>empty($raw_contact['lastname'])?'':$raw_contact['lastname'],
									'user_id'=>$sub_account['user_id'],
									'sub_account_id'=>$sub_account['sub_account_id'],
									'time'=>$time,
								);
							}
						}
						
						if(!empty($raw_phones)){
							foreach($raw_phones as $raw_phone)
							{
								$phone_i=$this->_valid_phone($raw_phone,$sub_account['default_dial_code']);
								if(empty($phone_i))continue;

								$contacts[$phone_i]=array(
									'phone'=>$phone_i,
									'group_name'=>$group_name,
									'user_id'=>$sub_account['user_id'],
									'sub_account_id'=>$sub_account['sub_account_id'],
									'time'=>$time,
								);
							}
						}
						
						$count_contacts=count($contacts);
						
						if($count_contacts==0)$response->error='No valid contact supplied.';
						else{
							$this->general_model->save_contacts(array_values($contacts));
							$response->total=$count_contacts;
						}
					}
				}
				elseif($_REQUEST['action']=='get_contact_groups')$response->contact_groups=$this->general_model->get_contacts_groups($sub_account['user_id'],$sub_account['sub_account_id']);
				elseif($_REQUEST['action']=='delete_contacts')
				{
					$search_term=@$_REQUEST['search_term'];
					$group_name=@$_REQUEST['group_name'];
					$contact_ids=@$_REQUEST['contact_ids'];
					
					if(empty($contact_ids)&&empty($group_name)&&empty($search_term)){
						$response->error='No contact_ids, group_name or search_term supplied to be deleted';
						$response->error_code=7;
					}
					else {
						$delete_params=array(
							'user_id'=>$sub_account['user_id'],'sub_account_id'=>$sub_account['sub_account_id'],'contact_ids'=>$contact_ids,'group_name'=>$this->_clean_group_name($group_name),'search_term'=>$search_term
							);

						$response->total=$this->general_model->delete_contacts($delete_params);
					}
				}
				elseif($_REQUEST['action']=='update_sms_category')
				{
					if(empty($_REQUEST['sms_category_id'])||!is_numeric(@$_REQUEST['sms_category_id'])){$response->error='No valid sms category id supplied to be updated'; $response->error_code=7; }
					elseif(empty($_REQUEST['sms_category'])){ $response->error='Category message not supplied'; $response->error_code=7;}
					else
					{ 
						$sms_category_id=$_REQUEST['sms_category_id'];
						
						$sms_category_data=$this->general_model->get_sms_category($sms_category_id,$sub_account['user_id'],$sub_account['sub_account_id']);
						if(empty($sms_category_data))$response->error='Category not found or in-accessible';
						else
						{
							$sms_category_data2=array(
								'sms_category'=>$_REQUEST['sms_category'],
								'sms_category_id'=>$_REQUEST['sms_category_id'],
								'category_description'=>$this->_input_request('category_description',true),
							);
						
							$resp=$this->general_model->update_sms_category($sms_category_data2,$sms_category_id);
							
							if(is_string($resp))$response->error=$resp;
							else $response->success='Category updated successfully';
						}
					}
				}
				elseif($_REQUEST['action']=='add_sms_category')
				{
					if(empty($_REQUEST['sms_category']))$response->error='Category message not supplied.';
					else{
							$sms_category_data2=array(
								'sms_category'=>$_REQUEST['sms_category'],
								'category_description'=>$this->_input_request('category_description',true),
								'user_id'=>$sub_account['user_id'],
								'sub_account_id'=>$sub_account['sub_account_id'],
							);
						
							$resp=$this->general_model->add_sms_category($sms_category_data2);
							if(is_string($resp))$response->error=$resp;
							else $response->success='Category added successfully';
					}
				}
				elseif($_REQUEST['action']=='delete_sms_category')
				{
					if(empty($_REQUEST['sms_category_ids'])){
						$response->error='No sms category id supplied to be deleted';
						$response->error_code=7;
					}
					else {
						$delete_params=array('sub_account_id'=>$sub_account['sub_account_id'],'sms_category_ids'=>trim($_REQUEST['sms_category_ids']));
						$response->total=$this->general_model->delete_sms_categories($delete_params);
					}
				}
				elseif($_REQUEST['action']=='update_sms_template')
				{
					if(empty($_REQUEST['sms_template_id'])){
						$response->error='No sms template id supplied to be updated';
						$response->error_code=7;
					}
					elseif(empty($_REQUEST['sms_template'])){ $response->error='Template message not supplied'; $response->error_code=7;}
					elseif(empty($_REQUEST['sms_category_id'])||!is_numeric(@$_REQUEST['sms_category_id'])){$response->error='Valid SMS Category not supplied.'; $response->error_code=7; }
					else
					{
						$sms_template_id=$_REQUEST['sms_template_id'];
						
						$sms_template_data=$this->general_model->get_sms_template($sms_template_id,$sub_account['user_id'],$sub_account['sub_account_id']);
						if(empty($sms_template_data))$response->error='Template not found or in-accessible';
						else
						{
							$sms_template_data2=array(
								'sms_template'=>$_REQUEST['sms_template'],
								'sms_category_id'=>$_REQUEST['sms_category_id'],
							);
						
							$resp=$this->general_model->update_sms_template($sms_template_data2,$sms_template_id);
							
							if(is_string($resp))$response->error=$resp;
							else $response->success='Template updated successfully';
						}
					}
				}
				elseif($_REQUEST['action']=='add_sms_template')
				{
					if(empty($_REQUEST['sms_template']))$response->error='Template message not supplied.';
					elseif(empty($_REQUEST['sms_category_id'])||!is_numeric(@$_REQUEST['sms_category_id']))$response->error='Valid SMS Category not supplied.';
					else{
							$sms_template_data2=array(
								'sms_template'=>$_REQUEST['sms_template'],
								'sms_category_id'=>$_REQUEST['sms_category_id'],
								'user_id'=>$sub_account['user_id'],
								'sub_account_id'=>$sub_account['sub_account_id'],
							);
						
							$resp=$this->general_model->add_sms_template($sms_template_data2);
							if(is_string($resp))$response->error=$resp;
							else $response->success='Template added successfully';
					}
				}
				elseif($_REQUEST['action']=='delete_sms_template')
				{
					if(empty($_REQUEST['sms_template_ids'])){
						$response->error='No sms template id supplied to be deleted';
						$response->error_code=7;
					}
					else {
						$delete_params=array('sub_account_id'=>$sub_account['sub_account_id'],'sms_template_ids'=>trim($_REQUEST['sms_template_ids']));
						$response->total=$this->general_model->delete_sms_templates($delete_params);
					}
				}
				elseif($_REQUEST['action']=='fetch_contacts')
				{
					$filters=array('sub_account_id'=>$sub_account_id);
					$filts=array('contact_ids','group_name','search_term','p');
					
					foreach($filts as $filt)$filters[$filt]=empty($_REQUEST[$filt])?'':$_REQUEST[$filt];
					
					if(isset($filters['group_name']))$filters['group_name']=$this->_clean_group_name($filters['group_name']);
					
					$perpage=@$_REQUEST['perpage'];
					if(!is_numeric($perpage))$perpage=100;
					elseif($perpage<1)$perpage=1;
					elseif($perpage>300)$perpage=300;
					$filters['perpage']=$perpage;
					
					$data['filter']=$filters;					
					$num=$this->general_model->get_contacts(true,$data['filter']);
					
					if(empty($num)){
						$data['totalpages']=$data['p']=0;
						$records=array();
					}
					else
					{
						extract($this->_analyse_pagination($num,$data));
						$records=$this->general_model->get_contacts(false,$data['filter']);
					}
				
					$response->total=$num;
					$response->totalpages=$data['totalpages'];
					$response->p=$data['p'];
					$response->perpage=$data['filter']['perpage'];
					$response->timezone_offset=$sub_account['timezone_offset'];
					$response->records=empty($_REQUEST['indexed'])?array_values($records):$records;
					$response->filter=$data['filter'];
				}
				else 
				{
					$response->error_code=6;
					$response->error="Invalid action {$_REQUEST['action']}";
				}
				
				if(empty($response->error)&&empty($response->error_code)&&!empty($_REQUEST['extra_actions'])){
					$exas=explode(',',$_REQUEST['extra_actions']);
					foreach($exas as $exa){
						if(trim($exa)=='get_contact_groups')
							$response->contact_groups=$this->general_model->get_contacts_groups($sub_account['user_id'],$sub_account['sub_account_id']);
						elseif(trim($exa)=='account_info'){
							$sub_account=$this->general_model->api_get_sub_account($_REQUEST['sub_account'],$_REQUEST['sub_account_pass']);
							unset($sub_account['enabled'],$sub_account['sub_account_password']);
							$response->account_info=$sub_account;
						}
					}					
				}
			}
		}
		
		header('Content-Type: application/json');
		if(isset($response->error_code)&&!isset($response->error)){
			$error_codes=$this->general_model->get_api_errors();
			$response->error=$error_codes[$response->error_code];
		}
		elseif(!isset($response->error_code)&&isset($response->error)){
			$response->error_code=11;
		}
		echo json_encode($response);	
	}
	
	public function privacy_policy(){
		$data['page_title']="Privay Policy";
		$this->load_client_views('privacy_policy.php',$data);
	}
	
	public function terms(){
		$data['page_title']='Users Agreement';
		$this->load_client_views('terms.php',$data);
	}

	public function pricing(){
		$data['page_title']='Buy SMS Credits';
		$data['section_title']='Pricing';
		$this->load_client_views('pricing.php',$data);
	}
	
	function _commit_transaction(){
		$response=array();
		$sms_units=floatval($this->input->post_get('units',true));
		if($sms_units<=0)return "Please supply a valid SMS credit units.";
		
		$user_id=$this->general_model->logged_in();
		if(!$user_id)return "You need to login first.";
		
		$configs=$this->general_model->get_configs();
		if($sms_units<$configs['minimum_units'])return "You can not buy less than {$configs['minimum_units']} SMS units";

		$cur=$this->input->post('payment_currency',true);
		$currencies=$this->general_model->get_currencies(true);
		
		$original_amount=$this->general_model->sms_units_to_price($sms_units,$currencies[$cur]['value'],!empty($user_data['reseller_account']));
		$amount=$original_amount;
		$tax_amount=0;
		if(!empty($configs['tax_percent'])){
			$tax_amount=$amount*0.01*$configs['tax_percent'];
			$amount+=$tax_amount;
		}
		$charges=0; $fixed_charge=0; $variable_charges=0;
		$amount=$amount+$charges;				
		$amount=number_format($amount,$currencies[$cur]['decimal_places'],'.','');

		$payment_memo="$sms_units SMS Credits";
		$time=time();
		$curr_code=$currencies[$cur]['iso_code'];
		$json_details=array('sms_units'=>$sms_units,'original_amount'=>$original_amount,'gateway_charges'=>$variable_charges,'payment_method_fixed_charges'=>$fixed_charge,'tax_amount'=>$tax_amount,'tax_percent'=>$configs['tax_percent']);
		
		$transaction=
			array(
					'user_id'=>$user_id,
					'time'=>$time,
					'transaction_reference'=>$time,
					'amount'=>$amount,
					'type'=>1, //account funding
					'currency_code'=>$cur,
					'details'=>$payment_memo,									
					'payment_method'=>'unifiedpurse',
					'sms_units'=>$sms_units,
					'net_amount_fiat'=>$original_amount/$currencies[$cur]['value'],
					'json_details'=>json_encode($json_details)
					);
		
		{
			
			$this->general_model->insert_transaction($transaction);
			$payment_date=date('d-m-Y g:i a',$transaction['time']);
			$notify_url=$this->general_model->get_url('transaction');
			$user_data=$this->general_model->get_user($user_id,'user_id');
			
			$mail_message="Hello {$user_data['firstname']}<br/><br/>
			Please find your transaction information below:<br/><br/>
			Date: $payment_date<br/>
			Amount: {$transaction['amount']} {$transaction['currency_code']}<br/>
			Payment Method: UnifiedPurse.com<br/>
			Details: {$transaction['details']}<br/><br/>
			You can always confirm your transaction/payment status at $notify_url?confirm_trans={$transaction['transaction_reference']}<br/><br/>Regards.";
			$this->general_model->send_email($user_data['email'],"Transaction Information",$mail_message);
			return "success:{$transaction['transaction_reference']}:$amount:{$transaction['currency_code']}";
		}
	}
	
	function transaction(){
		$confirm_payment=false;
		//$_REQUEST=array_merge($_REQUEST,$_GET,$_POST);
		if(!empty($_REQUEST['refNo'])&&!empty($_REQUEST['transactionId']))$trans_ref=$_REQUEST['transactionId'];
		elseif(!empty($_POST['gtpay_tranx_id']))$trans_ref=$_POST['gtpay_tranx_id'];
		elseif(!empty($_REQUEST['txnref'])&&!empty($_REQUEST['payRef']))$trans_ref=$_REQUEST['txnref'];		
		elseif(!empty($_POST['customid']))$trans_id=$_POST['customid'];
		elseif(isset($_POST['cart_order_id']))$trans_ref=$_POST['cart_order_id'];
		elseif(isset($_REQUEST['confirm_trans']))$trans_ref=$_REQUEST['confirm_trans'];
		elseif(isset($_REQUEST['ORDER_NUM']))$trans_ref=$_REQUEST['ORDER_NUM'];
		elseif(!empty($_GET['OrderID']))$trans_ref=$_GET['OrderID'];
		elseif(!empty($_GET['receipt']))$trans_ref=$_GET['receipt'];
		elseif(!empty($_REQUEST['ref']))$trans_ref=$_REQUEST['ref']; //for jostpay
	
		if(!empty($trans_ref)){
			$transaction=$this->general_model->get_transaction($trans_ref,true);
			$configs=$this->general_model->get_configs();
			$data['configs']=$configs;
			if(empty($transaction))$Error="Transaction record ($trans_ref) not found.";
			elseif(isset($_GET['receipt'])){
				if($this->general_model->valid_invoice_token($transaction,@$_GET['inv_tok']))$data['display_receipt']=true;
				else
				{
					$transaction=array();
					$Error='ACCESS DENIED';
				}
			}
			
			$bit_hash='';
			
			if($transaction['status']!=1&&empty($data['Error'])){
				$approved_amount='0.00';
				$expected_deposit=$transaction['amount'] ;
				$new_status=0;
				
				if($transaction['payment_method']=='unifiedpurse'){
					$post_data=array(
						'action'=>'get_transaction',
						'receiver'=>$configs['unifiedpurse_username'],
						'original_amount'=>$expected_deposit,
						'original_currency_code'=>$transaction['currency_code'],
						'ref'=>$trans_ref,
					);

					if($this->general_model->on_localhost())$temp_url='https://localhost/unifiedpurse.com/api_v1';
					else $temp_url='https://unifiedpurse.com/api_v1';
					
					$json=$this->general_model->_curl_json($temp_url,$post_data,true);
					
					if(!empty($json['error']))$json_data['info']=$json['error'];
					else {
						$json_data['approved_amount']=$json['amount'];
						$json_data['response_description']=$json['info'];

						if($json['status_msg']=='FAILED')$new_status=-1;
						elseif($json['status_msg']=='COMPLETED'){
							if(isset($json['original_currency_code'])&&$json['original_currency_code']!=$transaction['currency_code']){
							$json_data['response_description']="Incorrect deposit currency ({$transaction['currency_code']} was expected, but {$json['original_currency_code']} found). ";
								$new_status=-1;
							}
							elseif(floatval($json['original_amount'])<$expected_deposit){
								$json_data['response_description']="Incorrect deposit amount ($expected_deposit {$transaction['currency_code']} was expected, but {$json['original_amount']} found). ";
								$new_status=-1;
							}
							else {
								$new_status=1;
								
								if(!empty($json['meta_info']['amount_received'])&&!empty($json['meta_info']['currency_received'])){
									$approved_amount=floatval($json['meta_info']['amount_received']);
									$currency_received=$json['meta_info']['currency_received'];
									$payment_method_charges=floatval($json['meta_info']['payment_method_charges']);
									/*
									$expected_amount=floatval($json['meta_info']['amount_expected']);
									$expected_currency=$json['meta_info']['currency_expected'];
									*/
									
									$json_data['approved_amount']=$approved_amount;
									$json_data['response_description']=$json['info'];
									if($payment_method_charges<=0)$net_amount_receive=$approved_amount;
									else $net_amount_receive=$approved_amount-$payment_method_charges;
								
									if($net_amount_receive!=$expected_deposit){
										$temp_info=$json['info'];
										$payment_info=array(
											'amount_paid'=>$approved_amount,'currency_code'=>$currency_received,
											'gateway_charges'=>$payment_method_charges,
										);

										$data=$this->_admin_complete_transaction($data,$transaction,$payment_info,$temp_info);
										
										$transaction=$this->general_model->get_transaction($trans_ref,true);
										$json_data=$this->general_model->get_json($transaction);
										$transaction_pre_completed=true;
									}
								}
								
							}
						}
					}
				}
				else $data['Error']="Requery not implemented for {$transaction['payment_method']} ";
				
				if(empty($data['Error'])){
					//$new_status=1; //zombie payment confirmation:: do not uncomment.
					if(empty($batch_used))$batch_used='';
					$this->general_model->change_transaction_status($trans_ref,$new_status,$json_data,$batch_used);
					
					//update it for display purpose.
					$transaction['status']=$new_status;
					$transaction['json_info']=json_encode($json_data);
				}
			}
		}
		$this->check_login();
		$user_id=$this->get_login_data('user_id');
		if(!empty($transaction)&&empty($force_list))$data['transaction']=$transaction;
		else{
			$data['list_transactions']=true;
			$type=$this->input->get('t');
			$status=$this->input->get('s');
			$start_date=$this->input->get('sd');
			$end_date=$this->input->get('ed');
			$payment_method=$this->input->get('pm');
			$filter=array('user_id'=>$user_id,'payment_method'=>$payment_method,'type'=>$type,'status'=>$status,'start_date'=>$start_date,'end_date'=>$end_date);
			$num=$this->general_model->get_transactions(true,$filter);
			if(empty($num))$data['Warning']="No transaction record found.";
			else
			{
				$perpage=10;
				$totalpages=ceil($num/$perpage);
				$p=empty($_GET['p'])?1:$_GET['p'];
				if($p>$totalpages)$p=$totalpages;
				if($p<1)$p=1;
				$offset=($p-1) *$perpage;
				$data['p']=$p;
				$data['totalpages']=$totalpages;
				$data['total']=$num;
				$filter['offset']=$offset;
				$filter['perpage']=$perpage;			
				$data['transactions']=$this->general_model->get_transactions(false,$filter,true);			
			}
			$data['filter']=$filter;
		}		
		$data['page_title']="Transaction Details";
		$data['my_balance']=$this->general_model->get_balance($user_id);
		$this->load_client_views('transaction.php',$data);
	}
	
	########################################### -- ADMIN FUNCTIONS -- ############################
	
	function check_admin_logged_in(){
		if(!$this->general_model->admin_logged_in())$this->b_redirect('admin_login');
	}
	
	public function admin_login(){	
		$data['page_title']="Admin Login";		$code=$this->input->get('code',true);
		if($code){
			$code=md5($code);
			if($code==$this->session->userdata('admin_mail_code')){
				$this->general_model->log_admin_in();
				$this->b_redirect("panel");
			}
			else $data['Error']='Invalid authentication code!';	
		}
		
		$this->form_validation->set_rules('email', 'Email', 'required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'required');
		
		if($this->form_validation->run()){
			if($this->input->post('email')!=_ADMIN_EMAIL_||$this->input->post('password')!=_ADMIN_PASS_)$data['Error']="Incorrect admin email or password.";
			else {
				$skip_email=$this->input->get('override_e2fa',true);

				if($this->general_model->on_localhost()||$skip_email)
				{
					$this->general_model->log_admin_in();
					$this->b_redirect("panel");
				}
				else {
					$code=mt_rand(10000,999999);
					$access_link=$this->general_model->get_url("admin_login/?code=$code");
					$msg="Follow this link to access admin panel: $access_link ";
					$this->general_model->send_email(_ADMIN_EMAIL_,'Admin Panel Access Link',$msg);
					$this->session->set_userdata('admin_mail_code',md5($code));
					$data['Success']='Check the email for access link';
				}
			}
		}
		$this->load_admin_views('login.php',$data,true);
	}
	
	public function admin_unifiedpurse_widget(){
		$this->check_admin_logged_in();
		$temp_url=$this->input->post('unifiedpurse_widget_url',true);
		if(!empty($temp_url)){
			$this->general_model->update_config('unifiedpurse_widget_url',$temp_url);
			$data['Success']="Widget URL has been saved";
		}
		$data['page_title']="Unifiedpurse Transaction Widget | Admin Panel";		
		$this->load_admin_views('unifiedpurse_widget.php',$data);
	}
	
	public function panel(){	
		if($this->input->get('logout'))$this->general_model->log_admin_out();		
		$this->check_admin_logged_in();
		ini_set('error_reporting',E_WARNING|E_ERROR|E_PARSE);
		$data['page_title']="Website Configuration | Admin Panel";		
		$data['current_tab']='settings';
		$presetData=$configs=$this->general_model->get_configs();
		if(empty($presetData)||$this->input->post('save_configs')!=''){
			$presetData=
				array(
						'site_name'=>$this->input->post('site_name',true),
						'minimum_units'=>$this->input->post('minimum_units',true),
						'maximum_reseller_price'=>$this->input->post('maximum_reseller_price',true),
						'cron_mails_per_minute'=>$this->input->post('cron_mails_per_minute',true),
						'cron_report_email'=>$this->input->post('cron_report_email',true),
						'cgsms_sub_account'=>$this->input->post('cgsms_sub_account',true),
						'cgsms_sub_account_password'=>$this->input->post('cgsms_sub_account_password',true),
						'max_linked_sms'=>$this->input->post('max_linked_sms',true),
						'force_default_lang'=>$this->input->post('force_default_lang',true),
						'default_dial_code'=>$this->input->post('default_dial_code',true),
						'default_country_code'=>$this->input->post('default_country_code',true),
						'site_meta_title'=>$this->input->post('site_meta_title',true),
						'site_meta_copyright'=>$this->input->post('site_meta_copyright',true),
						'site_meta_keywords'=>$this->input->post('site_meta_keywords',true),
						'site_meta_description'=>$this->input->post('site_meta_description',true),
						'home'=>$this->input->post('home'),
						'snippets_in_header'=>$this->input->post('snippets_in_header'),
						'snippets_in_footer'=>$this->input->post('snippets_in_footer'),
						'site_vertical_banner'=>$this->input->post('site_vertical_banner'),
						'site_vertical_long_banner'=>$this->input->post('site_vertical_long_banner'),
						'site_horizontal_banner'=>$this->input->post('site_horizontal_banner'),
						'site_mobile_horizontal_banner'=>$this->input->post('site_mobile_horizontal_banner'),
						'site_notice_logged_in'=>$this->input->post('site_notice_logged_in',true),
						'site_notice_logged_out'=>$this->input->post('site_notice_logged_out',true),
						'facebook_url'=>$this->input->post('facebook_url',true),
						'twitter_url'=>$this->input->post('twitter_url',true),
						'facebook_app_id'=>$this->input->post('facebook_app_id',true),
						
						'contact_phone'=>$this->input->post('contact_phone',true),
						'contact_whatsapp'=>$this->input->post('contact_whatsapp',true),
						'contact_telegram'=>$this->input->post('contact_telegram',true),
						
						'flagged_words'=>$this->input->post('flagged_words',true),
						'restricted_sender_ids'=>$this->input->post('restricted_sender_ids',true),
					);

            if(empty(trim(strip_tags($presetData['home']))))$presetData['home']='';
            
			$allowed_signup_email_domains=preg_replace("~\s*,\s*~",',',$this->input->post('allowed_signup_email_domains',true));
			$presetData['allowed_signup_email_domains']=strtolower(trim($allowed_signup_email_domains,', '));

			$blacklisted_names=preg_replace("~\s*,\s*~",',',$this->input->post('blacklisted_names',true));
			$presetData['blacklisted_names']=strtolower(trim($blacklisted_names,', '));
		}
		$rules=		
		array(
               array(
                     'field'=>'site_meta_title',
                     'label'=>'meta title',
                     'rules'=>'trim|required'
                  ),
               array(
                     'field'=>'site_meta_copyright',
                     'label'=>'meta copyright',
                     'rules'=>'trim|required'
                  ),
               array(
                     'field'=>'site_meta_keywords',
                     'label'=>'meta keywords',
                     'rules'=>'trim|required'
                  ),
               array(
                     'field'=>'site_meta_description',
                     'label'=>'meta description',
                     'rules'=>'trim|required'
                  ),
               array(
                     'field'=>'site_vertical_banner',
                     'label'=>'vertical banner',
                     'rules'=>'trim'
                  ),
               array(
                     'field'=>'site_horizontal_banner',
                     'label'=>'horizontal banner',
                     'rules'=>'trim'
                  ),
		);
		
		$this->form_validation->set_rules($rules); 
		if(!$this->form_validation->run()&&!isset($_POST['show_dues']))$data['Error']=validation_errors();
		else{
			$this->general_model->update_configs($presetData);
			$data['Success']="Record Updated";
		}
		
		if(isset($_GET['show_balance'])){			
			$total_unused_units=$this->general_model->get_total_user_balance();
			$cheapglobalsms_balance_units=0;
			$info_resp=$this->general_model->_cheapglobalsms_get_balance($configs);
			if(is_string($info_resp))$data['Error']=$info_resp;
			else $cheapglobalsms_balance_units=$info_resp['balance'];			
			$reserve=$cheapglobalsms_balance_units-$total_unused_units;
			
			if($reserve>1000)$res_color='';
			elseif($reserve>0)$res_color='color:#f90';
			else $res_color='color:#f00';
			
			$data['Success']="Total Unused : ".number_format($total_unused_units)." Units".
			"<br/>CheapGlobalSMS Balance: ".number_format($cheapglobalsms_balance_units)." Units".
			"<br/><br/>Total Reserve: <strong style='$res_color;' >".number_format($$reserve)."</strong>";
		}
		
		
		if(isset($_POST['show_dues'])){
			$start_date=$this->_valid_date($this->input->post('start_date'));
			$start_time=strtotime($start_date);
			
			$end_date=$this->_valid_date($this->input->post('end_date'));
			$end_time=strtotime($end_date)+86400-1;
						
			$query=$this->db->query("SELECT SUM(sms_units) as total_units,SUM(net_amount_fiat) as total_amount FROM "._DB_PREFIX_."transactions WHERE status=1 AND time>$start_time AND time<$end_time ");
			$sum=$query->row();
			$total_units=$sum->total_units;
			
			$cheapglobalsms_price_per_unit=empty($configs['cheapglobalsms_price_per_unit'])?1.75:$configs['cheapglobalsms_price_per_unit'];
			
			$cheapglobalsms_total=$total_units*$cheapglobalsms_price_per_unit;
			$gross_gain=$sum->total_amount-$cheapglobalsms_total;
			
			$gross_vat=$sum->total_amount*0.05;
			$cheapglobalsms_vat=$cheapglobalsms_total*0.05;

			$my_vat=$gross_vat-$cheapglobalsms_vat;
			
			$tithe=$gross_gain*0.1;
			
			$my_profit=$gross_gain-$tithe;
			
			$start_date_str=date('D, jS M. Y',$start_time);
			$end_date_str=date('D, jS M. Y',$end_time);
			
			$data['Success']="FOR THE PERIOD OF: <strong>$start_date_str</strong> <i>TO</i> <strong>$end_date_str</strong><br/>
			<div class='list-group'>
				<div class='list-group-item'>
					CheapGlobalSMS Total: ".number_format($cheapglobalsms_total,2)." "._CURRENCY_CODE_."  ($total_units UNITS)
				</div>
				<div class='list-group-item'>
					My Vat: <strong>".number_format($my_vat,2)." "._CURRENCY_CODE_."</strong>
				</div>
				<div class='list-group-item'>
					Gross Gain: ".number_format($gross_gain,2)." "._CURRENCY_CODE_."
				</div>
				<div class='list-group-item'>
					Tithe: <strong>".number_format($tithe,2)." "._CURRENCY_CODE_."</strong>
				</div>
				<div class='list-group-item'>
					My Profit: <strong>".number_format($my_profit,2)." "._CURRENCY_CODE_."</strong>
				</div>
			</div>";
			
		}
		
		$data['presetData']=$presetData;		
		$this->load_admin_views('website_configuration.php',$data);
	}

	
	public function admin_manage_prices(){
		$this->check_admin_logged_in();
		$data['current_tab']='settings';
		$data['page_title']="CheapGlobalSMS prices | Admin Panel";
		$data['configs']=$this->general_model->get_configs();
		$data['templates']=array();
		$path='application/views/templates';
		
		if($this->input->post('default_currency_to_ngn')){
			$default_currency_to_ngn=floatval($this->input->post('default_currency_to_ngn',true));
			$this->general_model->update_config('default_currency_to_ngn',$default_currency_to_ngn);
			$data['configs']['default_currency_to_ngn']=$default_currency_to_ngn;
		}
		
		if($this->input->post('cheapglobalsms_price_per_unit')){
			$cheapglobalsms_price_per_unit=floatval($this->input->post('cheapglobalsms_price_per_unit',true));
			$this->general_model->update_config('cheapglobalsms_price_per_unit',$cheapglobalsms_price_per_unit);
			$data['configs']['cheapglobalsms_price_per_unit']=$cheapglobalsms_price_per_unit;
		}


		if($this->input->post('update_coverage_list')){
			$coverage=$this->general_model->_curl_json('https://cheapglobalsms.com/coverage_list?action=export_json');
			if(!empty($coverage['error']))$data['Error']=$coverage['error'];
			else {
				$this->general_model->update_coverage_list($coverage);
				$data['Success']='Coverage list reloaded';
			}
		}
		

		if($this->input->post('save')!=''){
			$num_prices=$this->input->post('num_prices');
			$prices=array();

			for($num=1;$num<=$num_prices;$num++){
				$price_i=$this->input->post("price_$num");
				$price_i=strtolower(trim($price_i));
				if(!is_numeric($price_i))continue;
				$prices[$price_i]=
							array(
								'price'=>$price_i,
								'min_units'=>$this->input->post("min_units_$num"),
								'bonus_units'=>$this->input->post("bonus_units_$num"),
								);
			}
			$prices=array_values($prices);
			if(!empty($prices)){
				$this->general_model->update_prices($prices);
				$data['Success']="prices updated successfully.";
			}
			else $data['Error']="No valid price supplied.";
		}
		$prices=$this->general_model->get_prices();		
		$data['prices_json']=json_encode($prices);
		$this->load_admin_views('manage_prices.php',$data);
	}
	

	
	public function admin_manage_users($temp_user_id=0){
		$this->check_admin_logged_in();				
		
		if($this->input->post('flag_user_id')){
			$new_flag_level=$this->input->post('flag_level');
			$this->general_model->update_user_data($this->input->post('flag_user_id'),'flag_level',$new_flag_level);
			$data['Success']="Flag level updated to $new_flag_level";
		}
		
		if($this->input->get('delete_verifiation_file')){
			$temp_user_id=$this->input->get('delete_verifiation_file');
			$temp_user=$this->general_model->get_user($temp_user_id,'user_id');
			if(empty($temp_user['verification_file']))$data['Error']='Verfication ecord not found';
			else {
				@unlink($temp_user['verification_file']);
				$this->general_model->update_user_data($temp_user_id,'verification_file','');
				$mail_msg="Dear {$temp_user['firstname']}\r\n\r\nPlease note that your submitted verification document has been removed from the server.\r\nYou may like to re-upload a genuine/valid document for verification.\r\n\r\nRegards";
				$this->general_model->send_email($temp_user['email'],'Your verification document was rejected',"");
				$data['Success']='Verification file has been removed';
			}
		}
		
		
		if($this->input->post('topup_user_id')){
			$topup_user_id=$this->input->post('topup_user_id')*1;
			$topup_units=$this->input->post('topup_units')*1;
			if($topup_units!=0&&$topup_user_id>0){
				$this->general_model->charge_balance($topup_units*-1,$topup_user_id);
				
				if($this->input->post('log_transaction')){
					$time=time();
					$transaction=array(
						'user_id'=>$topup_user_id,
						'time'=>$time,
						'transaction_reference'=>$time,
						'amount'=>0,
						'type'=>1,
						'status'=>1,
						'currency_code'=>'',
						'details'=>"System updated SMS balance by $topup_units units",								
						'payment_method'=>'free_checkout',
						'sms_units'=>$topup_units,
					);
					
					$this->general_model->insert_transaction($transaction);
				}
				$data['Success']="Balance altered by $topup_units units";
			}
		}
		
		$f_user_id=empty($this->input->get('f_user_id'))?$temp_user_id:$this->input->get('f_user_id');
		
		$data['filter']=array(
			'country'=>$this->input->get('country'),
			'search_term'=>$this->input->get('q'),
			'order_by'=>$this->input->get('o'),
			'user_id'=>$f_user_id
		);
		$num=$this->general_model->get_users(true,$data['filter']);
		if(empty($num))$data['Warning']="No record found.";
		else{
			extract($this->_analyse_pagination($num,$data));
			$data['users']=$this->general_model->get_users(false,$data['filter']);
		}
		$data['countries']=$this->general_model->get_countries();
		$data['page_title']="Users | Admin Panel";		
		$this->load_admin_views('users.php',$data);
	}	
	
	public function admin_sms_log(){
		$this->check_admin_logged_in();
		
		$q=$this->input->get('q');
		$stage=$this->input->get('stage');
		$start_date=$this->input->get('sd');
		$end_date=$this->input->get('ed');
		$email=$this->input->get('email',true);
		$user_id=$this->input->get('user_id',true);
		
		$perpage=@$_GET['perpage'];
		
		if(!is_numeric($perpage))$perpage=25;
		elseif($perpage<1)$perpage=1;
		$result_action=$this->input->get('result_action');
		
		$data['filter']=array(
			'email'=>$email,'perpage'=>$perpage,'start_date'=>$start_date,'end_date'=>$end_date,'stage'=>$stage,'search_term'=>$q,'result_action'=>$result_action,
			'deleted'=>$this->input->get('deleted',true)
		);
		
		
		
		if($user_id)
		{
			$data['filter']['user_id']=$user_id;
			$user=$this->general_model->get_user($user_id,'user_id');
			if(empty($user))$data['Error']="Customer user_id $user_id not found.";
			else $data['filter']['email']=$user['email'];
		}
		elseif($email)
		{
			$user=$this->general_model->get_user($email,'email');
			if(empty($user))$data['Error']="Customer email $email not found.";
			else $data['filter']['user_id']=$user['user_id'];
		}
		
		if($result_action=='calculate_total_units'){
			$resp=$this->general_model->run_batch_action($data['filter'],'get_total_units');
			$data['Success']="Total results found: {$resp['total']} messages. Total SMS units for the specified result filter: {$resp['total_units']} units";
		}
		else{
			$num=$this->general_model->get_sms_log(true,$data['filter']);		
			if(empty($num))$data['Warning']="No record found.";
			else
			{
				extract($this->_analyse_pagination($num,$data));
				$data['messages']=$this->general_model->get_sms_log(false,$data['filter']);
			}
		}
		
		$data['page_title']="SMS Log | Admin Panel";		
		$this->load_admin_views('sms_log.php',$data);
	}
	
	public function admin_error_log($action="list"){
		$this->check_admin_logged_in();		
		$data['action']=$action;
		if($action=='clear'){
			$this->general_model->clear_error_log();
			$data['Success']="The log has been cleared.";
		}
		
		$do_batch_action=intval($this->input->get('batch_action',true));
		if($this->input->get('dispatch_suspended_batch')){
			$batch_id=$this->input->get('dispatch_suspended_batch',true);
			$resp=$this->general_model->dispatch_suspended_batch($batch_id,$do_batch_action);
			if(is_string($resp))$data['Error']=$resp;
			elseif($resp)$data['Success']="Message batch $batch_id dispatched.";
		}
		
		if($this->input->get('cancel_suspended_batch')){
			$batch_id=$this->input->get('cancel_suspended_batch',true);
			$resp=$this->general_model->cancel_suspended_batch($batch_id,$do_batch_action);
			if(is_string($resp))$data['Error']=$resp;
			elseif($resp)$data['Success']="Message batch $batch_id rejected.";
		}
		
		if($this->input->get('penalize_suspended_batch')){
			$batch_id=$this->input->get('penalize_suspended_batch',true);
			$resp=$this->general_model->penalize_suspended_batch($batch_id,$do_batch_action);
			if(is_string($resp))$data['Error']=$resp;
			elseif($resp)$data['Success']="Message batch $batch_id discarded";
		}
		
		$data['filter']=array(
			'type'=>$this->input->get('type'),
			'search_term'=>$this->input->get('q')
			);

		$num=$this->general_model->get_errors(true,$data['filter']);
		if(!empty($num)){
			extract($this->_analyse_pagination($num,$data));
			$data['errors']=$this->general_model->get_errors(false,$data['filter']);
		}
		$data['action']=$action;		
		$data['page_title']="Log | Admin Panel";		
		$this->load_admin_views('errors.php',$data);
	}
	
	public function admin_whitelisted_messages(){
		$this->check_admin_logged_in();
		
		if($this->input->get('delete_whitelist')){
			$whitelisted_sms_id=$this->input->get('delete_whitelist',true);
			$resp=$this->general_model->delete_whitelisted_sms($whitelisted_sms_id);
			if(is_string($resp))$data['Error']=$resp;
			elseif($resp)$data['Success']="White-listed message removed";
		}
		
		$data['filter']=array(
			'user_id'=>$this->input->get('user_id'),
			'email'=>$this->input->get('email'),
			'search_term'=>$this->input->get('q')
			);

		$num=$this->general_model->get_whitelisted_messages(true,$data['filter']);
		if(!empty($num)){
			extract($this->_analyse_pagination($num,$data));
			$data['whitelisted_messages']=$this->general_model->get_whitelisted_messages(false,$data['filter']);
		}
		$data['page_title']="White-listed Messages | Admin Panel";		
		$this->load_admin_views('whitelisted_messages.php',$data);
	}
	
	
	function _admin_complete_transaction($data,$transaction,array $payment_info=array(),$teller_info='',$admin_login_data=''){
		$json_info=$this->general_model->get_json($transaction['json_info']);
		$trans_ref=$transaction['transaction_reference'];
		
		if(!empty($payment_info['amount_paid'])&&is_numeric($payment_info['amount_paid'])){
			if(empty($data['configs']))$data['configs']=$configs=$this->general_model->get_configs();
			else $configs=$data['configs'];
			
			$amount_paid=$payment_info['amount_paid'];
			$ccode=@$payment_info['currency_code'];
			if($ccode!='USD'&&$ccode!=_CURRENCY_CODE_)$ccode=$transaction['currency_code'];
			$currencies=$this->general_model->get_currencies();
			
			//$currency_value=1; for _CURRENCY_CODE_
			$currency_value=$currencies[$ccode]['value'];					
			$payment_method=$transaction['payment_method'];
			
			//remove gateway charges
			$charges=$amount_paid*0.01*$configs[$payment_method.'_charge'];
			$charge_cap=$configs[$payment_method.'_charge_cap'];
			if($charge_cap>0&&$charge_cap<$charges)$charges=$charge_cap;
			if(!empty($payment_info['gateway_charges']))$charges+=$payment_info['gateway_charges'];
			$variable_charges=$charges;
			
			if($payment_method=='bank_deposit'&&ceil($amount_paid)<950)$apply_fixed_charge=false;
			else $apply_fixed_charge=true;

			if($apply_fixed_charge&&!empty($configs[$payment_method.'_charge_fixed'])){
				$fixed_charge=$configs[$payment_method.'_charge_fixed']*$currencies[$ccode]['value'];
				$charges+=$fixed_charge;
			}
			else $fixed_charge=0;
			
			$net_amount_paid=$amount_paid-$charges;
			
			//remove tax_amount
			$tax_amount=0;
			
			if(!empty($configs['tax_percent'])){
				$percent_plus_tax=100+$configs['tax_percent'];
				$tax_amount=($net_amount_paid/$percent_plus_tax)*$configs['tax_percent'];
				$net_amount_paid-=$tax_amount;
			}
			
			//calculate equivalent units.
			$is_reseller=$this->general_model->get_user_data($transaction['user_id'],'reseller_account');
			$new_sms_units=$this->general_model->sms_price_to_units($net_amount_paid,$currency_value,!empty($is_reseller));
				
			$json_details=$this->general_model->get_json($transaction['json_details']);					
			$new_json_details=array('sms_units'=>$new_sms_units,'original_amount'=>$net_amount_paid,'gateway_charges'=>$variable_charges,
			'payment_method_fixed_charges'=>$fixed_charge,'tax_amount'=>$tax_amount,'tax_percent'=>$configs['tax_percent'],'approved_amount'=>$amount_paid);
			$json_details=array_merge($json_details,$new_json_details);
			
			//then re-update transaction
			$update_data=array('json_details'=>json_encode($json_details),'amount'=>$amount_paid);
			$update_data['sms_units']=$new_sms_units;
			$update_data['net_amount_fiat']=$net_amount_paid/$currencies[$ccode]['value'];
					
			$this->general_model->update_transaction($transaction['transaction_reference'],$update_data,false);
			
			$sstr=($fixed_charge==0)?"":"Payment Portal Fixed Charges: $fixed_charge $ccode<br/>";
			
			$json_info['response_description']="
			Payment Analysis.<br/>
			Gross amount paid: $amount_paid $ccode<br/>
			TAX/VAT: ".number_format($tax_amount,2)." $ccode ({$configs['tax_percent']}%)<br/>
			Payment Portal Charges: ".number_format($variable_charges,2)." $ccode<br/>
			$sstr
			NET Amount Paid: ".number_format($net_amount_paid,2)." $ccode<br/>
			SMS CREDITS: $new_sms_units Units";
		}
		
		if(!empty($teller_info))$json_info['info']=$teller_info;
		if(!empty($admin_login_data)){
		$json_info['admin_email']=$admin_login_data['email'];
		$json_info['admin_id']=$admin_login_data['admin_id'];
		}

		$trans_resp=$this->general_model->change_transaction_status($trans_ref,1,$json_info);

		if(!empty($admin_login_data)){
			if(!empty($trans_resp['user']['credit_notification'])&&!empty($trans_resp['user']['balance']))$this->_send_sms($trans_resp['user']['user_id'],array(0=>$trans_resp['user']),$trans_resp['sms_msg'],'CGSMS'); 
		}
		
		$data['Success']="Transaction status has now been successfully changed";
	
		return $data;
	}
	
	
	public function admin_transaction_history(){
		$this->check_admin_logged_in();
		$payment_method=$this->input->get('pm');
		$status=$this->input->get('s');
		$start_date=$this->input->get('sd');
		$end_date=$this->input->get('ed');
		$email=$this->input->get('email');
		if($this->input->post('complete')!='')
		{			
			$admin_login_data=$this->check_admin_logged_in(3);
			$teller_info=$this->input->post('teller_info',true);
			$trans_ref=$this->input->post('confirm_trans',true);
			$transaction=$this->general_model->get_transaction($trans_ref);
			if(empty($transaction))$data['Error']='Transaction record not found.';
			elseif($transaction['status']==1)$data['Error']='This transaction has already been completed.';
			else
			{				
				$amount_paid=$this->input->post('amount_transfered',true);
				$json_info=$this->general_model->get_json($transaction['json_info']);
				if(!empty($amount_paid)&&is_numeric($amount_paid))
				{
					$data['configs']=$configs=$this->general_model->get_configs();
					
					$currency_value=1;
					$ccode=$transaction['currency_code'];
					$currencies=$this->general_model->get_currencies();
					$currency_value=$currencies[$ccode]['value'];					
					$payment_method=$transaction['payment_method'];
					
					//remove gateway charges
					$charges=$amount_paid*0.01*$configs[$payment_method.'_charge'];
					$charge_cap=$configs[$payment_method.'_charge_cap'];
					if($charge_cap>0&&$charge_cap<$charges)$charges=$charge_cap;
					$variable_charges=$charges;
					
					if($payment_method=='bank_deposit'&&ceil($amount_paid)<950)$apply_fixed_charge=false;
					else $apply_fixed_charge=true;

					if($apply_fixed_charge&&!empty($configs[$payment_method.'_charge_fixed'])){
						$fixed_charge=$configs[$payment_method.'_charge_fixed']*$currencies[$ccode]['value'];
						$charges+=$fixed_charge;
					}
					else $fixed_charge=0;
					
					$net_amount_paid=$amount_paid-$charges;
					
					//remove tax_amount
					$tax_amount=0;
					
					if(!empty($configs['tax_percent'])){
						$percent_plus_tax=100+$configs['tax_percent'];
						$tax_amount=($net_amount_paid/$percent_plus_tax)*$configs['tax_percent'];
						$net_amount_paid-=$tax_amount;
					}
					
					//calculate equivalent units.
					$new_sms_units=$this->general_model->sms_price_to_units($net_amount_paid,$currency_value);
					
					$json_details=$this->general_model->get_json($transaction['json_details']);					
					$new_json_details=array('sms_units'=>$new_sms_units,'original_amount'=>$net_amount_paid,'gateway_charges'=>$variable_charges,
					'payment_method_fixed_charges'=>$fixed_charge,'tax_amount'=>$tax_amount,'tax_percent'=>$configs['tax_percent'],'approved_amount'=>$amount_paid);
					$json_details=array_merge($json_details,$new_json_details);
					
					//then re-update transaction
					$update_data=array('json_details'=>json_encode($json_details),'amount'=>$amount_paid);
					$update_data['sms_units']=$new_sms_units;
					$update_data['net_amount_fiat']=$net_amount_paid/$currencies[$ccode]['value'];
							
					$this->general_model->update_transaction($transaction['transaction_reference'],$update_data,false);
					
					$sstr=($fixed_charge==0)?"":"Payment Portal Fixed Charges: $fixed_charge $ccode<br/>";
					
					$json_info['response_description']="
					Payment Analysis.<br/>
					Gross amount paid: $amount_paid $ccode<br/>
					TAX/VAT: ".number_format($tax_amount,2)." $ccode ({$configs['tax_percent']}%)<br/>
					Payment Portal Charges: ".number_format($variable_charges,2)." $ccode<br/>
					$sstr
					NET Amount Paid: ".number_format($net_amount_paid,2)." $ccode<br/>
					SMS CREDITS: $new_sms_units Units";
				}
				if(!empty($teller_info))$json_info['info']=$teller_info;
				else foreach($this->general_model->payment_bank_params as $pbp)$json_info[$pbp]=$this->input->post($pbp,true); 
				$json_info['admin_email']=$admin_login_data['email'];
				$json_info['admin_id']=$admin_login_data['admin_id'];

				$trans_resp=$this->general_model->change_transaction_status($trans_ref,1,$json_info);

				if(!empty($trans_resp['user']['credit_notification'])&&!empty($trans_resp['user']['balance']))$this->_send_sms($trans_resp['user']['user_id'],array(0=>$trans_resp['user']),$trans_resp['sms_msg'],'CGSMS'); 
				$data['Success']="Transaction status has now been successfully changed";
			}
		}
		
		$filter=array('email'=>$email,'payment_method'=>$payment_method,'status'=>$status,'start_date'=>$start_date,'end_date'=>$end_date);
		$num=$this->general_model->get_transactions(true,$filter);
	
		if(empty($num))$data['Warning']="No transaction record found.";
		else{
			$perpage=10;
			$totalpages=ceil($num/$perpage);
			$p=empty($_GET['p'])?1:$_GET['p'];
			if($p>$totalpages)$p=$totalpages;
			if($p<1)$p=1;
			$offset=($p-1) *$perpage;
			$data['p']=$p;
			$data['totalpages']=$totalpages;
			$data['total']=$num;
			$filter['offset']=$offset;
			$filter['perpage']=$perpage;			
			$data['transactions']=$this->general_model->get_transactions(false,$filter,true);			
		}
		$data['filter']=$filter;			
		$data['page_title']="Transaction History | Admin Panel";
		$this->load_admin_views('transaction_history.php',$data);
	}
	
	public function admin_currencies(){
		$this->check_admin_logged_in();
		$data['page_title']="Currencies | Admin Panel";
		$data['current_tab']='settings';
		$num_currencies=$this->input->post('num_currencies');
		if($num_currencies!=''){
			$currencies=array();

			for($num=1;$num<=$num_currencies;$num++)
			{
				$currency_i=$this->input->post("currency_$num");
				if(empty($currency_i))continue;
				$currencies[$currency_i]=
					array(
						'currency'=>$currency_i,
						'currency_title'=>$this->input->post("currency_title_$num",true),
						'iso_code'=>$this->input->post("iso_code_$num",true),
						'symbol'=>$this->input->post("symbol_$num",true),
						'decimal_places'=>$this->input->post("decimal_places_$num",true),
						'value'=>$this->input->post("value_$num",true),
						'enabled'=>$this->input->post("enabled_$num",true),
						);
			}

			if(!empty($currencies))
			{
				$this->general_model->replace_currencies($currencies);
				$data['Success']="Currencies updated successfully.";
			}
			else $data['Error']="No valid currency supplied.";
		}
		$data['currencies']=$this->general_model->get_currencies(true);
		$this->load_admin_views('currencies.php',$data);		
	}
	
	
	public function admin_payment_gateways(){
		$this->check_admin_logged_in();
		ini_set('error_reporting',E_WARNING|E_ERROR|E_PARSE);
		$data['page_title']="Payment Gateways | Admin Panel";		
		$data['current_tab']='settings';
		$data['currencies']=$this->general_model->get_currencies();
		$presetData=$this->general_model->get_configs('',true);

		if(empty($presetData)||$this->input->post('save_configs')!=''){
			foreach($this->general_model->payment_gateway_params as $pgpname => $pgp)
			{
				$params_keys[]="{$pgpname}_currencies";
				$params_keys[]="{$pgpname}_enabled";
				$params_keys[]="{$pgpname}_demo";
				$params_keys[]="{$pgpname}_label";
				$params_keys[]="{$pgpname}_charge";
				$params_keys[]="{$pgpname}_charge_fixed";
				$params_keys[]="{$pgpname}_charge_cap";
				$params_keys[]=$pgp['merchant_id'];
				if(!empty($pgp['uid']))$params_keys[]=@$pgp['uid'];
				$params_keys[]=$pgp['key'];
			}
			$params_keys[]='gtpay_direct_webpay';
			$params_keys[]='free_sms';
			$params_keys[]='tax_percent';
			foreach($params_keys as $pkn){
				if(empty($pkn))continue;
				$presetData[$pkn]=is_array($_POST[$pkn])?implode(',',$_POST[$pkn]):$this->input->post($pkn);
			}
			if($this->input->post('save_configs')!='')
			{
				$this->general_model->update_configs($presetData);
				$data['Success']="Record Updated";
			}
		}

		$data['presetData']=$presetData;		
		$this->load_admin_views('payment_gateways.php',$data);
	}
	
	function _clean_group_name($group_name){
		return str_replace(array(',',"'",'"'),'',$group_name);
	}
	

function _replace_placeholders($template,$values)
{
	$find=array();
	$replace=array();
	
	foreach($values as $rk=>$rv){
		$find[]="[$rk]";
		$replace[]=$rv;
	}
	
	return str_ireplace($find,$replace,$template);
}

	function admin_send_email(){
		ignore_user_abort(true);
		set_time_limit(0);
		$this->check_admin_logged_in();	
		
		/*
		if(isset($_GET['custom_command'])){
			$total=$this->_mail_ngcontacts();
			$data['Success']="Mail scheduled to $total ngcontacts.";
		}
		*/
		
		$data['pref_recipients']=$this->input->get('recp');
	
		if($this->input->post('send_email')!=''){
			$rules=
				array(
					 array('field'=> 'from','label'=> "sender's email",'rules'=>'trim|max_length[225]'),
					 array('field'=> 'from_name','label'=>"sender's name",'rules'=>'trim|max_length[60]'),
					 array('field'=> 'recipient_type','label'=>'recipient type','rules'=> 'trim|required'),
					 array('field'=> 'subject','label'=> 'subject','rules'=> 'trim|required|max_length[140]'),
					 array('field'=> 'message','label'=> 'message','rules'=> 'trim|required'),
					 array('field'=> 'date','label'=> 'date','rules'=> 'trim'),
					 array('field'=> 'time','label'=> 'time','rules'=> 'trim'),
				);	
			if($this->input->post('recipient_type')=='specified')
					$rules[]= array('field'=> 'recipient_emails','label'=> 'recipient emails','rules'=> 'trim|required');
			$this->form_validation->set_rules($rules);
			if(!$this->form_validation->run())$data['Error']=validation_errors();
			else
			{
				$from=$this->input->post('from');
				$from_name=$this->input->post('from_name');
				$subject=$this->input->post('subject');
				$message=$this->input->post('message');
				$recipient_type=$this->input->post('recipient_type');
				$dd=$this->input->post('date');
				$dt=$this->input->post('time');
				$ds=trim("$dd $dt");
				$tnow=time();
				$diff=0;
				if(empty($ds))$time=$tnow;
				else
				{
					$time=strtotime($ds);
					$diff=$time-$tnow;
					if($diff<=0)$time=$tnow;
				}
				$extras=array('from'=>$from,'from_name'=>$from_name);
				$num=0;
				$mails=array();
				if($recipient_type=='specified')
				{
					$invalids=array();
					$recipients=$this->input->post('recipient_emails');
					if(empty($recipients))$recipients=array();
					else $recipients=preg_split("/[\s,]+/",$recipients);	
					
					
					if(!empty($_FILES['recipient_file']['name'])&&!empty($_FILES['recipient_file']['size'])){
						$file_name=$_FILES['recipient_file']['name'];
						$file_temp_name=$_FILES['recipient_file']['tmp_name'];
						$file_size=$_FILES['recipient_file']['size'];
						$file_type=$_FILES['recipient_file']['type'];
						
						$file_contents=trim(file_get_contents($file_temp_name));

						if(!empty($file_contents)){
							if($file_type=='text/csv'||$file_type=='text/plain')
							{
								$recp=preg_split("/[\s,]+/",$file_contents);
								$recipients=array_merge($recipients,$recp);
								$recipients=array_unique($recipients);
							}
							elseif($file_type=='application/json'||$file_type=='application/octet-stream')
							{
								$json_array=$this->general_model->get_json($file_contents);

								foreach($json_array as $json)
								{
									$find=array();
									$replace=array();
									
									foreach($json as $rk=>$rv)
									{
										$find[]="[$rk]";
										$replace[]="$rv";
									}
									
									$nmessage=str_ireplace($find,$replace,$message);
									$mails[]=array('email'=>$json['email'],'subject'=>$subject,'message'=>$nmessage,'time'=>$time,'sender'=>$from,'sender_name'=>$from_name);
						
								}
							}
						}
					}
					
					foreach($recipients as $r_email){
						if($this->general_model->valid_email($r_email)){
							$mails[]=array('email'=>$r_email,'subject'=>$subject,'message'=>$message,'time'=>$time,'sender'=>$from,'sender_name'=>$from_name);
							$num++;
						}
						else $invalids[]=$r_email;				
					}
					
					
					if(!empty($invalids)){
						$inv_count=count($invalids);
						$inv_str=implode(',',$invalids);
						
						$data['Error']="Mail not sent to $inv_count invalid email address $inv_str.";
					}
				}
				else {
					$filters=array('country_code'=>@$_POST['country_code']);

					$recipients=$this->general_model->admin_filter_recipients($filters);

					foreach($recipients as $recipient){
						$mails[]=array('email'=>$recipient['email'],'subject'=>$subject,'message'=>$this->_replace_placeholders($message,$recipient),'time'=>$time,'sender'=>$from,'sender_name'=>$from_name);
					}
				}

				$num=count($mails);
				if($num!=0){
					$resp_str=($num==1)?$mails[0]['email']:"$num recipients";
					$temp=0;
					if($diff<=0&&$num<10) //time is past, or present
					{						
						foreach($mails as $mail){
							$this->general_model->send_email($mail['email'],$mail['subject'],$mail['message'],$extras,'');
							if($num>5)sleep(10);
						}
						$data['Success']="Message sent to $resp_str";
					}
					else {
						$this->general_model->send_scheduled_emails($mails);
						$time_s=date('D, jS M. Y g:i a',$time);
						$data['Success']="Message submitted for delivery to $resp_str by $time_s.";
					}
				}
				elseif(empty($data['Error']))$data['Error']="No recipient found.";
			}
		}
	
		$data['countries']=$this->general_model->get_countries();
		$data['page_title']="Send Email | Admin Panel";		
		$this->load_admin_views('send_email.php',$data);
	}
	
	
	function callback_processor($section=''){		
		$resp=null;
		
		if(isset($_POST['result'])){
			$response_json=@json_decode($_POST['result']);
			if($response_json)$resp=$this->general_model->_cheapglobalsms_process_delivery_reports($response_json);
			else $resp="unable to decode result from posted result: ".json_encode($_POST['result']);
		}
		else $resp="unable to decode result from post_data: ".json_encode($_POST);
		
		echo $resp;
	}
	
	public function ajax_processor(){
		$action = empty($_REQUEST['action'])?'':$_REQUEST['action'];

		if($action=='suggest_user_email'){
			$val=$this->input->get('val');
			$suggestions=$this->general_model->get_email_suggestions($val);
			echo json_encode($suggestions);
		}
		elseif($action=='suggest_contact'){
			$val=$this->input->get('val');
			$user_id=$this->general_model->get_login_data('user_id');
			if(empty($user_id))$suggestions=array();
			else $suggestions=$this->general_model->get_contact_suggestions($val,$user_id);
			echo json_encode($suggestions);
		}
		elseif($action=='commit_transaction'){ echo $this->_commit_transaction(); }
		else {
			$response=array('error'=>"Invalid command $action");
			echo json_encode($response);
		}
	}
	
	
	function _analyse_pagination($num,array $data=array()){
		$filter=(empty($data['filter']))?array():$data['filter'];
		if(!empty($filter['perpage']))$perpage=$filter['perpage'];
		else $perpage=10;
		$totalpages=ceil($num/$perpage);
		if(isset($filter['p']))$p=empty($filter['p'])?1:$filter['p'];
		else $p=empty($_GET['p'])?1:$_GET['p'];

		if($p>$totalpages)$p=$totalpages;
		if($p<1)$p=1;
		$offset=($p-1) *$perpage;
		$data['total']=$num;
		$data['p']=$p;
		$data['totalpages']=$totalpages;
		$filter['offset']=$offset;
		$filter['perpage']=$perpage;
		$data['filter']=$filter;
		return array('data'=>$data);
	}

	
	/**
	*
	*@param $first_row_is_label 0 = none associative, 1=raw associative, 2=associative cleaned keys.
	*@param $sheet_index 0 = fetch all sheets combined, -1 => fetch all sheets and return an array of sheets.
	*
	*/
	function _read_excel($_file,$first_row_is_label=0,$sheet_index=0,$skip_empty=true) {
		require('libraries/spreadsheet_reader/php_excel_reader/excel_reader2.php');
		require('libraries/spreadsheet_reader/SpreadsheetReader.php');
	
		$file_name=$_file['name'];
		$file_temp_name=$_file['tmp_name'];
		$file_size=$_file['size'];
		$file_type=$_file['type'];
		$time=time();
		$rand=mt_rand('100','999');
		$exploded=explode('.',$file_name);
		$savename="{$time}_$rand.".end($exploded);
		$full_filename='tmp/'.$savename;
		//$full_filename='tmp/'.$file_name;
			
		try{
			move_uploaded_file($file_temp_name,$full_filename);
			$Spreadsheet = new SpreadsheetReader($full_filename);
			unlink($full_filename);
			$Sheets = $Spreadsheet -> Sheets();
			$sheet=0;
			$outs = array();
			$col_keys=array();
			foreach ($Sheets as $Index => $Name) //$Index may not be integer
			{
				if($sheet_index>0&&$sheet_index!=$sheet){ $sheet++; continue; }
				$Spreadsheet -> ChangeSheet($Index);				

				foreach ($Spreadsheet as $Key => $Row)
				{
					if(empty($Row))continue;
					if(!array_filter($Row))continue;
					
					//if(empty($Row)&&$skip_empty)continue;
					//if (!$Row)$Row=array();

					if($first_row_is_label&&empty($col_keys)){
						if($first_row_is_label==2){
							foreach($Row as $rk=>$rv)$col_keys[$rk]=str_replace(' ','_',trim(strtolower($rv)));
						}
						else $col_keys=$Row;
						continue;
					}

					if($first_row_is_label){
						$Row2=array();
						foreach($Row as $rk=>$rv){
							if(empty($col_keys[$rk]))continue;
							$tempkey=$col_keys[$rk];
							$Row2[$tempkey]=$rv;
						}
						
						$Row=$Row2;
					}
					
					if($sheet_index==-1)$outs[$sheet_index][]=$Row;
					else $outs[]=$Row;
				}
				$sheet++;
				$col_keys=array(); //empty it for the next sheet.
				if($sheet_index>0)break;
			}
			return $outs;
		}
		catch(Exception $e){
			@unlink($full_filename); 
			return "Error: ".$e->getMessage();
		}
	}
	
	function _cron_breath($last_sync_time='',$cron_id='cron_breathing.txt'){
		date_default_timezone_set('UTC');
		$sync_time=@file_get_contents($cron_id);
		$dt_now=date('y-m-d g:i a');
		if($last_sync_time==''&&!empty($sync_time)){
			$diff=time()-$sync_time;
			$grace=12*60; //12 minutes
			if($diff<$grace)
			{
				$str="DYING: $dt_now => Last breathe was $diff seconds ago.";
				//$this->general_model->_log_error($str);
				die($str);
			}
		}
		if($last_sync_time!=''&&$sync_time!=$last_sync_time){
			$str="TERMINATING: $dt_now => While stalling, another cron has started. ($sync_time!=$last_sync_time)";
			$this->general_model->_log_error($str);
			die($str);
		}
		$new_sync_time=time();
		file_put_contents($cron_id,$new_sync_time);
		return $new_sync_time;
	}
	
	function run_mail_queue()
	{
		ignore_user_abort(true);
		set_time_limit(0);
		//if(!$this->input->is_cli_request())die('DIRECT WEB ACCESS DENIED');
		$sync_file='cron_email_sync.txt';
		$sync_time=$this->_cron_breath('',$sync_file);
		$configs=$this->general_model->get_configs();
		$cron_run_count=0;
	
		$cron_mails_per_minute=$configs['cron_mails_per_minute'];
		if($cron_mails_per_minute<=0||$cron_mails_per_minute>10)$cron_mails_per_minute=2;
		
		$sleep_duration=60/$cron_mails_per_minute;
	
		while(true){
			$cron_run_count++; //if($cron_run_count>1)break;
			$sync_time=$this->_cron_breath($sync_time,$sync_file);
			$mails=$this->general_model->cron_obtain_scheduled_mails();
			$sync_time=$this->_cron_breath($sync_time,$sync_file);
			$updates=array();
			$mcount=count($mails);
			if($mcount>0)file_put_contents('mail_sending_log.txt',"About sending to: $mcount");
			$i=0;
			
			foreach($mails as $mail)
			{
				$extras=array('from'=>$mail->sender,'from_name'=>$mail->sender_name,'debug'=>true);

				$resp=$this->general_model->send_email($mail->email,$mail->subject,$mail->message,$extras,'');
				sleep($sleep_duration);
				$new_status=-1; $info='Message could not be sent';
				if(is_string($resp))$info=$resp;
				elseif($resp)
				{
					$new_status=2;
					$info='Message sent';
				}

				$i++;
				file_put_contents('mail_sending_log.txt',"$i: ".$mail->email);

				$updates[]=array('scheduled_mail_id'=>$mail->scheduled_mail_id,'time_sent'=>time(),'status'=>$new_status,'info'=>$info);
				if(count($updates)>10)
				{
					$this->general_model->cron_conclude_scheduled_mails($updates);
					$updates=array();
				}
				$sync_time=$this->_cron_breath($sync_time,$sync_file);
			}

			if(empty($mails))sleep(60);
			elseif(!empty($updates)) $this->general_model->cron_conclude_scheduled_mails($updates);
			echo "<br/>--- CRON PROCESS COMPLETED: $cron_run_count ---<br/>";
		}
	}
	
	
	function run_sms_cron(){
		set_time_limit(0);
		ignore_user_abort(true);
		
		$sync_time=$this->_cron_breath();
		$configs=$this->general_model->get_configs();
		$cron_run_count=0;

		while(true)
		{	
			$cron_run_count++;
			$sync_time=$this->_cron_breath($sync_time);
			$batches=$this->general_model->get_due_sms_batches();
			foreach($batches as $batch_info)
			{
				$sync_time=$this->_cron_breath($sync_time);				
				$sms_batch=$this->general_model->cron_get_sms_batch($batch_info['batch_id']);
				$sync_time=$this->_cron_breath($sync_time);

				if(!empty($sms_batch))$this->general_model->send_sms_batch($sms_batch,$configs);
			}
			echo "<br/>--- CRON PROCESS COMPLETED: $cron_run_count ---<br/>";
			$sync_time=$this->_cron_breath($sync_time);
			//if($cron_run_count>1)break;
			if(empty($batches))sleep(5);
		}
	}	
	  
	function run_reseller_cron()
	{		
		set_time_limit(0);
		ignore_user_abort(true);
		
		$this->db->query("SET SESSION group_concat_max_len=10000000");
		$sql="SELECT GROUP_CONCAT(user_id) user_ids FROM "._DB_PREFIX_."users WHERE reseller_account=1 AND last_surety_updated+INTERVAL 30 DAY < NOW()";

		$query=$this->db->query($sql);
		$row=$query->row();
		if(!empty($row->user_ids)){
			$user_ids=$row->user_ids;
			$reseller_min_sales=$this->general_model->get_reseller_min_sales();
			$sql="SELECT user_id,SUM(units) sum_units FROM "._DB_PREFIX_."sms_log WHERE user_id IN ($user_ids) GROUP BY user_id HAVING sum_units<$reseller_min_sales";
			$query=$this->db->query($sql);
			$result=$query->result();
			$user_ids=array();
			foreach($result as $row)$user_ids[]=$row->user_id;
			$user_id_str=implode(',',$user_ids);
			$this->db->query("UPDATE  "._DB_PREFIX_."users SET owing_surety=1 WHERE user_id IN ($user_id_str)");
			$this->db->query("UPDATE  "._DB_PREFIX_."sub_accounts SET owing_surety=1 WHERE user_id IN ($user_id_str)");
			
			foreach($user_ids as $user_id)$this->general_model->refill_surety_units($user_id);
		}
	}
	
	
	function __simulate_cron() //not used anywhere yet.
	{
		ignore_user_abort(true);
		set_time_limit(0);		
		$interval=60*15; 		
		do{
			//todo: add a rountine to be performed here; implement a break condition
			$this->run_cron();
			sleep($interval);			
		} while(true);
	}

}