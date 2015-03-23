<?php

class BuildOptions
{
	private $base_ver;
	private $type; //NONE, DEFAULT, IMPORT, INPUT, BUILD
	private $batch_id;
	private $validated = FALSE;


	private $vals = array(
			'OptionVersion' => OPTION_VERSION,
			'PHPVersion' => '',
			'ExtraPathEnv' => '',
			'InstallPath' => '',
			'CompilerFlags' => '',
			'ConfigParam' => '',
			'AddOnSuhosin' => FALSE,
			'AddOnMailHeader' => FALSE,
			'AddOnAPC' => FALSE,
			'AddOnXCache' => FALSE,
			'AddOnMemCache' => FALSE,
			'AddOnMemCached' => FALSE,
			'AddonOPcache' => FALSE
	);


	function __construct($version="")
	{
		if ( $version != "" && !$this->setVersion($version)) {
			return NULL;
		}
		$this->type = 'NONE';
		$this->batch_id = ''. time() . '.' . rand(1,9);
	}

	function SetValue($name, $val)
	{
		$this->vals[$name] = $val;
	}

	function GetValue($name)
	{
		return $this->vals[$name];
	}

	function GetBatchId()
	{
		return $this->batch_id;
	}

	function SetType($optionsType)
	{
		$this->type = $optionsType;
	}

	function GetType()
	{
		return $this->type;
	}

	function IsValidated()
	{
		return $this->validated;
	}

	function SetValidated($isValid)
	{
		$this->validated = $isValid;
	}

	function setVersion($version)
	{
		$base = substr($version, 0, strpos($version, '.'));
		if(!in_array($version, BuildConfig::GetVersion(BuildConfig::PHP_VERSION))) {
			return FALSE;
		}
		$this->base_ver = $base;
		$this->vals['PHPVersion'] = $version;
		return TRUE;
	}

	function setDefaultOptions()
	{
		$params = BuildConfig::Get(BuildConfig::DEFAULT_PARAMS);

		$this->vals['ExtraPathEnv'] = '';
		$this->vals['InstallPath'] = BuildConfig::Get(BuildConfig::DEFAULT_INSTALL_DIR) . $this->base_ver;
		$this->vals['CompilerFlags'] = '';
		$this->vals['ConfigParam'] = $params[$this->base_ver];
		$this->vals['AddOnSuhosin'] = FALSE;
		$this->vals['AddOnMailHeader'] = FALSE;
		$this->vals['AddOnAPC'] = FALSE;
		$this->vals['AddOnXCache'] = FALSE;
		$this->vals['AddOnMemCache'] = FALSE;
		$this->vals['AddOnMemCached'] = FALSE;
		$this->vals['AddOnOPcache'] = FALSE;

		$this->type = 'DEFAULT';
		$this->validated = TRUE;
	}

	function getSavedOptions()
	{
		$filename = BuildConfig::Get(BuildConfig::LAST_CONF) . $this->base_ver . '.options2';
		if (file_exists($filename)) {
			$str = file_get_contents($filename);
			if ($str != '') {
				$vals = unserialize($str);
				$saved_options = new BuildOptions($vals['PHPVersion']);
				$saved_options->type = 'IMPORT';
				$saved_options->vals = $vals;
				return $saved_options;
			}
		}
		return NULL;


	}

	public function SaveOptions()
	{
		if (!$this->validated) {
			return FALSE;
		}

		$saved_val = $this->vals;

		$saved_val['ConfigParam'] = trim(preg_replace("/ ?'--(prefix=)[^ ]*' */", ' ', $saved_val['ConfigParam']));

		$serialized_str = serialize($saved_val);

		$filename = BuildConfig::Get(BuildConfig::LAST_CONF) . $this->base_ver . '.options2';
		return file_put_contents($filename, $serialized_str);
	}

	public function gen_loadconf_onclick($method)
	{
		if ($this->GetType() != $method) {
			return 'disabled';
		}
		$params = str_replace("'", "\\'", $this->vals['ConfigParam']) ;
		$flags = $this->vals['CompilerFlags'];
		if ($flags != '') {
			$flags = str_replace("'", "\\'", $flags) ;
		}
		$addon_suhosin = $this->vals['AddOnSuhosin'] ? 'true':'false';
		$addon_mailHeader = $this->vals['AddOnMailHeader'] ? 'true':'false';
		$addon_apc = $this->vals['AddOnAPC'] ? 'true':'false';
		$addon_xcache = $this->vals['AddOnXCache'] ? 'true':'false';
		$addon_memcache = $this->vals['AddOnMemCache'] ? 'true':'false';
		$addon_opcache = $this->vals['AddOnOPcache'] ? 'true':'false';

		$loc = 'document.buildform';
		$buf = "onClick=\"$loc.path_env.value='{$this->vals['ExtraPathEnv']}';
		$loc.installPath.value = '{$this->vals['InstallPath']}';
		$loc.compilerFlags.value = '$flags';
		$loc.configureParams.value = '$params';
                if ($loc.addonMailHeader != null)
                    $loc.addonMailHeader.checked = $addon_mailHeader;
                if ($loc.addonAPC != null)
                    $loc.addonAPC.checked = $addon_apc;
		$loc.addonXCache.checked = $addon_xcache;
		$loc.addonMemCache.checked = $addon_memcache;
                if ($loc.addonOPcache != null)
                    $loc.addonOPcache.checked = $addon_opcache;
		if ($loc.addonSuhosin != null)
			$loc.addonSuhosin.checked = $addon_suhosin;
		\"";

		return $buf;
	}
}


class BuildCheck
{
	private $cur_step;
	private $next_step = 0;
	public $pass_val = array();

	function __construct()
	{
		$this->cur_step = UIBase::GrabInput('ANY',"curstep");
		$this->validate_step();
	}

	private function validate_step()
	{
		if ($this->cur_step == '') {
			$this->next_step = 1;
		}
		elseif ($this->cur_step == '1') {
			$this->validate_step1();
		}
		elseif ($this->cur_step == '2') {
			$this->validate_step2();
		}
		elseif ($this->cur_step == '3') {
			$this->validate_step3();
		}
		//else illegal

	}

	public function GetNextStep()
	{
		return $this->next_step;
	}

	public function GetCurrentStep()
	{
		return $this->cur_step;
	}

    public function GetModuleSupport($php_version)
	{
        $modules = array();
        $v = substr($php_version, 0, 4);

        $modules['suhosin'] = in_array($v, array('5.4.','5.5.','5.6.'));

        $modules['apc'] = in_array($v, array('4.4.', '5.1.', '5.2.', '5.3.', '5.4.')); // apc is supported up to 5.4.

        $modules['opcache'] = in_array($v, array('5.2.', '5.3.', '5.4.'));   // opcache is built-in since 5.5

        $modules['mailheader'] = in_array($v, array('4.4.', '5.1.', '5.2.', '5.3.', '5.4.', '5.5'));

        return $modules;
	}

	private function validate_step1()
	{
		$selversion = UIBase::GrabInput('post', 'phpversel');
		if ($this->validate_php_version($selversion))
			$this->pass_val['php_version'] = $selversion;

		//bash mesg
		$OS=`uname`;
		if ( strpos($OS,'FreeBSD') !== FALSE )	{
			if (!file_exists('/bin/bash') && !file_exists('/usr/bin/bash') && !file_exists('/usr/local/bin/bash')) {
				$this->pass_val['err'] = DMsg::Err('buildphp_errnobash');
			}
		}

		if (isset($this->pass_val['err'])) {
			$this->next_step = 1;
			return false;
		}
		else {
			$this->next_step = 2;
			return true;
		}

	}

	private function validate_step2()
	{
		$next = UIBase::GrabInput('ANY','next');
		if ($next == 0) {
			$this->next_step = 1;
			return TRUE;
		}
		$php_version = UIBase::GrabGoodInput('ANY','buildver');

		// only if illegal action, will have err
		if ( !$this->validate_php_version($php_version) ) {
			$this->next_step = 0;
			return FALSE;
		}
		$this->pass_val['php_version'] = $php_version;

		$options = new BuildOptions($php_version);

		$options->SetValue('ExtraPathEnv', UIBase::GrabGoodInput('ANY','path_env'));
		$options->SetValue('InstallPath', UIBase::GrabGoodInput('ANY','installPath'));
		$compilerFlags = UIBase::GrabGoodInput('ANY','compilerFlags');
		$configParams = UIBase::GrabGoodInput('ANY','configureParams');
		//set the input even it has error, so user can modify
		$options->SetValue('ConfigParam', $configParams);
		$options->SetValue('CompilerFlags', $compilerFlags);

		$options->SetValue('AddOnSuhosin', (NULL != UIBase::GrabGoodInput('ANY','addonSuhosin')));
		$options->SetValue('AddOnMailHeader',  (NULL != UIBase::GrabGoodInput('ANY','addonMailHeader')));
		$options->SetValue('AddOnAPC', (NULL != UIBase::GrabGoodInput('ANY','addonAPC')));
		$options->SetValue('AddOnXCache', (NULL != UIBase::GrabGoodInput('ANY','addonXCache')));
		$options->SetValue('AddOnMemCache', (NULL != UIBase::GrabGoodInput('ANY','addonMemCache')));
		$options->SetValue('AddOnMemCached', (NULL != UIBase::GrabGoodInput('ANY','addonMemCached')));
		$options->SetValue('AddOnOPcache', (NULL != UIBase::GrabGoodInput('ANY','addonOPcache')));

		// can be real input err
		$v1 = $this->validate_extra_path_env($options->GetValue('ExtraPathEnv'));
		$v2 = $this->validate_install_path($options->GetValue('InstallPath'));
		$v3 = $this->validate_complier_flags($compilerFlags);
		$v4 = $this->validate_config_params($configParams);

		if (!$v1 || !$v2 || !$v3 || !$v4) {
			$options->SetType('INPUT');

			$options->SetValidated(FALSE);
			$this->pass_val['input_options'] = $options;
			$this->next_step = 2;
			return FALSE;
		}

		if (strpos($configParams, '--with-litespeed') === FALSE) {
			$configParams .= " '--with-litespeed'";
		}

		$configParams = "'--prefix=" . $options->GetValue('InstallPath') . "' " . $configParams;
		$options->SetValue('ConfigParam', escapeshellcmd($configParams));
		$options->SetValue('CompilerFlags', escapeshellcmd($compilerFlags));


		$options->SetType('BUILD');
		$options->SetValidated(TRUE);
		$this->pass_val['build_options'] = $options;
		$this->next_step = 3;
		return TRUE;
	}

	private function validate_step3()
	{
		$php_version = UIBase::GrabGoodInput('ANY', 'buildver');
		if ($php_version == '') {
			echo "missing php_version";
			return FALSE;
		}

		$this->pass_val['php_version'] = $php_version;

		$next = UIBase::GrabInput('ANY','next');
		if ($next == 0) {
			$this->next_step = 2;
			return TRUE;
		}

		if (!isset($_SESSION['progress_file'])) {
			echo "missing progress file";
			return FALSE;
		}
		$progress_file = $_SESSION['progress_file'];

		if (!isset($_SESSION['log_file'])) {
			echo "missing log file";
			return FALSE;
		}
		$log_file = $_SESSION['log_file'];
		if (!file_exists($log_file)) {
			echo "logfile does not exist";
			return FALSE;
		}

		$manual_script = UIBase::GrabGoodInput('ANY','manual_script');
		if ($manual_script == '' || !file_exists($manual_script)) {
			echo "missing manual script";
			return FALSE;
		}

		$this->pass_val['progress_file'] = $progress_file;
		$this->pass_val['log_file'] = $log_file;
		$this->pass_val['manual_script'] = $manual_script;
		$this->pass_val['extentions'] = UIBase::GrabGoodInput('ANY', 'extentions');

		$this->next_step = 4;
		return TRUE;
	}


	private function validate_php_version($version)
	{
		$PHP_VER = BuildConfig::GetVersion(BuildConfig::PHP_VERSION);
		if (in_array($version, $PHP_VER)) {
			return true;
		}
		else {
			$this->pass_val['err'] = 'Illegal';
			return false;
		}
	}

	private function validate_extra_path_env($extra_path_env)
	{
		if ($extra_path_env === '') {
			return TRUE;
		}
		$envp = preg_split("/:/", $extra_path_env);
		foreach ($envp as $p) {
			if (!is_dir($p)) {
				$this->pass_val['err']['path_env'] = DMsg::Err('err_invalidpath') . $p;
				return FALSE;
			}
		}
		$extra_path_env .= ':';
		return TRUE;
	}

	private function validate_install_path($path)
	{
		$path = PathTool::clean($path);
		if ($path == '') {
			$this->pass_val['err']['installPath'] = DMsg::Err('err_valcannotempty');
			return FALSE;
		}
		if ($path[0] != '/') {
			$this->pass_val['err']['installPath'] = DMsg::Err('err_requireabspath');
			return FALSE;
		}

		if (preg_match('/([;&"|#$?`])/', $path)) {
			$this->pass_val['err']['installPath'] = DMsg::Err('err_illegalcharfound');
			return FALSE;
		}

		//parent exists.
		if (!is_dir($path)) {
			if (is_file($path)) {
				$this->pass_val['err']['installPath'] = DMsg::Err('err_invalidpath');
				return FALSE;
			}
			$testpath = dirname($path);
			if (!is_dir($testpath)) {
				$this->pass_val['err']['installPath'] = DMsg::Err('err_parentdirnotexist');
				return FALSE;
			}
		}
		else {
			$testpath = $path;
		}

		if ($testpath == '.' || $testpath == '/'
		|| PathTool::isDenied($testpath)) {
			$this->pass_val['err']['installPath'] = 'Illegal location';
			return FALSE;
		}

		return TRUE;
	}

	private function validate_complier_flags(&$cflags)
	{
		if ($cflags === '')
			return TRUE;

		if (preg_match('/([;&"|#$?`])/', $cflags)) {
			if (strpos($cflags, '"') !== FALSE)
				$this->pass_val['err']['compilerFlags'] = DMsg::Err('buildphp_errquotes');
			else
				$this->pass_val['err']['compilerFlags'] = DMsg::Err('err_illegalcharfound');
			return FALSE;
		}

		// split array
		$flag = array();
		$a = str_replace("\n", ' ', $cflags);
		$a = trim($a) . ' '; // need trailing space to match
		$FLAGS = 'CFLAGS|CPPFLAGS|CXXFLAGS|LDFLAGS';
		while (strlen($a) > 0)
		{
		    $m = NULL;
		    if (preg_match("/^($FLAGS)=[^'^\"^ ]+\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    elseif (preg_match("/^($FLAGS)='[^'^\"]+'\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    if ($m != NULL) {
				$a = substr($a, strlen($m));
				$flag[] = rtrim($m);
		    }
		    else {
		    	$pe = $a;
		    	$ipos = strpos($pe, ' ');
		    	if ( $ipos !== FALSE) {
		    		$pe = substr($a, 0, $ipos);
		    	}
				$this->pass_val['err']['compilerFlags'] = DMsg::Err('err_invalidvalat') . $pe;
				return FALSE;
		    }
		}
		if (!empty($flag)) {
		    $cflags = implode(' ', $flag);
		}
		else
			$cflags = '';
		return TRUE;

	}

	private function validate_config_params(&$config_params)
	{
		if (preg_match('/([;&"|#$?`])/', $config_params)) {
			if (strpos($config_params, '"') !== FALSE)
				$this->pass_val['err']['configureParams'] = DMsg::Err('buildphp_errquotes');
			else
				$this->pass_val['err']['configureParams'] = DMsg::Err('err_illegalcharfound');
			return FALSE;
		}

		// split array
		$params = array();
		$a = str_replace("\n", ' ', $config_params);
		$a = trim($a) . ' ';
		while (strlen($a) > 0)
		{
		    $m = NULL;
		    if (preg_match("/^'--[a-zA-Z_\-0-9]+=[^=^'^;]+'\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    elseif (preg_match("/^'--[a-zA-Z_\-0-9]+'\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    elseif (preg_match("/^--[a-zA-Z_\-0-9]+=[^=^'^;^ ]+\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    elseif (preg_match("/^--[a-zA-Z_\-0-9]+\s+/", $a, $matches)) {
				$m = $matches[0];
		    }
		    if ($m != NULL) {
				$a = substr($a, strlen($m));
				// ignore unused options
				// '--prefix=/usr/local'
				// '--with-apxs2=/usr/local/apache/bin/apxs' '--with-apxs=/usr/local/apache/bin/apxs' '--with-apxs2'
				// '--enable-fastcgi'
				if (!preg_match( "/(--prefix=)|(--with-apxs)|(--enable-fastcgi)/", $m)) {
					$m = trim(rtrim($m), "'");
					$params[] = "'$m'";
				}
		    }
		    else {
		    	$pe = $a;
		    	$ipos = strpos($pe, ' ');
		    	if ( $ipos !== FALSE) {
		    		$pe = substr($a, 0, $ipos);
		    	}
				$this->pass_val['err']['configureParams'] = DMsg::Err('err_invalidvalat') . $pe;
				return FALSE;
		    }
		}

		if (empty($params)) {
			$this->pass_val['err']['configureParams'] = DMsg::Err('err_valcannotempty');
			return FALSE;
		}

		$options = implode(' ', $params);

		$config_params = $options;
		return TRUE;
	}


}

class BuildTool
{
	var $options = NULL;
	var $ext_options = array();
	var $dlmethod;
	var $progress_file;
	var $log_file;
	var $extension_used;

	var $build_prepare_script = NULL;
	var $build_install_script = NULL;
	var $build_manual_run_script = NULL;

	function __construct($input_options)
	{
		if ($input_options == NULL || !$input_options->IsValidated()) {
			return NULL;
		}
		$this->options = $input_options;
	}

	function init(&$error, &$optionsaved)
	{
		$optionsaved = $this->options->SaveOptions();
		$BUILD_DIR = BuildConfig::Get(BuildConfig::BUILD_DIR);

		$this->progress_file = $BUILD_DIR . '/buildphp_' . $this->options->GetBatchId() . '.progress';
		$this->log_file = $BUILD_DIR . '/buildphp_' . $this->options->GetBatchId() . '.log';
		$this->build_prepare_script = $BUILD_DIR . '/buildphp_' . $this->options->GetBatchId() . '.prep.sh';
		$this->build_install_script = $BUILD_DIR . '/buildphp_' . $this->options->GetBatchId() . '.install.sh';
		$this->build_manual_run_script = $BUILD_DIR . '/buildphp_manual_run.sh';

		if (file_exists($this->progress_file)) {
			$error = DMsg::Err('buildphp_errinprogress');
			return FALSE;
		}

		if (!$this->detectDownloadMethod()) {
			$error = DMsg::Err('err_faildetectdlmethod');
			return FALSE;
		}

		$this->initDownloadUrl();
		return TRUE;
	}

	function detectDownloadMethod()
	{
		$OS=`uname`;
		$dlmethod = ''; // dlmethod $output $url
		if ( strpos($OS,'FreeBSD') !== FALSE )
		{
			if ((exec('PATH=$path_env:/bin:/usr/bin:/usr/local/bin fetch', $o,$status)||1) && $status <= 1) {
				$dlmethod = "fetch -o"; // status is 127 if not found
			}
		}
		if ( strpos($OS,'SunOS') !== FALSE ) // for SunOS, status is 1, so use return string
		{
			if ( exec('PATH=$path_env:/bin:/usr/bin:/usr/local/bin curl', $o, $status) != '') {
				$dlmethod = "curl -L -o";
			}
			elseif ( exec('PATH=$path_env:/bin:/usr/bin:/usr/local/bin wget', $o, $status) != '') {
				$dlmethod = "wget -nv -O";
			}
		}

		if ( $dlmethod == '' )
		{
			if ( (exec('PATH=$path_env:/bin:/usr/bin:/usr/local/bin curl', $o, $status)||1) && $status <= 2) {
				$dlmethod = "curl -L -o";
			}
			elseif ((exec( 'PATH=$path_env:/bin:/usr/bin:/usr/local/bin wget', $o, $status)|| 1) && $status <= 2 ) {
				$dlmethod = "wget -nv -O";
			}
			else {
				return FALSE;
			}
		}
		$this->dlmethod = $dlmethod;
		return TRUE;
	}

	function initDownloadUrl()
	{
		$php_version = $this->options->GetValue('PHPVersion');

		// extension
		$ext = array('{EXTENSION_NAME}' => 'Suhosin');
		$ver = 'suhosin-'. BuildConfig::GetVersion(BuildConfig::SUHOSIN_VERSION);
		$ext['{EXTENSION_DIR}'] = $ver;
		$ext['{EXTENSION_SRC}'] = $ver .'.tar.gz';
		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://download.suhosin.org/' . $ver . '.tar.gz';
		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
		$ext['{EXTENSION_EXTRA_CONFIG}'] = '';

		$this->ext_options['Suhosin'] = $ext;

		$ext = array('{EXTENSION_NAME}' => 'APC');
		$ver = 'APC-' . BuildConfig::GetVersion(BuildConfig::APC_VERSION);
		$ext['{EXTENSION_DIR}'] = $ver;
		$ext['{EXTENSION_SRC}'] = $ver . '.tgz';
		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://centminmod.com/centminmodparts/apc/php550/'. $ver . '.tgz';
		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
		$ext['{EXTENSION_EXTRA_CONFIG}'] = '--enable-apc';

		$this->ext_options['APC'] = $ext;

		$ext = array('{EXTENSION_NAME}' => 'XCache');
		$ver = 'xcache-' . BuildConfig::GetVersion(BuildConfig::XCACHE_VERSION);
		$ext['{EXTENSION_DIR}'] = $ver;
		$ext['{EXTENSION_SRC}'] = $ver . '.tar.gz';
		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://xcache.lighttpd.net/pub/Releases/' . XCACHE_VERSION . '/' . $ver . '.tar.gz';
		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
		$ext['{EXTENSION_EXTRA_CONFIG}'] = '--enable-xcache';

		$this->ext_options['XCache'] = $ext;

		$ext = array('{EXTENSION_NAME}' => 'MemCache');
		$ver = 'memcache-' . BuildConfig::GetVersion(BuildConfig::MEMCACHE_VERSION);
		$ext['{EXTENSION_DIR}'] = $ver;
		$ext['{EXTENSION_SRC}'] = $ver . '.tgz';
		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://pecl.php.net/get/'. $ver . '.tgz';
		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
		$ext['{EXTENSION_EXTRA_CONFIG}'] = '--enable-memcache';

		$this->ext_options['MemCache'] = $ext;

//		$ext = array('{EXTENSION_NAME}' => 'MemCached');
//		$ver = 'memcached-' . MEMCACHED_VERSION;
//		$ext['{EXTENSION_DIR}'] = $ver;
//		$ext['{EXTENSION_SRC}'] = $ver . '.tgz';
//		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://pecl.php.net/get/'. $ver . '.tgz';
//		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
//		$ext['{EXTENSION_EXTRA_CONFIG}'] = '--enable-memcached';
//
//		$this->ext_options['MemCached'] = $ext;

		$ext = array('{EXTENSION_NAME}' => 'OPcache');
		$ver = 'zendopcache-' . BuildConfig::GetVersion(BuildConfig::OPCACHE_VERSION);
		$ext['{EXTENSION_DIR}'] = $ver;
		$ext['{EXTENSION_SRC}'] = $ver . '.tgz';
		$ext['{EXTENSION_DOWNLOAD_URL}'] = 'http://pecl.php.net/get/'. $ver . '.tgz';
		$ext['{EXTRACT_METHOD}'] = 'tar -zxf';
		$ext['{EXTENSION_EXTRA_CONFIG}'] = '--enable-opcache';

		$this->ext_options['OPcache'] = $ext;
	}

	public static function getExtensionNotes($extensions)
	{
		$ocname = array();
		if (strpos($extensions, 'APC') !== FALSE) {
			$ocname[] = 'APC';
		}
		if (strpos($extensions, 'XCache') !== FALSE) {
			$ocname[] = 'XCache';
		}
		if (strpos($extensions, 'OPcache') !== FALSE) {
			$ocname[] = 'OPcache';
		}
		if (strpos($extensions, 'Suhosin') !== FALSE) {
			$ocname[] = 'Suhosin';
		}

		if (count($ocname) == 0) {
			return '';
		}
		$notes = '<li>' . DMsg::UIStr('buildphp_enableextnote') . '<br />';
		$notes1 = '';
		foreach($ocname as $ocn) {

			if ($ocn == 'Suhosin') {
				$notes1 .= '
;				=================
;				Suhosin
;				=================
extension=suhosin.so

';
			}

			if ($ocn == 'APC') {
				$notes1 .= '
;				=================
;				APC
;				=================
				extension=apc.so

				';
			}

			if ($ocn == 'XCache') {
				$notes1 .= '
;				=================
;				XCache
;				=================
				extension=xcache.so

				';
			}

			if ($ocn == 'OPcache') {
				$notes1 .= '
;				=================
;				Zend OPcache
;				=================
				zend_extension=opcache.so

opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
opcache.enable_cli=1

				';
			}
		}
		$notes .= nl2br($notes1);
		$notes .= '</li>';

		return $notes;
	}


	public function GenerateScript(&$error, &$optionsaved)
	{
		if ($this->progress_file == NULL) {
			if (!$this->init($error, $optionsaved)) {
				return FALSE;
			}
		}
		$params = array();
		$params['{PHP_VERSION}'] = $this->options->GetValue('PHPVersion');
		$params['{PROGRESS_F}'] = $this->progress_file;
		$params['{LOG_FILE}'] = $this->log_file;
        $processUser = posix_getpwuid(posix_geteuid());
        $gidinfo = posix_getgrgid($processUser['gid']);
		$params['{PHP_USR}'] = $processUser['name'];
        $params['{PHP_USRGROUP}'] = $gidinfo['name'];
		$params['{EXTRA_PATH_ENV}'] = $this->options->GetValue('ExtraPathEnv');
		$params['{PHP_BUILD_DIR}'] = BuildConfig::Get(BuildConfig::BUILD_DIR);
		$params['{DL_METHOD}'] = $this->dlmethod;
		$params['{INSTALL_DIR}'] = $this->options->GetValue('InstallPath');
		$params['{COMPILER_FLAGS}'] = $this->options->GetValue('CompilerFlags');
		$params['{ENABLE_MAILHEADER}'] = ($this->options->GetValue('AddOnMailHeader')) ? 1 : 0;
		$params['{LSAPI_VERSION}'] = BuildConfig::GetVersion(BuildConfig::LSAPI_VERSION);
		$params['{PHP_CONF_OPTIONS}'] = $this->options->GetValue('ConfigParam');
		$params['{LSWS_HOME}'] = SERVER_ROOT;
		$params['{INSTALL_SCRIPT}'] = $this->build_install_script;

		$search = array_keys($params);
		$replace = array_values($params);

		//common header
		$template_file = 'build_common.template';
		$template = file_get_contents($template_file, true);
		if ($template === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file;
			return false;
		}
		$template_script = str_replace($search, $replace, $template);
		$prepare_script = $template_script;
		$install_script = $template_script;


		// prepare php
		$template_file = 'build_prepare.template';
		$template = file_get_contents($template_file, true);
		if ($template === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file;
			return false;
		}
		$template_script = str_replace($search, $replace, $template);
		$prepare_script .= $template_script;

		// install php
		$template_file2 = 'build_install.template';
		$template2 = file_get_contents($template_file2, true);
		if ($template2 === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file2;
			return false;
		}
		$template_script2 = str_replace($search, $replace, $template2);
		$install_script .= $template_script2;

		//prepare extension
		$template_file = 'build_prepare_ext.template';
		$template = file_get_contents($template_file, true);
		if ($template === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file;
			return false;
		}

		//install extension
		$template_file2 = 'build_install_ext.template';
		$template2 = file_get_contents($template_file2, true);
		if ($template2 === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file2;
			return false;
		}

		$extList = array();
		if ($this->options->GetValue('AddOnSuhosin')) {
			$extList[] = 'Suhosin';
		}
		if ($this->options->GetValue('AddOnAPC')) {
			$extList[] = 'APC';
		}
		if ($this->options->GetValue('AddOnXCache')) {
			$extList[] = 'XCache';
		}
		if ($this->options->GetValue('AddOnMemCache')) {
			$extList[] = 'MemCache';
		}
//		if ($this->options->GetValue('AddOnMemCached')) {
//			$extList[] = 'MemCached';
//		}
		if ($this->options->GetValue('AddOnOPcache')) {
			$extList[] = 'OPcache';
		}
		foreach ($extList as $extName) {
			$newparams = array_merge($params, $this->ext_options[$extName]);
			$search = array_keys($newparams);
			$replace = array_values($newparams);
			$template_script = str_replace($search, $replace, $template);
			$prepare_script .= $template_script;
			$template_script2 = str_replace($search, $replace, $template2);
			$install_script .= $template_script2;
		}
		$this->extension_used = implode('.', $extList);

		$prepare_script .= 'main_msg "**DONE**"' . "\n";
		$install_script .= 'main_msg "**DONE**"' . "\n";

		if ( file_put_contents($this->build_prepare_script, $prepare_script) === FALSE) {

			$error = DMsg::Err('buildphp_errcreatescript') . $this->build_prepare_script;
			return false;
		}

		if ( chmod($this->build_prepare_script, 0700) == FALSE) {
			$error = DMsg::Err('buildphp_errchmod') . $this->build_prepare_script;
			return false;
		}

		if ( file_put_contents($this->build_install_script, $install_script) === FALSE) {

			$error = DMsg::Err('buildphp_errcreatescript') . $this->build_install_script;
			return false;
		}

		if ( chmod($this->build_install_script, 0700) == FALSE) {
			$error = DMsg::Err('buildphp_errchmod') . $this->build_install_script;
			return false;
		}

		// final manual run script
		$template_file = 'build_manual_run.template';
		$template = file_get_contents($template_file, true);
		if ($template === false) {
			$error = DMsg::Err('err_failreadfile') . $template_file;
			return false;
		}
		$template_script = str_replace($search, $replace, $template);
		if ( file_put_contents($this->build_manual_run_script, $template_script) === FALSE) {

			$error = DMsg::Err('buildphp_errcreatescript') . $this->build_manual_run_script;
			return false;
		}

		if ( chmod($this->build_manual_run_script, 0700) == FALSE) {
			$error = DMsg::Err('buildphp_errchmod') . $this->build_manual_run_script;
			return false;
		}


		return true;
	}


}

